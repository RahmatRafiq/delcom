# 🎯 Spam Detection System - Implementation Plan

> **Status:** Planning Phase
> **Last Updated:** 2026-01-05
> **Target Platform:** YouTube (primary)

---

## 📋 Overview

Sistem deteksi spam yang lebih powerful dari YouTube Studio filter dengan pendekatan multi-layer:
1. **Unicode Normalization** - Detect Unicode tricks (𝗝𝗨𝗗𝗢𝗟 → JUDOL)
2. **Fuzzy Matching** - Detect obfuscation (jud0l, j.u.d.o.l → judol)
3. **Pattern Analysis** - Character-level analysis (emoji spam, caps spam, etc)
4. **Auto-Delete + Logging** - Langsung hapus spam + catat ke database

---

## 🎯 Goals

### Primary Goals
- ✅ Detect spam yang **lolos YouTube filter**
- ✅ Auto-delete spam comments via YouTube API
- ✅ Log semua spam untuk analytics
- ✅ Lebih cepat dari manual moderation di YouTube Studio

### Non-Goals (For Now)
- ❌ Replace YouTube AI filter completely
- ❌ Behavioral analysis (velocity, similarity) - Phase 2
- ❌ Cross-platform detection - Phase 2
- ❌ Machine Learning model - **OPTIONAL** (only if we have 10k+ labeled data)

### Approach: Rule-Based (No Training Required)
- ✅ Keyword database (manually curated)
- ✅ Pattern matching (deterministic rules)
- ✅ Unicode normalization (algorithmic)
- ✅ Fuzzy matching (Levenshtein distance)
- ✅ **Zero training data needed** - works immediately!

---

## 🏗️ Architecture

### File Structure
```
app/Services/SpamDetection/
├── UnicodeNormalizer.php         ← Normalize Unicode variants to ASCII
├── FuzzyMatcher.php               ← Levenshtein distance matching
├── PatternAnalyzer.php            ← Character/emoji/caps analysis
├── KeywordDatabase.php            ← Spam keyword management
└── AutoModerationService.php      ← Orchestrator (detect + delete + log)

database/migrations/
└── xxxx_create_spam_logs_table.php

app/Models/
└── SpamLog.php
```

### Flow Diagram
```
Comment Input
    ↓
[1] Unicode Detection (INSTANT SPAM CHECK)
    Has fancy Unicode (𝗝𝗨𝗗𝗢𝗟, 𝔸𝔹ℂ, etc)?
    ├─ YES → SPAM ✓ (Score: 100, Skip all other checks!)
    └─ NO  → Continue to [2]
    ↓
[2] Emoji Spam Check
    >3 emoji OR abnormal emoji?
    ├─ YES → SPAM ✓ (Score: 90)
    └─ NO  → Continue to [3]
    ↓
[3] Pattern Analysis (Optional)
    - Caps spam check (>70% uppercase)
    - Repeat chars check (aaaa, !!!!)
    - Link count (>1 link)
    ↓
[4] Keyword Matching (Optional)
    - Exact match: judol, slot, gacor
    - Fuzzy match: jud0l, slοt
    ↓
[5] Calculate Final Score (0-100)
    ↓
[6] Decision
    Score >= 80 → Auto Delete
    Score 50-79 → Review Queue
    Score < 50  → Allow
    ↓
[7] Execute + Log
    - YouTubeService::deleteComment()
    - SpamLog::create()
```

**Key Insight:**
- Layer 1 (Unicode) is a **hard filter** - instant spam, no scoring needed
- Layer 2-4 are **soft filters** - contribute to spam score
- Most spam (60-70%) akan ter-catch di Layer 1 & 2 saja!

---

## 📦 Components

### 1. UnicodeDetector (Previously: UnicodeNormalizer)

**Purpose:** Detect fancy Unicode fonts (instant spam indicator)

**IMPORTANT:** This is NOT a normalizer - it's a **detector**.
- Detection logic: "Ada fancy font? → SPAM" (tidak perlu cek keyword!)
- Rationale: Legitimate comments NEVER use fancy Unicode fonts
- Action: Return spam=true immediately if detected

