# DelCom - Code Snippets Reference
> Kumpulan code snippets yang sudah di-design selama planning phase.

**Last Updated**: 13 Desember 2025

## Implementation Status

| Section | Status |
|---------|--------|
| 1. Database Migrations | ✅ IMPLEMENTED (dengan improvements) |
| 2. Eloquent Models | ✅ IMPLEMENTED (dengan improvements) |
| 3. Services | ✅ IMPLEMENTED (FilterMatcher & TokenEncryption) |
| 4. Chrome Extension | ⏳ Reference untuk Week 6-7 |
| 5. Preset Filter Seeder | ✅ IMPLEMENTED (dengan improvements) |
| 6. Platform Seeder | ✅ IMPLEMENTED |

> **Note**: File-file yang sudah diimplementasikan mungkin berbeda sedikit dari snippets ini
> karena ada improvements dan penyesuaian saat implementasi aktual.

---

## 1. DATABASE MIGRATIONS

### platforms table
```php
// database/migrations/xxxx_create_platforms_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platforms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('display_name', 100);
            $table->enum('tier', ['api', 'extension']);
            $table->string('api_base_url', 255)->nullable();
            $table->string('oauth_authorize_url', 255)->nullable();
            $table->string('oauth_token_url', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platforms');
    }
};
```

### user_platforms table
```php
// database/migrations/xxxx_create_user_platforms_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_platforms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('platform_id')->constrained()->onDelete('cascade');
            $table->string('platform_user_id', 255)->nullable();
            $table->string('platform_username', 255)->nullable();
            $table->string('platform_channel_id', 255)->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_moderation_enabled')->default(false);
            $table->integer('scan_frequency_minutes')->default(60);
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'platform_id', 'platform_user_id'], 'unique_user_platform');
            $table->index(['is_active', 'auto_moderation_enabled']);
            $table->index('last_scanned_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_platforms');
    }
};
```

### filter_groups table
```php
// database/migrations/xxxx_create_filter_groups_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filter_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('applies_to_platforms')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filter_groups');
    }
};
```

### filters table
```php
// database/migrations/xxxx_create_filters_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filter_group_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['keyword', 'phrase', 'regex', 'username', 'url', 'emoji_spam', 'repeat_char']);
            $table->string('pattern', 500);
            $table->enum('match_type', ['exact', 'contains', 'starts_with', 'ends_with', 'regex'])->default('contains');
            $table->boolean('case_sensitive')->default(false);
            $table->enum('action', ['delete', 'hide', 'flag', 'report'])->default('delete');
            $table->integer('priority')->default(0);
            $table->unsignedInteger('hit_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'priority']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filters');
    }
};
```

### moderation_logs table
```php
// database/migrations/xxxx_create_moderation_logs_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_platform_id')->constrained()->onDelete('cascade');
            $table->string('platform_comment_id', 255);
            $table->string('video_id', 255)->nullable();
            $table->string('post_id', 255)->nullable();
            $table->string('commenter_username', 255)->nullable();
            $table->string('commenter_id', 255)->nullable();
            $table->text('comment_text')->nullable();
            $table->foreignId('matched_filter_id')->nullable()->constrained('filters')->onDelete('set null');
            $table->string('matched_pattern', 500)->nullable();
            $table->enum('action_taken', ['deleted', 'hidden', 'flagged', 'reported', 'failed']);
            $table->enum('action_source', ['background_job', 'extension', 'manual']);
            $table->text('failure_reason')->nullable();
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->index(['user_id', 'processed_at']);
            $table->index(['user_platform_id', 'processed_at']);
            $table->index('action_taken');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_logs');
    }
};
```

---

## 2. ELOQUENT MODELS

### Platform Model
```php
// app/Models/Platform.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'tier',
        'api_base_url',
        'oauth_authorize_url',
        'oauth_token_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function userPlatforms(): HasMany
    {
        return $this->hasMany(UserPlatform::class);
    }

    public function isApiTier(): bool
    {
        return $this->tier === 'api';
    }

    public function isExtensionTier(): bool
    {
        return $this->tier === 'extension';
    }
}
```

