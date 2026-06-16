<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * DynamicRAGTrainingService - Huấn luyện AI từ các câu hỏi thực tế của sinh viên
 *
 * Cách hoạt động:
 * 1. Sinh viên hỏi câu hỏi → AI trả lời
 * 2. Hệ thống tự động học từ câu hỏi + câu trả lời
 * 3. Mỗi lần có câu hỏi mới → thêm vào knowledge base
 * 4. AI ngày càng thông minh hơn
 *
 * Ví dụ câu hỏi:
 * - "Con thương binh loại 1 được giảm bao nhiêu?"
 * - "Tôi cần submit minh chứng file gì?"
 * - "Hộ nghèo cần giấy tờ nào?"
 */
class DynamicRAGTrainingService
{
    private ImprovedRAGService $ragService;
    private CitationService $citationService;
    private TextNormalizationService $textNormalizationService;
    private string $learnedQuestionsPath = 'ai/learned_questions.jsonl';
    private string $questionsAnalysisPath = 'ai/questions_analysis.json';

    public function __construct(
        ImprovedRAGService $ragService,
        CitationService $citationService = null,
        TextNormalizationService $textNormalizationService = null
    ) {
        $this->ragService = $ragService;
        $this->citationService = $citationService ?? new CitationService();
        $this->textNormalizationService = $textNormalizationService ?? new TextNormalizationService();
    }

    /**
     * HỌC TỪ MỖI CÂU HỎI MỚI
     *
     * Mỗi khi sinh viên hỏi → hệ thống tự động:
     * 1. Phân tích câu hỏi (loại, intent, keywords)
     * 2. Lưu câu hỏi + câu trả lời
     * 3. Trích xuất kiến thức từ câu hỏi
     * 4. Cập nhật vector database
     *
     * @param string $userQuestion
     * @param string $aiAnswer
     * @param string $method (vector_rag or qa_cache)
     * @return void
     */
    public function learnFromQuestion(
        string $userQuestion,
        string $aiAnswer,
        string $method = 'vector_rag'
    ): void {
        try {
            Log::info('DynamicRAG: Learning from question', [
                'question' => $userQuestion,
                'method' => $method,
            ]);

            // STEP 1: Phân tích câu hỏi
            $analysis = $this->analyzeQuestion($userQuestion);

            // STEP 2: Tạo Q&A entry
            $qaEntry = [
                'timestamp' => now()->toIso8601String(),
                'question' => $userQuestion,
                'answer' => $aiAnswer,
                'method' => $method,
                'analysis' => $analysis,
                'confidence' => $this->calculateConfidence($userQuestion, $aiAnswer),
            ];

            // STEP 3: Lưu vào learned questions
            $this->saveLearnedQuestion($qaEntry);

            // STEP 4: Cập nhật thống kê câu hỏi
            $this->updateQuestionStatistics($analysis);

            // STEP 5: Nếu câu hỏi chưa biết → thêm vào QA cache
            if (!$this->isQuestionKnown($userQuestion)) {
                $this->addToQACache($userQuestion, $aiAnswer);
                Log::info('DynamicRAG: Added new Q&A to cache', [
                    'question' => $userQuestion,
                ]);
            }

            Log::info('DynamicRAG: Learning complete', [
                'type' => $analysis['type'],
                'intent' => $analysis['intent'],
            ]);
        } catch (\Exception $e) {
            Log::error('DynamicRAGTrainingService::learnFromQuestion - Error', [
                'message' => $e->getMessage(),
                'question' => $userQuestion,
            ]);
        }
    }

    /**
     * PHÂN TÍCH CÂU HỎI
     *
     * Tự động xác định:
     * - Loại câu hỏi (kiến thức, hành động, so sánh)
     * - Intent (muốn biết gì: miễn bao nhiêu, cần file gì, điều kiện gì)
     * - Entities (con thương binh, hộ nghèo, CCCD, v.v.)
     * - Keywords
     *
     * @param string $question
     * @return array
     */
    private function analyzeQuestion(string $question): array
    {
        $lowerQuestion = $this->textNormalizationService->toLower($question);

        // Xác định LOẠI CÂU HỎI
        $type = 'other';
        if ($this->textNormalizationService->contains($lowerQuestion, '?')) {
            if ($this->textNormalizationService->containsAny($lowerQuestion, ['miễn', 'giảm', 'được'])) {
                $type = 'reduction_query';
            } elseif ($this->textNormalizationService->containsAny($lowerQuestion, ['file', 'minh chứng', 'giấy', 'tờ'])) {
                $type = 'evidence_requirement';
            } elseif ($this->textNormalizationService->containsAny($lowerQuestion, ['điều kiện', 'ai được', 'ai hưởng'])) {
                $type = 'eligibility_query';
            } elseif ($this->textNormalizationService->containsAny($lowerQuestion, ['so sánh', 'khác', 'giống'])) {
                $type = 'comparison';
            }
        }

        // Xác định INTENT
        $intent = $this->extractIntent($question);

        // Trích xuất ENTITIES
        $entities = $this->extractEntities($question);

        // Trích xuất KEYWORDS
        $keywords = $this->extractKeywords($question);

        return [
            'type' => $type,
            'intent' => $intent,
            'entities' => $entities,
            'keywords' => $keywords,
            'question_length' => strlen($question),
            'language' => 'vi',
        ];
    }

