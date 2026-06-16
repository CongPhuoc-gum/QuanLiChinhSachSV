<?php declare(strict_types=1);

namespace App\Services;

use App\Common\Exceptions\IndexingException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * KnowledgeIndexerService - Index documents into ChromaDB for vector search
 *
 * Single Responsibility: Document indexing ONLY
 * - Reads knowledge base files
 * - Chunks documents
 * - Generates embeddings
 * - Sends to ChromaDB
 *
 * @package App\Services
 */
class KnowledgeIndexerService
{
    private EmbeddingService $embeddingService;
    private string $chromaUrl = 'http://localhost:8000';
    private string $collectionName = 'nghidinh81';
    private string $knowledgeBasePath = 'ai/nghidinh81.txt';

    public function __construct(EmbeddingService $embeddingService = null)
    {
        $this->embeddingService = $embeddingService ?? new EmbeddingService();
        $this->chromaUrl = env('CHROMA_URL', 'http://localhost:8000');
        $this->collectionName = env('CHROMA_COLLECTION', 'nghidinh81');
        $this->knowledgeBasePath = env('AI_KB_PATH', 'ai/nghidinh81.txt');
    }

    /**
     * Index documents from knowledge base using smart chunking
     *
     * Flow:
     * 1. Read knowledge base file
     * 2. Smart chunk with structure awareness (Nghị định → Điều → Khoản → Điểm)
     * 3. Generate embeddings for each chunk (with metadata)
     * 4. Send to ChromaDB
     *
     * @param int $chunkSize - DEPRECATED (now uses smart chunking)
     * @return array - {success, total_chunks, indexed_chunks, errors}
     * @throws IndexingException
     */
    public function index(int $chunkSize = 500): array
    {
        try {
            Log::info('KnowledgeIndexerService::index - Starting with smart chunking...');

            // STEP 1: Read knowledge base
            if (!Storage::disk('local')->exists($this->knowledgeBasePath)) {
                throw new IndexingException("Knowledge base not found: {$this->knowledgeBasePath}");
            }

            $content = Storage::disk('local')->get($this->knowledgeBasePath);

            // STEP 2: Smart chunk (NOT simple char-based chunking)
            $smartChunking = new SmartChunkingService();
            $chunked = $smartChunking->chunk($content);
            $chunks = $chunked['chunks'];

            Log::info("KnowledgeIndexerService::index - Smart chunked into {$chunked['count']} chunks");

            // Log statistics
            $stats = $smartChunking->getStatistics($chunked);
            Log::info('KnowledgeIndexerService::index - Chunk statistics', $stats);

            // STEP 3: Generate embeddings with metadata
            $documents = $this->prepareDocumentsWithMetadata($chunks);
            Log::info("KnowledgeIndexerService::index - Generated embeddings for {$documents['count']} chunks");

            // STEP 4: Send to ChromaDB
            $result = $this->sendToChromaDB($documents['data']);

            Log::info('KnowledgeIndexerService::index - Complete', [
                'total_chunks' => count($chunks),
                'indexed' => $result['indexed'],
                'failed' => $result['failed'],
            ]);

            return [
                'success' => true,
                'total_chunks' => count($chunks),
                'indexed_chunks' => $result['indexed'],
                'failed_chunks' => $result['failed'],
                'statistics' => $stats,
            ];
        } catch (IndexingException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('KnowledgeIndexerService::index - Error', [
                'message' => $e->getMessage(),
            ]);
            throw new IndexingException("Indexing failed: {$e->getMessage()}");
        }
    }

