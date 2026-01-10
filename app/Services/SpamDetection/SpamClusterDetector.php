<?php

namespace App\Services\SpamDetection;

/**
 * Spam Cluster Detector - Bot Campaign Detection
 *
 * Detects coordinated spam attacks by finding groups of similar comments.
 * Unlike keyword matching (handled by YouTube), this detects PATTERNS across multiple comments.
 *
 * Example bot campaign:
 * - "Gw yang habis wd 5jt 🤑"
 * - "Gw yang habis wd 3jt 🤑"
 * - "Gw yang habis wd bilek 🤑"
 * → Pattern: "[PRONOUN] yang habis wd [AMOUNT] 🤑"
 *
 * Detection Logic:
 * 1. Similarity Clustering: Group comments by text similarity (Levenshtein)
 * 2. Template Extraction: Extract common pattern from cluster
 * 3. Campaign Scoring: Score based on cluster size, template specificity, spam signals
 */
class SpamClusterDetector
{
    private UnicodeDetector $unicodeDetector;

    private PatternAnalyzer $patternAnalyzer;

    private FuzzyMatcher $fuzzyMatcher;

    private ContextualAnalyzer $contextAnalyzer;

    /**
     * Minimum cluster size to be considered spam campaign.
     */
    private const MIN_CLUSTER_SIZE = 2;

    /**
     * Maximum similarity distance for clustering (lower = more strict).
     *
     * Lowered from 0.3 to 0.6 (40% similarity) to catch spam campaigns where
     * only brand names match but surrounding text varies:
     * - "Kunjungi M0NA4D sekarang" vs "Main di MONA4D yuk" = 42% similar
     * - Both are spam for the same brand, should cluster together
     *
     * Note: With fuzzy matching normalization (M0NA4D → monaad), we can use
     * lower threshold without risking false positives
     */
    private const MAX_SIMILARITY_DISTANCE = 0.6; // 40% similarity required

    /**
     * Spam campaign threshold score (0-100).
     * Lowered from 70 to 50 to catch organized gambling spam campaigns that:
     * - Use multiple accounts (high author diversity to look legit)
     * - Avoid Unicode fonts (to evade simple detection)
     * - Post repetitive promotional content across videos
     */
    private const SPAM_CAMPAIGN_THRESHOLD = 50;

    public function __construct()
    {
        $this->unicodeDetector = new UnicodeDetector;
        $this->patternAnalyzer = new PatternAnalyzer;
        $this->fuzzyMatcher = new FuzzyMatcher;
        $this->contextAnalyzer = new ContextualAnalyzer;
    }

    /**
     * Analyze a batch of comments for spam campaigns.
     *
     * @param  array  $comments  Array of ['id' => string, 'text' => string, 'author' => string, ...]
     * @param  array  $channelContext  Channel metadata for TIER B context matching
     * @param  array  $videoContext  Video metadata for TIER B context matching
     * @return array{clusters: array, spam_campaigns: array, summary: array}
     */
    public function analyzeCommentBatch(array $comments, array $channelContext = [], array $videoContext = []): array
    {
        if (empty($comments)) {
            return [
                'clusters' => [],
                'spam_campaigns' => [],
                'summary' => ['total_comments' => 0, 'clusters_found' => 0, 'spam_campaigns' => 0],
            ];
        }

        // Step 1: Normalize and prepare comments
        $normalizedComments = $this->normalizeComments($comments);

        // Step 2: Find similar comment clusters using multi-pass approach
        // This uses N-gram + Levenshtein hybrid similarity and merges related clusters
        $clusters = $this->findClustersMultiPass($normalizedComments);

        // Step 3: Score each cluster for spam campaign likelihood (with TIER B context)
        $spamCampaigns = [];
        foreach ($clusters as $cluster) {
            $campaignScore = $this->scoreCluster($cluster, $channelContext, $videoContext);

            if ($campaignScore['score'] >= self::SPAM_CAMPAIGN_THRESHOLD) {
                $spamCampaigns[] = $campaignScore;
            }
        }

        // Step 4: Generate summary
        $summary = [
            'total_comments' => count($comments),
            'clusters_found' => count($clusters),
            'spam_campaigns' => count($spamCampaigns),
            'affected_comments' => array_sum(array_column($spamCampaigns, 'member_count')),
        ];

        return [
            'clusters' => $clusters,
            'spam_campaigns' => $spamCampaigns,
            'summary' => $summary,
        ];
    }

