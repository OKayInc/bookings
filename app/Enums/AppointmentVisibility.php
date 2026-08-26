<?php

namespace App\Enums;

enum AppointmentVisibility: string
{
    case Public = 'public';
    case Unlisted = 'unlisted';
    case InviteOnly = 'invite_only';
    case PasswordProtected = 'password_protected';
}
