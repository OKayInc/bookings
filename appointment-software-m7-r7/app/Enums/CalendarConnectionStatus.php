<?php

namespace App\Enums;

enum CalendarConnectionStatus: string
{
    case Active = 'active';
    case Error = 'error';
    case Revoked = 'revoked';
}
