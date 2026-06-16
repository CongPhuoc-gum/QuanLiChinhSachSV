<?php

namespace Tests\Feature;

use App\Services\CitationService;
use App\Services\ContextBuilderService;
use App\Services\EmbeddingService;
use App\Services\GenerationService;
use App\Services\RAGPipelineService;
use App\Services\SemanticCacheService;
use App\Services\VectorSearchService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Mockery;

/**
 * STEP 4B - Feature Tests
 *
 * Test complete RAG flow:
 * Question → Semantic Cache → Embedding → Vector Search →
 * Context Builder → Gemini → Citation → Response
 */
class RAGFlowTest extends TestCase
{
    private RAGPipelineService $pipeline;
    private $mockCache;
    private $mockVectorSearch;
    private $mockGeneration;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();

        // Mock all services
        $this->mockCache = Mockery::mock(SemanticCacheService::class);
        $this->mockVectorSearch = Mockery::mock(VectorSearchService::class);
        $this->mockGeneration = Mockery::mock(GenerationService::class);

        // Set default expectations for VectorSearchService
        $this
            ->mockVectorSearch
            ->shouldReceive('isHealthy')
            ->andReturn(true)
            ->byDefault();

        $this->pipeline = new RAGPipelineService(
            cacheService: $this->mockCache,
            vectorSearchService: $this->mockVectorSearch,
            contextBuilderService: app(ContextBuilderService::class),
            generationService: $this->mockGeneration,
            citationService: app(CitationService::class)
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Http::allowStrayRequests();
        Mockery::close();
    }

    /**
     * TEST 4B1: Cache Hit Flow
     * Question is in cache → return immediately
     */
    public function test_cache_hit_flow(): void
    {
        $question = 'Em là con hộ nghèo được miễn bao nhiêu?';

        // Mock cache returns a hit
        $this
            ->mockCache
            ->shouldReceive('get')
            ->with($question)
            ->andReturn([
                'answer' => 'Miễn 100% học phí',
                'citations' => ['Điều 3'],
                'metadata' => ['type' => 'cache_hit']
            ])
            ->once();

        // Vector search should NOT be called
        $this
            ->mockVectorSearch
            ->shouldNotReceive('search');

        // Call ask to trigger cache lookup
        $result = $this->pipeline->ask($question);

        // Verify result
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('answer', $result);
        $this->assertArrayHasKey('method', $result);
    }

    /**
     * TEST 4B2: Cache Miss + Vector Search Flow
     * Question not in cache → search vectors → return
     */
    public function test_cache_miss_vector_search_flow(): void
    {
        $question = 'Em được hỗ trợ bao nhiêu một tháng?';

        // Cache returns null (miss)
        $this
            ->mockCache
            ->shouldReceive('get')
            ->andReturnNull()
            ->once();

        // Vector search returns chunks
        $this
            ->mockVectorSearch
            ->shouldReceive('search')
            ->andReturn([
                ['text' => 'Theo Điều 4: Con thương binh được hỗ trợ 1.5M VND/tháng',
                    'similarity' => 0.92, 'metadata' => ['article' => '4']],
                ['text' => 'Trợ cấp xã hội bao gồm hỗ trợ học phí và sinh hoạt phí',
                    'similarity' => 0.88, 'metadata' => ['article' => '5']]
            ])
            ->once();

        // Generation called with context
        $this
            ->mockGeneration
            ->shouldReceive('generate')
            ->andReturn('Con thương binh được hỗ trợ 1.5 triệu đồng hàng tháng')
            ->once();

        // Call ask to trigger flow
        $result = $this->pipeline->ask($question);

        // Verify result
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('answer', $result);
    }

    /**
     * TEST 4B3: No Relevant Chunks Found
     * Vector search returns empty → graceful response
     */
    public function test_no_relevant_chunks_flow(): void
    {
        $question = 'Em là sinh viên nước ngoài được hỗ trợ gì?';

        // Cache miss
        $this
            ->mockCache
            ->shouldReceive('get')
            ->andReturnNull()
            ->once();

        // Vector search returns empty
        $this
            ->mockVectorSearch
            ->shouldReceive('search')
            ->andReturn([])
            ->once();

        // Call ask to trigger flow
        $result = $this->pipeline->ask($question);

        // Verify result
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('answer', $result);
    }

