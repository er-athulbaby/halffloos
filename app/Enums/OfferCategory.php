<?php

namespace App\Enums;

/**
 * Raw meat, poultry and fish are deliberately absent. Near-expiry raw protein
 * with a pickup window and no controlled cold chain is the highest-liability
 * category in the system, and the spec excludes it from v1. Until this enum
 * gained a case for it, that exclusion was only a policy a human applied at
 * approval time — now nothing can be listed under it at all.
 */
enum OfferCategory: string
{
    case Bakery = 'bakery';
    case Dairy = 'dairy';
    case Produce = 'produce';
    case Meals = 'meals';
    case Pantry = 'pantry';
    case Drinks = 'drinks';

    public function label(): string
    {
        return match ($this) {
            self::Bakery => __('Bakery'),
            self::Dairy => __('Dairy & eggs'),
            self::Produce => __('Fruit & veg'),
            self::Meals => __('Ready meals'),
            self::Pantry => __('Pantry'),
            self::Drinks => __('Drinks'),
        };
    }

    /** Inline SVG path data — never emoji, which renders inconsistently on Android. */
    public function icon(): string
    {
        return match ($this) {
            self::Bakery => 'M4 18h16M5 18a7 7 0 0 1 14 0M8 11V8M12 11V7M16 11V8',
            self::Dairy => 'M9 3h6l-1 3v2l2 4v9H8v-9l2-4V6L9 3zM8 15h8',
            self::Produce => 'M12 8a5 5 0 1 0 0 12 5 5 0 0 0 0-12zM12 8V5a3 3 0 0 1 3-3',
            self::Meals => 'M3 11h18M5 11a7 7 0 0 1 14 0M2 15h20M7 7V4M11 7V4',
            self::Pantry => 'M4 7h16v13H4zM4 7l2-3h12l2 3M9 12h6',
            self::Drinks => 'M6 3h12l-2 8v10H8V11L6 3zM7 7h10',
        };
    }
}