    /**
     * Normalize comments for comparison (Unicode, lowercase, trim).
     *
     * @param  array  $comments  Raw comments
     * @return array Normalized comments with original data preserved
     */
    private function normalizeComments(array $comments): array
    {
        $normalized = [];

        foreach ($comments as $comment) {
            $text = $comment['text'] ?? '';
            $commentId = $comment['id'] ?? null;

            // Step 1: Normalize Unicode fancy fonts to ASCII
            $normalizedText = $this->unicodeDetector->normalize($text);

            // Step 2: Normalize each word separately for better clustering
            // This catches variations like: M0NA4D → MONAAD while preserving sentence structure
            $words = preg_split('/\s+/u', $normalizedText, -1, PREG_SPLIT_NO_EMPTY);
            $normalizedWords = array_map(function ($word) {
                // Remove punctuation from word
                $cleaned = preg_replace('/[^\p{L}\p{N}]/u', '', $word);

                // Apply fuzzy normalization (leet speak, etc)
                return $this->fuzzyMatcher->normalize($cleaned);
            }, $words);
            $normalizedText = implode(' ', array_filter($normalizedWords));

            // Step 3: Final cleanup
            $normalizedText = mb_strtolower(trim($normalizedText), 'UTF-8');

            $normalized[] = [
                'id' => $commentId,
                'original_text' => $text,
                'normalized_text' => $normalizedText,
                'author' => $comment['author'] ?? 'Unknown',
                'metadata' => $comment, // Preserve all original data
            ];
        }

        return $normalized;
    }

    /**
     * Find clusters of similar comments using similarity threshold.
     *
     * @param  array  $normalizedComments  Normalized comment data
     * @return array Array of clusters, each containing similar comments
     */
    private function findSimilarClusters(array $normalizedComments): array
    {
        $clusters = [];
        $processed = [];

        foreach ($normalizedComments as $i => $comment1) {
            // Skip if already in a cluster
            if (isset($processed[$i])) {
                continue;
            }

            $cluster = [
                'members' => [$comment1],
                'indices' => [$i],
            ];

            // Find similar comments
            foreach ($normalizedComments as $j => $comment2) {
                if ($i === $j || isset($processed[$j])) {
                    continue;
                }

                $similarity = $this->calculateSimilarity(
                    $comment1['normalized_text'],
                    $comment2['normalized_text']
                );

                if ($similarity >= (1 - self::MAX_SIMILARITY_DISTANCE)) {
                    $cluster['members'][] = $comment2;
                    $cluster['indices'][] = $j;
                    $processed[$j] = true;
                }
            }

            // Only keep clusters with 2+ members
            if (count($cluster['members']) >= self::MIN_CLUSTER_SIZE) {
                $processed[$i] = true;
                $clusters[] = $cluster;
            }
        }

        return $clusters;
    }

