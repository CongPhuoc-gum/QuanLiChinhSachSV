<?php

namespace App\Http\Controllers;

use App\Models\PhienChatAI;
use App\Models\TinNhanAI;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
            // Validate only phien_id here; accept flexible input keys for question
            $request->validate([
                'phien_id' => 'nullable|integer|exists:phien_chat_ai,MaPhien'
            ]);

            $user = Auth::user();

            // Accept multiple possible keys from frontend: question, message, text, content, input
            $rawQuestion = null;
            foreach (['question', 'message', 'text', 'content', 'input'] as $k) {
                if ($request->filled($k)) {
                    $rawQuestion = $request->input($k);
                    break;
                }
            }

            $rawQuestion = $rawQuestion !== null ? trim((string)$rawQuestion) : '';

            if ($rawQuestion === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu đầu vào không hợp lệ: thiếu câu hỏi'
                ], 422);
            }

            // Remove common greetings at start (e.g. "hi", "hello", "xin chào", "chào", "hey", "alo")
            $normalized = preg_replace('/^(?:\s*(?:hi|hello|hey|xin chào|chào|alo)[\s,!:.-]*)+/iu', '', $rawQuestion);
            $normalized = trim((string)$normalized);

            // If after stripping greetings nothing remains, ask client to provide question content
            if ($normalized === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Vui lòng nhập nội dung câu hỏi sau lời chào.'
                ], 422);
            }

            // Keep the normalized question as the actual question text
            $userQuestion = $normalized;
            $phienId = $request->input('phien_id');

            // 0. Local QA / keyword-based quick answers (check before calling Gemini)
            try {
                $qaPath = env('AI_QA_PATH', 'ai/qa_pairs.json');
                if (Storage::disk('local')->exists($qaPath)) {
                    $qaJson = Storage::disk('local')->get($qaPath);
                    $qaList = json_decode($qaJson, true) ?? [];
                    foreach ($qaList as $entry) {
                        $entryQuestion = isset($entry['question']) ? mb_strtolower($entry['question']) : '';
                        $keywords = $entry['keywords'] ?? [];

                        $matched = false;
                        // keyword match
                        foreach ($keywords as $kw) {
                            if ($kw !== '' && mb_stripos(mb_strtolower($userQuestion), mb_strtolower($kw)) !== false) {
                                $matched = true;
                                break;
                            }
                        }

                        // direct substring match on question template
                        if (!$matched && $entryQuestion !== '' && mb_stripos(mb_strtolower($userQuestion), $entryQuestion) !== false) {
                            $matched = true;
                        }

                        if ($matched) {
                            // create or use existing phien
                            if (!$phienId) {
                                $phien = PhienChatAI::create([
                                    'MaNguoiDung' => Auth::user()->MaNguoiDung,
                                    'ThoiGianBatDau' => now()
                                ]);
                                $phienId = $phien->MaPhien;
                            }

                            // save user message
                            $tinNhanUser = TinNhanAI::create([
                                'MaPhien' => $phienId,
                                'VaiTro' => 'user',
                                'NoiDung' => $userQuestion,
                                'ThoiGian' => now(),
                                'TokenSuDung' => 0
                            ]);

                            // save assistant canned answer
                            $assistantAnswer = $entry['answer'] ?? 'Thông tin không tìm thấy.';
                            $tinNhanAssistant = TinNhanAI::create([
                                'MaPhien' => $phienId,
                                'VaiTro' => 'assistant',
                                'NoiDung' => $assistantAnswer,
                                'ThoiGian' => now(),
                                'TokenSuDung' => 0
                            ]);

                            return response()->json([
                                'success' => true,
                                'phien_id' => $phienId,
                                'tin_nhan_user_id' => $tinNhanUser->MaTinNhan,
                                'tin_nhan_assistant_id' => $tinNhanAssistant->MaTinNhan,
                                'question' => $userQuestion,
                                'answer' => $assistantAnswer,
                                'citations' => $entry['citations'] ?? [],
                                'tokens_used' => 0,
                                'timestamp' => now()->toIso8601String()
                            ], 200);
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('AiChatbotController::ask - QA local check failed: ' . $e->getMessage());
            }

            // Quick keyword-based canonical answers for critical legal cases
            $lq = mb_strtolower($userQuestion);
            if (mb_stripos($lq, 'liệt sĩ') !== false || mb_stripos($lq, 'thương binh') !== false) {
                if (!$phienId) {
                    $phien = PhienChatAI::create([
                        'MaNguoiDung' => Auth::user()->MaNguoiDung,
                        'ThoiGianBatDau' => now()
                    ]);
                    $phienId = $phien->MaPhien;
                }

                $tinNhanUser = TinNhanAI::create([
                    'MaPhien' => $phienId,
                    'VaiTro' => 'user',
                    'NoiDung' => $userQuestion,
                    'ThoiGian' => now(),
                    'TokenSuDung' => 0
                ]);

                $assistantAnswer = 'Theo Điều 3, con liệt sĩ và con thương binh được miễn 100% học phí.';
                $tinNhanAssistant = TinNhanAI::create([
                    'MaPhien' => $phienId,
                    'VaiTro' => 'assistant',
                    'NoiDung' => $assistantAnswer,
                    'ThoiGian' => now(),
                    'TokenSuDung' => 0
                ]);

                return response()->json([
                    'success' => true,
                    'phien_id' => $phienId,
                    'tin_nhan_user_id' => $tinNhanUser->MaTinNhan,
                    'tin_nhan_assistant_id' => $tinNhanAssistant->MaTinNhan,
                    'question' => $userQuestion,
                    'answer' => $assistantAnswer,
                    'citations' => ['Điều 3'],
                    'tokens_used' => 0,
                    'timestamp' => now()->toIso8601String()
                ], 200);
            }

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
