import { Form, usePage } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
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
    grantId,
    meta,
    status,
}: {
    grantId: string;
    meta?: Record<string, string | null>;
    status?: string;
}) {
    const { localization } = usePage().props;
    const copy = localization.programmeWorkspace;
    const [open, setOpen] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={localization.common.open_row_actions}
                    >
                        <MoreHorizontal aria-hidden="true" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={() => setOpen(true)}>
                        {copy.update_grant}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="overflow-y-auto sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>{copy.update_grant}</SheetTitle>
                        <SheetDescription>
                            {copy.update_grant_description}
                        </SheetDescription>
                    </SheetHeader>
                    <Form
                        {...update.form({ grant: grantId })}
                        className="grid gap-4 px-4 pb-8"
                        onSuccess={() => setOpen(false)}
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`grant-allocation-${grantId}`}
                                    >
                                        {copy.allocated_amount}
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
                                        aria-invalid={Boolean(
                                            errors.allocated_amount,
                                        )}
                                        aria-describedby={
                                            errors.allocated_amount
                                                ? `grant-allocation-${grantId}-error`
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id={`grant-allocation-${grantId}-error`}
                                        message={errors.allocated_amount}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`grant-disbursed-${grantId}`}
                                    >
                                        {copy.disbursed_amount}
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
                                        aria-invalid={Boolean(
                                            errors.disbursed_amount,
                                        )}
                                        aria-describedby={
                                            errors.disbursed_amount
                                                ? `grant-disbursed-${grantId}-error`
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id={`grant-disbursed-${grantId}-error`}
                                        message={errors.disbursed_amount}
                                    />
                                </div>
                                <SearchableSelect
                                    id={`grant-status-${grantId}`}
                                    name="status"
                                    defaultValue={status}
                                    label={copy.status}
                                    error={errors.status}
                                    options={[
                                        'planned',
                                        'processing',
                                        'approved',
                                        'disbursed',
                                        'received',
                                    ].map((value) => ({
                                        id: value,
                                        name: copy[`status_${value}`],
                                    }))}
                                />
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={processing}
                                    aria-busy={processing}
                                >
                                    {copy.save_grant}
                                </Button>
                            </>
                        )}
                    </Form>
                </SheetContent>
            </Sheet>
        </>
    );
}
