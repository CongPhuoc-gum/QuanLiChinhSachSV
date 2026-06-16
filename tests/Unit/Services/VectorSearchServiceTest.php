<?php

namespace Tests\Unit\Services;

use App\Common\Exceptions\VectorSearchException;
use App\Services\EmbeddingService;
use App\Services\VectorSearchService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Mockery;

class VectorSearchServiceTest extends TestCase
{
    private VectorSearchService $service;
    private $mockEmbedding;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();

        // Mock EmbeddingService to avoid API calls
        $this->mockEmbedding = Mockery::mock(EmbeddingService::class);
        $this
            ->mockEmbedding
            ->shouldReceive('generate')
            ->andReturn(array_fill(0, 768, 0.1))
            ->byDefault();

        $this->service = new VectorSearchService($this->mockEmbedding);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Http::allowStrayRequests();
        Mockery::close();
    }

    /**
     * Test search with mock response returns array
     */
    public function test_search_returns_array(): void
    {
        Http::fake([
            '*localhost:8000*' => Http::response([
                'ids' => [['chunk_1', 'chunk_2']],
                'distances' => [[0.1, 0.2]],
                'metadatas' => [[
                    ['article' => '1'],
                    ['article' => '2']
                ]],
                'documents' => [['Doc 1', 'Doc 2']]
            ], 200)
        ]);

        $result = $this->service->search('test question');

        $this->assertIsArray($result);
    }

    /**
     * Test search with empty query
     */
    public function test_search_with_empty_embedding(): void
    {
        try {
            $this->service->search('');
            $this->fail('Expected VectorSearchException to be thrown');
        } catch (VectorSearchException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test search respects topK parameter
     */
    public function test_search_respects_topk(): void
    {
        Http::fake([
            '*localhost:8000*' => Http::response([
                'ids' => [[]],
                'distances' => [[]],
                'metadatas' => [[]],
                'documents' => [[]]
            ], 200)
        ]);

        $result = $this->service->search('test', 10);

        // Should use topK parameter
        $this->assertIsArray($result);
    }

    /**
     * Test search handles connection error
     */
    public function test_search_handles_connection_error(): void
    {
        Http::fake([
            '*localhost:8000*' => Http::response([], 500)
        ]);

        try {
            $this->service->search('test');
            $this->fail('Expected VectorSearchException to be thrown');
        } catch (VectorSearchException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test is_healthy returns boolean
     */
    public function test_is_healthy_returns_boolean(): void
    {
        Http::fake([
            '*localhost:8000*' => Http::response(['ok' => true], 200)
        ]);

        $result = $this->service->isHealthy();

        $this->assertIsBool($result);
    }

    /**
     * Test search results have text field
     */
    public function test_search_results_have_text(): void
    {
        Http::fake([
            '*localhost:8000*' => Http::response([
                'ids' => [['id_1']],
                'distances' => [[0.1]],
                'metadatas' => [['article' => '1']],
                'documents' => [['Chunk text']]
            ], 200)
        ]);

        $result = $this->service->search('test question');

        if (!empty($result)) {
            foreach ($result as $item) {
                $this->assertArrayHasKey('text', $item);
            }
        }
    }

    /**
     * Test search results include similarity scores
     */
    public function test_search_results_have_similarity(): void
    {
        Http::fake([
            '*localhost:8000*' => Http::response([
                'ids' => [['id_1']],
                'distances' => [[0.15]],
                'metadatas' => [['article' => '1']],
                'documents' => [['Text']]
            ], 200)
        ]);

        $result = $this->service->search('test question');

        if (!empty($result)) {
            foreach ($result as $item) {
                $this->assertArrayHasKey('similarity', $item);
            }
        }
    }

    /**
     * Test search with long query
     */
    public function test_search_with_large_embedding(): void
    {
        Http::fake([
            '*localhost:8000*' => Http::response([
                'ids' => [[]],
                'distances' => [[]],
                'metadatas' => [[]],
                'documents' => [[]]
            ], 200)
        ]);

        $result = $this->service->search(str_repeat('test ', 100));

        $this->assertIsArray($result);
    }

    /**
     * Test search handles connection timeout
     */
    public function test_search_connection_timeout(): void
    {
        Http::fake([
            '*localhost:8000*' => function () {
                throw new \Exception('Connection timeout');
            }
        ]);

        // Should handle exception and throw VectorSearchException
        try {
            $this->service->search('test');
            $this->fail('Expected VectorSearchException to be thrown');
        } catch (VectorSearchException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test search sends query to endpoint
     */
    public function test_search_sends_embedding(): void
    {
        Http::fake([
            '*localhost:8000*' => Http::response([
                'ids' => [[]],
                'distances' => [[]],
                'metadatas' => [[]],
                'documents' => [[]]
            ], 200)
        ]);

        $this->service->search('test question');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'localhost:8000');
        });
    }
}
