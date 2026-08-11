<?php

namespace App\Support;

use JsonException;
use RuntimeException;

class ReferenceCatalogue
{
    /** @var array{schemaVersion:int, packages:array<string, string>, defaults:array{countryCode:string,countryName:string,currencyCode:string,languageCode:string,ocrLanguageCode:string,locale:string,timezone:string}, countries:list<array{code:string, name:string}>, currencies:list<string>, languages:list<string>, timezones:list<string>}|null */
    private static ?array $catalogue = null;

    /** @return list<string> */
    public static function countryCodes(): array
    {
        return array_column(self::load()['countries'], 'code');
    }

    /** @return list<string> */
    public static function countryNames(): array
    {
        return array_column(self::load()['countries'], 'name');
    }

    /** @return list<string> */
    public static function currencies(): array
    {
        return self::load()['currencies'];
    }

    /** @return list<string> */
    public static function languages(): array
    {
        return self::load()['languages'];
    }

    /** @return list<string> */
    public static function timezones(): array
    {
        return self::load()['timezones'];
    }

    /** @return array<string, string> */
    public static function packageVersions(): array
    {
        return self::load()['packages'];
    }

    public static function defaultCountryCode(): string
    {
        return self::load()['defaults']['countryCode'];
    }

    public static function defaultCountryName(): string
    {
        return self::load()['defaults']['countryName'];
    }

    public static function defaultCurrency(): string
    {
        return self::load()['defaults']['currencyCode'];
    }

    public static function defaultLanguage(): string
    {
        return self::load()['defaults']['languageCode'];
    }

    public static function defaultOcrLanguage(): string
    {
        return self::load()['defaults']['ocrLanguageCode'];
    }

    public static function defaultLocale(): string
    {
        return self::load()['defaults']['locale'];
    }

    public static function defaultTimezone(): string
    {
        return self::load()['defaults']['timezone'];
    }

    /** @return array{schemaVersion:int, packages:array<string, string>, defaults:array{countryCode:string,countryName:string,currencyCode:string,languageCode:string,ocrLanguageCode:string,locale:string,timezone:string}, countries:list<array{code:string, name:string}>, currencies:list<string>, languages:list<string>, timezones:list<string>} */
    private static function load(): array
    {
        if (self::$catalogue !== null) {
            return self::$catalogue;
        }

        $path = resource_path('reference-catalog.json');
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('The generated reference catalogue is unavailable.');
        }

        try {
            $catalogue = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The generated reference catalogue is malformed.', previous: $exception);
        }

        if (! is_array($catalogue) || ($catalogue['schemaVersion'] ?? null) !== 3) {
            throw new RuntimeException('The generated reference catalogue has an unsupported schema.');
        }

        $packages = self::stringMap($catalogue['packages'] ?? null, 'packages');
        $defaults = self::defaults($catalogue['defaults'] ?? null);
        $countries = self::countriesList($catalogue['countries'] ?? null);
        $currencies = self::stringList($catalogue['currencies'] ?? null, 'currencies');
        $languages = self::stringList($catalogue['languages'] ?? null, 'languages');
        $timezones = self::stringList($catalogue['timezones'] ?? null, 'timezones');

        self::$catalogue = compact('defaults', 'countries', 'currencies', 'languages', 'timezones') + [
            'schemaVersion' => 3,
            'packages' => $packages,
        ];

        return self::$catalogue;
    }

    /** @return array{countryCode:string,countryName:string,currencyCode:string,languageCode:string,ocrLanguageCode:string,locale:string,timezone:string} */
    private static function defaults(mixed $value): array
    {
        $defaults = self::stringMap($value, 'defaults');
        foreach (['countryCode', 'countryName', 'currencyCode', 'languageCode', 'ocrLanguageCode', 'locale', 'timezone'] as $key) {
            if (! isset($defaults[$key]) || $defaults[$key] === '') {
                throw new RuntimeException("The generated reference catalogue default {$key} is invalid.");
            }
        }

        return [
            'countryCode' => $defaults['countryCode'],
            'countryName' => $defaults['countryName'],
            'currencyCode' => $defaults['currencyCode'],
            'languageCode' => $defaults['languageCode'],
            'ocrLanguageCode' => $defaults['ocrLanguageCode'],
            'locale' => $defaults['locale'],
            'timezone' => $defaults['timezone'],
        ];
    }

    /** @return array<string, string> */
    private static function stringMap(mixed $value, string $field): array
    {
        if (! is_array($value)) {
            throw new RuntimeException("The generated reference catalogue {$field} field is invalid.");
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (! is_string($key) || ! is_string($item)) {
                throw new RuntimeException("The generated reference catalogue {$field} field is invalid.");
            }
            $normalized[$key] = $item;
        }

        return $normalized;
    }

    /** @return list<string> */
    private static function stringList(mixed $value, string $field): array
    {
        if (! is_array($value)) {
            throw new RuntimeException("The generated reference catalogue {$field} field is invalid.");
        }

        $normalized = [];
        foreach ($value as $item) {
            if (! is_string($item)) {
                throw new RuntimeException("The generated reference catalogue {$field} field is invalid.");
            }
            $normalized[] = $item;
        }

        if ($normalized === []) {
            throw new RuntimeException("The generated reference catalogue {$field} field is empty.");
        }

        return $normalized;
    }

    /** @return list<array{code:string, name:string}> */
    private static function countriesList(mixed $value): array
    {
        if (! is_array($value)) {
            throw new RuntimeException('The generated reference catalogue countries field is invalid.');
        }

        $countries = [];
        foreach ($value as $country) {
            if (! is_array($country) || ! is_string($country['code'] ?? null) || ! is_string($country['name'] ?? null)) {
                throw new RuntimeException('The generated reference catalogue countries field is invalid.');
            }
            $countries[] = ['code' => $country['code'], 'name' => $country['name']];
        }

        if ($countries === []) {
            throw new RuntimeException('The generated reference catalogue countries field is empty.');
        }

        return $countries;
    }
}
