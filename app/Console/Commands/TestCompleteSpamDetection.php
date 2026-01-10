<?php

namespace App\Console\Commands;

use App\Services\HybridSpamDetector;
use App\Services\SpamDetection\ContextualAnalyzer;
use App\Services\SpamDetection\FuzzyMatcher;
use App\Services\SpamDetection\PatternAnalyzer;
use App\Services\SpamDetection\SpamClusterDetector;
use App\Services\SpamDetection\UnicodeDetector;
use Illuminate\Console\Command;

class TestCompleteSpamDetection extends Command
{
    protected $signature = 'test:complete-spam-detection
                          {--fixture=complete_sample_comments_02.json : Fixture filename to test}
                          {--detailed : Show detailed analysis for each comment}
                          {--limit=10 : Limit number of results to show}
                          {--export : Export categorized results to JSON file}
                          {--show-categories : Show categorized comments by verdict}';

    protected $description = 'Test complete spam detection system with ALL layers (Pattern, Unicode, Cluster, Context)';

    private HybridSpamDetector $hybridDetector;

    private PatternAnalyzer $patternAnalyzer;

    private UnicodeDetector $unicodeDetector;

    private ContextualAnalyzer $contextualAnalyzer;

    private SpamClusterDetector $clusterDetector;

    private FuzzyMatcher $fuzzyMatcher;

    public function __construct()
    {
        parent::__construct();

        $this->hybridDetector = new HybridSpamDetector;
        $this->patternAnalyzer = new PatternAnalyzer;
        $this->unicodeDetector = new UnicodeDetector;
        $this->contextualAnalyzer = new ContextualAnalyzer;
        $this->clusterDetector = new SpamClusterDetector;
        $this->fuzzyMatcher = new FuzzyMatcher;
    }

    public function handle(): int
    {
        $this->info('🚀 COMPLETE SPAM DETECTION SYSTEM TEST');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        // Load fixture data
        $fixtureFile = $this->option('fixture');
        $fixturePath = base_path("tests/Fixtures/SpamDetection/{$fixtureFile}");

        if (! file_exists($fixturePath)) {
            $this->error("❌ Fixture file not found: {$fixtureFile}");
            $this->line("   Path: {$fixturePath}");

            return 1;
        }

        $data = json_decode(file_get_contents($fixturePath), true);
        $comments = $data['comments'] ?? [];

        if (empty($comments)) {
            $this->error('❌ No comments found in fixture!');

            return 1;
        }

        $this->info("📦 Loaded ".count($comments)." comments from fixture: {$fixtureFile}");
        $this->newLine();

        // Phase 1: Hybrid Detector (Batch Analysis)
        $this->info('🔬 PHASE 1: Hybrid Detector (Batch Analysis)');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $batchResult = $this->runHybridDetection($comments);
        $this->newLine();

        // Phase 2: Individual Layer Analysis
        $this->info('🔬 PHASE 2: Individual Layer Analysis');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $layerResults = $this->runLayerAnalysis($comments, $batchResult);
        $this->newLine();

        // Phase 3: Accuracy Comparison
        $this->info('🔬 PHASE 3: Accuracy Analysis');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $accuracy = $this->calculateAccuracy($comments, $layerResults, $batchResult);
        $this->newLine();

        // Phase 4: Detailed Results (if requested)
        if ($this->option('detailed')) {
            $this->info('🔬 PHASE 4: Detailed Results');
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->showDetailedResults($comments, $layerResults, $batchResult);
            $this->newLine();
        }

        // Phase 5: Show Categorized Comments (if requested)
        if ($this->option('show-categories')) {
            $this->info('🔬 PHASE 5: Categorized Comments');
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->showCategorizedComments($comments, $layerResults, $batchResult);
            $this->newLine();
        }

        // Export results (if requested)
        if ($this->option('export')) {
            $this->exportResults($comments, $layerResults, $batchResult, $accuracy);
        }

        // Final Summary
        $this->displayFinalSummary($accuracy, $batchResult);

        return 0;
    }