### UserPlatform Model
```php
// app/Models/UserPlatform.php
<?php

namespace App\Models;

use App\Services\TokenEncryptionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserPlatform extends Model
{
    protected $fillable = [
        'user_id',
        'platform_id',
        'platform_user_id',
        'platform_username',
        'platform_channel_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'is_active',
        'auto_moderation_enabled',
        'scan_frequency_minutes',
        'last_scanned_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'last_scanned_at' => 'datetime',
        'scopes' => 'array',
        'is_active' => 'boolean',
        'auto_moderation_enabled' => 'boolean',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function moderationLogs(): HasMany
    {
        return $this->hasMany(ModerationLog::class);
    }

    // Encrypt token saat disimpan
    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = $value
            ? app(TokenEncryptionService::class)->encrypt($value)
            : null;
    }

    // Decrypt token saat diambil
    public function getAccessTokenAttribute($value): ?string
    {
        return $value
            ? app(TokenEncryptionService::class)->decrypt($value)
            : null;
    }

    public function setRefreshTokenAttribute($value): void
    {
        $this->attributes['refresh_token'] = $value
            ? app(TokenEncryptionService::class)->encrypt($value)
            : null;
    }

    public function getRefreshTokenAttribute($value): ?string
    {
        return $value
            ? app(TokenEncryptionService::class)->decrypt($value)
            : null;
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }

    public function needsScanning(): bool
    {
        if (!$this->is_active || !$this->auto_moderation_enabled) {
            return false;
        }

        if (!$this->last_scanned_at) {
            return true;
        }

        return $this->last_scanned_at
            ->addMinutes($this->scan_frequency_minutes)
            ->isPast();
    }
}
```

### FilterGroup Model
```php
// app/Models/FilterGroup.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FilterGroup extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'is_active',
        'applies_to_platforms',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'applies_to_platforms' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function filters(): HasMany
    {
        return $this->hasMany(Filter::class);
    }

    public function activeFilters(): HasMany
    {
        return $this->hasMany(Filter::class)
            ->where('is_active', true)
            ->orderBy('priority', 'desc');
    }

    public function appliesToPlatform(string $platform): bool
    {
        if (empty($this->applies_to_platforms)) {
            return true; // Applies to all if not specified
        }

        return in_array($platform, $this->applies_to_platforms);
    }
}
```

### Filter Model
```php
// app/Models/Filter.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Filter extends Model
{
    protected $fillable = [
        'filter_group_id',
        'type',
        'pattern',
        'match_type',
        'case_sensitive',
        'action',
        'priority',
        'hit_count',
        'is_active',
    ];

    protected $casts = [
        'case_sensitive' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function filterGroup(): BelongsTo
    {
        return $this->belongsTo(FilterGroup::class);
    }

    public function moderationLogs(): HasMany
    {
        return $this->hasMany(ModerationLog::class, 'matched_filter_id');
    }

    public function incrementHitCount(): void
    {
        $this->increment('hit_count');
    }
}
```

---

## 3. SERVICES

