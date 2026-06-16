<?php

namespace App\Console\Commands;

use App\Services\ImprovedRAGService;
use Illuminate\Console\Command;

class RagIndexDocuments extends Command
{
    protected $signature = 'rag:index-documents';
    protected $description = 'Index Nghị định 81/2021 vào ChromaDB để sử dụng cho RAG chatbot';

    public function handle(ImprovedRAGService $ragService): int
    {
        $this->info('🔄 Starting RAG indexing for Nghị định 81/2021...');
        $this->newLine();

        try {
            $this->info('📚 Loading knowledge base...');
            $ragService->indexDocuments();

            $this->newLine();
            $this->info('✅ RAG indexing completed successfully!');
            $this->info('🎯 ChromaDB now has Nghị định 81/2021 indexed and ready for vector search.');
            $this->info('');
            $this->info('You can now use:');
            $this->line('  • POST /api/chatbot/improved/ask - Improved RAG chatbot');
            $this->line('  • POST /api/ho-so/{id}/analyze-for-reduction - Auto-analyze hồ sơ');
            $this->newLine();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Error during RAG indexing:');
            $this->error($e->getMessage());
            $this->newLine();

            if (config('app.debug')) {
                $this->error('Trace:');
                $this->error($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }
}
