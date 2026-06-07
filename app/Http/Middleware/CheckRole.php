<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * Middleware này kiểm tra xem người dùng có vai trò được phép truy cập route không.
     * 
     * Cách sử dụng:
     * Route::post('/approve', [Controller::class, 'approve'])
     *     ->middleware('auth:sanctum')
     *     ->middleware('role:3'); // Chỉ Trưởng phòng (MaVaiTro = 3)
     * 
     * Hoặc nhiều vai trò:
     * ->middleware('role:3,4'); // Trưởng phòng hoặc Ban Giám hiệu
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $roles
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        // Lấy người dùng hiện tại
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Chưa xác thực.',
            ], 401);
        }

        // Chuyển đổi chuỗi vai trò thành mảng
        $allowedRoles = array_map('intval', explode(',', $roles));

        // Kiểm tra xem người dùng có vai trò được phép không
        if (!in_array($user->MaVaiTro, $allowedRoles)) {
            return response()->json([
                'message' => 'Bạn không có quyền truy cập tài nguyên này.',
                'required_roles' => $allowedRoles,
                'user_role' => $user->MaVaiTro,
            ], 403);
        }

        return $next($request);
    }
}
