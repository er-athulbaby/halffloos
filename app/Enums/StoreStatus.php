<?php

namespace App\Enums;

enum StoreStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Suspended = 'suspended';
}
