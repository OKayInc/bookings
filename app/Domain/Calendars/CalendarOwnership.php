<?php

namespace App\Domain\Calendars;

/** Ownership is not the same as permission to edit a shared calendar. */
final class CalendarOwnership
{
    /** @param array<string, mixed> $calendar @param array<string, mixed> $profile */
    public static function detect(array $calendar, array $profile): ?bool
    {
        $owner = self::email($calendar['owner_email'] ?? null);
        $emails = array_values(array_filter(array_map(
            self::email(...),
            [$profile['email'] ?? null, ...((array) ($profile['emails'] ?? []))],
        )));

        if ($owner !== null) {
            return $emails === [] ? null : in_array($owner, $emails, true);
        }

        // Providers identify the authenticated account's primary/default calendar.
        if (($calendar['is_primary'] ?? false) === true) {
            return true;
        }

        // Google "owner" is an ACL manager role, not necessarily the data owner.
        // Do not guess from that role or Microsoft's canEdit/canShare flags.
        return null;
    }

    private static function email(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? strtolower(trim($value)) : null;
    }
}
