<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * RerankingService - Rerank chunks by relevance
 *
 * Single Responsibility: Rerank search results
 * - Takes top N chunks from ChromaDB
 * - Reranks by relevance (currently: similarity score)
 * - Returns top K chunks
 *
 * Interface for future reranker implementations
 * (Could use CrossEncoder, semantic similarity, BM25, etc.)
 *
 * @package App\Services
 */
class RerankingService
{
    /**
     * Rerank chunks for better relevance
     *
     * Current implementation: Simple sorting by similarity
     * Future implementations can add:
     * - Cross-encoder models
     * - BM25 ranking
     * - Machine learning models
     *
     * @param array $chunks - [{text, similarity, metadata}, ...]
     * @param string $query - Original query for context
     * @param int $topK - Number of chunks to return (default 5)
     * @return array - Reranked top K chunks
     */
    public function rerank(array $chunks, string $query, int $topK = 5): array
    {
        try {
            if (empty($chunks)) {
                return [];
            }

            Log::debug('RerankingService::rerank - Starting', [
                'input_chunks' => count($chunks),
                'target_topk' => $topK,
            ]);

            // STEP 1: Score each chunk
            $scored = $this->scoreChunks($chunks, $query);

            // STEP 2: Sort by score (descending)
            usort($scored, function ($a, $b) {
                return $b['score'] <=> $a['score'];
            });

            // STEP 3: Take top K
            $reranked = array_slice($scored, 0, $topK);

            // STEP 4: Restore original format (without score field for clean output)
            $result = array_map(function ($chunk) {
                return [
                    'text' => $chunk['text'],
                    'similarity' => $chunk['similarity'],
                    'metadata' => $chunk['metadata'],
                ];
            }, $reranked);

            Log::debug('RerankingService::rerank - Complete', [
                'output_chunks' => count($result),
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::warning('RerankingService::rerank - Error, returning original chunks', [
                'error' => $e->getMessage(),
            ]);
            // Fallback: return original chunks sorted by similarity
            return $this->simpleSort($chunks, $topK);
        }
    }

    /**
     * Score chunks based on multiple factors
     *
     * Currently:
     * - Vector similarity (80% weight)
     * - Text length (10% weight - prefer complete info, not fragments)
     * - Metadata quality (10% weight - prefer specific Điều/Khoản)
     *
     * Future: Could use ML models for scoring
     *
     * @param array $chunks
     * @param string $query
     * @return array - Chunks with score field added
     */
    private function scoreChunks(array $chunks, string $query): array
    {
        return array_map(function ($chunk) use ($query) {
            $similarity = $chunk['similarity'] ?? 0.5;
            $textScore = $this->scoreTextQuality($chunk['text'] ?? '');
            $metadataScore = $this->scoreMetadata($chunk['metadata'] ?? []);

            // Weighted score
            $score = (
                ($similarity * 0.8)
                + ($textScore * 0.1)
                + ($metadataScore * 0.1)
            );

            $chunk['score'] = $score;
            return $chunk;
        }, $chunks);
    }

    /**
     * Score text quality
     *
     * Considers:
     * - Length (100-500 chars optimal)
     * - Contains numbers/statistics
     * - Starts with important section (Điều, Khoản)
     *
     * @param string $text
     * @return float - 0 to 1
     */
    private function scoreTextQuality(string $text): float
    {
        $score = 0.5;  // Baseline

        $length = strlen($text);

        // Length score (100-500 chars optimal)
        if ($length >= 100 && $length <= 500) {
            $score += 0.3;
        } elseif ($length >= 50 && $length <= 1000) {
            $score += 0.15;
        }

        // Contains structured info (numbers, percentages)
        if (preg_match('/\d+\s*%|\d+\s*điểm|\d+\s*vnd/ui', $text)) {
            $score += 0.2;
        }

        // Starts with legal section
        if (preg_match('/^(Điều|Khoản|Chương)/u', trim($text))) {
            $score += 0.2;
        }

        return min($score, 1.0);
    }

    /**
     * Score metadata quality
     *
     * Chunks with complete metadata (article, clause, point) score higher
     *
     * @param array $metadata
     * @return float - 0 to 1
     */
    private function scoreMetadata(array $metadata): float
    {
        $score = 0;

        // Article number present
        if (!empty($metadata['article'])) {
            $score += 0.3;
        }

        // Clause number present
        if (!empty($metadata['clause'])) {
            $score += 0.3;
        }

        // Point present
        if (!empty($metadata['point'])) {
            $score += 0.2;
        }

        // Type is specific (not generic)
        $type = $metadata['type'] ?? '';
        if (in_array($type, ['point', 'clause', 'article'])) {
            $score += 0.2;
        }

        return min($score, 1.0);
    }

    /**
     * Simple sort fallback (if scoring fails)
     *
     * Just sort by similarity, take top K
     *
     * @param array $chunks
     * @param int $topK
     * @return array
     */
    private function simpleSort(array $chunks, int $topK): array
    {
        usort($chunks, function ($a, $b) {
            return ($b['similarity'] ?? 0) <=> ($a['similarity'] ?? 0);
        });

        return array_slice($chunks, 0, $topK);
    }

    /**
     * Calculate chunk relevance to query
     *
     * Simple heuristic: how many query words appear in chunk?
     *
     * @param array $chunk
     * @param string $query
     * @return float - 0 to 1
     */
    public function calculateRelevance(array $chunk, string $query): float
    {
        $text = strtolower($chunk['text'] ?? '');
        $query = strtolower($query);

        // Extract words
        $queryWords = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($queryWords)) {
            return 0.5;
        }

        // Count matches
        $matches = 0;
        foreach ($queryWords as $word) {
            if (strlen($word) > 3 && strpos($text, $word) !== false) {
                $matches++;
            }
        }

        // Percentage of query words found
        return min(1.0, $matches / count($queryWords));
    }
}
