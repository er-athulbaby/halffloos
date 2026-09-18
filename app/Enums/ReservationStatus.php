<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Reserved = 'reserved';
    case Collected = 'collected';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';
}
