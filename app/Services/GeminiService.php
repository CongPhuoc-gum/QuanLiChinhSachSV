<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class GeminiService
{
    private string $apiKey;
    private string $model;
    private string $visionModel;
    private string $endpoint;
    private int $timeout;
    private int $maxRetries;
    private string $temperature;
    private string $topP;

    public function __construct()
    {
        $this->apiKey = config('app.gemini_api_key') ?? env('GEMINI_API_KEY');
        $this->model = config('app.gemini_model') ?? env('GEMINI_MODEL', 'gemini-2.0-flash');
        $this->visionModel = config('app.gemini_vision_model') ?? env('GEMINI_VISION_MODEL', 'gemini-2.0-flash-vision');
        $this->endpoint = config('app.gemini_endpoint') ?? env('GEMINI_API_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models');
        $this->timeout = (int) env('GEMINI_TIMEOUT', 30);
        $this->maxRetries = (int) env('GEMINI_MAX_RETRIES', 3);
        $this->temperature = env('AI_TEMPERATURE', '0');
        $this->topP = env('AI_TOP_P', '0.95');

        if (!$this->apiKey) {
            throw new Exception('GEMINI_API_KEY is not configured in .env');
        }
    }

    /**
     * Chat RAG - Hỏi Gemini câu hỏi dựa trên ngữ cảnh Nghị định 81/2021
     *
     * @param string $userQuestion Câu hỏi của sinh viên
     * @return array Phản hồi từ Gemini kèm theo trích dẫn
     *
     * @throws Exception
     */
    public function askChatbotRag(string $userQuestion): array
    {
        try {
            // 1. Đọc file ngữ cảnh Nghị định 81
            $knowledgeBase = $this->loadKnowledgeBase();

            // If knowledge base is missing, return a polite fallback answer instead of throwing
            if (empty(trim($knowledgeBase))) {
                return [
                    'success' => true,
                    'answer' => 'Xin lỗi, dữ liệu Nghị định 81/2021 chưa được tải lên hệ thống. Vui lòng liên hệ quản trị để cập nhật tài liệu trước khi sử dụng chức năng tư vấn AI.',
                    'citations' => [],
                    'tokens_used' => 0,
                    'timestamp' => now()
                ];
            }

            // 2. Xây dựng System Prompt với ngữ cảnh
            $systemPrompt = $this->buildSystemPromptWithKB($knowledgeBase);

            // 3. Gọi Gemini API với retry logic
            $response = $this->callGeminiWithRetry(
                $userQuestion,
                $systemPrompt
            );

            // 4. Parse response và trích dẫn
            $result = $this->parseGeminiResponse($response, $userQuestion);

            return $result;
        } catch (Exception $e) {
            Log::error('GeminiService::askChatbotRag - Error: ' . $e->getMessage(), [
                'question' => $userQuestion,
                'trace' => $e->getTraceAsString()
            ]);

            // If KB is available, attempt a simple local KB-based answer as fallback
            if (!empty(trim($knowledgeBase))) {
                try {
                    $local = $this->localKbAnswer($userQuestion, $knowledgeBase);
                    if ($local['success']) {
                        return $local;
                    }
                } catch (Exception $ex) {
                    Log::warning('GeminiService::localKbAnswer failed: ' . $ex->getMessage());
                }
            }

            return [
                'success' => false,
                'message' => 'Xin lỗi, hệ thống AI gặp sự cố. Vui lòng thử lại sau.',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Simple local KB search fallback: tries to find relevant lines in the KB
     * and returns them as a short answer with any detected citations.
     */
    private function localKbAnswer(string $question, string $kb): array
    {
        // Normalize
        $q = mb_strtolower(strip_tags($question));

        // Extract keyword tokens (words with length >=3)
        $tokens = preg_split('/[^\p{L}0-9]+/u', $q);
        $tokens = array_filter(array_map('trim', $tokens), function ($t) {
            return mb_strlen($t) >= 3;
        });

        if (empty($tokens)) {
            return ['success' => false];
        }

        // Search KB lines for matches
        $lines = preg_split('/\r?\n/', $kb);
        $matches = [];
        foreach ($lines as $line) {
            $low = mb_strtolower($line);
            foreach ($tokens as $tok) {
                if (mb_stripos($low, $tok) !== false) {
                    $matches[] = trim($line);
                    break;
                }
            }
            if (count($matches) >= 6)
                break;  // limit
        }

        if (empty($matches)) {
            // If nothing matched, explicitly say not found in KB
            return [
                'success' => true,
                'answer' => 'Thông tin này không có trong Nghị định 81/2021.',
                'citations' => [],
                'tokens_used' => 0,
                'timestamp' => now()
            ];
        }

        $snippet = implode(' ', array_slice($matches, 0, 6));
        preg_match_all('/Điều\s+\d+/u', $snippet, $m);
        $citations = array_unique($m[0] ?? []);

        return [
            'success' => true,
            'answer' => $snippet,
            'citations' => $citations,
            'tokens_used' => 0,
            'timestamp' => now()
        ];
    }

    /**
     * Gọi Gemini Vision API để OCR ảnh minh chứng
     *
     * @param string $imageUrl URL ảnh hoặc base64
     * @param string $documentType Loại giấy tờ: cccd, ho_khau, ho_ngheo, etc.
     * @return array Dữ liệu trích xuất từ ảnh (JSON chuẩn)
     *
     * @throws Exception
     */
    public function ocrDocument(string $imageUrl, string $documentType = 'cccd'): array
    {
        try {
            // 1. Lấy prompt OCR tùy theo loại giấy tờ
            $ocrPrompt = $this->getOcrPrompt($documentType);

            // 2. Gọi Gemini Vision API
            $response = $this->callGeminiVisionWithRetry(
                $imageUrl,
                $ocrPrompt
            );

            // 3. Parse JSON từ Gemini
            $extractedData = $this->parseOcrResponse($response);

            return [
                'success' => true,
                'data' => $extractedData,
                'confidence' => $extractedData['confidence'] ?? 0.9
            ];
        } catch (Exception $e) {
            Log::error('GeminiService::ocrDocument - Error: ' . $e->getMessage(), [
                'imageUrl' => substr($imageUrl, 0, 50),
                'documentType' => $documentType,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Không thể đọc tài liệu. Vui lòng tải lên ảnh rõ ràng hơn.',
                'error' => $e->getMessage()
            ];
        }
    }

    /** ==================== PRIVATE HELPER METHODS ==================== */

    /**
     * Đọc file ngữ cảnh Nghị định 81/2021
     */
    private function loadKnowledgeBase(): string
    {
        $kbPath = env('AI_KB_PATH', 'ai/nghidinh81.txt');

        if (!Storage::disk('local')->exists($kbPath)) {
            Log::warning("GeminiService::loadKnowledgeBase - Knowledge base file not found at: {$kbPath}");
            // Return empty string to allow the service to decide on a graceful fallback
            return '';
        }

        $content = Storage::disk('local')->get($kbPath);

        // Limit content nếu vượt quá context window
        $maxSize = (int) env('AI_CONTEXT_WINDOW_SIZE', 60000);
        if (strlen($content) > $maxSize) {
            $content = substr($content, 0, $maxSize);
            $content .= "\n[Nội dung bị cắt ngắn để phù hợp với giới hạn ngữ cảnh]";
        }

        return $content;
    }

    /**
     * Xây dựng System Prompt cho RAG
     */
    private function buildSystemPromptWithKB(string $knowledgeBase): string
    {
        return <<<'PROMPT'
            Bạn là một trợ lý AI chuyên tư vấn về các chính sách miễn giảm học phí và trợ cấp xã hội 
            cho sinh viên theo Nghị định 81/2021/NĐ-CP của Chính phủ Việt Nam.

            KIẾN THỨC CƠ SỞ - NGHỊ ĐỊNH 81/2021:
            ''' . PHP_EOL . $knowledgeBase . PHP_EOL . '''

            YÊU CẦU TRÊN CÂTRẢ LỜI:
            1. Bạn phải trả lời dựa HOÀN TOÀN trên nội dung Nghị định 81/2021 ở trên.
            2. Nếu không tìm thấy câu trả lời trong Nghị định, hãy nói rõ: "Thông tin này không có trong Nghị định 81/2021."
            3. Bạn PHẢI trích dẫn chính xác Điều/Khoản từ Nghị định khi đưa ra câu trả lời. VD: "Theo Điều 3, ..."
            4. Trả lời bằng TIẾNG VIỆT, ngắn gọn, dễ hiểu cho sinh viên.
            5. Nếu câu hỏi không liên quan đến chính sách miễn giảm, hãy nói: "Tôi chỉ có thể tư vấn về Nghị định 81/2021. Câu hỏi của bạn không liên quan."

            ĐỊNH DẠNG TRẢ LỜI:
            - Câu trả lời chính: [Trích dẫn Điều/Khoản] + [Giải thích rõ ràng]
            - Mẫu ứng dụng: [Nếu có] VD sinh viên hộ nghèo được miễn 100% học phí
            - Liên hệ: [Nếu cần] Vui lòng liên hệ phòng CTSV trường để xác nhận chi tiết

            HƯỚNG DẪN THÊM CHO PHONG CÁCH TRẢ LỜI:
            - Trả lời ngắn gọn (1-2 câu). Nếu có câu trả lời chính xác trong KIẾN THỨC CƠ SỞ, hãy TRÍCH DẪN NGAY "Điều X" trong câu trả lời.
            - KHÔNG TƯỞNG TƯỢNG hoặc SUY DIỄN vượt quá nội dung của KIẾN THỨC CƠ SỞ. Nếu không tìm thấy, trả: "Thông tin này không có trong Nghị định 81/2021."
            - Luôn trả bằng TIẾNG VIỆT.

            ---
            PROMPT;
    }

    /**
     * Gọi Gemini API với retry logic
     */
    private function callGeminiWithRetry(string $userQuestion, string $systemPrompt): string
    {
        $attempt = 0;
        $lastError = null;

        while ($attempt < $this->maxRetries) {
            try {
                $attempt++;

                $url = "{$this->endpoint}/{$this->model}:generateContent?key={$this->apiKey}";

                $payload = [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                [
                                    'text' => $systemPrompt . "\n\nCâu hỏi của sinh viên: " . $userQuestion
                                ]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => (float) $this->temperature,
                        'topP' => (float) $this->topP,
                        'maxOutputTokens' => 1000
                    ]
                ];

                $response = Http::timeout($this->timeout)
                    ->retry($this->maxRetries, 1000)
                    ->post($url, $payload);

                if ($response->successful()) {
                    return $response->json();
                } else {
                    $lastError = "HTTP {$response->status()}: " . json_encode($response->json());
                    Log::warning("GeminiService::callGeminiWithRetry - Attempt {$attempt}/{$this->maxRetries}: {$lastError}");
                    sleep(2);  // Backoff
                    continue;
                }
            } catch (Exception $e) {
                $lastError = $e->getMessage();
                Log::warning("GeminiService::callGeminiWithRetry - Attempt {$attempt}/{$this->maxRetries}: {$lastError}");
                sleep(2);
            }
        }

        throw new Exception("Gemini API call failed after {$this->maxRetries} attempts: {$lastError}");
    }

    /**
     * Gọi Gemini Vision API với retry
     */
    private function callGeminiVisionWithRetry(string $imageUrl, string $prompt): string
    {
        $attempt = 0;
        $lastError = null;

        while ($attempt < $this->maxRetries) {
            try {
                $attempt++;

                $url = "{$this->endpoint}/{$this->visionModel}:generateContent?key={$this->apiKey}";

                // Nếu là URL trực tiếp (Cloudinary)
                if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                    $imagePart = [
                        'text' => $prompt
                    ];

                    // Tải ảnh từ URL và convert sang base64
                    try {
                        $imageResponse = Http::timeout(15)->get($imageUrl);
                        if ($imageResponse->successful()) {
                            $base64Image = base64_encode($imageResponse->body());
                            $mimeType = $imageResponse->header('Content-Type') ?? 'image/jpeg';

                            $payload = [
                                'contents' => [
                                    [
                                        'role' => 'user',
                                        'parts' => [
                                            [
                                                'text' => $prompt
                                            ],
                                            [
                                                'inline_data' => [
                                                    'mime_type' => $mimeType,
                                                    'data' => $base64Image
                                                ]
                                            ]
                                        ]
                                    ]
                                ],
                                'generationConfig' => [
                                    'temperature' => 0,
                                    'topP' => 0.95,
                                    'maxOutputTokens' => 500
                                ]
                            ];
                        } else {
                            throw new Exception('Failed to fetch image from URL');
                        }
                    } catch (Exception $e) {
                        Log::warning("Failed to load image from URL: {$imageUrl}, retrying...");
                        sleep(2);
                        continue;
                    }
                } else {
                    // Nếu là base64
                    $payload = [
                        'contents' => [
                            [
                                'role' => 'user',
                                'parts' => [
                                    [
                                        'text' => $prompt
                                    ],
                                    [
                                        'inline_data' => [
                                            'mime_type' => 'image/jpeg',
                                            'data' => $imageUrl
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'generationConfig' => [
                            'temperature' => 0,
                            'topP' => 0.95,
                            'maxOutputTokens' => 500
                        ]
                    ];
                }

                $response = Http::timeout($this->timeout)
                    ->post($url, $payload);

                if ($response->successful()) {
                    return $response->json();
                } else {
                    $lastError = "HTTP {$response->status()}: " . json_encode($response->json());
                    Log::warning("GeminiService::callGeminiVisionWithRetry - Attempt {$attempt}: {$lastError}");
                    sleep(2);
                    continue;
                }
            } catch (Exception $e) {
                $lastError = $e->getMessage();
                Log::warning("GeminiService::callGeminiVisionWithRetry - Attempt {$attempt}: {$lastError}");
                sleep(2);
            }
        }

        throw new Exception("Gemini Vision API call failed: {$lastError}");
    }

    /**
     * Parse Gemini response và trích dẫn
     */
    private function parseGeminiResponse(array $response, string $userQuestion): array
    {
        try {
            // Trích xuất text từ response
            $candidates = $response['candidates'] ?? [];
            if (empty($candidates)) {
                return [
                    'success' => false,
                    'message' => 'Không nhận được phản hồi từ Gemini.'
                ];
            }

            $content = $candidates[0]['content']['parts'][0]['text'] ?? '';

            // Trích dẫn các "Điều X" từ response
            preg_match_all('/Điều\s+\d+/', $content, $matches);
            $citations = array_unique($matches[0] ?? []);

            // Tính token được sử dụng
            $usageMetadata = $response['usageMetadata'] ?? [];
            $inputTokens = $usageMetadata['promptTokenCount'] ?? 0;
            $outputTokens = $usageMetadata['candidatesTokenCount'] ?? 0;

            return [
                'success' => true,
                'answer' => $content,
                'citations' => $citations,
                'question' => $userQuestion,
                'tokens_used' => $inputTokens + $outputTokens,
                'timestamp' => now()
            ];
        } catch (Exception $e) {
            Log::error('GeminiService::parseGeminiResponse - Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Lỗi xử lý phản hồi từ Gemini.',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Parse OCR response - chuyển đổi Gemini text → JSON chuẩn
     */
    private function parseOcrResponse(array $response): array
    {
        try {
            $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Tìm JSON trong text
            if (preg_match('/\{.*\}/s', $text, $matches)) {
                $json = json_decode($matches[0], true);
                if ($json) {
                    return $json;
                }
            }

            // Nếu không tìm thấy JSON, trả về format mặc định
            return [
                'ho_ten' => '',
                'id_number' => '',
                'doi_tuong' => '',
                'confidence' => 0.5,
                'raw_text' => $text
            ];
        } catch (Exception $e) {
            Log::error('GeminiService::parseOcrResponse - Error: ' . $e->getMessage());
            return [
                'ho_ten' => '',
                'id_number' => '',
                'doi_tuong' => '',
                'confidence' => 0.0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Lấy OCR prompt tùy theo loại giấy tờ
     */
    private function getOcrPrompt(string $documentType): string
    {
        $prompts = [
            'cccd' => <<<'PROMPT'
                Đọc ảnh thẻ Căn Cước Công Dân này. Trích xuất CHÍNH XÁC:
                - Họ và tên: (lấy chữ thường, chuẩn hóa)
                - Số CCCD: (12 chữ số)
                - Ngày sinh: (DD/MM/YYYY)
                - Giới tính: (Nam/Nữ/Khác)

                TRẢ VỀ DỪNG MỘT JSON CẤU TRÚC CỐ ĐỊNH:
                {
                  "ho_ten": "Nguyễn Văn A",
                  "id_number": "001234567890",
                  "ngay_sinh": "15/01/2005",
                  "gioi_tinh": "Nam",
                  "confidence": 0.95
                }

                CHỈ TRẢ VỀ JSON, KHÔNG TRẢ LỜI KHÁC.
                PROMPT,
            'ho_khau' => <<<'PROMPT'
                Đọc ảnh Sổ Hộ Khẩu hoặc Giấy Đăng Ký Thường Trú. Trích xuất CHÍNH XÁC:
                - Chủ hộ: (tên chủ hộ)
                - Số hộ khẩu: (mã số)
                - Địa chỉ thường trú: (đầy đủ)

                TRẢ VỀ JSON CẤU TRÚC CỐ ĐỊNH:
                {
                  "chu_ho": "Nguyễn Văn A",
                  "so_ho_khau": "123456789",
                  "dia_chi": "Số 10 Đường Trần Hưng Đạo, Phường 1, Quận 1, TP. Hồ Chí Minh",
                  "confidence": 0.92
                }

                CHỈ TRẢ VỀ JSON, KHÔNG TRẢ LỜI KHÁC.
                PROMPT,
            'ho_ngheo' => <<<'PROMPT'
                Đọc ảnh Giấy Chứng Nhận Hộ Nghèo. Trích xuất CHÍNH XÁC:
                - Chủ hộ: (tên chủ hộ)
                - Mã hộ nghèo: (mã số cấp)
                - Ngày cấp: (ngày)
                - Cơ quan cấp: (UBND/Phòng/...)
                - Căn cứ: (căn cứ pháp luật)

                TRẢ VỀ JSON CẤU TRÚC CỐ ĐỊNH:
                {
                  "chu_ho": "Nguyễn Văn A",
                  "ma_ho_ngheo": "123456789",
                  "ngay_cap": "15/01/2024",
                  "co_quan_cap": "UBND Xã/Phường X",
                  "can_cu": "Nghị định 81/2021",
                  "confidence": 0.95
                }

                CHỈ TRẢ VỀ JSON, KHÔNG TRẢ LỜI KHÁC.
                PROMPT,
            'khai_sinh' => <<<'PROMPT'
                Đọc ảnh Giấy Khai Sinh (Bản sao công chứng). Trích xuất CHÍNH XÁC:
                - Họ và tên: (chuẩn hóa)
                - Ngày sinh: (DD/MM/YYYY)
                - Tên cha: (tên cha đầy đủ)
                - Tên mẹ: (tên mẹ đầy đủ)

                TRẢ VỀ JSON CẤU TRÚC CỐ ĐỊNH:
                {
                  "ho_ten": "Nguyễn Văn A",
                  "ngay_sinh": "15/01/2005",
                  "ten_cha": "Nguyễn Văn X",
                  "ten_me": "Nguyễn Thị Y",
                  "confidence": 0.93
                }

                CHỈ TRẢ VỀ JSON, KHÔNG TRẢ LỜI KHÁC.
                PROMPT,
            'default' => <<<'PROMPT'
                Đọc ảnh tài liệu này và trích xuất tất cả thông tin quan trọng.
                Trả về JSON với các trường: name, date, id, type, details, confidence.

                {
                  "name": "...",
                  "id": "...",
                  "date": "...",
                  "type": "...",
                  "details": {...},
                  "confidence": 0.9
                }

                CHỈ TRẢ VỀ JSON, KHÔNG TRẢ LỜI KHÁC.
                PROMPT
        ];

        return $prompts[$documentType] ?? $prompts['default'];
    }
}
