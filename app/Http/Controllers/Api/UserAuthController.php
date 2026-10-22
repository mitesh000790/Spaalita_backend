<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ForgotPasswordRequest;
use App\Http\Requests\User\UserLoginRequest;
use App\Http\Resources\LoginUserResource;
use App\Models\User;
use App\Services\JwtService;
use App\Support\TypeCaster;
use App\Support\WordPressPassword;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Throwable;

class UserAuthController extends Controller
{
    private const RESET_TOKEN_TTL_MINUTES = 15;

    public function __construct(private readonly JwtService $jwt) {}

    /**
     * POST /api/users/login
     *
     * The "email" field accepts the customer's email address or user name.
     */
    public function login(UserLoginRequest $request): JsonResponse
    {
        try {
            $email = TypeCaster::string($request->input('email'), 'email', ['lowercase' => true]);
            $userName = TypeCaster::string($request->input('email'), 'email', ['trim' => true]);

            $activeUsers = fn () => User::query()->with(['billingAddress', 'shippingAddress'])->where('status', 'active');

            $user = $activeUsers()->where('email', $email)->first()
                ?? $activeUsers()->where('user_name', $userName)->first();

            $password = (string) $request->input('password');
            $isWordPressHash = $user !== null && WordPressPassword::isWordPressHash($user->password);
            $passwordIsValid = $user !== null && ($isWordPressHash
                ? WordPressPassword::check($password, $user->password)
                : Hash::check($password, $user->password));

            if (! $passwordIsValid) {
                return response()->json(['success' => false, 'message' => 'Invalid email or password'], 401);
            }

            if ($isWordPressHash) {
                $user->password = Hash::make($password);
                $user->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'token' => $this->jwt->issueUserToken($user),
                    'user' => new LoginUserResource($user),
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->serverError('Error logging in', $exception);
        }
    }

    /**
     * POST /api/users/logout
     *
     * Tokens are stateless; the client discards its token.
     */
    public function logout(): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Logout successful']);
    }

    /**
     * POST /api/users/forgot-password
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $email = TypeCaster::string($request->input('email'), 'email', ['lowercase' => true]);
            $userName = TypeCaster::string($request->input('userName'), 'userName', ['trim' => true]);

            $user = User::query()
                ->where(fn (Builder $query) => $query->where('email', $email)->orWhere('user_name', $userName))
                ->where('status', 'active')
                ->first();

            if ($user === null) {
                return response()->json(['success' => false, 'message' => 'User not found'], 404);
            }

            $resetToken = bin2hex(random_bytes(32));

            $user->forceFill([
                'reset_password_token' => hash('sha256', $resetToken),
                'reset_password_expire' => now()->addMinutes(self::RESET_TOKEN_TTL_MINUTES),
            ])->save();

            return response()->json([
                'success' => true,
                'message' => 'Reset password link generated',
                'resetLink' => rtrim(config('app.frontend_url'), '/').'/reset-password/'.$resetToken,
            ]);
        } catch (Throwable $exception) {
            return $this->serverError('Error in forgot password', $exception);
        }
    }

    /**
     * POST /api/users/reset-password/{token}
     */
    public function resetPassword(Request $request, string $token): JsonResponse
    {
        try {
            $user = User::query()
                ->where('reset_password_token', hash('sha256', $token))
                ->where('reset_password_expire', '>', now())
                ->first();

            if ($user === null) {
                return response()->json(['success' => false, 'message' => 'Token invalid or expired'], 400);
            }

            $password = $request->input('password');

            if (! is_string($password)) {
                throw new RuntimeException('Illegal arguments: '.($password === null ? 'undefined' : get_debug_type($password)).', string');
            }

            $user->forceFill([
                'password' => Hash::make($password),
                'reset_password_token' => null,
                'reset_password_expire' => null,
            ])->save();

            return response()->json(['success' => true, 'message' => 'Password reset successful']);
        } catch (Throwable $exception) {
            return $this->serverError('Error resetting password', $exception);
        }
    }
}
