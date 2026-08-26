<?php

namespace App\Enums;

enum QuestionType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Checkboxes = 'checkboxes';
    case Radio = 'radio';
    case Select = 'select';
    case Date = 'date';
    case Time = 'time';
    case DateTime = 'datetime';
    case Number = 'number';
    case File = 'file';
    case Email = 'email';
    case Telephone = 'telephone';
    case Address = 'address';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Open text',
            self::Textarea => 'Long text',
            self::Checkboxes => 'Multiple selection (checkboxes)',
            self::Radio => 'Unique selection (radio)',
            self::Select => 'Unique selection (select)',
            self::Date => 'Date',
            self::Time => 'Time',
            self::DateTime => 'Date and time',
            self::Number => 'Number',
            self::File => 'File upload',
            self::Email => 'Email',
            self::Telephone => 'Telephone',
            self::Address => 'Address',
        };
    }

    public function hasOptions(): bool
    {
        return in_array($this, [self::Checkboxes, self::Radio, self::Select], true);
    }
}
