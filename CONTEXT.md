# DelCom - Project Context Document
> Dokumen ini berisi semua konteks dan perencanaan project untuk memudahkan kelanjutan development di sesi berikutnya.

**Last Updated**: 13 Desember 2025
**Status**: Week 1 Complete - Core Models & Services Done

---

## 1. PROJECT OVERVIEW

### Apa itu DelCom?
**DelCom** (Delete Comment) adalah tool moderasi komentar untuk content creator yang menggabungkan:
- **Website Dashboard** (Laravel) untuk management, settings, analytics
- **Chrome Extension** sebagai executor untuk delete komentar di berbagai platform

### Problem yang Diselesaikan
Content creator Indonesia sering mendapat spam komentar:
- Judi online (slot gacor, togel, dll)
- Buzzer/bot
- Link spam
- Komentar toxic

Tool yang ada di pasar:
- Mahal ($29-199/bulan)
- Tidak support semua platform (terutama YouTube + TikTok combo)
- Tidak ada yang fokus ke pattern spam Indonesia

### Target Platform
| Platform | Method | API Status | Feasibility |
|----------|--------|------------|-------------|
| YouTube | Official API | ✅ Free, Legal | ✅ SANGAT FEASIBLE |
| Instagram | Graph API | ✅ Free, Legal | ✅ FEASIBLE (perlu Business Account) |
| X/Twitter | API v2 | ⚠️ $100/mo | ✅ FEASIBLE (berbayar) |
| Threads | Meta API | ✅ Free, Legal | ✅ FEASIBLE (hide only) |
| TikTok | DOM Automation | ❌ No API | ⚠️ GREY AREA (via Extension) |

---

## 2. RISET KOMPETITOR

### Kompetitor Utama
| Tool | Harga | Platform | Kelebihan | Kekurangan |
|------|-------|----------|-----------|------------|
| CommentGuard | $29-199/mo | FB, IG only | Murah, unlimited users | Tidak ada YouTube, TikTok |
| Brandwise AI | $49-269/mo | FB, IG, TikTok | AI-powered | Tidak ada YouTube |
| NapoleonCat | $79-465/mo | Multi | Lengkap | Mahal untuk moderation |
| Statusbrew | $69-229/mo | Multi | Good value | Pricing tidak transparan |

### Gap di Pasar (Peluang DelCom)
1. ❌ Tidak ada yang pakai format **Chrome Extension**
2. ❌ Kebanyakan skip **YouTube** (padahal creator terbesar)
3. ❌ Tidak ada yang fokus **spam pattern Indonesia**
4. ❌ Multi-platform tools **sangat mahal** ($119+/mo)

### Positioning DelCom
- Chrome Extension pertama untuk comment moderation
- Support YouTube + Instagram + TikTok + X + Threads
- Harga target: $19-29/mo (lebih murah dari semua kompetitor)
- Filter khusus spam Indonesia

---

## 3. ARSITEKTUR SISTEM

### Two-Tier Architecture

```
┌─────────────────────────────────────────────────────────────┐
│  TIER 1: BACKGROUND JOBS (Server-side)                      │
│  Untuk platform DENGAN Official API                         │
│                                                             │
│  ✅ YouTube (API) → Background Job → Auto Delete            │
│  ✅ Instagram (Graph API) → Background Job → Auto Delete    │
│  ✅ Twitter (API v2) → Background Job → Auto Delete         │
│  ✅ Threads (API) → Background Job → Auto Hide              │
│                                                             │
│  Keuntungan: Jalan 24/7, tidak perlu browser terbuka        │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│  TIER 2: CHROME EXTENSION (Client-side)                     │
│  Untuk platform TANPA Official API                          │
│                                                             │
│  ⚠️ TikTok → Extension → DOM Automation → Delete            │
│                                                             │
│  Cara Kerja:                                                │
│  - Extension jalan saat browser aktif & user login          │
│  - Mimic human behavior (random delays, natural clicks)     │
│  - Scan comments → Match filters → Execute delete           │
│  - Terlihat organik, meminimalisir deteksi                  │
└─────────────────────────────────────────────────────────────┘
```

### System Flow

