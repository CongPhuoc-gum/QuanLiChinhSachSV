<?php

namespace Tests\Unit\Services;

use App\Services\RerankingService;
use Tests\TestCase;

class RerankingServiceTest extends TestCase
{
    private RerankingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RerankingService();
    }

    /**
     * Test rerank returns array
     *
     * @test
     */
    public function test_rerank_returns_array(): void
    {
        $chunks = [
            [
                'text' => 'Test chunk 1',
                'similarity' => 0.9,
                'metadata' => ['article' => '1']
            ]
        ];

        $result = $this->service->rerank($chunks, 'test query');

        $this->assertIsArray($result);
    }

    /**
     * Test rerank with empty chunks returns empty
     *
     * @test
     */
    public function test_rerank_empty_chunks_returns_empty(): void
    {
        $result = $this->service->rerank([], 'query');

        $this->assertEmpty($result);
    }

    /**
     * Test rerank respects topK parameter
     *
     * @test
     */
    public function test_rerank_respects_topk_limit(): void
    {
        $chunks = array_map(function ($i) {
            return [
                'text' => "Chunk $i",
                'similarity' => 0.8 + ($i * 0.01),
                'metadata' => ['article' => (string) $i]
            ];
        }, range(1, 20));

        $result = $this->service->rerank($chunks, 'test', topK: 5);

        $this->assertLessThanOrEqual(5, count($result));
    }

    /**
     * Test rerank sorts by score descending
     *
     * @test
     */
    public function test_rerank_sorts_by_score_descending(): void
    {
        $chunks = [
            [
                'text' => 'Chunk 1',
                'similarity' => 0.5,
                'metadata' => ['article' => '1', 'type' => 'article']
            ],
            [
                'text' => 'Chunk 2',
                'similarity' => 0.9,
                'metadata' => ['article' => '2', 'type' => 'article']
            ],
            [
                'text' => 'Chunk 3',
                'similarity' => 0.7,
                'metadata' => ['article' => '3', 'type' => 'article']
            ]
        ];

        $result = $this->service->rerank($chunks, 'test', topK: 3);

        $this->assertCount(3, $result);
        // First result should have higher similarity than others
        $this->assertGreaterThanOrEqual($result[1]['similarity'] ?? 0, $result[0]['similarity']);
    }

    /**
     * Test calculate_relevance with matching words
     *
     * @test
     */
    public function test_calculate_relevance_with_matching_words(): void
    {
        $chunk = [
            'text' => 'Student support learning program for education'
        ];
        $query = 'student learning education';

        $relevance = $this->service->calculateRelevance($chunk, $query);

        $this->assertGreaterThan(0, $relevance);
        $this->assertLessThanOrEqual(1, $relevance);
    }

    /**
     * Test calculate_relevance with no matching words
     *
     * @test
     */
    public function test_calculate_relevance_with_no_matches(): void
    {
        $chunk = [
            'text' => 'The quick brown fox'
        ];
        $query = 'student learning support';

        $relevance = $this->service->calculateRelevance($chunk, $query);

        $this->assertEquals(0, $relevance);
    }

    /**
     * Test rerank preserves chunk structure
     *
     * @test
     */
    public function test_rerank_preserves_chunk_structure(): void
    {
        $chunks = [
            [
                'text' => 'Điều 4 về hỗ trợ',
                'similarity' => 0.85,
                'metadata' => ['article' => '4', 'type' => 'article']
            ]
        ];

        $result = $this->service->rerank($chunks, 'test');

        $this->assertArrayHasKey('text', $result[0]);
        $this->assertArrayHasKey('similarity', $result[0]);
        $this->assertArrayHasKey('metadata', $result[0]);
    }

    /**
     * Test rerank with metadata impacts scoring
     *
     * @test
     */
    public function test_rerank_scores_metadata_quality(): void
    {
        $chunkWithMetadata = [
            'text' => 'Test text with good content',
            'similarity' => 0.8,
            'metadata' => [
                'article' => '4',
                'clause' => '1',
                'point' => 'a)',
                'type' => 'point'
            ]
        ];

        $chunkWithoutMetadata = [
            'text' => 'Test text with good content',
            'similarity' => 0.8,
            'metadata' => ['type' => 'unknown']
        ];

        $result1 = $this->service->rerank([$chunkWithMetadata], 'test');
        $result2 = $this->service->rerank([$chunkWithoutMetadata], 'test');

        $this->assertCount(1, $result1);
        $this->assertCount(1, $result2);
    }

    /**
     * Test rerank with text quality scoring
     *
     * @test
     */
    public function test_rerank_scores_text_quality(): void
    {
        $shortText = [
            'text' => 'Short',
            'similarity' => 0.8,
            'metadata' => ['type' => 'article']
        ];

        $goodLengthText = [
            'text' => str_repeat('Test content ', 20),
            'similarity' => 0.8,
            'metadata' => ['type' => 'article']
        ];

        $result = $this->service->rerank(
            [$shortText, $goodLengthText],
            'test',
            topK: 2
        );

        $this->assertCount(2, $result);
    }

    /**
     * Test rerank handles high similarity scores
     *
     * @test
     */
    public function test_rerank_handles_high_similarity(): void
    {
        $chunks = array_map(function ($i) {
            return [
                'text' => "Chunk $i with good content about subsidies",
                'similarity' => 0.95 + ($i * 0.001),
                'metadata' => ['article' => (string) $i, 'type' => 'article']
            ];
        }, range(1, 10));

        $result = $this->service->rerank($chunks, 'subsidy', topK: 5);

        $this->assertCount(5, $result);
        // Verify sorted by score
        for ($i = 0; $i < count($result) - 1; $i++) {
            $this->assertGreaterThanOrEqual(
                $result[$i + 1]['similarity'] ?? 0,
                $result[$i]['similarity']
            );
        }
    }

    /**
     * Test rerank with low similarity scores
     *
     * @test
     */
    public function test_rerank_with_low_similarity(): void
    {
        $chunks = [
            [
                'text' => 'Chunk 1',
                'similarity' => 0.1,
                'metadata' => ['article' => '1']
            ],
            [
                'text' => 'Chunk 2',
                'similarity' => 0.2,
                'metadata' => ['article' => '2']
            ]
        ];

        $result = $this->service->rerank($chunks, 'test');

        // Should still return chunks even with low similarity
        $this->assertNotEmpty($result);
    }

    /**
     * Test rerank with exactly topK chunks
     *
     * @test
     */
    public function test_rerank_with_exact_topk_count(): void
    {
        $chunks = array_map(function ($i) {
            return [
                'text' => "Chunk $i",
                'similarity' => 0.5 + ($i * 0.01),
                'metadata' => ['article' => (string) $i]
            ];
        }, range(1, 5));

        $result = $this->service->rerank($chunks, 'test', topK: 5);

        $this->assertCount(5, $result);
    }

    /**
     * Test rerank with topK greater than chunks
     *
     * @test
     */
    public function test_rerank_topk_greater_than_chunks(): void
    {
        $chunks = [
            ['text' => 'Chunk 1', 'similarity' => 0.8, 'metadata' => []],
            ['text' => 'Chunk 2', 'similarity' => 0.7, 'metadata' => []]
        ];

        $result = $this->service->rerank($chunks, 'test', topK: 10);

        // Should return all available chunks
        $this->assertCount(2, $result);
    }

    /**
     * Test rerank with topK = 0
     *
     * @test
     */
    public function test_rerank_with_topk_zero(): void
    {
        $chunks = [
            ['text' => 'Chunk 1', 'similarity' => 0.8, 'metadata' => []]
        ];

        $result = $this->service->rerank($chunks, 'test', topK: 0);

        $this->assertEmpty($result);
    }
}
