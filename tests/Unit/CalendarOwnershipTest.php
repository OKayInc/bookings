<?php

namespace Tests\Unit;

use App\Domain\Calendars\CalendarOwnership;
use PHPUnit\Framework\TestCase;

class CalendarOwnershipTest extends TestCase
{
    public function test_ownership_uses_account_identity_not_edit_permissions(): void
    {
        $profile = ['email' => 'member@example.test', 'emails' => ['alias@example.test']];
        $cases = [
            [['is_primary' => true], true],
            [['owner_email' => ' MEMBER@EXAMPLE.TEST '], true],
            [['owner_email' => 'ALIAS@example.test'], true],
            [['owner_email' => 'other@example.test', 'can_write' => true], false],
            [['owner_email' => 'other@example.test', 'access_role' => 'owner'], false],
            [['access_role' => 'owner', 'can_write' => true], null],
            [['can_write' => true], null],
            [[], null],
        ];
        foreach ($cases as [$calendar, $expected]) {
            $this->assertSame($expected, CalendarOwnership::detect($calendar, $profile));
        }
        $this->assertNull(CalendarOwnership::detect(['owner_email' => 'member@example.test'], []));
    }
}