    private function runHybridDetection(array $comments): array
    {
        // Format comments for batch analysis
        $formattedComments = array_map(function ($comment, $index) {
            return [
                'id' => $comment['id'] ?? "comment_{$index}",
                'text' => $comment['text'],
                'author' => $comment['author'] ?? 'Unknown',
            ];
        }, $comments, array_keys($comments));

        $result = $this->hybridDetector->analyzeCommentBatch($formattedComments);

        $this->line('Cluster Detection Results:');
        $this->line("├─ Total Clusters: {$result['summary']['clusters_found']}");
        $this->line("├─ Spam Campaigns: {$result['summary']['spam_campaigns']}");
        $this->line("├─ Comments Flagged: {$result['summary']['affected_comments']}");
        $this->line("└─ Total Comments: {$result['summary']['total_comments']}");

        return $result;
    }

    private function runLayerAnalysis(array $comments, array $batchResult): array
    {
        $results = [
            'pattern' => [],
            'unicode' => [],
            'contextual' => [],
            'individual_spam' => [],
        ];

        // Map spam campaign scores by comment ID
        $spamScores = [];
        foreach ($batchResult['spam_campaigns'] ?? [] as $campaign) {
            $score = $campaign['score'] ?? 0;
            foreach ($campaign['comment_ids'] ?? [] as $commentId) {
                $spamScores[$commentId] = [
                    'score' => $score,
                    'signals' => $campaign['signals'] ?? [],
                ];
            }
        }

        foreach ($comments as $comment) {
            if (empty($comment['text'])) {
                continue;
            }

            $id = $comment['id'] ?? 'unknown';
            $text = $comment['text'];
            $expected = $comment['expected_result'] ?? 'unknown';

            // Layer 1: Pattern Analysis
            $patternResult = $this->patternAnalyzer->analyzePatterns($text);
            $results['pattern'][$id] = $patternResult;

            // Layer 2: Unicode Detection
            $unicodeResult = $this->unicodeDetector->getStatistics($text);
            $results['unicode'][$id] = [
                'has_fancy_fonts' => $unicodeResult['hasFancy'],
                'suspicious_unicode' => $unicodeResult['density'] > 0.3,
                'unicode_count' => $unicodeResult['count'],
                'unicode_density' => $unicodeResult['density'],
                'detected_fonts' => array_keys($unicodeResult['ranges'] ?? []),
            ];

            // Layer 3: Contextual Analysis
            $baseScore = 50; // neutral score
            $contextResult = $this->contextualAnalyzer->analyzeContext($text, $baseScore);
            $results['contextual'][$id] = $contextResult;

            // Get score from HybridDetector (if flagged)
            $spamScore = $spamScores[$id]['score'] ?? 0;
            $signals = $spamScores[$id]['signals'] ?? [];

            // Categorize based on score
            $category = $this->categorizeByScore($spamScore);

            $results['individual_spam'][$id] = [
                'spam_score' => $spamScore,
                'category' => $category,
                'is_spam' => $spamScore >= 70, // CRITICAL threshold
                'signals' => $signals,
                'expected' => $expected,
                'text' => $text,
            ];
        }

        // Display layer statistics
        $this->displayLayerStats($results);

        return $results;
    }

    private function categorizeByScore(int $score): string
    {
        if ($score >= 70) {
            return 'CRITICAL'; // Auto delete
        } elseif ($score >= 40) {
            return 'MEDIUM'; // Review queue
        } else {
            return 'LOW'; // Ignore
        }
    }

    private function calculateIndividualSpamScore(array $pattern, array $unicode, array $context): int
    {
        $score = 0;

        // Pattern signals (max 40 points)
        if ($pattern['has_money']) {
            $score += 15;
        }
        if ($pattern['has_urgency']) {
            $score += 10;
        }
        if ($pattern['has_link_promotion']) {
            $score += 15;
        }

        // Unicode tricks (max 30 points)
        if ($unicode['has_fancy_fonts'] ?? false) {
            $score += 20;
        }
        if ($unicode['suspicious_unicode'] ?? false) {
            $score += 10;
        }

        // Caps and emoji (max 20 points)
        if ($pattern['caps_ratio'] > 0.5) {
            $score += 10;
        }
        if ($pattern['emoji_density'] > 0.2) {
            $score += 10;
        }

        // Apply contextual adjustment (max 10 points reduction or addition)
        $contextAdjustment = $context['adjusted_score'] - 50; // 50 is neutral
        $score += $contextAdjustment;

        return max(0, min(100, $score));
    }