### FilterMatcher Service
```php
// app/Services/FilterMatcher.php
<?php

namespace App\Services;

use App\Models\Filter;
use Illuminate\Support\Collection;

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

    public function matches(string $text, Filter $filter): bool
    {
        $pattern = $filter->pattern;
        $searchText = $filter->case_sensitive ? $text : mb_strtolower($text);
        $searchPattern = $filter->case_sensitive ? $pattern : mb_strtolower($pattern);

        return match ($filter->type) {
            'keyword', 'phrase' => $this->matchKeyword($searchText, $searchPattern, $filter->match_type),
            'regex' => $this->matchRegex($text, $pattern, $filter->case_sensitive),
            'username' => $this->matchUsername($searchText, $searchPattern),
            'url' => $this->matchUrl($searchText, $searchPattern),
            'emoji_spam' => $this->matchEmojiSpam($text, (int) $pattern),
            'repeat_char' => $this->matchRepeatChar($text, (int) $pattern),
            default => false,
        };
    }

    private function matchKeyword(string $text, string $pattern, string $matchType): bool
    {
        return match ($matchType) {
            'exact' => $text === $pattern,
            'contains' => str_contains($text, $pattern),
            'starts_with' => str_starts_with($text, $pattern),
            'ends_with' => str_ends_with($text, $pattern),
            default => false,
        };
    }

    private function matchRegex(string $text, string $pattern, bool $caseSensitive): bool
    {
        $flags = $caseSensitive ? '' : 'i';
        return (bool) @preg_match("/{$pattern}/{$flags}u", $text);
    }

    private function matchUsername(string $text, string $pattern): bool
    {
        // Username biasanya exact match atau contains
        return str_contains($text, $pattern);
    }

    private function matchUrl(string $text, string $pattern): bool
    {
        // Convert wildcard pattern to regex
        $regexPattern = str_replace(['*', '.'], ['.*', '\.'], $pattern);
        return (bool) @preg_match("/{$regexPattern}/i", $text);
    }

    private function matchEmojiSpam(string $text, int $threshold): bool
    {
        // Match common emoji ranges
        $emojiPattern = '/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{1F1E0}-\x{1F1FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]/u';
        preg_match_all($emojiPattern, $text, $matches);
        return count($matches[0]) >= $threshold;
    }

    private function matchRepeatChar(string $text, int $threshold): bool
    {
        // Match repeated characters (e.g., "aaaaaa")
        return (bool) preg_match('/(.)\1{' . ($threshold - 1) . ',}/u', $text);
    }
}
```

### TokenEncryptionService
```php
// app/Services/TokenEncryptionService.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class TokenEncryptionService
{
    public function encrypt(string $value): string
    {
        return Crypt::encryptString($value);
    }

    public function decrypt(string $value): ?string
    {
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            report($e);
            return null;
        }
    }
}
```

---

## 4. CHROME EXTENSION

### manifest.json
```json
{
  "manifest_version": 3,
  "name": "DelCom - Comment Moderation",
  "version": "1.0.0",
  "description": "Automated comment moderation for content creators",

  "permissions": [
    "storage",
    "alarms",
    "tabs",
    "activeTab"
  ],

  "host_permissions": [
    "https://www.tiktok.com/*",
    "https://your-domain.com/api/*"
  ],

  "background": {
    "service_worker": "background/service-worker.js",
    "type": "module"
  },

  "content_scripts": [
    {
      "matches": ["https://www.tiktok.com/*"],
      "js": [
        "lib/utils.js",
        "lib/filter-matcher.js",
        "content-scripts/common/human-simulator.js",
        "content-scripts/tiktok/tiktok-selectors.js",
        "content-scripts/tiktok/tiktok-scanner.js",
        "content-scripts/tiktok/tiktok-deleter.js"
      ],
      "run_at": "document_idle"
    }
  ],

  "action": {
    "default_popup": "popup/popup.html",
    "default_icon": {
      "16": "icons/icon-16.png",
      "48": "icons/icon-48.png",
      "128": "icons/icon-128.png"
    }
  },

  "options_page": "options/options.html"
}
```

