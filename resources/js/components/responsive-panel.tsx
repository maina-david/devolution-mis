import type { ReactNode } from 'react';
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerHeader,
    DrawerTitle,
    DrawerTrigger,
} from '@/components/ui/drawer';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';

export default function ResponsivePanel({
    open,
    onOpenChange,
    trigger,
    title,
    description,
    children,
    className,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    trigger?: ReactNode;
    title: string;
    description: string;
    children: ReactNode;
    className?: string;
}) {
    const isMobile = useIsMobile();

    if (isMobile) {
        return (
            <Drawer open={open} onOpenChange={onOpenChange}>
                {trigger && <DrawerTrigger asChild>{trigger}</DrawerTrigger>}
                <DrawerContent className="max-h-[92dvh]">
                    <DrawerHeader className="text-left">
                        <DrawerTitle>{title}</DrawerTitle>
                        <DrawerDescription>{description}</DrawerDescription>
                    </DrawerHeader>
                    <div className={cn('overflow-y-auto px-4 pb-6', className)}>
                        {children}
                    </div>
                </DrawerContent>
            </Drawer>
        );
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            {trigger && <SheetTrigger asChild>{trigger}</SheetTrigger>}
            <SheetContent
                className={cn('overflow-y-auto sm:max-w-lg', className)}
            >
                <SheetHeader>
                    <SheetTitle>{title}</SheetTitle>
                    <SheetDescription>{description}</SheetDescription>
                </SheetHeader>
                {children}
            </SheetContent>
        </Sheet>
    );
}
