<?php

namespace Tests\Unit\Services;

use App\Common\Exceptions\GenerationException;
use App\Services\GenerationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenerationServiceTest extends TestCase
{
    private GenerationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->service = new GenerationService();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Http::allowStrayRequests();
    }

    /**
     * Test generate returns string
     */
    public function test_generate_returns_string(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Generated answer']]]
                ]]
            ], 200)
        ]);

        $result = $this->service->generate('Question?', 'Context here');

        $this->assertIsString($result);
    }

    /**
     * Test generate with empty question throws exception
     */
    public function test_generate_with_empty_question_throws(): void
    {
        try {
            $this->service->generate('', 'context');
            $this->fail('Expected GenerationException to be thrown');
        } catch (GenerationException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test generate with empty context still works
     */
    public function test_generate_with_empty_context(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Answer without context']]]
                ]]
            ], 200)
        ]);

        $result = $this->service->generate('Question?', '');

        $this->assertIsString($result);
    }

    /**
     * Test generate with vietnamese text
     */
    public function test_generate_vietnamese_question(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Đáp án']]]
                ]]
            ], 200)
        ]);

        $result = $this->service->generate(
            'Em được hỗ trợ bao nhiêu?',
            'Theo Điều 4...'
        );

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test generate with long context
     */
    public function test_generate_long_context(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Answer']]]
                ]]
            ], 200)
        ]);

        $longContext = str_repeat('Context line ', 100);
        $result = $this->service->generate('Question?', $longContext);

        $this->assertIsString($result);
    }

    /**
     * Test generate handles API error response (500)
     */
    public function test_generate_handles_api_error(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([], 500)
        ]);

        try {
            $this->service->generate('Question?', 'Context');
            $this->fail('Expected GenerationException to be thrown');
        } catch (GenerationException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test generate handles rate limit (429)
     */
    public function test_generate_rate_limit(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([], 429)
        ]);

        try {
            $this->service->generate('Question?', 'Context');
            $this->fail('Expected GenerationException to be thrown');
        } catch (GenerationException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test generate handles timeout exception
     */
    public function test_generate_timeout(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => function () {
                throw new \Exception('Timeout');
            }
        ]);

        try {
            $this->service->generate('Question?', 'Context');
            $this->fail('Expected GenerationException to be thrown');
        } catch (GenerationException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test generate calls correct Gemini API endpoint
     */
    public function test_generate_calls_gemini_api(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Answer']]]
                ]]
            ], 200)
        ]);

        $this->service->generate('Question?', 'Context');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'generativelanguage.googleapis.com') &&
                str_contains($request->url(), 'generateContent');
        });
    }

    /**
     * Test generate with special characters
     */
    public function test_generate_special_characters(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Answer with "quotes"']]]
                ]]
            ], 200)
        ]);

        $result = $this->service->generate(
            'Question with "quotes" and (parens)?',
            'Context with [brackets]'
        );

        $this->assertIsString($result);
    }

    /**
     * Test generate response is not empty
     */
    public function test_generate_response_not_empty(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Non-empty answer']]]
                ]]
            ], 200)
        ]);

        $result = $this->service->generate('Q?', 'C');

        $this->assertNotEmpty($result);
    }

    /**
     * Test generate response includes text
     */
    public function test_generate_includes_text(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Expected answer text']]]
                ]]
            ], 200)
        ]);

        $result = $this->service->generate('Q?', 'C');

        $this->assertStringContainsString('answer', strtolower($result));
    }
}
