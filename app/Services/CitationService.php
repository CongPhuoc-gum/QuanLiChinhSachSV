<?php declare(strict_types=1);

namespace App\Services;

/**
 * CitationService - Extract citations from text
 *
 * Replaces duplicate extractCitations() logic in:
 * - ImprovedRAGService
 * - DynamicRAGTrainingService
 *
 * Single responsibility: Extract "Điều X" patterns
 */
class CitationService
{
    /**
     * Extract citations (Điều X patterns) from text
     *
     * Strategy: Metadata-first, then regex fallback
     * 1. If metadata provided → use metadata citations
     * 2. If no metadata → extract from text using regex
     *
     * @param string $text Text to search for citations
     * @param array $metadata Optional metadata from chunk {article, clause, ...}
     * @return array Array of unique citations like ["Điều 4", "Điều 5"]
     */
    public function extract(string $text, array $metadata = []): array
    {
        try {
            // STEP 1: Try metadata first (highest priority)
            if (!empty($metadata)) {
                $citations = $this->extractFromMetadata($metadata);
                if (!empty($citations)) {
                    return $citations;
                }
            }

            // STEP 2: Fallback to regex extraction
            preg_match_all('/Điều\s+\d+/u', $text, $matches);
            return array_unique($matches[0] ?? []);
        } catch (\Exception $e) {
            // Return what we can from regex
            preg_match_all('/Điều\s+\d+/u', $text, $matches);
            return array_unique($matches[0] ?? []);
        }
    }

    /**
     * Extract citations from metadata (preferred method)
     *
     * Metadata comes from smart chunking and contains:
     * - article: Article number
     * - clause: Clause number (Khoản)
     * - point: Point (Điểm)
     *
     * @param array $metadata {article, clause, point, ...}
     * @return array - Citations like ["Điều 3", "Khoản 2", ...]
     */
    private function extractFromMetadata(array $metadata): array
    {
        $citations = [];

        // Add article citation (primary)
        if (!empty($metadata['article'])) {
            $citations[] = 'Điều ' . $metadata['article'];
        }

        return array_unique($citations);
    }

    /**
     * Extract citations with line numbers (for detailed citation tracking)
     *
     * @param string $text
     * @return array Format: [["citation" => "Điều 4", "lines" => [1, 5, 8]], ...]
     */
    public function extractWithLineNumbers(string $text): array
    {
        $lines = preg_split('/\n/', $text);
        $citations = [];

        foreach ($lines as $lineNum => $line) {
            preg_match_all('/Điều\s+\d+/u', $line, $matches);
            foreach ($matches[0] ?? [] as $citation) {
                if (!isset($citations[$citation])) {
                    $citations[$citation] = [];
                }
                $citations[$citation][] = $lineNum + 1;
            }
        }

        return array_map(function ($citation, $lines) {
            return [
                'citation' => $citation,
                'lines' => $lines,
                'count' => count($lines),
            ];
        }, array_keys($citations), array_values($citations));
    }

    /**
     * Check if text contains any citations
     *
     * @param string $text
     * @return bool
     */
    public function hasCitations(string $text): bool
    {
        return (bool) preg_match('/Điều\s+\d+/u', $text);
    }
}
