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
    const [selected, setSelected] = useState<PartnerPortfolioCounty | null>(
        counties.length === 1 ? counties[0] : null,
    );
    const money = (value: number) =>
        `KES ${value.toLocaleString(undefined, { maximumFractionDigits: 0 })}`;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <MapPinned className="text-primary" aria-hidden="true" />
                    Geographic partner portfolio
                </CardTitle>
                <CardDescription>
                    {showFullCountry
                        ? 'Nationwide geography with only authorized portfolio counties activated.'
                        : 'Your county is automatically zoomed and isolated.'}{' '}
                    Select an active county to inspect its partner portfolio.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-5 xl:grid-cols-[minmax(0,1.5fr)_minmax(18rem,0.65fr)]">
                <div className="mx-auto flex min-h-72 w-full max-w-xl items-center overflow-hidden rounded-xl bg-[#eef4f0] p-4 dark:bg-[#0c2830]">
                    <KenyaCountyMap
                        counties={counties}
                        showFullCountry={showFullCountry}
                        selectedCountyId={selected?.id}
                        onSelect={setSelected}
                    />
                </div>
                <aside className="rounded-xl border bg-muted/25 p-5">
                    {selected ? (
                        <div className="grid gap-5">
                            <CountyIdentity county={selected} />
                            <div className="grid grid-cols-2 gap-3">
                                <Metric
                                    label="Partners"
                                    value={selected.partnerCount.toString()}
                                />
                                <Metric
                                    label="Active agreements"
                                    value={selected.activeAgreementCount.toString()}
                                />
                                <Metric
                                    label="Committed"
                                    value={money(selected.committedAmount)}
                                />
                                <Metric
                                    label="Disbursed"
                                    value={money(selected.disbursedAmount)}
                                />
                            </div>
                            <Badge variant="outline" className="w-fit">
                                <Handshake />
                                {selected.mapLabel}
                            </Badge>
                            <p className="text-xs text-muted-foreground">
                                The selection updates this portfolio summary
                                without leaving the partner workspace.
                            </p>
                        </div>
                    ) : (
                        <div className="grid min-h-56 place-items-center text-center text-sm text-muted-foreground">
                            Select an authorized county on the map to inspect
                            its portfolio.
                        </div>
                    )}
                </aside>
            </CardContent>
        </Card>
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
