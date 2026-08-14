import type { ICity, IState } from 'country-state-city';
import { useEffect, useMemo, useState } from 'react';
import ReferenceCatalogSelect from '@/components/reference-catalog-select';
import SearchableSelect from '@/components/searchable-select';
import { useCommonCopy } from '@/hooks/use-localization';
import {
    DEFAULT_COUNTRY_CODE,
    DEFAULT_COUNTRY_NAME,
    countryCodeForName,
} from '@/lib/reference-catalog';

export default function GeographyCatalogFields({
    countryError,
    subdivisionError,
    cityError,
}: {
    countryError?: string;
    subdivisionError?: string;
    cityError?: string;
}) {
    const copy = useCommonCopy();
    const [countryName, setCountryName] = useState(DEFAULT_COUNTRY_NAME);
    const [subdivisionName, setSubdivisionName] = useState('');
    const [cityName, setCityName] = useState('');
    const [subdivisions, setSubdivisions] = useState<IState[]>([]);
    const [cities, setCities] = useState<ICity[]>([]);
    const countryCode = useMemo(
        () =>
            countryCodeForName(countryName) ??
            (countryName === DEFAULT_COUNTRY_NAME
                ? DEFAULT_COUNTRY_CODE
                : undefined),
        [countryName],
    );
    const subdivision = subdivisions.find(
        (candidate) => candidate.name === subdivisionName,
    );

    useEffect(() => {
        let active = true;

        void import('country-state-city').then(({ State }) => {
            if (active) {
                setSubdivisions(
                    countryCode ? State.getStatesOfCountry(countryCode) : [],
                );
            }
        });

        return () => {
            active = false;
        };
    }, [countryCode]);

    useEffect(() => {
        let active = true;

        void import('country-state-city').then(({ City }) => {
            if (!active || !countryCode) {
                return;
            }

            setCities(
                subdivision
                    ? City.getCitiesOfState(countryCode, subdivision.isoCode)
                    : subdivisions.length === 0
                      ? (City.getCitiesOfCountry(countryCode) ?? [])
                      : [],
            );
        });

        return () => {
            active = false;
        };
    }, [countryCode, subdivision, subdivisions.length]);

    return (
        <>
            <ReferenceCatalogSelect
                id="travel-country"
                name="destination_country"
                label={copy.destination_country}
                catalog="country-name"
                value={countryName}
                onValueChange={(value) => {
                    setCountryName(value);
                    setSubdivisionName('');
                    setCityName('');
                }}
                error={countryError}
            />
            <SearchableSelect
                id="travel-subdivision"
                name="destination_county"
                label={copy.destination_region}
                options={subdivisions.map((candidate) => ({
                    id: candidate.name,
                    name: `${candidate.name} (${candidate.isoCode})`,
                }))}
                value={subdivisionName}
                onValueChange={(value) => {
                    setSubdivisionName(value);
                    setCityName('');
                }}
                optional
                error={subdivisionError}
            />
            <SearchableSelect
                id="travel-city"
                name="destination_city"
                label={copy.destination_city}
                options={cities.map((city) => ({
                    id: city.name,
                    name: city.name,
                }))}
                value={cityName}
                onValueChange={setCityName}
                error={cityError}
            />
        </>
    );
}