**Fancy Unicode Ranges to Detect:**
- Mathematical Bold: 𝐀-𝐙 (U+1D400-U+1D419)
- Mathematical Italic: 𝐴-𝑍 (U+1D434-U+1D44D)
- Mathematical Bold Italic: 𝑨-𝒁 (U+1D468-U+1D481)
- Fullwidth: Ａ-Ｚ (U+FF21-U+FF3A)
- Circled: 🅐-🅩 (U+1F150-U+1F169)
- Squared: 🅰-🆉 (U+1F170-U+1F189)
- Double-struck: 𝔸-ℤ (U+1D538-U+1D551)
- Sans-serif: 𝖠-𝖹 (U+1D5A0-U+1D5B9)
- Monospace: 𝙰-𝚉 (U+1D670-U+1D689)

**Methods:**
```php
public function hasFancyUnicode(string $text): bool
public function getFancyCharCount(string $text): int
public function getFancyCharPositions(string $text): array
```

**Example:**
```php
Input:  "𝕁𝕌𝔻𝕆𝕃 𝔾𝔸ℂ𝕆ℝ"
Output: true (has fancy Unicode)
→ SPAM ✓ (instant decision, no keyword check needed!)

Input:  "JUDOL GACOR" (plain ASCII)
Output: false (no fancy Unicode)
→ Continue to next layer (keyword check)
```

---

### 2. FuzzyMatcher

**Purpose:** Detect obfuscation dengan Levenshtein distance

**Techniques:**
- Character substitution: `jud0l` (0→O), `s1ot` (1→l)
- Separator insertion: `j.u.d.o.l`, `j-u-d-o-l`
- Subscript/superscript: `ju₽ol`, `ʲᵘᵈᵒˡ`

**Methods:**
```php
public function isSimilar(string $text, string $keyword, int $maxDistance = 2): bool
public function getDistance(string $str1, string $str2): int
public function findBestMatch(string $text, array $keywords): ?string
```

**Example:**
```php
isSimilar("jud0l", "judol", 2) → true (distance: 1)
isSimilar("j.u.d.o.l", "judol", 2) → false (distance: 4)
// After removing dots:
isSimilar("judol", "judol", 2) → true (distance: 0)
```

---

### 3. PatternAnalyzer

**Purpose:** Character-level spam detection

**Checks:**
1. **Emoji Spam**
   - Count emoji density
   - Threshold: >3 emoji per 50 characters

2. **Caps Spam**
   - Uppercase ratio
   - Threshold: >70% uppercase

3. **Repeat Characters**
   - Detect "gacooorrr", "!!!!!", "????"
   - Threshold: >4 consecutive same chars

4. **Non-ASCII Ratio**
   - Calculate non-ASCII character percentage
   - Threshold: >30% non-ASCII

5. **Link Detection**
   - Count URLs
   - Threshold: >1 link = suspicious

**Methods:**
```php
public function analyze(string $text): array
public function getEmojiCount(string $text): int
public function getCapsRatio(string $text): float
public function hasRepeatChars(string $text, int $threshold = 4): bool
public function getNonAsciiRatio(string $text): float
public function getLinkCount(string $text): int
```

**Output:**
```php
[
    'emoji_count' => 3,
    'emoji_density' => 0.15, // 3 emoji in 20 chars
    'caps_ratio' => 0.8,     // 80% uppercase
    'has_repeat_chars' => true,
    'non_ascii_ratio' => 0.4,
    'link_count' => 2,
    'spam_score' => 85,      // Calculated score
]
```

---

### 4. KeywordDatabase

**Purpose:** Manage spam keywords dengan prioritas

**Categories:**
- `gambling`: judol, slot, gacor, maxwin, rtp
- `crypto`: trading, profit, investasi, bitcoin
- `promotion`: link bio, cek channel, follow back
- `scam`: gratis, bonus, hadiah, klaim

**Priority Levels:**
- `high` (90-100): Instant delete keywords (judol, slot)
- `medium` (70-89): Review queue keywords (trading, investasi)
- `low` (50-69): Suspicious keywords (link, cek)

