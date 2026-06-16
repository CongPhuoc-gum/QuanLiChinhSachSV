<?php declare(strict_types=1);

namespace App\Services;

use App\Common\Exceptions\RAGException;
use Illuminate\Support\Facades\Log;

/**
 * RAGPipelineService - Orchestrates RAG pipeline
 *
 * Single Responsibility: Coordinate RAG flow
 * - Takes question as input
 * - Orchestrates: Cache → VectorSearch → ContextBuilder → Generation
 * - Returns complete answer with citations
 *
 * @package App\Services
 */
class RAGPipelineService
{
    private SemanticCacheService $cacheService;
    private VectorSearchService $vectorSearchService;
    private ContextBuilderService $contextBuilderService;
    private GenerationService $generationService;
    private CitationService $citationService;

    public function __construct(
        SemanticCacheService $cacheService = null,
        VectorSearchService $vectorSearchService = null,
        ContextBuilderService $contextBuilderService = null,
        GenerationService $generationService = null,
        CitationService $citationService = null
    ) {
        $this->cacheService = $cacheService ?? new SemanticCacheService(new GeminiService());
        $this->vectorSearchService = $vectorSearchService ?? new VectorSearchService();
        $this->contextBuilderService = $contextBuilderService ?? new ContextBuilderService();
        $this->generationService = $generationService ?? new GenerationService();
        $this->citationService = $citationService ?? new CitationService();
    }

    /**
     * Ask question using advanced RAG pipeline
     *
     * Flow:
     * 1. Check semantic cache (instant answer)
     * 2. If miss: Vector search for top 20 chunks
     * 3. Rerank top 20 → select top 5
     * 4. Build context from top 5
     * 5. Call Gemini to generate answer
     * 6. Extract citations (metadata-first, regex fallback)
     * 7. Return complete answer with citations
     *
     * @param string $userQuestion - User's question
     * @return array - {success, answer, citations, method, metadata}
     */
    public function ask(string $userQuestion): array
    {
        try {
            Log::info('RAGPipelineService::ask - Starting', ['question' => $userQuestion]);

            // ===== STEP 1: CHECK CACHE =====
            $cached = $this->cacheService->get($userQuestion);
            if ($cached) {
                Log::info('RAGPipelineService::ask - Cache HIT');
                return [
                    'success' => true,
                    'answer' => $cached['answer'],
                    'citations' => $cached['citations'] ?? [],
                    'method' => 'cache',
                    'metadata' => [
                        'similarity' => $cached['similarity'] ?? 1.0,
                        'source' => 'semantic_cache',
                    ],
                ];
            }

            // ===== STEP 2: VECTOR SEARCH =====
            Log::debug('RAGPipelineService::ask - Searching vectors...');
            $chunks = $this->vectorSearchService->search($userQuestion, topK: 20);

            if (empty($chunks)) {
                return [
                    'success' => true,
                    'answer' => 'Thông tin này không có trong Nghị định 81/2021. Vui lòng liên hệ Phòng CTSV.',
                    'citations' => [],
                    'method' => 'no_match',
                    'metadata' => ['retrieved_chunks' => 0],
                ];
            }

            Log::debug('RAGPipelineService::ask - Vector search found chunks', [
                'count' => count($chunks),
            ]);

            // ===== STEP 3: RERANK (Top 20 → Top 5) =====
            $reranker = new RerankingService();
            $topChunks = $reranker->rerank($chunks, $userQuestion, topK: 5);
            Log::debug('RAGPipelineService::ask - Reranked to top chunks', [
                'selected' => count($topChunks),
            ]);

            // ===== STEP 4: BUILD CONTEXT =====
            $context = $this->contextBuilderService->build($topChunks);
            $contextTokens = $this->contextBuilderService->estimateTokens($context);
            Log::debug('RAGPipelineService::ask - Context built', [
                'estimated_tokens' => $contextTokens,
            ]);

            // ===== STEP 5: GENERATE ANSWER =====
            $answer = $this->generationService->generate($userQuestion, $context);
            Log::debug('RAGPipelineService::ask - Answer generated', [
                'answer_length' => strlen($answer),
            ]);

            // ===== STEP 6: EXTRACT CITATIONS (metadata-first) =====
            $citations = [];
            if (!empty($topChunks)) {
                // Try metadata first from top chunk
                $metadata = $topChunks[0]['metadata'] ?? [];
                $citations = $this->citationService->extract($answer, $metadata);
            }
            if (empty($citations)) {
                // Fallback to regex
                $citations = $this->citationService->extract($answer);
            }
            Log::debug('RAGPipelineService::ask - Citations extracted', [
                'citations_count' => count($citations),
            ]);

            // ===== RETURN RESULT =====
            return [
                'success' => true,
                'answer' => $answer,
                'citations' => $citations,
                'method' => 'advanced_rag',
                'metadata' => [
                    'retrieved_chunks' => count($chunks),
                    'ranked_chunks' => count($topChunks),
                    'context_tokens' => $contextTokens,
                    'answer_tokens' => ceil(strlen($answer) / 4),
                ],
            ];
        } catch (RAGException $e) {
            Log::error('RAGPipelineService::ask - RAG Error', [
                'error' => $e->getMessage(),
                'question' => $userQuestion,
            ]);

            return [
                'success' => false,
                'message' => 'Lỗi xử lý câu hỏi',
                'error' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            Log::error('RAGPipelineService::ask - Unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Lỗi hệ thống',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * Get health status of pipeline components
     *
     * Useful for monitoring
     *
     * @return array - {cache_healthy, vector_search_healthy, generation_healthy}
     */
    public function getHealthStatus(): array
    {
        return [
            'cache_working' => $this->testCache(),
            'vector_search_working' => $this->vectorSearchService->isHealthy(),
            'generation_working' => true,  // TODO: Add health check to GenerationService
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Test cache functionality
     *
     * @return bool
     */
    private function testCache(): bool
    {
        try {
            // Try to get from cache (should return null)
            $result = $this->cacheService->get('test query');
            return true;
        } catch (\Exception $e) {
            Log::warning('RAGPipelineService::testCache - Failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Warm up cache by adding common questions
     *
     * @param array $qaPairs - [{question, answer, citations}, ...]
     * @return int - Number of entries added
     */
    public function warmupCache(array $qaPairs): int
    {
        $added = 0;

        foreach ($qaPairs as $pair) {
            try {
                $this->cacheService->put(
                    $pair['question'] ?? '',
                    $pair['answer'] ?? '',
                    $pair['citations'] ?? [],
                    $pair['metadata'] ?? []
                );
                $added++;
            } catch (\Exception $e) {
                Log::warning('RAGPipelineService::warmupCache - Failed to add entry', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('RAGPipelineService::warmupCache - Complete', [
            'total' => count($qaPairs),
            'added' => $added,
        ]);

        return $added;
    }

    /**
     * Get pipeline configuration
     *
     * @return array
     */
    public function getConfiguration(): array
    {
        return [
            'cache_type' => 'semantic_vector',
            'vector_search_topk' => 20,
            'rerank_topk' => 5,
            'context_max_tokens' => 2000,
            'generation_max_tokens' => 500,
            'generation_temperature' => 0.0,
            'vector_similarity_threshold' => 0.5,
        ];
    }
}
