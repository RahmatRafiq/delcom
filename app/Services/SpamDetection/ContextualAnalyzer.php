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
        'mending', 'lebih baik', 'atau', // Comparison questions
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

        // BEHAVIORAL ANALYSIS (SCALABLE - not keyword-based)
        // Analyze comment structure/behavior instead of specific words
        $behavioral = $this->analyzeBehavioralSignals($text);

        // Calculate adjustments
        $scoreAdjustment = 0;
        $context = 'unknown';
        $isLegitimate = false;

        // TIER C: BEHAVIORAL DETECTION (highest priority - overrides keyword patterns)
        // Very short genuine comments (1-5 words, no links) are almost never spam
        // Examples: "Mantap!", "Keren bang", "Bismillah", "Terima kasih"
        if ($behavioral['is_short_genuine']) {
            $scoreAdjustment -= 50; // Strong reduction - short comments are rarely spam
            $context = 'short_genuine';
            $isLegitimate = true;
        }
        // Simple praise (short + exclamation, no links) = enthusiastic user, not bot
        elseif ($behavioral['is_simple_praise']) {
            $scoreAdjustment -= 45; // Strong reduction
            $context = 'simple_praise';
            $isLegitimate = true;
        }
        // Question structure = legitimate engagement
        elseif ($behavioral['has_question_structure'] && ! $hasPromotionalIndicators) {
            $scoreAdjustment -= 35;
            $context = 'question';
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
                // BEHAVIORAL SIGNALS (TIER C - structure-based, not keywords)
                'behavioral' => [
                    'word_count' => $behavioral['word_count'],
                    'is_short_genuine' => $behavioral['is_short_genuine'],
                    'is_simple_praise' => $behavioral['is_simple_praise'],
                    'has_question_structure' => $behavioral['has_question_structure'],
                    'legitimacy_score' => $behavioral['legitimacy_score'],
                    'signals' => $behavioral['signals'],
                ],
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

    /**
     * Analyze behavioral signals (SCALABLE - structure-based, NOT keyword-based).
     *
     * Detects spam by HOW comment behaves, not WHAT words it contains.
     * This is robust against keyword variations - spammers can change words,
     * but behavioral patterns remain consistent.
     *
     * Behavioral signals:
     * 1. Very short comments (1-5 words) → Usually genuine reactions ("Mantap!", "Keren bang")
     * 2. Question structure (has ?) → Legitimate engagement
     * 3. Single sentence + exclamation → Enthusiastic praise, not promotional
     * 4. No links + very short → Not trying to redirect traffic
     * 5. Natural punctuation usage → Human-written, not bot template
     *
     * @param  string  $text  Comment text (original, not lowercased)
     * @return array{is_short_genuine: bool, has_question_structure: bool, is_simple_praise: bool, word_count: int, sentence_count: int, has_links: bool, legitimacy_score: float, signals: array}
     */
    private function analyzeBehavioralSignals(string $text): array
    {
        $signals = [];

        // 1. Count words (exclude punctuation)
        $cleanText = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $words = preg_split('/\s+/', trim($cleanText), -1, PREG_SPLIT_NO_EMPTY);
        $wordCount = count($words);

        // 2. Count sentences (rough estimate by punctuation)
        $sentenceEnders = ['.', '!', '?', '…'];
        $sentenceCount = 1; // Start with 1
        foreach ($sentenceEnders as $ender) {
            $sentenceCount += substr_count($text, $ender);
        }
        $sentenceCount = max(1, $sentenceCount - 1); // Adjust for counting

        // 3. Check for links (http://, https://, www., .com, .id, etc.)
        $hasLinks = (bool) preg_match('/(?:https?:\/\/|www\.|\.com|\.id|\.net|\.org)/i', $text);

        // 4. Check structure
        $hasQuestionMark = str_contains($text, '?');
        $hasExclamation = str_contains($text, '!');

        // BEHAVIORAL SIGNAL 1: Very short comment (1-5 words)
        // Examples: "Mantap!", "Keren bang", "Bismillah", "Terima kasih"
        // These are GENUINE reactions, not spam (spam is usually longer with promotional text)
        $isVeryShort = $wordCount >= 1 && $wordCount <= 5;
        if ($isVeryShort) {
            $signals[] = 'Very short comment (1-5 words) - likely genuine reaction';
        }

        // BEHAVIORAL SIGNAL 2: Question structure
        // Questions = seeking information, legitimate engagement
        $hasQuestionStructure = $hasQuestionMark;
        if ($hasQuestionStructure) {
            $signals[] = 'Question structure detected - legitimate engagement';
        }

        // BEHAVIORAL SIGNAL 3: Simple praise (short + exclamation, no links)
        // Examples: "Mantap!", "Keren!", "Bagus banget!"
        // Spam would include links or longer promotional text
        $isSimplePraise = $isVeryShort && $hasExclamation && ! $hasLinks;
        if ($isSimplePraise) {
            $signals[] = 'Simple praise (short + exclamation, no links) - genuine enthusiasm';
        }

        // BEHAVIORAL SIGNAL 4: Short + no links = Not promotional
        // Spam needs links to redirect traffic
        $isShortNonPromotional = $isVeryShort && ! $hasLinks;
        if ($isShortNonPromotional) {
            $signals[] = 'Short comment without links - not promotional';
        }

        // Calculate legitimacy score based on behavioral signals
        $legitimacyScore = 0.0;

        if ($isVeryShort) {
            $legitimacyScore += 0.4; // 40% boost
        }

        if ($hasQuestionStructure) {
            $legitimacyScore += 0.3; // 30% boost
        }

        if ($isSimplePraise) {
            $legitimacyScore += 0.5; // 50% boost (strong signal)
        }

        if (! $hasLinks && $wordCount <= 10) {
            $legitimacyScore += 0.2; // 20% boost for short non-promotional
        }

        // Cap at 1.0 (100%)
        $legitimacyScore = min(1.0, $legitimacyScore);

        return [
            'is_short_genuine' => $isVeryShort && ! $hasLinks,
            'has_question_structure' => $hasQuestionStructure,
            'is_simple_praise' => $isSimplePraise,
            'word_count' => $wordCount,
            'sentence_count' => $sentenceCount,
            'has_links' => $hasLinks,
            'legitimacy_score' => $legitimacyScore,
            'signals' => $signals,
        ];
    }

    /**
     * Analyze text with video/channel context (TIER B: Video Context Matching).
     *
     * Reduces false positives by checking if comment keywords match video topic.
     *
     * Example:
     * - Video: "Honda Jazz Review 2024"
     * - Comment: "Jazz makin gacor nih!" (gacor = getting better)
     * - Without context: SPAM (keyword: gacor = gambling)
     * - With context: CLEAN (gacor relevant to car performance review)
     *
     * @param  string  $text  Comment text to analyze
     * @param  int  $currentScore  Current spam score
     * @param  array  $videoContext  Video metadata [title, description, tags, category]
     * @return array{adjusted_score: int, context: string, is_legitimate: bool, confidence_adjustment: float, signals: array, video_relevance: float}
     */
    public function analyzeWithVideoContext(string $text, int $currentScore, array $videoContext = []): array
    {
        // Run standard context analysis first
        $baseAnalysis = $this->analyzeContext($text, $currentScore);

        // If no video context provided, return base analysis
        if (empty($videoContext)) {
            return array_merge($baseAnalysis, ['video_relevance' => 0.0]);
        }

        // Extract video topics from metadata
        $videoTopics = $this->extractVideoTopics($videoContext);

        // Extract keywords from comment
        $commentKeywords = $this->extractCommentKeywords($text);

        // Calculate video relevance (0.0 to 1.0)
        $videoRelevance = $this->matchesVideoContext($commentKeywords, $videoTopics);

        // Apply video context adjustment
        $scoreAdjustment = 0;
        $context = $baseAnalysis['context'];
        $isLegitimate = $baseAnalysis['is_legitimate'];
        $signals = $baseAnalysis['signals'];

        // If comment is relevant to video topic
        // Using Comment Keyword Match Percentage (not Jaccard)
        // Example: "Honda Jazz mantap" (3 keywords) → 3/3 = 100% match with video about Honda Jazz
        if ($videoRelevance >= 0.5) {
            // ≥50% match = At least half of comment keywords relate to video
            $scoreAdjustment = -40; // Strong legitimate signal
            $context = 'video_relevant';
            $isLegitimate = true;
            $signals['video_context'] = sprintf(
                'Comment highly relevant to video topic (%.0f%% match)',
                $videoRelevance * 100
            );
        } elseif ($videoRelevance >= 0.15) {
            // ≥15% match = At least 1-2 keywords relate to video (catches comparison questions)
            // Example: "mending jazz atau baleno?" → jazz matches (1/5 = 20%)
            $scoreAdjustment = -30; // Moderate-strong legitimate signal
            $context = 'video_relevant';
            $isLegitimate = true; // Mark as legitimate if has video context
            $signals['video_context'] = sprintf(
                'Comment relevant to video topic (%.0f%% match)',
                $videoRelevance * 100
            );
        }

        // Apply additional adjustment
        $adjustedScore = max(0, min(100, $baseAnalysis['adjusted_score'] + $scoreAdjustment));

        // Update confidence adjustment
        $confidenceAdjustment = $baseAnalysis['confidence_adjustment'];
        if ($videoRelevance >= 0.5 && $adjustedScore < 60) {
            $confidenceAdjustment = -0.5; // Very high confidence it's legitimate
        }

        return [
            'adjusted_score' => $adjustedScore,
            'context' => $context,
            'is_legitimate' => $isLegitimate,
            'confidence_adjustment' => $confidenceAdjustment,
            'signals' => $signals,
            'video_relevance' => $videoRelevance,
        ];
    }

    /**
     * Extract topics from video metadata.
     *
     * Extracts keywords from:
     * - Video title
     * - Video description (first 200 chars)
     * - Video tags
     * - Category (if provided)
     *
     * @param  array  $videoContext  Video metadata
     * @return array<string> Extracted topics (normalized, lowercase)
     */
    private function extractVideoTopics(array $videoContext): array
    {
        $topics = [];

        // Extract from title
        if (isset($videoContext['title'])) {
            $titleWords = $this->extractKeywords($videoContext['title']);
            $topics = array_merge($topics, $titleWords);
        }

        // Extract from description (first 200 chars to avoid spam in description)
        if (isset($videoContext['description'])) {
            $descriptionSnippet = mb_substr($videoContext['description'], 0, 200);
            $descWords = $this->extractKeywords($descriptionSnippet);
            $topics = array_merge($topics, $descWords);
        }

        // Add tags directly
        if (isset($videoContext['tags']) && is_array($videoContext['tags'])) {
            $normalizedTags = array_map(function ($tag) {
                return mb_strtolower(trim($tag), 'UTF-8');
            }, $videoContext['tags']);
            $topics = array_merge($topics, $normalizedTags);
        }

        // Add category as topic
        if (isset($videoContext['category'])) {
            $topics[] = mb_strtolower(trim($videoContext['category']), 'UTF-8');
        }

        // Remove duplicates and common stop words
        $topics = array_unique($topics);
        $topics = $this->removeStopWords($topics);

        // Add contextual semantic expansion
        $topics = $this->expandContextualKeywords($topics, $videoContext);

        return array_values($topics);
    }

    /**
     * Extract meaningful keywords from comment text.
     *
     * @param  string  $text  Comment text
     * @return array<string> Extracted keywords (normalized, lowercase)
     */
    private function extractCommentKeywords(string $text): array
    {
        return $this->extractKeywords($text);
    }

    /**
     * Expand video topics with contextual semantic keywords.
     *
     * For car/vehicle videos, adds semantic keywords for:
     * - Performance: "gacor", "ngebut", "kencang", "cepat"
     * - Design: "ganteng", "cantik", "keren", "bagus"
     * - Quality: "mantap", "oke", "recommended", "worth it"
     *
     * @param  array  $topics  Base topics extracted from video
     * @param  array  $videoContext  Full video context
     * @return array<string> Expanded topics
     */
    private function expandContextualKeywords(array $topics, array $videoContext): array
    {
        $expanded = $topics;

        // Detect if video is about vehicles/cars
        $isVehicleVideo = $this->isVehicleRelated($topics, $videoContext);

        if ($isVehicleVideo) {
            // Add common Indonesian slang/terms for vehicle reviews
            $vehicleKeywords = [
                // Performance terms
                'gacor', 'ngebut', 'kencang', 'cepat', 'laju', 'akselerasi',
                // Design/appearance terms
                'ganteng', 'cantik', 'keren', 'elegan', 'sporty', 'mewah',
                // Quality/value terms
                'mantap', 'oke', 'recommended', 'worth', 'layak', 'terbaik',
                // General vehicle terms
                'performa', 'mesin', 'fitur', 'spesifikasi', 'harga', 'interior', 'eksterior',
            ];

            $expanded = array_merge($expanded, $vehicleKeywords);
        }

        return array_unique($expanded);
    }

    /**
     * Check if video/topics are related to vehicles.
     *
     * @param  array  $topics  Extracted topics
     * @param  array  $videoContext  Video context
     * @return bool True if vehicle-related
     */
    private function isVehicleRelated(array $topics, array $videoContext): bool
    {
        $vehicleIndicators = [
            'mobil', 'motor', 'car', 'motorcycle', 'bike', 'vehicle', 'automotive',
            'honda', 'toyota', 'yamaha', 'suzuki', 'kawasaki', 'review', 'test drive',
        ];

        // Check topics
        foreach ($topics as $topic) {
            if (in_array($topic, $vehicleIndicators, true)) {
                return true;
            }
        }

        // Check category
        if (isset($videoContext['category'])) {
            $category = mb_strtolower($videoContext['category'], 'UTF-8');
            if (str_contains($category, 'auto') || str_contains($category, 'vehicle')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract keywords from text (helper method).
     *
     * @param  string  $text  Text to extract from
     * @return array<string> Keywords
     */
    private function extractKeywords(string $text): array
    {
        $lowerText = mb_strtolower($text, 'UTF-8');

        // Remove punctuation and split into words
        $cleanText = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $lowerText);
        $words = preg_split('/\s+/u', $cleanText, -1, PREG_SPLIT_NO_EMPTY);

        // Filter: Only words >= 3 characters
        $keywords = array_filter($words, function ($word) {
            return mb_strlen($word, 'UTF-8') >= 3;
        });

        return array_values($keywords);
    }

    /**
     * Remove common Indonesian stop words.
     *
     * @param  array  $words  List of words
     * @return array Filtered words
     */
    private function removeStopWords(array $words): array
    {
        $stopWords = [
            'yang', 'dan', 'untuk', 'dari', 'dengan', 'ini', 'itu', 'adalah',
            'pada', 'atau', 'dalam', 'akan', 'oleh', 'juga', 'ada', 'tidak',
            'kan', 'nih', 'sih', 'deh', 'yah', 'dong', 'aja', 'gue', 'gua',
            'the', 'and', 'for', 'from', 'with', 'this', 'that', 'are',
        ];

        return array_filter($words, function ($word) use ($stopWords) {
            return ! in_array($word, $stopWords);
        });
    }

    /**
     * Calculate video context matching score.
     *
     * Uses Comment Keyword Match Percentage: What percentage of comment's
     * keywords match video topics? This is more meaningful than Jaccard
     * similarity for this use case.
     *
     * Example:
     * - Comment: "Honda Jazz mantap" → keywords: [honda, jazz, mantap]
     * - Video: 36 topics including [honda, jazz, mantap, ...]
     * - Match: 3/3 = 100% (all comment keywords match video!)
     *
     * @param  array  $commentKeywords  Keywords from comment
     * @param  array  $videoTopics  Topics from video
     * @return float Match score (0.0 to 1.0)
     */
    private function matchesVideoContext(array $commentKeywords, array $videoTopics): float
    {
        if (empty($commentKeywords) || empty($videoTopics)) {
            return 0.0;
        }

        // Calculate what % of comment keywords match video topics
        $intersection = array_intersect($commentKeywords, $videoTopics);
        $matchCount = count($intersection);
        $totalCommentKeywords = count($commentKeywords);

        if ($totalCommentKeywords === 0) {
            return 0.0;
        }

        return $matchCount / $totalCommentKeywords;
    }
}
