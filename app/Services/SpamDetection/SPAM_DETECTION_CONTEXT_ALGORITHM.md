# Per-Channel Spam Context Algorithm

> **Status:** Design Document - Ready for Migration
> **Created:** 2026-01-10
> **Purpose:** Context-aware spam detection per channel owner

---

## 🎯 Problem Statement

**Current Issue:**
- Hardcoded keywords in `PatternAnalyzer` tidak cocok untuk semua video type
- "juta" = spam di gaming video, tapi legitimate di car review
- "cepat" = spam urgency, tapi legitimate di tech review
- One-size-fits-all approach = high false positives

**Solution:**
Per-channel context system via Admin Panel - setiap channel owner define spam rules mereka sendiri.

---

## 🏗️ Architecture Design

### Database Schema

```sql
-- Channel spam context configuration
CREATE TABLE channel_spam_contexts (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_platform_id BIGINT NOT NULL,
    context_type ENUM('automotive', 'gaming', 'cooking', 'tech', 'general') DEFAULT 'general',

    -- Whitelist: Keywords yang BOLEH muncul (not spam)
    whitelist_keywords JSON DEFAULT NULL,
    -- Example: ["juta", "cepat", "modal", "ribuan"]

    -- Blacklist: Brand/site names yang PASTI spam
    blacklist_keywords JSON DEFAULT NULL,
    -- Example: ["M0NA4D", "PULAUWIN", "GACOR88"]

    -- Custom patterns: Regex untuk detection khusus
    custom_patterns JSON DEFAULT NULL,
    -- Example: [{"pattern": ".*WIN$", "score": 30}]

    -- Threshold overrides
    cluster_threshold INT DEFAULT 50,
    pattern_weight_multiplier DECIMAL(3,2) DEFAULT 1.0,

    -- Metadata
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_platform_id) REFERENCES user_platforms(id) ON DELETE CASCADE,
    INDEX idx_user_platform (user_platform_id),
    INDEX idx_active (is_active)
);

-- Spam detection audit log
CREATE TABLE spam_detection_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_platform_id BIGINT NOT NULL,
    video_id VARCHAR(255) NOT NULL,
    comment_id VARCHAR(255) NOT NULL,
    comment_text TEXT NOT NULL,

    -- Detection results
    spam_score INT NOT NULL,
    category ENUM('CRITICAL', 'MEDIUM', 'LOW') NOT NULL,
    signals JSON NOT NULL,
    action_taken ENUM('AUTO_DELETE', 'REVIEW_QUEUE', 'IGNORE') NOT NULL,

    -- Context used
    context_applied JSON DEFAULT NULL,

    -- Metadata
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_platform_id) REFERENCES user_platforms(id) ON DELETE CASCADE,
    INDEX idx_video (video_id),
    INDEX idx_action (action_taken),
    INDEX idx_detected (detected_at)
);
```

---

## 🧮 Algorithm Flow

### Phase 1: Load Channel Context

```php
function loadChannelContext(int $userPlatformId): array
{
    // 1. Fetch context from database
    $context = DB::table('channel_spam_contexts')
        ->where('user_platform_id', $userPlatformId)
        ->where('is_active', true)
        ->first();

    if (!$context) {
        // Use default context
        return [
            'whitelist_keywords' => [],
            'blacklist_keywords' => [],
            'custom_patterns' => [],
            'cluster_threshold' => 50,
            'pattern_weight' => 1.0,
        ];
    }

    return [
        'whitelist_keywords' => json_decode($context->whitelist_keywords, true) ?? [],
        'blacklist_keywords' => json_decode($context->blacklist_keywords, true) ?? [],
        'custom_patterns' => json_decode($context->custom_patterns, true) ?? [],
        'cluster_threshold' => $context->cluster_threshold,
        'pattern_weight' => $context->pattern_weight_multiplier,
    ];
}
```

### Phase 2: Context-Aware Pattern Detection