### Human Simulator
```javascript
// extension/content-scripts/common/human-simulator.js
class HumanSimulator {
  constructor() {
    this.config = {
      minDelay: 500,
      maxDelay: 3000,
      clickVariance: 5,
      scrollDelay: 100,
    };
  }

  async randomDelay(min = this.config.minDelay, max = this.config.maxDelay) {
    const delay = this.gaussianRandom(min, max);
    await this.sleep(delay);
    return delay;
  }

  // Gaussian distribution untuk timing yang lebih natural
  gaussianRandom(min, max) {
    let u = 0, v = 0;
    while (u === 0) u = Math.random();
    while (v === 0) v = Math.random();

    let num = Math.sqrt(-2.0 * Math.log(u)) * Math.cos(2.0 * Math.PI * v);
    num = num / 10.0 + 0.5;

    if (num > 1 || num < 0) {
      return this.gaussianRandom(min, max);
    }

    return Math.floor(num * (max - min + 1) + min);
  }

  async humanClick(element) {
    // Wait sebelum click
    await this.randomDelay(100, 300);

    // Get element position dengan random offset
    const rect = element.getBoundingClientRect();
    const x = rect.left + rect.width / 2 + this.randomOffset();
    const y = rect.top + rect.height / 2 + this.randomOffset();

    // Simulate mouse move
    await this.simulateMouseMove(x, y);
    await this.sleep(this.gaussianRandom(50, 150));

    // Click event
    const clickEvent = new MouseEvent('click', {
      bubbles: true,
      cancelable: true,
      view: window,
      clientX: x,
      clientY: y,
    });

    element.dispatchEvent(clickEvent);

    // Wait setelah click
    await this.randomDelay(200, 500);
  }

  async scrollToElement(element) {
    const rect = element.getBoundingClientRect();
    const isInView = rect.top >= 0 && rect.bottom <= window.innerHeight;

    if (!isInView) {
      element.scrollIntoView({ behavior: 'smooth', block: 'center' });
      await this.randomDelay(300, 800);
    }
  }

  randomOffset() {
    return (Math.random() - 0.5) * 2 * this.config.clickVariance;
  }

  async simulateMouseMove(targetX, targetY) {
    const moveEvent = new MouseEvent('mousemove', {
      bubbles: true,
      cancelable: true,
      view: window,
      clientX: targetX,
      clientY: targetY,
    });
    document.dispatchEvent(moveEvent);
    await this.sleep(50);
  }

  sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  // 10% chance untuk pause seperti manusia
  async maybeBreak() {
    if (Math.random() < 0.1) {
      console.log('[HumanSimulator] Taking a break...');
      await this.randomDelay(2000, 5000);
    }
  }
}

window.HumanSimulator = HumanSimulator;
```

### TikTok Selectors (Updateable)
```javascript
// extension/content-scripts/tiktok/tiktok-selectors.js
// Selectors ini bisa di-update dari server jika TikTok mengubah DOM-nya
window.TikTokSelectors = {
  // Comment containers
  commentContainer: '[data-e2e="comment-list"]',
  commentItem: '[data-e2e="comment-item"]',

  // Comment content
  commentText: '[data-e2e="comment-level-1"] span, [data-e2e="comment-text"]',
  commentAuthor: '[data-e2e="comment-username-1"]',

  // Actions
  moreOptionsButton: '[data-e2e="comment-more-icon"]',
  deleteButton: '[data-e2e="comment-delete"]',
  confirmDeleteButton: '[data-e2e="confirm-popup-confirm"]',

  // Replies
  replyContainer: '[data-e2e="reply-list"]',
  replyItem: '[data-e2e="comment-level-2"]',
};

window.TikTokSelectorsVersion = '1.0.0';
```

---

## 5. PRESET FILTERS SEEDER