    /**
     * Find clusters using multi-pass approach for better grouping.
     *
     * PASS 1 (Moderate): Find initial clusters with moderate similarity (40% threshold)
     * PASS 2 (Merge): Combine related clusters using hybrid similarity (30% threshold)
     *
     * This catches spam campaigns that have variations:
     * - Core cluster: ["Kunjungi M0NA4D!", "Main di M0NA4D!"] (very similar)
     * - Related cluster: ["MON4D gacor!", "M0N4D trusted!"] (similar brand, different text)
     * - MERGE: All 4 comments become one large campaign
     *
     * @param  array  $normalizedComments  Normalized comment data
     * @return array Array of merged clusters
     */
    private function findClustersMultiPass(array $normalizedComments): array
    {
        // PASS 1: Find initial clusters (moderate threshold)
        $strictClusters = $this->findSimilarClustersWithThreshold(
            $normalizedComments,
            threshold: 0.4 // 40% similarity required (same as TIER S)
        );

        // If we only found 0-1 clusters, no need to merge
        if (count($strictClusters) <= 1) {
            return $strictClusters;
        }

        // PASS 2: Merge related clusters
        $mergedClusters = [];
        $processedClusters = [];

        foreach ($strictClusters as $i => $cluster1) {
            // Skip if already merged
            if (isset($processedClusters[$i])) {
                continue;
            }

            // Try to find related clusters to merge
            $mergedCluster = $cluster1;
            $processedClusters[$i] = true;

            foreach ($strictClusters as $j => $cluster2) {
                // Skip same cluster or already processed
                if ($i === $j || isset($processedClusters[$j])) {
                    continue;
                }

                // Check if clusters are related by comparing representatives
                if ($this->areClustersRelated($mergedCluster, $cluster2)) {
                    // Merge cluster2 into mergedCluster
                    $mergedCluster['members'] = array_merge(
                        $mergedCluster['members'],
                        $cluster2['members']
                    );
                    $mergedCluster['indices'] = array_merge(
                        $mergedCluster['indices'],
                        $cluster2['indices']
                    );
                    $processedClusters[$j] = true;
                }
            }

            $mergedClusters[] = $mergedCluster;
        }

        return $mergedClusters;
    }

    /**
     * Find clusters with custom similarity threshold.
     *
     * @param  array  $normalizedComments  Normalized comment data
     * @param  float  $threshold  Similarity threshold (0.0 to 1.0)
     * @return array Array of clusters
     */
    private function findSimilarClustersWithThreshold(array $normalizedComments, float $threshold): array
    {
        $clusters = [];
        $processed = [];

        foreach ($normalizedComments as $i => $comment1) {
            if (isset($processed[$i])) {
                continue;
            }

            $cluster = [
                'members' => [$comment1],
                'indices' => [$i],
            ];

            foreach ($normalizedComments as $j => $comment2) {
                if ($i === $j || isset($processed[$j])) {
                    continue;
                }

                // Use hybrid similarity (Levenshtein + N-gram)
                $similarity = $this->calculateHybridSimilarity(
                    $comment1['normalized_text'],
                    $comment2['normalized_text']
                );

                if ($similarity >= $threshold) {
                    $cluster['members'][] = $comment2;
                    $cluster['indices'][] = $j;
                    $processed[$j] = true;
                }
            }

            if (count($cluster['members']) >= self::MIN_CLUSTER_SIZE) {
                $processed[$i] = true;
                $clusters[] = $cluster;
            }
        }

        return $clusters;
    }

    /**
     * Check if two clusters are related and should be merged.
     *
     * Clusters are related if their representative comments share structural patterns
     * or common brand names (detected via hybrid similarity).
     *
     * @param  array  $cluster1  First cluster
     * @param  array  $cluster2  Second cluster
     * @return bool True if clusters should be merged
     */
    private function areClustersRelated(array $cluster1, array $cluster2): bool
    {
        // Get representatives (first comment of each cluster)
        $rep1 = $cluster1['members'][0]['normalized_text'];
        $rep2 = $cluster2['members'][0]['normalized_text'];

        // Use hybrid similarity with looser threshold for merging
        $similarity = $this->calculateHybridSimilarity($rep1, $rep2);

        // Merge if similarity >= 30% (looser than initial clustering)
        // This catches variations like "M0NA4D" vs "MON4D" across clusters
        // N-gram component helps detect structural patterns even with character differences
        return $similarity >= 0.30;
    }

