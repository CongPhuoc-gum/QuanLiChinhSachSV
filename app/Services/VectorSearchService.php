<?php declare(strict_types=1);

namespace App\Services;

use App\Common\Exceptions\VectorSearchException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * VectorSearchService - Search ChromaDB using vector similarity
 *
 * Single Responsibility: Vector search ONLY
 * - Takes query embedding as input
 * - Searches ChromaDB for similar chunks
 * - Returns relevant chunks with metadata
 *
 * @package App\Services
 */
class VectorSearchService
{
    private EmbeddingService $embeddingService;
    private string $chromaUrl = 'http://localhost:8000';
    private string $collectionName = 'nghidinh81';
    private float $similarityThreshold = 0.5;

    public function __construct(EmbeddingService $embeddingService = null)
    {
        $this->embeddingService = $embeddingService ?? new EmbeddingService();
        $this->chromaUrl = env('CHROMA_URL', 'http://localhost:8000');
        $this->collectionName = env('CHROMA_COLLECTION', 'nghidinh81');
        $this->similarityThreshold = (float) env('CHROMA_SIMILARITY_THRESHOLD', 0.5);
    }

    /**
     * Search for similar chunks using query text
     *
     * Flow:
     * 1. Generate embedding for query (using EmbeddingService)
     * 2. Search ChromaDB for similar chunks
     * 3. Filter by similarity threshold
     * 4. Return sorted by similarity
     *
     * @param string $query - User question
     * @param int $topK - Number of chunks to retrieve (default 20, then rerank to 5)
     * @return array - [{text, similarity, metadata}, ...]
     * @throws VectorSearchException
     */
    public function search(string $query, int $topK = 20): array
    {
        try {
            if (empty($query)) {
                throw new VectorSearchException('Query cannot be empty');
            }

            // STEP 1: Generate embedding for query
            $queryEmbedding = $this->embeddingService->generate($query);

            // STEP 2: Search ChromaDB
            $chunks = $this->searchByEmbedding($queryEmbedding, $topK);

            Log::info('VectorSearchService::search - Complete', [
                'query' => $query,
                'found_chunks' => count($chunks),
            ]);

            return $chunks;
        } catch (VectorSearchException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('VectorSearchService::search - Error', [
                'message' => $e->getMessage(),
                'query' => $query,
            ]);
            throw new VectorSearchException("Search failed: {$e->getMessage()}");
        }
    }

    /**
     * Search ChromaDB by embedding vector
     *
     * Internal method - used by search() and others
     *
     * @param array $queryEmbedding - Embedding vector
     * @param int $topK - Number of results
     * @return array - Sorted chunks [{text, similarity, metadata}, ...]
     * @throws VectorSearchException
     */
    public function searchByEmbedding(array $queryEmbedding, int $topK = 20): array
    {
        try {
            if (empty($queryEmbedding)) {
                throw new VectorSearchException('Embedding cannot be empty');
            }

            $response = Http::timeout(10)->post(
                "{$this->chromaUrl}/api/v1/collections/{$this->collectionName}/query",
                [
                    'query_embeddings' => [$queryEmbedding],
                    'n_results' => $topK,
                    'where' => ['source' => 'nghidinh81.txt'],
                    'include' => ['documents', 'metadatas', 'distances']
                ]
            );

            if (!$response->successful()) {
                Log::error('VectorSearchService::searchByEmbedding - ChromaDB query failed', [
                    'status' => $response->status(),
                    'chroma_url' => $this->chromaUrl,
                ]);
                throw new VectorSearchException("ChromaDB returned status {$response->status()}");
            }

            $results = $response->json();
            $chunks = [];

            // Parse ChromaDB results
            if (isset($results['documents'][0]) && is_array($results['documents'][0])) {
                foreach ($results['documents'][0] as $idx => $document) {
                    // ChromaDB returns distances (lower = more similar)
                    // Convert to similarity score (0-1, higher = more similar)
                    $distance = $results['distances'][0][$idx] ?? 1.0;
                    $similarity = 1 / (1 + $distance);

                    // Filter by threshold
                    if ($similarity >= $this->similarityThreshold) {
                        $chunks[] = [
                            'text' => $document,
                            'similarity' => (float) $similarity,
                            'metadata' => $results['metadatas'][0][$idx] ?? [],
                        ];
                    }
                }
            }

            // Sort by similarity (descending)
            usort($chunks, function ($a, $b) {
                return $b['similarity'] <=> $a['similarity'];
            });

            Log::debug('VectorSearchService::searchByEmbedding - Success', [
                'chunks_found' => count($chunks),
                'threshold' => $this->similarityThreshold,
            ]);

            return $chunks;
        } catch (VectorSearchException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('VectorSearchService::searchByEmbedding - Error', [
                'message' => $e->getMessage(),
            ]);
            throw new VectorSearchException("ChromaDB search failed: {$e->getMessage()}");
        }
    }

    /**
     * Get Chroma server health
     *
     * @return bool - true if Chroma is healthy
     */
    public function isHealthy(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->chromaUrl}/api/v1/heartbeat");
            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('VectorSearchService::isHealthy - Chroma not responding', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get ChromaDB collection info
     *
     * @return array - Collection metadata or empty array if error
     */
    public function getCollectionInfo(): array
    {
        try {
            $response = Http::timeout(5)->get(
                "{$this->chromaUrl}/api/v1/collections/{$this->collectionName}"
            );

            if ($response->successful()) {
                return $response->json();
            }

            return [];
        } catch (\Exception $e) {
            Log::warning('VectorSearchService::getCollectionInfo - Error', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Set similarity threshold for filtering
     *
     * @param float $threshold - 0.0 to 1.0
     * @return void
     */
    public function setSimilarityThreshold(float $threshold): void
    {
        if ($threshold < 0 || $threshold > 1) {
            throw new \InvalidArgumentException('Threshold must be between 0 and 1');
        }
        $this->similarityThreshold = $threshold;
    }
}
