# 🛡️ Spam Detection System - Project Context

> **Last Updated:** 2026-01-10
> **Status:** Active Development - Ready for Migration
> **Purpose:** Context untuk AI Assistant saat bekerja dengan spam detection services

---

## 🎯 Project Mission

**Melindungi masyarakat Indonesia dari spam judi online di YouTube comments.**

Sistem ini mendeteksi dan menghapus komentar spam gambling yang menggunakan:
- Fancy Unicode fonts (𝐌0𝐍𝐀𝟒𝐃, 𝘽𝙀𝙍𝙆𝘼𝙃99, ℙ𝕌𝕃𝔸𝕌𝕎𝕀ℕ)
- Repetitive spam campaigns (cluster detection)
- Gambling keywords dan money mentions

---

## 📁 File Locations

### Core Services (Current Location - AKAN DIMIGRATION)
```
app/Services/SpamDetection/
├── HybridSpamDetector.php          ← Main orchestrator
├── UnicodeDetector.php              ← 100% accuracy, PRODUCTION READY ✅
├── PatternAnalyzer.php              ← Gambling keywords detector
├── SpamClusterDetector.php          ← Campaign detection
├── ContextualAnalyzer.php           ← Context-based analysis
├── FuzzyMatcher.php                 ← Similarity matching
│
├── SPAM_DETECTION_CONTEXT_ALGORITHM.md  ← Per-channel design
└── SERVICE_MIGRATION_PLAN.md             ← Migration roadmap
```

### Test Infrastructure
```
app/Console/Commands/
└── TestCompleteSpamDetection.php   ← Comprehensive testing command

tests/Fixtures/SpamDetection/
├── complete_sample_comments_02.json     ← Unicode spam (12/208)
└── cluster_detection_test.json          ← Cluster spam (30/50)

docs/
└── SPAM_DETECTION_ALGORITHM.md          ← Core algorithm documentation
```

---

## 🏆 Current Achievements

### ✅ Working Systems

**1. Unicode Detection (PRODUCTION READY)**
```
Accuracy: 100%
Precision: 100%
Recall: 100%
F1 Score: 100%

Detection Method: Fancy Unicode ranges
Score: +95 points (instant CRITICAL)
Action: Auto-delete
Status: DEPLOYED ✅
```

**2. Cluster Detection (WORKING)**
```
Campaigns Detected: 3/3
Precision: 100% (no false positives)
Recall: 16.7% (needs tuning)
F1 Score: 28.6%

Detection Method: Repetitive patterns + fuzzy matching
Threshold: 50 points (lowered from 70)
Action: Review queue (MEDIUM score)
Status: Needs improvement ⚠️
```

**3. Pattern Analysis (IMPROVED)**
```
Keywords Added:
├─ Gambling: menang, kalah, zonk, jepe, jp, bonus
├─ Sites: depo, slot, toto, togel, bandar
└─ Existing: gacor, maxwin, scatter, jackpot

Issue: High false positives on legitimate comments
Solution: Per-channel context (future)
Status: Context-dependent ⚠️
```

### 🐛 Known Issues

**1. Cluster Detection Recall Too Low**
- Problem: Only detecting 16.7% of spam campaigns
- Root Cause: Small clusters (2 comments) instead of full campaigns (10-12)
- Suspect: Fuzzy matching too strict OR clustering algorithm issue
- Priority: MEDIUM (Unicode detection covers most cases)

**2. Pattern Keywords Context-Dependent**
- Problem: "juta" = spam in gaming, but legitimate in car reviews
- Solution: Per-channel whitelist/blacklist (documented, not implemented)
- Priority: HIGH (for future scalability)

**3. Repository Structure Messy**
- Problem: Services terlalu coupled, hardcoded values, no DI
- Solution: Clean architecture migration (planned in SERVICE_MIGRATION_PLAN.md)
- Timeline: 2-3 weeks
- Priority: HIGH (technical debt)

---

## 🧪 How to Test

### Quick Test (Unicode Detection)
```bash
php artisan test:complete-spam-detection
# Expected: 12 spam detected (CRITICAL), 196 clean (LOW)
# Result: 100% accuracy
```