    /**
     * Calculate similarity between two texts (0.0 = completely different, 1.0 = identical).
     *
     * Uses normalized Levenshtein distance for strings <= 255 chars.
     * Uses similar_text for longer strings.
     *
     * @param  string  $text1  First text
     * @param  string  $text2  Second text
     * @return float Similarity score (0.0 to 1.0)
     */
    private function calculateSimilarity(string $text1, string $text2): float
    {
        if ($text1 === $text2) {
            return 1.0;
        }

        $len1 = mb_strlen($text1, 'UTF-8');
        $len2 = mb_strlen($text2, 'UTF-8');
        $maxLen = max($len1, $len2);

        if ($maxLen === 0) {
            return 0.0;
        }

        // Use levenshtein for short strings (<= 255 chars)
        if ($len1 <= 255 && $len2 <= 255) {
            $distance = levenshtein($text1, $text2);

            // Handle levenshtein failure
            if ($distance === -1) {
                return $this->calculateSimilarityFallback($text1, $text2);
            }

            return 1 - ($distance / $maxLen);
        }

        // For long strings, use alternative algorithm
        return $this->calculateSimilarityFallback($text1, $text2);
    }

    /**
     * Fallback similarity calculation for long strings.
     *
     * Uses similar_text for strings > 255 chars.
     *
     * @param  string  $text1  First text
     * @param  string  $text2  Second text
     * @return float Similarity score (0.0 to 1.0)
     */
    private function calculateSimilarityFallback(string $text1, string $text2): float
    {
        if ($text1 === $text2) {
            return 1.0;
        }

        // Get similarity percentage directly
        similar_text($text1, $text2, $percent);

        return $percent / 100;
    }

    /**
     * Extract character n-grams from text.
     *
     * N-grams are consecutive character sequences used for structural pattern matching.
     * Example: "MONAD" with n=3 → ["MON", "ONA", "NAD"]
     *
     * @param  string  $text  Text to extract n-grams from
     * @param  int  $n  N-gram size (default: 3 for trigrams)
     * @return array Array of n-grams
     */
    private function extractNgrams(string $text, int $n = 3): array
    {
        $length = mb_strlen($text, 'UTF-8');
        if ($length < $n) {
            return [$text]; // Text too short for n-grams
        }

        $ngrams = [];
        for ($i = 0; $i <= $length - $n; $i++) {
            $ngrams[] = mb_substr($text, $i, $n, 'UTF-8');
        }

        return $ngrams;
    }

    /**
     * Calculate N-gram based similarity (Jaccard Index).
     *
     * Uses character trigrams to detect structural patterns even when characters differ.
     * Example:
     * - "M0NA4D" → [M0N, 0NA, NA4, A4D]
     * - "MONA4D" → [MON, ONA, NA4, A4D]
     * - Intersection: {NA4, A4D} = 2
     * - Union: {M0N, MON, 0NA, ONA, NA4, A4D} = 6
     * - Similarity: 2/6 = 33%
     *
     * @param  string  $text1  First text
     * @param  string  $text2  Second text
     * @param  int  $n  N-gram size (default: 3)
     * @return float Jaccard similarity (0.0 to 1.0)
     */
    private function calculateNgramSimilarity(string $text1, string $text2, int $n = 3): float
    {
        if ($text1 === $text2) {
            return 1.0;
        }

        $ngrams1 = $this->extractNgrams($text1, $n);
        $ngrams2 = $this->extractNgrams($text2, $n);

        if (empty($ngrams1) && empty($ngrams2)) {
            return 1.0; // Both empty = identical
        }

        if (empty($ngrams1) || empty($ngrams2)) {
            return 0.0; // One empty = completely different
        }

        // Calculate Jaccard Index: |intersection| / |union|
        $intersection = count(array_intersect($ngrams1, $ngrams2));
        $union = count(array_unique(array_merge($ngrams1, $ngrams2)));

        return $union > 0 ? $intersection / $union : 0.0;
    }