    private function displayLayerStats(array $results): void
    {
        $patternCount = count(array_filter($results['pattern'], fn ($r) => ! empty($r['spam_signals'])));
        $unicodeCount = count(array_filter($results['unicode'], fn ($r) => $r['has_fancy_fonts'] || $r['suspicious_unicode']));
        $spamCount = count(array_filter($results['individual_spam'], fn ($r) => $r['is_spam']));

        $this->line('Layer Detection Results:');
        $this->line("├─ Pattern Layer: {$patternCount} flagged");
        $this->line("├─ Unicode Layer: {$unicodeCount} flagged");
        $this->line("└─ Combined Individual: {$spamCount} spam detected");
    }

    private function calculateAccuracy(array $comments, array $layerResults, array $batchResult): array
    {
        $stats = [
            'total' => count($comments),
            'total_spam' => 0,
            'total_clean' => 0,
            'layers' => [
                'pattern' => ['tp' => 0, 'fp' => 0, 'tn' => 0, 'fn' => 0],
                'unicode' => ['tp' => 0, 'fp' => 0, 'tn' => 0, 'fn' => 0],
                'individual' => ['tp' => 0, 'fp' => 0, 'tn' => 0, 'fn' => 0],
                'hybrid' => ['tp' => 0, 'fp' => 0, 'tn' => 0, 'fn' => 0],
            ],
        ];

        // Get flagged IDs from batch result
        $flaggedIds = [];
        foreach ($batchResult['spam_campaigns'] ?? [] as $campaign) {
            foreach ($campaign['comment_ids'] ?? [] as $commentId) {
                $flaggedIds[] = $commentId;
            }
        }

        foreach ($comments as $comment) {
            $id = $comment['id'] ?? 'unknown';
            $expected = $comment['expected_result'] ?? 'unknown';
            $isSpam = $expected === 'spam';

            if ($isSpam) {
                $stats['total_spam']++;
            } else {
                $stats['total_clean']++;
            }

            // Pattern layer accuracy
            $patternFlagged = ! empty($layerResults['pattern'][$id]['spam_signals'] ?? []);
            $this->updateConfusionMatrix($stats['layers']['pattern'], $isSpam, $patternFlagged);

            // Unicode layer accuracy
            $unicodeFlagged = ($layerResults['unicode'][$id]['has_fancy_fonts'] ?? false) ||
                            ($layerResults['unicode'][$id]['suspicious_unicode'] ?? false);
            $this->updateConfusionMatrix($stats['layers']['unicode'], $isSpam, $unicodeFlagged);

            // Individual combined accuracy
            $individualSpam = $layerResults['individual_spam'][$id]['is_spam'] ?? false;
            $this->updateConfusionMatrix($stats['layers']['individual'], $isSpam, $individualSpam);

            // Hybrid batch accuracy
            $hybridFlagged = in_array($id, $flaggedIds);
            $this->updateConfusionMatrix($stats['layers']['hybrid'], $isSpam, $hybridFlagged);
        }

        // Display accuracy metrics
        $this->displayAccuracyMetrics($stats);

        return $stats;
    }

    private function updateConfusionMatrix(array &$layer, bool $isSpam, bool $flagged): void
    {
        if ($isSpam && $flagged) {
            $layer['tp']++; // True Positive
        } elseif (! $isSpam && $flagged) {
            $layer['fp']++; // False Positive
        } elseif (! $isSpam && ! $flagged) {
            $layer['tn']++; // True Negative
        } else {
            $layer['fn']++; // False Negative
        }
    }

    private function displayAccuracyMetrics(array $stats): void
    {
        $this->line("Dataset: {$stats['total']} comments ({$stats['total_spam']} spam, {$stats['total_clean']} clean)");
        $this->newLine();

        foreach ($stats['layers'] as $layerName => $metrics) {
            $precision = $this->calculatePrecision($metrics);
            $recall = $this->calculateRecall($metrics);
            $f1 = $this->calculateF1($precision, $recall);
            $accuracy = $this->calculateAccuracyRate($metrics);

            $displayName = ucfirst($layerName);
            $this->line("<fg=cyan>{$displayName} Layer:</>");
            $this->line('├─ Accuracy:  '.number_format($accuracy, 1).'%');
            $this->line('├─ Precision: '.number_format($precision, 1).'%');
            $this->line('├─ Recall:    '.number_format($recall, 1).'%');
            $this->line('└─ F1 Score:  '.number_format($f1, 1).'%');
            $this->newLine();
        }
    }