    /**
     * XÁC ĐỊNH INTENT (ý định câu hỏi là gì?)
     *
     * @param string $question
     * @return string
     */
    private function extractIntent(string $question): string
    {
        $lowerQuestion = $this->textNormalizationService->toLower($question);

        // Intent: Muốn biết mức giảm học phí
        if ($this->textNormalizationService->containsAny($lowerQuestion, ['miễn', 'giảm']) ||
            ($this->textNormalizationService->contains($lowerQuestion, 'được') &&
                $this->textNormalizationService->contains($lowerQuestion, '%'))) {
            return 'ask_reduction_amount';
        }

        // Intent: Muốn biết cần những minh chứng gì
        if ($this->textNormalizationService->containsAny($lowerQuestion, ['file', 'minh chứng', 'giấy tờ', 'chứng chỉ'])) {
            return 'ask_evidence_requirements';
        }

        // Intent: Muốn biết ai được hưởng
        if ($this->textNormalizationService->containsAny($lowerQuestion, ['ai được', 'ai hưởng', 'điều kiện'])) {
            return 'ask_eligibility';
        }

        // Intent: So sánh 2 loại chính sách
        if ($this->textNormalizationService->contains($lowerQuestion, 'so sánh') ||
            ($this->textNormalizationService->contains($lowerQuestion, 'khác') &&
                ($this->textNormalizationService->contains($lowerQuestion, 'mghp') ||
                    $this->textNormalizationService->contains($lowerQuestion, 'tcxh')))) {
            return 'compare_policies';
        }

        // Intent: Muốn biết quy trình
        if ($this->textNormalizationService->containsAny($lowerQuestion, ['cách', 'thế nào', 'làm sao', 'quy trình'])) {
            return 'ask_process';
        }

        return 'general_query';
    }

    /**
     * TRÍCH XUẤT ENTITIES (các thực thể được đề cập)
     *
     * Ví dụ:
     * - "Con thương binh loại 1" → entity: con_thuong_binh, loai_1
     * - "Hộ cận nghèo" → entity: ho_can_ngheo
     * - "CCCD" → entity: cccd, identity_doc
     *
     * @param string $question
     * @return array
     */
    private function extractEntities(string $question): array
    {
        $entities = [];
        $lowerQuestion = mb_strtolower($question);

        // Loại đối tượng chính sách
        $policyMatches = [
            'con hộ nghèo|hộ nghèo' => 'ho_ngheo',
            'hộ cận nghèo|cận nghèo' => 'ho_can_ngheo',
            'con thương binh|thương binh' => 'con_thuong_binh',
            'con liệt sĩ|liệt sĩ' => 'con_liet_si',
            'dân tộc thiểu số' => 'dan_toc_thieu_so',
            'chính sách|hộ chính sách' => 'ho_chinh_sach',
        ];

        foreach ($policyMatches as $pattern => $entity) {
            if (preg_match("/$pattern/u", $lowerQuestion)) {
                $entities[] = $entity;
            }
        }

        // Cấp bậc thương binh
        if (preg_match('/loại\s*([1-4])/u', $lowerQuestion, $matches)) {
            $entities[] = 'thuong_binh_loai_' . $matches[1];
        }

        // Loại minh chứng
        $evidenceMatches = [
            'cccd|căn cước' => 'cccd',
            'hộ khẩu|sổ hộ khẩu' => 'ho_khau',
            'giấy khai sinh|khai sinh' => 'khai_sinh',
            'sổ tiết kiệm|tài khoản ngân hàng' => 'bank_account',
            'giấy chứng nhận hộ nghèo' => 'ho_ngheo_doc',
            'giấy chứng nhận thương binh' => 'thuong_binh_doc',
            'giấy xác nhận' => 'certification',
        ];

        foreach ($evidenceMatches as $pattern => $entity) {
            if (preg_match("/$pattern/u", $lowerQuestion)) {
                $entities[] = $entity;
            }
        }

        // Loại chính sách
        if (mb_strpos($lowerQuestion, 'mghp') !== false) {
            $entities[] = 'policy_mghp';
        }
        if (mb_strpos($lowerQuestion, 'tcxh') !== false) {
            $entities[] = 'policy_tcxh';
        }

        return array_unique($entities);
    }

