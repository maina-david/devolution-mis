import { Form, usePage } from '@inertiajs/react';
import { AlertTriangle, MoreHorizontal } from 'lucide-react';
import { resolveOperationalAlert } from '@/actions/App/Http/Controllers/PartnerCoordinationController';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
import FormSheet from '@/components/form-sheet';
import InputError from '@/components/input-error';
import SearchableSelect from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import WorkspaceEmptyState from '@/components/workspace-empty-state';
import { interpolate } from '@/hooks/use-localization';

export type PartnerOperationalAlert = {
    id: string;
    type: string;
    severity: string;
    status: string;
    summary: string;
    partner: string;
    county: CountyIdentityValue | null;
    dueOn: string | null;
    detectedAt: string;
    resolution: string | null;
};

export default function PartnerOperationalAlerts({
    alerts,
    canResolve,
}: {
    alerts: PartnerOperationalAlert[];
    canResolve: boolean;
}) {
    const { current: locale, partnerCoordination: copy } =
        usePage().props.localization;

    if (alerts.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <AlertHeader />
                </CardHeader>
                <CardContent>
                    <WorkspaceEmptyState
                        title={copy.no_operational_alerts}
                        description={copy.no_operational_alerts_description}
                        className="min-h-48 border-0"
                    />
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <AlertHeader />
            </CardHeader>
            <CardContent className="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{copy.alert}</TableHead>
                            <TableHead>{copy.partner_county}</TableHead>
                            <TableHead>{copy.status}</TableHead>
                            <TableHead>{copy.detected}</TableHead>
                            <TableHead className="w-12">
                                <span className="sr-only">{copy.actions}</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {alerts.map((alert) => (
                            <TableRow key={alert.id}>
                                <TableCell>
                                    <div className="flex gap-2">
                                        <Badge
                                            variant={
                                                alert.severity === 'critical'
                                                    ? 'destructive'
                                                    : 'secondary'
                                            }
                                        >
                                            {copy[
                                                `severity_${alert.severity}`
                                            ] ?? alert.severity}
                                        </Badge>
                                        <div>
                                            <p className="font-medium">
                                                {copy[
                                                    `alert_type_${alert.type}`
                                                ] ??
                                                    alert.type.replaceAll(
                                                        '_',
                                                        ' ',
                                                    )}
                                            </p>
                                            <p className="max-w-xl text-xs text-muted-foreground">
                                                {alert.summary}
                                            </p>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <p className="mb-2 text-sm font-medium">
                                        {alert.partner}
                                    </p>
                                    {alert.county && (
                                        <CountyIdentity
                                            county={alert.county}
                                            compact
                                        />
                                    )}
                                </TableCell>
                                <TableCell>
                                    <Badge variant="outline">
                                        {copy[`status_${alert.status}`] ??
                                            alert.status.replaceAll('_', ' ')}
                                    </Badge>
                                    {alert.resolution && (
                                        <p className="mt-1 max-w-xs text-xs text-muted-foreground">
                                            {alert.resolution}
                                        </p>
                                    )}
                                </TableCell>
                                <TableCell>
                                    {new Date(
                                        alert.detectedAt,
                                    ).toLocaleDateString(locale)}
                                </TableCell>
                                <TableCell>
                                    {canResolve && alert.status === 'open' && (
                                        <AlertActions alert={alert} />
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    );
}

function AlertHeader() {
    const copy = usePage().props.localization.partnerCoordination;

    return (
        <>
            <CardTitle className="flex items-center gap-2">
                <AlertTriangle className="text-amber-600" aria-hidden="true" />
                {copy.operational_control_alerts}
            </CardTitle>
            <CardDescription>
                {copy.operational_control_alerts_description}
            </CardDescription>
        </>
    );
}

function AlertActions({ alert }: { alert: PartnerOperationalAlert }) {
    const copy = usePage().props.localization.partnerCoordination;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    size="icon"
                    variant="ghost"
                    aria-label={interpolate(copy.alert_actions, {
                        alert: copy[`alert_type_${alert.type}`] ?? alert.type,
                    })}
                >
                    <MoreHorizontal aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem asChild>
                    <FormSheet
                        title={copy.resolve_operational_alert}
                        triggerLabel={copy.record_disposition}
                        description={copy.resolve_operational_alert_description}
                    >
                        <Form
                            {...resolveOperationalAlert.form({
                                alert: alert.id,
                            })}
                            className="grid gap-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <SearchableSelect
                                        id={`operational-status-${alert.id}`}
                                        name="status"
                                        label={copy.disposition}
                                        error={errors.status}
                                        options={[
                                            {
                                                id: 'resolved',
                                                name: copy.status_resolved,
                                            },
                                            {
                                                id: 'accepted_risk',
                                                name: copy.status_accepted_risk,
                                            },
                                        ]}
                                    />
                                    <label
                                        htmlFor={`operational-resolution-${alert.id}`}
                                        className="grid gap-2 text-sm font-medium"
                                    >
                                        {copy.resolution_evidence}
                                        <Textarea
                                            id={`operational-resolution-${alert.id}`}
                                            name="resolution"
                                            minLength={20}
                                            required
                                            aria-invalid={Boolean(
                                                errors.resolution,
                                            )}
                                            aria-describedby={
                                                errors.resolution
                                                    ? `operational-resolution-${alert.id}-error`
                                                    : undefined
                                            }
                                        />
                                    </label>
                                    <InputError
                                        id={`operational-resolution-${alert.id}-error`}
                                        message={errors.resolution}
                                    />
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        aria-busy={processing}
                                    >
                                        {copy.record_disposition}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </FormSheet>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
