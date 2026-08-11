import SearchableSelect from '@/components/searchable-select';
import {
    countryCodeOptions,
    countryNameOptions,
    currencyOptions,
    DEFAULT_COUNTRY_CODE,
    DEFAULT_COUNTRY_NAME,
    DEFAULT_CURRENCY_CODE,
    DEFAULT_LANGUAGE_CODE,
    DEFAULT_TIMEZONE,
    languageOptions,
    timezoneOptions,
} from '@/lib/reference-catalog';

type Catalog =
    'country-code' | 'country-name' | 'currency' | 'language' | 'timezone';

const catalogues = {
    'country-code': {
        options: countryCodeOptions,
        defaultValue: DEFAULT_COUNTRY_CODE,
    },
    'country-name': {
        options: countryNameOptions,
        defaultValue: DEFAULT_COUNTRY_NAME,
    },
    currency: { options: currencyOptions, defaultValue: DEFAULT_CURRENCY_CODE },
    language: { options: languageOptions, defaultValue: DEFAULT_LANGUAGE_CODE },
    timezone: { options: timezoneOptions, defaultValue: DEFAULT_TIMEZONE },
} satisfies Record<
    Catalog,
    { options: typeof countryCodeOptions; defaultValue: string }
>;

export default function ReferenceCatalogSelect({
    id,
    name,
    label,
    catalog,
    defaultValue,
    error,
    optional = false,
    value,
    onValueChange,
}: {
    id: string;
    name: string;
    label: string;
    catalog: Catalog;
    defaultValue?: string;
    error?: string;
    optional?: boolean;
    value?: string;
    onValueChange?: (value: string) => void;
}) {
    const definition = catalogues[catalog];

    return (
        <SearchableSelect
            id={id}
            name={name}
            label={label}
            options={definition.options}
            defaultValue={defaultValue ?? definition.defaultValue}
            value={value}
            onValueChange={onValueChange}
            error={error}
            optional={optional}
        />
    );
}
