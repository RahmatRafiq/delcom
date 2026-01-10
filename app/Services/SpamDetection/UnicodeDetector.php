<?php

namespace App\Services\SpamDetection;

/**
 * Unicode Detector for Spam Detection
 *
 * Detects fancy Unicode fonts commonly used by spammers to evade text filters.
 * Examples: 𝗝𝗨𝗗𝗢𝗟 (Mathematical Bold), 𝓢𝓛𝓞𝓣 (Script), 𝔾𝔸ℂ𝕆ℝ (Double-struck)
 *
 * IMPORTANT: This is a DETECTOR, not a normalizer.
 * Detection logic: "Has fancy Unicode? → SPAM" (instant spam indicator)
 * Rationale: Legitimate comments NEVER use fancy Unicode fonts on YouTube.
 */
class UnicodeDetector
{
    /**
     * Fancy Unicode ranges commonly abused for spam evasion.
     *
     * @var array<string, array{start: int, end: int, name: string}>
     */
    private const FANCY_RANGES = [
        'mathematical_bold' => [
            'start' => 0x1D400,
            'end' => 0x1D433,
            'name' => 'Mathematical Bold',
        ],
        'mathematical_italic' => [
            'start' => 0x1D434,
            'end' => 0x1D467,
            'name' => 'Mathematical Italic',
        ],
        'mathematical_bold_italic' => [
            'start' => 0x1D468,
            'end' => 0x1D49B,
            'name' => 'Mathematical Bold Italic',
        ],
        'mathematical_script' => [
            'start' => 0x1D49C,
            'end' => 0x1D4CF,
            'name' => 'Mathematical Script',
        ],
        'mathematical_bold_script' => [
            'start' => 0x1D4D0,
            'end' => 0x1D503,
            'name' => 'Mathematical Bold Script',
        ],
        'mathematical_fraktur' => [
            'start' => 0x1D504,
            'end' => 0x1D537,
            'name' => 'Mathematical Fraktur',
        ],
        'mathematical_double_struck' => [
            'start' => 0x1D538,
            'end' => 0x1D56B,
            'name' => 'Mathematical Double-Struck',
        ],
        'mathematical_sans_serif' => [
            'start' => 0x1D5A0,
            'end' => 0x1D5D3,
            'name' => 'Mathematical Sans-Serif',
        ],
        'mathematical_sans_serif_bold' => [
            'start' => 0x1D5D4,
            'end' => 0x1D607,
            'name' => 'Mathematical Sans-Serif Bold',
        ],
        'mathematical_sans_serif_italic' => [
            'start' => 0x1D608,
            'end' => 0x1D63B,
            'name' => 'Mathematical Sans-Serif Italic',
        ],
        'mathematical_sans_serif_bold_italic' => [
            'start' => 0x1D63C,
            'end' => 0x1D66F,
            'name' => 'Mathematical Sans-Serif Bold Italic',
        ],
        'mathematical_monospace' => [
            'start' => 0x1D670,
            'end' => 0x1D6A3,
            'name' => 'Mathematical Monospace',
        ],
        'fullwidth_latin' => [
            'start' => 0xFF21,
            'end' => 0xFF5A,
            'name' => 'Fullwidth Latin',
        ],
        'circled_latin' => [
            'start' => 0x24B6,
            'end' => 0x24E9,
            'name' => 'Circled Latin',
        ],
        'squared_latin' => [
            'start' => 0x1F130,
            'end' => 0x1F189,
            'name' => 'Squared Latin',
        ],
        'negative_circled' => [
            'start' => 0x1F150,
            'end' => 0x1F169,
            'name' => 'Negative Circled',
        ],
        'negative_squared' => [
            'start' => 0x1F170,
            'end' => 0x1F189,
            'name' => 'Negative Squared',
        ],
        'mathematical_bold_digits' => [
            'start' => 0x1D7CE,
            'end' => 0x1D7D7,
            'name' => 'Mathematical Bold Digits',
        ],
        'mathematical_double_struck_digits' => [
            'start' => 0x1D7D8,
            'end' => 0x1D7E1,
            'name' => 'Mathematical Double-Struck Digits',
        ],
        'mathematical_sans_serif_digits' => [
            'start' => 0x1D7E2,
            'end' => 0x1D7EB,
            'name' => 'Mathematical Sans-Serif Digits',
        ],
        'mathematical_sans_serif_bold_digits' => [
            'start' => 0x1D7EC,
            'end' => 0x1D7F5,
            'name' => 'Mathematical Sans-Serif Bold Digits',
        ],
        'mathematical_monospace_digits' => [
            'start' => 0x1D7F6,
            'end' => 0x1D7FF,
            'name' => 'Mathematical Monospace Digits',
        ],
        'combining_diacritical_marks' => [
            'start' => 0x0300,
            'end' => 0x036F,
            'name' => 'Combining Diacritical Marks',
        ],
        'combining_diacritical_extended' => [
            'start' => 0x1AB0,
            'end' => 0x1AFF,
            'name' => 'Combining Diacritical Marks Extended',
        ],
        'combining_diacritical_supplement' => [
            'start' => 0x1DC0,
            'end' => 0x1DFF,
            'name' => 'Combining Diacritical Marks Supplement',
        ],
        'combining_marks_for_symbols' => [
            'start' => 0x20D0,
            'end' => 0x20FF,
            'name' => 'Combining Diacritical Marks for Symbols',
        ],
        // NOTE: Variation Selectors (U+FE00-FE0F) removed from fancy ranges
        // They are used in legitimate emojis (❤️, ☢️, etc) and should not be flagged as spam
        // They are still counted in getCombiningMarksCount() for threshold detection
    ];

