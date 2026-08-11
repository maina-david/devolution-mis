import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';

export default function FormSheet({
    title,
    description,
    triggerLabel,
    icon: Icon,
    children,
    size = 'lg',
    triggerDisabled = false,
    triggerTitle,
}: {
    title: string;
    description: string;
    triggerLabel: string;
    icon?: LucideIcon;
    children: ReactNode;
    size?: 'md' | 'lg' | 'xl';
    triggerDisabled?: boolean;
    triggerTitle?: string;
}) {
    const [open, setOpen] = useState(false);
    const width = {
        md: 'sm:max-w-lg',
        lg: 'sm:max-w-2xl',
        xl: 'sm:max-w-4xl',
    }[size];

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <Button
                    variant="secondary"
                    disabled={triggerDisabled}
                    title={triggerTitle}
                >
                    {Icon && <Icon aria-hidden="true" />}
                    {triggerLabel}
                </Button>
            </SheetTrigger>
            <SheetContent className={`overflow-y-auto ${width}`}>
                <SheetHeader>
                    <SheetTitle>{title}</SheetTitle>
                    <SheetDescription>{description}</SheetDescription>
                </SheetHeader>
                {open && <div className="px-4 pb-8">{children}</div>}
            </SheetContent>
        </Sheet>
    );
}
