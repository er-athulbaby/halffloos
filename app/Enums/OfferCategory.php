<?php

namespace App\Enums;

/**
 * Raw meat, poultry and fish are deliberately absent. Near-expiry raw protein
 * with a pickup window and no controlled cold chain is the highest-liability
 * category in the system, and the spec excludes it from v1. Until this enum
 * existed that exclusion was only a policy a human applied at approval time —
 * now nothing can be listed under it at all.
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

    /** Phosphor icon name, resolved by the <x-icon> component. */
    public function icon(): string
    {
        return match ($this) {
            self::Bakery => 'bread',
            self::Dairy => 'cheese',
            self::Produce => 'avocado',
            self::Meals => 'bowl-food',
            self::Pantry => 'jar',
            self::Drinks => 'coffee',
        };
    }

    /**
     * Tint for the placeholder art shown when an offer has no photograph.
     * Distinct hues make a list scannable at a glance without relying on the
     * text, and a deliberate tinted glyph reads as designed rather than broken.
     */
    public function tint(): string
    {
        return match ($this) {
            self::Bakery => 'bg-amber-100 text-amber-700',
            self::Dairy => 'bg-sky-100 text-sky-700',
            self::Produce => 'bg-lime-100 text-lime-700',
            self::Meals => 'bg-orange-100 text-orange-700',
            self::Pantry => 'bg-stone-100 text-stone-600',
            self::Drinks => 'bg-violet-100 text-violet-700',
        };
    }
}
