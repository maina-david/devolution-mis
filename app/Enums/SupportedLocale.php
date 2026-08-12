<?php

namespace App\Enums;

enum SupportedLocale: string
{
    case English = 'en';
    case Kiswahili = 'sw';
    case French = 'fr';

    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Kiswahili => 'Kiswahili',
            self::French => 'Français',
        };
    }

    public function flag(): string
    {
        return match ($this) {
            self::English => '🇬🇧',
            self::Kiswahili => '🇰🇪',
            self::French => '🇫🇷',
        };
    }
}
