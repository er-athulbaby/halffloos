<?php

namespace App\Enums;

enum OfferStatus: string
{
    case Active = 'active';
    case SoldOut = 'sold_out';
    case Closed = 'closed';
}
