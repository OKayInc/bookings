<?php

namespace App\Enums;

enum MembershipRole: string
{
    case Owner = 'owner';
    case Administrator = 'administrator';
    case Manager = 'manager';
    case Employee = 'employee';

    public function canManageOrganization(): bool
    {
        return in_array($this, [self::Owner, self::Administrator], true);
    }

    public function canManageScheduling(): bool
    {
        return in_array($this, [self::Owner, self::Administrator, self::Manager], true);
    }
}