### Cluster Detection Test
```bash
php artisan test:complete-spam-detection --fixture=cluster_detection_test.json
# Expected: 30 spam, 20 clean
# Current: 5 spam detected (MEDIUM), 45 ignored
# Issue: Low recall (16.7%)
```

### Export Results
```bash
php artisan test:complete-spam-detection --export --show-categories
# Creates: storage/app/spam_detection_results_TIMESTAMP.json
# Shows: CRITICAL/MEDIUM/LOW categorization
```

### Detailed Analysis
```bash
php artisan test:complete-spam-detection --detailed --limit=20
# Shows: Per-comment signals, scores, and reasoning
```

---

## 🔧 Key Configuration

### Thresholds (Current)
```
CRITICAL (Auto-delete):    >= 70 points
MEDIUM (Review queue):     40-69 points
LOW (Ignore):              0-39 points

Cluster Campaign Threshold: 50 points (was 70)
Similarity Distance:        0.3 (30% difference allowed)
```

### Scoring System
```
Unicode Fancy Fonts:        +95 (INSTANT SPAM)
Cluster Size (5+):          +40 (maxed)
Template Specificity:       0-30
Money Keywords:             +20
Urgency Language:           +15
Link Promotion:             +15
Author Diversity < 0.5:     +20 (bot indicator)
```

---

## 📚 Documentation Reference

### Algorithm Documentation
- **SPAM_DETECTION_ALGORITHM.md** (docs/) - Core algorithm explanation
- **SPAM_DETECTION_CONTEXT_ALGORITHM.md** (services/) - Per-channel design
- **SERVICE_MIGRATION_PLAN.md** (services/) - Clean architecture plan

### Test Results
- **SPAM_DETECTION_TEST_RESULTS.md** (docs/) - Benchmark results

---

## 🚀 Future Work (Documented, Not Implemented)

### Phase 1: Per-Channel Context System (HIGH PRIORITY)

**Goal:** Each channel owner can customize spam rules

**Database Schema:**
```sql
channel_spam_contexts:
├─ user_platform_id (FK)
├─ context_type (automotive/gaming/cooking/tech)
├─ whitelist_keywords (JSON) ← "juta, cepat" allowed
├─ blacklist_keywords (JSON) ← "M0NA4D" always spam
├─ custom_patterns (JSON)
└─ cluster_threshold (INT)
```

**Admin Panel Features:**
- Select video context type (preset whitelist)
- Add custom whitelist (allow keywords)
- Add custom blacklist (block brands)
- Adjust sensitivity (cluster threshold)
- View detection logs

**Benefits:**
- No false positives on car prices (whitelist "juta")
- No false positives on tech specs (whitelist "cepat")
- Context-aware scoring
- User control

### Phase 2: Clean Architecture Migration (HIGH PRIORITY)

**Goal:** Migrate services to clean architecture

**Structure:**
```
app/Domain/SpamDetection/         ← Business logic
app/Application/SpamDetection/     ← Use cases
app/Infrastructure/SpamDetection/  ← Detectors, repos
```

**Benefits:**
- Testable (interfaces)
- Maintainable (separation of concerns)
- Scalable (DI container)
- Clean (no hardcoded values)

**Timeline:** 2-3 weeks (see SERVICE_MIGRATION_PLAN.md)

### Phase 3: ML-Based Improvements (LOW PRIORITY)

- Auto-suggest whitelist from channel history
- Adaptive thresholds based on false positive rate
- Community-based blacklist sharing

---

## 🎯 Working with This System

### Adding New Keywords

**Location:** `app/Services/SpamDetection/PatternAnalyzer.php`

```php
// Add to MONEY_KEYWORDS
private const MONEY_KEYWORDS = [
    // ... existing
    'new_gambling_term',  // Add here
];
```

**Test:**
```bash
php artisan test:complete-spam-detection --detailed
```

### Adjusting Thresholds

**Cluster Threshold:**
```php
// SpamClusterDetector.php line 45
private const SPAM_CAMPAIGN_THRESHOLD = 50; // Lower = more sensitive
```

