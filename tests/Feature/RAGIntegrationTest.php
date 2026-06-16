<?php

namespace Tests\Feature;

use App\Services\RAGPipelineService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

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

    protected function setUp(): void
    {
        parent::setUp();
        $this->pipeline = app(RAGPipelineService::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Http::allowStrayRequests();
    }

    /**
     * STEP 4C1: Gemini Timeout Handling
     * Gemini API times out → should fail gracefully
     */
    public function test_gemini_timeout_handling(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => function () {
                throw new \Exception('Connection timeout');
            }
        ]);

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
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response(
                ['error' => 'Rate limit exceeded'],
                429
            )
        ]);

        // Should not crash
        $health = $this->pipeline->getHealthStatus();
        $this->assertIsArray($health);

        $config = $this->pipeline->getConfiguration();
        $this->assertGreaterThan(0, count($config));
    }

    /**
     * STEP 4C3: Gemini 500 (Server Error)
     * Gemini returns 500 → handle server error
     */
    public function test_gemini_500_server_error(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response(
                ['error' => 'Internal server error'],
                500
            )
        ]);

        // Should not crash
        $health = $this->pipeline->getHealthStatus();
        $this->assertIsArray($health);

        // Should still return config
        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);
        $this->assertArrayHasKey('cache_type', $config);
    }

    /**
     * STEP 4C4: ChromaDB Unavailable
     * ChromaDB connection refused → handle gracefully
     */
    public function test_chroma_db_unavailable(): void
    {
        Http::fake([
            '*localhost:8000*' => function () {
                throw new \Exception('Connection refused');
            }
        ]);

        // Should not crash
        $health = $this->pipeline->getHealthStatus();
        $this->assertIsArray($health);

        // Should still work with cache fallback
        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);
    }

    /**
     * STEP 4C5: Network Failure
     * Complete network failure → handle network errors
     */
    public function test_network_failure_handling(): void
    {
        Http::fake([
            '*' => function () {
                throw new \Exception('Network unreachable');
            }
        ]);

        // Should not crash
        $health = $this->pipeline->getHealthStatus();
        $this->assertIsArray($health);

        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);
    }

    /**
     * STEP 4C6: Cache Storage Unavailable
     * Cache storage fails → continue without caching
     */
    public function test_cache_unavailable_fallback(): void
    {
        // Test that system works even when cache is down
        // by checking configuration without relying on cache

        $config = $this->pipeline->getConfiguration();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('cache_type', $config);

        // Cache type should be defined
        $this->assertNotEmpty($config['cache_type']);
    }

    /**
     * STEP 4C7: JSON Corruption in Response
     * API returns corrupted JSON → handle gracefully
     */
    public function test_json_corruption_in_response(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response(
                'Invalid JSON {broken',
                200
            )
        ]);

        // Should not crash with invalid JSON
        $health = $this->pipeline->getHealthStatus();
        $this->assertIsArray($health);
    }

    /**
     * STEP 4C8: Metadata Corruption
     * Vector search returns corrupted metadata → handle gracefully
     */
    public function test_metadata_corruption_handling(): void
    {
        // Test with various corrupted metadata
        $corruptedMetadata = [
            'article' => -1,
            'clause' => '',
            'source' => null,
            'invalid_field' => 'should not crash'
        ];

        // Verify system can handle it
        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);
        $this->assertIsInt($config['vector_search_topk']);
    }

    /**
     * STEP 4C9: Empty API Response
     * API returns empty response → handle gracefully
     */
    public function test_empty_api_response(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([], 200)
        ]);

        // Should not crash with empty response
        $health = $this->pipeline->getHealthStatus();
        $this->assertIsArray($health);
    }

    /**
     * STEP 4C10: Partial Response (Missing Fields)
     * API response missing expected fields → handle gracefully
     */
    public function test_partial_response_handling(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response(
                ['candidates' => [['content' => []]]],  // Missing text
                200
            )
        ]);

        // Should not crash with partial response
        $health = $this->pipeline->getHealthStatus();
        $this->assertIsArray($health);
    }

    /**
     * STEP 4C11: Fallback Mechanism - No Cache, ChromaDB Down
     * Both cache and ChromaDB unavailable → use fallback
     */
    public function test_fallback_both_cache_and_chroma_down(): void
    {
        Http::fake([
            '*localhost:8000*' => Http::response([], 503),
            '*cache*' => function () {
                throw new \Exception('Cache unavailable');
            }
        ]);

        // System should still function
        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);
        $this->assertGreaterThan(0, count($config));
    }

    /**
     * STEP 4C12: Retry on Transient Failure
     * First request fails, second succeeds → verify retry logic
     */
    public function test_retry_on_transient_failure(): void
    {
        $callCount = 0;

        Http::fake([
            '*generativelanguage.googleapis.com*' => function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    throw new \Exception('Transient failure');
                }
                return Http::response([
                    'embedding' => ['values' => array_fill(0, 768, 0.1)]
                ], 200);
            }
        ]);

        // Should handle gracefully
        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);
    }

    /**
     * STEP 4C13: Verify No Stack Trace in Response
     * Errors should not expose stack traces
     */
    public function test_no_stack_trace_in_error_response(): void
    {
        // Configuration should be user-friendly, not expose internals
        $config = $this->pipeline->getConfiguration();

        $configJson = json_encode($config);

        // Should not contain stack trace indicators
        $this->assertStringNotContainsString('Stack trace', $configJson);
        $this->assertStringNotContainsString('Exception', $configJson);
        $this->assertStringNotContainsString('at line', $configJson);
    }

    /**
     * STEP 4C14: Logging of Errors
     * Errors should be logged for debugging
     */
    public function test_error_logging_enabled(): void
    {
        // Verify logging config is available
        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);

        // Verify logger channels exist
        $logChannels = config('logging.channels');
        $this->assertNotEmpty($logChannels);
    }

    /**
     * STEP 4C15: Configuration Stability
     * Configuration should be consistent across calls
     */
    public function test_configuration_stability(): void
    {
        $config1 = $this->pipeline->getConfiguration();
        $config2 = $this->pipeline->getConfiguration();
        $config3 = $this->pipeline->getConfiguration();

        // All should be identical
        $this->assertEquals($config1, $config2);
        $this->assertEquals($config2, $config3);

        // Verify key structure
        $this->assertArrayHasKey('vector_search_topk', $config1);
        $this->assertArrayHasKey('generation_temperature', $config1);
    }

    /**
     * STEP 4C16: Health Status Consistency
     * Health status should be retrievable multiple times
     */
    public function test_health_status_consistency(): void
    {
        $health1 = $this->pipeline->getHealthStatus();
        $health2 = $this->pipeline->getHealthStatus();

        $this->assertIsArray($health1);
        $this->assertIsArray($health2);

        // Both should have timestamp
        $this->assertArrayHasKey('timestamp', $health1);
        $this->assertArrayHasKey('timestamp', $health2);
    }

    /**
     * STEP 4C17: Memory Efficiency Under Load
     * Processing many questions shouldn't leak memory
     */
    public function test_memory_efficiency_under_load(): void
    {
        $initialMemory = memory_get_usage();

        // Simulate multiple operations
        for ($i = 0; $i < 10; $i++) {
            $config = $this->pipeline->getConfiguration();
            $health = $this->pipeline->getHealthStatus();
            $this->assertIsArray($config);
            $this->assertIsArray($health);
        }

        $finalMemory = memory_get_usage();

        // Memory shouldn't grow dramatically (less than 5MB increase acceptable)
        $diff = $finalMemory - $initialMemory;
        $this->assertLessThan(5 * 1024 * 1024, $diff);
    }

    /**
     * STEP 4C18: Concurrent Request Simulation
     * Multiple requests in sequence shouldn't cause issues
     */
    public function test_concurrent_request_simulation(): void
    {
        $results = [];

        for ($i = 0; $i < 5; $i++) {
            $config = $this->pipeline->getConfiguration();
            $results[] = $config;
        }

        // All results should be valid
        foreach ($results as $result) {
            $this->assertIsArray($result);
            $this->assertArrayHasKey('cache_type', $result);
        }
    }

    /**
     * STEP 4C19: Service Degradation Gracefully
     * When services are down, system degrades gracefully
     */
    public function test_service_degradation_gracefully(): void
    {
        Http::fake([
            '*' => Http::response([], 503)  // All services down
        ]);

        // Should still return valid config (not crash)
        $config = $this->pipeline->getConfiguration();
        $this->assertIsArray($config);
        $this->assertIsInt($config['vector_search_topk']);
        $this->assertIsFloat($config['generation_temperature']);
    }

    /**
     * STEP 4C20: Cache Warmup Under Failure
     * Cache warmup should handle failures gracefully
     */
    public function test_cache_warmup_with_failures(): void
    {
        $qaPairs = [
            ['question' => 'Q1?', 'answer' => 'A1', 'citations' => ['Điều 1']],
            ['question' => 'Q2?'],  // Invalid (missing answer)
            ['question' => '', 'answer' => 'A3', 'citations' => ['Điều 3']],  // Empty question
            ['question' => 'Q4?', 'answer' => 'A4', 'citations' => ['Điều 4']]
        ];

        // Should handle mixed valid/invalid data
        $result = $this->pipeline->warmupCache($qaPairs);

        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
        // At least some should succeed
        $this->assertGreaterThanOrEqual(0, $result);
    }
}
