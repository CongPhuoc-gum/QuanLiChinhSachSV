<?php declare(strict_types=1);

namespace App\Common\Exceptions;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * RAGException - Base exception for RAG system
 *
 * All RAG-related exceptions inherit from this.
 * Provides consistent logging and context.
 *
 * Usage:
 *   throw new RAGException('Operation failed', 0, $previous);
 *   throw new EmbeddingException('Gemini API error', 503, $previous);
 *   throw new VectorSearchException('ChromaDB connection failed');
 */
class RAGException extends \Exception
{
    /**
     * Context data for logging
     *
     * @var array
     */
    protected array $context = [];

    /**
     * Create exception with optional context
     *
     * @param string $message Error message
     * @param int $code HTTP status code or error code
     * @param Throwable|null $previous Previous exception
     * @param array $context Additional context for logging
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;

        // Log exception with context
        $this->logException();
    }

    /**
     * Log exception with context
     *
     * @return void
     */
    protected function logException(): void
    {
        $logContext = array_merge(
            [
                'exception_class' => static::class,
                'error_message' => $this->message,
                'error_code' => $this->code,
            ],
            $this->context
        );

        Log::error(static::class, $logContext);
    }

    /**
     * Get context data
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set context data
     *
     * @param array $context
     * @return self
     */
    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    /**
     * Add context data
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function addContext(string $key, mixed $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * Get HTTP status code
     *
     * @return int
     */
    public function getHttpStatusCode(): int
    {
        return match ($this->code) {
            503 => 503,  // Service unavailable
            504 => 504,  // Gateway timeout
            default => 400
        };
    }
}
