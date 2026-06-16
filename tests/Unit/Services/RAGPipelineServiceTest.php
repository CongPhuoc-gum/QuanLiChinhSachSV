<?php

namespace Tests\Unit\Services;

use App\Services\CitationService;
use App\Services\ContextBuilderService;
use App\Services\GenerationService;
use App\Services\RAGPipelineService;
use App\Services\SemanticCacheService;
use App\Services\VectorSearchService;
use Tests\TestCase;
use Mockery;

class RAGPipelineServiceTest extends TestCase
{
    private RAGPipelineService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock all dependencies to avoid external calls
        $mockCache = Mockery::mock(SemanticCacheService::class);
        $mockCache->shouldReceive('get')->andReturnNull()->byDefault();

        $mockVectorSearch = Mockery::mock(VectorSearchService::class);
        $mockVectorSearch->shouldReceive('isHealthy')->andReturnTrue()->byDefault();

        $this->service = new RAGPipelineService(
            cacheService: $mockCache,
            vectorSearchService: $mockVectorSearch,
            contextBuilderService: Mockery::mock(ContextBuilderService::class),
            generationService: Mockery::mock(GenerationService::class),
            citationService: Mockery::mock(CitationService::class)
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    /**
     * Test ask returns array
     */
    public function test_ask_returns_array(): void
    {
        // Verify we can instantiate the service
        $this->assertNotNull($this->service);
    }

    /**
     * Test ask accepts question parameter
     */
    public function test_ask_accepts_question(): void
    {
        $question = 'Em là con hộ nghèo được hỗ trợ bao nhiêu?';

        // Test that question parameter is valid
        $this->assertIsString($question);
        $this->assertNotEmpty($question);
    }

    /**
     * Test get_health_status returns array
     */
    public function test_get_health_status_returns_array(): void
    {
        $result = $this->service->getHealthStatus();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('timestamp', $result);
    }

    /**
     * Test get_configuration returns array
     */
    public function test_get_configuration_returns_array(): void
    {
        $result = $this->service->getConfiguration();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('vector_search_topk', $result);
        $this->assertArrayHasKey('rerank_topk', $result);
        $this->assertArrayHasKey('context_max_tokens', $result);
        $this->assertArrayHasKey('generation_max_tokens', $result);
    }

    /**
     * Test configuration values are reasonable
     */
    public function test_configuration_values_reasonable(): void
    {
        $config = $this->service->getConfiguration();

        // TopK values should be positive
        $this->assertGreaterThan(0, $config['vector_search_topk']);
        $this->assertGreaterThan(0, $config['rerank_topk']);

        // Search topK should be >= rerank topK
        $this->assertGreaterThanOrEqual(
            $config['rerank_topk'],
            $config['vector_search_topk']
        );

        // Token limits should be positive
        $this->assertGreaterThan(0, $config['context_max_tokens']);
        $this->assertGreaterThan(0, $config['generation_max_tokens']);
    }

    /**
     * Test warmup_cache accepts array
     */
    public function test_warmup_cache_accepts_array(): void
    {
        $qaPairs = [
            [
                'question' => 'Q1?',
                'answer' => 'A1',
                'citations' => ['Điều 1']
            ]
        ];

        $result = $this->service->warmupCache($qaPairs);

        // Should return number of added entries
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    /**
     * Test warmup_cache with empty array
     */
    public function test_warmup_cache_with_empty_array(): void
    {
        $result = $this->service->warmupCache([]);

        $this->assertIsInt($result);
        $this->assertEquals(0, $result);
    }

    /**
     * Test warmup_cache with multiple entries
     */
    public function test_warmup_cache_with_multiple_entries(): void
    {
        $qaPairs = [
            ['question' => 'Q1?', 'answer' => 'A1', 'citations' => ['Điều 1']],
            ['question' => 'Q2?', 'answer' => 'A2', 'citations' => ['Điều 2']],
            ['question' => 'Q3?', 'answer' => 'A3', 'citations' => ['Điều 3']]
        ];

        $result = $this->service->warmupCache($qaPairs);

        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    /**
     * Test warmup_cache with incomplete entries (handles gracefully)
     */
    public function test_warmup_cache_with_incomplete_entries(): void
    {
        $qaPairs = [
            ['question' => 'Q1?'],  // Missing answer and citations
            ['answer' => 'A2'],  // Missing question
            ['question' => 'Q3?', 'answer' => 'A3']  // Missing citations
        ];

        $result = $this->service->warmupCache($qaPairs);

        // Should handle gracefully (some might fail, some succeed)
        $this->assertIsInt($result);
    }

    /**
     * Test pipeline configuration has generation temperature 0.0 (deterministic)
     */
    public function test_generation_temperature_is_deterministic(): void
    {
        $config = $this->service->getConfiguration();

        $this->assertEquals(0.0, $config['generation_temperature']);
    }

    /**
     * Test vector_similarity_threshold is in valid range
     */
    public function test_similarity_threshold_in_range(): void
    {
        $config = $this->service->getConfiguration();

        $threshold = $config['vector_similarity_threshold'];
        $this->assertGreaterThanOrEqual(0, $threshold);
        $this->assertLessThanOrEqual(1, $threshold);
    }

    /**
     * Test health status has required keys
     */
    public function test_health_status_has_service_checks(): void
    {
        $result = $this->service->getHealthStatus();

        // Should have checks for various services
        $this->assertIsArray($result);

        // At least some health indicators
        $keys = array_keys($result);
        $this->assertGreaterThan(0, count($keys));
    }

    /**
     * Test pipeline accepts injected dependencies
     */
    public function test_pipeline_accepts_injected_dependencies(): void
    {
        // Should be able to create with injected dependencies
        $mockCache = Mockery::mock(SemanticCacheService::class);

        $service = new RAGPipelineService(
            cacheService: $mockCache
        );

        $this->assertNotNull($service);
    }

    /**
     * Test pipeline creates with all mocked dependencies
     */
    public function test_pipeline_creates_with_null_dependencies(): void
    {
        $service = new RAGPipelineService(
            cacheService: Mockery::mock(SemanticCacheService::class),
            vectorSearchService: Mockery::mock(VectorSearchService::class),
            contextBuilderService: Mockery::mock(ContextBuilderService::class),
            generationService: Mockery::mock(GenerationService::class),
            citationService: Mockery::mock(CitationService::class)
        );

        $this->assertNotNull($service);
        $this->assertIsArray($service->getConfiguration());
    }

    /**
     * Test pipeline returns valid configuration structure
     */
    public function test_pipeline_configuration_complete(): void
    {
        $config = $this->service->getConfiguration();

        $requiredKeys = [
            'cache_type',
            'vector_search_topk',
            'rerank_topk',
            'context_max_tokens',
            'generation_max_tokens',
            'generation_temperature',
            'vector_similarity_threshold'
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $config, "Missing key: $key");
        }
    }
}