```php
function analyzeWithContext(string $text, array $context): array
{
    $score = 0;
    $signals = [];

    // 1. Check blacklist FIRST (instant high score)
    foreach ($context['blacklist_keywords'] as $keyword) {
        if (str_contains(mb_strtolower($text), mb_strtolower($keyword))) {
            $score += 80; // VERY HIGH - explicitly blacklisted
            $signals[] = "Blacklisted keyword: {$keyword} (+80)";
            break; // One match is enough
        }
    }

    // 2. Pattern analysis with whitelist filtering
    $patterns = $this->patternAnalyzer->analyzePatterns($text);

    // Apply pattern scores ONLY if NOT whitelisted
    if ($patterns['has_money']) {
        // Check if money keywords are whitelisted
        $isWhitelisted = $this->containsWhitelistedKeyword(
            $text,
            $context['whitelist_keywords']
        );

        if (!$isWhitelisted) {
            $score += 20 * $context['pattern_weight'];
            $signals[] = "Money mentions (+20)";
        }
    }

    // Similar for urgency, link_promotion, etc.

    // 3. Apply custom patterns
    foreach ($context['custom_patterns'] as $pattern) {
        if (preg_match("/{$pattern['pattern']}/i", $text)) {
            $score += $pattern['score'];
            $signals[] = "Custom pattern matched: {$pattern['pattern']} (+{$pattern['score']})";
        }
    }

    return [
        'score' => min($score, 100),
        'signals' => $signals,
        'context_applied' => [
            'whitelist_used' => $context['whitelist_keywords'],
            'blacklist_matched' => /* ... */,
        ],
    ];
}
```

### Phase 3: Context-Aware Cluster Detection

```php
function scoreClusterWithContext(array $cluster, array $context): array
{
    // Base cluster scoring (same as current)
    $score = $this->baseClusterScore($cluster);

    // Apply context-specific threshold
    $threshold = $context['cluster_threshold']; // Default 50, customizable

    // Check if cluster contains blacklisted brands
    $representativeText = $cluster['members'][0]['normalized_text'];
    foreach ($context['blacklist_keywords'] as $keyword) {
        if (str_contains(mb_strtolower($representativeText), mb_strtolower($keyword))) {
            $score += 30; // Boost score for blacklisted brands
            break;
        }
    }

    return [
        'score' => min($score, 100),
        'is_spam_campaign' => $score >= $threshold,
        'context_applied' => $context,
    ];
}
```

---

## 📊 Scoring Matrix

### Base Scores (No Context)

| Signal | Score | Notes |
|--------|-------|-------|
| Unicode Fancy Fonts | +95 | INSTANT SPAM (context-independent) |
| Cluster Size (5+) | +40 | Campaign indicator |
| Money Keywords | +20 | Context-dependent |
| Urgency Language | +15 | Context-dependent |
| Link Promotion | +15 | Context-dependent |

### Context Modifiers

| Context | Whitelist Example | Effect |
|---------|------------------|--------|
| Automotive | juta, cepat, modal | Money/urgency NOT flagged |
| Gaming | menang, kalah, slot | Gambling terms allowed (game context) |
| Cooking | modal, murah, ribuan | Budget terms allowed |
| Tech | cepat, performa, harga | Specs/price terms allowed |

### Blacklist Boost

| Type | Score Boost | Auto-Action |
|------|-------------|-------------|
| Known Gambling Site | +80 | CRITICAL → Auto Delete |
| Brand Name Pattern | +50 | CRITICAL if > 70 |
| Scam Keywords | +60 | CRITICAL if > 70 |

---

## 🔄 Migration Flow

### Current System → New System

```
CURRENT (Hardcoded):
PatternAnalyzer::MONEY_KEYWORDS → All videos get same rules

NEW (Context-Aware):
1. Load channel_spam_contexts by user_platform_id
2. Apply whitelist/blacklist per channel
3. Custom threshold per channel owner
4. Audit log for transparency

BACKWARD COMPATIBLE:
- If no context exists → use default (current behavior)
- Gradual migration per channel
- Admin can enable/disable context
```

---

## 🎨 Admin Panel UI Flow

### 1. Channel Settings Page

```
┌─────────────────────────────────────────────┐
│ Spam Detection Settings                     │
├─────────────────────────────────────────────┤
│                                              │
│ Video Context Type: [Automotive ▼]          │
│                                              │
│ ✅ Use Custom Spam Rules                    │
│                                              │
│ ┌──────────────────────────────────────┐   │
│ │ Whitelist (Allow These Keywords)     │   │
│ │ ┌──────────────────────────────────┐ │   │
│ │ │ juta, ribu, cepat, modal         │ │   │
│ │ └──────────────────────────────────┘ │   │
│ │ These words WON'T trigger spam       │   │
│ └──────────────────────────────────────┘   │
│                                              │
│ ┌──────────────────────────────────────┐   │
│ │ Blacklist (Always Block)             │   │
│ │ ┌──────────────────────────────────┐ │   │
│ │ │ M0NA4D, PULAUWIN, GACOR88        │ │   │
│ │ └──────────────────────────────────┘ │   │
│ │ Auto-delete comments with these      │   │
│ └──────────────────────────────────────┘   │
│                                              │
│ Advanced Settings:                           │
│ ├─ Cluster Threshold: [50] (1-100)          │
│ ├─ Pattern Sensitivity: [Medium ▼]          │
│ └─ Auto-delete Score: [70] (70-100)         │
│                                              │
│ [Save Settings]  [Reset to Default]         │
└─────────────────────────────────────────────┘
```

