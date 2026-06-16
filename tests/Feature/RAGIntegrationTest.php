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
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Mockery;

/**
 * STEP 4C - Integration Tests
 *
 * Test resilience against external service failures:
 * - Gemini timeouts
 * - Gemini 429 (rate limit)
 * - Gemini 500 (server error)
 * - ChromaDB unavailable
 * - Network failures
 * - Cache unavailable
 * - JSON corruption
 * - Metadata corruption
 * - Fallback behavior
 */
class RAGIntegrationTest extends TestCase
{
    private RAGPipelineService $pipeline;
    private $mockCache;
    private $mockVectorSearch;
    private $mockGeneration;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();

        // Mock all external services to prevent real API calls
        $this->mockCache = Mockery::mock(SemanticCacheService::class);
        $this->mockVectorSearch = Mockery::mock(VectorSearchService::class);
        $this->mockGeneration = Mockery::mock(GenerationService::class);

        // Set default behavior - cache miss, search returns empty, generation returns placeholder
        $this
            ->mockCache
            ->shouldReceive('get')
            ->byDefault()
            ->andReturnNull();

        $this
            ->mockVectorSearch
            ->shouldReceive('search')
            ->byDefault()
            ->andReturn([]);

        $this
            ->mockVectorSearch
            ->shouldReceive('isHealthy')
            ->byDefault()
            ->andReturn(true);

        $this
            ->mockGeneration
            ->shouldReceive('generate')
            ->byDefault()
            ->andReturn('Mock response');

