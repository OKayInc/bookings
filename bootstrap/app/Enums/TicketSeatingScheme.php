<?php

namespace App\Enums;

enum TicketSeatingScheme: string
{
    case None = 'none';
    case Consecutive = 'consecutive';
    case SectionSeat = 'section_seat';
    case RowSeat = 'row_seat';
    case SectionRowSeat = 'section_row_seat';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Unassigned seating',
            self::Consecutive => 'Consecutive numbers (1, 2, 3…)',
            self::SectionSeat => 'Section + seat',
            self::RowSeat => 'Row + seat',
            self::SectionRowSeat => 'Section + row + seat',
        };
    }

    public function usesBlocks(): bool
    {
        return ! in_array($this, [self::None, self::Consecutive], true);
    }

    public function supportsOptionalSeat(): bool
    {
        return in_array($this, [self::SectionSeat, self::RowSeat], true);
    }
}
