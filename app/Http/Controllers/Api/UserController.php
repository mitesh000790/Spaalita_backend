<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CastException;
use App\Http\Controllers\Concerns\MapsRequestFields;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserAddressesRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\NewsletterService;
use App\Support\Js;
use App\Support\Like;
use App\Support\Pagination;
use App\Support\TypeCaster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class UserController extends Controller
{
    use MapsRequestFields;

    /**
     * Request field => [column, setters] for the editable profile fields.
     */
    private const PROFILE_FIELDS = [
        'userName' => ['user_name', ['trim' => true]],
        'firstName' => ['first_name', ['trim' => true]],
        'lastName' => ['last_name', ['trim' => true]],
        'email' => ['email', ['lowercase' => true]],
        'phone' => ['phone', ['trim' => true]],
        'country' => ['country', ['trim' => true]],
        'city' => ['city', ['trim' => true]],
        'address' => ['address', ['trim' => true]],
        'postalCode' => ['postal_code', ['trim' => true]],
        'role' => ['role'],
        'status' => ['status'],
    ];

    public function __construct(private readonly NewsletterService $newsletter) {}

    /**
     * GET /api/users
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $pagination = Pagination::lenient($request);
            $search = (string) $request->query('search', '');

            $query = User::query()->notInactive();

            if (trim($search) !== '') {
                $pattern = Like::contains($search);
                $query->where(fn (Builder $query) => $query
                    ->where('user_name', 'like', $pattern)
                    ->orWhere('first_name', 'like', $pattern)
                    ->orWhere('last_name', 'like', $pattern)
                    ->orWhere('email', 'like', $pattern)
                    ->orWhere('role', 'like', $pattern));
            }

            $total = $query->count();
            $users = $pagination->apply($query->with(['billingAddress', 'shippingAddress'])->newestFirst())->get();

            return response()->json([
                'success' => true,
                'data' => UserResource::collection($users),
                'pagination' => [
                    'total' => $total,
                    'page' => $pagination->page,
                    'limit' => $pagination->limit,
                    'totalPages' => $pagination->pages($total),
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->serverError('Error fetching users', $exception);
        }
    }

    /**
     * POST /api/users (multipart: optional "profilePicture" image)
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        try {
            $attributes = $this->stringAttributes($request, self::PROFILE_FIELDS);

            $exists = User::query()
                ->where(fn (Builder $query) => $query
                    ->where('user_name', $attributes['user_name'])
                    ->orWhere('email', $attributes['email']))
                ->exists();

            if ($exists) {
                return response()->json(['success' => false, 'message' => 'User name or email already exists'], 409);
            }

            $uploaded = $request->attributes->get('uploaded_file');

            $user = new User(array_filter([
                ...$attributes,
                'password' => Hash::make((string) $request->input('password')),
            ], fn ($value, $column) => $value !== null || ! in_array($column, ['role', 'status'], true), ARRAY_FILTER_USE_BOTH));
            $user->profile_picture = $uploaded !== null ? "/uploads/userProfile/{$uploaded}" : null;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => new UserResource($user),
            ], 201);
        } catch (Throwable $exception) {
            return $this->serverError('Error creating user', $exception);
        }
    }

    /**
     * GET /api/users/{id}  (admin, or the customer's own account)
     */
    public function show(Request $request, string $id): JsonResponse
    {
        if (! $this->canManage($request, $id)) {
            return $this->forbidden();
        }

        try {
            $user = User::query()->with(['billingAddress', 'shippingAddress'])->find($id);

            if ($user === null) {
                return response()->json(['success' => false, 'message' => 'User not found'], 404);
            }

            return response()->json(['success' => true, 'data' => new UserResource($user)]);
        } catch (Throwable $exception) {
            return $this->serverError('Error fetching user', $exception);
        }
    }

    /**
     * PUT /api/users/{id} (multipart: optional "profilePicture" image)
     *
     * Admin, or the customer's own account. Only the fields present in the
     * request are changed; role and status can only be changed by an admin.
     *
     * Optional: newsletterOption ("subscribe" | "unsubscribe" | "orderUpdates"),
     * and passwordNew + passwordConfirm (+ passwordCurrent for customers).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        if (! $this->canManage($request, $id)) {
            return $this->forbidden();
        }

        try {
            $byAdmin = $request->user()->isAdmin();
            $fields = $byAdmin ? self::PROFILE_FIELDS : array_diff_key(self::PROFILE_FIELDS, array_flip(['role', 'status']));
            $attributes = $this->stringAttributes($request, $fields, onlyPresent: true);
            $uploaded = $request->attributes->get('uploaded_file');

            if ($uploaded !== null) {
                $attributes['profile_picture'] = "/uploads/userProfile/{$uploaded}";
            }

            $newsletterOption = $request->input('newsletterOption');

            if (Js::truthy($newsletterOption)) {
                if (! in_array($newsletterOption, User::NEWSLETTER_OPTIONS, true)) {
                    return response()->json(['success' => false, 'message' => 'Invalid newsletter option'], 400);
                }

                $attributes['newsletter_option'] = $newsletterOption;
            }

            $user = User::query()->with(['billingAddress', 'shippingAddress'])->find($id);

            if ($user === null) {
                return response()->json(['success' => false, 'message' => 'User not found'], 404);
            }

            if (Js::truthy($request->input('passwordNew'))) {
                $passwordError = $this->passwordChangeError($request, $user, $byAdmin);

                if ($passwordError !== null) {
                    return response()->json(['success' => false, 'message' => $passwordError], 400);
                }

                $attributes['password'] = Hash::make($request->input('passwordNew'));
            }

            DB::transaction(function () use ($user, $attributes, $newsletterOption) {
                $user->applyUpdate($attributes);

                if (isset($attributes['newsletter_option'])) {
                    $this->newsletter->applyUserOption($user, $newsletterOption);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => new UserResource($user),
            ]);
        } catch (Throwable $exception) {
            return $this->serverError('Error updating user', $exception);
        }
    }

    /**
     * PUT /api/users/{id}/addresses  (admin, or the customer's own account)
     *
     * Each address sent replaces the saved one entirely.
     */
    public function updateAddresses(UpdateUserAddressesRequest $request, string $id): JsonResponse
    {
        if (! $this->canManage($request, $id)) {
            return $this->forbidden();
        }

        try {
            $addresses = [];

            foreach ([UserAddress::TYPE_BILLING => 'billing', UserAddress::TYPE_SHIPPING => 'shipping'] as $type => $field) {
                if (Js::truthy($request->input($field))) {
                    $addresses[$type] = $this->addressAttributes($request->input($field), $type, $field);
                }
            }

            $user = User::query()->find($id);

            if ($user === null) {
                return response()->json(['success' => false, 'message' => 'User not found'], 404);
            }

            DB::transaction(function () use ($user, $addresses) {
                foreach ($addresses as $type => $attributes) {
                    $user->addresses()->where('type', $type)->delete();
                    $user->addresses()->create(['type' => $type] + $attributes);
                }

                $user->applyUpdate([]);
            });

            return response()->json([
                'success' => true,
                'message' => 'User addresses updated successfully',
                'data' => new UserResource($user->load(['billingAddress', 'shippingAddress'])),
            ]);
        } catch (Throwable $exception) {
            return $this->serverError('Error updating addresses', $exception);
        }
    }

    /**
     * DELETE /api/users/{id} (soft delete: status becomes "inactive")
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $user = User::query()->find($id);

            if ($user === null) {
                return response()->json(['success' => false, 'message' => 'User not found'], 404);
            }

            $user->applyUpdate(['status' => 'inactive']);

            return response()->json(['success' => true, 'message' => 'User deleted SuccessFully']);
        } catch (Throwable $exception) {
            return $this->serverError('Error deleting user', $exception);
        }
    }

    /**
     * Admins manage every account; customers only their own.
     */
    private function canManage(Request $request, string $id): bool
    {
        $caller = $request->user();

        return $caller !== null && ($caller->isAdmin() || strcasecmp((string) $caller->id, $id) === 0);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'You can only access your own account'], 403);
    }

    /**
     * Why the requested password change cannot be made, or null when it can.
     * Customers must confirm their current password; admins may set a new one directly.
     */
    private function passwordChangeError(Request $request, User $user, bool $byAdmin): ?string
    {
        $newPassword = $request->input('passwordNew');

        if (! is_string($newPassword)) {
            return 'Please provide a valid new password';
        }

        if ($request->exists('passwordConfirm') && $request->input('passwordConfirm') !== $newPassword) {
            return 'New password and confirm password do not match';
        }

        if (! $byAdmin && ! Hash::check((string) $request->input('passwordCurrent'), $user->password)) {
            return 'Current password is incorrect';
        }

        return null;
    }

    /**
     * @return array<string, string|null>
     */
    private function addressAttributes(mixed $address, string $type, string $field): array
    {
        if (! is_array($address) || array_is_list($address)) {
            throw CastException::make('Object', $address, $field);
        }

        $attributes = [];

        foreach (UserAddress::FIELDS[$type] as $key => $column) {
            $attributes[$column] = TypeCaster::string($address[$key] ?? null, "{$field}.{$key}");
        }

        return $attributes;
    }
}
