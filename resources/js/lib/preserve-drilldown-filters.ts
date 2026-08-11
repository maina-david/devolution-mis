const DRILLDOWN_FILTER_KEYS = [
    'from',
    'to',
    'search',
    'cycle_id',
    'status',
    'county_id',
    'programme_id',
    'sector_id',
    'partner_id',
    'financial_year',
    'assessment_id',
    'verification_status',
    'results_level',
] as const;

export function preserveDrilldownFilters(
    targetUrl: string,
    sourceUrl: string,
): string {
    const [targetPath, targetQuery = ''] = targetUrl.split('?');
    const sourceQuery = sourceUrl.split('?')[1] ?? '';
    const targetParameters = new URLSearchParams(targetQuery);
    const sourceParameters = new URLSearchParams(sourceQuery);

    for (const key of DRILLDOWN_FILTER_KEYS) {
        const value = sourceParameters.get(key);

        if (value !== null && value !== '') {
            targetParameters.set(key, value);
        }
    }

    const query = targetParameters.toString();

    return query ? `${targetPath}?${query}` : targetPath;
}
