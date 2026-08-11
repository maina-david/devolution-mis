import { Building2 } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

export type CountyIdentityValue = {
    kind: 'county';
    id: string;
    code: number;
    name: string;
    logoUrl: string | null;
    officialWebsiteUrl?: string | null;
    logoSourceUrl?: string | null;
    logoSourceAuthority?: string | null;
    logoSourceChecksum?: string | null;
    logoChecksum?: string | null;
    logoVerifiedAt?: string | null;
};

export type CountyIdentityGroupValue = {
    kind: 'county-list';
    items: CountyIdentityValue[];
};

export default function CountyIdentity({
    county,
    compact = false,
    inverse = false,
    className,
}: {
    county: CountyIdentityValue;
    compact?: boolean;
    inverse?: boolean;
    className?: string;
}) {
    const identity = (
        <span
            className={cn(
                'inline-flex min-w-0 items-center gap-2.5',
                className,
            )}
        >
            <Avatar
                className={cn(
                    'rounded-md border bg-background p-1',
                    compact ? 'size-8' : 'size-11',
                    inverse ? 'border-white/20' : 'border-border',
                )}
            >
                {county.logoUrl ? (
                    <AvatarImage
                        src={county.logoUrl}
                        alt={`${county.name} County official logo`}
                        className="object-contain"
                    />
                ) : null}
                <AvatarFallback className="rounded-sm">
                    <Building2
                        className="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                </AvatarFallback>
            </Avatar>
            <span className="min-w-0">
                <span className="block truncate font-medium">
                    {county.name}
                </span>
                {!compact && (
                    <span
                        className={cn(
                            'block text-xs',
                            inverse ? 'text-white/70' : 'text-muted-foreground',
                        )}
                    >
                        County {String(county.code).padStart(3, '0')}
                    </span>
                )}
            </span>
        </span>
    );

    if (!county.logoSourceAuthority) {
        return identity;
    }

    return (
        <TooltipProvider delayDuration={150}>
            <Tooltip>
                <TooltipTrigger asChild>{identity}</TooltipTrigger>
                <TooltipContent>
                    <p className="font-medium">Verified county identity</p>
                    <p>{county.logoSourceAuthority}</p>
                    {county.logoVerifiedAt && (
                        <p>Verified {county.logoVerifiedAt}</p>
                    )}
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}

export function CountyIdentityGroup({
    counties,
}: {
    counties: CountyIdentityValue[];
}) {
    if (counties.length === 0) {
        return <span className="text-muted-foreground">None</span>;
    }

    return (
        <span className="flex flex-wrap gap-2">
            {counties.map((county) => (
                <CountyIdentity key={county.id} county={county} compact />
            ))}
        </span>
    );
}