```
User Install Extension
        ↓
Login ke Website (Laravel)
        ↓
Connect Platforms (OAuth)
├── YouTube → OAuth 2.0 → Store Token
├── Instagram → Graph API → Store Token
├── Twitter → OAuth 2.0 + PKCE → Store Token
├── Threads → OAuth 2.0 → Store Token
└── TikTok → No OAuth (session-based via Extension)
        ↓
Setup Filters
├── Keyword: "slot gacor", "togel", "judi"
├── Regex: link patterns
├── Presets: Indonesia spam patterns
        ↓
Background Jobs (Tier 1)
├── Scheduled scan every X minutes
├── Fetch comments via API
├── Match against filters
├── Delete matching comments
├── Log to database
        ↓
Extension (Tier 2 - TikTok)
├── Detect when user on TikTok
├── Scan visible comments
├── Match against filters (synced from server)
├── Human-like delete (random delays)
├── Report back to server
```

---

## 4. TECH STACK

### Backend - Laravel Starter Kit
**Repository**: https://github.com/RahmatRafiq/laravel-12-spattie-media-and-roles

Yang Sudah Termasuk:
- Laravel 12 + Inertia.js 2.0 + React 19 + TypeScript
- MariaDB 11, Redis
- Laravel Breeze (Auth) + Socialite (Social Login)
- Spatie Permission 6 (Roles & Permissions)
- Spatie Media Library 11 (File Management)
- Spatie Activity Log 4 (untuk Moderation Logs!)
- Laravel Reverb 1.4 (WebSocket - untuk real-time updates!)
- Tailwind CSS 4.0 + shadcn/ui

Yang Perlu Ditambahkan:
- OAuth controllers untuk YouTube, Instagram, Twitter, Threads
- Platform service classes
- Background scan/delete jobs
- Filter management system
- Extension API endpoints

### Chrome Extension
- Manifest V3
- Service Worker (background)
- Content Scripts (TikTok DOM manipulation)
- Vanilla JavaScript
- Communication via REST API + WebSocket (Reverb)

---

## 5. DATABASE SCHEMA

### Core Tables

```sql
-- platforms (reference data)
CREATE TABLE platforms (
    id BIGINT PRIMARY KEY,
    name VARCHAR(50) UNIQUE,        -- youtube, instagram, tiktok, twitter, threads
    display_name VARCHAR(100),
    tier ENUM('api', 'extension'),  -- api = background job, extension = DOM
    api_base_url VARCHAR(255),
    oauth_authorize_url VARCHAR(255),
    oauth_token_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE
);

-- user_platforms (connected accounts)
CREATE TABLE user_platforms (
    id BIGINT PRIMARY KEY,
    user_id BIGINT,
    platform_id BIGINT,
    platform_user_id VARCHAR(255),
    platform_username VARCHAR(255),
    platform_channel_id VARCHAR(255),
    access_token TEXT,              -- encrypted
    refresh_token TEXT,             -- encrypted
    token_expires_at TIMESTAMP,
    scopes JSON,
    is_active BOOLEAN DEFAULT TRUE,
    auto_moderation_enabled BOOLEAN DEFAULT FALSE,
    scan_frequency_minutes INT DEFAULT 60,
    last_scanned_at TIMESTAMP
);

-- filter_groups
CREATE TABLE filter_groups (
    id BIGINT PRIMARY KEY,
    user_id BIGINT,
    name VARCHAR(100),
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    applies_to_platforms JSON       -- ["youtube", "instagram"]
);

-- filters
CREATE TABLE filters (
    id BIGINT PRIMARY KEY,
    filter_group_id BIGINT,
    type ENUM('keyword', 'phrase', 'regex', 'username', 'url', 'emoji_spam', 'repeat_char'),
    pattern VARCHAR(500),
    match_type ENUM('exact', 'contains', 'starts_with', 'ends_with', 'regex'),
    case_sensitive BOOLEAN DEFAULT FALSE,
    action ENUM('delete', 'hide', 'flag', 'report') DEFAULT 'delete',
    priority INT DEFAULT 0,
    hit_count INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE
);

-- moderation_logs
CREATE TABLE moderation_logs (
    id BIGINT PRIMARY KEY,
    user_id BIGINT,
    user_platform_id BIGINT,
    platform_comment_id VARCHAR(255),
    video_id VARCHAR(255),
    commenter_username VARCHAR(255),
    comment_text TEXT,
    matched_filter_id BIGINT,
    matched_pattern VARCHAR(500),
    action_taken ENUM('deleted', 'hidden', 'flagged', 'failed'),
    action_source ENUM('background_job', 'extension', 'manual'),
    failure_reason TEXT,
    processed_at TIMESTAMP
);
```

---

## 6. KEY SERVICES TO BUILD

