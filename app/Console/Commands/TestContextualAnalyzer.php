<?php

namespace App\Console\Commands;

use App\Services\SpamDetection\ContextualAnalyzer;
use App\Services\SpamDetection\PatternAnalyzer;
use Illuminate\Console\Command;

class TestContextualAnalyzer extends Command
{
    protected $signature = 'test:contextual-analyzer';

    protected $description = 'Test ContextualAnalyzer service with fixture data';

    public function handle(): int
    {
        $this->info('🧪 Testing ContextualAnalyzer Service');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        // Load test data
        $fixturePath = base_path('tests/Fixtures/SpamDetection/complete_sample_comments_02.json');
        if (! file_exists($fixturePath)) {
            $this->error('❌ Fixture file not found!');

            return 1;
        }

        $data = json_decode(file_get_contents($fixturePath), true);
        $comments = $data['comments'] ?? [];

        if (empty($comments)) {
            $this->error('❌ No comments found in fixture!');

            return 1;
        }

        $contextual = new ContextualAnalyzer();
        $pattern = new PatternAnalyzer();

        // Test contextual analysis
        $stats = [
            'total' => count($comments),
            'contexts' => [
                'educational' => 0,
                'question' => 0,
                'warning' => 0,
                'promotional' => 0,
                'unknown' => 0,
            ],
            'whitelisted' => 0,
            'false_positives_fixed' => [],
            'remaining_false_positives' => [],
            'legitimate_spam_reduced' => [],
        ];

        foreach ($comments as $comment) {
            if (empty($comment['text'])) {
                continue;
            }

            $expected = $comment['expected_result'] ?? 'unknown';
            $isSpam = $expected === 'spam';

            // Check if PatternAnalyzer flagged it
            $patternResult = $pattern->analyzePatterns($comment['text']);
            $flaggedByPattern = ! empty($patternResult['spam_signals']);

            // Test contextual analysis with base score 70 (medium spam)
            $contextResult = $contextual->analyzeContext($comment['text'], 70);
            $isWhitelisted = $contextual->shouldWhitelist($comment['text']);

            // Track contexts
            $context = $contextResult['context'];
            if (isset($stats['contexts'][$context])) {
                $stats['contexts'][$context]++;
            }

            if ($isWhitelisted) {
                $stats['whitelisted']++;
            }

            // Check if context fixed false positive
            if ($flaggedByPattern && ! $isSpam && $contextResult['adjusted_score'] < 60) {
                $stats['false_positives_fixed'][] = [
                    'id' => $comment['id'],
                    'text' => $comment['text'],
                    'context' => $context,
                    'original_score' => 70,
                    'adjusted_score' => $contextResult['adjusted_score'],
                    'signals' => $patternResult['spam_signals'],
                ];
            } elseif ($flaggedByPattern && ! $isSpam && $contextResult['adjusted_score'] >= 60) {
                // Still false positive after context
                $stats['remaining_false_positives'][] = [
                    'id' => $comment['id'],
                    'text' => $comment['text'],
                    'context' => $context,
                    'adjusted_score' => $contextResult['adjusted_score'],
                    'signals' => $patternResult['spam_signals'],
                ];
            }

            // Check if context incorrectly reduced spam score
            if ($flaggedByPattern && $isSpam && $contextResult['adjusted_score'] < 60) {
                $stats['legitimate_spam_reduced'][] = [
                    'id' => $comment['id'],
                    'text' => $comment['text'],
                    'context' => $context,
                    'adjusted_score' => $contextResult['adjusted_score'],
                ];
            }
        }

        // Display results
        $this->displaySummary($stats);
        $this->newLine();

        if (! empty($stats['false_positives_fixed'])) {
            $this->displayFixedFalsePositives($stats['false_positives_fixed']);
            $this->newLine();
        }

        if (! empty($stats['remaining_false_positives'])) {
            $this->displayRemainingFalsePositives($stats['remaining_false_positives']);
            $this->newLine();
        }

        if (! empty($stats['legitimate_spam_reduced'])) {
            $this->displayLegitimateSpamReduced($stats['legitimate_spam_reduced']);
            $this->newLine();
        }

        $this->displayVerdict($stats);

        return 0;
    }