    /**
     * Chunk document into smaller pieces
     *
     * Strategy:
     * 1. Split by sentences (preserve context)
     * 2. Group into chunks of ~chunkSize characters
     * 3. Each chunk should be meaningful (not cut mid-sentence if possible)
     *
     * @param string $content - Full document content
     * @param int $chunkSize - Target chunk size in characters
     * @return array - {count, chunks}
     */
    public function chunk(string $content, int $chunkSize = 500): array
    {
        try {
            // Split by sentence (ends with . ! ?)
            $sentences = preg_split('/(?<=[.!?\n])\s+/u', $content);

            $chunks = [];
            $current = '';

            foreach ($sentences as $sentence) {
                $sentence = trim($sentence);
                if (empty($sentence)) {
                    continue;
                }

                // If adding this sentence doesn't exceed chunkSize
                if (strlen($current) + strlen($sentence) + 1 <= $chunkSize) {
                    $current .= (empty($current) ? '' : ' ') . $sentence;
                } else {
                    // Save current chunk and start new one
                    if (!empty($current)) {
                        $chunks[] = trim($current);
                    }
                    $current = $sentence;
                }
            }

            // Add final chunk
            if (!empty($current)) {
                $chunks[] = trim($current);
            }

            Log::debug('KnowledgeIndexerService::chunk - Complete', [
                'chunks_count' => count($chunks),
                'avg_chunk_size' => count($chunks) > 0 ? round(strlen($content) / count($chunks), 0) : 0,
            ]);

            return [
                'count' => count($chunks),
                'chunks' => $chunks,
            ];
        } catch (\Exception $e) {
            Log::error('KnowledgeIndexerService::chunk - Error', [
                'message' => $e->getMessage(),
            ]);
            throw new IndexingException("Chunking failed: {$e->getMessage()}");
        }
    }

    /**
     * Prepare documents for ChromaDB with smart chunk metadata
     *
     * Uses metadata from SmartChunkingService
     *
     * @param array $chunks - Array of {text, metadata} from SmartChunkingService
     * @return array - {count, data}
     */
    private function prepareDocumentsWithMetadata(array $chunks): array
    {
        try {
            $documents = [];
            $failed = 0;

            foreach ($chunks as $idx => $chunk) {
                try {
                    // Generate embedding using EmbeddingService
                    $embedding = $this->embeddingService->generate($chunk['text']);

                    // Use metadata from smart chunking
                    $metadata = array_merge(
                        $chunk['metadata'] ?? [],
                        [
                            'chunk_index' => $idx,
                            'chunk_size' => strlen($chunk['text']),
                        ]
                    );

                    // Prepare ChromaDB document
                    $documents[] = [
                        'ids' => ["chunk_{$idx}"],
                        'embeddings' => [$embedding],
                        'metadatas' => [$metadata],
                        'documents' => [$chunk['text']],
                    ];
                } catch (\Exception $e) {
                    Log::warning("KnowledgeIndexerService::prepareDocumentsWithMetadata - Failed chunk {$idx}", [
                        'error' => $e->getMessage(),
                    ]);
                    $failed++;
                }
            }

            Log::debug('KnowledgeIndexerService::prepareDocumentsWithMetadata - Complete', [
                'total' => count($chunks),
                'prepared' => count($documents),
                'failed' => $failed,
            ]);

            return [
                'count' => count($documents),
                'data' => $documents,
            ];
        } catch (\Exception $e) {
            Log::error('KnowledgeIndexerService::prepareDocumentsWithMetadata - Error', [
                'message' => $e->getMessage(),
            ]);
            throw new IndexingException("Document preparation failed: {$e->getMessage()}");
        }
    }

    /**
     * DEPRECATED: Prepare documents (old method)
     *
     * @deprecated Use prepareDocumentsWithMetadata instead
     */
    private function prepareDocuments(array $chunks): array
    {
        // Fallback to simple metadata if needed
        $documents = [];

        foreach ($chunks as $idx => $chunkText) {
            try {
                $embedding = $this->embeddingService->generate($chunkText);
                $documents[] = [
                    'ids' => ["chunk_{$idx}"],
                    'embeddings' => [$embedding],
                    'metadatas' => [[
                        'source' => 'nghidinh81.txt',
                        'chunk_index' => $idx,
                    ]],
                    'documents' => [$chunkText],
                ];
            } catch (\Exception $e) {
                Log::warning("Failed chunk {$idx}", ['error' => $e->getMessage()]);
            }
        }

        return ['count' => count($documents), 'data' => $documents];
    }

