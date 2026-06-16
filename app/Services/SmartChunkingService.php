<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * SmartChunkingService - Intelligent document chunking
 *
 * Single Responsibility: Chunk documents intelligently
 * - Understands legal document structure (Nghị định → Điều → Khoản → Điểm)
 * - Extracts metadata for each chunk
 * - Preserves context and relationships
 *
 * @package App\Services
 */
class SmartChunkingService
{
    /**
     * Chunk document with structure awareness
     *
     * Extracts:
     * - Nghị định (Ordinance/Regulation number and title)
     * - Điều (Article number)
     * - Khoản (Clause number)
     * - Điểm (Point/sub-clause number)
     *
     * @param string $content - Full document content
     * @return array - {count, chunks: [{text, metadata}, ...]}
     */
    public function chunk(string $content): array
    {
        try {
            Log::info('SmartChunkingService::chunk - Starting intelligent chunking');

            // Extract ordinance info (title)
            $ordinanceInfo = $this->extractOrdinanceInfo($content);

            // Split into chapters
            $chapters = $this->splitByChapter($content);

            // Process each chapter
            $chunks = [];
            foreach ($chapters as $chapter) {
                $chapterChunks = $this->chunkChapter($chapter, $ordinanceInfo);
                $chunks = array_merge($chunks, $chapterChunks);
            }

            Log::info('SmartChunkingService::chunk - Complete', [
                'total_chunks' => count($chunks),
                'ordinance' => $ordinanceInfo['title'] ?? 'Unknown',
            ]);

            return [
                'count' => count($chunks),
                'chunks' => $chunks,
            ];
        } catch (\Exception $e) {
            Log::error('SmartChunkingService::chunk - Error', [
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Extract ordinance/regulation information
     *
     * @param string $content
     * @return array - {number, title, type}
     */
    private function extractOrdinanceInfo(string $content): array
    {
        $info = [
            'number' => 'Unknown',
            'title' => 'Nghị định 81/2021',
            'type' => 'Ordinance',
        ];

        // Extract ordinance number and title
        if (preg_match('/NGHỊ ĐỊNH\s+(\d+\/[\d\/]+)\s+(.+?)(?:\n|$)/u', $content, $matches)) {
            $info['number'] = trim($matches[1]);
            $info['title'] = 'Nghị định ' . $info['number'];
            // Extract actual title
            if (isset($matches[2])) {
                $info['full_title'] = 'Nghị định ' . $info['number'] . ' ' . trim($matches[2]);
            }
        }

        return $info;
    }

    /**
     * Split content by chapters (CHƯƠNG I, CHƯƠNG II, etc.)
     *
     * @param string $content
     * @return array - Array of chapter contents
     */
    private function splitByChapter(string $content): array
    {
        $chapters = preg_split('/(?=CHƯƠNG\s+[IVX]+\s*:)/u', $content);
        return array_filter($chapters, fn($ch) => !empty(trim($ch)));
    }

    /**
     * Chunk a single chapter by articles (Điều)
     *
     * @param string $chapterContent
     * @param array $ordinanceInfo
     * @return array - Array of chunks with metadata
     */
    private function chunkChapter(string $chapterContent, array $ordinanceInfo): array
    {
        $chunks = [];

        // Extract chapter info
        if (preg_match('/CHƯƠNG\s+([IVX]+)\s*:\s*(.+?)(?:\n|$)/u', $chapterContent, $matches)) {
            $chapterRoman = trim($matches[1]);
            $chapterTitle = trim($matches[2] ?? '');
        } else {
            $chapterRoman = 'Unknown';
            $chapterTitle = '';
        }

        // Split by articles (Điều)
        $articles = preg_split('/(?=Điều\s+\d+\.)/u', $chapterContent);
        $articles = array_filter($articles, fn($art) => !empty(trim($art)));

        foreach ($articles as $article) {
            $articleChunks = $this->chunkArticle($article, $ordinanceInfo, $chapterRoman, $chapterTitle);
            $chunks = array_merge($chunks, $articleChunks);
        }

        return $chunks;
    }

    /**
     * Chunk a single article by clauses (Khoản)
     *
     * If no Khoản exists, treat article as single chunk
     *
     * @param string $articleContent
     * @param array $ordinanceInfo
     * @param string $chapterRoman
     * @param string $chapterTitle
     * @return array - Array of chunks
     */
    private function chunkArticle(string $articleContent, array $ordinanceInfo, string $chapterRoman, string $chapterTitle): array
    {
        $chunks = [];

        // Extract article number and title
        if (!preg_match('/Điều\s+(\d+)\.\s*(.+?)(?:\n|$)/u', $articleContent, $matches)) {
            return [];
        }

        $articleNumber = trim($matches[1]);
        $articleTitle = trim($matches[2] ?? '');
        $articleContent = substr($articleContent, strlen($matches[0]));  // Remove title

        // Check if article has Khoản (clauses)
        if (preg_match('/\d+\./u', $articleContent)) {
            // Has numbered clauses (Khoản)
            $chunks = $this->chunkArticleWithClauses(
                $articleContent,
                $ordinanceInfo,
                $chapterRoman,
                $articleNumber,
                $articleTitle
            );
        } else {
            // Single clause article
            $text = trim("Điều {$articleNumber}. {$articleTitle}\n{$articleContent}");
            $chunks[] = [
                'text' => $text,
                'metadata' => [
                    'source' => $ordinanceInfo['number'] ?? 'Unknown',
                    'article' => $articleNumber,
                    'article_title' => $articleTitle,
                    'chapter' => $chapterRoman,
                    'clause' => null,
                    'point' => null,
                    'type' => 'article',
                    'ordinance_title' => $ordinanceInfo['title'] ?? 'Nghị định 81/2021',
                ],
            ];
        }

        return $chunks;
    }

    /**
     * Chunk article with multiple clauses (Khoản)
     *
     * Extracts:
     * - Khoản (clause) 1, 2, 3, ...
     * - Điểm (points) a, b, c, ... within each clause
     *
     * @param string $articleContent
     * @param array $ordinanceInfo
     * @param string $chapterRoman
     * @param string $articleNumber
     * @param string $articleTitle
     * @return array
     */
    private function chunkArticleWithClauses(string $articleContent, array $ordinanceInfo, string $chapterRoman, string $articleNumber, string $articleTitle): array
    {
        $chunks = [];

        // Split by numbered clauses
        $clauses = preg_split('/(?=\d+\.)/u', $articleContent);
        $clauses = array_filter($clauses, fn($c) => !empty(trim($c)));

        foreach ($clauses as $clauseIdx => $clauseContent) {
            // Extract clause number
            if (preg_match('/^(\d+)\./u', $clauseContent, $matches)) {
                $clauseNumber = trim($matches[1]);
                $clauseContent = substr($clauseContent, strlen($matches[0]));
            } else {
                continue;  // Skip if no clause number
            }

            // Check if clause has sub-points (Điểm a, b, c, ...)
            if (preg_match('/[a-z]\)/u', $clauseContent)) {
                // Has points, chunk by point
                $chunks = array_merge($chunks, $this->chunkClauseWithPoints(
                    $clauseContent,
                    $ordinanceInfo,
                    $chapterRoman,
                    $articleNumber,
                    $articleTitle,
                    $clauseNumber
                ));
            } else {
                // Single point clause
                $text = trim("Điều {$articleNumber}. Khoản {$clauseNumber}.\n{$clauseContent}");
                $chunks[] = [
                    'text' => $text,
                    'metadata' => [
                        'source' => $ordinanceInfo['number'] ?? 'Unknown',
                        'article' => $articleNumber,
                        'article_title' => $articleTitle,
                        'clause' => $clauseNumber,
                        'point' => null,
                        'chapter' => $chapterRoman,
                        'type' => 'clause',
                        'ordinance_title' => $ordinanceInfo['title'] ?? 'Nghị định 81/2021',
                    ],
                ];
            }
        }

        return $chunks;
    }

    /**
     * Chunk clause into points (Điểm a, b, c, ...)
     *
     * @param string $clauseContent
     * @param array $ordinanceInfo
     * @param string $chapterRoman
     * @param string $articleNumber
     * @param string $articleTitle
     * @param string $clauseNumber
     * @return array
     */
    private function chunkClauseWithPoints(string $clauseContent, array $ordinanceInfo, string $chapterRoman, string $articleNumber, string $articleTitle, string $clauseNumber): array
    {
        $chunks = [];

        // Split by points (a), b), c), etc.)
        $points = preg_split('/(?=[a-z]\))/u', $clauseContent);
        $points = array_filter($points, fn($p) => !empty(trim($p)));

        foreach ($points as $pointContent) {
            // Extract point letter
            if (preg_match('/^([a-z]\))/u', $pointContent, $matches)) {
                $pointLetter = trim($matches[1]);
                $pointContent = substr($pointContent, strlen($matches[0]));
            } else {
                continue;
            }

            $text = trim("Điều {$articleNumber}. Khoản {$clauseNumber}. Điểm {$pointLetter}\n{$pointContent}");
            $chunks[] = [
                'text' => $text,
                'metadata' => [
                    'source' => $ordinanceInfo['number'] ?? 'Unknown',
                    'article' => $articleNumber,
                    'article_title' => $articleTitle,
                    'clause' => $clauseNumber,
                    'point' => $pointLetter,
                    'chapter' => $chapterRoman,
                    'type' => 'point',
                    'ordinance_title' => $ordinanceInfo['title'] ?? 'Nghị định 81/2021',
                ],
            ];
        }

        return $chunks;
    }

    /**
     * Get chunk statistics
     *
     * @param array $chunks - Result from chunk()
     * @return array - {total, by_type, avg_size}
     */
    public function getStatistics(array $chunks): array
    {
        $byType = [
            'ordinance' => 0,
            'chapter' => 0,
            'article' => 0,
            'clause' => 0,
            'point' => 0,
        ];

        $totalSize = 0;

        foreach ($chunks as $chunk) {
            $type = $chunk['metadata']['type'] ?? 'unknown';
            if (isset($byType[$type])) {
                $byType[$type]++;
            }
            $totalSize += strlen($chunk['text'] ?? '');
        }

        return [
            'total' => count($chunks),
            'by_type' => $byType,
            'avg_size' => count($chunks) > 0 ? round($totalSize / count($chunks), 0) : 0,
            'total_size' => $totalSize,
        ];
    }
}
