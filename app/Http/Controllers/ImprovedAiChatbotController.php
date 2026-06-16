<?php

namespace App\Http\Controllers;

use App\Models\PhienChatAI;
use App\Models\TinNhanAI;
use App\Services\DynamicRAGTrainingService;
use App\Services\HoSoAnalysisService;
use App\Services\ImprovedRAGService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * ImprovedAiChatbotController - Cải thiện Chatbot với RAG + HoSo Analysis
 *
 * Endpoints:
 * POST /api/chatbot/improved/ask - Ask với improved RAG
 * POST /api/ho-so/{id}/analyze-for-reduction - Phân tích hồ sơ tính giảm học phí
 */
class ImprovedAiChatbotController extends Controller
{
    private ImprovedRAGService $ragService;
    private HoSoAnalysisService $hoSoAnalysisService;
    private DynamicRAGTrainingService $dynamicRAGService;

    public function __construct(
        ImprovedRAGService $ragService,
        HoSoAnalysisService $hoSoAnalysisService,
        DynamicRAGTrainingService $dynamicRAGService
    ) {
        $this->ragService = $ragService;
        $this->hoSoAnalysisService = $hoSoAnalysisService;
        $this->dynamicRAGService = $dynamicRAGService;
    }

    /**
     * IMPROVED: Ask chatbot với RAG thực sự
     *
     * POST /api/chatbot/improved/ask
     *
     * Request:
     * {
     *   "question": "Con hộ nghèo được miễn bao nhiêu?",
     *   "phien_id": null
     * }
     *
     * Response:
     * {
     *   "success": true,
     *   "phien_id": 1,
     *   "answer": "Theo Điều 3, con hộ nghèo được miễn 100% học phí...",
     *   "citations": ["Điều 3"],
     *   "retrieved_chunks_count": 3,
     *   "method": "vector_rag",
     *   "tokens_saved": 12000,
     *   "speed_improvement": "50% faster"
     * }
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function improvedAsk(Request $request)
    {
        try {
            $request->validate([
                'question' => 'required|string|max:500',
                'phien_id' => 'nullable|integer|exists:phien_chat_ai,MaPhien',
            ]);

            $user = Auth::user();
            $userQuestion = trim($request->input('question'));
            $phienId = $request->input('phien_id');

            // 1. Normalize question
            $normalizedQuestion = preg_replace('/^(?:\s*(?:hi|hello|hey|xin chào|chào|alo)[\s,!:.-]*)+/iu', '', $userQuestion);
            $normalizedQuestion = trim($normalizedQuestion);

            if (empty($normalizedQuestion)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vui lòng nhập nội dung câu hỏi sau lời chào.',
                ], 422);
            }

            // 2. Call improved RAG service
            $ragResult = $this->ragService->improvedAskChatbot($normalizedQuestion);

            if (!$ragResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $ragResult['message'] ?? 'Lỗi xử lý câu hỏi',
                ], 500);
            }

            // 3. Create or get chat session
            if (!$phienId) {
                $phien = PhienChatAI::create([
                    'MaNguoiDung' => $user->MaNguoiDung,
                    'ThoiGianBatDau' => now(),
                ]);
                $phienId = $phien->MaPhien;
            }

            // 4. Save messages
            $tinNhanUser = TinNhanAI::create([
                'MaPhien' => $phienId,
                'VaiTro' => 'user',
                'NoiDung' => $normalizedQuestion,
                'ThoiGian' => now(),
                'TokenSuDung' => 0,
            ]);

            $tinNhanAssistant = TinNhanAI::create([
                'MaPhien' => $phienId,
                'VaiTro' => 'assistant',
                'NoiDung' => $ragResult['answer'],
                'ThoiGian' => now(),
                'TokenSuDung' => 0,
            ]);

            // 6. LEARN FROM THIS QUESTION (Tự động học)
            $this->dynamicRAGService->learnFromQuestion(
                $normalizedQuestion,
                $ragResult['answer'],
                $ragResult['method'] ?? 'vector_rag'
            );

            // 7. Response
            return response()->json([
                'success' => true,
                'phien_id' => $phienId,
                'tin_nhan_user_id' => $tinNhanUser->MaTinNhan,
                'tin_nhan_assistant_id' => $tinNhanAssistant->MaTinNhan,
                'question' => $normalizedQuestion,
                'answer' => $ragResult['answer'],
                'citations' => $ragResult['citations'] ?? [],
                'retrieved_chunks_count' => $ragResult['retrieved_chunks_count'] ?? 0,
                'method' => $ragResult['method'] ?? 'unknown',
                'tokens_saved' => $ragResult['tokens_saved'] ?? 0,
                'timestamp' => now()->toIso8601String(),
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu đầu vào không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('ImprovedAiChatbotController::improvedAsk - Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi xử lý yêu cầu',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * ANALYZE HỒ SỚ - Tự động tính mức giảm học phí
     *
     * POST /api/ho-so/{maHoSo}/analyze-for-reduction
     *
     * Response (MGHP):
     * {
     *   "success": true,
     *   "policy_type": "MGHP",
     *   "doi_tuong": "ho_ngheo",
     *   "reduction_percent": 100,
     *   "reduction_text": "Miễn 100% học phí",
     *   "basis": "Điều 3, Nghị định 81/2021 - Con hộ nghèo",
     *   "evidence_status": {
     *     "files_provided": {
     *       "has_cccd": true,
     *       "has_ho_ngheo_doc": true,
     *       "total_files": 3
     *     },
     *     "complete": true,
     *     "completeness_percent": 100
     *   },
     *   "recommendation": "✅ Hồ sơ đầy đủ. Khuyến cáo phê duyệt miễn 100% học phí.",
     *   "status": "APPROVED",
     *   "analyzed_at": "2026-06-12T10:30:00Z"
     * }
     *
     * Response (TCXH):
     * {
     *   "success": true,
     *   "policy_type": "TCXH",
     *   "loai_doi_tuong": "con_liet_si",
     *   "reduction_percent": 100,
     *   "reduction_text": "Miễn 100% học phí",
     *   "subsidy_amount": 2000000,
     *   "subsidy_text": "Trợ cấp: 2,000,000 VNĐ/tháng",
     *   "bank_account": {
     *     "so_tai_khoan": "0123****89",
     *     "ten_chu_tai_khoan": "NGUYỄN VĂN A"
     *   },
     *   "basis": "Điều 5, Nghị định 81/2021 - Con liệt sĩ",
     *   "evidence_status": {
     *     "complete": true,
     *     "completeness_percent": 100
     *   },
     *   "recommendation": "✅ Hồ sơ đầy đủ. Khuyến cáo phê duyệt: Miễn 100% học phí + Trợ cấp 2,000,000 VNĐ/tháng.",
     *   "status": "APPROVED",
     *   "analyzed_at": "2026-06-12T10:30:00Z"
     * }
     *
     * @param int $maHoSo
     * @return \Illuminate\Http\JsonResponse
     */
    public function analyzeForReduction($maHoSo)
    {
        try {
            $analysis = $this->hoSoAnalysisService->analyzeHoSoForReduction($maHoSo);

            if (!$analysis['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $analysis['message'] ?? 'Lỗi phân tích hồ sơ',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => $analysis,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ImprovedAiChatbotController::analyzeForReduction - Error', [
                'ma_ho_so' => $maHoSo,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi phân tích hồ sơ',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * INDEX DOCUMENTS - Chạy setup RAG (1 lần)
     *
     * POST /api/admin/rag-setup
     *
     * Body: { "action": "index-documents" }
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ragSetup(Request $request)
    {
        try {
            $action = $request->input('action');

            if ($action !== 'index-documents') {
                return response()->json([
                    'success' => false,
                    'message' => 'Action không hợp lệ',
                ], 400);
            }

            Log::info('Starting RAG setup - indexing documents');

            $this->ragService->indexDocuments();

            return response()->json([
                'success' => true,
                'message' => 'RAG indexing completed successfully',
                'action' => 'index-documents',
                'timestamp' => now()->toIso8601String(),
            ], 200);
        } catch (\Exception $e) {
            Log::error('ImprovedAiChatbotController::ragSetup - Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi setup RAG',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * XEM THỐNG KÊ CÂU HỎI - AI ĐÃ HỌC GÌ
     *
     * GET /api/admin/dynamic-rag/statistics
     *
     * Response:
     * {
     *   "total_questions": 125,
     *   "types": {
     *     "reduction_query": 45,
     *     "evidence_requirement": 38,
     *     "eligibility_query": 30,
     *     ...
     *   },
     *   "intents": {
     *     "ask_reduction_amount": 50,
     *     "ask_evidence_requirements": 40,
     *     ...
     *   },
     *   "top_entities": [
     *     {"entity": "ho_ngheo", "count": 35},
     *     {"entity": "con_thuong_binh", "count": 28},
     *     ...
     *   ],
     *   "top_keywords": [
     *     {"keyword": "miễn", "count": 45},
     *     {"keyword": "giảm", "count": 38},
     *     ...
     *   ]
     * }
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getQuestionStatistics()
    {
        try {
            $stats = $this->dynamicRAGService->getQuestionStatistics();

            if (empty($stats)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Chưa có dữ liệu thống kê',
                    'data' => [
                        'total_questions' => 0,
                        'types' => [],
                        'intents' => [],
                        'entities' => [],
                        'keywords' => [],
                    ],
                ], 200);
            }

            // Sort và format response
            $topEntities = $this->sortByCount($stats['entities'] ?? []);
            $topKeywords = $this->sortByCount($stats['keywords'] ?? []);

            return response()->json([
                'success' => true,
                'data' => [
                    'total_questions' => $stats['total_questions'] ?? 0,
                    'types' => $stats['types'] ?? [],
                    'intents' => $stats['intents'] ?? [],
                    'top_entities' => array_slice($topEntities, 0, 10),
                    'top_keywords' => array_slice($topKeywords, 0, 15),
                    'last_updated' => $stats['last_updated'] ?? null,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('ImprovedAiChatbotController::getQuestionStatistics - Error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi lấy thống kê',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * XEM CÂU HỎI ĐƯỢC HỌC GẦN ĐÂY
     *
     * GET /api/admin/dynamic-rag/recent-questions
     *
     * Response:
     * {
     *   "success": true,
     *   "data": [
     *     {
     *       "timestamp": "2026-06-12T10:30:00Z",
     *       "question": "Con thương binh loại 1 được giảm bao nhiêu?",
     *       "answer": "Theo Điều 4...",
     *       "analysis": {
     *         "type": "reduction_query",
     *         "intent": "ask_reduction_amount",
     *         "entities": ["con_thuong_binh", "thuong_binh_loai_1"],
     *         "keywords": ["thương", "binh", "loại"]
     *       },
     *       "confidence": 0.95
     *     },
     *     ...
     *   ]
     * }
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRecentLearnedQuestions(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);
            $limit = min($limit, 50);  // Max 50

            $questions = $this->dynamicRAGService->getRecentLearnedQuestions($limit);

            return response()->json([
                'success' => true,
                'data' => $questions,
                'total' => count($questions),
            ], 200);
        } catch (\Exception $e) {
            Log::error('ImprovedAiChatbotController::getRecentLearnedQuestions - Error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi lấy câu hỏi học gần đây',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Helper: Sort array by count (desc)
     *
     * @param array $array
     * @return array
     */
    private function sortByCount(array $array): array
    {
        $result = [];
        foreach ($array as $key => $count) {
            $result[] = ['name' => $key, 'count' => $count];
        }

        usort($result, fn($a, $b) => $b['count'] - $a['count']);
        return $result;
    }

    /**
     * POST /api/chatbot/calculate-subsidy
     * Tính trợ cấp xã hội (TCXH)
     *
     * Body: {
     *   "loai_doi_tuong": "con_liet_si",
     *   "thuong_binh_loai": 1,
     *   "so_thang_hoc": 4
     * }
     *
     * Response: {
     *   "success": true,
     *   "data": {
     *     "tien_huong_hang_thang": 2000000,
     *     "tong_tien_huong": 8000000,
     *     ...
     *   }
     * }
     */
    public function calculateSubsidy(\Illuminate\Http\Request $request)
    {
        try {
            $subsidyService = new \App\Services\SubsidyCalculationService();

            $validated = $request->validate([
                'loai_doi_tuong' => 'required|string',
                'thuong_binh_loai' => 'nullable|integer|in:1,2,3,4',
                'so_thang_hoc' => 'nullable|integer|min:1|max:12',
            ]);

            $result = $subsidyService->calculateSubsidy($validated);

            return response()->json($result, $result['success'] ? 200 : 422);
        } catch (\Exception $e) {
            \Log::error('Error in ImprovedAiChatbotController@calculateSubsidy', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi tính toán trợ cấp',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
