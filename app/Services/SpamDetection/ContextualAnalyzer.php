<?php

namespace App\Services\SpamDetection;

/**
 * Contextual Analyzer for Spam Detection
 *
 * Adds context-aware intelligence to differentiate spam from legitimate content.
 * Prevents false positives by considering:
 * - Content context (educational, review, news, etc.)
 * - Sentiment analysis (constructive vs promotional)
 * - Whitelisted patterns (legitimate use cases)
 *
 * Example use cases:
 * - "This video explains how slot machines work" → Educational (NOT spam)
 * - "WIN BIG at SLOT GACOR! Click link bio!" → Spam
 */
class ContextualAnalyzer
{
    /**
     * Educational/informational keywords that reduce spam score.
     *
     * @var array<string>
     */
    private const LEGITIMATE_CONTEXTS = [
        // Educational
        'penjelasan', 'dijelaskan', 'cara kerja', 'bagaimana', 'mengapa',
        'tutorial', 'panduan', 'belajar', 'edukasi', 'informasi',
        'artikel', 'berita', 'laporan', 'analisis', 'review',

        // Critical/warning
        'bahaya', 'hati-hati', 'waspada', 'jangan', 'hindari',
        'resiko', 'kerugian', 'dampak negatif', 'penipuan',

        // Discussion/question
        'menurut', 'pendapat', 'bagaimana menurut', 'ada yang tahu',
        'apakah benar', 'pengalaman', 'sharing', 'diskusi',

        // Academic/professional
        'penelitian', 'studi', 'data', 'statistik', 'fakta',
        'regulasi', 'hukum', 'legal', 'ilegal', 'undang-undang',
    ];

    /**
     * Question patterns that indicate legitimate engagement.
     *
     * @var array<string>
     */
    private const QUESTION_PATTERNS = [
        'apakah', 'bagaimana', 'mengapa', 'kapan', 'dimana',
        'siapa', 'berapa', 'apa', 'gimana', 'kenapa',
    ];

    /**
     * Promotional spam indicators (high weight).
     *
     * @var array<string>
     */
    private const PROMOTIONAL_INDICATORS = [
        // Call to action
        'klik', 'daftar', 'join', 'gabung', 'register',
        'claim', 'klaim', 'ambil', 'dapatkan sekarang',

        // Urgency
        'hari ini', 'sekarang juga', 'limited', 'terbatas',
        'buruan', 'cepat', 'jangan sampai',

        // Guarantees (spam tactic)
        'dijamin', 'pasti', 'auto', 'gampang banget',
        'mudah banget', 'terbukti 100%', 'tanpa modal',
    ];

    /**
     * Analyze text context to adjust spam scoring.
     *
     * @param  string  $text  Text to analyze
     * @param  int  $currentScore  Current spam score from FilterMatcher
     * @return array{adjusted_score: int, context: string, is_legitimate: bool, confidence_adjustment: float, signals: array}
     */
    public function analyzeContext(string $text, int $currentScore): array
    {
        $lowerText = mb_strtolower($text);

        // Detect context signals
        $hasEducationalContext = $this->hasEducationalContext($lowerText);
        $hasQuestionPattern = $this->hasQuestionPattern($lowerText);
        $hasWarningContext = $this->hasWarningContext($lowerText);
        $hasPromotionalIndicators = $this->hasPromotionalIndicators($lowerText);

        $sentiment = $this->analyzeSentiment($lowerText);

        // Calculate adjustments
        $scoreAdjustment = 0;
        $context = 'unknown';
        $isLegitimate = false;

        // Legitimate contexts reduce spam score
        if ($hasEducationalContext) {
            $scoreAdjustment -= 30;
            $context = 'educational';
            $isLegitimate = true;
        } elseif ($hasQuestionPattern && ! $hasPromotionalIndicators) {
            $scoreAdjustment -= 20;
            $context = 'question';
            $isLegitimate = true;
        } elseif ($hasWarningContext) {
            $scoreAdjustment -= 25;
            $context = 'warning';
            $isLegitimate = true;
        }

        // Promotional indicators increase spam score
        if ($hasPromotionalIndicators) {
            $scoreAdjustment += 15;
            $context = 'promotional';
            $isLegitimate = false;
        }

        // Sentiment adjustments
        if ($sentiment === 'constructive') {
            $scoreAdjustment -= 10;
        } elseif ($sentiment === 'promotional') {
            $scoreAdjustment += 10;
        }

        // Apply adjustments
        $adjustedScore = max(0, min(100, $currentScore + $scoreAdjustment));

        // If adjusted score drops below spam threshold (60), mark as legitimate
        if ($adjustedScore < 60 && $isLegitimate) {
            $confidenceAdjustment = -0.3; // Reduce confidence in spam classification
        } else {
            $confidenceAdjustment = 0;
        }

        return [
            'adjusted_score' => $adjustedScore,
            'context' => $context,
            'is_legitimate' => $isLegitimate,
            'confidence_adjustment' => $confidenceAdjustment,
            'signals' => [
                'educational' => $hasEducationalContext,
                'question' => $hasQuestionPattern,
                'warning' => $hasWarningContext,
                'promotional' => $hasPromotionalIndicators,
                'sentiment' => $sentiment,
                'score_adjustment' => $scoreAdjustment,
            ],
        ];
    }

