import { usePage } from '@inertiajs/react';
import { Handshake, MapPinned } from 'lucide-react';
import { useState } from 'react';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
import KenyaCountyMap from '@/components/kenya-county-map';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    DEFAULT_CURRENCY_CODE,
    formatCurrency,
    formatNumber,
} from '@/lib/reference-catalog';

export type PartnerPortfolioCounty = CountyIdentityValue & {
    assessmentStatus: string;
    mapTone: 'active' | 'warning' | 'inactive';
    mapLabel: string;
    partnerCount: number;
    activeAgreementCount: number;
    committedAmount: number;
    disbursedAmount: number;
};

export default function PartnerPortfolioMap({
    showFullCountry,
    counties,
}: {
    showFullCountry: boolean;
    counties: PartnerPortfolioCounty[];
}) {
    const { localization } = usePage().props;
    const copy = localization.partnerCoordination;
    const [selected, setSelected] = useState<PartnerPortfolioCounty | null>(
        counties.length === 1 ? counties[0] : null,
    );
    const money = (value: number) =>
        formatCurrency(value, DEFAULT_CURRENCY_CODE, {
            maximumFractionDigits: 0,
        });

    return (
        <section className="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1.5fr)_minmax(19rem,0.7fr)]">
            <Card className="min-w-0 gap-0 overflow-hidden py-0">
                <CardHeader className="py-6">
                    <CardTitle className="flex items-center gap-2">
                        <MapPinned
                            className="text-primary"
                            aria-hidden="true"
                        />
                        {copy.geographic_partner_portfolio}
                    </CardTitle>
                    <CardDescription>
                        {showFullCountry
                            ? copy.nationwide_portfolio_description
                            : copy.single_county_portfolio_description}{' '}
                        {copy.select_active_county_description}
                    </CardDescription>
                </CardHeader>
                <CardContent className="p-0">
                    <KenyaCountyMap
                        counties={counties}
                        showFullCountry={showFullCountry}
                        selectedCountyId={selected?.id}
                        onSelect={setSelected}
                        className="rounded-none border-x-0 border-b-0"
                    />
                </CardContent>
            </Card>
            <Card aria-live="polite">
                <CardHeader>
                    <CardDescription>{copy.county_portfolio}</CardDescription>
                    <CardTitle>
                        {selected?.name ?? copy.select_authorized_county}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    {selected ? (
                        <div className="grid gap-5">
                            <CountyIdentity county={selected} />
                            <div className="grid grid-cols-2 gap-3">
                                <Metric
                                    label={copy.partners}
                                    value={formatNumber(selected.partnerCount)}
                                />
                                <Metric
                                    label={copy.active_agreements}
                                    value={formatNumber(
                                        selected.activeAgreementCount,
                                    )}
                                />
                                <Metric
                                    label={copy.committed}
                                    value={money(selected.committedAmount)}
                                />
                                <Metric
                                    label={copy.disbursed}
                                    value={money(selected.disbursedAmount)}
                                />
                            </div>
                            <Badge variant="outline" className="w-fit">
                                <Handshake />
                                {selected.mapLabel}
                            </Badge>
                            <p className="text-xs text-muted-foreground">
                                {copy.portfolio_selection_notice}
                            </p>
                        </div>
                    ) : (
                        <div className="grid min-h-56 place-items-center text-center text-sm text-muted-foreground">
                            {copy.select_county_on_map}
                        </div>
                    )}
                </CardContent>
            </Card>
        </section>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border bg-background p-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 font-semibold">{value}</p>
        </div>
    );
}
