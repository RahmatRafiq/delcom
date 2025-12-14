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
        'hit_count' => 'integer',
        'priority' => 'integer',
    ];

    /**
     * Filter types available.
     */
    public const TYPE_KEYWORD = 'keyword';
    public const TYPE_PHRASE = 'phrase';
    public const TYPE_REGEX = 'regex';
    public const TYPE_USERNAME = 'username';
    public const TYPE_URL = 'url';
    public const TYPE_EMOJI_SPAM = 'emoji_spam';
    public const TYPE_REPEAT_CHAR = 'repeat_char';

    /**
     * Match types available.
     */
    public const MATCH_EXACT = 'exact';
    public const MATCH_CONTAINS = 'contains';
    public const MATCH_STARTS_WITH = 'starts_with';
    public const MATCH_ENDS_WITH = 'ends_with';
    public const MATCH_REGEX = 'regex';

    /**
     * Actions available.
     */
    public const ACTION_DELETE = 'delete';
    public const ACTION_HIDE = 'hide';
    public const ACTION_FLAG = 'flag';
    public const ACTION_REPORT = 'report';

    /**
     * All available types as array.
     */
    public const TYPES = [
        self::TYPE_KEYWORD,
        self::TYPE_PHRASE,
        self::TYPE_REGEX,
        self::TYPE_USERNAME,
        self::TYPE_URL,
        self::TYPE_EMOJI_SPAM,
        self::TYPE_REPEAT_CHAR,
    ];

    /**
     * All available match types as array.
     */
    public const MATCH_TYPES = [
        self::MATCH_EXACT,
        self::MATCH_CONTAINS,
        self::MATCH_STARTS_WITH,
        self::MATCH_ENDS_WITH,
        self::MATCH_REGEX,
    ];

    /**
     * All available actions as array.
     */
    public const ACTIONS = [
        self::ACTION_DELETE,
        self::ACTION_HIDE,
        self::ACTION_FLAG,
        self::ACTION_REPORT,
    ];

    /**
     * Get the filter group that owns this filter.
     */
    public function filterGroup(): BelongsTo
    {
        return $this->belongsTo(FilterGroup::class);
    }

    /**
     * Get the moderation logs that used this filter.
     */
    public function moderationLogs(): HasMany
    {
        return $this->hasMany(ModerationLog::class, 'matched_filter_id');
    }

    /**
     * Increment the hit count for this filter.
     */
    public function incrementHitCount(): void
    {
        $this->increment('hit_count');
    }

    /**
     * Scope to get only active filters.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by priority (highest first).
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    /**
     * Scope to get filters of a specific type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get all available filter types.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_KEYWORD => 'Keyword',
            self::TYPE_PHRASE => 'Phrase',
            self::TYPE_REGEX => 'Regular Expression',
            self::TYPE_USERNAME => 'Username',
            self::TYPE_URL => 'URL Pattern',
            self::TYPE_EMOJI_SPAM => 'Emoji Spam',
            self::TYPE_REPEAT_CHAR => 'Repeated Characters',
        ];
    }

    /**
     * Get all available match types.
     */
    public static function getMatchTypes(): array
    {
        return [
            self::MATCH_EXACT => 'Exact Match',
            self::MATCH_CONTAINS => 'Contains',
            self::MATCH_STARTS_WITH => 'Starts With',
            self::MATCH_ENDS_WITH => 'Ends With',
            self::MATCH_REGEX => 'Regex',
        ];
    }

    /**
     * Get all available actions.
     */
    public static function getActions(): array
    {
        return [
            self::ACTION_DELETE => 'Delete',
            self::ACTION_HIDE => 'Hide',
            self::ACTION_FLAG => 'Flag for Review',
            self::ACTION_REPORT => 'Report to Platform',
        ];
    }
}
