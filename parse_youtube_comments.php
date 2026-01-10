<?php
/**
 * Parse raw YouTube comments into structured JSON
 */

$inputFile = '/home/rahmat/mvp/delcom/tests/Fixtures/SpamDetection/complete_sample_comments.01.json';
$outputFile = '/home/rahmat/mvp/delcom/tests/Fixtures/SpamDetection/complete_sample_comments_02.json';

$lines = file($inputFile, FILE_IGNORE_NEW_LINES);
$comments = [];
$currentComment = null;
$commentId = 1;

for ($i = 0; $i < count($lines); $i++) {
    $line = trim($lines[$i]);

    // Check if line starts with @ (username)
    if (str_starts_with($line, '@') && !empty($line)) {
        // Save previous comment if exists
        if ($currentComment !== null && !empty($currentComment['text'])) {
            $comments[] = $currentComment;
            $commentId++;
        }

        // Start new comment
        $currentComment = [
            'id' => (string) $commentId,
            'video_id' => 'video_003',
            'text' => '',
            'author' => $line,
            'timestamp' => '',
            'expected_result' => 'clean',
            'reason' => 'Legitimate comment'
        ];

        // Next line should be timestamp
        if (isset($lines[$i + 1])) {
            $currentComment['timestamp'] = trim($lines[$i + 1]);
            $i++; // Skip timestamp line
        }

        // Collect comment text (may be multi-line until we hit numbers or "Reply")
        $commentText = [];
        $j = $i + 1;
        while ($j < count($lines)) {
            $nextLine = trim($lines[$j]);

            // Stop if we hit Reply, empty line followed by @, or numeric likes
            if ($nextLine === 'Reply' ||
                (empty($nextLine) && isset($lines[$j+1]) && str_starts_with(trim($lines[$j+1]), '@')) ||
                (is_numeric($nextLine) && $nextLine !== '')) {
                break;
            }

            if (!empty($nextLine)) {
                $commentText[] = $nextLine;
            }
            $j++;
        }

        $currentComment['text'] = implode("\n", $commentText);
        $i = $j - 1; // Update position
    }
}

// Add last comment
if ($currentComment !== null && !empty($currentComment['text'])) {
    $comments[] = $currentComment;
}

// Detect spam patterns
foreach ($comments as &$comment) {
    $text = $comment['text'];
    $lowerText = mb_strtolower($text);

    // Check for Unicode fancy fonts (Mathematical Alphanumeric, Circled, Squared, Fullwidth, Combining Marks)
    // These are NEVER used legitimately on YouTube
    if (preg_match('/[\x{1D400}-\x{1D7FF}]/u', $text) ||        // Mathematical Alphanumeric
        preg_match('/[\x{1F130}-\x{1F189}]/u', $text) ||        // Squared Latin
        preg_match('/[\x{24B6}-\x{24E9}]/u', $text) ||          // Circled Latin
        preg_match('/[\x{FF21}-\x{FF5A}]/u', $text) ||          // Fullwidth Latin
        preg_match('/[\x{0300}-\x{036F}]/u', $text) ||          // Combining Diacritical Marks
        preg_match('/[\x{1AB0}-\x{1AFF}]/u', $text) ||          // Combining Extended
        preg_match('/[\x{1DC0}-\x{1DFF}]/u', $text) ||          // Combining Supplement
        preg_match('/[\x{20D0}-\x{20FF}]/u', $text) ||          // Combining for Symbols (keycaps)
        preg_match('/[\x{FE00}-\x{FE0F}]/u', $text)) {          // Variation Selectors
        $comment['expected_result'] = 'spam';
        $comment['reason'] = 'Unicode fancy fonts detected - gambling/promotional spam';
        continue;
    }

    // Check for gambling promotion patterns (keyword + fancy emojis combo)
    $gamblingKeywords = ['pulawin', 'pulauwin', 'berkah99', 'pstoto', 'mona4d'];
    $hasFancyEmojis = preg_match('/[🔴🟣💕💦]/u', $text);

    foreach ($gamblingKeywords as $keyword) {
        if (str_contains($lowerText, $keyword) && $hasFancyEmojis) {
            $comment['expected_result'] = 'spam';
            $comment['reason'] = 'Gambling promotion detected: ' . $keyword . ' with fancy emojis';
            break;
        }
    }

    // Check for ALL CAPS (>90% uppercase)
    $lettersOnly = preg_replace('/[^a-zA-Z]/', '', $text);
    if (!empty($lettersOnly)) {
        $uppercaseRatio = mb_strlen(preg_replace('/[^A-Z]/', '', $lettersOnly)) / mb_strlen($lettersOnly);
        if ($uppercaseRatio > 0.9 && mb_strlen($lettersOnly) > 10) {
            $comment['expected_result'] = 'suspicious';
            $comment['reason'] = 'ALL CAPS detected';
        }
    }

    // Check for self-promotion
    if ((str_contains($lowerText, 'dijual') || str_contains($lowerText, 'jual')) &&
        (preg_match('/\d+\s*jt/i', $text) || preg_match('/\d+\s*rb/i', $text))) {
        $comment['expected_result'] = 'suspicious';
        $comment['reason'] = 'Self-promotion - selling items';
    }
}

// Create final structure
$output = [
    'test_metadata' => [
        'description' => 'YouTube comments from Honda Jazz GK5 review video (Video 3)',
        'source' => 'Real YouTube comments - Carmudi Honda Jazz GK5 review',
        'total_comments' => count($comments),
        'date_collected' => date('Y-m-d'),
        'test_categories' => [
            'Legitimate comments',
            'Spam with fancy Unicode',
            'Gambling promotions',
            'Self-promotion'
        ]
    ],
    'video_metadata' => [
        'video_id' => 'video_003',
        'title' => 'Honda Jazz GK5 Review - Carmudi Indonesia',
        'channel' => 'Carmudi Indonesia'
    ],
    'comments' => $comments
];

// Add expected detection summary
$spamCount = count(array_filter($comments, fn($c) => $c['expected_result'] === 'spam'));
$suspiciousCount = count(array_filter($comments, fn($c) => $c['expected_result'] === 'suspicious'));
$cleanCount = count(array_filter($comments, fn($c) => $c['expected_result'] === 'clean'));

$spamIds = array_map(fn($c) => $c['id'], array_filter($comments, fn($c) => $c['expected_result'] === 'spam'));

$output['expected_detection_summary'] = [
    'clean' => $cleanCount,
    'suspicious' => $suspiciousCount,
    'spam' => $spamCount,
    'breakdown' => [
        'spam_comments' => [
            'count' => $spamCount,
            'ids' => $spamIds
        ]
    ]
];

// Save to file
file_put_contents($outputFile, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "✅ Parsed " . count($comments) . " comments\n";
echo "   Clean: $cleanCount\n";
echo "   Suspicious: $suspiciousCount\n";
echo "   Spam: $spamCount\n";
echo "\n📁 Output: $outputFile\n";