    /**
     * Calculate hybrid similarity combining Levenshtein and N-gram approaches.
     *
     * Uses Levenshtein as primary + N-gram as bonus boost:
     * - Levenshtein (base): Character-level edit distance (primary matcher)
     * - N-gram (bonus): Adds up to +15% if structural patterns match
     *
     * This approach:
     * - Preserves high Levenshtein scores (doesn't pull them down)
     * - Boosts scores when N-gram detects additional structural similarity
     * - Example: Lev 42% + Ngram 30% bonus = 42% + (30% * 0.15) = 46.5%
     *
     * @param  string  $text1  First text
     * @param  string  $text2  Second text
     * @return float Combined similarity score (0.0 to 1.0)
     */
    private function calculateHybridSimilarity(string $text1, string $text2): float
    {
        // Get both similarity scores
        $levenshteinSim = $this->calculateSimilarity($text1, $text2);
        $ngramSim = $this->calculateNgramSimilarity($text1, $text2);

        // Use Levenshtein as base, N-gram adds bonus (up to +15%)
        // This prevents N-gram from pulling down good Levenshtein scores
        $hybrid = $levenshteinSim + ($ngramSim * 0.15);

        // Cap at 100%
        return min($hybrid, 1.0);
    }

    /**
     * Extract common template pattern from cluster members.
     *
     * Example:
     * - "Gw yang habis wd 5jt 🤑"
     * - "Gw yang habis wd 3jt 🤑"
     * → Template: "Gw yang habis wd [N]jt 🤑"
     *
     * @param  array  $members  Cluster members
     * @return string Extracted template pattern
     */
    private function extractTemplate(array $members): string
    {
        if (empty($members)) {
            return '';
        }

        // Use first comment as base template
        $baseText = $members[0]['normalized_text'];

        // Find common parts by comparing with other members
        $template = $baseText;

        // Replace numbers with [N]
        $template = preg_replace('/\d+/', '[N]', $template);

        // Replace common variable parts with placeholders
        $template = preg_replace('/\b(bilek|cuan|profit|untung)\b/i', '[AMOUNT]', $template);

        // Collapse multiple spaces
        $template = preg_replace('/\s+/', ' ', $template);

        return trim($template);
    }

