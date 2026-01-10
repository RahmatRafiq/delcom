<?php

namespace App\Services\SpamDetection;

/**
 * Fuzzy Matcher for Spam Detection
 *
 * Handles obfuscated spam variations using Levenshtein distance and normalization.
 * Examples:
 * - j.u.d.o.l → judol
 * - jud0l → judol
 * - j u d o l → judol
 * - JVDOL → judol
 *
 * Use cases:
 * 1. Detect leet speak variations (0 → o, 1 → i, 3 → e)
 * 2. Remove separator obfuscation (dots, spaces, dashes)
 * 3. Handle visual similarity (V → U, 0 → O)
 */
class FuzzyMatcher
{
    /**
     * Maximum Levenshtein distance to consider a match.
     * Distance 2 allows for: 1 character substitution + 1 insertion/deletion.
     */
    private const MAX_DISTANCE = 2;

    /**
     * Leet speak character mappings.
     *
     * @var array<string, string>
     */
    private const LEET_MAPPINGS = [
        '0' => 'o',
        '1' => 'i',
        '3' => 'e',
        '4' => 'a',
        '5' => 's',
        '7' => 't',
        '8' => 'b',
        '@' => 'a',
        '$' => 's',
    ];

    /**
     * Visual similarity character mappings (uppercase).
     *
     * @var array<string, string>
     */
    private const VISUAL_MAPPINGS = [
        'V' => 'U',
        'O' => '0',
        'I' => '1',
        'Z' => '2',
        'E' => '3',
        'A' => '4',
        'S' => '5',
        'G' => '6',
        'T' => '7',
        'B' => '8',
    ];

    /**
     * Check if two strings are similar within the distance threshold.
     *
     * @param  string  $text1  First string to compare
     * @param  string  $text2  Second string to compare
     * @param  int|null  $maxDistance  Optional custom max distance (default: 2)
     * @return bool True if similar, false otherwise
     */
    public function isSimilar(string $text1, string $text2, ?int $maxDistance = null): bool
    {
        $maxDistance = $maxDistance ?? self::MAX_DISTANCE;

        // Normalize both strings
        $normalized1 = $this->normalize($text1);
        $normalized2 = $this->normalize($text2);

        // Calculate distance using safe method (handles 255+ chars)
        $distance = $this->calculateDistance($normalized1, $normalized2);

        return $distance >= 0 && $distance <= $maxDistance;
    }

    /**
     * Normalize text by removing obfuscation.
     * Converts: j.u.d.o.l → judol, jud0l → judol, J U D O L → judol
     *
     * @param  string  $text  Text to normalize
     * @return string Normalized text
     */
    public function normalize(string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // Step 1: Lowercase
        $normalized = mb_strtolower($text, 'UTF-8');

        // Step 2: Remove common separators (dots, spaces, dashes, underscores)
        $normalized = str_replace(['.', ' ', '-', '_', '|', '*', '+', '/', '\\'], '', $normalized);

        // Step 3: Replace leet speak characters
        $normalized = strtr($normalized, self::LEET_MAPPINGS);

        // Step 4: Remove non-alphanumeric characters (keeping only a-z, 0-9)
        $normalized = preg_replace('/[^a-z0-9]/u', '', $normalized);

        return $normalized;
    }

    /**
     * Find the best matching keyword from a list.
     *
     * @param  string  $text  Text to match against
     * @param  array<string>  $keywords  List of keywords to check
     * @param  int|null  $maxDistance  Optional custom max distance
     * @return array{match: string|null, distance: int|null, normalized: string}
     */
    public function findBestMatch(string $text, array $keywords, ?int $maxDistance = null): array
    {
        $maxDistance = $maxDistance ?? self::MAX_DISTANCE;
        $normalizedText = $this->normalize($text);

        $bestMatch = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($keywords as $keyword) {
            $normalizedKeyword = $this->normalize($keyword);

            // Skip if keyword is empty after normalization
            if (empty($normalizedKeyword)) {
                continue;
            }

            // Calculate distance using safe method
            $distance = $this->calculateDistance($normalizedText, $normalizedKeyword);

            // Update best match if this is closer
            if ($distance >= 0 && $distance < $bestDistance) {
                $bestDistance = $distance;
                $bestMatch = $keyword;
            }
        }

        // Return null if no match within threshold
        if ($bestDistance > $maxDistance) {
            return [
                'match' => null,
                'distance' => null,
                'normalized' => $normalizedText,
            ];
        }

        return [
            'match' => $bestMatch,
            'distance' => $bestDistance,
            'normalized' => $normalizedText,
        ];
    }