### 2. Spam Detection Dashboard

```
┌─────────────────────────────────────────────┐
│ Recent Spam Detections (Last 7 Days)        │
├─────────────────────────────────────────────┤
│                                              │
│ Video: "Review Honda Civic 2024"            │
│ ├─ CRITICAL: 12 auto-deleted                │
│ ├─ MEDIUM: 3 in review queue → [Review]    │
│ └─ LOW: 245 ignored                         │
│                                              │
│ Blacklist Matches This Week: 45              │
│ ├─ M0NA4D: 23 comments                      │
│ ├─ PULAUWIN: 15 comments                    │
│ └─ GACOR88: 7 comments                      │
│                                              │
│ False Positive Rate: 0.5% ✅                │
│ (User feedback: 2/400 were legitimate)       │
│                                              │
│ [View Full Report]  [Adjust Settings]       │
└─────────────────────────────────────────────┘
```

---

## 🚀 Implementation Priority

### Phase 1: Database & Core Service (Week 1)
- ✅ Create `channel_spam_contexts` table
- ✅ Create `spam_detection_logs` table
- ✅ Implement `ChannelContextService`
- ✅ Modify `HybridSpamDetector` to accept context

### Phase 2: Admin Panel (Week 2)
- ✅ Channel settings page (CRUD)
- ✅ Whitelist/blacklist management
- ✅ Context type presets (automotive, gaming, etc.)
- ✅ Test mode (preview without applying)

### Phase 3: Integration (Week 3)
- ✅ Hook spam detection to review queue
- ✅ Auto-delete for CRITICAL scores
- ✅ User feedback loop (false positive reporting)
- ✅ Analytics dashboard

### Phase 4: Machine Learning (Future)
- 🔄 Auto-suggest whitelist based on channel history
- 🔄 Adaptive thresholds based on false positive rate
- 🔄 Community-based blacklist sharing

---

## 📝 Migration Checklist

### Services to Migrate

```
Current Repo (Messy):
├─ app/Services/SpamDetection/
│  ├─ HybridSpamDetector.php ✅ (migrate)
│  ├─ PatternAnalyzer.php ✅ (modify for context)
│  ├─ UnicodeDetector.php ✅ (no change - universal)
│  ├─ SpamClusterDetector.php ✅ (modify for context)
│  ├─ ContextualAnalyzer.php ✅ (migrate)
│  └─ FuzzyMatcher.php ✅ (no change - utility)

New Additions:
├─ ChannelContextService.php 🆕
├─ SpamDetectionLogger.php 🆕
└─ SpamDetectionFactory.php 🆕 (creates detector with context)
```

### Database Migrations

```
New Tables:
├─ channel_spam_contexts
└─ spam_detection_logs

Modified Tables:
├─ user_platforms (add has_custom_spam_rules flag)
└─ pending_moderations (add context_applied JSON)
```

### Config Files

```
config/spam-detection.php:
├─ default_whitelist (per context type)
├─ default_blacklist (common gambling sites)
├─ default_thresholds
└─ context_presets (automotive, gaming, cooking, etc.)
```

---

## 🎯 Success Metrics

### Before Context-Aware System
- Unicode Detection: 100% ✅
- Cluster Detection: 16.7% recall ⚠️
- False Positives: 27 on clean comments ❌

### After Context-Aware System (Target)
- Unicode Detection: 100% ✅ (unchanged)
- Cluster Detection: 80%+ recall ✅
- False Positives: <1% ✅
- Per-channel customization: 100% ✅
- User satisfaction: 90%+ ✅

---

## 🔒 Security Considerations

1. **Injection Prevention**: Sanitize user input for custom patterns (regex)
2. **Rate Limiting**: Prevent abuse of context changes
3. **Audit Trail**: Log all context changes with user attribution
4. **Rollback**: Allow reverting to previous context version
5. **Admin Override**: Super admin can override channel contexts

---

## 📚 API Design (Future)

```php
// For service migration
interface SpamDetectionInterface
{
    public function detectSpam(
        array $comments,
        int $userPlatformId
    ): array;

    public function loadContext(int $userPlatformId): array;

    public function applyContext(
        array $detectionResult,
        array $context
    ): array;
}

// Factory pattern
class SpamDetectionFactory
{
    public static function create(int $userPlatformId): SpamDetector
    {
        $context = (new ChannelContextService())->load($userPlatformId);
        return new HybridSpamDetector($context);
    }
}
```

---

**END OF DESIGN DOCUMENT**

> Next: Create `MIGRATION_PLAN.md` for detailed migration steps
