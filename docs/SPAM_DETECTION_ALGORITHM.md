# 🔍 Spam Detection Algorithm Documentation

> **Comprehensive guide to DELCOM's Hybrid Spam Detection System**

## 📋 Table of Contents

- [Overview](#overview)
- [System Architecture](#system-architecture)
- [Scanning Process Flow](#scanning-process-flow)
- [Spam Detection Pipeline](#spam-detection-pipeline)
- [Detection Components](#detection-components)
- [Scoring System](#scoring-system)
- [Decision Logic](#decision-logic)
- [API Integration](#api-integration)
- [Examples](#examples)

---

## Overview

DELCOM's spam detection system focuses on **what YouTube/Instagram/TikTok CAN'T detect**:

### ❌ What Platforms Already Handle
- Regex keyword matching
- Basic spam patterns
- Simple text filters

### ✅ What DELCOM Detects
- **Bot Campaign Detection** - Coordinated spam attacks with similar patterns
- **Unicode Bypass Detection** - Fancy fonts used to evade filters (𝗝𝗨𝗗𝗢𝗟 → JUDOL)
- **Contextual Analysis** - Reduces false positives (educational vs promotional)
- **Implicit Patterns** - Gambling/scam patterns without obvious keywords

---

## System Architecture

```mermaid
graph TB
    subgraph "User Layer"
        A[Content Creator]
    end

    subgraph "DELCOM Platform"
        B[Web Controller]
        C[Platform Service Factory]
        D[YouTube Service]
        E[Instagram Service]
        F[TikTok Service]

        subgraph "Spam Detection Engine"
            G[HybridSpamDetector]
            H[SpamClusterDetector]
            I[UnicodeDetector]
            J[ContextualAnalyzer]
            K[PatternAnalyzer]
        end

        L[(Database)]
    end

    subgraph "External APIs"
        M[YouTube Data API]
        N[Instagram Graph API]
        O[TikTok API]
    end

    A -->|1. Trigger Scan| B
    B --> C
    C -->|Route to| D
    C -->|Route to| E
    C -->|Route to| F

    D -->|Fetch Data| M
    E -->|Fetch Data| N
    F -->|Fetch Data| O

    D -->|Comments| G
    E -->|Comments| G
    F -->|Comments| G

    G --> H
    G --> I
    G --> J
    H --> K

    G -->|Results| L
    L -->|Display| B
    B -->|Review Queue| A
```

---

## Scanning Process Flow

### Complete Sequence Diagram

```mermaid
sequenceDiagram
    actor User as Content Creator
    participant UI as Web Dashboard
    participant Ctrl as Controller
    participant PS as Platform Service
    participant API as External API
    participant SD as HybridSpamDetector
    participant DB as Database

    User->>UI: Click "Scan Videos"
    UI->>Ctrl: POST /scan/start

    Note over Ctrl,API: Phase 1: Fetch Content
    Ctrl->>PS: getContents()
    PS->>API: GET /videos
    API-->>PS: Video List [25 items]
    PS-->>Ctrl: Videos with metadata

    Note over Ctrl,SD: Phase 2: Fetch & Analyze Comments
    loop For Each Video
        Ctrl->>PS: getComments(videoId)
        PS->>API: GET /comments?videoId={id}
        API-->>PS: Comments [100 items]
        PS-->>Ctrl: Comment batch

        Ctrl->>SD: analyzeCommentBatch(comments)

        activate SD
        SD->>SD: 1. Normalize Unicode
        SD->>SD: 2. Find Clusters
        SD->>SD: 3. Score Patterns
        SD->>SD: 4. Context Analysis
        SD-->>Ctrl: Spam Report
        deactivate SD

        Ctrl->>DB: Save spam clusters
    end

    Note over Ctrl,User: Phase 3: Display Results
    Ctrl-->>UI: Scan Complete
    UI-->>User: Show Review Queue

    User->>UI: Click "Delete Spam"
    UI->>Ctrl: POST /comments/delete
    Ctrl->>PS: deleteComment(commentId)
    PS->>API: DELETE /comments/{id}
    API-->>PS: Success
    PS-->>Ctrl: Deleted
    Ctrl-->>UI: Confirm
    UI-->>User: ✓ Deleted
```

---

## Spam Detection Pipeline

### High-Level Pipeline

```mermaid
flowchart LR
    A[Raw Comments] --> B[Normalization]
    B --> C[Cluster Detection]
    C --> D[Pattern Analysis]
    D --> E[Context Analysis]
    E --> F[Scoring]
    F --> G{Score >= 70?}
    G -->|Yes| H[SPAM ❌]
    G -->|No| I[CLEAN ✅]
```

### Detailed Processing Steps

```mermaid
graph TD
    Start([Input: Comment Batch]) --> Step1[Step 1: Normalization]

    Step1 --> Step1a[Unicode → ASCII]
    Step1 --> Step1b[Lowercase]
    Step1 --> Step1c[Trim whitespace]

    Step1a --> Step2[Step 2: Cluster Detection]
    Step1b --> Step2
    Step1c --> Step2

    Step2 --> Step2a{Find Similar<br/>Comments}
    Step2a -->|Similarity > 70%| Step2b[Group into Cluster]
    Step2a -->|Similarity < 70%| Step2c[Individual Comment]

    Step2b --> Step3[Step 3: Extract Template]
    Step2c --> Step3

    Step3 --> Step3a["Template:<br/>'wd [N]jt 🤑'"]

    Step3a --> Step4[Step 4: Pattern Analysis]

    Step4 --> Step4a{Money<br/>Keywords?}
    Step4 --> Step4b{Urgency<br/>Language?}
    Step4 --> Step4c{Link<br/>Promotion?}
    Step4 --> Step4d{Emoji<br/>Density?}
    Step4 --> Step4e{CAPS<br/>Ratio?}

    Step4a -->|Yes| Score1[+10 pts]
    Step4b -->|Yes| Score2[+10 pts]
    Step4c -->|Yes| Score3[+15 pts]
    Step4d -->|High| Score4[+5 pts]
    Step4e -->|High| Score5[+5 pts]

    Score1 --> Step5[Step 5: Context Analysis]
    Score2 --> Step5
    Score3 --> Step5
    Score4 --> Step5
    Score5 --> Step5

    Step5 --> Step5a{Educational<br/>Content?}
    Step5 --> Step5b{Question<br/>Pattern?}
    Step5 --> Step5c{Warning<br/>Context?}

    Step5a -->|Yes| Adjust1[-30 pts]
    Step5b -->|Yes| Adjust2[-20 pts]
    Step5c -->|Yes| Adjust3[-25 pts]

    Adjust1 --> Step6[Step 6: Calculate Total]
    Adjust2 --> Step6
    Adjust3 --> Step6

    Step6 --> Decision{Total >= 70?}

    Decision -->|Yes| Spam[MARK AS SPAM ❌]
    Decision -->|No| Clean[ALLOW ✅]

    Spam --> End([Output: Spam Report])
    Clean --> End
```

---

## Detection Components

### 1. Cluster Detection (Bot Campaign)

```mermaid
graph LR
    subgraph "Input Comments"
        C1["Comment 1:<br/>'Gw wd 5jt 🤑'"]
        C2["Comment 2:<br/>'Gw wd 3jt 🤑'"]
        C3["Comment 3:<br/>'Gw wd bilek 🤑'"]
        C4["Comment 4:<br/>'Nice video!'"]
    end

    subgraph "Similarity Analysis"
        C1 --> S1{Levenshtein<br/>Distance}
        C2 --> S1
        C3 --> S1
        C4 --> S2{Levenshtein<br/>Distance}

        S1 -->|< 30% diff| Cluster[Cluster A<br/>3 members]
        S2 -->|> 30% diff| Single[No Cluster]
    end

    subgraph "Template Extraction"
        Cluster --> T1["Pattern:<br/>'Gw wd [N]jt 🤑'"]
        T1 --> Score["+25 points<br/>(Cluster size)"]
    end

    Single --> NoScore["0 points"]
```

**Algorithm:**
```javascript
function findClusters(comments) {
    clusters = []
    processed = []

    for (i = 0; i < comments.length; i++) {
        if (processed.includes(i)) continue

        cluster = [comments[i]]

        for (j = i+1; j < comments.length; j++) {
            if (processed.includes(j)) continue

            similarity = calculateSimilarity(
                comments[i].text,
                comments[j].text
            )

            if (similarity > 0.7) {  // 70% similar
                cluster.push(comments[j])
                processed.push(j)
            }
        }

        if (cluster.length >= 2) {
            clusters.push(cluster)
        }
    }

    return clusters
}
```

### 2. Unicode Detection

```mermaid
graph TD
    Input["Input: '𝗝𝗨𝗗𝗢𝗟 𝗚𝗔𝗖𝗢𝗥 🎰'"] --> Detect{Has Fancy<br/>Unicode?}

    Detect -->|Yes| Ranges[Check Unicode Ranges]
    Detect -->|No| Clean[Regular Text]

    Ranges --> R1[Mathematical Bold:<br/>0x1D400-0x1D433]
    Ranges --> R2[Fullwidth Latin:<br/>0xFF21-0xFF5A]
    Ranges --> R3[Circled:<br/>0x24B6-0x24E9]

    R1 --> Found{Found?}
    R2 --> Found
    R3 --> Found

    Found -->|Yes| Normalize[Normalize to ASCII]
    Normalize --> Result["Output: 'JUDOL GACOR 🎰'"]
    Result --> Flag["+15 SPAM points"]

    Found -->|No| Clean
    Clean --> NoFlag["0 points"]
```

### 3. Pattern Analysis

```mermaid
mindmap
  root((Pattern<br/>Analysis))
    Money
      Keywords
        jt, juta
        wd, withdraw
        profit, cuan
        jackpot, maxwin
      Score
        +10 points
    Urgency
      Keywords
        sekarang
        buruan, cepat
        limited, terbatas
      Score
        +10 points
    Links
      Keywords
        klik link
        cek bio
        link di bio
      Score
        +15 points
    Visual
      Emoji Density
        > 15% = spam
        +5 points
      CAPS Ratio
        > 50% = shouting
        +5 points
```

### 4. Context Analysis (False Positive Filter)

```mermaid
flowchart TD
    Comment[Comment Text] --> Check1{Contains<br/>Educational<br/>Keywords?}

    Check1 -->|Yes| E1["'penjelasan'<br/>'tutorial'<br/>'cara kerja'"]
    Check1 -->|No| Check2{Is<br/>Question?}

    E1 --> Reduce1["-30 points"]

    Check2 -->|Yes| Q1["Starts with:<br/>'bagaimana'<br/>'apakah'<br/>'mengapa'"]
    Check2 -->|No| Check3{Has<br/>Warning<br/>Context?}

    Q1 --> Reduce2["-20 points"]

    Check3 -->|Yes| W1["'bahaya'<br/>'hati-hati'<br/>'waspada'"]
    Check3 -->|No| Check4{Has<br/>Promotional<br/>Indicators?}

    W1 --> Reduce3["-25 points"]

    Check4 -->|Yes| P1["'klik'<br/>'daftar'<br/>'buruan'"]
    Check4 -->|No| Neutral[Neutral<br/>0 points]

    P1 --> Increase["+15 points"]

    Reduce1 --> Result[Adjusted Score]
    Reduce2 --> Result
    Reduce3 --> Result
    Increase --> Result
    Neutral --> Result
```

---

## Scoring System

### Score Breakdown

```mermaid
pie title Spam Score Distribution (Max 100 points)
    "Cluster Size" : 40
    "Template Specificity" : 30
    "Pattern Signals" : 45
    "Author Diversity" : 20
    "Unicode Detection" : 15
```

### Scoring Formula

| Component | Condition | Points |
|-----------|-----------|--------|
| **Cluster Size** | 2 similar comments | +20 |
| | 3-4 similar comments | +25 |
| | 5+ similar comments | +40 |
| **Template Specificity** | Generic template | +10 |
| | Moderate specificity | +20 |
| | High specificity | +30 |
| **Money Keywords** | Contains 1+ money terms | +10 |
| **Urgency Language** | Contains 1+ urgency words | +10 |
| **Link Promotion** | Contains call-to-action | +15 |
| **Emoji Density** | > 15% of text | +5 |
| **CAPS Ratio** | > 50% uppercase | +5 |
| **Author Diversity** | Same author (bot) | +20 |
| | Multiple authors (coordinated) | +10 |
| **Unicode Detection** | Fancy fonts detected | +15 |
| **Educational Context** | Educational content | -30 |
| **Question Pattern** | Legitimate question | -20 |
| **Warning Context** | Cautionary content | -25 |

### Threshold

```mermaid
graph LR
    S[Score] --> D{Value}
    D -->|0-39| Low[Low Risk<br/>✅ CLEAN]
    D -->|40-69| Medium[Medium Risk<br/>⚠️ REVIEW]
    D -->|70-100| High[High Risk<br/>❌ SPAM]
```

---

## Decision Logic

### Spam Detection Decision Tree

```mermaid
graph TD
    Start([New Comment]) --> Q1{Part of<br/>Cluster?}

    Q1 -->|Yes, 5+ members| Score1[Score: +40]
    Q1 -->|Yes, 2-4 members| Score2[Score: +20]
    Q1 -->|No| Score3[Score: 0]

    Score1 --> Q2{Has Fancy<br/>Unicode?}
    Score2 --> Q2
    Score3 --> Q2

    Q2 -->|Yes| Score4[Score: +15]
    Q2 -->|No| Q3

    Score4 --> Q3{Money<br/>Keywords?}

    Q3 -->|Yes| Score5[Score: +10]
    Q3 -->|No| Q4

    Score5 --> Q4{Urgency<br/>Language?}

    Q4 -->|Yes| Score6[Score: +10]
    Q4 -->|No| Q5

    Score6 --> Q5{Link<br/>Promotion?}

    Q5 -->|Yes| Score7[Score: +15]
    Q5 -->|No| Q6

    Score7 --> Q6{Educational<br/>Content?}

    Q6 -->|Yes| Adjust1[Score: -30]
    Q6 -->|No| Q7

    Adjust1 --> Q7{Question<br/>Pattern?}

    Q7 -->|Yes| Adjust2[Score: -20]
    Q7 -->|No| Total

    Adjust2 --> Total[Calculate Total]

    Total --> Final{Total >= 70?}

    Final -->|Yes| Spam[🚨 SPAM<br/>Delete/Hide]
    Final -->|No| Clean[✅ CLEAN<br/>Allow]
```

---

## API Integration

### Platform Service Architecture

```mermaid
graph TB
    subgraph "Platform Service Factory"
        Factory[PlatformServiceFactory]
    end

    subgraph "Service Implementations"
        YouTube[YouTubeService]
        Instagram[InstagramService]
        TikTok[TikTokService]
    end

    subgraph "Common Interface"
        Interface[PlatformServiceInterface]
        Interface --> M1[getAccount]
        Interface --> M2[getContents]
        Interface --> M3[getComments]
        Interface --> M4[deleteComment]
        Interface --> M5[hideComment]
    end

    Factory -->|creates| YouTube
    Factory -->|creates| Instagram
    Factory -->|creates| TikTok

    YouTube -->|implements| Interface
    Instagram -->|implements| Interface
    TikTok -->|implements| Interface

    YouTube --> API1[YouTube Data API v3]
    Instagram --> API2[Instagram Graph API]
    TikTok --> API3[TikTok Extension Only]
```

### Rate Limiting Flow

```mermaid
sequenceDiagram
    participant User
    participant Service as YouTubeService
    participant RL as RateLimiter
    participant Cache
    participant API as YouTube API

    User->>Service: Request to fetch comments
    Service->>RL: canPerformAction(user)

    RL->>Cache: GET rate_limit:user:{id}
    Cache-->>RL: requests_count: 25

    RL->>RL: Check: 25 < 30 (max/min)

    alt Within Rate Limit
        RL-->>Service: ✓ Allowed
        Service->>RL: incrementRequestCount(user)
        RL->>Cache: INCREMENT rate_limit:user:{id}

        Service->>RL: hasQuotaFor('list_comments')
        RL->>Cache: GET quota:daily
        Cache-->>RL: used: 8500
        RL->>RL: Check: 8500 + 1 < 10000
        RL-->>Service: ✓ Has quota

        Service->>API: GET /commentThreads
        API-->>Service: Comments data

        Service->>RL: trackQuotaUsage('list_comments')
        RL->>Cache: INCREMENT quota:daily

        Service-->>User: Comments returned
    else Rate Limited
        RL-->>Service: ✗ Rate limited
        Service-->>User: Error: Too many requests
    end
```

---

## Examples

### Example 1: Bot Campaign Detection

**Input Comments:**
```json
[
  { "id": "1", "text": "Gw yang habis wd 5jt 🤑", "author": "bot123" },
  { "id": "2", "text": "Gw yang habis wd 3jt 🤑", "author": "bot123" },
  { "id": "3", "text": "Gw yang habis wd bilek 🤑", "author": "bot123" },
  { "id": "4", "text": "Nice video!", "author": "realuser" }
]
```

**Processing:**

```mermaid
graph LR
    A["Comments 1-3"] --> B[Normalized:<br/>'gw yang habis wd']
    B --> C{Similarity<br/>Check}
    C -->|85% similar| D[Cluster Found!]
    D --> E["Template:<br/>'gw yang habis wd [N]'"]
    E --> F[Scoring]

    F --> F1["+25 cluster size"]
    F --> F2["+10 money 'wd'"]
    F --> F3["+20 same author"]
    F --> F4["+5 emoji density"]

    F1 --> Total["Total: 60 points"]
    F2 --> Total
    F3 --> Total
    F4 --> Total

    Total --> Verdict{">= 70?"}
    Verdict -->|No| Result["Medium Risk<br/>⚠️ Review"]
```

**Output:**
```json
{
  "spam_campaigns": [
    {
      "score": 60,
      "severity": "MEDIUM",
      "member_count": 3,
      "template": "gw yang habis wd [N]",
      "comment_ids": ["1", "2", "3"],
      "authors": ["bot123"],
      "signals": [
        "Cluster size: 3 (+25)",
        "Money mentions (+10)",
        "Low author diversity (+20)",
        "High emoji density (+5)"
      ],
      "recommendation": "Review manually"
    }
  ]
}
```

### Example 2: Unicode Bypass Detection

**Input:**
```json
{
  "id": "5",
  "text": "𝗝𝗨𝗗𝗢𝗟 𝗚𝗔𝗖𝗢𝗥 𝟭𝟬𝟬% 𝗪𝗜𝗡 🎰 klik link bio!",
  "author": "spammer999"
}
```

**Processing Flow:**

```mermaid
sequenceDiagram
    participant Input as Raw Comment
    participant Unicode as UnicodeDetector
    participant Pattern as PatternAnalyzer
    participant Score as Scorer

    Input->>Unicode: Detect fancy Unicode
    Unicode->>Unicode: Scan codepoints
    Note over Unicode: Found: Mathematical Bold<br/>0x1D400-0x1D433
    Unicode-->>Score: +15 points (Unicode detected)

    Input->>Unicode: Normalize to ASCII
    Unicode-->>Pattern: "JUDOL GACOR 100% WIN 🎰..."

    Pattern->>Pattern: Analyze patterns
    Note over Pattern: Found: 'gacor' (gambling)<br/>Found: 'klik link'
    Pattern-->>Score: +10 money<br/>+15 link promo

    Score->>Score: Calculate total
    Note over Score: 15 + 10 + 15 = 40<br/>Below threshold (70)
    Score-->>Input: MEDIUM RISK ⚠️
```

**Output:**
```json
{
  "score": 40,
  "is_spam": false,
  "signals": [
    "Unicode fancy fonts detected (+15)",
    "Money/gambling keywords (+10)",
    "Link promotion (+15)"
  ],
  "recommendation": "Monitor - Below spam threshold but suspicious"
}
```

### Example 3: False Positive Prevention

**Input:**
```json
{
  "id": "6",
  "text": "Video ini menjelaskan cara kerja slot machine dengan baik. Bagaimana menurut kalian?",
  "author": "educator"
}
```

**Context Analysis:**

```mermaid
graph TD
    Input["Input: Educational comment"] --> Check1{Contains<br/>'slot'?}
    Check1 -->|Yes| Base[Base Score: +10<br/>Money keyword]

    Base --> Check2{Contains<br/>Educational<br/>Keywords?}
    Check2 -->|Yes| Found["Found:<br/>'menjelaskan'<br/>'cara kerja'"]

    Found --> Adjust[Score: -30<br/>Educational context]

    Adjust --> Check3{Is<br/>Question?}
    Check3 -->|Yes| Q["Has '?'<br/>and 'bagaimana'"]

    Q --> Adjust2[Score: -20<br/>Question pattern]

    Adjust2 --> Total[Total Score:<br/>10 - 30 - 20 = -40]

    Total --> Result[✅ CLEAN<br/>Allow comment]
```

**Output:**
```json
{
  "score": -40,
  "is_spam": false,
  "context": "educational",
  "signals": [
    "Educational content detected (-30)",
    "Question pattern detected (-20)",
    "Money keyword found (+10)"
  ],
  "verdict": "CLEAN - Educational discussion"
}
```

---

## Performance Characteristics

### Batch Processing

```mermaid
gantt
    title Comment Scanning Timeline (100 comments)
    dateFormat  s
    axisFormat %Ss

    section Fetch
    API Request      :a1, 0, 2s
    Receive Data     :a2, after a1, 1s

    section Detection
    Normalization    :b1, after a2, 0.5s
    Cluster Analysis :b2, after b1, 1.5s
    Pattern Scoring  :b3, after b2, 1s
    Context Analysis :b4, after b2, 1s

    section Storage
    Save Results     :c1, after b3, 0.5s

    section Total
    Complete         :milestone, after c1, 0s
```

**Time Complexity:**
- Normalization: O(n) where n = comment length
- Cluster Detection: O(n²) where n = comment count
- Pattern Analysis: O(n×m) where m = pattern keywords
- Context Analysis: O(n×k) where k = context keywords

**Optimizations:**
- Batch size: 25-100 comments per request
- Cache: Rate limits & quota usage
- Async: Queue processing for large scans
- Pagination: Fetch comments in chunks

---

## Testing Checklist

### Unit Tests
- [ ] UnicodeDetector normalizes all fancy font ranges
- [ ] FuzzyMatcher handles strings > 255 chars
- [ ] PatternAnalyzer detects all keyword categories
- [ ] ContextualAnalyzer reduces false positives
- [ ] SpamClusterDetector finds similar patterns

### Integration Tests
- [ ] YouTubeService fetches and parses comments correctly
- [ ] InstagramService handles pagination
- [ ] TikTokService gracefully handles extension-only mode
- [ ] Rate limiter prevents quota exhaustion
- [ ] Token refresh works before expiry

### End-to-End Tests
- [ ] Full scan completes successfully
- [ ] Spam detected and flagged correctly
- [ ] Clean comments not flagged
- [ ] Delete action removes comment from platform
- [ ] Results persist in database

---

## Troubleshooting

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| No spam detected | Threshold too high | Lower from 70 to 60 |
| Too many false positives | Context analysis disabled | Enable ContextualAnalyzer |
| Rate limit exceeded | Too many requests | Increase delay between batches |
| Token expired | No auto-refresh | Check token expiry logic |
| Levenshtein error | String > 255 chars | Use fallback algorithm |

### Debug Mode

Enable verbose logging:
```php
// In HybridSpamDetector
config(['app.debug' => true]);

Log::info('Cluster detected', [
    'size' => $cluster->size(),
    'template' => $cluster->template,
    'score' => $cluster->score,
]);
```

---

## Future Enhancements

```mermaid
mindmap
  root((Future<br/>Features))
    Machine Learning
      Train on labeled data
      Auto-adjust thresholds
      Pattern recognition
    Multi-Language
      Support English
      Support other languages
      Auto-detect language
    Real-Time
      WebSocket updates
      Live monitoring
      Instant alerts
    Advanced Analytics
      Trend analysis
      Spam heatmaps
      Bot network graphs
    API Expansion
      Public API
      Webhooks
      Third-party integrations
```

---

## References

### Internal Documentation
- [Code Audit Report](./AUDIT_REPORT.md)
- [API Documentation](./API_REFERENCE.md)
- [Setup Guide](./SETUP.md)

### External Resources
- [YouTube Data API](https://developers.google.com/youtube/v3)
- [Instagram Graph API](https://developers.facebook.com/docs/instagram-api)
- [Unicode Normalization](https://unicode.org/reports/tr15/)
- [Levenshtein Distance](https://en.wikipedia.org/wiki/Levenshtein_distance)

---

## Contributors

- **Spam Detection Engine**: Claude AI + DELCOM Team
- **Architecture**: Laravel 12 + React 19
- **Last Updated**: January 2026

---

**Questions or feedback?** Open an issue on GitHub or contact the dev team.

