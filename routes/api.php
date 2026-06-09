<?php

use App\Http\Controllers\AiChatbotController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ==================== AUTHENTICATION ====================
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// ==================== PROTECTED ROUTES ====================
Route::middleware('auth:sanctum')->group(function () {
    // User info
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // ==================== AI CHATBOT ====================
    // POST /api/chatbot/ask - Gửi câu hỏi đến AI
    // Body: { "question": "...", "phien_id": null }
    // Response: { "success": true, "phien_id": 1, "answer": "...", "citations": [...] }
    Route::post('/chatbot/ask', [AiChatbotController::class, 'ask']);

    // GET /api/chatbot/phien/{phienId} - Lấy lịch sử hội thoại 1 phiên
    // Response: { "success": true, "phien": {...}, "messages": [...] }
    Route::get('/chatbot/phien/{phienId}', [AiChatbotController::class, 'getPhien']);

    // POST /api/chatbot/phien/{phienId}/danh-gia - Đánh giá phiên chat
    // Body: { "diem": 4, "ghi_chu": "..." }
    Route::post('/chatbot/phien/{phienId}/danh-gia', [AiChatbotController::class, 'ratePhien']);

    // GET /api/chatbot/phien-list - Danh sách phiên chat của SV
    // Response: { "success": true, "data": [...], "total": 5 }
    Route::get('/chatbot/phien-list', [AiChatbotController::class, 'listPhien']);
});