    private function displaySummary(array $stats): void
    {
        $this->info('📊 RINGKASAN');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $this->line("Total Komentar: {$stats['total']}");
        $this->line("Whitelisted: {$stats['whitelisted']}");
        $this->newLine();

        $this->line('Context Detection:');
        foreach ($stats['contexts'] as $context => $count) {
            $this->line("├─ ".ucfirst($context).": {$count}");
        }
        $this->newLine();

        $fixedCount = count($stats['false_positives_fixed']);
        $remainingCount = count($stats['remaining_false_positives']);
        $spamReducedCount = count($stats['legitimate_spam_reduced']);

        $this->info("✅ False Positives Fixed: {$fixedCount}");
        $this->error("❌ Remaining False Positives: {$remainingCount}");
        $this->error("⚠️  Legitimate Spam Reduced: {$spamReducedCount}");
    }

    private function displayFixedFalsePositives(array $fixed): void
    {
        $this->info('✅ FALSE POSITIVES FIXED BY CONTEXT ('.count($fixed).' komentar)');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        foreach (array_slice($fixed, 0, 10) as $item) {
            $this->line("<fg=green>ID {$item['id']}</>");
            $this->line("Context: {$item['context']}");
            $this->line("Score: {$item['original_score']} → {$item['adjusted_score']} (below threshold)");
            $this->line('Pattern signals: '.implode(', ', $item['signals']));
            $this->line('💬 '.mb_substr($item['text'], 0, 100).'...');
            $this->newLine();
        }

        if (count($fixed) > 10) {
            $remaining = count($fixed) - 10;
            $this->line("<fg=gray>... dan {$remaining} komentar lainnya</>");
        }
    }

    private function displayRemainingFalsePositives(array $fps): void
    {
        $this->error('❌ FALSE POSITIVES YANG BELUM TERFIX ('.count($fps).' komentar)');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        foreach (array_slice($fps, 0, 10) as $item) {
            $this->line("<fg=yellow>ID {$item['id']}</>");
            $this->line("Context: {$item['context']} (score: {$item['adjusted_score']})");
            $this->line('Pattern signals: '.implode(', ', $item['signals']));
            $this->line('💬 '.mb_substr($item['text'], 0, 100).'...');
            $this->newLine();
        }

        if (count($fps) > 10) {
            $remaining = count($fps) - 10;
            $this->line("<fg=gray>... dan {$remaining} komentar lainnya</>");
        }
    }

    private function displayLegitimateSpamReduced(array $reduced): void
    {
        $this->error('⚠️  SPAM YANG SCORE-NYA DITURUNKAN CONTEXT ('.count($reduced).' komentar)');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        foreach ($reduced as $item) {
            $this->line("<fg=red>ID {$item['id']}</>");
            $this->line("Context: {$item['context']} (adjusted score: {$item['adjusted_score']})");
            $this->line('💬 '.mb_substr($item['text'], 0, 100).'...');
            $this->newLine();
        }
    }

    private function displayVerdict(array $stats): void
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('🎯 KESIMPULAN');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $fixedCount = count($stats['false_positives_fixed']);
        $remainingCount = count($stats['remaining_false_positives']);
        $totalFP = $fixedCount + $remainingCount;
        $fixRate = $totalFP > 0 ? round(($fixedCount / $totalFP) * 100, 1) : 0;

        $spamReducedCount = count($stats['legitimate_spam_reduced']);

        $this->line("Context Fixed: {$fixedCount}/{$totalFP} false positives ({$fixRate}%)");
        $this->line("Legitimate Spam Reduced: {$spamReducedCount} (BAD)");
        $this->newLine();

        if ($fixRate >= 70 && $spamReducedCount === 0) {
            $this->info('✅ ContextualAnalyzer working well!');
        } elseif ($fixRate >= 50) {
            $this->warn('⚠️  Context helps but needs improvement');
            if ($spamReducedCount > 0) {
                $this->line('   → Context incorrectly reducing spam scores');
            }
            if ($remainingCount > $fixedCount) {
                $this->line('   → Many false positives still not fixed by context');
            }
        } else {
            $this->error('❌ Context not effective enough');
            $this->line('   → Need better contextual patterns for Indonesian car reviews');
        }
    }
}
