import * as React from 'react';
import { ChevronDownIcon, ChevronLeftIcon, ChevronRightIcon } from 'lucide-react';
import { DayPicker, getDefaultClassNames, type DayButton } from 'react-day-picker';
import { cn } from '@/lib/utils';
import { Button, buttonVariants } from '@/components/ui/button';

function Calendar({ className, classNames, showOutsideDays = true, captionLayout = 'label', ...props }: React.ComponentProps<typeof DayPicker>) {
    const defaults = getDefaultClassNames();
    return <DayPicker showOutsideDays={showOutsideDays} captionLayout={captionLayout} className={cn('bg-background p-3 [--cell-size:--spacing(8)]', className)} classNames={{ months: 'flex flex-col gap-4 sm:flex-row', month: 'flex flex-col gap-4', nav: 'absolute inset-x-3 top-3 flex justify-between', button_previous: cn(buttonVariants({ variant: 'ghost' }), 'size-(--cell-size) p-0'), button_next: cn(buttonVariants({ variant: 'ghost' }), 'size-(--cell-size) p-0'), month_caption: 'flex h-(--cell-size) items-center justify-center px-(--cell-size)', weekdays: 'flex', weekday: 'flex-1 text-center text-xs text-muted-foreground', week: 'mt-2 flex', day: 'relative aspect-square size-(--cell-size) p-0 text-center', range_start: 'rounded-l-md bg-accent', range_middle: 'rounded-none bg-accent', range_end: 'rounded-r-md bg-accent', today: 'rounded-md bg-accent text-accent-foreground', outside: 'text-muted-foreground opacity-50', disabled: 'text-muted-foreground opacity-50', hidden: 'invisible', ...classNames }} components={{ Chevron: ({ orientation }) => orientation === 'left' ? <ChevronLeftIcon /> : orientation === 'right' ? <ChevronRightIcon /> : <ChevronDownIcon />, DayButton: CalendarDayButton }} {...props} />;
}

function CalendarDayButton({ day, modifiers, ...props }: React.ComponentProps<typeof DayButton>) {
    return <Button variant="ghost" size="icon" data-day={day.date.toLocaleDateString()} data-selected={modifiers.selected} className="size-(--cell-size) data-[selected=true]:bg-primary data-[selected=true]:text-primary-foreground" {...props} />;
}

export { Calendar, CalendarDayButton };
