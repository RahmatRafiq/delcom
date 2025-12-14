<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PresetFilter extends Model
{
    protected $fillable = [
        'name',
        'description',
        'category',
        'filters_data',
        'is_active',
    ];

    protected $casts = [
        'filters_data' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Categories available.
     */
    public const CATEGORY_SPAM = 'spam';
    public const CATEGORY_HATE_SPEECH = 'hate_speech';
    public const CATEGORY_SCAM = 'scam';
    public const CATEGORY_SELF_PROMOTION = 'self_promotion';
    public const CATEGORY_INAPPROPRIATE = 'inappropriate';

    /**
     * Scope to get only active presets.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get presets by category.
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get all available categories.
     */
    public static function getCategories(): array
    {
        return [
            self::CATEGORY_SPAM => 'Spam',
            self::CATEGORY_HATE_SPEECH => 'Hate Speech',
            self::CATEGORY_SCAM => 'Scam',
            self::CATEGORY_SELF_PROMOTION => 'Self Promotion',
            self::CATEGORY_INAPPROPRIATE => 'Inappropriate',
        ];
    }

    /**
     * Apply this preset to a user's filter group.
     */
    public function applyToFilterGroup(FilterGroup $filterGroup): void
    {
        foreach ($this->filters_data as $filterData) {
            $filterGroup->filters()->create([
                'type' => $filterData['type'] ?? 'keyword',
                'pattern' => $filterData['pattern'],
                'match_type' => $filterData['match_type'] ?? 'contains',
                'case_sensitive' => $filterData['case_sensitive'] ?? false,
                'action' => $filterData['action'] ?? 'delete',
                'priority' => $filterData['priority'] ?? 0,
                'is_active' => true,
            ]);
        }
    }
}