**Methods:**
```php
public function getKeywords(string $category = null): Collection
public function addKeyword(string $keyword, string $category, int $priority): void
public function match(string $text): ?array
public function getScore(string $text): int
```

**Database Schema:**
```php
spam_keywords:
- id
- keyword (indexed)
- category (enum: gambling, crypto, promotion, scam)
- priority (1-100)
- match_type (exact, fuzzy, regex)
- created_at
- updated_at
```

---

### 5. AutoModerationService

**Purpose:** Orchestrator yang combine semua detection methods

**Flow:**
```php
1. Receive comment
2. Normalize Unicode → $normalized
3. Analyze patterns → $patterns
4. Match keywords (exact) → $exactMatch
5. If no match, try fuzzy → $fuzzyMatch
6. Calculate final score → $spamScore
7. Make decision → $action
8. Execute action (delete/hide/allow)
9. Log to database
10. Return result
```

**Methods:**
```php
public function analyzeComment(array $comment): SpamDetectionResult
public function processComments(array $comments): array
public function deleteSpam(string $commentId, array $reason): bool
public function logSpam(array $data): SpamLog
```

**SpamDetectionResult:**
```php
class SpamDetectionResult
{
    public bool $isSpam;
    public int $score;           // 0-100
    public string $reason;        // Human-readable reason
    public array $matched;        // Matched keywords/patterns
    public string $action;        // 'delete' | 'review' | 'allow'
    public array $analysis;       // Pattern analysis details
}
```

---

## 🗄️ Database Schema

### spam_logs Table
```php
Schema::create('spam_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('user_platform_id')->constrained()->onDelete('cascade');
    $table->string('platform')->default('youtube'); // youtube, instagram, tiktok
    $table->string('comment_id')->index();
    $table->string('content_id')->index(); // video_id, post_id, etc
    $table->text('comment_text');
    $table->string('author_name')->nullable();
    $table->string('author_channel_id')->nullable();
    $table->integer('spam_score'); // 0-100
    $table->string('detection_method'); // 'keyword' | 'fuzzy' | 'pattern' | 'ai'
    $table->json('matched_patterns')->nullable(); // What triggered the detection
    $table->string('action_taken'); // 'deleted' | 'hidden' | 'flagged'
    $table->boolean('auto_moderated')->default(false);
    $table->timestamp('detected_at');
    $table->timestamp('deleted_at')->nullable();
    $table->timestamps();

    $table->index(['user_id', 'detected_at']);
    $table->index(['platform', 'spam_score']);
});
```

---

## 🔢 Scoring System

### Weight Distribution
```
Total Score = 100 points

1. Keyword Match (50 points)
   - Exact high-priority keyword: 50
   - Fuzzy high-priority keyword: 40
   - Exact medium-priority keyword: 35
   - Fuzzy medium-priority keyword: 25

2. Pattern Analysis (30 points)
   - Emoji spam (>3): +15
   - Caps spam (>70%): +10
   - Repeat chars: +5
   - High non-ASCII ratio: +10

3. Link Detection (20 points)
   - 1 link: +10
   - 2+ links: +20
```

### Decision Thresholds
```
Score >= 80: Auto Delete (High confidence spam)
Score 50-79: Review Queue (Suspicious, needs manual review)
Score < 50:  Allow (Likely genuine)
```

---

## 🚀 Implementation Phases

### Phase 1: Core Detection (Week 1)
- [x] Plan & documentation
- [ ] UnicodeNormalizer.php
- [ ] PatternAnalyzer.php
- [ ] KeywordDatabase.php + migration
- [ ] SpamLog model + migration
- [ ] Unit tests

### Phase 2: Fuzzy Matching (Week 2)
- [ ] FuzzyMatcher.php
- [ ] Integration with KeywordDatabase
- [ ] Performance optimization (caching)
- [ ] Unit tests

### Phase 3: Auto-Moderation (Week 3)
- [ ] AutoModerationService.php
- [ ] Integration with YouTubeService
- [ ] Queue job for async processing
- [ ] Logging & analytics