### FilterMatcher Service
```php
// app/Services/FilterMatcher.php
class FilterMatcher
{
    public function findMatch(string $text, Collection $filters): ?Filter
    {
        foreach ($filters as $filter) {
            if ($this->matches($text, $filter)) {
                return $filter;
            }
        }
        return null;
    }

    private function matches(string $text, Filter $filter): bool
    {
        return match ($filter->type) {
            'keyword', 'phrase' => $this->matchKeyword($text, $filter),
            'regex' => $this->matchRegex($text, $filter),
            'url' => $this->matchUrl($text, $filter),
            'emoji_spam' => $this->matchEmojiSpam($text, $filter),
            'repeat_char' => $this->matchRepeatChar($text, $filter),
            default => false,
        };
    }
}
```

### Human Simulator (Extension)
```javascript
// extension/content-scripts/common/human-simulator.js
class HumanSimulator {
    async randomDelay(min = 500, max = 3000) {
        const delay = this.gaussianRandom(min, max);
        await this.sleep(delay);
    }

    gaussianRandom(min, max) {
        // Gaussian distribution untuk timing yang lebih natural
        let u = 0, v = 0;
        while (u === 0) u = Math.random();
        while (v === 0) v = Math.random();
        let num = Math.sqrt(-2.0 * Math.log(u)) * Math.cos(2.0 * Math.PI * v);
        num = num / 10.0 + 0.5;
        return Math.floor(num * (max - min + 1) + min);
    }

    async humanClick(element) {
        await this.randomDelay(100, 300);
        // Simulate mouse movement
        // Random offset dari center element
        // Click event
        await this.randomDelay(200, 500);
    }

    async maybeBreak() {
        // 10% chance untuk pause 2-5 detik (seperti manusia)
        if (Math.random() < 0.1) {
            await this.randomDelay(2000, 5000);
        }
    }
}
```

---

## 7. API ENDPOINTS

### Extension API
```
POST /api/extension/login          - Login dari extension (return token)
POST /api/extension/logout         - Logout
GET  /api/extension/verify         - Verify token valid
GET  /api/extension/sync           - Get user settings & filters
POST /api/extension/logs           - Submit deletion logs
GET  /api/extension/pending        - Get pending actions from server
POST /api/extension/complete       - Mark action as complete
```

### Dashboard API
```
GET    /api/filter-groups          - List filter groups
POST   /api/filter-groups          - Create filter group
GET    /api/filter-groups/{id}     - Get filter group detail
PUT    /api/filter-groups/{id}     - Update filter group
DELETE /api/filter-groups/{id}     - Delete filter group

GET    /api/filters                - List filters
POST   /api/filters                - Create filter
PUT    /api/filters/{id}           - Update filter
DELETE /api/filters/{id}           - Delete filter

GET    /api/logs                   - List moderation logs
GET    /api/logs/export            - Export logs (CSV)

GET    /api/analytics/summary      - Dashboard summary stats
GET    /api/analytics/chart        - Chart data (daily/weekly)

GET    /api/platforms              - List connected platforms
POST   /api/platforms/{name}/connect    - Initiate OAuth
DELETE /api/platforms/{id}/disconnect   - Disconnect platform
```

---

## 8. RATE LIMITS PER PLATFORM

| Platform | Scan Limit | Delete Limit | Notes |
|----------|-----------|--------------|-------|
| YouTube | 10K quota/day | ~200 deletes/day | 1 delete = 50 quota units |
| Instagram | 200/hour | 60/hour | Business account only |
| Twitter | 900/15min read | 50/15min delete | API berbayar $100/mo |
| Threads | 100/hour | 25/hour | Hide only, not delete |
| TikTok | N/A | 50/session | Self-imposed via extension |

---

## 9. PRESET FILTERS INDONESIA

### Judi Online
```
Keywords:
- slot gacor, slot online, slot maxwin
- togel, togel online, togel singapore, togel hongkong
- judi bola, taruhan bola
- link alternatif, deposit pulsa
- rtp live, rtp slot
- jackpot, maxwin, scatter

Regex:
- (slot|togel|judi|casino)\s*(gacor|online|maxwin)
- (deposit|wd|withdraw)\s*(pulsa|dana|ovo|gopay)
- wa\.me\/\d+
```

### Buzzer/Spam
```
Keywords:
- cek bio, link di bio
- followers gratis, follower gratis
- kunjungi profil, visit profile
- DM untuk info, DM for info

Regex:
- (cek|klik|visit)\s*(bio|profil|profile)
- (gratis|free)\s*(followers?|likes?)
```

