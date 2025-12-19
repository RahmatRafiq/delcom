<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SpamDetectionService
{
    private string $provider;

    private string $model;

    private float $confidenceThreshold;

    public function __construct()
    {
        $this->provider = config('services.ai.provider', 'openai');
        $this->model = config('services.ai.model', 'gpt-4o-mini');
        $this->confidenceThreshold = config('services.ai.spam_threshold', 0.7);
    }

    /**
     * Analyze a single comment for spam.
     */
    public function analyzeComment(string $comment, array $context = []): SpamAnalysisResult
    {
        $cacheKey = 'spam_analysis:'.md5($comment);

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($comment, $context) {
            return $this->callAI($comment, $context);
        });
    }

    /**
     * Analyze multiple comments in batch (more efficient).
     */
    public function analyzeBatch(array $comments, array $context = []): array
    {
        $results = [];
        $uncached = [];

        // Check cache first
        foreach ($comments as $index => $comment) {
            $cacheKey = 'spam_analysis:'.md5($comment['text'] ?? $comment);
            $cached = Cache::get($cacheKey);

            if ($cached) {
                $results[$index] = $cached;
            } else {
                $uncached[$index] = $comment;
            }
        }

        // Analyze uncached comments in batch
        if (! empty($uncached)) {
            $batchResults = $this->callAIBatch($uncached, $context);

            foreach ($batchResults as $index => $result) {
                $text = is_array($uncached[$index]) ? $uncached[$index]['text'] : $uncached[$index];
                $cacheKey = 'spam_analysis:'.md5($text);
                Cache::put($cacheKey, $result, now()->addHours(24));
                $results[$index] = $result;
            }
        }

        // Sort by original index
        ksort($results);

        return $results;
    }

    /**
     * Call AI API for single comment analysis.
     */
    private function callAI(string $comment, array $context): SpamAnalysisResult
    {
        $prompt = $this->buildPrompt([$comment], $context);

        try {
            $response = $this->makeAPICall($prompt);
            $parsed = $this->parseResponse($response);

            return $parsed[0] ?? new SpamAnalysisResult(
                isSpam: false,
                confidence: 0,
                reason: 'Analysis failed',
                categories: []
            );
        } catch (\Exception $e) {
            Log::error('AI spam detection failed', [
                'error' => $e->getMessage(),
                'comment_length' => strlen($comment),
            ]);

            return new SpamAnalysisResult(
                isSpam: false,
                confidence: 0,
                reason: 'Analysis error: '.$e->getMessage(),
                categories: [],
                error: true
            );
        }
    }

    /**
     * Call AI API for batch comment analysis.
     */
    private function callAIBatch(array $comments, array $context): array
    {
        // Process in chunks of 20 to avoid token limits
        $chunks = array_chunk($comments, 20, true);
        $allResults = [];

        foreach ($chunks as $chunk) {
            $texts = array_map(fn ($c) => is_array($c) ? $c['text'] : $c, $chunk);
            $prompt = $this->buildPrompt($texts, $context);

            try {
                $response = $this->makeAPICall($prompt);
                $parsed = $this->parseResponse($response);

                $indices = array_keys($chunk);
                foreach ($parsed as $i => $result) {
                    if (isset($indices[$i])) {
                        $allResults[$indices[$i]] = $result;
                    }
                }
            } catch (\Exception $e) {
                Log::error('AI batch spam detection failed', [
                    'error' => $e->getMessage(),
                    'batch_size' => count($chunk),
                ]);

                // Return error results for this batch
                foreach (array_keys($chunk) as $index) {
                    $allResults[$index] = new SpamAnalysisResult(
                        isSpam: false,
                        confidence: 0,
                        reason: 'Batch analysis error',
                        categories: [],
                        error: true
                    );
                }
            }
        }

        return $allResults;
    }

    /**
     * Build the prompt for AI analysis.
     */
    private function buildPrompt(array $comments, array $context): string
    {
        $contextInfo = '';
        if (! empty($context)) {
            $contextInfo = "Context:\n";
            if (isset($context['platform'])) {
                $contextInfo .= "- Platform: {$context['platform']}\n";
            }
            if (isset($context['content_type'])) {
                $contextInfo .= "- Content type: {$context['content_type']}\n";
            }
            if (isset($context['author_info'])) {
                $contextInfo .= "- Author info available: yes\n";
            }
            $contextInfo .= "\n";
        }

        $commentsList = '';
        foreach ($comments as $i => $comment) {
            $num = $i + 1;
            $text = is_array($comment) ? $comment['text'] : $comment;
            $text = mb_substr($text, 0, 500); // Limit length
            $commentsList .= "[{$num}] {$text}\n\n";
        }

        return <<<PROMPT
You are a spam detection expert. Analyze the following comment(s) and determine if they are spam.

{$contextInfo}Spam categories to detect:
- PROMOTIONAL: Unsolicited advertising, "check my profile", selling products
- SCAM: Financial scams, crypto schemes, "get rich quick", fake giveaways
- PHISHING: Suspicious links, fake login requests, credential harvesting
- BOT_GENERATED: Repetitive patterns, generic responses, automated messages
- HARASSMENT: Toxic comments, threats, targeted attacks
- INAPPROPRIATE: Adult content, explicit material
- ENGAGEMENT_BAIT: "Like for like", "Follow me", manipulation tactics
- IRRELEVANT: Off-topic, copy-paste spam

Comments to analyze:
{$commentsList}

Respond in JSON format:
{
  "results": [
    {
      "index": 1,
      "is_spam": true/false,
      "confidence": 0.0-1.0,
      "categories": ["CATEGORY1", "CATEGORY2"],
      "reason": "Brief explanation"
    }
  ]
}

Only mark as spam if confidence >= 0.7. Be conservative - genuine comments should not be flagged.
PROMPT;
    }

    /**
     * Make API call to the configured provider.
     */
    private function makeAPICall(string $prompt): string
    {
        return match ($this->provider) {
            'anthropic', 'claude' => $this->callClaude($prompt),
            'openai' => $this->callOpenAI($prompt),
            default => throw new \InvalidArgumentException("Unknown AI provider: {$this->provider}"),
        };
    }

    /**
     * Call Claude API.
     */
    private function callClaude(string $prompt): string
    {
        $apiKey = config('services.ai.anthropic_api_key');

        if (empty($apiKey)) {
            throw new \RuntimeException('Anthropic API key not configured');
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model ?: 'claude-3-haiku-20240307',
            'max_tokens' => 2048,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Claude API error: '.$response->body());
        }

        $data = $response->json();

        return $data['content'][0]['text'] ?? '';
    }

    /**
     * Call OpenAI API.
     */
    private function callOpenAI(string $prompt): string
    {
        $apiKey = config('services.ai.openai_api_key');

        if (empty($apiKey)) {
            throw new \RuntimeException('OpenAI API key not configured');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $this->model ?: 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a spam detection expert. Respond only with valid JSON.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'max_tokens' => 2048,
            'temperature' => 0.1, // Low temperature for consistent results
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('OpenAI API error: '.$response->body());
        }

        $data = $response->json();

        return $data['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Parse AI response into SpamAnalysisResult objects.
     */
    private function parseResponse(string $response): array
    {
        // Extract JSON from response (handle markdown code blocks)
        $json = $response;
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $response, $matches)) {
            $json = $matches[1];
        }

        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to parse AI response', [
                'response' => mb_substr($response, 0, 500),
                'error' => json_last_error_msg(),
            ]);

            return [];
        }

        $results = [];
        foreach ($data['results'] ?? [] as $item) {
            $results[] = new SpamAnalysisResult(
                isSpam: (bool) ($item['is_spam'] ?? false),
                confidence: (float) ($item['confidence'] ?? 0),
                reason: $item['reason'] ?? '',
                categories: $item['categories'] ?? []
            );
        }

        return $results;
    }

    /**
     * Check if AI detection is enabled and configured.
     */
    public static function isEnabled(): bool
    {
        if (! config('services.ai.enabled', false)) {
            return false;
        }

        $provider = config('services.ai.provider', 'openai');

        return match ($provider) {
            'anthropic', 'claude' => ! empty(config('services.ai.anthropic_api_key')),
            'openai' => ! empty(config('services.ai.openai_api_key')),
            default => false,
        };
    }

    /**
     * Get the confidence threshold for spam detection.
     */
    public function getConfidenceThreshold(): float
    {
        return $this->confidenceThreshold;
    }
}