```php
// database/seeders/PresetFilterSeeder.php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PresetFilterSeeder extends Seeder
{
    public function run(): void
    {
        $presets = [
            [
                'name' => 'Judi Online Indonesia',
                'description' => 'Filter untuk spam judi online, slot, togel',
                'category' => 'spam',
                'filters_data' => json_encode([
                    ['type' => 'keyword', 'pattern' => 'slot gacor', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'slot online', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'slot maxwin', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'togel', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'togel online', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'judi bola', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'link alternatif', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'deposit pulsa', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'rtp live', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'rtp slot', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'maxwin', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'jackpot', 'match_type' => 'contains'],
                    ['type' => 'regex', 'pattern' => '(slot|togel|judi|casino)\\s*(gacor|online|maxwin)', 'match_type' => 'regex'],
                    ['type' => 'regex', 'pattern' => 'wa\\.me\\/\\d+', 'match_type' => 'regex'],
                ]),
            ],
            [
                'name' => 'Buzzer & Self Promotion',
                'description' => 'Filter untuk buzzer dan spam promosi',
                'category' => 'spam',
                'filters_data' => json_encode([
                    ['type' => 'keyword', 'pattern' => 'cek bio', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'link di bio', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'followers gratis', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'kunjungi profil', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'DM untuk info', 'match_type' => 'contains'],
                    ['type' => 'regex', 'pattern' => '(cek|klik|visit)\\s*(bio|profil|profile)', 'match_type' => 'regex'],
                    ['type' => 'regex', 'pattern' => '(gratis|free)\\s*(followers?|likes?)', 'match_type' => 'regex'],
                ]),
            ],
            [
                'name' => 'Suspicious URLs',
                'description' => 'Filter untuk URL mencurigakan',
                'category' => 'spam',
                'filters_data' => json_encode([
                    ['type' => 'url', 'pattern' => 'bit.ly/*', 'match_type' => 'regex'],
                    ['type' => 'url', 'pattern' => 's.id/*', 'match_type' => 'regex'],
                    ['type' => 'url', 'pattern' => 'linktr.ee/*', 'match_type' => 'regex'],
                    ['type' => 'url', 'pattern' => 'tinyurl.com/*', 'match_type' => 'regex'],
                    ['type' => 'url', 'pattern' => '*.slot*', 'match_type' => 'regex'],
                    ['type' => 'url', 'pattern' => '*.togel*', 'match_type' => 'regex'],
                    ['type' => 'url', 'pattern' => '*.casino*', 'match_type' => 'regex'],
                    ['type' => 'url', 'pattern' => '*gacor*', 'match_type' => 'regex'],
                ]),
            ],
            [
                'name' => 'Spam Patterns',
                'description' => 'Filter untuk pola spam umum',
                'category' => 'spam',
                'filters_data' => json_encode([
                    ['type' => 'emoji_spam', 'pattern' => '10', 'match_type' => 'exact'], // 10+ emojis
                    ['type' => 'repeat_char', 'pattern' => '5', 'match_type' => 'exact'], // 5+ repeated chars
                ]),
            ],
        ];

        foreach ($presets as $preset) {
            DB::table('preset_filters')->insert([
                'name' => $preset['name'],
                'description' => $preset['description'],
                'category' => $preset['category'],
                'filters_data' => $preset['filters_data'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
```

---

## 6. PLATFORM SEEDER

```php
// database/seeders/PlatformSeeder.php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $platforms = [
            [
                'name' => 'youtube',
                'display_name' => 'YouTube',
                'tier' => 'api',
                'api_base_url' => 'https://www.googleapis.com/youtube/v3',
                'oauth_authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'oauth_token_url' => 'https://oauth2.googleapis.com/token',
                'is_active' => true,
            ],
            [
                'name' => 'instagram',
                'display_name' => 'Instagram',
                'tier' => 'api',
                'api_base_url' => 'https://graph.instagram.com',
                'oauth_authorize_url' => 'https://api.instagram.com/oauth/authorize',
                'oauth_token_url' => 'https://api.instagram.com/oauth/access_token',
                'is_active' => true,
            ],
            [
                'name' => 'twitter',
                'display_name' => 'X (Twitter)',
                'tier' => 'api',
                'api_base_url' => 'https://api.twitter.com/2',
                'oauth_authorize_url' => 'https://twitter.com/i/oauth2/authorize',
                'oauth_token_url' => 'https://api.twitter.com/2/oauth2/token',
                'is_active' => true,
            ],
            [
                'name' => 'threads',
                'display_name' => 'Threads',
                'tier' => 'api',
                'api_base_url' => 'https://graph.threads.net',
                'oauth_authorize_url' => 'https://threads.net/oauth/authorize',
                'oauth_token_url' => 'https://graph.threads.net/oauth/access_token',
                'is_active' => true,
            ],
            [
                'name' => 'tiktok',
                'display_name' => 'TikTok',
                'tier' => 'extension',
                'api_base_url' => null,
                'oauth_authorize_url' => null,
                'oauth_token_url' => null,
                'is_active' => true,
            ],
        ];

        foreach ($platforms as $platform) {
            DB::table('platforms')->insert(array_merge($platform, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
```

---

**Dokumen ini berisi semua code snippets yang sudah di-design.**
**Copy-paste dan sesuaikan saat implementasi.**
