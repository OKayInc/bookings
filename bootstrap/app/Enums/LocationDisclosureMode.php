<?php

namespace App\Enums;

enum LocationDisclosureMode: string
{
    case Public = 'public';
    case AfterAcceptance = 'after_acceptance';
    case HoursBeforeEvent = 'hours_before_event';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Show immediately',
            self::AfterAcceptance => 'Show only after admission is accepted',
            self::HoursBeforeEvent => 'Show a set number of hours before the event',
        };
    }
}
