import AppLogoIcon from '@/components/app-logo-icon';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
import { Badge } from '@/components/ui/badge';

export default function AppLogo({
    county = null,
}: {
    county?: CountyIdentityValue | null;
}) {
    return (
        <>
            <div className="flex aspect-square size-9 items-center justify-center text-sidebar-foreground">
                <AppLogoIcon className="size-8" />
            </div>
            <div className="ml-1 grid flex-1 text-left">
                <span className="flex min-w-0 items-center gap-1.5">
                    <span className="shrink-0 text-sm leading-tight font-bold tracking-[-0.01em] text-sidebar-foreground">
                        IDMIS
                    </span>
                    {county ? (
                        <Badge
                            variant="outline"
                            className="min-w-0 border-sidebar-foreground/25 bg-sidebar-foreground/10 px-1.5 py-0 text-sidebar-foreground"
                        >
                            <CountyIdentity
                                county={county}
                                compact
                                className="max-w-full gap-1 text-[9px] leading-4 [&_[data-slot=avatar]]:size-4 [&_[data-slot=avatar]]:p-0.5"
                            />
                        </Badge>
                    ) : null}
                </span>
                <span className="truncate text-[0.65rem] leading-tight text-sidebar-foreground/75">
                    State Department for Devolution
                </span>
            </div>
        </>
    );
}