**Score Thresholds:**
```php
// TestCompleteSpamDetection.php
private function categorizeByScore(int $score): string
{
    if ($score >= 70) return 'CRITICAL'; // Auto-delete
    if ($score >= 40) return 'MEDIUM';   // Review
    return 'LOW'; // Ignore
}
```

### Adding New Detector

**Steps:**
1. Create class implementing detection logic
2. Add to HybridSpamDetector integration
3. Add scoring weight
4. Create test fixture
5. Run tests

**Example:**
```php
class NewDetector
{
    public function analyze(string $text): array
    {
        return [
            'score' => 0,
            'signals' => [],
        ];
    }
}
```

---

## 🔍 Debugging Guide

### Issue: False Positives

**Check:**
1. Which layer flagged it? (Pattern/Unicode/Cluster)
2. What signals triggered? (--detailed flag)
3. Is it context-dependent? (car price, tech specs)

**Solution:**
- If Pattern layer: Add to whitelist (future feature)
- If Unicode layer: Verify it's actually fancy Unicode
- If Cluster layer: Check similarity threshold

### Issue: Missed Spam

**Check:**
1. What's the spam technique? (Unicode/Cluster/Pattern)
2. What score did it get? (--detailed flag)
3. Which threshold is it below?

**Solution:**
- If < 50: Lower cluster threshold
- If 50-69: Review queue (manual review)
- If no signals: Add missing keywords

### Issue: Low Cluster Recall

**Root Cause:**
- Fuzzy matching not grouping variations (M0NA4D vs MONA4D)
- Similarity threshold too strict (0.3 = 30% difference)

**Debug:**
```bash
# Check cluster results in export
cat storage/app/spam_detection_results_*.json | jq '.categorization_summary.medium'

# Look for cluster size in signals
# Expected: "Cluster size: 10+ comments"
# Actual: "Cluster size: 2 comments" ← ISSUE
```

---

## ⚠️ Important Notes for AI Assistants

### When Working on Spam Detection

1. **ALWAYS test changes**
   ```bash
   php artisan test:complete-spam-detection
   php artisan test:complete-spam-detection --fixture=cluster_detection_test.json
   ```

2. **NEVER lower Unicode score**
   - Unicode = 95 points is SACRED
   - 100% accuracy, PROVEN in production
   - This is the PRIMARY defense

3. **Be careful with Pattern keywords**
   - Context-dependent (car/gaming/cooking)
   - High false positive risk
   - Future: Per-channel whitelist

4. **Repository is messy (known issue)**
   - Migration planned (see SERVICE_MIGRATION_PLAN.md)
   - Don't add more mess
   - Prepare for clean architecture

5. **Commit message format**
   ```
   feat(spam-detection): description
   fix(spam-detection): description
   docs(spam-detection): description
   ```

### When Asked About Spam Detection

**User might ask:**
- "Kenapa komentar legit kena spam?" → Check Pattern layer false positives
- "Kenapa spam lolos?" → Check score, might be < 70 (review queue)
- "Bagaimana deteksi cluster?" → Explain fuzzy matching + repetitive patterns
- "Mau ubah threshold" → Tanya context (lower = more sensitive, higher = less)

**Always reference:**
- Test results (--export output)
- Documentation (SPAM_DETECTION_ALGORITHM.md)
- Migration plans (SERVICE_MIGRATION_PLAN.md)

---

## 📊 Success Metrics

### Production Targets

**Primary Goal:**
- Unicode Detection: 100% ✅ ACHIEVED
- Zero gambling spam with fancy fonts

**Secondary Goals:**
- Cluster Detection: 80%+ recall (currently 16.7%)
- False Positive Rate: < 1% (currently ~5% on pattern layer)
- User Satisfaction: 90%+

### Current Performance

```
Dataset: complete_sample_comments_02.json (208 comments)
├─ Spam: 12 (all Unicode)
├─ Clean: 196
└─ Results:
   ├─ CRITICAL: 12 detected ✅
   ├─ False Positives: 0 ✅
   └─ Accuracy: 100% ✅

Dataset: cluster_detection_test.json (50 comments)
├─ Spam: 30 (3 campaigns, no Unicode)
├─ Clean: 20
└─ Results:
   ├─ MEDIUM: 5 detected ⚠️
   ├─ False Positives: 0 ✅
   └─ Recall: 16.7% ⚠️ (needs improvement)
```

