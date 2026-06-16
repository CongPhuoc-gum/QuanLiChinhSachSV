<?php declare(strict_types=1);

namespace App\Common\Exceptions;

/**
 * GenerationException - Gemini generation failed
 *
 * Thrown when Gemini API fails to generate an answer.
 */
class GenerationException extends RAGException {}
