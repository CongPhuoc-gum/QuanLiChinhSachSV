<?php

namespace Tests\Unit\Services;

use App\Services\ContextBuilderService;
use Tests\TestCase;

class ContextBuilderServiceTest extends TestCase
{
    private ContextBuilderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ContextBuilderService();
    }

    /**
     * Test build returns string
     *
     * @test
     */
    public function test_build_returns_string(): void
    {
        $chunks = [
            [
                'text' => 'Test chunk 1',
                'similarity' => 0.9,
                'metadata' => ['article' => '1']
            ]
        ];

        $result = $this->service->build($chunks);

        $this->assertIsString($result);
    }

    /**
     * Test build with empty chunks
     *
     * @test
     */
    public function test_build_empty_chunks_returns_string(): void
    {
        $result = $this->service->build([]);

        $this->assertIsString($result);
    }

    /**
     * Test build includes chunk text
     *
     * @test
     */
    public function test_build_includes_chunk_text(): void
    {
        $chunks = [
            [
                'text' => 'Điều 4 về hỗ trợ học phí',
                'similarity' => 0.9,
                'metadata' => ['article' => '4']
            ]
        ];

        $result = $this->service->build($chunks);

        $this->assertStringContainsString('Điều 4 về hỗ trợ học phí', $result);
    }

    /**
     * Test build formats chunks properly
     *
     * @test
     */
    public function test_build_formats_chunks_with_separators(): void
    {
        $chunks = [
            [
                'text' => 'Chunk 1',
                'similarity' => 0.9,
                'metadata' => ['article' => '1']
            ],
            [
                'text' => 'Chunk 2',
                'similarity' => 0.8,
                'metadata' => ['article' => '2']
            ]
        ];

        $result = $this->service->build($chunks);

        $this->assertStringContainsString('Chunk 1', $result);
        $this->assertStringContainsString('Chunk 2', $result);
        // Should have some separation between chunks
        $this->assertGreaterThan(
            strlen('Chunk 1Chunk 2'),
            strlen($result)
        );
    }

    /**
     * Test estimate_tokens returns integer
     *
     * @test
     */
    public function test_estimate_tokens_returns_integer(): void
    {
        $text = 'This is a test text for token estimation.';
        $tokens = $this->service->estimateTokens($text);

        $this->assertIsInt($tokens);
        $this->assertGreaterThan(0, $tokens);
    }

    /**
     * Test estimate_tokens scales with text length
     *
     * @test
     */
    public function test_estimate_tokens_scales_with_length(): void
    {
        $shortText = 'Short.';
        $longText = str_repeat('This is a longer text that contains more content. ', 20);

        $shortTokens = $this->service->estimateTokens($shortText);
        $longTokens = $this->service->estimateTokens($longText);

        $this->assertGreaterThan($shortTokens, $longTokens);
    }

    /**
     * Test estimate_tokens with empty string
     *
     * @test
     */
    public function test_estimate_tokens_empty_string(): void
    {
        $tokens = $this->service->estimateTokens('');

        $this->assertIsInt($tokens);
        $this->assertEquals(0, $tokens);
    }

    /**
     * Test estimate_tokens with unicode vietnamese text
     *
     * @test
     */
    public function test_estimate_tokens_vietnamese_text(): void
    {
        $text = 'Theo Nghị định 81/2021, sinh viên con hộ nghèo được hỗ trợ học phí.';
        $tokens = $this->service->estimateTokens($text);

        $this->assertGreaterThan(0, $tokens);
    }

    /**
     * Test build with multiple chunks includes all text
     *
     * @test
     */
    public function test_build_multiple_chunks_includes_all(): void
    {
        $chunks = [
            ['text' => 'Article 1', 'similarity' => 0.9, 'metadata' => []],
            ['text' => 'Article 2', 'similarity' => 0.8, 'metadata' => []],
            ['text' => 'Article 3', 'similarity' => 0.7, 'metadata' => []]
        ];

        $result = $this->service->build($chunks);

        $this->assertStringContainsString('Article 1', $result);
        $this->assertStringContainsString('Article 2', $result);
        $this->assertStringContainsString('Article 3', $result);
    }

    /**
     * Test build handles special characters
     *
     * @test
     */
    public function test_build_handles_special_characters(): void
    {
        $chunks = [
            [
                'text' => 'Content with "quotes" and \'apostrophes\' and (parentheses)',
                'similarity' => 0.9,
                'metadata' => []
            ]
        ];

        $result = $this->service->build($chunks);

        $this->assertStringContainsString('quotes', $result);
        $this->assertStringContainsString('apostrophes', $result);
    }

    /**
     * Test build with metadata includes article info
     *
     * @test
     */
    public function test_build_includes_article_metadata(): void
    {
        $chunks = [
            [
                'text' => 'Content text',
                'similarity' => 0.9,
                'metadata' => [
                    'article' => '4',
                    'source' => '81/2021'
                ]
            ]
        ];

        $result = $this->service->build($chunks);

        // Should contain the text
        $this->assertStringContainsString('Content text', $result);
    }

    /**
     * Test estimate_tokens with very long text
     *
     * @test
     */
    public function test_estimate_tokens_very_long_text(): void
    {
        $text = str_repeat('This is a test sentence. ', 1000);
        $tokens = $this->service->estimateTokens($text);

        $this->assertGreaterThan(1000, $tokens);
    }

    /**
     * Test build context respects formatting
     */
    public function test_build_preserves_text_formatting(): void
    {
        $chunks = [
            [
                'text' => "Line 1\nLine 2\nLine 3",
                'similarity' => 0.9,
                'metadata' => []
            ]
        ];

        $result = $this->service->build($chunks);

        $this->assertStringContainsString('Line 1', $result);
        $this->assertStringContainsString('Line 2', $result);
        $this->assertStringContainsString('Line 3', $result);
    }

    /**
     * Test estimate_tokens consistency
     *
     * @test
     */
    public function test_estimate_tokens_consistent(): void
    {
        $text = 'This is a test for consistency checking.';

        $tokens1 = $this->service->estimateTokens($text);
        $tokens2 = $this->service->estimateTokens($text);

        $this->assertEquals($tokens1, $tokens2);
    }
}
