<?php

namespace App\Services;

use App\Services\SpamDetection\ContextualAnalyzer;
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
     * This is the main detection method that focuses on bot campaign detection,
     * which YouTube's native filtering cannot detect.
     *
     * @param  array  $comments  Array of ['id' => string, 'text' => string, 'author' => string, ...]
     * @return array{clusters: array, spam_campaigns: array, summary: array}
     */
    public function analyzeCommentBatch(array $comments): array
    {
        return $this->clusterDetector->analyzeCommentBatch($comments);
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
     * Get statistics about detection capabilities.
     */
    public function getCapabilities(): array
    {
        return [
            'cluster_detection' => true, // PRIMARY: Bot campaign detection
            'unicode_normalization' => true, // Fancy font bypass detection
            'contextual_analysis' => true, // Indonesian context understanding
            'pattern_analysis' => true, // Implicit gambling patterns
            'author_diversity' => true, // Bot vs. coordinated spam
            'template_extraction' => true, // Pattern identification
            'note' => 'Regex filtering handled by YouTube natively',
        ];
    }
}
