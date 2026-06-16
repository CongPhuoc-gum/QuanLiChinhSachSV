<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * ContextBuilderService - Build context from retrieved chunks
 *
 * Single Responsibility: Build context ONLY
 * - Takes retrieved chunks as input
 * - Formats them into readable context for Gemini
 * - Optimizes token usage
 *
 * @package App\Services
 */
class ContextBuilderService
{
    /**
     * Build context string from chunks
     *
     * Format:
     * KIẾN THỨC CƠ SỞ (NHỮNG ĐOẠN LIÊN QUAN):
     *
     * • [Điều 3] (Độ liên quan: 98%)
     *   Text of the chunk...
     *
     * • [Điều 4] (Độ liên quan: 95%)
     *   Text of the chunk...
     *
     * @param array $chunks - Retrieved chunks [{text, similarity, metadata}, ...]
     * @return string - Formatted context
     */
    public function build(array $chunks): string
    {
        if (empty($chunks)) {
            return 'KIẾN THỨC CƠ SỞ: Không tìm thấy đoạn liên quan.';
        }

        $context = "KIẾN THỨC CƠ SỞ (NHỮNG ĐOẠN LIÊN QUAN):\n\n";

        foreach ($chunks as $chunk) {
            $similarity = round(($chunk['similarity'] ?? 0.5) * 100, 0);
            $source = $this->extractSource($chunk);

            $context .= "• [{$source}] (Độ liên quan: {$similarity}%)\n";
            $context .= '  ' . trim($chunk['text']) . "\n\n";
        }

        return $context;
    }

    /**
     * Build context with detailed metadata
     *
     * More verbose format including full metadata
     *
     * @param array $chunks
     * @return string
     */
    public function buildDetailed(array $chunks): string
    {
        if (empty($chunks)) {
            return 'KIẾN THỨC CƠ SỞ: Không tìm thấy đoạn liên quan.';
        }

        $context = "KIẾN THỨC CƠ SỞ CHI TIẾT:\n\n";

        foreach ($chunks as $idx => $chunk) {
            $similarity = round(($chunk['similarity'] ?? 0.5) * 100, 0);
            $source = $this->extractSource($chunk);
            $metadata = $chunk['metadata'] ?? [];

            $context .= '[Đoạn ' . ($idx + 1) . "]\n";
            $context .= "Nguồn: {$source}\n";
            $context .= "Độ liên quan: {$similarity}%\n";

            if (!empty($metadata)) {
                $context .= 'Metadata: ' . json_encode($metadata, JSON_UNESCAPED_UNICODE) . "\n";
            }

            $context .= "Nội dung:\n{$chunk['text']}\n\n";
        }

        return $context;
    }

    /**
     * Extract source info from chunk metadata
     *
     * @param array $chunk
     * @return string - "Điều 3" or "Nghị định 81/2021" or metadata source
     */
    private function extractSource(array $chunk): string
    {
        $metadata = $chunk['metadata'] ?? [];

        // Try: dinh_section (Điều X)
        if (!empty($metadata['dinh_section'])) {
            return $metadata['dinh_section'];
        }

        // Try: clause (for structured law data)
        if (!empty($metadata['clause'])) {
            return "Điều {$metadata['clause']}";
        }

        // Try: article (alternative field)
        if (!empty($metadata['article'])) {
            return "Điều {$metadata['article']}";
        }

        // Default
        return $metadata['source'] ?? 'Nghị định 81/2021';
    }

    /**
     * Estimate token count for context
     *
     * Rough estimation: 1 token ≈ 4 characters
     *
     * @param string $context
     * @return int - Estimated token count
     */
    public function estimateTokens(string $context): int
    {
        return (int) ceil(strlen($context) / 4);
    }

    /**
     * Truncate context if too long
     *
     * Keeps most relevant chunks (highest similarity)
     * Ensures context stays within token limit
     *
     * @param array $chunks - Sorted by similarity descending
     * @param int $maxTokens - Maximum tokens to use
     * @return array - Filtered chunks
     */
    public function truncateByTokens(array $chunks, int $maxTokens = 2000): array
    {
        $currentTokens = 0;
        $filtered = [];

        foreach ($chunks as $chunk) {
            $chunkText = $chunk['text'] ?? '';
            $chunkTokens = $this->estimateTokens($chunkText);

            if ($currentTokens + $chunkTokens <= $maxTokens) {
                $filtered[] = $chunk;
                $currentTokens += $chunkTokens;
            } else {
                // Reached token limit, stop adding chunks
                break;
            }
        }

        Log::info('ContextBuilderService::truncateByTokens', [
            'total_chunks' => count($chunks),
            'filtered_chunks' => count($filtered),
            'tokens_used' => $currentTokens,
            'max_tokens' => $maxTokens,
        ]);

        return $filtered;
    }

    /**
     * Merge duplicate/overlapping chunks
     *
     * If two chunks have high text similarity, merge them
     *
     * @param array $chunks
     * @param float $overlapThreshold - 0-1, default 0.8
     * @return array - Merged chunks
     */
    public function mergeDuplicates(array $chunks, float $overlapThreshold = 0.8): array
    {
        if (count($chunks) <= 1) {
            return $chunks;
        }

        $merged = [];
        $used = [];

        foreach ($chunks as $idx => $chunk) {
            if (in_array($idx, $used)) {
                continue;
            }

            $merged[] = $chunk;
            $used[] = $idx;

            // Check for similar chunks
            for ($j = $idx + 1; $j < count($chunks); $j++) {
                if (in_array($j, $used)) {
                    continue;
                }

                $similarity = $this->calculateTextSimilarity(
                    $chunk['text'] ?? '',
                    $chunks[$j]['text'] ?? ''
                );

                if ($similarity >= $overlapThreshold) {
                    $used[] = $j;  // Mark as used (skip this chunk)
                }
            }
        }

        Log::debug('ContextBuilderService::mergeDuplicates', [
            'original' => count($chunks),
            'merged' => count($merged),
        ]);

        return $merged;
    }

    /**
     * Calculate text similarity (simple)
     *
     * Uses Levenshtein distance as a rough measure
     *
     * @param string $text1
     * @param string $text2
     * @return float - 0 to 1
     */
    private function calculateTextSimilarity(string $text1, string $text2): float
    {
        if (empty($text1) || empty($text2)) {
            return 0;
        }

        // Levenshtein distance
        $distance = levenshtein($text1, $text2);
        $maxLen = max(strlen($text1), strlen($text2));

        if ($maxLen === 0) {
            return 1;
        }

        return 1 - ($distance / $maxLen);
    }

    /**
     * Format chunks for logging/debugging
     *
     * @param array $chunks
     * @return string
     */
    public function formatForLogging(array $chunks): string
    {
        $formatted = "=== Context Chunks ===\n";
        $formatted .= 'Total: ' . count($chunks) . " chunks\n\n";

        foreach ($chunks as $idx => $chunk) {
            $text = substr($chunk['text'] ?? '', 0, 100);  // First 100 chars
            $similarity = round(($chunk['similarity'] ?? 0) * 100, 1);
            $formatted .= "[$idx] Similarity: {$similarity}% | {$text}...\n";
        }

        return $formatted;
    }
}