    /**
     * TRÍCH XUẤT KEYWORDS
     *
     * @param string $question
     * @return array
     */
    private function extractKeywords(string $question): array
    {
        $keywords = [];
        $lowerQuestion = mb_strtolower($question);

        // Split thành các từ
        $words = preg_split('/\s+/u', $lowerQuestion);

        // Loại bỏ stop words (từ không quan trọng)
        $stopWords = ['là', 'được', 'có', 'cái', 'này', 'từ', 'để', 'sao', 'gì', 'ai'];

        foreach ($words as $word) {
            $word = preg_replace('/[^a-zàáảãạăằắẳẵặâầấẩẫậèéẻẽẹêềếểễệìíỉĩịòóỏõọôồốổỗộơờớởỡợùúủũụưừứửữựỳýỷỹỵđ0-9]/u', '', $word);

            // Giữ lại nếu: độ dài > 2 ký tự và không phải stop word
            if (strlen($word) > 2 && !in_array($word, $stopWords)) {
                $keywords[] = $word;
            }
        }

        return array_unique($keywords);
    }

    /**
     * TÍNH ĐỘ TIN CẬY CỦA CÂU TRƯNG LỰC
     *
     * Câu hỏi nào có độ tin cậy cao = AI trả lời chính xác
     * Dựa trên: method, câu hỏi có rõ ràng không, answer có citations
     *
     * @param string $question
     * @param string $answer
     * @return float (0-1)
     */
    private function calculateConfidence(string $question, string $answer): float
    {
        $confidence = 0.7;  // Base confidence

        // Nếu answer có "Điều X" → confidence cao
        if (preg_match('/Điều\s+\d+/u', $answer)) {
            $confidence += 0.2;
        }

        // Nếu question rõ ràng (không mơ hồ) → confidence cao
        if (mb_strlen($question) > 20 && mb_strlen($question) < 200) {
            $confidence += 0.05;
        }

        // Nếu answer không có "không biết" → confidence cao
        if (mb_strpos(mb_strtolower($answer), 'không biết') === false) {
            $confidence += 0.05;
        }

        return min($confidence, 1.0);
    }

