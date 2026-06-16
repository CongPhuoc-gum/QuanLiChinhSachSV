<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * ImprovedRAGService - DEPRECATED Facade/Compatibility Layer
 *
 * ⚠️ DEPRECATED: This service is now a facade for backward compatibility.
 * Use RAGPipelineService, VectorSearchService, EmbeddingService, etc. instead.
 *
 * Delegates to:
 * - RAGPipelineService (main orchestration)
 * - KnowledgeIndexerService (document indexing)
 * - EmbeddingService (embeddings)
 * - VectorSearchService (vector search)
 * - GenerationService (text generation)
 * - ContextBuilderService (context building)
 *
 * Will be removed in next major version.
 *
 * @deprecated Use RAGPipelineService instead for new code
 */
class ImprovedRAGService
{
    private RAGPipelineService $pipeline;
    private KnowledgeIndexerService $indexer;
    private GeminiService $geminiService;

    public function __construct(
        GeminiService $geminiService,
        RAGPipelineService $pipeline = null,
        KnowledgeIndexerService $indexer = null
    ) {
        $this->geminiService = $geminiService;
        $this->pipeline = $pipeline ?? new RAGPipelineService();
        $this->indexer = $indexer ?? new KnowledgeIndexerService();
    }

    /**
     * DEPRECATED: Ask chatbot - Delegates to RAGPipelineService
     *
     * @param string $userQuestion
     * @return array - {success, answer, citations, method, metadata}
     * @deprecated Use RAGPipelineService::ask() instead
     */
    public function improvedAskChatbot(string $userQuestion): array
    {
        Log::debug('ImprovedRAGService::improvedAskChatbot DEPRECATED - use RAGPipelineService instead');
        return $this->pipeline->ask($userQuestion);
    }

    /**
     * DEPRECATED: Index documents - Delegates to KnowledgeIndexerService
     *
     * @return void
     * @deprecated Use KnowledgeIndexerService::index() instead
     */
    public function indexDocuments(): void
    {
        Log::debug('ImprovedRAGService::indexDocuments DEPRECATED - use KnowledgeIndexerService instead');
        $this->indexer->index();
    }

    /**
     * Get the underlying RAG Pipeline
     *
     * For accessing new services directly
     *
     * @return RAGPipelineService
     */
    public function getPipeline(): RAGPipelineService
    {
        return $this->pipeline;
    }

    /**
     * Get the knowledge indexer
     *
     * For document management
     *
     * @return KnowledgeIndexerService
     */
    public function getIndexer(): KnowledgeIndexerService
    {
        return $this->indexer;
    }
}
