<?php

namespace App\Console\Commands;

use App\Services\SpamDetection\PatternAnalyzer;
use Illuminate\Console\Command;

class TestPatternAnalyzer extends Command
{
    protected $signature = 'test:pattern-analyzer';

    protected $description = 'Test PatternAnalyzer service with fixture data';

    public function handle(): int
    {
        $this->info('🧪 Testing PatternAnalyzer Service');
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

        $analyzer = new PatternAnalyzer();

        // Analyze all comments
        $stats = [
            'total' => count($comments),
            'spam' => 0,
            'clean' => 0,
            'detected' => [
                'money' => 0,
                'urgency' => 0,
                'links' => 0,
                'emoji' => 0,
                'caps' => 0,
            ],
            'false_positives' => [],
            'missed_spam' => [],
            'correct_detections' => [],
        ];

        foreach ($comments as $comment) {
            if (empty($comment['text'])) {
                continue;
            }

            $expected = $comment['expected_result'] ?? 'unknown';
            $isSpam = $expected === 'spam';

            if ($isSpam) {
                $stats['spam']++;
            } else {
                $stats['clean']++;
            }

            $result = $analyzer->analyzePatterns($comment['text']);
            $hasAnySignal = ! empty($result['spam_signals']);

            // Count signal types
            if ($result['has_money']) {
                $stats['detected']['money']++;
            }
            if ($result['has_urgency']) {
                $stats['detected']['urgency']++;
            }
            if ($result['has_link_promotion']) {
                $stats['detected']['links']++;
            }
            if ($result['emoji_density'] > 0.15) {
                $stats['detected']['emoji']++;
            }
            if ($result['caps_ratio'] > 0.5) {
                $stats['detected']['caps']++;
            }

            // Check accuracy
            if ($hasAnySignal && $isSpam) {
                // Correct detection
                $stats['correct_detections'][] = [
                    'id' => $comment['id'],
                    'text' => $comment['text'],
                    'signals' => $result['spam_signals'],
                ];
            } elseif ($hasAnySignal && ! $isSpam) {
                // False positive
                $details = $analyzer->getDetailedPatterns($comment['text']);
                $stats['false_positives'][] = [
                    'id' => $comment['id'],
                    'text' => $comment['text'],
                    'signals' => $result['spam_signals'],
                    'details' => $details,
                ];
            } elseif (! $hasAnySignal && $isSpam) {
                // Missed spam
                $stats['missed_spam'][] = [
                    'id' => $comment['id'],
                    'text' => $comment['text'],
                    'reason' => $comment['reason'] ?? 'unknown',
                ];
            }
        }

        // Display summary
        $this->displaySummary($stats);
        $this->newLine();

        // Display correct detections
        if (! empty($stats['correct_detections'])) {
            $this->displayCorrectDetections($stats['correct_detections']);
            $this->newLine();
        }

        // Display false positives
        if (! empty($stats['false_positives'])) {
            $this->displayFalsePositives($stats['false_positives']);
            $this->newLine();
        }

        // Display missed spam
        if (! empty($stats['missed_spam'])) {
            $this->displayMissedSpam($stats['missed_spam']);
            $this->newLine();
        }

        // Final verdict
        $this->displayVerdict($stats);

        return 0;
    }

    private function displaySummary(array $stats): void
    {
        $this->info('📊 RINGKASAN');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $this->line("Total Komentar: {$stats['total']}");
        $this->line("├─ Clean: {$stats['clean']}");
        $this->line("└─ Spam: {$stats['spam']}");
        $this->newLine();

        $this->line('Deteksi Per Pattern:');
        $this->line("├─ Money keywords: {$stats['detected']['money']}");
        $this->line("├─ Urgency language: {$stats['detected']['urgency']}");
        $this->line("├─ Link promotion: {$stats['detected']['links']}");
        $this->line("├─ High emoji (>15%): {$stats['detected']['emoji']}");
        $this->line("└─ High CAPS (>50%): {$stats['detected']['caps']}");
        $this->newLine();

        $correctCount = count($stats['correct_detections']);
        $fpCount = count($stats['false_positives']);
        $missedCount = count($stats['missed_spam']);

        $this->line('Akurasi:');
        $this->info("✅ Correct: {$correctCount}");
        $this->error("❌ False Positives: {$fpCount}");
        $this->error("⚠️  Missed Spam: {$missedCount}");
    }

