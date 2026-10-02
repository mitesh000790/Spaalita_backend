<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\MapsRequestFields;
use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Support\Js;
use App\Support\TypeCaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class RoleController extends Controller
{
    use MapsRequestFields;

    private const FIELDS = [
        'name' => ['name', ['trim' => true]],
        'description' => ['description', ['trim' => true]],
        'status' => ['status'],
    ];

    /**
     * GET /api/roles
     */
    public function index(): JsonResponse
    {
        try {
            $roles = Role::query()->orderBy('created_at')->orderBy('id')->get();

            return response()->json(['success' => true, 'data' => RoleResource::collection($roles)]);
        } catch (Throwable $exception) {
            return $this->serverError('Error fetching roles', $exception);
        }
    }

    /**
     * POST /api/roles
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        try {
            $attributes = $this->stringAttributes($request, self::FIELDS, onlyPresent: true);

            if (Role::query()->where('name', $attributes['name'])->exists()) {
                return response()->json(['success' => false, 'message' => 'Role name already exists'], 409);
            }

            $role = Role::query()->create(array_filter(
                $attributes,
                fn ($value, $column) => $value !== null || $column !== 'status',
                ARRAY_FILTER_USE_BOTH,
            ));

            return response()->json([
                'success' => true,
                'message' => 'Role created successfully',
                'data' => new RoleResource($role),
            ], 201);
        } catch (Throwable $exception) {
            return $this->serverError('Error creating role', $exception);
        }
    }

    /**
     * GET /api/roles/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $role = Role::query()->find($id);

            if ($role === null) {
                return response()->json(['success' => false, 'message' => 'Role not found'], 404);
            }

            return response()->json(['success' => true, 'data' => new RoleResource($role)]);
        } catch (Throwable $exception) {
            return $this->serverError('Error fetching role', $exception);
        }
    }

    /**
     * PUT /api/roles/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $name = $request->input('name');

            if (Js::truthy($name)) {
                $duplicate = Role::query()
                    ->where('name', TypeCaster::string($name, 'name', ['trim' => true]))
                    ->whereKeyNot($id)
                    ->exists();

                if ($duplicate) {
                    return response()->json(['success' => false, 'message' => 'Role name already exists'], 409);
                }
            }

            $attributes = $this->stringAttributes($request, self::FIELDS, onlyPresent: true);
            $role = Role::query()->find($id);

            if ($role === null) {
                return response()->json(['success' => false, 'message' => 'Role not found'], 404);
            }

            $role->applyUpdate($attributes);

            return response()->json([
                'success' => true,
                'message' => 'Role updated successfully',
                'data' => new RoleResource($role),
            ]);
        } catch (Throwable $exception) {
            return $this->serverError('Error updating role', $exception);
        }
    }

    /**
     * DELETE /api/roles/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $role = Role::query()->find($id);

            if ($role === null) {
                return response()->json(['success' => false, 'message' => 'Role not found'], 404);
            }

            $role->delete();

            return response()->json(['success' => true, 'message' => 'Role deleted successfully']);
        } catch (Throwable $exception) {
            return $this->serverError('Error deleting role', $exception);
        }
    }
}
