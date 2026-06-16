<?php declare(strict_types=1);

namespace App\Services;

use App\Common\Exceptions\CacheException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * SemanticCacheService - Thay thế keyword matching bằng vector similarity
 *
 * Sử dụng vector embeddings để tìm câu hỏi tương tự từ cache.
 * Thay vì so khớp keywords (O(n×m) complexity, sai nhiều),
 * dùng cosine similarity giữa embedding vectors (O(n) complexity, chính xác hơn).
 *
 * @package App\Services
 */
class SemanticCacheService
{
    /**
     * Cache hit threshold (0-1)
     * Nếu similarity > 0.95 => cache hit
     */
    private const SIMILARITY_THRESHOLD = 0.95;

    /**
     * Cache file path
     */
    private string $cachePath;

    /**
     * Cache data (loaded once from file)
     */
    private array $cacheData = [];

    /**
     * Constructor
     *
     * @param GeminiService $geminiService - Dùng để tạo embeddings
     */
    public function __construct(
        private GeminiService $geminiService
    ) {
        $this->cachePath = env('AI_QA_PATH', 'ai/qa_pairs.json');
        $this->loadCache();
    }

    /**
     * Tìm câu trả lời từ cache dựa trên semantic similarity
     *
     * Flow:
     * 1. Nhận câu hỏi từ user
     * 2. Tạo embedding cho câu hỏi
     * 3. So sánh với tất cả embeddings trong cache
     * 4. Nếu có entry có similarity > threshold => return
     * 5. Nếu không => return null
     *
     * @param string $question - Câu hỏi từ user
     * @return array|null - ['answer' => '', 'citations' => [], 'similarity' => 0.98] hoặc null
     * @throws CacheException
     */
    public function get(string $question): ?array
    {
        try {
            if (empty($this->cacheData)) {
                return null;
            }

            // Tạo embedding cho câu hỏi
            $queryVector = $this->geminiService->generateEmbedding($question);

            // Tìm cache entry có similarity cao nhất
            $bestMatch = null;
            $bestSimilarity = 0;

            foreach ($this->cacheData as $entry) {
                if (empty($entry['vector'])) {
                    continue;
                }

                $similarity = $this->cosineSimilarity($queryVector, $entry['vector']);

                if ($similarity > $bestSimilarity) {
                    $bestSimilarity = $similarity;
                    $bestMatch = $entry;
                }
            }

            // Nếu similarity cao hơn threshold => cache hit
            if ($bestSimilarity >= self::SIMILARITY_THRESHOLD && $bestMatch !== null) {
                Log::info('SemanticCacheService::get - Cache HIT', [
                    'question' => $question,
                    'similarity' => $bestSimilarity,
                ]);

                return [
                    'success' => true,
                    'answer' => $bestMatch['answer'] ?? 'Không có câu trả lời',
                    'citations' => $bestMatch['citations'] ?? [],
                    'method' => 'semantic_cache',
                    'similarity' => round($bestSimilarity, 3),
                ];
            }

            Log::debug('SemanticCacheService::get - Cache MISS', [
                'question' => $question,
                'best_similarity' => $bestSimilarity,
                'threshold' => self::SIMILARITY_THRESHOLD,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::warning('SemanticCacheService::get - Error', [
                'message' => $e->getMessage(),
                'question' => $question,
            ]);
            return null;
        }
    }

    /**
     * Thêm entry mới vào cache
     *
     * @param string $question - Câu hỏi
     * @param string $answer - Câu trả lời
     * @param array $citations - Danh sách trích dẫn
     * @param array $metadata - Metadata (type, intent, entities, keywords)
     * @return void
     * @throws CacheException
     */
    public function put(string $question, string $answer, array $citations = [], array $metadata = []): void
    {
        try {
            // Tạo embedding cho câu hỏi
            $vector = $this->geminiService->generateEmbedding($question);

            $entry = [
                'question' => $question,
                'answer' => $answer,
                'vector' => $vector,
                'citations' => $citations,
                'analysis' => $metadata,
                'confidence' => 1.0,
                'source' => 'manual_cache',
                'created_at' => now()->toIso8601String(),
            ];

            // Thêm vào cache data
            $this->cacheData[] = $entry;

            // Lưu vào file
            $this->saveCache();

            Log::info('SemanticCacheService::put - Cache entry added', [
                'question' => $question,
                'total_entries' => count($this->cacheData),
            ]);
        } catch (\Exception $e) {
            Log::error('SemanticCacheService::put - Error', [
                'message' => $e->getMessage(),
                'question' => $question,
            ]);
            throw new CacheException("Failed to add cache entry: {$e->getMessage()}");
        }
    }

    /**
     * Xóa toàn bộ cache
     *
     * @return void
     */
    public function flush(): void
    {
        try {
            $this->cacheData = [];
            Storage::disk('local')->put($this->cachePath, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            Log::info('SemanticCacheService::flush - Cache flushed');
        } catch (\Exception $e) {
            Log::error('SemanticCacheService::flush - Error', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Lấy tổng số entry trong cache
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->cacheData);
    }

    /**
     * Lấy tất cả cache entries
     *
     * @return array
     */
    public function all(): array
    {
        return $this->cacheData;
    }

    /**
     * Tính cosine similarity giữa 2 vectors
     *
     * Formula:
     * cosine_similarity = (A · B) / (||A|| * ||B||)
     *
     * Kết quả: 0 = không giống, 1 = giống hệt nhau
     *
     * @param array $v1 - Vector 1
     * @param array $v2 - Vector 2
     * @return float - Giá trị từ 0 đến 1
     */
    private function cosineSimilarity(array $v1, array $v2): float
    {
        if (empty($v1) || empty($v2)) {
            return 0;
        }

        // Đảm bảo cùng kích thước
        if (count($v1) !== count($v2)) {
            return 0;
        }

        // Tính dot product (A · B)
        $dotProduct = 0;
        for ($i = 0; $i < count($v1); $i++) {
            $dotProduct += $v1[$i] * $v2[$i];
        }

        // Tính magnitude ||A|| và ||B||
        $magnitudeV1 = sqrt(array_sum(array_map(fn($x) => $x ** 2, $v1)));
        $magnitudeV2 = sqrt(array_sum(array_map(fn($x) => $x ** 2, $v2)));

        // Tránh chia cho 0
        if ($magnitudeV1 == 0 || $magnitudeV2 == 0) {
            return 0;
        }

        return $dotProduct / ($magnitudeV1 * $magnitudeV2);
    }

    /**
     * Load cache từ file
     *
     * @return void
     */
    private function loadCache(): void
    {
        try {
            if (!Storage::disk('local')->exists($this->cachePath)) {
                $this->cacheData = [];
                return;
            }

            $json = Storage::disk('local')->get($this->cachePath);
            $data = json_decode($json, true) ?? [];

            // Chuyển format cũ (chỉ có keywords, answer, citations) sang format mới (có vector)
            $this->cacheData = array_map(function ($entry) {
                // Nếu chưa có vector => tạo mới
                if (empty($entry['vector'])) {
                    try {
                        $question = $entry['question'] ?? implode(' ', $entry['keywords'] ?? []);
                        $entry['vector'] = $this->geminiService->generateEmbedding($question);
                    } catch (\Exception $e) {
                        Log::warning('SemanticCacheService::loadCache - Failed to generate vector', [
                            'entry' => $entry,
                            'error' => $e->getMessage(),
                        ]);
                        $entry['vector'] = [];
                    }
                }

                return $entry;
            }, $data);

            Log::debug('SemanticCacheService::loadCache - Loaded cache', [
                'entries' => count($this->cacheData),
            ]);
        } catch (\Exception $e) {
            Log::warning('SemanticCacheService::loadCache - Error', [
                'message' => $e->getMessage(),
            ]);
            $this->cacheData = [];
        }
    }

    /**
     * Lưu cache vào file
     *
     * @return void
     */
    private function saveCache(): void
    {
        try {
            $json = json_encode($this->cacheData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            Storage::disk('local')->put($this->cachePath, $json);
        } catch (\Exception $e) {
            Log::error('SemanticCacheService::saveCache - Error', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