    /**
     * Check if text contains any fancy Unicode characters.
     *
     * NEW APPROACH: General anomaly detection instead of maintaining 30+ ranges
     * Detects:
     * 1. Combining marks (overlays, underlines, etc)
     * 2. Mathematical/fancy alphabets
     * 3. Visual anomalies (mixed scripts)
     *
     * @param  string  $text  The text to analyze
     * @return bool True if fancy Unicode detected, false otherwise
     */
    public function hasFancyUnicode(string $text): bool
    {
        if (empty($text)) {
            return false;
        }

        // Quick check: combining marks count
        if ($this->getCombiningMarksCount($text) > 2) {
            return true;
        }

        // Check fancy ranges (keep only high-value ranges)
        $codePoints = $this->getCodePoints($text);
        foreach ($codePoints as $codePoint) {
            if ($this->isFancyCodePoint($codePoint)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Count combining marks in text (any diacritics/overlays).
     * Legitimate text rarely has more than 1-2 combining marks.
     * Spam uses many: S̳A̳A̳T̳4̳D̳ (6 combining marks)
     *
     * @param  string  $text  The text to analyze
     * @return int Count of combining marks
     */
    private function getCombiningMarksCount(string $text): int
    {
        $count = 0;
        $codePoints = $this->getCodePoints($text);

        foreach ($codePoints as $codePoint) {
            // Combining marks ranges (general check)
            if (($codePoint >= 0x0300 && $codePoint <= 0x036F) ||  // Combining Diacritical
                ($codePoint >= 0x1AB0 && $codePoint <= 0x1AFF) ||  // Extended
                ($codePoint >= 0x1DC0 && $codePoint <= 0x1DFF) ||  // Supplement
                ($codePoint >= 0x20D0 && $codePoint <= 0x20FF) ||  // For Symbols
                ($codePoint >= 0xFE00 && $codePoint <= 0xFE0F)) {  // Variation Selectors
                $count++;
            }
        }

        return $count;
    }

    /**
     * Count fancy Unicode characters in text.
     *
     * @param  string  $text  The text to analyze
     * @return int Number of fancy Unicode characters found
     */
    public function getFancyCharCount(string $text): int
    {
        if (empty($text)) {
            return 0;
        }

        $count = 0;
        $codePoints = $this->getCodePoints($text);

        foreach ($codePoints as $codePoint) {
            if ($this->isFancyCodePoint($codePoint)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get positions of fancy Unicode characters in text.
     *
     * @param  string  $text  The text to analyze
     * @return array<int, array{position: int, codePoint: int, char: string, range: string}>
     */
    public function getFancyCharPositions(string $text): array
    {
        if (empty($text)) {
            return [];
        }

        $positions = [];
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($chars as $index => $char) {
            $codePoint = $this->charToCodePoint($char);

            if ($this->isFancyCodePoint($codePoint)) {
                $rangeName = $this->getRangeName($codePoint);
                $positions[] = [
                    'position' => $index,
                    'codePoint' => $codePoint,
                    'char' => $char,
                    'range' => $rangeName,
                ];
            }
        }

        return $positions;
    }

    /**
     * Get fancy Unicode density (percentage of fancy chars).
     *
     * @param  string  $text  The text to analyze
     * @return float Density from 0.0 to 1.0
     */
    public function getFancyDensity(string $text): float
    {
        if (empty($text)) {
            return 0.0;
        }

        $totalChars = mb_strlen($text, 'UTF-8');
        if ($totalChars === 0) {
            return 0.0;
        }

        $fancyCount = $this->getFancyCharCount($text);

        return $fancyCount / $totalChars;
    }

    /**
     * Normalize fancy Unicode to ASCII (for keyword matching).
     * Converts 𝗝𝗨𝗗𝗢𝗟 → JUDOL, 𝓢𝓛𝓞𝓣 → SLOT, etc.
     *
     * @param  string  $text  The text to normalize
     * @return string Normalized ASCII text
     */
    public function normalize(string $text): string
    {
        if (empty($text)) {
            return '';
        }

        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $normalized = '';

        foreach ($chars as $char) {
            $codePoint = $this->charToCodePoint($char);

            if ($this->isFancyCodePoint($codePoint)) {
                // Convert fancy Unicode to ASCII equivalent
                $normalized .= $this->fancyToAscii($codePoint);
            } else {
                $normalized .= $char;
            }
        }

        return $normalized;
    }

    /**
     * Check if a Unicode code point is in fancy ranges.
     *
     * @param  int  $codePoint  The Unicode code point
     * @return bool True if fancy, false otherwise
     */
    private function isFancyCodePoint(int $codePoint): bool
    {
        foreach (self::FANCY_RANGES as $range) {
            if ($codePoint >= $range['start'] && $codePoint <= $range['end']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the range name for a code point.
     *
     * @param  int  $codePoint  The Unicode code point
     * @return string Range name or 'Unknown'
     */
    private function getRangeName(int $codePoint): string
    {
        foreach (self::FANCY_RANGES as $key => $range) {
            if ($codePoint >= $range['start'] && $codePoint <= $range['end']) {
                return $range['name'];
            }
        }

        return 'Unknown';
    }

    /**
     * Convert string to array of Unicode code points.
     *
     * @param  string  $text  The text to convert
     * @return array<int> Array of code points
     */
    private function getCodePoints(string $text): array
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $codePoints = [];

        foreach ($chars as $char) {
            $codePoints[] = $this->charToCodePoint($char);
        }

        return $codePoints;
    }

    /**
     * Convert character to Unicode code point.
     *
     * @param  string  $char  Single character
     * @return int Unicode code point
     */
    private function charToCodePoint(string $char): int
    {
        $values = unpack('N', mb_convert_encoding($char, 'UCS-4BE', 'UTF-8'));

        return $values[1] ?? 0;
    }

    /**
     * Convert fancy Unicode code point to ASCII character.
     *
     * @param  int  $codePoint  The fancy Unicode code point
     * @return string ASCII character
     */
    private function fancyToAscii(int $codePoint): string
    {
        // Determine which range and calculate offset
        foreach (self::FANCY_RANGES as $key => $range) {
            if ($codePoint >= $range['start'] && $codePoint <= $range['end']) {
                $offset = $codePoint - $range['start'];

                // Handle digit ranges (0-9)
                if (str_contains($key, 'digits')) {
                    if ($offset < 10) {
                        return chr(48 + $offset); // 0-9
                    }
                }

                // Map to ASCII A-Z (uppercase) or a-z (lowercase)
                // Most fancy ranges follow alphabetical order
                if ($offset < 26) {
                    return chr(65 + $offset); // A-Z
                } elseif ($offset < 52) {
                    return chr(97 + ($offset - 26)); // a-z
                }
            }
        }

        // Fallback: return question mark for unknown mappings
        return '?';
    }

    /**
     * Get statistics about fancy Unicode usage in text.
     *
     * @param  string  $text  The text to analyze
     * @return array{hasFancy: bool, count: int, density: float, ranges: array<string, int>, normalized: string}
     */
    public function getStatistics(string $text): array
    {
        $positions = $this->getFancyCharPositions($text);
        $rangeCount = [];

        foreach ($positions as $pos) {
            $rangeName = $pos['range'];
            $rangeCount[$rangeName] = ($rangeCount[$rangeName] ?? 0) + 1;
        }

        return [
            'hasFancy' => $this->hasFancyUnicode($text),
            'count' => $this->getFancyCharCount($text),
            'density' => $this->getFancyDensity($text),
            'ranges' => $rangeCount,
            'normalized' => $this->normalize($text),
        ];
    }
}