    /**
     * Extract "Điều X" from chunk text
     *
     * Looks for Vietnamese law structure: "Điều 3", "Điều 4", etc.
     *
     * @param string $text - Chunk text
     * @return string - "Điều X" or "Nghị định 81/2021"
     */
    private function extractDinhSection(string $text): string
    {
        if (preg_match('/Điều\s+\d+/u', $text, $matches)) {
            return $matches[0];
        }

        // Try to find "Khoản" (Clause)
        if (preg_match('/Khoản\s+\d+/u', $text, $matches)) {
            return $matches[0];
        }

        return 'Nghị định 81/2021';
    }

    /**
     * Send documents to ChromaDB
     *
     * Creates collection (if not exists) and adds documents
     *
     * @param array $documents - Prepared documents
     * @return array - {indexed, failed}
     * @throws IndexingException
     */
    private function sendToChromaDB(array $documents): array
    {
        try {
            // Ensure collection exists
            $this->ensureCollection();

            $indexed = 0;
            $failed = 0;

            // Add documents to collection
            foreach ($documents as $doc) {
                try {
                    $response = Http::timeout(30)->post(
                        "{$this->chromaUrl}/api/v1/collections/{$this->collectionName}/add",
                        $doc
                    );

                    if ($response->successful()) {
                        $indexed++;
                    } else {
                        Log::warning('ChromaDB add document failed', [
                            'status' => $response->status(),
                        ]);
                        $failed++;
                    }
                } catch (\Exception $e) {
                    Log::warning('ChromaDB add document error', [
                        'error' => $e->getMessage(),
                    ]);
                    $failed++;
                }
            }

            Log::info('KnowledgeIndexerService::sendToChromaDB - Complete', [
                'indexed' => $indexed,
                'failed' => $failed,
            ]);

            return [
                'indexed' => $indexed,
                'failed' => $failed,
            ];
        } catch (\Exception $e) {
            Log::error('KnowledgeIndexerService::sendToChromaDB - Error', [
                'message' => $e->getMessage(),
            ]);
            throw new IndexingException("ChromaDB send failed: {$e->getMessage()}");
        }
    }

    /**
     * Ensure ChromaDB collection exists
     *
     * Creates collection with cosine distance metric if not exists
     *
     * @return void
     */
    private function ensureCollection(): void
    {
        try {
            // Try to get collection
            $response = Http::timeout(5)->get(
                "{$this->chromaUrl}/api/v1/collections/{$this->collectionName}"
            );

            if ($response->successful()) {
                Log::debug('ChromaDB collection exists');
                return;
            }

            // Collection doesn't exist, create it
            $response = Http::timeout(30)->post(
                "{$this->chromaUrl}/api/v1/collections",
                [
                    'name' => $this->collectionName,
                    'metadata' => ['hnsw:space' => 'cosine'],
                ]
            );

            if ($response->successful()) {
                Log::info('ChromaDB collection created', [
                    'collection' => $this->collectionName,
                ]);
            } else {
                throw new IndexingException("Failed to create collection: {$response->status()}");
            }
        } catch (\Exception $e) {
            Log::error('KnowledgeIndexerService::ensureCollection - Error', [
                'message' => $e->getMessage(),
            ]);
            throw new IndexingException("Collection management failed: {$e->getMessage()}");
        }
    }

    /**
     * Clear collection (delete all documents)
     *
     * Useful for reindexing
     *
     * @return bool
     */
    public function clear(): bool
    {
        try {
            $response = Http::timeout(30)->delete(
                "{$this->chromaUrl}/api/v1/collections/{$this->collectionName}"
            );

            if ($response->successful()) {
                Log::info('ChromaDB collection cleared', ['collection' => $this->collectionName]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::warning('KnowledgeIndexerService::clear - Error', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
