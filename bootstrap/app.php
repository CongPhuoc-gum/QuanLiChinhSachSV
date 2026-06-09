<?php

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Application;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Đăng ký middleware alias cho kiểm tra vai trò
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'check.admin.role' => \App\Http\Middleware\CheckAdminRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Xử lý ValidationException để trả về JSON
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // Xử lý AuthenticationException
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Chưa xác thực.',
                ], 401);
            }
        });

        // Xử lý AuthorizationException
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Bạn không có quyền truy cập tài nguyên này.',
                ], 403);
            }
        });

        // Xử lý ModelNotFoundException
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Không tìm thấy tài nguyên.',
                ], 404);
            }
        });
    })
    ->create();