    /**
     * Check if text contains any fuzzy match of keywords.
     *
     * @param  string  $text  Text to check
     * @param  array<string>  $keywords  Keywords to match against
     * @param  int|null  $maxDistance  Optional custom max distance
     * @return bool True if any fuzzy match found
     */
    public function containsFuzzyMatch(string $text, array $keywords, ?int $maxDistance = null): bool
    {
        $maxDistance = $maxDistance ?? self::MAX_DISTANCE;

        // Split text into words
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($words as $word) {
            $result = $this->findBestMatch($word, $keywords, $maxDistance);

            if ($result['match'] !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find all fuzzy matches in text.
     *
     * @param  string  $text  Text to analyze
     * @param  array<string>  $keywords  Keywords to match against
     * @param  int|null  $maxDistance  Optional custom max distance
     * @return array<int, array{word: string, match: string, distance: int, position: int}>
     */
    public function findAllMatches(string $text, array $keywords, ?int $maxDistance = null): array
    {
        $maxDistance = $maxDistance ?? self::MAX_DISTANCE;
        $matches = [];

        // Split text into words while preserving positions
        preg_match_all('/\S+/u', $text, $wordMatches, PREG_OFFSET_CAPTURE);

        foreach ($wordMatches[0] as $wordMatch) {
            [$word, $position] = $wordMatch;

            $result = $this->findBestMatch($word, $keywords, $maxDistance);

            if ($result['match'] !== null) {
                $matches[] = [
                    'word' => $word,
                    'match' => $result['match'],
                    'distance' => $result['distance'],
                    'position' => $position,
                ];
            }
        }

        return $matches;
    }

    /**
     * Get fuzzy match statistics for text.
     *
     * @param  string  $text  Text to analyze
     * @param  array<string>  $keywords  Keywords to match against
     * @param  int|null  $maxDistance  Optional custom max distance
     * @return array{hasMatch: bool, matchCount: int, totalWords: int, matches: array, confidence: float}
     */
    public function getStatistics(string $text, array $keywords, ?int $maxDistance = null): array
    {
        $matches = $this->findAllMatches($text, $keywords, $maxDistance);

        // Count total words
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $totalWords = count($words);

        // Calculate confidence based on match count and distances
        $confidence = 0.0;

        if (! empty($matches)) {
            $avgDistance = array_sum(array_column($matches, 'distance')) / count($matches);

            // Lower distance = higher confidence
            // Distance 0 = 100%, Distance 1 = 75%, Distance 2 = 50%
            $distanceScore = max(0, 1 - ($avgDistance / (self::MAX_DISTANCE * 2)));

            // More matches = higher confidence
            $matchRatio = min(1, count($matches) / max(1, $totalWords));

            // Combined confidence (weighted average)
            $confidence = ($distanceScore * 0.7) + ($matchRatio * 0.3);
        }

        return [
            'hasMatch' => ! empty($matches),
            'matchCount' => count($matches),
            'totalWords' => $totalWords,
            'matches' => $matches,
            'confidence' => round($confidence, 2),
        ];
    }

    /**
     * Get Levenshtein distance between two strings.
     * Public wrapper for testing/debugging.
     *
     * @param  string  $text1  First string
     * @param  string  $text2  Second string
     * @return int Distance value
     */
    public function getDistance(string $text1, string $text2): int
    {
        return $this->calculateDistance($this->normalize($text1), $this->normalize($text2));
    }

    /**
     * Calculate distance between two strings (handles 255+ chars).
     *
     * Uses levenshtein for short strings (<= 255 chars).
     * Uses similar_text for long strings (> 255 chars).
     *
     * @param  string  $text1  First string
     * @param  string  $text2  Second string
     * @return int Distance value
     */
    private function calculateDistance(string $text1, string $text2): int
    {
        $len1 = mb_strlen($text1, 'UTF-8');
        $len2 = mb_strlen($text2, 'UTF-8');

        // Use levenshtein for strings <= 255 chars
        if ($len1 <= 255 && $len2 <= 255) {
            $distance = levenshtein($text1, $text2);

            if ($distance === -1) {
                // Fallback if levenshtein fails
                return $this->calculateDistanceFallback($text1, $text2);
            }

            return $distance;
        }

        // For long strings, use alternative algorithm
        return $this->calculateDistanceFallback($text1, $text2);
    }

    /**
     * Fallback distance calculation for long strings.
     *
     * Uses similar_text converted to distance metric.
     *
     * @param  string  $text1  First string
     * @param  string  $text2  Second string
     * @return int Approximate distance
     */
    private function calculateDistanceFallback(string $text1, string $text2): int
    {
        if ($text1 === $text2) {
            return 0;
        }

        $maxLen = max(mb_strlen($text1, 'UTF-8'), mb_strlen($text2, 'UTF-8'));

        if ($maxLen === 0) {
            return 0;
        }

        // Get similarity percentage
        similar_text($text1, $text2, $percent);

        // Convert to distance (0% similar = maxLen distance, 100% similar = 0 distance)
        return (int) round($maxLen * (1 - ($percent / 100)));
    }

    /**
     * Get maximum allowed distance.
     *
     * @return int Max distance threshold
     */
    public function getMaxDistance(): int
    {
        return self::MAX_DISTANCE;
    }
}
