<?php

namespace App\Services\SpamDetection;

/**
 * Pattern Analyzer for Spam Detection
 *
 * Analyzes text patterns to identify spam signals:
 * - Money mentions (amount, currency, profit)
 * - Urgency language (sekarang, cepat, buruan)
 * - Link promotion (klik, bio, link)
 * - Emoji density (spam uses excessive emojis)
 * - Caps ratio (SHOUTING = spam indicator)
 */
class PatternAnalyzer
{
    /**
     * Money-related keywords (Indonesian context).
     *
     * @var array<string>
     */
    private const MONEY_KEYWORDS = [
        // Currency
        'jt', 'juta', 'rb', 'ribu', 'rp', 'rupiah', 'dollar', 'usd',

        // Amounts
        'jutaan', 'ratusan', 'puluhan', 'milyar', 'miliar',

        // Money actions
        'wd', 'withdraw', 'profit', 'untung', 'cuan', 'duit',
        'uang', 'modal', 'deposit', 'bayar', 'transfer',

        // Gambling
        'jackpot', 'maxwin', 'bilek', 'gacor', 'scatter',
    ];

    /**
     * Urgency keywords (create FOMO).
     *
     * @var array<string>
     */
    private const URGENCY_KEYWORDS = [
        // Time pressure
        'sekarang', 'hari ini', 'buruan', 'cepat', 'segera',
        'jangan sampai', 'keburu', 'limited', 'terbatas',

        // Scarcity
        'tinggal', 'hanya', 'terakhir', 'slot terbatas',

        // Urgency phrases
        'sebelum telat', 'sebelum kehabisan', 'jangan nyesel',
    ];

    /**
     * Link promotion keywords.
     *
     * @var array<string>
     */
    private const LINK_KEYWORDS = [
        // Call to action
        'klik', 'klik link', 'cek bio', 'link bio', 'di bio',
        'link di bio', 'lihat bio', 'cek profil', 'daftar',
        'join', 'gabung', 'register', 'sign up', 'kunjungi',

        // URLs
        'http', 'https', 'www', '.com', '.id', '.net',
        'bit.ly', 't.me', 'wa.me',
    ];

    /**
     * Analyze text patterns for spam signals.
     *
     * @param  string  $text  Text to analyze (original text, NOT lowercased)
     * @return array{has_money: bool, has_urgency: bool, has_link_promotion: bool, emoji_density: float, caps_ratio: float, spam_signals: array}
     */
    public function analyzePatterns(string $text): array
    {
        $lowerText = mb_strtolower($text);

        $hasMoney = $this->containsKeywords($lowerText, self::MONEY_KEYWORDS);
        $hasUrgency = $this->containsKeywords($lowerText, self::URGENCY_KEYWORDS);
        $hasLinkPromotion = $this->containsKeywords($lowerText, self::LINK_KEYWORDS);
        $emojiDensity = $this->calculateEmojiDensity($text);
        $capsRatio = $this->calculateCapsRatio($text);

        // Collect spam signals for debugging
        $signals = [];
        if ($hasMoney) {
            $signals[] = 'money_mentions';
        }
        if ($hasUrgency) {
            $signals[] = 'urgency_language';
        }
        if ($hasLinkPromotion) {
            $signals[] = 'link_promotion';
        }
        if ($emojiDensity > 0.15) {
            $signals[] = 'high_emoji_density';
        }
        if ($capsRatio > 0.5) {
            $signals[] = 'excessive_caps';
        }

        return [
            'has_money' => $hasMoney,
            'has_urgency' => $hasUrgency,
            'has_link_promotion' => $hasLinkPromotion,
            'emoji_density' => $emojiDensity,
            'caps_ratio' => $capsRatio,
            'spam_signals' => $signals,
        ];
    }

    /**
     * Check if text contains any keywords from list.
     *
     * Uses word boundary matching to avoid false positives:
     * - "rp" won't match "terperfect" or "perpanjangan"
     * - "rb" won't match "terbaik"
     * - "gacor" won't match in middle of words
     *
     * @param  string  $text  Text to check (lowercase)
     * @param  array<string>  $keywords  Keywords to search for
     * @return bool True if any keyword found
     */
    private function containsKeywords(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            // Use word boundary regex to avoid substring matches
            // \b matches word boundaries (space, punctuation, start/end of string)
            $pattern = '/\b'.preg_quote($keyword, '/').'\b/u';

            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate emoji density (ratio of emojis to total characters).
     *
     * @param  string  $text  Text to analyze
     * @return float Density from 0.0 to 1.0
     */
    private function calculateEmojiDensity(string $text): float
    {
        if (empty($text)) {
            return 0.0;
        }

        // Count emojis using regex for common emoji ranges
        $emojiPattern = '/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]|[\x{1F900}-\x{1F9FF}]|[\x{1F1E0}-\x{1F1FF}]/u';

        preg_match_all($emojiPattern, $text, $matches);
        $emojiCount = count($matches[0]);

        $totalChars = mb_strlen($text, 'UTF-8');

        if ($totalChars === 0) {
            return 0.0;
        }

        return $emojiCount / $totalChars;
    }

    /**
     * Calculate CAPS ratio (uppercase letters / total letters).
     *
     * @param  string  $text  Text to analyze
     * @return float Ratio from 0.0 to 1.0
     */
    private function calculateCapsRatio(string $text): float
    {
        if (empty($text)) {
            return 0.0;
        }

        // Remove non-letter characters for accurate ratio
        $letters = preg_replace('/[^a-zA-Z]/u', '', $text);

        if (empty($letters)) {
            return 0.0;
        }

        $totalLetters = mb_strlen($letters, 'UTF-8');
        $uppercaseLetters = mb_strlen(preg_replace('/[^A-Z]/', '', $letters), 'UTF-8');

        return $uppercaseLetters / $totalLetters;
    }

    /**
     * Get matched keywords from text (for debugging).
     *
     * @param  string  $text  Text to analyze
     * @param  array<string>  $keywords  Keywords to search for
     * @return array<string> Matched keywords
     */
    public function getMatchedKeywords(string $text, array $keywords): array
    {
        $matched = [];

        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                $matched[] = $keyword;
            }
        }

        return $matched;
    }

    /**
     * Get all spam patterns detected in text (detailed report).
     *
     * @param  string  $text  Text to analyze
     * @return array{money: array, urgency: array, links: array, emoji_count: int, caps_count: int}
     */
    public function getDetailedPatterns(string $text): array
    {
        $lowerText = mb_strtolower($text, 'UTF-8');

        return [
            'money' => $this->getMatchedKeywords($lowerText, self::MONEY_KEYWORDS),
            'urgency' => $this->getMatchedKeywords($lowerText, self::URGENCY_KEYWORDS),
            'links' => $this->getMatchedKeywords($lowerText, self::LINK_KEYWORDS),
            'emoji_count' => $this->countEmojis($text),
            'caps_count' => mb_strlen(preg_replace('/[^A-Z]/', '', $text), 'UTF-8'),
        ];
    }

    /**
     * Count total emojis in text.
     *
     * @param  string  $text  Text to analyze
     * @return int Number of emojis
     */
    private function countEmojis(string $text): int
    {
        $emojiPattern = '/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]|[\x{1F900}-\x{1F9FF}]|[\x{1F1E0}-\x{1F1FF}]/u';

        preg_match_all($emojiPattern, $text, $matches);

        return count($matches[0]);
    }
}