    private function calculatePrecision(array $metrics): float
    {
        $tp = $metrics['tp'];
        $fp = $metrics['fp'];

        return ($tp + $fp) > 0 ? ($tp / ($tp + $fp)) * 100 : 0;
    }

    private function calculateRecall(array $metrics): float
    {
        $tp = $metrics['tp'];
        $fn = $metrics['fn'];

        return ($tp + $fn) > 0 ? ($tp / ($tp + $fn)) * 100 : 0;
    }

    private function calculateF1(float $precision, float $recall): float
    {
        return ($precision + $recall) > 0 ? 2 * ($precision * $recall) / ($precision + $recall) : 0;
    }

    private function calculateAccuracyRate(array $metrics): float
    {
        $total = $metrics['tp'] + $metrics['fp'] + $metrics['tn'] + $metrics['fn'];

        return $total > 0 ? (($metrics['tp'] + $metrics['tn']) / $total) * 100 : 0;
    }

    private function showDetailedResults(array $comments, array $layerResults, array $batchResult): void
    {
        $limit = (int) $this->option('limit');
        $count = 0;

        $this->line('Showing detailed analysis for first '.$limit.' comments:');
        $this->newLine();

        foreach ($comments as $comment) {
            if ($count >= $limit) {
                break;
            }

            $id = $comment['id'] ?? 'unknown';
            $text = $comment['text'];
            $expected = $comment['expected_result'] ?? 'unknown';

            $this->line('<fg=yellow>═══════════════════════════════════════════════════════════════</>');
            $this->line("<fg=cyan>Comment ID: {$id}</>");
            $this->line("<fg=gray>Expected: {$expected}</>");
            $this->line('Text: '.mb_substr($text, 0, 100).'...');
            $this->newLine();

            // Pattern analysis
            $pattern = $layerResults['pattern'][$id] ?? [];
            $this->line('<fg=magenta>Pattern Analysis:</>');
            if (! empty($pattern['spam_signals'])) {
                $this->line('  Signals: '.implode(', ', $pattern['spam_signals']));
            } else {
                $this->line('  No pattern signals detected');
            }

            // Unicode analysis
            $unicode = $layerResults['unicode'][$id] ?? [];
            $this->line('<fg=magenta>Unicode Analysis:</>');
            if ($unicode['has_fancy_fonts'] ?? false) {
                $this->line('  ⚠️  Fancy fonts detected: '.implode(', ', $unicode['detected_fonts'] ?? []));
            } else {
                $this->line('  No fancy fonts detected');
            }

            // Contextual analysis
            $context = $layerResults['contextual'][$id] ?? [];
            $this->line('<fg=magenta>Contextual Analysis:</>');
            $this->line("  Context: {$context['context']} (score adjustment: ".($context['adjusted_score'] - 50).')');

            // Final score
            $individual = $layerResults['individual_spam'][$id] ?? [];
            $finalScore = $individual['spam_score'] ?? 0;
            $isSpam = $individual['is_spam'] ?? false;
            $verdict = $isSpam ? '<fg=red>SPAM</>' : '<fg=green>CLEAN</>';

            $this->line("<fg=magenta>Final Verdict: {$verdict} (Score: {$finalScore}/100)</>");
            $this->newLine();

            $count++;
        }

        if (count($comments) > $limit) {
            $remaining = count($comments) - $limit;
            $this->line("<fg=gray>... and {$remaining} more comments (use --limit=N to show more)</>");
        }
    }