### URL Patterns
```
Shorteners:
- bit.ly/*, s.id/*, linktr.ee/*, tinyurl.com/*

Suspicious domains:
- *.slot*, *.togel*, *.casino*, *.judi*
- *gacor*, *maxwin*
```

---

## 10. IMPLEMENTATION TIMELINE

### Week 1: Setup & Core Models ✅ COMPLETED
- [x] Clone starter kit ke /home/rahmat/mvp/delcom
- [x] Setup environment (.env, database, redis)
- [x] Create migrations (platforms, user_platforms, filter_groups, filters, preset_filters, logs)
- [x] Create Eloquent models dengan relationships
- [x] Build FilterMatcher service
- [x] Build TokenEncryptionService
- [x] Seed platforms & preset filters Indonesia
- [x] Update User model dengan extension fields & platform relationships
- [ ] Setup Laravel Horizon untuk queue management (optional, bisa nanti)

### Week 2: Filter System + UI
- [x] Build FilterMatcher service (dipindah ke Week 1)
- [x] Seed preset filters Indonesia (dipindah ke Week 1)
- [ ] Create React pages: Filters CRUD
- [ ] Create React pages: Filter Groups management
- [ ] Build moderation logs viewer (integrate dengan Spatie Activity Log)

### Week 3: OAuth All Platforms
- [ ] YouTube OAuth (Google)
- [ ] Instagram OAuth (Meta Graph API)
- [ ] Twitter/X OAuth 2.0 + PKCE
- [ ] Threads OAuth (Meta)
- [ ] Platform connection UI (React)

### Week 4-5: Platform Services & Background Jobs
- [ ] YouTubeService (getComments, deleteComment)
- [ ] InstagramService
- [ ] TwitterService
- [ ] ThreadsService
- [ ] Background scan jobs
- [ ] Rate limiting service

### Week 6-7: Chrome Extension MVP
- [ ] Extension project setup (Manifest V3)
- [ ] Auth flow (connect to Laravel)
- [ ] Popup UI (status, quick actions)
- [ ] Settings sync
- [ ] WebSocket connection ke Reverb

### Week 8-9: TikTok DOM Integration
- [ ] TikTok content scripts
- [ ] Human behavior simulator
- [ ] Comment scanner
- [ ] Delete executor
- [ ] Logging ke server

### Week 10: Testing & Polish
- [ ] End-to-end testing
- [ ] Security audit
- [ ] Performance optimization
- [ ] Documentation

---

## 11. FILES TO CREATE

### Priority 1: Core Setup (Week 1) ✅ DONE
```
database/migrations/
├── 2025_12_13_200000_create_platforms_table.php         ✅
├── 2025_12_13_200001_create_user_platforms_table.php    ✅
├── 2025_12_13_200002_create_filter_groups_table.php     ✅
├── 2025_12_13_200003_create_filters_table.php           ✅
├── 2025_12_13_200004_create_preset_filters_table.php    ✅ (added)
├── 2025_12_13_200005_create_moderation_logs_table.php   ✅
└── 2025_12_13_200006_add_extension_fields_to_users_table.php ✅ (added)

app/Models/
├── Platform.php        ✅
├── UserPlatform.php    ✅
├── FilterGroup.php     ✅
├── Filter.php          ✅
├── PresetFilter.php    ✅ (added)
└── ModerationLog.php   ✅

database/seeders/
├── PlatformSeeder.php      ✅
└── PresetFilterSeeder.php  ✅
```

### Priority 2: Services (Week 2-5) 🔄 IN PROGRESS
```
app/Services/
├── FilterMatcher.php         ✅ DONE
├── TokenEncryptionService.php ✅ DONE
├── RateLimitService.php      ⏳ TODO
└── Platform/
    ├── PlatformServiceInterface.php  ⏳ TODO
    ├── YouTubeService.php            ⏳ TODO
    ├── InstagramService.php          ⏳ TODO
    ├── TwitterService.php            ⏳ TODO
    └── ThreadsService.php            ⏳ TODO

app/Jobs/Platform/
├── ScanYouTubeComments.php    ⏳ TODO
├── DeleteYouTubeComment.php   ⏳ TODO
├── ScanInstagramComments.php  ⏳ TODO
├── DeleteInstagramComment.php ⏳ TODO
└── ...
```

### Priority 3: Controllers & API
```
app/Http/Controllers/
├── Auth/OAuthController.php
├── Api/ExtensionAuthController.php
├── Api/ExtensionSyncController.php
├── Api/FilterController.php
└── Api/ModerationLogController.php
```

