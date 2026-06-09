<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Closure;

/**
 * CheckAdminRole Middleware - DAY 4
 *
 * Kiểm tra xem user có quyền Admin/CanBo không
 * Chỉ cho phép truy cập /api/admin/* routes
 *
 * Usage: middleware('check.admin.role')
 */
class CheckAdminRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\ResponseFactory)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\ResponseFactory
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Check if user is authenticated
        if (!$user) {
            Log::warning('CheckAdminRole - Unauthenticated access attempt', [
                'path' => $request->path(),
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Vui lòng đăng nhập'
            ], 401);
        }

        // Load user's role
        $user->load('vaiTro');
        $role = $user->vaiTro?->TenVaiTro ?? null;

        // Check if role is Admin or CanBo (CTSV staff)
        $allowedRoles = ['admin', 'can_bo', 'ctsv'];

        if (!$role || !in_array(strtolower($role), $allowedRoles)) {
            Log::warning('CheckAdminRole - Unauthorized access attempt', [
                'user_id' => $user->MaNguoiDung,
                'role' => $role,
                'path' => $request->path()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền truy cập chức năng này'
            ], 403);
        }

        // Log successful authorization
        Log::info('CheckAdminRole - Authorized access', [
            'user_id' => $user->MaNguoiDung,
            'role' => $role,
            'path' => $request->path()
        ]);

        return $next($request);
    }
}
