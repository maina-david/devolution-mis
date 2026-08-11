import { Form } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';
import { useState } from 'react';
import SearchableSelect from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { update } from '@/routes/grants';

export default function GrantRowAction({
    teamSlug,
    grantId,
    meta,
    status,
}: {
    teamSlug: string;
    grantId: string;
    meta?: Record<string, string | null>;
    status?: string;
}) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Open row actions"
                    >
                        <MoreHorizontal aria-hidden="true" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={() => setOpen(true)}>
                        Update grant
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="overflow-y-auto sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>Update grant</SheetTitle>
                        <SheetDescription>
                            Record the approved allocation, cumulative
                            disbursement and current processing status.
                        </SheetDescription>
                    </SheetHeader>
                    <Form
                        {...update.form({
                            current_team: teamSlug,
                            grant: grantId,
                        })}
                        className="grid gap-4 px-4 pb-8"
                        onSuccess={() => setOpen(false)}
                    >
                        {({ processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`grant-allocation-${grantId}`}
                                    >
                                        Allocated amount
                                    </Label>
                                    <Input
                                        id={`grant-allocation-${grantId}`}
                                        name="allocated_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        defaultValue={
                                            meta?.allocatedAmount ?? ''
                                        }
                                        required
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`grant-disbursed-${grantId}`}
                                    >
                                        Disbursed amount
                                    </Label>
                                    <Input
                                        id={`grant-disbursed-${grantId}`}
                                        name="disbursed_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        defaultValue={
                                            meta?.disbursedAmount ?? ''
                                        }
                                        required
                                    />
                                </div>
                                <SearchableSelect
                                    id={`grant-status-${grantId}`}
                                    name="status"
                                    defaultValue={status}
                                    label="Status"
                                    options={[
                                        'planned',
                                        'processing',
                                        'approved',
                                        'disbursed',
                                        'received',
                                    ].map((value) => ({
                                        id: value,
                                        name: value,
                                    }))}
                                />
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={processing}
                                >
                                    Save grant
                                </Button>
                            </>
                        )}
                    </Form>
                </SheetContent>
            </Sheet>
        </>
    );
}
