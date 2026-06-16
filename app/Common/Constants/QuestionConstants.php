<?php

namespace App\Common\Constants;

/**
 * Question Type Constants
 *
 * Replaces magic strings: 'reduction_query', 'evidence_requirement', etc.
 */
class QuestionType
{
    // Question types
    public const REDUCTION_QUERY = 'reduction_query';  // Hỏi về mức giảm
    public const EVIDENCE_REQUIREMENT = 'evidence_requirement';  // Hỏi cần file gì
    public const ELIGIBILITY_QUERY = 'eligibility_query';  // Hỏi ai được hưởng
    public const COMPARISON = 'comparison';  // So sánh 2 chính sách
    public const OTHER = 'other';  // Câu hỏi khác

    public static function all(): array
    {
        return [
            self::REDUCTION_QUERY,
            self::EVIDENCE_REQUIREMENT,
            self::ELIGIBILITY_QUERY,
            self::COMPARISON,
            self::OTHER,
        ];
    }

    public static function isValid(string $type): bool
    {
        return in_array($type, self::all());
    }
}

/**
 * Question Intent Constants
 */
class QuestionIntent
{
    public const ASK_REDUCTION_AMOUNT = 'ask_reduction_amount';
    public const ASK_EVIDENCE_REQUIREMENTS = 'ask_evidence_requirements';
    public const ASK_ELIGIBILITY = 'ask_eligibility';
    public const ASK_PROCESS = 'ask_process';
    public const COMPARE_POLICIES = 'compare_policies';
    public const GENERAL_QUERY = 'general_query';

    public static function all(): array
    {
        return [
            self::ASK_REDUCTION_AMOUNT,
            self::ASK_EVIDENCE_REQUIREMENTS,
            self::ASK_ELIGIBILITY,
            self::ASK_PROCESS,
            self::COMPARE_POLICIES,
            self::GENERAL_QUERY,
        ];
    }

    public static function isValid(string $intent): bool
    {
        return in_array($intent, self::all());
    }
}

/**
 * Entity Type Constants
 *
 * Replaces: 'ho_ngheo', 'con_thuong_binh', etc.
 */
class EntityType
{
    // Policy recipients
    public const HO_NGHEO = 'ho_ngheo';
    public const HO_CAN_NGHEO = 'ho_can_ngheo';
    public const CON_LIET_SI = 'con_liet_si';
    public const CON_THUONG_BINH = 'con_thuong_binh';
    public const DAB_TOC_THIEU_SO = 'dan_toc_thieu_so';
    public const HO_CHINH_SACH = 'ho_chinh_sach';
    // Thương binh cấp
    public const THUONG_BINH_LOAI_1 = 'thuong_binh_loai_1';
    public const THUONG_BINH_LOAI_2 = 'thuong_binh_loai_2';
    public const THUONG_BINH_LOAI_3 = 'thuong_binh_loai_3';
    public const THUONG_BINH_LOAI_4 = 'thuong_binh_loai_4';
    // Evidence documents
    public const CCCD = 'cccd';
    public const HO_KHAU = 'ho_khau';
    public const KHAI_SINH = 'khai_sinh';
    public const BANK_ACCOUNT = 'bank_account';
    public const HO_NGHEO_DOC = 'ho_ngheo_doc';
    public const THUONG_BINH_DOC = 'thuong_binh_doc';
    public const CERTIFICATION = 'certification';
    // Policy types
    public const POLICY_MGHP = 'policy_mghp';
    public const POLICY_TCXH = 'policy_tcxh';
}

/**
 * Regex Pattern Constants
 *
 * Replaces: '/Điều\s+\d+/u' scattered throughout code
 */
class RegexPattern
{
    public const CITATION_DIEU = '/Điều\s+\d+/u';
    public const CLAUSE_KOAN = '/Khoản\s+\d+/u';
    public const VIETNAMESE_TEXT = '/[a-zàáảãạăằắẳẵặâầấẩẫậèéẻẽẹêềếểễệìíỉĩịòóỏõọôồốổỗộơờớởỡợùúủũụưừứửữựỳýỷỹỵđ0-9\s]/ui';
}

/**
 * Cache and Storage Constants
 */
class StorageConstant
{
    public const AI_PATH = 'ai';
    public const QA_CACHE_FILE = 'ai/qa_pairs.json';
    public const LEARNED_QUESTIONS_FILE = 'ai/learned_questions.jsonl';
    public const ANALYTICS_FILE = 'ai/questions_analysis.json';
    public const KNOWLEDGE_BASE_FILE = 'ai/nghidinh81.txt';
    // Cache TTL (seconds)
    public const CACHE_TTL_SHORT = 3600;  // 1 hour
    public const CACHE_TTL_MEDIUM = 86400;  // 1 day
    public const CACHE_TTL_LONG = 2592000;  // 30 days
}

/**
 * Semantic Cache Constants
 */
class CacheConstant
{
    public const SIMILARITY_THRESHOLD = 0.95;  // 95% similarity for cache hit
    public const MIN_SIMILARITY = 0.5;  // Minimum accepted similarity
    public const MAX_CACHE_SIZE = 10000;  // Max cached items
}
