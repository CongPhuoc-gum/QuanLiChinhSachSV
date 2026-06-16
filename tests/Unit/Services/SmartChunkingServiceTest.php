<?php

namespace Tests\Unit\Services;

use App\Services\SmartChunkingService;
use Tests\TestCase;

class SmartChunkingServiceTest extends TestCase
{
    private SmartChunkingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SmartChunkingService();
    }

    /**
     * Test basic chunking produces chunks
     *
     * @test
     */
    public function test_chunk_returns_array_with_count_and_chunks(): void
    {
        $content = <<<'TEXT'
            NGHỊ ĐỊNH 81/2021 VỀ HỖ TRỢ HỌC PHÍ

            CHƯƠNG I: ĐỊNH NGHĨA VÀ PHẠM VI

            Điều 1. Định nghĩa
            1. Người học là sinh viên đại học

            Điều 2. Phạm vi
            1. Nghị định này áp dụng cho sinh viên
            TEXT;

        $result = $this->service->chunk($content);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('count', $result);
        $this->assertArrayHasKey('chunks', $result);
        $this->assertGreaterThan(0, $result['count']);
        $this->assertIsArray($result['chunks']);
    }

    /**
     * Test each chunk has text and metadata
     *
     * @test
     */
    public function test_each_chunk_has_text_and_metadata(): void
    {
        $content = <<<'TEXT'
            NGHỊ ĐỊNH 81/2021

            CHƯƠNG I: TEST

            Điều 3. Test content
            1. This is test content for verification
            TEXT;

        $result = $this->service->chunk($content);

        foreach ($result['chunks'] as $chunk) {
            $this->assertArrayHasKey('text', $chunk);
            $this->assertArrayHasKey('metadata', $chunk);
            $this->assertNotEmpty($chunk['text']);
            $this->assertIsArray($chunk['metadata']);
        }
    }

    /**
     * Test metadata contains required fields
     *
     * @test
     */
    public function test_metadata_contains_required_fields(): void
    {
        $content = <<<'TEXT'
            NGHỊ ĐỊNH 81/2021

            CHƯƠNG I: TITLE

            Điều 4. Article Title
            1. Clause content
            a) Point content
            TEXT;

        $result = $this->service->chunk($content);

        if (!empty($result['chunks'])) {
            $metadata = $result['chunks'][0]['metadata'];

            $this->assertArrayHasKey('source', $metadata);
            $this->assertArrayHasKey('article', $metadata);
            $this->assertArrayHasKey('type', $metadata);
            $this->assertArrayHasKey('ordinance_title', $metadata);
        }
    }

    /**
     * Test chunk statistics
     *
     * @test
     */
    public function test_get_statistics_returns_valid_metrics(): void
    {
        $content = <<<'TEXT'
            NGHỊ ĐỊNH 81/2021

            CHƯƠNG I: TEST

            Điều 5. Test
            1. Content here

            Điều 6. Another
            1. More content
            TEXT;

        $result = $this->service->chunk($content);
        $stats = $this->service->getStatistics($result['chunks']);

        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('by_type', $stats);
        $this->assertArrayHasKey('avg_size', $stats);
        $this->assertArrayHasKey('total_size', $stats);
        $this->assertGreaterThan(0, $stats['total']);
        $this->assertGreaterThanOrEqual(0, $stats['avg_size']);
    }

    /**
     * Test empty content
     *
     * @test
     */
    public function test_chunk_with_empty_content_returns_empty_chunks(): void
    {
        $result = $this->service->chunk('');

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['count']);
        $this->assertEmpty($result['chunks']);
    }

    /**
     * Test whitespace only content
     *
     * @test
     */
    public function test_chunk_with_whitespace_only_returns_empty_chunks(): void
    {
        $result = $this->service->chunk('   \n  \t  ');

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['count']);
    }

    /**
     * Test article extraction
     *
     * @test
     */
    public function test_article_number_extracted_correctly(): void
    {
        $content = <<<'TEXT'
            NGHỊ ĐỊNH 81/2021

            CHƯƠNG I: TEST

            Điều 10. Article Title
            1. Content
            TEXT;

        $result = $this->service->chunk($content);

        $hasArticle10 = false;
        foreach ($result['chunks'] as $chunk) {
            if (isset($chunk['metadata']['article']) && $chunk['metadata']['article'] === '10') {
                $hasArticle10 = true;
                break;
            }
        }

        $this->assertTrue($hasArticle10, 'Article 10 not found in chunks');
    }

    /**
     * Test clause and point extraction
     *
     * @test
     */
    public function test_clause_and_point_extracted_correctly(): void
    {
        $content = <<<'TEXT'
            NGHỊ ĐỊNH 81/2021

            CHƯƠNG I: TEST

            Điều 7. Title
            1. First clause
            a) First point
            b) Second point
            2. Second clause
            a) Another point
            TEXT;

        $result = $this->service->chunk($content);

        $clauses = [];
        $points = [];

        foreach ($result['chunks'] as $chunk) {
            if (!empty($chunk['metadata']['clause'])) {
                $clauses[] = $chunk['metadata']['clause'];
            }
            if (!empty($chunk['metadata']['point'])) {
                $points[] = $chunk['metadata']['point'];
            }
        }

        $this->assertNotEmpty($clauses, 'No clauses found');
        $this->assertNotEmpty($points, 'No points found');
    }

    /**
     * Test large document chunking
     *
     * @test
     */
    public function test_chunking_large_document(): void
    {
        // Create a document with multiple chapters and articles
        $content = '';
        for ($chapter = 1; $chapter <= 3; $chapter++) {
            $content .= 'CHƯƠNG ' . chr(64 + $chapter) . ': Chương ' . $chapter . "\n\n";
            for ($article = $chapter * 10; $article < $chapter * 10 + 5; $article++) {
                $content .= "Điều $article. Article Title\n";
                $content .= "1. Clause 1\n";
                $content .= "a) Point a\n\n";
            }
        }

        $result = $this->service->chunk($content);

        $this->assertGreaterThan(0, $result['count']);
        $this->assertGreaterThan(10, $result['count'], 'Expected more than 10 chunks for large document');
    }

    /**
     * Test UTF-8 Vietnamese text handling
     *
     * @test
     */
    public function test_vietnamese_text_handling(): void
    {
        $content = <<<'TEXT'
            NGHỊ ĐỊNH 81/2021 VỀ HỖ TRỢ HỌC PHÍ VÀ MIỄN HỌC PHÍ

            CHƯƠNG I: CÓ ĐIỀU KHOẢN

            Điều 1. Định nghĩa người học
            1. Người học là công dân Việt Nam

            Điều 2. Hỗ trợ chi phí đào tạo
            1. Hỗ trợ học phí cho sinh viên khó khăn
            TEXT;

        $result = $this->service->chunk($content);

        $this->assertGreaterThan(0, $result['count']);
        foreach ($result['chunks'] as $chunk) {
            $this->assertStringContainsString('Điều', $chunk['text']);
        }
    }
}