    /**
     * Check if text has educational/informational context.
     *
     * @param  string  $text  Lowercased text
     * @return bool True if educational context detected
     */
    private function hasEducationalContext(string $text): bool
    {
        foreach (self::LEGITIMATE_CONTEXTS as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if text contains question patterns.
     *
     * @param  string  $text  Lowercased text
     * @return bool True if question pattern found
     */
    private function hasQuestionPattern(string $text): bool
    {
        // Check for question words
        foreach (self::QUESTION_PATTERNS as $pattern) {
            if (str_contains($text, $pattern)) {
                return true;
            }
        }

        // Check for question mark
        return str_contains($text, '?');
    }

    /**
     * Check if text has warning/cautionary context.
     *
     * @param  string  $text  Lowercased text
     * @return bool True if warning context detected
     */
    private function hasWarningContext(string $text): bool
    {
        $warningKeywords = [
            'bahaya', 'hati-hati', 'waspada', 'jangan',
            'hindari', 'resiko', 'kerugian', 'penipuan',
        ];

        foreach ($warningKeywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if text has promotional spam indicators.
     *
     * @param  string  $text  Lowercased text
     * @return bool True if promotional indicators found
     */
    private function hasPromotionalIndicators(string $text): bool
    {
        $count = 0;

        foreach (self::PROMOTIONAL_INDICATORS as $indicator) {
            if (str_contains($text, $indicator)) {
                $count++;
            }
        }

        // 2+ promotional indicators = spam
        return $count >= 2;
    }

    /**
     * Analyze sentiment of the text.
     *
     * @param  string  $text  Lowercased text
     * @return string Sentiment: 'constructive', 'neutral', 'promotional'
     */
    private function analyzeSentiment(string $text): string
    {
        $constructiveWords = [
            'terima kasih', 'bagus', 'menarik', 'informatif',
            'bermanfaat', 'membantu', 'jelas', 'paham',
            'setuju', 'benar', 'baik', 'suka', 'good',
        ];

        $promotionalWords = [
            'menang', 'untung', 'profit', 'mudah',
            'cepat', 'gratis', 'bonus', 'promo',
        ];

        $constructiveCount = 0;
        $promotionalCount = 0;

        foreach ($constructiveWords as $word) {
            if (str_contains($text, $word)) {
                $constructiveCount++;
            }
        }

        foreach ($promotionalWords as $word) {
            if (str_contains($text, $word)) {
                $promotionalCount++;
            }
        }

        if ($constructiveCount > $promotionalCount) {
            return 'constructive';
        } elseif ($promotionalCount > $constructiveCount) {
            return 'promotional';
        }

        return 'neutral';
    }

    /**
     * Check if text should be whitelisted (override spam detection).
     *
     * @param  string  $text  Text to check
     * @return bool True if should be whitelisted
     */
    public function shouldWhitelist(string $text): bool
    {
        $lowerText = mb_strtolower($text);

        // Whitelist if:
        // 1. Educational context + No promotional indicators
        // 2. Warning/cautionary content
        // 3. Pure questions (< 50 chars, has ?)

        $hasEducational = $this->hasEducationalContext($lowerText);
        $hasWarning = $this->hasWarningContext($lowerText);
        $hasPromo = $this->hasPromotionalIndicators($lowerText);
        $isShortQuestion = mb_strlen($text) < 50 && str_contains($text, '?');

        if ($hasEducational && ! $hasPromo) {
            return true;
        }

        if ($hasWarning) {
            return true;
        }

        if ($isShortQuestion) {
            return true;
        }

        return false;
    }

    /**
     * Get whitelisted domains/sources (future expansion).
     *
     * @return array<string> List of trusted domains
     */
    public function getWhitelistedDomains(): array
    {
        return [
            // News/media
            'kompas.com', 'detik.com', 'tribunnews.com',
            'liputan6.com', 'cnnindonesia.com',

            // Government/official
            'go.id', 'polri.go.id', 'ojk.go.id',

            // Legitimate review sites (non-promotional)
            'wikipedia.org', 'wikihow.com',
        ];
    }
}
