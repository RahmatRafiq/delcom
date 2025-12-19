<?php

namespace App\Services\AI;

class SpamAnalysisResult
{
    public function __construct(
        public readonly bool $isSpam,
        public readonly float $confidence,
        public readonly string $reason,
        public readonly array $categories,
        public readonly bool $error = false
    ) {}

    /**
     * Check if the confidence meets the threshold.
     */
    public function meetsThreshold(float $threshold = 0.7): bool
    {
        return $this->isSpam && $this->confidence >= $threshold;
    }

    /**
     * Get the primary category.
     */
    public function getPrimaryCategory(): ?string
    {
        return $this->categories[0] ?? null;
    }

    /**
     * Check if a specific category is present.
     */
    public function hasCategory(string $category): bool
    {
        return in_array(strtoupper($category), array_map('strtoupper', $this->categories));
    }

    /**
     * Convert to array for API responses.
     */
    public function toArray(): array
    {
        return [
            'is_spam' => $this->isSpam,
            'confidence' => $this->confidence,
            'reason' => $this->reason,
            'categories' => $this->categories,
            'error' => $this->error,
        ];
    }
}