    /**
     * Score a cluster for spam campaign likelihood.
     *
     * Scoring factors:
     * - Cluster size (more members = higher score)
     * - Template specificity (more specific pattern = higher score)
     * - Spam signals (money mentions, urgency, emojis, etc.)
     * - Author diversity (same author = bot, different authors = coordinated)
     * - TIER B: Video context matching (reduces false positives)
     *
     * @param  array  $cluster  Cluster data with members
     * @param  array  $channelContext  Channel metadata for context matching
     * @param  array  $videoContext  Video metadata for context matching
     * @return array Campaign score with details
     */
    private function scoreCluster(array $cluster, array $channelContext = [], array $videoContext = []): array
    {
        $members = $cluster['members'];
        $memberCount = count($members);

        $score = 0;
        $signals = [];

        // Factor 1: Cluster size (25-60 points, progressive)
        // Larger campaigns get significantly higher penalties
        $sizeScore = match (true) {
            $memberCount >= 15 => 60,  // Massive campaign (viral spam)
            $memberCount >= 10 => 50,  // Large campaign (organized)
            $memberCount >= 5 => 40,   // Medium campaign (bot network)
            $memberCount >= 3 => 30,   // Small campaign (multi-account)
            default => 25,             // Minimal cluster (2 comments)
        };
        $score += $sizeScore;
        $signals[] = "Cluster size: {$memberCount} comments (+{$sizeScore})";

        // Factor 2: Template extraction
        $template = $this->extractTemplate($members);
        $templateSpecificity = $this->calculateTemplateSpecificity($template);
        $score += $templateSpecificity;
        $signals[] = "Template specificity: {$templateSpecificity}/30";

        // Factor 3: Spam pattern signals from first member (representative)
        $representativeText = $members[0]['normalized_text'];
        $patternScore = $this->analyzeSpamPatterns($representativeText);
        $score += $patternScore['score'];
        $signals = array_merge($signals, $patternScore['signals']);

        // Factor 4: Author diversity (both extremes are suspicious)
        $authorDiversity = $this->calculateAuthorDiversity($members);
        if ($authorDiversity < 0.3) {
            // Single bot posting repeatedly
            $score += 25;
            $signals[] = 'Very low author diversity (single bot) (+25)';
        } elseif ($authorDiversity > 0.8 && $memberCount >= 8) {
            // Organized campaign with multiple accounts to look legitimate
            $score += 20;
            $signals[] = 'High author diversity in large cluster (coordinated attack) (+20)';
        }

        // Factor 5: Unicode spam indicator
        $hasUnicode = false;
        foreach ($members as $member) {
            if ($this->unicodeDetector->hasFancyUnicode($member['original_text'])) {
                $hasUnicode = true;
                break;
            }
        }
        if ($hasUnicode) {
            $score += 15;
            $signals[] = 'Unicode fancy fonts detected (+15)';
        }

        // Factor 6: TIER B Video Context Matching (CRITICAL for reducing false positives)
        // Check if cluster discusses video-relevant topic instead of spam
        // Legitimate cluster types: questions, educational comments, video-relevant discussions
        if (! empty($channelContext) || ! empty($videoContext)) {
            // Merge channel + video context
            $fullContext = $this->mergeContexts($channelContext, $videoContext);

            // Analyze cluster template with video context
            $lowerTemplate = mb_strtolower($template);
            $contextAnalysis = $this->contextAnalyzer->analyzeWithVideoContext($lowerTemplate, $score, $fullContext);

            // If cluster is legitimate (video relevant, educational, question, behavioral patterns)
            // reduce score significantly - these are NOT spam campaigns
            if ($contextAnalysis['is_legitimate']) {
                // Score reduction varies by context type
                // TIER C (behavioral) gets HIGHEST reduction - structure-based, not keywords
                $scoreReduction = match ($contextAnalysis['context']) {
                    // TIER C: Behavioral patterns (STRONGEST signals - no keywords)
                    'short_genuine' => 60,      // 1-5 words, no links → genuine reaction
                    'simple_praise' => 55,      // Short + exclamation → enthusiastic user

                    // TIER B: Video context matching
                    'video_relevant' => 40,     // Comment matches video topic

                    // TIER A: Contextual patterns
                    'educational', 'question', 'correction' => 30, // Legitimate engagement

                    default => 0,
                };

                if ($scoreReduction > 0) {
                    $score = max(0, $score - $scoreReduction);
                    $videoRelevance = $contextAnalysis['video_relevance'] ?? 0;

                    // Determine tier based on context type
                    $tier = match ($contextAnalysis['context']) {
                        'short_genuine', 'simple_praise' => 'TIER C (Behavioral)',
                        'video_relevant' => 'TIER B (Video Context)',
                        default => 'TIER A (Contextual)',
                    };

                    $signals[] = sprintf(
                        '%s: %s (-%d, relevance: %.0f%%)',
                        $tier,
                        ucfirst(str_replace('_', ' ', $contextAnalysis['context'])),
                        $scoreReduction,
                        $videoRelevance * 100
                    );
                }
            }
        }

        // Get comment IDs
        $commentIds = array_column($members, 'id');

        return [
            'score' => min($score, 100),
            'is_spam_campaign' => $score >= self::SPAM_CAMPAIGN_THRESHOLD,
            'member_count' => $memberCount,
            'template' => $template,
            'signals' => $signals,
            'comment_ids' => $commentIds,
            'authors' => array_unique(array_column($members, 'author')),
            'author_diversity' => $authorDiversity,
            'sample_text' => $members[0]['original_text'],
        ];
    }

    /**
     * Calculate template specificity score (0-30 points).
     *
     * More placeholders = less specific = lower score.
     *
     * @param  string  $template  Template string
     * @return int Specificity score
     */
    private function calculateTemplateSpecificity(string $template): int
    {
        // Count placeholders
        $placeholderCount = substr_count($template, '[N]') + substr_count($template, '[AMOUNT]');

        // Count total words
        $wordCount = str_word_count($template);

        if ($wordCount === 0) {

            return 0;
        }

        // High specificity = few placeholders relative to word count
        $specificity = max(0, 30 - ($placeholderCount * 5));

        return (int) $specificity;
    }

