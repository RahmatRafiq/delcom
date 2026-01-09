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

    /**
     * Minimum cluster size to be considered spam campaign.
     */
    private const MIN_CLUSTER_SIZE = 2;

    /**
     * Maximum similarity distance for clustering (lower = more strict).
     */
    private const MAX_SIMILARITY_DISTANCE = 0.3; // 30% difference allowed

    /**
     * Spam campaign threshold score (0-100).
     */
    private const SPAM_CAMPAIGN_THRESHOLD = 70;

    public function __construct()
    {
        $this->unicodeDetector = new UnicodeDetector;
        $this->patternAnalyzer = new PatternAnalyzer;
    }

    /**
     * Analyze a batch of comments for spam campaigns.
     *
     * @param  array  $comments  Array of ['id' => string, 'text' => string, 'author' => string, ...]
     * @return array{clusters: array, spam_campaigns: array, summary: array}
     */
    public function analyzeCommentBatch(array $comments): array
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

        // Step 2: Find similar comment clusters
        $clusters = $this->findSimilarClusters($normalizedComments);

        // Step 3: Score each cluster for spam campaign likelihood
        $spamCampaigns = [];
        foreach ($clusters as $cluster) {
            $campaignScore = $this->scoreCluster($cluster);

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

            // Normalize Unicode fancy fonts to ASCII
            $normalizedText = $this->unicodeDetector->normalize($text);

            // Lowercase and trim
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
     * Calculate similarity between two texts (0.0 = completely different, 1.0 = identical).
     *
     * Uses normalized Levenshtein distance.
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

        $maxLen = max(mb_strlen($text1, 'UTF-8'), mb_strlen($text2, 'UTF-8'));
        if ($maxLen === 0) {
            return 0.0;
        }

        $distance = levenshtein($text1, $text2);

        return 1 - ($distance / $maxLen);
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
     *
     * @param  array  $cluster  Cluster data with members
     * @return array Campaign score with details
     */
    private function scoreCluster(array $cluster): array
    {
        $members = $cluster['members'];
        $memberCount = count($members);

        $score = 0;
        $signals = [];

        // Factor 1: Cluster size (20-40 points)
        $sizeScore = min(20 + ($memberCount * 5), 40);
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

        // Factor 4: Author diversity (low diversity = likely bot)
        $authorDiversity = $this->calculateAuthorDiversity($members);
        if ($authorDiversity < 0.5) {
            // Same author posting similar comments = very suspicious
            $score += 20;
            $signals[] = 'Low author diversity (likely bot) (+20)';
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
}