### Priority 4: React Pages
```
resources/js/Pages/
├── Platforms/
│   ├── Index.tsx
│   └── Connect.tsx
├── Filters/
│   ├── Index.tsx
│   ├── Create.tsx
│   └── Edit.tsx
├── Logs/
│   └── Index.tsx
└── Analytics/
    └── Dashboard.tsx
```

### Priority 5: Chrome Extension
```
extension/
├── manifest.json
├── background/
│   ├── service-worker.js
│   └── api-client.js
├── content-scripts/
│   ├── common/
│   │   └── human-simulator.js
│   └── tiktok/
│       ├── tiktok-selectors.js
│       ├── tiktok-scanner.js
│       └── tiktok-deleter.js
├── popup/
│   ├── popup.html
│   ├── popup.css
│   └── popup.js
└── options/
    ├── options.html
    └── options.js
```

---

## 12. CURRENT STATUS

### Planning Phase ✅
- [x] Market research & competitor analysis
- [x] API feasibility study per platform
- [x] Architecture design (Two-Tier)
- [x] Database schema design
- [x] Tech stack selection
- [x] Implementation timeline
- [x] File structure planning

### Week 1: Core Setup ✅ COMPLETED
- [x] Clone starter kit to /home/rahmat/mvp/delcom
- [x] Create 7 migrations:
  - `2025_12_13_200000_create_platforms_table.php`
  - `2025_12_13_200001_create_user_platforms_table.php`
  - `2025_12_13_200002_create_filter_groups_table.php`
  - `2025_12_13_200003_create_filters_table.php`
  - `2025_12_13_200004_create_preset_filters_table.php`
  - `2025_12_13_200005_create_moderation_logs_table.php`
  - `2025_12_13_200006_add_extension_fields_to_users_table.php`
- [x] Create 6 Models:
  - `Platform.php` (tier: api/extension, OAuth URLs)
  - `UserPlatform.php` (connected accounts, encrypted tokens)
  - `FilterGroup.php` (user filter collections)
  - `Filter.php` (7 types, 4 actions)
  - `PresetFilter.php` (ready-to-use filter sets)
  - `ModerationLog.php` (action history)
- [x] Create 2 Services:
  - `FilterMatcher.php` (pattern matching engine)
  - `TokenEncryptionService.php` (OAuth token encryption)
- [x] Create 2 Seeders:
  - `PlatformSeeder.php` (5 platforms: youtube, instagram, twitter, threads, tiktok)
  - `PresetFilterSeeder.php` (5 preset groups: Judi Online, Buzzer, URLs, Spam, Hate Speech)
- [x] Update existing files:
  - `User.php` (extension fields, platform relationships, getActiveFilters)
  - `DatabaseSeeder.php` (include new seeders)

### Next Steps (Week 2: Filter System UI)
- [ ] Create React pages: Filters CRUD (Index, Create, Edit)
- [ ] Create React pages: Filter Groups management
- [ ] Create React pages: Preset Filters browser
- [ ] Build moderation logs viewer
- [ ] Add API routes for filter management

---

## 13. IMPORTANT NOTES

### Legal Considerations
- YouTube, Instagram, Twitter, Threads: **100% Legal** (menggunakan Official API)
- TikTok: **Grey Area** (DOM automation, technically violates ToS tapi sulit dideteksi jika dilakukan dengan human-like behavior)

### Security Priorities
1. Encrypt OAuth tokens at rest
2. HTTPS only
3. Rate limit all APIs
4. Extension: minimal permissions, no eval()
5. Token rotation & expiration

### Key Decisions Made
1. **Nama produk**: DelCom
2. **Starter kit**: laravel-12-spattie-media-and-roles
3. **MVP scope**: Semua platform sekaligus
4. **Timeline**: ~10 minggu
5. **TikTok approach**: DOM automation dengan human simulator

---

## 14. RESOURCES & REFERENCES

### API Documentation
- YouTube Data API v3: https://developers.google.com/youtube/v3
- Instagram Graph API: https://developers.facebook.com/docs/instagram-api
- Twitter API v2: https://developer.twitter.com/en/docs/twitter-api
- Threads API: https://developers.facebook.com/docs/threads

### Starter Kit
- Repository: https://github.com/RahmatRafiq/laravel-12-spattie-media-and-roles

### Plan File
- Location: `/home/rahmat/.claude/plans/merry-jumping-duck.md`

---

**Untuk melanjutkan di sesi berikutnya, cukup katakan:**
> "Lanjutkan development DelCom. Baca dulu CONTEXT.md di /home/rahmat/mvp/delcom/"
