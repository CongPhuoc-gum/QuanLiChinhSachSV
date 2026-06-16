<?php declare(strict_types=1);

namespace App\Services;

use App\Common\Exceptions\GenerationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GenerationService - Generate answers using Gemini API
 *
 * Single Responsibility: Generate answers ONLY
 * - Takes question + context as input
 * - Calls Gemini API to generate response
 * - Handles API errors and timeouts
 *
 * @package App\Services
 */
class GenerationService
{
    private string $apiEndpoint = '';
    private string $model = 'gemini-2.0-flash';
    private string $apiKey = '';

    public function __construct()
    {
        $this->apiEndpoint = env('GEMINI_API_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models');
        $this->model = env('GEMINI_MODEL', 'gemini-2.0-flash');
        $this->apiKey = env('GEMINI_API_KEY', '');

        if (empty($this->apiKey)) {
            Log::warning('GenerationService - GEMINI_API_KEY not set');
        }
    }

    /**
     * Generate answer from question and context
     *
     * @param string $question - User question
     * @param string $context - Retrieved context with chunks
     * @param array $options - Generation options (temperature, maxTokens, etc.)
     * @return string - Generated answer
     * @throws GenerationException
     */
    public function generate(string $question, string $context, array $options = []): string
    {
        try {
            if (empty($question)) {
                throw new GenerationException('Question cannot be empty');
            }

            // Build system prompt
            $systemPrompt = $this->buildSystemPrompt();

            // Build user message
            $userMessage = $context . "\n\nCâu hỏi: " . $question;

            // Generation config with defaults
            $generationConfig = [
                'temperature' => $options['temperature'] ?? 0.0,  // Deterministic
                'topP' => $options['topP'] ?? 0.95,
                'maxOutputTokens' => $options['maxOutputTokens'] ?? 500,
            ];

            // Call Gemini API
            $response = Http::timeout(30)->post(
                "{$this->apiEndpoint}/{$this->model}:generateContent",
                [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $systemPrompt . "\n\n" . $userMessage]
                            ]
                        ]
                    ],
                    'generationConfig' => $generationConfig,
                    'safety_settings' => $this->buildSafetySettings(),
                ],
                [
                    'x-goog-api-key' => $this->apiKey,
                ]
            );

            if (!$response->successful()) {
                Log::error('GenerationService::generate - API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new GenerationException("Gemini API returned status {$response->status()}");
            }

            $data = $response->json();

            // Extract answer from response
            if (empty($data['candidates']) || empty($data['candidates'][0]['content']['parts'])) {
                throw new GenerationException('No valid response from Gemini API');
            }

            $answer = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

            if (empty($answer)) {
                throw new GenerationException('Empty answer from Gemini');
            }

            Log::debug('GenerationService::generate - Success', [
                'question_length' => strlen($question),
                'context_length' => strlen($context),
                'answer_length' => strlen($answer),
            ]);

            return trim($answer);
        } catch (GenerationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('GenerationService::generate - Error', [
                'message' => $e->getMessage(),
                'question' => $question,
            ]);
            throw new GenerationException("Generation failed: {$e->getMessage()}");
        }
    }

    /**
     * Generate with streaming (if supported)
     *
     * For now, returns non-streamed response
     * In V2, implement proper streaming
     *
     * @param string $question
     * @param string $context
     * @param callable $callback - Called with each chunk
     * @return string - Full answer
     */
    public function generateStreaming(string $question, string $context, callable $callback): string
    {
        // Currently non-streamed, call callback with full response
        $answer = $this->generate($question, $context);
        $callback($answer);
        return $answer;
    }

    /**
     * Generate multiple answer variants
     *
     * Useful for testing or getting multiple interpretations
     *
     * @param string $question
     * @param string $context
     * @param int $count - Number of variants (1-3)
     * @return array - [{answer, ...}, ...]
     */
    public function generateVariants(string $question, string $context, int $count = 2): array
    {
        $variants = [];
        $temperatures = [0.1, 0.5, 0.9];  // Low, medium, high temperature for variety

        for ($i = 0; $i < min($count, 3); $i++) {
            try {
                $answer = $this->generate($question, $context, [
                    'temperature' => $temperatures[$i] ?? 0.1,
                ]);
                $variants[] = [
                    'answer' => $answer,
                    'temperature' => $temperatures[$i] ?? 0.1,
                ];
            } catch (GenerationException $e) {
                Log::warning('GenerationService::generateVariants - Variant failed', [
                    'index' => $i,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $variants;
    }

    /**
     * Build system prompt for legal Q&A
     *
     * @return string
     */
    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
            Bạn là một trợ lý AI chuyên tư vấn về Nghị định 81/2021/NĐ-CP về chính sách miễn/giảm học phí 
            và trợ cấp xã hội cho sinh viên.

            YÊU CẦU:
            1. Trả lời dựa HOÀN TOÀN trên các đoạn kiến thức được cung cấp bên dưới
            2. PHẢI trích dẫn chính xác Điều/Khoản/Dòng từ Nghị định
            3. Nếu không tìm thấy thông tin trong kiến thức cung cấp → trả: "Thông tin này không có trong Nghị định 81/2021. Vui lòng liên hệ Phòng CTSV."
            4. Trả lời ngắn gọn (1-2 câu), chuyên nghiệp, tiếng Việt chuẩn

            KHÔNG BAO GỜ:
            - Sáng tạo hoặc suy diễn vượt quá nội dung kiến thức cung cấp
            - Cho lời khuyên pháp luật ngoài phạm vi Nghị định 81/2021
            - Trả lời về các quy định khác ngoài chính sách miễn/giảm học phí

            PHONG CÁCH:
            - Tôn trọng, chuyên nghiệp
            - Rõ ràng, dễ hiểu
            - Đảm bảo tính chính xác cao nhất

            PROMPT;
    }

    /**
     * Build safety settings for Gemini
     *
     * Blocks harmful content while allowing legal discussion
     *
     * @return array
     */
    private function buildSafetySettings(): array
    {
        return [
            [
                'category' => 'HARM_CATEGORY_HARASSMENT',
                'threshold' => 'BLOCK_ONLY_HIGH',
            ],
            [
                'category' => 'HARM_CATEGORY_HATE_SPEECH',
                'threshold' => 'BLOCK_ONLY_HIGH',
            ],
            [
                'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                'threshold' => 'BLOCK_ONLY_HIGH',
            ],
            [
                'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                'threshold' => 'BLOCK_ONLY_HIGH',
            ],
        ];
    }
}
