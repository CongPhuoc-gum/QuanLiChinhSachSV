<?php

namespace Tests\Unit\Services;

use App\Services\CitationService;
use Tests\TestCase;

class CitationServiceTest extends TestCase
{
    private CitationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CitationService();
    }

    /**
     * Test extract returns array
     *
     * @test
     */
    public function test_extract_returns_array(): void
    {
        $text = 'According to Điều 4, this is important.';
        $result = $this->service->extract($text);

        $this->assertIsArray($result);
    }

    /**
     * Test extract finds citations in text
     *
     * @test
     */
    public function test_extract_finds_dieu_citations(): void
    {
        $text = 'Theo Điều 4 và Điều 5, học sinh được hỗ trợ.';
        $result = $this->service->extract($text);

        $this->assertNotEmpty($result);
        $this->assertContains('Điều 4', $result);
        $this->assertContains('Điều 5', $result);
    }

    /**
     * Test extract with no citations returns empty
     *
     * @test
     */
    public function test_extract_with_no_citations_returns_empty(): void
    {
        $text = 'This is just regular text without any article references.';
        $result = $this->service->extract($text);

        $this->assertEmpty($result);
    }

    /**
     * Test extract handles duplicate citations
     *
     * @test
     */
    public function test_extract_removes_duplicate_citations(): void
    {
        $text = 'Điều 3, Điều 3, và Điều 3 all mention the same rule.';
        $result = $this->service->extract($text);

        $occurrences = array_count_values($result);
        $this->assertEquals(1, $occurrences['Điều 3'] ?? 0);
    }

    /**
     * Test extract with metadata (metadata-first approach)
     *
     * @test
     */
    public function test_extract_with_metadata_returns_metadata_citations(): void
    {
        $text = 'Some text without citations';
        $metadata = [
            'article' => '4',
            'clause' => '1',
            'point' => 'a)'
        ];

        $result = $this->service->extract($text, $metadata);

        $this->assertNotEmpty($result);
        $this->assertContains('Điều 4', $result);
    }

    /**
     * Test extract without metadata falls back to regex
     *
     * @test
     */
    public function test_extract_without_metadata_uses_regex(): void
    {
        $text = 'According to Điều 6, students must register.';
        $metadata = [];

        $result = $this->service->extract($text, $metadata);

        $this->assertNotEmpty($result);
        $this->assertContains('Điều 6', $result);
    }

    /**
     * Test extract with multiple digit articles
     *
     * @test
     */
    public function test_extract_handles_multiple_digit_articles(): void
    {
        $text = 'Điều 10, Điều 25, và Điều 100 have different requirements.';
        $result = $this->service->extract($text);

        $this->assertContains('Điều 10', $result);
        $this->assertContains('Điều 25', $result);
        $this->assertContains('Điều 100', $result);
    }

    /**
     * Test extract handles lowercase dieu
     *
     * @test
     */
    public function test_extract_is_case_sensitive(): void
    {
        $text = 'điều 4 and ĐIỀU 5 have different formats.';
        $result = $this->service->extract($text);

        // Should only find uppercase "Điều"
        $this->assertEmpty($result);
    }

    /**
     * Test extract with line numbers
     *
     * @test
     */
    public function test_extract_with_line_numbers(): void
    {
        $text = "Line 1: Điều 2 is here\nLine 2: More text\nLine 3: Điều 2 again";
        $result = $this->service->extractWithLineNumbers($text);

        $this->assertNotEmpty($result);
        $this->assertIsArray($result[0] ?? []);
        $this->assertArrayHasKey('citation', $result[0] ?? []);
        $this->assertArrayHasKey('lines', $result[0] ?? []);
        $this->assertArrayHasKey('count', $result[0] ?? []);
    }

    /**
     * Test has_citations returns true when citations present
     *
     * @test
     */
    public function test_has_citations_returns_true(): void
    {
        $text = 'According to Điều 3, this is correct.';
        $result = $this->service->hasCitations($text);

        $this->assertTrue($result);
    }

    /**
     * Test has_citations returns false when no citations
     *
     * @test
     */
    public function test_has_citations_returns_false(): void
    {
        $text = 'This text has no article references at all.';
        $result = $this->service->hasCitations($text);

        $this->assertFalse($result);
    }

    /**
     * Test extract with complex answer text
     *
     * @test
     */
    public function test_extract_from_complex_answer(): void
    {
        $answer = <<<'TEXT'
            Theo Nghị định 81/2021, sinh viên là con hộ nghèo được hỗ trợ học phí như sau:

            Điều 4 quy định: Hỗ trợ học phí cho sinh viên có hoàn cảnh khó khăn.

            Khoản 1: Miễn 100% học phí

            Theo Điều 5: Hỗ trợ sách vở, vật dụng học tập

            Mục tiêu của Điều 3 là hỗ trợ toàn diện.
            TEXT;

        $result = $this->service->extract($answer);

        $this->assertContains('Điều 4', $result);
        $this->assertContains('Điều 5', $result);
        $this->assertContains('Điều 3', $result);
    }

    /**
     * Test extract with whitespace variations
     *
     * @test
     */
    public function test_extract_handles_whitespace_variations(): void
    {
        $text = "Điều  4, Điều\t5, and Điều
        6 are mentioned.";
        $result = $this->service->extract($text);

        // Regex should handle whitespace variations
        $this->assertGreaterThanOrEqual(2, count($result));
    }

    /**
     * Test empty text returns empty citations
     *
     * @test
     */
    public function test_extract_empty_text_returns_empty(): void
    {
        $result = $this->service->extract('');

        $this->assertEmpty($result);
    }

    /**
     * Test null metadata is handled gracefully
     *
     * @test
     */
    public function test_extract_with_null_metadata_value(): void
    {
        $text = 'Điều 7 is mentioned here.';
        $metadata = ['article' => null];

        $result = $this->service->extract($text, $metadata);

        // Should fall back to regex
        $this->assertContains('Điều 7', $result);
    }
}
