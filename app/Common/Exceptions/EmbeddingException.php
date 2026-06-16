<?php declare(strict_types=1);

namespace App\Common\Exceptions;

/**
 * EmbeddingException - Embedding generation failed
 *
 * Thrown when Gemini Embedding API fails or returns invalid response.
 */
class EmbeddingException extends RAGException {}
