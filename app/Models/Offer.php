<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\OfferCategory;
use App\Enums\OfferStatus;
use App\Enums\OfferType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => OfferType::class,
            'category' => OfferCategory::class,
            'status' => OfferStatus::class,
            'retail_value_fils' => MoneyCast::class,
            'price_fils' => MoneyCast::class,
            'expires_on' => 'date',
            'pickup_start' => 'datetime',
            'pickup_end' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