---

## 🔐 Security Considerations

### Spam Detection Bypass Attempts

**Known Techniques:**
1. **Unicode fonts** → 100% caught ✅
2. **Slight variations** (M0NA4D, MONA4D) → Cluster detection ⚠️
3. **High author diversity** → Lowers cluster score ⚠️
4. **No money keywords** → Pattern detection misses ⚠️

**Future Mitigations:**
- Per-channel blacklist (brand names)
- Pattern learning from user reports
- Community-based spam database

---

## 💡 Quick Reference

### File Shortcuts
```bash
# Main orchestrator
vim app/Services/SpamDetection/HybridSpamDetector.php

# Unicode detector (100% working)
vim app/Services/SpamDetection/UnicodeDetector.php

# Pattern keywords
vim app/Services/SpamDetection/PatternAnalyzer.php

# Cluster detection
vim app/Services/SpamDetection/SpamClusterDetector.php

# Test command
vim app/Console/Commands/TestCompleteSpamDetection.php
```

### Common Commands
```bash
# Run tests
php artisan test:complete-spam-detection
php artisan test:complete-spam-detection --fixture=cluster_detection_test.json

# Export results
php artisan test:complete-spam-detection --export --show-categories

# Detailed analysis
php artisan test:complete-spam-detection --detailed --limit=10

# Check latest export
cat storage/app/spam_detection_results_*.json | jq '.categorization_summary'
```

### Git Workflow
```bash
# Check current changes
git status

# Commit improvements
git add app/Services/SpamDetection/
git commit -m "feat(spam-detection): description"

# Push to feature branch
git push origin feature/security-and-ai-improvements
```

---

## 🎓 Learning Resources

### Understanding the Algorithm

1. **Read first:** `docs/SPAM_DETECTION_ALGORITHM.md`
   - Core detection pipeline
   - Scoring breakdown
   - Examples with real spam

2. **Then read:** `app/Services/SpamDetection/SPAM_DETECTION_CONTEXT_ALGORITHM.md`
   - Per-channel context design
   - Future admin panel
   - Database schema

3. **For migration:** `app/Services/SpamDetection/SERVICE_MIGRATION_PLAN.md`
   - Clean architecture plan
   - Phase-by-phase steps
   - Timeline: 2-3 weeks

### Code Walkthrough

**Detection Flow:**
```
1. HybridSpamDetector.detectBatch(comments)
2. ├─ SpamClusterDetector.analyzeCommentBatch()
   │  ├─ Normalize Unicode → ASCII
   │  ├─ Find similar comments (FuzzyMatcher)
   │  ├─ Score clusters (size + template + patterns)
   │  └─ Flag campaigns >= 50 points
3. ├─ detectIndividualSpam() for non-cluster spam
   │  ├─ UnicodeDetector (+95 if fancy fonts)
   │  ├─ PatternAnalyzer (money/urgency/link)
   │  └─ Score each comment
4. └─ mergeDetectionResults()
   ├─ Combine cluster + individual
   ├─ Assign scores to comment IDs
   └─ Return spam_campaigns array
```

---

## 🆘 Emergency Contacts

### If Spam Detection Breaks

**Symptoms:**
- No spam detected (all score 0)
- Everything flagged as spam (100% false positives)
- Errors in test command

**First Steps:**
1. Check recent commits: `git log --oneline -10`
2. Run tests: `php artisan test:complete-spam-detection`
3. Check export: Look for empty signals or wrong scores
4. Rollback: `git revert <commit-hash>`

**Common Fixes:**
- Empty signals → Check detector integration in HybridDetector
- Wrong scores → Check threshold constants
- Clustering broken → Check FuzzyMatcher or similarity distance

---

**PENTING:** Repository ini "sudah kacau" (user's words) dan akan dimigration.
Jangan tambah technical debt. Prepare for clean architecture migration.

**Prinsip:** "Lakukan yang terbaik demi bangsa yang harus terhindar dari pengaruh judola" 🇮🇩

---

**END OF CONTEXT**

Last Updated: 2026-01-10 by Claude Code Session
Next Session: Reference this file for full context
