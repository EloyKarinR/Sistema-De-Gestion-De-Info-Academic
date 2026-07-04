<?php

namespace App\Enums;

enum Shift: string
{
    case Matutino = 'matutino';
    case Vespertino = 'vespertino';

    /**
     * Get the display label for the shift.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Get the fixed time range for the shift.
     */
    public function timeRange(): string
    {
        return match ($this) {
            self::Matutino => '7:00 a.m. – 12:00 p.m.',
            self::Vespertino => '12:15 p.m. – 5:00 p.m.',
        };
    }

    /**
     * Get the label with the time range, e.g. "Matutino (7:00 a.m. – 12:00 p.m.)".
     */
    public function labelWithTime(): string
    {
        return "{$this->label()} ({$this->timeRange()})";
    }

    /**
     * Get the badge color associated with the shift.
     */
    public function color(): string
    {
        return match ($this) {
            self::Matutino => 'amber',
            self::Vespertino => 'indigo',
        };
    }

    /**
     * Get the icon associated with the shift.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Matutino => 'sun',
            self::Vespertino => 'moon',
        };
    }

    /**
     * Get all shifts as [value => labelWithTime] pairs, for select options.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $shift) => [$shift->value => $shift->labelWithTime()])
            ->all();
    }
}
