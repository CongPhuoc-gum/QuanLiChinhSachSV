<?php declare(strict_types=1);

namespace App\Services;

/**
 * TextNormalizationService - Text processing utilities
 *
 * Removes duplicate normalization logic from:
 * - ImprovedRAGService
 * - DynamicRAGTrainingService
 * - GeminiService
 * - AiChatbotController
 * - ComparisonService
 *
 * Single responsibility: Text normalization
 */
class TextNormalizationService
{
    /**
     * Normalize text for comparison (lowercase, trim, strip tags)
     *
     * @param string $text
     * @return string
     */
    public function normalize(string $text): string
    {
        return mb_strtolower(trim(strip_tags($text)));
    }

    /**
     * Normalize text to lowercase only
     *
     * @param string $text
     * @return string
     */
    public function toLower(string $text): string
    {
        return mb_strtolower($text);
    }

    /**
     * Normalize text to uppercase
     *
     * @param string $text
     * @return string
     */
    public function toUpper(string $text): string
    {
        return mb_strtoupper($text);
    }

    /**
     * Normalize Vietnamese text (remove diacritics for search)
     *
     * @param string $text
     * @return string
     */
    public function removeVietnameseDiacritics(string $text): string
    {
        $map = [
            'á' => 'a',
            'à' => 'a',
            'ả' => 'a',
            'ã' => 'a',
            'ạ' => 'a',
            'ă' => 'a',
            'ắ' => 'a',
            'ằ' => 'a',
            'ẳ' => 'a',
            'ẵ' => 'a',
            'ặ' => 'a',
            'â' => 'a',
            'ấ' => 'a',
            'ầ' => 'a',
            'ẩ' => 'a',
            'ẫ' => 'a',
            'ậ' => 'a',
            'é' => 'e',
            'è' => 'e',
            'ẻ' => 'e',
            'ẽ' => 'e',
            'ẹ' => 'e',
            'ê' => 'e',
            'ế' => 'e',
            'ề' => 'e',
            'ể' => 'e',
            'ễ' => 'e',
            'ệ' => 'e',
            'í' => 'i',
            'ì' => 'i',
            'ỉ' => 'i',
            'ĩ' => 'i',
            'ị' => 'i',
            'ó' => 'o',
            'ò' => 'o',
            'ỏ' => 'o',
            'õ' => 'o',
            'ọ' => 'o',
            'ô' => 'o',
            'ố' => 'o',
            'ồ' => 'o',
            'ổ' => 'o',
            'ỗ' => 'o',
            'ộ' => 'o',
            'ơ' => 'o',
            'ớ' => 'o',
            'ờ' => 'o',
            'ở' => 'o',
            'ỡ' => 'o',
            'ợ' => 'o',
            'ú' => 'u',
            'ù' => 'u',
            'ủ' => 'u',
            'ũ' => 'u',
            'ụ' => 'u',
            'ư' => 'u',
            'ứ' => 'u',
            'ừ' => 'u',
            'ử' => 'u',
            'ữ' => 'u',
            'ự' => 'u',
            'ý' => 'y',
            'ỳ' => 'y',
            'ỷ' => 'y',
            'ỹ' => 'y',
            'ỵ' => 'y',
            'đ' => 'd',
        ];

        return strtr($text, $map);
    }

    /**
     * Extract words from text (remove non-word characters)
     *
     * @param string $text
     * @param int $minLength Minimum word length (default: 2)
     * @return array
     */
    public function extractWords(string $text, int $minLength = 2): array
    {
        // Remove non-word characters (keep Vietnamese letters)
        $cleaned = preg_replace('/[^a-zàáảãạăằắẳẵặâầấẩẫậèéẻẽẹêềếểễệìíỉĩịòóỏõọôồốổỗộơờớởỡợùúủũụưừứửữựỳýỷỹỵđ0-9\s]/ui', '', $text);

        // Split by whitespace
        $words = preg_split('/\s+/u', trim($cleaned));

        // Filter by minimum length
        return array_filter($words, fn($word) => mb_strlen($word) >= $minLength);
    }

    /**
     * Check if string contains substring (case-insensitive)
     *
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public function contains(string $haystack, string $needle): bool
    {
        return mb_stripos($haystack, $needle) !== false;
    }

    /**
     * Check if text contains any of the keywords
     *
     * @param string $text
     * @param array $keywords
     * @return bool
     */
    public function containsAny(string $text, array $keywords): bool
    {
        $lowerText = $this->toLower($text);
        foreach ($keywords as $keyword) {
            if (!empty($keyword) && $this->contains($lowerText, $keyword)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if text contains all keywords
     *
     * @param string $text
     * @param array $keywords
     * @return bool
     */
    public function containsAll(string $text, array $keywords): bool
    {
        $lowerText = $this->toLower($text);
        foreach ($keywords as $keyword) {
            if (empty($keyword) || !$this->contains($lowerText, $keyword)) {
                return false;
            }
        }
        return true;
    }
}
