<?php

namespace App\Services;

use App\Models\Filter;
use App\Services\AI\SpamAnalysisResult;
use App\Services\AI\SpamDetectionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class HybridSpamDetector
{
    private FilterMatcher $filterMatcher;

    private ?SpamDetectionService $aiService = null;

    public function __construct(FilterMatcher $filterMatcher)
    {
        $this->filterMatcher = $filterMatcher;

        if (SpamDetectionService::isEnabled()) {
            $this->aiService = new SpamDetectionService;
        }
    }

    /**
     * Analyze comments using both regex filters and AI.
     *
     * Strategy:
     * 1. First pass: Check against user's regex filters (fast, free)
     * 2. Second pass: Use AI for unmatched comments (slower, costs money)
     * 3. Combine results with metadata about detection method
     *
     * @param  array  $comments  Array of ['id' => string, 'text' => string, ...]
     * @param  Collection  $filters  User's filter rules
     * @param  array  $options  Detection options
     * @return array Array of detection results
     */
    public function analyzeComments(array $comments, Collection $filters, array $options = []): array
    {
        $results = [];
        $useAI = $options['use_ai'] ?? ($this->aiService !== null);
        $aiOnly = $options['ai_only'] ?? false;
        $context = $options['context'] ?? [];

        // Separate comments that need AI analysis
        $needsAI = [];

        foreach ($comments as $index => $comment) {
            $text = $comment['text'] ?? '';
            $commentId = $comment['id'] ?? $index;

            // Skip if AI-only mode
            if (! $aiOnly) {
                // First: Try regex/filter matching
                $matchedFilter = $this->filterMatcher->findMatch($text, $filters);

                if ($matchedFilter) {
                    $results[$commentId] = [
                        'id' => $commentId,
                        'is_spam' => true,
                        'confidence' => 1.0,
                        'detection_method' => 'filter',
                        'matched_filter_id' => $matchedFilter->id,
                        'matched_filter_name' => $matchedFilter->name,
                        'reason' => "Matched filter: {$matchedFilter->name}",
                        'categories' => [$this->filterTypeToCategory($matchedFilter->type)],
                        'action' => $matchedFilter->action,
                    ];

                    continue;
                }
            }

            // Mark for AI analysis if enabled
            if ($useAI && $this->aiService) {
                $needsAI[$commentId] = $comment;
            } else {
                // No match, no AI - mark as not spam
                $results[$commentId] = [
                    'id' => $commentId,
                    'is_spam' => false,
                    'confidence' => 0,
                    'detection_method' => 'none',
                    'reason' => 'No filter match',
                    'categories' => [],
                    'action' => null,
                ];
            }
        }

        // Second pass: AI analysis for remaining comments
        if (! empty($needsAI) && $this->aiService) {
            try {
                $aiResults = $this->aiService->analyzeBatch(
                    array_values($needsAI),
                    $context
                );

                $commentIds = array_keys($needsAI);
                foreach ($aiResults as $idx => $aiResult) {
                    $commentId = $commentIds[$idx] ?? $idx;
                    $comment = $needsAI[$commentId] ?? null;

                    $results[$commentId] = [
                        'id' => $commentId,
                        'is_spam' => $aiResult->isSpam,
                        'confidence' => $aiResult->confidence,
                        'detection_method' => 'ai',
                        'reason' => $aiResult->reason,
                        'categories' => $aiResult->categories,
                        'action' => $aiResult->meetsThreshold() ? 'review' : null, // AI detections go to review
                        'ai_error' => $aiResult->error,
                    ];
                }
            } catch (\Exception $e) {
                Log::error('Hybrid detector AI analysis failed', [
                    'error' => $e->getMessage(),
                    'comment_count' => count($needsAI),
                ]);

                // Fallback: mark as not spam with error
                foreach (array_keys($needsAI) as $commentId) {
                    $results[$commentId] = [
                        'id' => $commentId,
                        'is_spam' => false,
                        'confidence' => 0,
                        'detection_method' => 'error',
                        'reason' => 'AI analysis failed',
                        'categories' => [],
                        'action' => null,
                        'error' => true,
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Quick check using only regex filters.
     */
    public function quickCheck(string $text, Collection $filters): ?Filter
    {
        return $this->filterMatcher->findMatch($text, $filters);
    }

    /**
     * AI-only check (skip regex).
     */
    public function aiCheck(string $text, array $context = []): ?SpamAnalysisResult
    {
        if (! $this->aiService) {
            return null;
        }

        return $this->aiService->analyzeComment($text, $context);
    }

    /**
     * Check if AI detection is available.
     */
    public function isAIEnabled(): bool
    {
        return $this->aiService !== null;
    }

    /**
     * Map filter type to spam category.
     */
    private function filterTypeToCategory(string $filterType): string
    {
        return match ($filterType) {
            Filter::TYPE_KEYWORD, Filter::TYPE_PHRASE => 'KEYWORD',
            Filter::TYPE_REGEX => 'PATTERN',
            Filter::TYPE_USERNAME => 'USER',
            Filter::TYPE_URL => 'PHISHING',
            Filter::TYPE_EMOJI_SPAM => 'SPAM_PATTERN',
            Filter::TYPE_REPEAT_CHAR => 'SPAM_PATTERN',
            default => 'OTHER',
        };
    }

    /**
     * Get statistics about detection capabilities.
     */
    public function getCapabilities(): array
    {
        return [
            'regex_enabled' => true,
            'ai_enabled' => $this->isAIEnabled(),
            'ai_provider' => $this->isAIEnabled() ? config('services.ai.provider') : null,
            'ai_model' => $this->isAIEnabled() ? config('services.ai.model') : null,
            'confidence_threshold' => $this->aiService?->getConfidenceThreshold() ?? 0.7,
        ];
    }
}
