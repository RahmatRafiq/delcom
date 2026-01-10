<?php

namespace App\Services;

use App\Services\SpamDetection\ContextualAnalyzer;
use App\Services\SpamDetection\PatternAnalyzer;
use App\Services\SpamDetection\SpamClusterDetector;
use App\Services\SpamDetection\UnicodeDetector;

/**
 * Hybrid Spam Detector - Focus on What YouTube CAN'T Detect
 *
 * YouTube already handles:
 * ❌ Regex keyword matching
 * ❌ Basic spam patterns
 *
 * We focus on:
 * ✅ Bot campaign detection (cluster analysis)
 * ✅ Unicode fancy font bypass detection
 * ✅ Contextual analysis (Indonesian context)
 * ✅ Implicit gambling patterns
 */
class HybridSpamDetector
{
    private ContextualAnalyzer $contextAnalyzer;

    private SpamClusterDetector $clusterDetector;

    private UnicodeDetector $unicodeDetector;

    public function __construct()
    {
        $this->contextAnalyzer = new ContextualAnalyzer;
        $this->clusterDetector = new SpamClusterDetector;
        $this->unicodeDetector = new UnicodeDetector;
    }

    /**
     * Analyze a batch of comments for spam campaigns (PRIMARY DETECTION METHOD).
     *
     * This is the main detection method that combines:
     * 1. Cluster detection (bot campaigns)
     * 2. Individual spam detection (Unicode, ALL CAPS, gambling)
     * 3. Contextual analysis with channel + video metadata (TIER B)
     *
     * @param  array  $comments  Array of ['id' => string, 'text' => string, 'author' => string, ...]
     * @param  array  $channelContext  Channel metadata ['name', 'description', 'category', 'tags']
     * @param  array  $videoContext  Video metadata ['title', 'description', 'tags', 'category']
     * @return array{clusters: array, spam_campaigns: array, summary: array}
     */
    public function analyzeCommentBatch(array $comments, array $channelContext = [], array $videoContext = []): array
    {
        // Run cluster detection with TIER B context (CRITICAL for reducing false positives)
        $clusterResult = $this->clusterDetector->analyzeCommentBatch($comments, $channelContext, $videoContext);

        // Run individual spam detection with context
        $individualSpam = $this->detectIndividualSpam($comments, $channelContext, $videoContext);

        // Merge results
        return $this->mergeDetectionResults($clusterResult, $individualSpam);
    }

    /**
     * Detect individual spam comments that don't form clusters.
     *
     * Catches:
     * - Fancy Unicode gambling spam
     * - ALL CAPS off-topic comments
     * - Single promotional spam
     *
     * Uses TIER B double context (channel + video) to reduce false positives.
     *
     * @param  array  $comments  Array of comments
     * @param  array  $channelContext  Channel metadata
     * @param  array  $videoContext  Video metadata
     * @return array Individual spam campaigns
     */
    private function detectIndividualSpam(array $comments, array $channelContext = [], array $videoContext = []): array
    {
        $individualSpam = [];

        foreach ($comments as $comment) {
            $text = $comment['text'] ?? '';
            $normalizedText = $this->unicodeDetector->normalize($text);
            $lowerText = mb_strtolower($normalizedText);

            // Calculate spam score
            $score = 0;
            $signals = [];

            // 1. Fancy Unicode detection (+95 points) - CRITICAL - NEVER LEGITIMATE
            // Gambling spammers use fancy Unicode (𝐏𝐒𝐓𝐎𝐓𝐎, 𝘽𝙀𝙍𝙆𝘼𝙃) to bypass filters
            // This is the STRONGEST spam indicator
            if ($this->unicodeDetector->hasFancyUnicode($text)) {
                $score += 95;
                $signals[] = 'Unicode fancy fonts detected (+95) - DEFINITE SPAM';
            }

            // 2. Pattern analysis (pass original normalized text for CAPS detection)
            $patterns = app(PatternAnalyzer::class)->analyzePatterns($normalizedText);

            if ($patterns['has_money']) {
                $score += 20;
                $signals[] = 'Money mentions detected (+20)';
            }

            if ($patterns['has_urgency']) {
                $score += 15;
                $signals[] = 'Urgency language detected (+15)';
            }

            if ($patterns['has_link_promotion']) {
                $score += 15;
                $signals[] = 'Link promotion detected (+15)';
            }

            // 3. ALL CAPS detection (+30 points for >90%, +10 for >50%)
            // Note: ALL CAPS is often LEGITIMATE (enthusiastic users, corrections)
            // Examples: "PLEASE REVIEW BRV", "HARGA NAIK!", user corrections
            // This is a WEAK spam signal - needs other signals to confirm
            if ($patterns['caps_ratio'] > 0.9) {
                $score += 30;
                $signals[] = sprintf('ALL CAPS detected (%.0f%%, +30) - needs review', $patterns['caps_ratio'] * 100);
            } elseif ($patterns['caps_ratio'] > 0.5) {
                $score += 10;
                $signals[] = sprintf('High CAPS ratio (%.0f%%, +10)', $patterns['caps_ratio'] * 100);
            }

            // 4. Context analysis (reduce false positives) - TIER B with DOUBLE CONTEXT
            // Note: Skip context analysis if strong spam signals present:
            // - Unicode fancy fonts (NEVER legitimate on YouTube)
            // - ALL CAPS >90% (strong spam signal)
            $hasFancyUnicode = $this->unicodeDetector->hasFancyUnicode($text);
            $skipContextAnalysis = $hasFancyUnicode || $patterns['caps_ratio'] > 0.9;

            if (! $skipContextAnalysis) {
                // Merge channel + video context for maximum accuracy
                $fullContext = $this->mergeContexts($channelContext, $videoContext);

                // Use TIER B video context matching if context available
                if (! empty($fullContext)) {
                    $contextAnalysis = $this->contextAnalyzer->analyzeWithVideoContext($lowerText, $score, $fullContext);
                } else {
                    // Fallback to generic context analysis
                    $contextAnalysis = $this->contextAnalyzer->analyzeContext($lowerText, $score);
                }

                if ($contextAnalysis['is_legitimate']) {
                    // Legitimate content reduces score significantly
                    $scoreReduction = $contextAnalysis['context'] === 'video_relevant' ? 40 : 30;
                    $score = max(0, $score - $scoreReduction);
                    $signals[] = sprintf('Legitimate context detected (%s, -%d)', $contextAnalysis['context'], $scoreReduction);
                } elseif ($contextAnalysis['context'] === 'promotional') {
                    // Promotional content increases score
                    $score += 10;
                    $signals[] = 'Promotional indicators (+10)';
                }

                // Add video relevance signal if available
                if (isset($contextAnalysis['video_relevance']) && $contextAnalysis['video_relevance'] > 0) {
                    $signals[] = sprintf('Video relevance: %.0f%%', $contextAnalysis['video_relevance'] * 100);
                }
            }

            // 5. Threshold: 50 points = spam
            // Unicode alone (95 points) = DEFINITE spam
            // ALL CAPS alone (30 points) = NOT spam (needs more signals)
            // Money (20) + Urgency (15) + ALL CAPS (30) = 65 points = spam
            if ($score >= 50) {
                $individualSpam[] = [
                    'comment_ids' => [$comment['id']],
                    'member_count' => 1,
                    'authors' => [$comment['author']],
                    'author_diversity' => 1.0,
                    'template' => $normalizedText,
                    'sample_text' => $text,
                    'score' => $score,
                    'signals' => $signals,
                    'detection_type' => 'individual',
                ];
            }
        }

        return $individualSpam;
    }

