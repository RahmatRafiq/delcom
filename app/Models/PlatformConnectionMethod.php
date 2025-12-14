<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformConnectionMethod extends Model
{
    protected $fillable = [
        'platform_id',
        'connection_method',
        'requires_business_account',
        'requires_paid_api',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'requires_business_account' => 'boolean',
        'requires_paid_api' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function isApi(): bool
    {
        return $this->connection_method === 'api';
    }

    public function isExtension(): bool
    {
        return $this->connection_method === 'extension';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeApi($query)
    {
        return $query->where('connection_method', 'api');
    }

    public function scopeExtension($query)
    {
        return $query->where('connection_method', 'extension');
    }
}
