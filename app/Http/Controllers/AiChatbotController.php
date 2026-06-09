<?php

namespace App\Http\Controllers;

use App\Models\PhienChatAI;
use App\Models\TinNhanAI;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AiChatbotController extends Controller
{
    private GeminiService $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    /**
     * DAY 1: POST /api/chatbot/ask
     * Sinh viên đặt câu hỏi → Chatbot RAG trả lời dựa Nghị định 81/2021
     *
     * Request:
     * {
     *   "question": "Em là con hộ nghèo, được miễn bao nhiêu %?",
     *   "phien_id": null (nếu tạo phiên mới)
     * }
     *
     * Response:
     * {
     *   "success": true,
     *   "phien_id": 1,
     *   "tin_nhan_user_id": 2,
     *   "tin_nhan_assistant_id": 3,
     *   "answer": "Theo Điều 3, Nghị định 81/2021...",
     *   "citations": ["Điều 3", "Điều 5"],
     *   "tokens_used": 1234,
     *   "timestamp": "2026-05-21T14:30:00Z"
     * }
     */
    public function ask(Request $request)
    {
        try {
            // Validate input
            $request->validate([
                'question' => 'required|string|min:5|max:500',
                'phien_id' => 'nullable|integer|exists:phien_chat_ai,MaPhien'
            ]);

            $user = Auth::user();
            $userQuestion = trim($request->input('question'));
            $phienId = $request->input('phien_id');

            // 1. Tạo hoặc lấy phiên chat
            if (!$phienId) {
                $phien = PhienChatAI::create([
                    'MaNguoiDung' => $user->MaNguoiDung,
                    'ThoiGianBatDau' => now()
                ]);
                $phienId = $phien->MaPhien;
            }

            // 2. Gọi Gemini Service
            $geminiResult = $this->geminiService->askChatbotRag($userQuestion);

            if (!$geminiResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $geminiResult['message'] ?? 'Lỗi gọi Gemini API'
                ], 500);
            }

            // 3. Lưu câu hỏi vào TIN_NHAN_AI (user)
            $tinNhanUser = TinNhanAI::create([
                'MaPhien' => $phienId,
                'VaiTro' => 'user',
                'NoiDung' => $userQuestion,
                'ThoiGian' => now(),
                'TokenSuDung' => 0
            ]);

            // 4. Lưu câu trả lời vào TIN_NHAN_AI (assistant)
            $tinNhanAssistant = TinNhanAI::create([
                'MaPhien' => $phienId,
                'VaiTro' => 'assistant',
                'NoiDung' => $geminiResult['answer'],
                'ThoiGian' => now(),
                'TokenSuDung' => $geminiResult['tokens_used'] ?? 0
            ]);

            // 5. Trả response
            return response()->json([
                'success' => true,
                'phien_id' => $phienId,
                'tin_nhan_user_id' => $tinNhanUser->MaTinNhan,
                'tin_nhan_assistant_id' => $tinNhanAssistant->MaTinNhan,
                'question' => $userQuestion,
                'answer' => $geminiResult['answer'],
                'citations' => $geminiResult['citations'] ?? [],
                'tokens_used' => $geminiResult['tokens_used'] ?? 0,
                'timestamp' => now()->toIso8601String()
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu đầu vào không hợp lệ',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('AiChatbotController::ask - Error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'question' => $request->input('question'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi xử lý yêu cầu. Vui lòng thử lại sau.',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * DAY 1: GET /api/chatbot/phien/{phienId}
     * Lấy lịch sử hội thoại của một phiên
     *
     * Response:
     * {
     *   "success": true,
     *   "phien": {
     *     "MaPhien": 1,
     *     "MaNguoiDung": 1,
     *     "ThoiGianBatDau": "2026-05-21T14:30:00Z",
     *     "ThoiGianKetThuc": null
     *   },
     *   "messages": [
     *     { "MaTinNhan": 1, "VaiTro": "user", "NoiDung": "..." },
     *     { "MaTinNhan": 2, "VaiTro": "assistant", "NoiDung": "..." }
     *   ]
     * }
     */
    public function getPhien($phienId)
    {
        try {
            $user = Auth::user();

            // Kiểm tra quyền: chỉ SV sở hữu phiên mới được xem
            $phien = PhienChatAI::where('MaPhien', $phienId)
                ->where('MaNguoiDung', $user->MaNguoiDung)
                ->first();

            if (!$phien) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phiên chat không tồn tại hoặc bạn không có quyền truy cập'
                ], 404);
            }

            // Lấy tất cả tin nhắn trong phiên
            $messages = TinNhanAI::where('MaPhien', $phienId)
                ->orderBy('ThoiGian', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'phien' => $phien,
                'messages' => $messages,
                'total_messages' => $messages->count()
            ], 200);
        } catch (\Exception $e) {
            Log::error('AiChatbotController::getPhien - Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Lỗi lấy dữ liệu phiên chat',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * DAY 1: POST /api/chatbot/phien/{phienId}/danh-gia
     * Sinh viên đánh giá chất lượng câu trả lời AI (1-5 sao)
     *
     * Request:
     * {
     *   "diem": 4,
     *   "ghi_chu": "Câu trả lời rõ ràng nhưng thiếu ví dụ"
     * }
     */
    public function ratePhien($phienId, Request $request)
    {
        try {
            $request->validate([
                'diem' => 'required|integer|min:1|max:5',
                'ghi_chu' => 'nullable|string|max:300'
            ]);

            $user = Auth::user();

            $phien = PhienChatAI::where('MaPhien', $phienId)
                ->where('MaNguoiDung', $user->MaNguoiDung)
                ->first();

            if (!$phien) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phiên chat không tồn tại'
                ], 404);
            }

            // Cập nhật đánh giá
            $phien->update([
                'DiemDanhGia' => $request->input('diem'),
                'GhiChuDanhGia' => $request->input('ghi_chu'),
                'ThoiGianKetThuc' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cảm ơn bạn đã đánh giá. Ý kiến của bạn giúp chúng tôi cải thiện!',
                'phien' => $phien
            ], 200);
        } catch (\Exception $e) {
            Log::error('AiChatbotController::ratePhien - Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Lỗi lưu đánh giá',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * DAY 1: GET /api/chatbot/phien-list
     * Lấy danh sách tất cả phiên chat của SV
     */
    public function listPhien()
    {
        try {
            $user = Auth::user();

            $phiens = PhienChatAI::where('MaNguoiDung', $user->MaNguoiDung)
                ->with('tinNhans')
                ->orderBy('ThoiGianBatDau', 'desc')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'data' => $phiens,
                'total' => $phiens->total()
            ], 200);
        } catch (\Exception $e) {
            Log::error('AiChatbotController::listPhien - Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Lỗi lấy danh sách phiên',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }
}