    /**
     * LƯU CÂU HỎI ĐÃ HỌC
     *
     * Lưu định dạng JSONL (mỗi dòng = 1 entry)
     * Dùng để:
     * 1. Phân tích pattern câu hỏi
     * 2. Cải thiện QA cache
     * 3. Theo dõi lịch sử học
     *
     * @param array $qaEntry
     * @return void
     */
    private function saveLearnedQuestion(array $qaEntry): void
    {
        try {
            $jsonLine = json_encode($qaEntry, JSON_UNESCAPED_UNICODE) . "\n";

            if (!Storage::disk('local')->exists($this->learnedQuestionsPath)) {
                Storage::disk('local')->put($this->learnedQuestionsPath, '');
            }

            Storage::disk('local')->append($this->learnedQuestionsPath, rtrim($jsonLine));

            Log::info('Learned question saved', [
                'path' => $this->learnedQuestionsPath,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to save learned question', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * CẬP NHẬT THỐNG KÊ CÂU HỎI
     *
     * Theo dõi:
     * - Loại câu hỏi nào hỏi nhiều nhất
     * - Intent nào hỏi nhiều nhất
     * - Entities nào xuất hiện nhiều
     * - Dùng để optimize QA cache
     *
     * @param array $analysis
     * @return void
     */
    private function updateQuestionStatistics(array $analysis): void
    {
        try {
            $statsPath = $this->questionsAnalysisPath;

            // Load existing stats
            $stats = [];
            if (Storage::disk('local')->exists($statsPath)) {
                $json = Storage::disk('local')->get($statsPath);
                $stats = json_decode($json, true) ?? [];
            }

            // Update counts
            $type = $analysis['type'];
            $intent = $analysis['intent'];

            $stats['types'][$type] = ($stats['types'][$type] ?? 0) + 1;
            $stats['intents'][$intent] = ($stats['intents'][$intent] ?? 0) + 1;

            foreach ($analysis['entities'] as $entity) {
                $stats['entities'][$entity] = ($stats['entities'][$entity] ?? 0) + 1;
            }

            foreach ($analysis['keywords'] as $keyword) {
                $stats['keywords'][$keyword] = ($stats['keywords'][$keyword] ?? 0) + 1;
            }

            $stats['last_updated'] = now()->toIso8601String();
            $stats['total_questions'] = ($stats['total_questions'] ?? 0) + 1;

            // Save
            Storage::disk('local')->put(
                $statsPath,
                json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            Log::info('Question statistics updated', [
                'total' => $stats['total_questions'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update statistics', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * KIỂM TRA CÂU HỎI ĐÃ BIẾT CHƯA
     *
     * @param string $question
     * @return bool
     */
    private function isQuestionKnown(string $question): bool
    {
        try {
            if (!Storage::disk('local')->exists('ai/qa_pairs.json')) {
                return false;
            }

            $json = Storage::disk('local')->get('ai/qa_pairs.json');
            $pairs = json_decode($json, true) ?? [];

            $questionLower = mb_strtolower($question);

            foreach ($pairs as $pair) {
                $pairedQuestionLower = mb_strtolower($pair['keywords'][0] ?? '');
                if (mb_stripos($questionLower, $pairedQuestionLower) !== false) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Error checking known question', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * THÊM CÂU HỎI MỚI VÀO QA CACHE
     *
     * Nếu một câu hỏi chưa biết và được trả lời chính xác → thêm vào cache
     * Lần sau hỏi lại sẽ instant answer
     *
     * @param string $question
     * @param string $answer
     * @return void
     */
    private function addToQACache(string $question, string $answer): void
    {
        try {
            $qaPath = 'ai/qa_pairs.json';

            $pairs = [];
            if (Storage::disk('local')->exists($qaPath)) {
                $json = Storage::disk('local')->get($qaPath);
                $pairs = json_decode($json, true) ?? [];
            }

            // Tạo entry mới
            $analysis = $this->analyzeQuestion($question);
            $newEntry = [
                'keywords' => $analysis['keywords'] + [$analysis['intent']],
                'answer' => $answer,
                'citations' => $this->extractCitations($answer),
                'added_date' => now()->toIso8601String(),
                'source' => 'dynamic_learning',
            ];

            $pairs[] = $newEntry;

            // Lưu
            Storage::disk('local')->put(
                $qaPath,
                json_encode($pairs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            Log::info('Added to QA cache', [
                'question' => $question,
                'total_pairs' => count($pairs),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to add to QA cache', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * TRÍCH XUẤT CITATIONS (Điều, Khoản)
     * Sử dụng CitationService (loại bỏ duplication)
     *
     * @param string $text
     * @return array
     * @deprecated Sử dụng $this->citationService->extract() thay vì trực tiếp
     */
    private function extractCitations(string $text): array
    {
        return $this->citationService->extract($text);
    }

    /**
     * LẤY THỐNG KÊ CÂU HỎI
     *
     * Dùng để xem AI đã học gì
     *
     * @return array
     */
    public function getQuestionStatistics(): array
    {
        try {
            $statsPath = $this->questionsAnalysisPath;

            if (!Storage::disk('local')->exists($statsPath)) {
                return [];
            }

            $json = Storage::disk('local')->get($statsPath);
            return json_decode($json, true) ?? [];
        } catch (\Exception $e) {
            Log::error('Error getting statistics', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * LẤY CÂU HỎI ĐƯỢC HỌC GẦN ĐÂY
     *
     * @param int $limit
     * @return array
     */
    public function getRecentLearnedQuestions(int $limit = 10): array
    {
        try {
            if (!Storage::disk('local')->exists($this->learnedQuestionsPath)) {
                return [];
            }

            $content = Storage::disk('local')->get($this->learnedQuestionsPath);
            $lines = explode("\n", trim($content));

            // Lấy $limit dòng cuối cùng
            $recentLines = array_slice($lines, -$limit);

            $questions = [];
            foreach ($recentLines as $line) {
                if (trim($line)) {
                    $questions[] = json_decode($line, true);
                }
            }

            return $questions;
        } catch (\Exception $e) {
            Log::error('Error getting recent questions', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