### Phase 4: Dashboard & UI (Week 4)
- [ ] Spam log viewer page
- [ ] Analytics dashboard (charts, trends)
- [ ] Manual review queue UI
- [ ] Keyword management UI

---

## ⚠️ Important Considerations

### 1. YouTube API Quota
- `setModerationStatus` costs 50 quota units
- Daily limit: 10,000 units = ~200 deletions/day
- Need to implement quota tracking & throttling

### 2. False Positives
- Conservative thresholds to minimize deleting genuine comments
- Always log before delete (reversible via restore feature)
- Manual review queue for borderline cases

### 3. Performance
- Cache normalized keywords (Redis)
- Batch processing for multiple comments
- Async deletion via Queue jobs

### 4. Privacy & Compliance
- Store minimal comment data
- Anonymize author info after 30 days
- GDPR compliance (data export/deletion)

---

## 🧪 Testing Strategy

### Unit Tests
- UnicodeNormalizer: Test all Unicode ranges
- FuzzyMatcher: Test Levenshtein edge cases
- PatternAnalyzer: Test each metric individually
- KeywordDatabase: Test matching logic

### Integration Tests
- AutoModerationService: End-to-end flow
- YouTubeService integration
- Database logging

### Manual Testing
- Real spam comments from YouTube
- Edge cases (mixed languages, emojis, etc)
- Performance with large batches (1000+ comments)

---

## 📊 Success Metrics

### Detection Accuracy
- True Positive Rate: >95% (spam correctly identified)
- False Positive Rate: <5% (genuine wrongly flagged)
- False Negative Rate: <10% (spam missed)

### Performance
- Detection speed: <10ms per comment
- Batch processing: >100 comments/second
- API quota usage: <50% of daily limit

### User Impact
- Time saved: >70% vs manual YouTube Studio moderation
- Spam reduction: >80% of spam auto-deleted
- User satisfaction: >4.5/5 stars

---

## 🔮 Future Enhancements (Phase 2+)

### Behavioral Analysis
- Comment velocity tracking
- Content similarity detection
- User reputation scoring
- Cross-video spam patterns

### Machine Learning (OPTIONAL - Only After 6+ Months)
- **Prerequisite:** Collect 10,000+ labeled comments from user feedback
- Train custom spam classifier (Naive Bayes / SVM - lightweight, CPU-only)
- Feature engineering from behavioral data
- Continuous learning from user feedback
- **Use Case:** Only for borderline cases (score 50-79), not replace rule-based
- **Trade-off:** Adds complexity, needs maintenance, but can improve accuracy by ~10-15%

### Cross-Platform
- Instagram spam detection
- TikTok spam detection
- Shared spam pattern database

### Advanced Actions
- Auto-reply to spam with warning
- Shadow ban (hide from others, visible to author)
- Report spam users to platform
- Bulk user blocking

---

## 📝 Notes & Questions

### Open Questions
1. **Apakah spam yang lolos YouTube filter benar-benar pakai Unicode tricks?**
   - Need real examples to validate approach
   - Mungkin YouTube sudah detect Unicode, tapi threshold-nya lebih tinggi?

2. **Seberapa sering spammer ganti taktik?**
   - Need to update keyword database regularly
   - Consider crowdsourced keyword reporting

3. **Apakah user mau full auto-delete atau prefer review queue?**
   - Configurable threshold per user
   - Default: conservative (review queue)

### Risks
- Over-engineering: Solusi terlalu kompleks untuk masalah simple
- Maintenance burden: Keyword database perlu update terus
- False positives: User complain genuine comments dihapus

### Mitigations
- Start simple: Focus on high-confidence spam only
- Community-driven: Let users contribute spam patterns
- Transparency: Always show why comment flagged
- Reversible: Keep deleted comments in logs (restore option)

---

**Next Steps:**
1. Validate approach dengan real spam examples
2. Prototype UnicodeNormalizer + PatternAnalyzer
3. Test dengan sample data
4. Iterate based on results