    private function displayCorrectDetections(array $detections): void
    {
        $this->info('✅ SPAM TERDETEKSI DENGAN BENAR ('.count($detections).' komentar)');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        foreach (array_slice($detections, 0, 5) as $item) {
            $this->line("<fg=green>ID {$item['id']}</>");
            $this->line('Signals: '.implode(', ', $item['signals']));
            $this->line('💬 '.mb_substr($item['text'], 0, 100).'...');
            $this->newLine();
        }

        if (count($detections) > 5) {
            $remaining = count($detections) - 5;
            $this->line("<fg=gray>... dan {$remaining} komentar lainnya</>");
        }
    }

    private function displayFalsePositives(array $fps): void
    {
        $this->error('❌ FALSE POSITIVES ('.count($fps).' komentar)');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        foreach (array_slice($fps, 0, 10) as $item) {
            $this->line("<fg=yellow>ID {$item['id']}</>");
            $this->line('Signals: '.implode(', ', $item['signals']));

            // Show which keywords triggered
            if (! empty($item['details']['money'])) {
                $this->line('  Money: '.implode(', ', $item['details']['money']));
            }
            if (! empty($item['details']['urgency'])) {
                $this->line('  Urgency: '.implode(', ', $item['details']['urgency']));
            }
            if (! empty($item['details']['links'])) {
                $this->line('  Links: '.implode(', ', $item['details']['links']));
            }

            $this->line('💬 '.mb_substr($item['text'], 0, 100).'...');
            $this->newLine();
        }

        if (count($fps) > 10) {
            $remaining = count($fps) - 10;
            $this->line("<fg=gray>... dan {$remaining} komentar lainnya</>");
        }
    }

    private function displayMissedSpam(array $missed): void
    {
        $this->error('⚠️  SPAM TIDAK TERDETEKSI ('.count($missed).' komentar)');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        foreach (array_slice($missed, 0, 5) as $item) {
            $this->line("<fg=red>ID {$item['id']}</>");
            $this->line('Reason: '.$item['reason']);
            $this->line('💬 '.mb_substr($item['text'], 0, 100).'...');
            $this->newLine();
        }

        if (count($missed) > 5) {
            $remaining = count($missed) - 5;
            $this->line("<fg=gray>... dan {$remaining} komentar lainnya</>");
        }
    }

    private function displayVerdict(array $stats): void
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('🎯 KESIMPULAN');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $totalSpam = $stats['spam'];
        $detected = count($stats['correct_detections']);
        $detectionRate = $totalSpam > 0 ? round(($detected / $totalSpam) * 100, 1) : 0;

        $totalClean = $stats['clean'];
        $falsePositives = count($stats['false_positives']);
        $fpRate = $totalClean > 0 ? round(($falsePositives / $totalClean) * 100, 1) : 0;

        $this->line("Detection Rate: {$detected}/{$totalSpam} ({$detectionRate}%)");
        $this->line("False Positive Rate: {$falsePositives}/{$totalClean} ({$fpRate}%)");
        $this->newLine();

        // Issues and recommendations
        if ($detectionRate < 50) {
            $this->warn('⚠️  Detection rate rendah! PatternAnalyzer hanya mendeteksi '.number_format($detectionRate, 1).'% spam');
            $this->line('   → Kebanyakan spam menggunakan Unicode fancy fonts (ditangani UnicodeDetector)');
            $this->line('   → PatternAnalyzer fokus pada money/urgency/link patterns');
        }

        if ($fpRate > 10) {
            $this->warn('⚠️  False positive rate tinggi! '.number_format($fpRate, 1).'% komentar clean salah deteksi');
            $this->line('   → Substring matching problem (rp in "terperfect", rb in "terbaik")');
            $this->line('   → Contextual words (gacor, harga, uang) legitimate dalam car review');
            $this->line('   → Fix: Gunakan word boundary checks atau ContextualAnalyzer');
        }

        if ($detectionRate >= 50 && $fpRate <= 10) {
            $this->info('✅ PatternAnalyzer performance acceptable!');
        }
    }
}