    /**
     * Merge results from cluster and individual detection.
     *
     * @param  array  $clusterResult  Result from cluster detector
     * @param  array  $individualSpam  Individual spam detected
     * @return array Merged results
     */
    private function mergeDetectionResults(array $clusterResult, array $individualSpam): array
    {
        // Get existing spam campaigns from cluster detection
        $allSpamCampaigns = $clusterResult['spam_campaigns'] ?? [];

        // Add individual spam to campaigns
        foreach ($individualSpam as $spam) {
            $allSpamCampaigns[] = $spam;
        }

        // Calculate affected comments
        $affectedCommentIds = [];
        foreach ($allSpamCampaigns as $campaign) {
            $affectedCommentIds = array_merge($affectedCommentIds, $campaign['comment_ids']);
        }
        $affectedCommentIds = array_unique($affectedCommentIds);

        // Update summary
        $summary = $clusterResult['summary'] ?? [
            'total_comments' => 0,
            'clusters_found' => 0,
            'spam_campaigns' => 0,
            'affected_comments' => 0,
        ];

        $summary['spam_campaigns'] = count($allSpamCampaigns);
        $summary['affected_comments'] = count($affectedCommentIds);

        return [
            'clusters' => $clusterResult['clusters'] ?? [],
            'spam_campaigns' => $allSpamCampaigns,
            'summary' => $summary,
        ];
    }

    /**
     * Generate detailed report of spam campaigns found.
     *
     * @param  array  $comments  Comments to analyze
     * @return array Human-readable report
     */
    public function generateReport(array $comments): array
    {
        return $this->clusterDetector->generateReport($comments);
    }

    /**
     * Quick check if a batch contains potential spam campaigns.
     *
     * @param  array  $comments  Comments to check
     * @return bool True if spam campaign detected
     */
    public function hasSpamCampaign(array $comments): bool
    {
        return $this->clusterDetector->hasSpamCampaign($comments);
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

    /**
     * Get statistics about detection capabilities.
     */
    public function getCapabilities(): array
    {
        return [
            'cluster_detection' => true, // PRIMARY: Bot campaign detection
            'unicode_normalization' => true, // Fancy font bypass detection
            'contextual_analysis' => true, // Indonesian context understanding
            'video_context_matching' => true, // TIER B: Video/channel context (NEW!)
            'pattern_analysis' => true, // Implicit gambling patterns
            'author_diversity' => true, // Bot vs. coordinated spam
            'template_extraction' => true, // Pattern identification
            'note' => 'Regex filtering handled by YouTube natively',
        ];
    }
}
