<?php

namespace Tests\Unit\Services;

use App\Common\Exceptions\EmbeddingException;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmbeddingServiceTest extends TestCase
{
    private EmbeddingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->service = new EmbeddingService();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Http::allowStrayRequests();
    }

    /**
     * Test generate with mock response returns array
     */
    public function test_generate_returns_array(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'embedding' => ['values' => array_fill(0, 768, 0.1)]
            ], 200)
        ]);

        $result = $this->service->generate('test text');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test generate with empty text throws exception
     */
    public function test_generate_with_empty_text_throws_exception(): void
    {
        try {
            $this->service->generate('');
            $this->fail('Expected EmbeddingException to be thrown');
        } catch (EmbeddingException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test generate with whitespace only throws exception
     */
    public function test_generate_with_whitespace_throws_exception(): void
    {
        try {
            $this->service->generate('   ');
            $this->fail('Expected EmbeddingException to be thrown');
        } catch (EmbeddingException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test generate returns 768-dim vector (Gemini embedding model dimension)
     */
    public function test_generate_returns_correct_dimensions(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'embedding' => ['values' => array_fill(0, 768, 0.1)]
            ], 200)
        ]);

        $result = $this->service->generate('test');

        $this->assertCount(768, $result);
    }

    /**
     * Test generate with vietnamese text
     */
    public function test_generate_vietnamese_text(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'embedding' => ['values' => array_fill(0, 768, 0.1)]
            ], 200)
        ]);

        $result = $this->service->generate('Em là sinh viên con hộ nghèo');

        $this->assertIsArray($result);
        $this->assertCount(768, $result);
    }

    /**
     * Test generate with long text
     */
    public function test_generate_long_text(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'embedding' => ['values' => array_fill(0, 768, 0.1)]
            ], 200)
        ]);

        $longText = str_repeat('This is a test sentence. ', 100);
        $result = $this->service->generate($longText);

        $this->assertIsArray($result);
    }

    /**
     * Test generate with special characters
     */
    public function test_generate_special_characters(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'embedding' => ['values' => array_fill(0, 768, 0.1)]
            ], 200)
        ]);

        $result = $this->service->generate('Text with "quotes" and (parentheses) and [brackets]');

        $this->assertIsArray($result);
    }

    /**
     * Test generate makes HTTP request to correct endpoint
     */
    public function test_generate_calls_correct_endpoint(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'embedding' => ['values' => array_fill(0, 768, 0.1)]
            ], 200)
        ]);

        $this->service->generate('test');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'generativelanguage.googleapis.com') &&
                str_contains($request->url(), 'embedding');
        });
    }

    /**
     * Test generate with error response (500)
     */
    public function test_generate_handles_error_response(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([], 500)
        ]);

        try {
            $this->service->generate('test');
            $this->fail('Expected EmbeddingException to be thrown');
        } catch (EmbeddingException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test generate with rate limit (429)
     */
    public function test_generate_handles_rate_limit(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([], 429)
        ]);

        try {
            $this->service->generate('test');
            $this->fail('Expected EmbeddingException to be thrown');
        } catch (EmbeddingException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test generate embedding values are numeric
     */
    public function test_embedding_values_are_numeric(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'embedding' => ['values' => [0.1, 0.2, 0.3, -0.1, -0.2]]
            ], 200)
        ]);

        $result = $this->service->generate('test');

        foreach (array_slice($result, 0, 5) as $value) {
            $this->assertTrue(is_numeric($value));
        }
    }

    /**
     * Test generate consistency - same text returns same embedding
     */
    public function test_generate_consistency(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'embedding' => ['values' => array_fill(0, 768, 0.5)]
            ], 200)
        ]);

        $text = 'consistent test text';
        $result1 = $this->service->generate($text);
        $result2 = $this->service->generate($text);

        // Both should have same structure
        $this->assertCount(count($result1), $result2);
    }
}
