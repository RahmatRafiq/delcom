<?php

namespace App\Console\Commands;

use App\Services\HybridSpamDetector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TestSpamDetection extends Command
{
    protected $signature = 'spam:test {--file=complete_sample_comments.json}';

    protected $description = 'Test spam detection algorithm with sample comments from fixture';

    public function handle(): int
    {
        $this->info('🔍 Spam Detection Algorithm Test');
        $this->newLine();

        // Load test fixture
        $fixturePath = base_path('tests/Fixtures/SpamDetection/'.$this->option('file'));

        if (! File::exists($fixturePath)) {
            $this->error("❌ Fixture file not found: {$fixturePath}");

            return self::FAILURE;
        }

        $this->info("📂 Loading fixture: {$this->option('file')}");
        $testData = json_decode(File::get($fixturePath), true);

        if (! $testData) {
            $this->error('❌ Failed to parse JSON fixture');

            return self::FAILURE;
        }

        $this->info("✅ Loaded {$testData['test_metadata']['total_comments']} comments");
        $this->newLine();

        // Initialize detector
        $detector = new HybridSpamDetector;

        // Process comments
        $this->info('🚀 Processing comments...');
        $this->newLine();

        $comments = collect($testData['comments'])->map(function ($comment) {
            return [
                'id' => $comment['id'],
                'text' => $comment['text'],
                'author' => $comment['author'],
            ];
        })->toArray();

        $result = $detector->analyzeCommentBatch($comments);

        // Simple display
        $this->displaySimpleResults($result, $testData['comments'], $testData['expected_detection_summary']);

        return self::SUCCESS;
    }

    private function displaySimpleResults(array $result, array $allComments, array $expected): void
    {
        $totalComments = count($allComments);
        $spamDetected = count($result['spam_campaigns']);

        // Collect all spam comment IDs
        $spamIds = [];
        foreach ($result['spam_campaigns'] as $campaign) {
            foreach ($campaign['comment_ids'] as $id) {
                $spamIds[] = $id;
            }
        }
        $spamIds = array_unique($spamIds);

        // Summary
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📊 HASIL DETEKSI SPAM');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line("Total Komentar: {$totalComments}");
        $this->line("Clean: " . ($totalComments - count($spamIds)));
        $this->error("Spam Terdeteksi: " . count($spamIds) . " komentar");
        $this->newLine();

        // Show spam comments
        if (empty($spamIds)) {
            $this->info('✅ Tidak ada spam terdeteksi - Semua komentar bersih!');
            return;
        }

        $this->error('🚨 KOMENTAR YANG HARUS DIHAPUS (' . count($spamIds) . ' komentar):');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        foreach ($spamIds as $id) {
            $comment = collect($allComments)->firstWhere('id', $id);
            if (!$comment) {
                continue;
            }

            $this->line("<fg=red>ID {$id}</>");
            $this->line("👤 {$comment['author']}");
            $this->line("💬 " . mb_substr($comment['text'], 0, 150) . '...');
            $this->newLine();
        }

        // Validation
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $expectedSpam = $expected['breakdown']['spam_comments']['count'] ?? 0;
        $expectedIds = $expected['breakdown']['spam_comments']['ids'] ?? [];
        $matched = count(array_intersect($spamIds, $expectedIds));

        if ($matched === count($expectedIds)) {
            $this->info("✅ TEST PASS: Semua spam terdeteksi ({$matched}/{$expectedSpam})");
        } else {
            $this->error("❌ TEST FAIL: {$matched}/{$expectedSpam} spam terdeteksi");
        }
    }

    private function displaySummary(array $summary): void
    {
        $this->info('📊 SUMMARY');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Comments', $summary['total_comments']],
                ['Clusters Found', $summary['clusters_found']],
                ['Spam Campaigns', $summary['spam_campaigns']],
                ['Affected Comments', $summary['affected_comments'] ?? 0],
            ]
        );
    }

    private function displayClusters(array $clusters): void
    {
        if (empty($clusters)) {
            $this->warn('⚠️  No clusters detected');

            return;
        }

        $this->info("🔗 CLUSTERS DETECTED: ".count($clusters));

        foreach ($clusters as $index => $cluster) {
            $this->line("Cluster #".($index + 1).": {$cluster['members'][0]['normalized_text']}");
            $this->line("  Members: ".count($cluster['members']));
        }
    }

    private function displaySpamCampaigns(array $campaigns): void
    {
        if (empty($campaigns)) {
            $this->info('✅ No spam campaigns detected');

            return;
        }

        $this->error('🚨 SPAM CAMPAIGNS DETECTED: '.count($campaigns));
        $this->newLine();

        foreach ($campaigns as $index => $campaign) {
            $severity = $this->getSeverityColor($campaign['score']);

            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->line("Campaign #".($index + 1));
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->line("<{$severity}>Score: {$campaign['score']}/100</>");
            $this->line("Pattern: {$campaign['template']}");
            $this->line("Members: {$campaign['member_count']} comments");
            $this->line("Authors: ".implode(', ', $campaign['authors']));
            $this->line("Author Diversity: ".round($campaign['author_diversity'] * 100).'%');
            $this->line("\nSample: {$campaign['sample_text']}");
            $this->line("\nComment IDs: ".implode(', ', $campaign['comment_ids']));

            $this->newLine();
            $this->line('Detection Signals:');
            foreach ($campaign['signals'] as $signal) {
                $this->line("  • {$signal}");
            }

            $this->newLine();
        }
    }

    private function displayModerationActions(array $result, array $allComments): void
    {
        $this->info('🛡️  MODERATION ACTIONS REQUIRED');
        $this->newLine();

        // Collect all spam comment IDs
        $spamCommentIds = [];
        foreach ($result['spam_campaigns'] as $campaign) {
            foreach ($campaign['comment_ids'] as $id) {
                $spamCommentIds[$id] = [
                    'score' => $campaign['score'],
                    'signals' => $campaign['signals'],
                    'detection_type' => $campaign['detection_type'] ?? 'cluster',
                ];
            }
        }

        if (empty($spamCommentIds)) {
            $this->info('✅ No spam detected - All comments are clean!');

            return;
        }

        // Group by severity
        $highPriority = []; // Score >= 70
        $mediumPriority = []; // Score 60-69
        $lowPriority = []; // Score 50-59

        foreach ($spamCommentIds as $id => $data) {
            $comment = collect($allComments)->firstWhere('id', $id);
            if (! $comment) {
                continue;
            }

            $item = [
                'id' => $id,
                'author' => $comment['author'] ?? 'Unknown',
                'text' => mb_substr($comment['text'], 0, 100),
                'score' => $data['score'],
                'signals' => $data['signals'],
            ];

            if ($data['score'] >= 70) {
                $highPriority[] = $item;
            } elseif ($data['score'] >= 60) {
                $mediumPriority[] = $item;
            } else {
                $lowPriority[] = $item;
            }
        }

        // Display HIGH PRIORITY
        if (! empty($highPriority)) {
            $this->error('🚨 HIGH PRIORITY - Delete Immediately ('.count($highPriority).' comments)');
            $this->line('These are definite spam and should be removed immediately:');
            $this->newLine();

            foreach ($highPriority as $item) {
                $this->line("<fg=red>ID {$item['id']}</> - Score: {$item['score']}/100");
                $this->line("  Author: {$item['author']}");
                $this->line("  Text: {$item['text']}...");
                $this->line('  Reason: '.implode(', ', array_slice($item['signals'], 0, 2)));
                $this->newLine();
            }
        }

        // Display MEDIUM PRIORITY
        if (! empty($mediumPriority)) {
            $this->warn('⚠️  MEDIUM PRIORITY - Review & Remove ('.count($mediumPriority).' comments)');
            $this->line('These are likely spam and should be reviewed:');
            $this->newLine();

            foreach ($mediumPriority as $item) {
                $this->line("<fg=yellow>ID {$item['id']}</> - Score: {$item['score']}/100");
                $this->line("  Author: {$item['author']}");
                $this->line("  Text: {$item['text']}...");
                $this->line('  Reason: '.implode(', ', array_slice($item['signals'], 0, 2)));
                $this->newLine();
            }
        }

        // Display LOW PRIORITY
        if (! empty($lowPriority)) {
            $this->line('📋 LOW PRIORITY - Review ('.count($lowPriority).' comments)');
            $this->line('These are suspicious and should be monitored:');
            $this->newLine();

            foreach ($lowPriority as $item) {
                $this->line("<fg=cyan>ID {$item['id']}</> - Score: {$item['score']}/100");
                $this->line("  Author: {$item['author']}");
                $this->line("  Text: {$item['text']}...");
                $this->line('  Reason: '.implode(', ', array_slice($item['signals'], 0, 2)));
                $this->newLine();
            }
        }

        // Summary of actions
        $totalSpam = count($spamCommentIds);
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("📊 TOTAL: {$totalSpam} comments require moderation");
        $this->line('   High Priority (≥70): '.count($highPriority).' - DELETE IMMEDIATELY');
        $this->line('   Medium Priority (60-69): '.count($mediumPriority).' - REVIEW & REMOVE');
        $this->line('   Low Priority (50-59): '.count($lowPriority).' - MONITOR');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }

    private function validateResults(array $result, array $expected): void
    {
        $this->info('✓ VALIDATION');
        $this->newLine();

        $actualSpam = count($result['spam_campaigns']);
        $expectedSpam = $expected['breakdown']['spam_comments']['count'] ?? 0;

        $actualClusters = count($result['clusters']);
        $expectedClusters = count($expected['expected_clusters'] ?? []);

        // Check spam detection (allow detecting MORE spam than expected, but must detect at least expected amount)
        if ($actualSpam >= $expectedSpam) {
            $this->info("✅ Spam Detection: PASS ({$actualSpam} detected, expected at least {$expectedSpam})");
        } else {
            $this->error("❌ Spam Detection: FAIL ({$actualSpam}/{$expectedSpam})");
        }

        // Check cluster detection
        if ($actualClusters >= $expectedClusters) {
            $this->info("✅ Cluster Detection: PASS ({$actualClusters} clusters found, expected {$expectedClusters})");
        } else {
            $this->warn("⚠️  Cluster Detection: Partial ({$actualClusters}/{$expectedClusters})");
        }

        // Check specific spam comments
        $detectedSpamIds = [];
        foreach ($result['spam_campaigns'] as $campaign) {
            $detectedSpamIds = array_merge($detectedSpamIds, $campaign['comment_ids']);
        }

        $expectedSpamIds = $expected['breakdown']['spam_comments']['ids'] ?? [];
        $matched = count(array_intersect($detectedSpamIds, $expectedSpamIds));
        $total = count($expectedSpamIds);

        if ($matched === $total) {
            $this->info("✅ Spam IDs Matched: PASS ({$matched}/{$total})");
        } else {
            $this->error("❌ Spam IDs Matched: FAIL ({$matched}/{$total})");
            $this->line('Expected: '.implode(', ', $expectedSpamIds));
            $this->line('Detected: '.implode(', ', $detectedSpamIds));
        }

        // Check Unicode detection (look for any signal containing "Unicode fancy fonts detected")
        $hasUnicodeSpam = false;
        foreach ($result['spam_campaigns'] as $campaign) {
            foreach ($campaign['signals'] as $signal) {
                if (str_contains($signal, 'Unicode fancy fonts detected')) {
                    $hasUnicodeSpam = true;
                    break 2;
                }
            }
        }

        if ($hasUnicodeSpam) {
            $this->info('✅ Unicode Detection: PASS (Fancy fonts detected)');
        } else {
            $this->error('❌ Unicode Detection: FAIL (Fancy fonts NOT detected)');
        }

        $this->newLine();

        // Overall verdict
        // PASS criteria:
        // 1. All expected spam IDs found (matched === total)
        // 2. Unicode detection working (hasUnicodeSpam)
        // 3. At least expected spam count detected (actualSpam >= expectedSpam)
        $passed = ($matched === $total) && $hasUnicodeSpam && ($actualSpam >= $expectedSpam);

        if ($passed) {
            $this->info('🎉 TEST RESULT: PASS');
            $this->line('The algorithm correctly identified all spam patterns!');
            if ($actualClusters < $expectedClusters) {
                $this->newLine();
                $this->line('Note: Spam detected via individual detection instead of clustering.');
                $this->line('This is correct behavior when spam text varies too much to cluster.');
            }
        } else {
            $this->error('❌ TEST RESULT: FAIL');
            $this->line('Some spam patterns were not detected correctly.');
        }
    }

    private function getSeverityColor(int $score): string
    {
        if ($score >= 90) {
            return 'fg=red;options=bold';
        }
        if ($score >= 80) {
            return 'fg=red';
        }
        if ($score >= 70) {
            return 'fg=yellow';
        }

        return 'fg=green';
    }
}