    /**
     * Analyze spam patterns in text (money, urgency, links, etc.).
     *
     * @param  string  $text  Normalized text
     * @return array{score: int, signals: array}
     */
    private function analyzeSpamPatterns(string $text): array
    {
        $score = 0;
        $signals = [];

        $patterns = $this->patternAnalyzer->analyzePatterns($text);

        if ($patterns['has_money']) {
            $score += 10;
            $signals[] = 'Money mentions (+10)';
        }

        if ($patterns['has_urgency']) {
            $score += 10;
            $signals[] = 'Urgency language (+10)';
        }

        if ($patterns['has_link_promotion']) {
            $score += 15;
            $signals[] = 'Link promotion (+15)';
        }

        if ($patterns['emoji_density'] > 0.15) {
            $score += 5;
            $signals[] = 'High emoji density (+5)';
        }

        if ($patterns['caps_ratio'] > 0.5) {
            $score += 5;
            $signals[] = 'Excessive caps (+5)';
        }

        return [
            'score' => $score,
            'signals' => $signals,
        ];
    }

    /**
     * Calculate author diversity (0.0 = all same author, 1.0 = all different).
     *
     * @param  array  $members  Cluster members
     * @return float Diversity score
     */
    private function calculateAuthorDiversity(array $members): float
    {
        $authors = array_column($members, 'author');
        $uniqueAuthors = count(array_unique($authors));
        $totalMembers = count($members);

        if ($totalMembers === 0) {
            return 0.0;
        }

        return $uniqueAuthors / $totalMembers;
    }

    /**
     * Quick check if a batch contains potential spam campaigns.
     *
     * @param  array  $comments  Comments to check
     * @return bool True if spam campaign detected
     */
    public function hasSpamCampaign(array $comments): bool
    {
        $result = $this->analyzeCommentBatch($comments);

        return ! empty($result['spam_campaigns']);
    }

    /**
     * Get detailed report of spam campaigns found.
     *
     * @param  array  $comments  Comments to analyze
     * @return array Human-readable report
     */
    public function generateReport(array $comments): array
    {
        $result = $this->analyzeCommentBatch($comments);

        $report = [
            'summary' => $result['summary'],
            'campaigns' => [],
        ];

        foreach ($result['spam_campaigns'] as $campaign) {
            $report['campaigns'][] = [
                'severity' => $this->getSeverityLevel($campaign['score']),
                'confidence' => $campaign['score'].'%',
                'pattern' => $campaign['template'],
                'comment_count' => $campaign['member_count'],
                'authors' => $campaign['authors'],
                'sample' => $campaign['sample_text'],
                'reasons' => $campaign['signals'],
            ];
        }

        return $report;
    }

    /**
     * Get severity level based on score.
     *
     * @param  int  $score  Campaign score
     * @return string Severity level
     */
    private function getSeverityLevel(int $score): string
    {
        if ($score >= 90) {
            return 'CRITICAL';
        }
        if ($score >= 80) {
            return 'HIGH';
        }
        if ($score >= 70) {
            return 'MEDIUM';
        }

        return 'LOW';
    }

    /**
     * Merge channel and video contexts for maximum accuracy.
     *
     * Combines metadata from both channel and video to create
     * comprehensive context for spam detection.
     *
     * @param  array  $channelContext  Channel metadata
     * @param  array  $videoContext  Video metadata
     * @return array Merged context
     */
    private function mergeContexts(array $channelContext, array $videoContext): array
    {
        $merged = [];

        // Add channel info
        if (! empty($channelContext)) {
            $merged['channel_name'] = $channelContext['name'] ?? '';
            $merged['channel_description'] = $channelContext['description'] ?? '';
            $merged['channel_category'] = $channelContext['category'] ?? '';
            $merged['channel_tags'] = $channelContext['tags'] ?? [];
        }

        // Add video info (takes priority in title/description)
        if (! empty($videoContext)) {
            $merged['title'] = $videoContext['title'] ?? '';
            $merged['description'] = $videoContext['description'] ?? '';
            $merged['category'] = $videoContext['category'] ?? $merged['channel_category'] ?? '';
            $merged['tags'] = array_merge(
                $videoContext['tags'] ?? [],
                $merged['channel_tags'] ?? []
            );
        }

        return $merged;
    }
}