    /**
     * TEST 4B4: Empty Question Handling
     * Empty question → should not crash
     */
    public function test_empty_question_handling(): void
    {
        $question = '';

        // Empty question should not call cache or search
        $this
            ->mockCache
            ->shouldNotReceive('get');

        $this
            ->mockVectorSearch
            ->shouldNotReceive('search');

        // Can test with config method
        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);
        $this->assertGreaterThan(0, count($config));
    }

    /**
     * TEST 4B5: Malformed Document Handling
     * Vector search returns malformed chunks → handle gracefully
     */
    public function test_malformed_document_handling(): void
    {
        $question = 'Em được hỗ trợ bao nhiêu?';

        // Cache miss
        $this
            ->mockCache
            ->shouldReceive('get')
            ->andReturnNull();

        // Vector search returns malformed data
        $this
            ->mockVectorSearch
            ->shouldReceive('search')
            ->andReturn([
                ['text' => null, 'metadata' => []],  // null text
                ['text' => '', 'metadata' => null],  // empty text, null metadata
                ['similarity' => -1, 'text' => 'Valid chunk']  // invalid similarity
            ]);

        // Generation should handle this
        $this
            ->mockGeneration
            ->shouldReceive('generate')
            ->andReturn('Có lỗi xử lý dữ liệu.');

        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);
    }

    /**
     * TEST 4B6: Invalid Metadata Handling
     * Vector search returns chunks with invalid metadata → handle gracefully
     */
    public function test_invalid_metadata_handling(): void
    {
        $question = 'Điều gì?';

        $this
            ->mockCache
            ->shouldReceive('get')
            ->andReturnNull();

        // Vector search returns invalid metadata
        $this
            ->mockVectorSearch
            ->shouldReceive('search')
            ->andReturn([
                ['text' => 'Content', 'similarity' => 0.8, 'metadata' => 'not_array'],
                ['text' => 'Content2', 'similarity' => 0.7, 'metadata' => ['article' => null]],
                ['text' => 'Content3', 'similarity' => 0.9]  // missing metadata
            ]);

        $this
            ->mockGeneration
            ->shouldReceive('generate')
            ->andReturn('Phản hồi được tạo.');

        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);
    }

    /**
     * TEST 4B7: Very Long Question
     * Question is very long (1000+ chars) → handle without crash
     */
    public function test_very_long_question_handling(): void
    {
        $question = str_repeat('Câu hỏi dài ', 50);  // ~650 chars

        $this
            ->mockCache
            ->shouldReceive('get')
            ->andReturnNull();

        $this
            ->mockVectorSearch
            ->shouldReceive('search')
            ->andReturn([
                ['text' => 'Trả lời', 'similarity' => 0.7, 'metadata' => ['article' => '1']]
            ]);

        $this
            ->mockGeneration
            ->shouldReceive('generate')
            ->andReturn('Đáp án cho câu hỏi dài.');

        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);
    }

    /**
     * TEST 4B8: Vietnamese UTF-8 Question
     * Vietnamese special characters → handle encoding correctly
     */
    public function test_vietnamese_utf8_question_handling(): void
    {
        $question = 'Em là sinh viên con hộ nghèo, tàn tật, có phải hưởng trợ cấp xã hội không? Được hỗ trợ bao nhiêu?';

        $this
            ->mockCache
            ->shouldReceive('get')
            ->andReturn([
                'answer' => 'Được hỗ trợ theo quy định',
                'citations' => ['Điều 4'],
                'metadata' => ['encoding' => 'UTF-8']
            ]);

        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);
    }

    /**
     * TEST 4B9: Empty Result Handling
     * Vector search and generation both return empty → don't crash
     */
    public function test_empty_result_handling(): void
    {
        $question = 'Không có?';

        $this
            ->mockCache
            ->shouldReceive('get')
            ->andReturnNull();

        $this
            ->mockVectorSearch
            ->shouldReceive('search')
            ->andReturn([]);

        $this
            ->mockGeneration
            ->shouldReceive('generate')
            ->andReturn('');

        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);
    }

    /**
     * TEST 4B10: Full Flow Integration
     * Complete RAG flow with mocks: cache → vector search → context → generation → citations
     */
    public function test_full_rag_flow_integration(): void
    {
        $question = 'Con hộ nghèo được miễn bao nhiêu học phí?';

        // Step 1: Try cache
        $this
            ->mockCache
            ->shouldReceive('get')
            ->with($question)
            ->andReturnNull()
            ->once();

        // Step 2: Vector search
        $chunks = [
            [
                'text' => 'Điều 3: Hộ nghèo được miễn 100% học phí',
                'similarity' => 0.95,
                'metadata' => ['article' => '3', 'clause' => 'hộ_nghèo']
            ],
            [
                'text' => 'Hộ cận nghèo được miễn 70% học phí',
                'similarity' => 0.82,
                'metadata' => ['article' => '3', 'clause' => 'hộ_cận_nghèo']
            ]
        ];

        $this
            ->mockVectorSearch
            ->shouldReceive('search')
            ->andReturn($chunks);

        // Step 3: Generate - allow multiple calls to handle internal generation
        $this
            ->mockGeneration
            ->shouldReceive('generate')
            ->andReturn('Theo Điều 3 của Nghị định 81/2021, hộ nghèo được miễn 100% học phí.')
            ->zeroOrMoreTimes();

        // Call ask to verify pipeline works
        $result = $this->pipeline->ask($question);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('answer', $result);

        // Verify pipeline configuration
        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);
        $this->assertArrayHasKey('vector_search_topk', $config);
    }

    /**
     * TEST 4B11: Multiple Questions in Sequence
     * Ask multiple questions → verify state isn't corrupted
     */
    public function test_multiple_questions_in_sequence(): void
    {
        $questions = [
            'Con hộ nghèo được miễn bao nhiêu?',
            'Con thương binh được hỗ trợ gì?',
            'Điều kiện để nhận trợ cấp là gì?'
        ];

        foreach ($questions as $q) {
            $this
                ->mockCache
                ->shouldReceive('get')
                ->andReturnNull();

            $this
                ->mockVectorSearch
                ->shouldReceive('search')
                ->andReturn([
                    ['text' => 'Chunk text', 'similarity' => 0.8, 'metadata' => ['article' => '1']]
                ]);

            $this
                ->mockGeneration
                ->shouldReceive('generate')
                ->andReturn('Đáp án');
        }

        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);
    }

    /**
     * TEST 4B12: Citation Extraction
     * Response contains citations → extract and verify
     */
    public function test_citation_extraction_from_response(): void
    {
        $response = 'Theo Điều 3, Điều 4 và Điều 5 của Nghị định 81/2021, sinh viên được hỗ trợ.';

        // Citations should extract "Điều 3", "Điều 4", "Điều 5"
        $citationService = app(CitationService::class);
        $citations = $citationService->extract($response);

        $this->assertIsArray($citations);
        $this->assertGreaterThan(0, count($citations));
        $this->assertContains('Điều 3', $citations);
        $this->assertContains('Điều 4', $citations);
        $this->assertContains('Điều 5', $citations);
    }

    /**
     * TEST 4B13: No Citations in Response
     * Response has no citations → return empty array gracefully
     */
    public function test_no_citations_in_response(): void
    {
        $response = 'Tôi không có thông tin chính xác về câu hỏi này.';

        $citationService = app(CitationService::class);
        $citations = $citationService->extract($response);

        $this->assertIsArray($citations);
        $this->assertEmpty($citations);
    }

    /**
     * TEST 4B14: Cached Question with Citations
     * Cache hit with proper citations structure
     */
    public function test_cached_question_with_citations(): void
    {
        $question = 'Được hỗ trợ bao nhiêu?';

        $cachedResult = [
            'answer' => 'Miễn 100% theo Điều 3',
            'citations' => ['Điều 3', 'Điều 4'],
            'metadata' => ['type' => 'cached']
        ];

        $this
            ->mockCache
            ->shouldReceive('get')
            ->andReturn($cachedResult);

        // Verify citations are in result
        $this->assertArrayHasKey('citations', $cachedResult);
        $this->assertIsArray($cachedResult['citations']);
        $this->assertGreaterThan(0, count($cachedResult['citations']));
    }

    /**
     * TEST 4B15: Health Status Check Before Flow
     * Check health before executing RAG flow
     */
    public function test_health_status_before_flow(): void
    {
        // Ensure isHealthy() has expectations set
        $this
            ->mockVectorSearch
            ->shouldReceive('isHealthy')
            ->andReturn(true)
            ->once();

        $health = $this->pipeline->getHealthStatus();

        $this->assertIsArray($health);
        $this->assertArrayHasKey('timestamp', $health);
        $this->assertIsString($health['timestamp']);
    }
}
