<?php declare(strict_types=1);

namespace App\Services;

use App\Common\Exceptions\EmbeddingException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * EmbeddingService - Generate embeddings for text using Gemini API
 *
 * Single Responsibility: Generate embeddings ONLY
 * - Takes text as input
 * - Returns embedding vector
 * - Handles API calls and errors
 *
 * @package App\Services
 */
class EmbeddingService
{
    /**
     * Generate embedding for text using Gemini Embedding API
     *
     * @param string $text - Text to embed
     * @return array - Embedding vector [0.12, -0.34, 0.56, ...]
     * @throws EmbeddingException
     */
    public function generate(string $text): array
    {
        try {
            if (empty($text)) {
                throw new EmbeddingException('Text cannot be empty');
            }

            $response = Http::timeout(15)->post(
                'https://generativelanguage.googleapis.com/v1/models/embedding-001:embedContent',
                [
                    'model' => 'models/embedding-001',
                    'content' => [
                        'parts' => [
                            ['text' => $text]
                        ]
                    ]
                ],
                [
                    'x-goog-api-key' => env('GEMINI_API_KEY'),
                ]
            );

            if (!$response->successful()) {
                Log::error('EmbeddingService::generate - API failed', [
                    'status' => $response->status(),
                    'text_length' => strlen($text),
                ]);
                throw new EmbeddingException("Embedding API returned status {$response->status()}");
            }

            $data = $response->json();
            $embedding = $data['embedding']['values'] ?? [];

            if (empty($embedding)) {
                throw new EmbeddingException('No embedding returned from API');
            }

            Log::debug('EmbeddingService::generate - Success', [
                'text_length' => strlen($text),
                'embedding_dimension' => count($embedding),
            ]);

            return $embedding;
        } catch (EmbeddingException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('EmbeddingService::generate - Error', [
                'message' => $e->getMessage(),
                'text_length' => strlen($text),
            ]);
            throw new EmbeddingException("Failed to generate embedding: {$e->getMessage()}");
        }
    }

    /**
     * Batch generate embeddings for multiple texts
     *
     * Note: Gemini API doesn't support batch embeddings,
     * so this calls generate() multiple times.
     * In V2, switch to a batch-capable embedding model.
     *
     * @param array $texts - Array of texts to embed
     * @return array - Array of embedding vectors
     * @throws EmbeddingException
     */
    public function generateBatch(array $texts): array
    {
        try {
            $embeddings = [];
            $failed = 0;

            foreach ($texts as $idx => $text) {
                try {
                    $embeddings[] = $this->generate($text);
                } catch (EmbeddingException $e) {
                    Log::warning('EmbeddingService::generateBatch - Failed for index ' . $idx, [
                        'error' => $e->getMessage(),
                    ]);
                    $failed++;
                    $embeddings[] = [];  // Empty vector for failed items
                }
            }

            if ($failed > 0) {
                Log::warning('EmbeddingService::generateBatch - Some embeddings failed', [
                    'total' => count($texts),
                    'failed' => $failed,
                ]);
            }

            return $embeddings;
        } catch (\Exception $e) {
            Log::error('EmbeddingService::generateBatch - Error', [
                'message' => $e->getMessage(),
                'count' => count($texts),
            ]);
            throw new EmbeddingException("Batch embedding failed: {$e->getMessage()}");
        }
    }

    /**
     * Get embedding dimension (size of embedding vector)
     *
     * Gemini embedding-001 model: 768 dimensions
     *
     * @return int
     */
    public function getDimension(): int
    {
        return 768;  // Gemini embedding-001 model
    }
}
