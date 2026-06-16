<?php declare(strict_types=1);

namespace App\Common\Exceptions;

/**
 * VectorSearchException - Vector search failed
 *
 * Thrown when ChromaDB query fails or returns no results.
 */
class VectorSearchException extends RAGException {}
