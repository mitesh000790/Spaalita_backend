<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Models\Admin;
use App\Services\JwtService;
use App\Support\TypeCaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Throwable;

class AdminAuthController extends Controller
{
    public function __construct(private readonly JwtService $jwt) {}

    /**
     * POST /api/admin/login
     */
    public function login(AdminLoginRequest $request): JsonResponse
    {
        try {
            $username = TypeCaster::string($request->input('username'), 'username', ['trim' => true]);

            // The "username" field accepts the admin's username or email address
            $admin = Admin::query()->where('username', $username)->first()
                ?? Admin::query()->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $username)])->first();

            if ($admin === null) {
                return response()->json(['success' => false, 'message' => 'Invalid credentials'], 400);
            }

            if (! $admin->is_active) {
                return response()->json(['success' => false, 'message' => 'Admin account is deactivated'], 403);
            }

            if (! Hash::check((string) $request->input('password'), $admin->password)) {
                return response()->json(['success' => false, 'message' => 'Invalid credentials'], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'token' => $this->jwt->issueAdminToken($admin),
                'data' => [
                    'id' => $admin->id,
                    'username' => $admin->username,
                    'email' => $admin->email,
                    'role' => $admin->role,
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->serverError('Error during login', $exception);
        }
    }

    /**
     * POST /api/admin/logout
     *
     * Tokens are stateless; the client discards its token.
     */
    public function logout(): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Logout successful']);
    }
}
