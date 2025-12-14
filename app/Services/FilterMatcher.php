<?php

namespace App\Services;

use App\Models\Filter;
use Illuminate\Support\Collection;

class FilterMatcher
{
    /**
     * Find the first matching filter for the given text.
     */
    public function findMatch(string $text, Collection $filters): ?Filter
    {
        foreach ($filters as $filter) {
            if ($this->matches($text, $filter)) {
                return $filter;
            }
        }

        return null;
    }

    /**
     * Find all matching filters for the given text.
     */
    public function findAllMatches(string $text, Collection $filters): Collection
    {
        return $filters->filter(fn (Filter $filter) => $this->matches($text, $filter));
    }

    /**
     * Check if a filter matches the given text.
     */
    public function matches(string $text, Filter $filter): bool
    {
        $pattern = $filter->pattern;
        $searchText = $filter->case_sensitive ? $text : mb_strtolower($text);
        $searchPattern = $filter->case_sensitive ? $pattern : mb_strtolower($pattern);

        return match ($filter->type) {
            Filter::TYPE_KEYWORD, Filter::TYPE_PHRASE => $this->matchKeyword($searchText, $searchPattern, $filter->match_type),
            Filter::TYPE_REGEX => $this->matchRegex($text, $pattern, $filter->case_sensitive),
            Filter::TYPE_USERNAME => $this->matchUsername($searchText, $searchPattern),
            Filter::TYPE_URL => $this->matchUrl($searchText, $searchPattern),
            Filter::TYPE_EMOJI_SPAM => $this->matchEmojiSpam($text, (int) $pattern),
            Filter::TYPE_REPEAT_CHAR => $this->matchRepeatChar($text, (int) $pattern),
            default => false,
        };
    }

    /**
     * Match using keyword/phrase rules.
     */
    private function matchKeyword(string $text, string $pattern, string $matchType): bool
    {
        return match ($matchType) {
            Filter::MATCH_EXACT => $text === $pattern,
            Filter::MATCH_CONTAINS => str_contains($text, $pattern),
            Filter::MATCH_STARTS_WITH => str_starts_with($text, $pattern),
            Filter::MATCH_ENDS_WITH => str_ends_with($text, $pattern),
            default => false,
        };
    }

    /**
     * Match using regular expression.
     */
    private function matchRegex(string $text, string $pattern, bool $caseSensitive): bool
    {
        $flags = $caseSensitive ? '' : 'i';

        // Suppress errors in case of invalid regex
        return (bool) @preg_match("/{$pattern}/{$flags}u", $text);
    }

    /**
     * Match username patterns.
     */
    private function matchUsername(string $text, string $pattern): bool
    {
        // Username matching - typically contains match
        return str_contains($text, $pattern);
    }

    /**
     * Match URL patterns.
     */
    private function matchUrl(string $text, string $pattern): bool
    {
        // Simple URL pattern matching
        // Supports wildcards like *.domain.com or domain.com/*
        if (str_contains($pattern, '*')) {
            // Convert wildcard pattern to regex
            $regexPattern = str_replace(['*', '.'], ['.*', '\\.'], $pattern);

            return (bool) @preg_match("/{$regexPattern}/i", $text);
        }

        return str_contains($text, $pattern);
    }

    /**
     * Match excessive emoji usage.
     */
    private function matchEmojiSpam(string $text, int $threshold): bool
    {
        // Match common emoji Unicode ranges
        $emojiPattern = '/[\x{1F600}-\x{1F64F}]'.  // Emoticons
                        '|[\x{1F300}-\x{1F5FF}]'.  // Misc Symbols and Pictographs
                        '|[\x{1F680}-\x{1F6FF}]'.  // Transport and Map
                        '|[\x{1F1E0}-\x{1F1FF}]'.  // Flags
                        '|[\x{2600}-\x{26FF}]'.    // Misc symbols
                        '|[\x{2700}-\x{27BF}]'.    // Dingbats
                        '|[\x{FE00}-\x{FE0F}]'.    // Variation Selectors
                        '|[\x{1F900}-\x{1F9FF}]'.  // Supplemental Symbols and Pictographs
                        '|[\x{1FA00}-\x{1FA6F}]'.  // Chess Symbols
                        '|[\x{1FA70}-\x{1FAFF}]'.  // Symbols and Pictographs Extended-A
                        '|[\x{231A}-\x{231B}]'.    // Watch, Hourglass
                        '|[\x{23E9}-\x{23F3}]'.    // Some media controls
                        '|[\x{23F8}-\x{23FA}]/u';   // More media controls

        preg_match_all($emojiPattern, $text, $matches);

        return count($matches[0]) >= $threshold;
    }

    /**
     * Match repeated characters (e.g., "aaaaa" or "!!!!!").
     */
    private function matchRepeatChar(string $text, int $threshold): bool
    {
        // Match any character repeated N or more times consecutively
        return (bool) preg_match('/(.)\1{'.($threshold - 1).',}/u', $text);
    }

    /**
     * Test a pattern against sample text (for validation).
     */
    public function testPattern(string $type, string $pattern, string $matchType, bool $caseSensitive, string $testText): bool
    {
        $filter = new Filter([
            'type' => $type,
            'pattern' => $pattern,
            'match_type' => $matchType,
            'case_sensitive' => $caseSensitive,
        ]);

        return $this->matches($testText, $filter);
    }

    /**
     * Validate a regex pattern.
     */
    public function isValidRegex(string $pattern): bool
    {
        return @preg_match("/{$pattern}/u", '') !== false;
    }
}