    private function showCategorizedComments(array $comments, array $layerResults, array $batchResult): void
    {
        $categorized = [
            'critical' => [], // Score 70-100
            'medium' => [], // Score 40-69
            'low' => [], // Score 0-39
            'spam_detected' => [],
            'clean_verified' => [],
            'false_positives' => [],
            'false_negatives' => [],
        ];

        // Get flagged IDs from batch result
        $flaggedIds = [];
        foreach ($batchResult['spam_campaigns'] ?? [] as $campaign) {
            foreach ($campaign['comment_ids'] ?? [] as $commentId) {
                $flaggedIds[] = $commentId;
            }
        }

        foreach ($comments as $comment) {
            $id = $comment['id'] ?? 'unknown';
            $text = $comment['text'];
            $expected = $comment['expected_result'] ?? 'unknown';
            $isSpam = $expected === 'spam';

            $individual = $layerResults['individual_spam'][$id] ?? [];
            $detectedAsSpam = $individual['is_spam'] ?? false;
            $spamScore = $individual['spam_score'] ?? 0;

            // Get detection details
            $pattern = $layerResults['pattern'][$id] ?? [];
            $unicode = $layerResults['unicode'][$id] ?? [];
            $context = $layerResults['contextual'][$id] ?? [];

            $signals = [];
            if (! empty($pattern['spam_signals'])) {
                $signals = array_merge($signals, $pattern['spam_signals']);
            }
            if ($unicode['has_fancy_fonts'] ?? false) {
                $signals[] = 'Unicode Fancy Fonts';
            }

            $category = $individual['category'] ?? 'LOW';

            $commentData = [
                'id' => $id,
                'text' => mb_substr($text, 0, 100),
                'expected' => $expected,
                'spam_score' => $spamScore,
                'category' => $category,
                'signals' => $signals,
                'context' => $context['context'] ?? 'unknown',
                'unicode_density' => $unicode['unicode_density'] ?? 0,
            ];

            // Categorize by score thresholds
            if ($category === 'CRITICAL') {
                $categorized['critical'][] = $commentData;
            } elseif ($category === 'MEDIUM') {
                $categorized['medium'][] = $commentData;
            } else {
                $categorized['low'][] = $commentData;
            }

            // Legacy categorization (for accuracy comparison)
            if ($isSpam && $detectedAsSpam) {
                $categorized['spam_detected'][] = $commentData;
            } elseif (! $isSpam && ! $detectedAsSpam) {
                $categorized['clean_verified'][] = $commentData;
            } elseif (! $isSpam && $detectedAsSpam) {
                $categorized['false_positives'][] = $commentData;
            } else {
                $categorized['false_negatives'][] = $commentData;
            }
        }

        // Display categorized results
        $limit = (int) $this->option('limit');

        // === KATEGORI BERDASARKAN SCORE ===
        $this->info('📊 KATEGORISASI BERDASARKAN SCORE:');
        $this->newLine();

        // 1. CRITICAL (70-100) - Auto Delete
        if (! empty($categorized['critical'])) {
            $count = count($categorized['critical']);
            $this->error("🔴 CRITICAL - AUTO DELETE ({$count} comments)");
            $this->line('Action: Hapus otomatis');
            $this->newLine();
            foreach (array_slice($categorized['critical'], 0, $limit) as $item) {
                $this->line("<fg=red>ID: {$item['id']}</> | Score: {$item['spam_score']}/100 | Category: {$item['category']}");
                $this->line('Signals: '.implode(', ', $item['signals']));
                $this->line("Text: {$item['text']}...");
                $this->newLine();
            }
            if ($count > $limit) {
                $this->line('<fg=gray>... and '.($count - $limit).' more</>');
                $this->newLine();
            }
        }

        // 2. MEDIUM (40-69) - Review Queue
        if (! empty($categorized['medium'])) {
            $count = count($categorized['medium']);
            $this->warn("🟡 MEDIUM - REVIEW QUEUE ({$count} comments)");
            $this->line('Action: Masuk queue untuk review manual');
            $this->newLine();
            foreach (array_slice($categorized['medium'], 0, $limit) as $item) {
                $this->line("<fg=yellow>ID: {$item['id']}</> | Score: {$item['spam_score']}/100 | Category: {$item['category']}");
                $this->line('Signals: '.implode(', ', $item['signals']));
                $this->line("Text: {$item['text']}...");
                $this->newLine();
            }
            if ($count > $limit) {
                $this->line('<fg=gray>... and '.($count - $limit).' more</>');
                $this->newLine();
            }
        }

        // 3. LOW (0-39) - Ignore
        $lowCount = count($categorized['low']);
        $this->info("🟢 LOW - IGNORE ({$lowCount} comments)");
        $this->line('Action: Tidak ada action, biarkan saja');
        $this->newLine();

        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📋 ACCURACY BREAKDOWN:');
        $this->newLine();

        // 1. Spam Detected (True Positives)
        if (! empty($categorized['spam_detected'])) {
            $count = count($categorized['spam_detected']);
            $this->info("✅ SPAM CORRECTLY DETECTED ({$count} comments)");
            $this->newLine();
            foreach (array_slice($categorized['spam_detected'], 0, $limit) as $item) {
                $this->line("<fg=green>ID: {$item['id']}</> | Score: {$item['spam_score']}/100");
                $this->line('Signals: '.implode(', ', $item['signals']));
                $this->line("Text: {$item['text']}...");
                $this->newLine();
            }
            if ($count > $limit) {
                $this->line('<fg=gray>... and '.($count - $limit).' more</>');
                $this->newLine();
            }
        }

        // 2. False Positives
        if (! empty($categorized['false_positives'])) {
            $count = count($categorized['false_positives']);
            $this->error("❌ FALSE POSITIVES ({$count} comments)");
            $this->newLine();
            foreach (array_slice($categorized['false_positives'], 0, $limit) as $item) {
                $this->line("<fg=yellow>ID: {$item['id']}</> | Score: {$item['spam_score']}/100");
                $this->line('Signals: '.implode(', ', $item['signals']));
                $this->line("Context: {$item['context']}");
                $this->line("Text: {$item['text']}...");
                $this->newLine();
            }
            if ($count > $limit) {
                $this->line('<fg=gray>... and '.($count - $limit).' more</>');
                $this->newLine();
            }
        }

        // 3. False Negatives (Missed Spam)
        if (! empty($categorized['false_negatives'])) {
            $count = count($categorized['false_negatives']);
            $this->error("⚠️  MISSED SPAM ({$count} comments)");
            $this->newLine();
            foreach (array_slice($categorized['false_negatives'], 0, $limit) as $item) {
                $this->line("<fg=red>ID: {$item['id']}</> | Score: {$item['spam_score']}/100");
                $this->line('Reason: Score below threshold');
                $this->line("Text: {$item['text']}...");
                $this->newLine();
            }
            if ($count > $limit) {
                $this->line('<fg=gray>... and '.($count - $limit).' more</>');
                $this->newLine();
            }
        }

        // 4. Clean Verified (True Negatives) - Summary only
        $cleanCount = count($categorized['clean_verified']);
        $this->info("✅ CLEAN COMMENTS VERIFIED: {$cleanCount}");
        $this->newLine();
    }