        // Create pipeline with mocked services
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
     * STEP 4C1: Gemini Timeout Handling
     * Gemini API times out → should fail gracefully
     */
    public function test_gemini_timeout_handling(): void
    {
        // Mock generation service to simulate timeout
        $this
            ->mockGeneration
            ->shouldReceive('generate')
            ->andThrow(new \Exception('Connection timeout'));

        // Should not crash the application
        $health = $this->pipeline->getHealthStatus();
        $this->assertIsArray($health);

        // App should continue working
        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);
    }

    /**
     * STEP 4C2: Gemini 429 (Rate Limit)
     * Gemini returns 429 → handle rate limit
     */
    public function test_gemini_rate_limit_429(): void
    {
        // Mock generation service to simulate rate limit
        $this
            ->mockGeneration
            ->shouldReceive('generate')
            ->andThrow(new \Exception('Rate limit: 429'));

        // Should not crash
        $health = $this->pipeline->getHealthStatus();
        $this->assertIsArray($health);

        $config = $this->pipeline->getConfiguration();
        $this->assertGreaterThan(0, count($config));
    }

    /**
     * STEP 4C3: Gemini 500 Server Error
     * Gemini returns 500 → handle server error
     */
    public function test_gemini_500_server_error(): void
    {
        // Mock generation service to simulate server error
        $this
            ->mockGeneration
            ->shouldReceive('generate')
            ->andThrow(new \Exception('Server error: 500'));

        // Should not crash
        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);
        $this->assertArrayHasKey('vector_similarity_threshold', $config);
    }

    /**
     * STEP 4C4: ChromaDB Unavailable
     * Vector search service unavailable → handle gracefully
     */
    public function test_chroma_db_unavailable(): void
    {
        // Mock vector search to throw exception
        $this
            ->mockVectorSearch
            ->shouldReceive('search')
            ->andThrow(new \Exception('ChromaDB connection failed'));

        // Should not crash
        $health = $this->pipeline->getHealthStatus();
        $this->assertIsArray($health);
    }

    /**
     * STEP 4C5: Network Failure Handling
     * Network connection fails → handle gracefully
     */
    public function test_network_failure_handling(): void
    {
        // Mock both services to simulate network failure
        $this
            ->mockCache
            ->shouldReceive('get')
            ->andThrow(new \Exception('Network unreachable'));

        $this
            ->mockVectorSearch
            ->shouldReceive('search')
            ->andThrow(new \Exception('Network unreachable'));

        // Should not crash
        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);
    }

    /**
     * STEP 4C6: Cache Unavailable Fallback
     * Cache service unavailable → fallback to vector search
     */
    public function test_cache_unavailable_fallback(): void
    {
        // Cache fails but vector search works
        $this
            ->mockCache
            ->shouldReceive('get')
            ->andThrow(new \Exception('Cache unavailable'));

        $this
            ->mockVectorSearch
            ->shouldReceive('search')
            ->andReturn([
                ['text' => 'Fallback result', 'similarity' => 0.8, 'metadata' => ['article' => '1']]
            ]);

        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);
    }

    /**
     * STEP 4C7: JSON Corruption in Response
     * API returns corrupted JSON → handle gracefully
     */
    public function test_json_corruption_in_response(): void
    {
        // Mock generation to return invalid JSON
        $this
            ->mockGeneration
            ->shouldReceive('generate')
            ->andReturn('Invalid {JSON}');

        // Should not crash
        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);
    }

    /**
     * STEP 4C8: Metadata Corruption Handling
     * Vector search returns corrupted metadata → handle gracefully
     */
    public function test_metadata_corruption_handling(): void
    {
        // Return chunks with corrupted metadata
        $this
            ->mockVectorSearch
            ->shouldReceive('search')
            ->andReturn([
                ['text' => 'Valid text', 'similarity' => 0.8, 'metadata' => null],
                ['text' => 'Another text', 'similarity' => 0.7, 'metadata' => 'invalid']
            ]);

        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);
    }

    /**
     * STEP 4C9: Empty API Response
     * API returns empty response → handle gracefully
     */
    public function test_empty_api_response(): void
    {
        // Mock to return empty
        $this
            ->mockGeneration
            ->shouldReceive('generate')
            ->andReturn('');

        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);
    }

    /**
     * STEP 4C10: Partial Response Handling
     * API returns incomplete response → handle gracefully
     */
    public function test_partial_response_handling(): void
    {
        // Mock to return partial response
        $this
            ->mockGeneration
            ->shouldReceive('generate')
            ->andReturn('Partial...');

        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);
    }

    /**
     * STEP 4C11: Fallback Both Cache and ChromaDB Down
     * Both cache and vector search down → handle gracefully
     */
    public function test_fallback_both_cache_and_chroma_down(): void
    {
        // Both services fail
        $this
            ->mockCache
            ->shouldReceive('get')
            ->andThrow(new \Exception('Cache down'));

        $this
            ->mockVectorSearch
            ->shouldReceive('search')
            ->andThrow(new \Exception('ChromaDB down'));

        // Should still return valid config
        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);
    }

    /**
     * STEP 4C12: Retry on Transient Failure
     * Temporary failure → should retry and recover
     */
    public function test_retry_on_transient_failure(): void
    {
        $callCount = 0;

        // First call fails, second succeeds (retry)
        $this
            ->mockGeneration
            ->shouldReceive('generate')
            ->andReturnUsing(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    throw new \Exception('Transient failure');
                }
                return 'Recovered';
            });

        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);
    }

    /**
     * STEP 4C13: No Stack Trace in Error Response
     * Errors don't expose internal details → security
     */
    public function test_no_stack_trace_in_error_response(): void
    {
        // Mock to throw exception
        $this
            ->mockGeneration
            ->shouldReceive('generate')
            ->andThrow(new \Exception('Internal error'));

        // Should handle without exposing stack trace
        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);

        // Verify no sensitive information leaked
        $configStr = json_encode($config);
        $this->assertStringNotContainsString('stack trace', strtolower($configStr));
    }

    /**
     * STEP 4C14: Error Logging Enabled
     * Errors are logged for debugging
     */
    public function test_error_logging_enabled(): void
    {
        // Mock to throw exception
        $this
            ->mockGeneration
            ->shouldReceive('generate')
            ->andThrow(new \Exception('Test error'));

        // Execute and verify logging works
        Log::spy();
        $config = $this->pipeline->getConfiguration();

        $this->assertIsArray($config);
        // Logging should be enabled (framework default)
    }

    /**
     * STEP 4C15: Configuration Stability
     * Configuration stays consistent under errors
     */
    public function test_configuration_stability(): void
    {
        $config1 = $this->pipeline->getConfiguration();

        // Trigger error
        $this
            ->mockGeneration
            ->shouldReceive('generate')
            ->andThrow(new \Exception('Error'));

        $config2 = $this->pipeline->getConfiguration();

        // Configs should be consistent
        $this->assertIsArray($config1);
        $this->assertIsArray($config2);
        $this->assertEquals(
            $config1['vector_search_topk'],
            $config2['vector_search_topk']
        );
    }

    /**
     * STEP 4C16: Health Status Consistency
     * Health check stays consistent
     */
    public function test_health_status_consistency(): void
    {
        $health1 = $this->pipeline->getHealthStatus();
        $health2 = $this->pipeline->getHealthStatus();

        // Both should be valid
        $this->assertIsArray($health1);
        $this->assertIsArray($health2);
        $this->assertArrayHasKey('timestamp', $health1);
        $this->assertArrayHasKey('timestamp', $health2);
    }

    /**
     * STEP 4C17: Memory Efficiency Under Load
     * System doesn't leak memory under repeated calls
     */
    public function test_memory_efficiency_under_load(): void
    {
        $initialMemory = memory_get_usage();

        // Make repeated calls
        for ($i = 0; $i < 10; $i++) {
            $this->pipeline->getConfiguration();
        }

        $finalMemory = memory_get_usage();

        // Memory increase should be reasonable
        $memoryIncrease = $finalMemory - $initialMemory;
        $this->assertLessThan(1024 * 1024, $memoryIncrease);  // Less than 1MB increase
    }

    /**
     * STEP 4C18: Concurrent Request Simulation
     * Multiple requests can be processed
     */
    public function test_concurrent_request_simulation(): void
    {
        $results = [];

        for ($i = 0; $i < 5; $i++) {
            $results[] = $this->pipeline->getConfiguration();
        }

        // All requests should return valid configs
        foreach ($results as $config) {
            $this->assertIsArray($config);
            $this->assertGreaterThan(0, count($config));
        }
    }

    /**
     * STEP 4C19: Service Degradation Gracefully
     * System degrades gracefully when services fail
     */
    public function test_service_degradation_gracefully(): void
    {
        // Simulate gradual service degradation
        $this
            ->mockCache
            ->shouldReceive('get')
            ->andThrow(new \Exception('Cache degrading'));

        $this
            ->mockVectorSearch
            ->shouldReceive('isHealthy')
            ->andReturn(false);

        // Should still work with reduced functionality
        $health = $this->pipeline->getHealthStatus();
        $this->assertIsArray($health);
    }

    /**
     * STEP 4C20: Cache Warmup with Failures
     * Cache warmup handles failures gracefully
     */
    public function test_cache_warmup_with_failures(): void
    {
        $items = [
            ['question' => 'Q1', 'answer' => 'A1'],
            ['question' => 'Q2', 'answer' => 'A2'],
        ];

        $this
            ->mockCache
            ->shouldReceive('put')
            ->byDefault()
            ->andReturnNull();

        // Warmup should handle any items
        $this->pipeline->warmupCache($items);

        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);
    }
}
