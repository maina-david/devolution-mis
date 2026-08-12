import { Form } from '@inertiajs/react';
import { AlertTriangle, MoreHorizontal } from 'lucide-react';
import { resolveOperationalAlert } from '@/actions/App/Http/Controllers/PartnerCoordinationController';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
import FormSheet from '@/components/form-sheet';
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
    if (alerts.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <AlertHeader />
                </CardHeader>
                <CardContent>
                    <WorkspaceEmptyState
                        title="No partner operational alerts"
                        description="The scheduled monitor has not detected expiry or reconciliation exceptions in your county scope."
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
                            <TableHead>Alert</TableHead>
                            <TableHead>Partner / county</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Detected</TableHead>
                            <TableHead className="w-12">
                                <span className="sr-only">Actions</span>
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
                                            {alert.severity}
                                        </Badge>
                                        <div>
                                            <p className="font-medium">
                                                {alert.type.replaceAll(
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
                                        {alert.status.replaceAll('_', ' ')}
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
                                    ).toLocaleDateString()}
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
    return (
        <>
            <CardTitle className="flex items-center gap-2">
                <AlertTriangle className="text-amber-600" aria-hidden="true" />
                Operational control alerts
            </CardTitle>
            <CardDescription>
                Idempotent monitoring of agreement expiry and overdue,
                exception, or rejected contribution reconciliations.
            </CardDescription>
        </>
    );
}

function AlertActions({ alert }: { alert: PartnerOperationalAlert }) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    size="icon"
                    variant="ghost"
                    aria-label={`Actions for ${alert.type}`}
                >
                    <MoreHorizontal />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem asChild>
                    <FormSheet
                        title="Resolve operational alert"
                        triggerLabel="Record disposition"
                        description="Record remediation or a formally accepted risk decision; source evidence remains unchanged."
                    >
                        <Form
                            {...resolveOperationalAlert.form({
                                alert: alert.id,
                            })}
                            className="grid gap-4"
                        >
                            <SearchableSelect
                                id={`operational-status-${alert.id}`}
                                name="status"
                                label="Disposition"
                                options={[
                                    { id: 'resolved', name: 'Resolved' },
                                    {
                                        id: 'accepted_risk',
                                        name: 'Accepted risk',
                                    },
                                ]}
                            />
                            <label className="grid gap-2 text-sm font-medium">
                                Resolution evidence
                                <Textarea
                                    name="resolution"
                                    minLength={20}
                                    required
                                />
                            </label>
                            <Button type="submit">Record disposition</Button>
                        </Form>
                    </FormSheet>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