    private function exportResults(array $comments, array $layerResults, array $batchResult, array $accuracy): void
    {
        $exportData = [
            'test_info' => [
                'timestamp' => now()->toDateTimeString(),
                'total_comments' => count($comments),
                'fixture_file' => 'complete_sample_comments_02.json',
            ],
            'accuracy_metrics' => $accuracy,
            'categorization_summary' => [
                'critical' => [
                    'count' => 0,
                    'action' => 'Auto Delete',
                    'threshold' => '70-100',
                    'comments' => [],
                ],
                'medium' => [
                    'count' => 0,
                    'action' => 'Review Queue',
                    'threshold' => '40-69',
                    'comments' => [],
                ],
                'low' => [
                    'count' => 0,
                    'action' => 'Ignore',
                    'threshold' => '0-39',
                    'comments' => [],
                ],
            ],
            'categorized_comments' => [],
        ];

        foreach ($comments as $comment) {
            $id = $comment['id'] ?? 'unknown';
            $individual = $layerResults['individual_spam'][$id] ?? [];
            $pattern = $layerResults['pattern'][$id] ?? [];
            $unicode = $layerResults['unicode'][$id] ?? [];
            $context = $layerResults['contextual'][$id] ?? [];

            $spamScore = $individual['spam_score'] ?? 0;
            $category = $individual['category'] ?? 'LOW';
            $signals = $individual['signals'] ?? [];

            $commentData = [
                'id' => $id,
                'text' => $comment['text'],
                'author' => $comment['author'] ?? 'Unknown',
                'expected_result' => $comment['expected_result'] ?? 'unknown',
                'detection_result' => [
                    'is_spam' => $individual['is_spam'] ?? false,
                    'spam_score' => $spamScore,
                    'category' => $category,
                    'signals' => $signals,
                ],
                'layer_details' => [
                    'pattern' => [
                        'signals' => $pattern['spam_signals'] ?? [],
                        'has_money' => $pattern['has_money'] ?? false,
                        'has_urgency' => $pattern['has_urgency'] ?? false,
                        'has_link_promotion' => $pattern['has_link_promotion'] ?? false,
                        'caps_ratio' => $pattern['caps_ratio'] ?? 0,
                        'emoji_density' => $pattern['emoji_density'] ?? 0,
                    ],
                    'unicode' => [
                        'has_fancy_fonts' => $unicode['has_fancy_fonts'] ?? false,
                        'unicode_density' => $unicode['unicode_density'] ?? 0,
                        'detected_fonts' => $unicode['detected_fonts'] ?? [],
                    ],
                    'contextual' => [
                        'context' => $context['context'] ?? 'unknown',
                        'adjusted_score' => $context['adjusted_score'] ?? 0,
                    ],
                ],
            ];

            // Add to full list
            $exportData['categorized_comments'][] = $commentData;

            // Add to category-specific list
            $categoryKey = strtolower($category);
            if (isset($exportData['categorization_summary'][$categoryKey])) {
                $exportData['categorization_summary'][$categoryKey]['comments'][] = [
                    'id' => $id,
                    'text' => $comment['text'],
                    'author' => $comment['author'] ?? 'Unknown',
                    'score' => $spamScore,
                    'signals' => $signals,
                ];
                $exportData['categorization_summary'][$categoryKey]['count']++;
            }
        }

        $filename = storage_path('app/spam_detection_results_'.now()->format('Y-m-d_His').'.json');
        file_put_contents($filename, json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->newLine();
        $this->info("📁 Results exported to: {$filename}");
        $this->line("   - CRITICAL: {$exportData['categorization_summary']['critical']['count']} comments");
        $this->line("   - MEDIUM: {$exportData['categorization_summary']['medium']['count']} comments");
        $this->line("   - LOW: {$exportData['categorization_summary']['low']['count']} comments");
        $this->newLine();
    }

    private function displayFinalSummary(array $accuracy, array $batchResult): void
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('🎯 FINAL SUMMARY');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        // Best performing layer
        $bestLayer = null;
        $bestF1 = 0;

        foreach ($accuracy['layers'] as $name => $metrics) {
            $precision = $this->calculatePrecision($metrics);
            $recall = $this->calculateRecall($metrics);
            $f1 = $this->calculateF1($precision, $recall);

            if ($f1 > $bestF1) {
                $bestF1 = $f1;
                $bestLayer = $name;
            }
        }

        $this->info('🏆 Best Performing Layer: '.ucfirst($bestLayer).' (F1: '.number_format($bestF1, 1).'%)');
        $this->newLine();

        // Recommendations
        $this->line('<fg=cyan>📋 Recommendations:</>');

        $hybridMetrics = $accuracy['layers']['hybrid'];
        $hybridF1 = $this->calculateF1(
            $this->calculatePrecision($hybridMetrics),
            $this->calculateRecall($hybridMetrics)
        );

        if ($hybridF1 >= 80) {
            $this->info('✅ Hybrid detector performing excellently!');
        } elseif ($hybridF1 >= 60) {
            $this->warn('⚠️  Hybrid detector needs improvement:');
            if ($hybridMetrics['fp'] > $hybridMetrics['tp']) {
                $this->line('   → Too many false positives, adjust thresholds');
            }
            if ($hybridMetrics['fn'] > $hybridMetrics['tp'] / 2) {
                $this->line('   → Missing too much spam, add more detection patterns');
            }
        } else {
            $this->error('❌ Hybrid detector needs major improvements:');
            $this->line('   → Consider using individual layer with best F1 score');
            $this->line('   → Review and update detection algorithms');
        }

        $this->newLine();
        $this->line('<fg=gray>💡 Tip: Use --detailed flag to see per-comment analysis</>');
    }
}
