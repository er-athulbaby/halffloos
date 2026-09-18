<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\StoreStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => StoreStatus::class,
            'verified_at' => 'datetime',
            'delivers' => 'boolean',
            'delivery_fee_fils' => MoneyCast::class,
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }
}
