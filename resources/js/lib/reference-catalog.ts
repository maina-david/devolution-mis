import {
    getAllCountries,
    getAllTimezones,
    getCountry,
} from 'countries-and-timezones';
import { data as isoCurrencies } from 'currency-codes';
import ISO6391 from 'iso-639-1';
import type { SearchableSelectOption } from '@/components/searchable-select';
import referenceSnapshot from '../../reference-catalog.json';

const collator = new Intl.Collator('en', { sensitivity: 'base' });
const countries = Object.values(getAllCountries());
const timezones = Object.values(getAllTimezones()).filter(
    (timezone) => timezone.aliasOf === null,
);
const kenya = getCountry(referenceSnapshot.defaults.countryCode);

if (!kenya) {
    throw new Error('The installed ISO/IANA catalogue does not contain Kenya.');
}

const kenyaCurrency = isoCurrencies.find((currency) =>
    currency.countries.some(
        (country) =>
            country.toLocaleLowerCase() === kenya.name.toLocaleLowerCase(),
    ),
);

if (!kenyaCurrency) {
    throw new Error('The installed ISO 4217 catalogue does not contain Kenya.');
}

export const DEFAULT_COUNTRY_CODE = referenceSnapshot.defaults.countryCode;
export const DEFAULT_COUNTRY_NAME = referenceSnapshot.defaults.countryName;
export const DEFAULT_CURRENCY_CODE = referenceSnapshot.defaults.currencyCode;
export const DEFAULT_TIMEZONE = referenceSnapshot.defaults.timezone;
export const DEFAULT_LANGUAGE_CODE = referenceSnapshot.defaults.languageCode;
export const DEFAULT_LOCALE = referenceSnapshot.defaults.locale;

export function activeLocale(): string {
    if (typeof document === 'undefined') {
        return DEFAULT_LOCALE;
    }

    const language = document.documentElement.lang.trim();

    return language || DEFAULT_LOCALE;
}

export function countryCodeForName(name: string): string | undefined {
    return countries.find((country) => country.name === name)?.id;
}

export function formatCurrency(
    value: number,
    currency = DEFAULT_CURRENCY_CODE,
    options: Omit<Intl.NumberFormatOptions, 'style' | 'currency'> = {},
): string {
    return new Intl.NumberFormat(activeLocale(), {
        style: 'currency',
        currency,
        ...options,
    }).format(value);
}

export function formatNumber(
    value: number,
    options: Intl.NumberFormatOptions = {},
): string {
    return new Intl.NumberFormat(activeLocale(), options).format(value);
}

export function formatDateTime(
    value: string | number | Date,
    options: Intl.DateTimeFormatOptions = {},
): string {
    return new Intl.DateTimeFormat(activeLocale(), options).format(
        new Date(value),
    );
}

export const countryCodeOptions: SearchableSelectOption[] = countries
    .map((country) => ({
        id: country.id,
        name: `${country.name} (${country.id})`,
    }))
    .sort((left, right) => collator.compare(left.name, right.name));

export const countryNameOptions: SearchableSelectOption[] = countries
    .map((country) => ({
        id: country.name,
        name: `${country.name} (${country.id})`,
    }))
    .sort((left, right) => collator.compare(left.name, right.name));

export const currencyOptions: SearchableSelectOption[] = isoCurrencies
    .map((currency) => ({
        id: currency.code,
        name: `${currency.code} · ${currency.currency}`,
    }))
    .sort((left, right) => collator.compare(left.name, right.name));

export const timezoneOptions: SearchableSelectOption[] = timezones
    .map((timezone) => ({
        id: timezone.name,
        name: `${timezone.name} (UTC${timezone.utcOffsetStr})`,
    }))
    .sort((left, right) => collator.compare(left.name, right.name));

export const languageOptions: SearchableSelectOption[] = ISO6391.getLanguages(
    ISO6391.getAllCodes(),
)
    .map((language) => ({
        id: language.code,
        name: `${language.name}${language.nativeName === language.name ? '' : ` · ${language.nativeName}`} (${language.code})`,
    }))
    .sort((left, right) => collator.compare(left.name, right.name));
