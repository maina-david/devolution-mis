import { readFile, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import process from 'node:process';
import {
    getAllCountries,
    getAllTimezones,
    getCountry,
} from 'countries-and-timezones';
import currencyCodes from 'currency-codes';
import ISO6391 from 'iso-639-1';
import { iso6393 } from 'iso-639-3';

const root = resolve(import.meta.dirname, '../../..');
const target = resolve(root, 'resources/reference-catalog.json');
const packageJson = JSON.parse(
    await readFile(resolve(root, 'package.json'), 'utf8'),
);
const policy = JSON.parse(
    await readFile(resolve(root, 'resources/reference-policy.json'), 'utf8'),
);
const sort = (values) =>
    [...new Set(values)].sort((left, right) => left.localeCompare(right, 'en'));
const applicationCountry = getCountry(policy.applicationCountryCode);

if (!applicationCountry) {
    throw new Error(
        'The installed country catalogue does not contain the application country.',
    );
}

const applicationCurrency = currencyCodes.data.find((currency) =>
    currency.countries.some(
        (country) =>
            country.toLocaleLowerCase() ===
            applicationCountry.name.toLocaleLowerCase(),
    ),
);

if (!applicationCurrency) {
    throw new Error(
        'The installed currency catalogue does not contain the application country currency.',
    );
}

const applicationLanguage = ISO6391.getCode(policy.applicationLanguageName);

if (!applicationLanguage) {
    throw new Error(
        'The installed language catalogue does not contain the application language.',
    );
}

const applicationOcrLanguage = iso6393.find(
    (language) => language.iso6391 === applicationLanguage,
);

if (!applicationOcrLanguage) {
    throw new Error(
        'The installed ISO 639-3 catalogue does not contain the application OCR language.',
    );
}

const snapshot = {
    schemaVersion: 3,
    packages: {
        countriesAndTimezones:
            packageJson.dependencies['countries-and-timezones'],
        countryStateCity: packageJson.dependencies['country-state-city'],
        currencyCodes: packageJson.dependencies['currency-codes'],
        iso6391: packageJson.dependencies['iso-639-1'],
        iso6393: packageJson.dependencies['iso-639-3'],
    },
    defaults: {
        countryCode: applicationCountry.id,
        countryName: applicationCountry.name,
        currencyCode: applicationCurrency.code,
        languageCode: applicationLanguage,
        ocrLanguageCode: applicationOcrLanguage.iso6393,
        locale: new Intl.Locale(applicationLanguage, {
            region: applicationCountry.id,
        }).toString(),
        timezone: applicationCountry.timezones[0],
    },
    countries: Object.values(getAllCountries())
        .map((country) => ({ code: country.id, name: country.name }))
        .sort((left, right) => left.code.localeCompare(right.code, 'en')),
    currencies: sort(currencyCodes.codes()),
    languages: sort(ISO6391.getAllCodes()),
    timezones: sort(
        Object.values(getAllTimezones())
            .filter((timezone) => timezone.aliasOf === null)
            .map((timezone) => timezone.name),
    ),
};
const serialized = `${JSON.stringify(snapshot, null, 2)}\n`;

if (process.argv.includes('--check')) {
    const current = await readFile(target, 'utf8').catch(() => '');

    if (current !== serialized) {
        process.stderr.write(
            'Reference catalogue snapshot is stale. Run npm run catalog:generate.\n',
        );
        process.exitCode = 1;
    }
} else {
    await writeFile(target, serialized, 'utf8');
    process.stdout.write(`Wrote ${target}.\n`);
}
