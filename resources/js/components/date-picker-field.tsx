import { format } from 'date-fns';
import { CalendarIcon } from 'lucide-react';
import { useEffect, useId, useState } from 'react';
import TimePickerField from '@/components/time-picker-field';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export default function DatePickerField({
    name,
    label,
    error,
    required = false,
    includeTime = false,
    defaultValue = '',
    min,
    value: controlledValue,
    onValueChange,
}: {
    name: string;
    label: string;
    error?: string;
    required?: boolean;
    includeTime?: boolean;
    defaultValue?: string;
    min?: string;
    value?: string;
    onValueChange?: (value: string) => void;
}) {
    const generatedId = useId();
    const sourceValue = controlledValue ?? defaultValue;
    const initialDate = sourceValue ? new Date(sourceValue) : undefined;
    const [date, setDate] = useState<Date | undefined>(
        initialDate && !Number.isNaN(initialDate.valueOf())
            ? initialDate
            : undefined,
    );
    const [time, setTime] = useState(
        initialDate ? format(initialDate, 'HH:mm') : '09:00',
    );
    const id = `date-${name.replaceAll(/[^a-zA-Z0-9_-]/g, '-')}-${generatedId}`;
    const guidanceId = `${id}-guidance`;
    const errorId = `${id}-error`;
    const value = date
        ? `${format(date, 'yyyy-MM-dd')}${includeTime ? `T${time}` : ''}`
        : '';

    useEffect(() => {
        onValueChange?.(value);
    }, [onValueChange, value]);

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <input type="hidden" name={name} value={value} />
            <div className="flex gap-2">
                <Popover>
                    <PopoverTrigger asChild>
                        <Button
                            id={id}
                            type="button"
                            variant="outline"
                            aria-required={required}
                            aria-invalid={Boolean(error)}
                            aria-describedby={
                                error
                                    ? errorId
                                    : required && !value
                                      ? guidanceId
                                      : undefined
                            }
                            className={cn(
                                'flex-1 justify-start font-normal',
                                !date && 'text-muted-foreground',
                            )}
                        >
                            <CalendarIcon aria-hidden="true" />
                            {date ? format(date, 'dd MMM yyyy') : 'Select date'}
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-auto p-0" align="start">
                        <Calendar
                            mode="single"
                            selected={date}
                            onSelect={setDate}
                            disabled={
                                min
                                    ? {
                                          before: new Date(`${min}T00:00:00`),
                                      }
                                    : undefined
                            }
                        />
                    </PopoverContent>
                </Popover>
            </div>
            {includeTime && (
                <TimePickerField
                    id={`${name}-time`}
                    label={`${label} time`}
                    value={time}
                    onValueChange={setTime}
                    required={required}
                />
            )}
            {required && !value && (
                <p id={guidanceId} className="text-xs text-muted-foreground">
                    Required
                </p>
            )}
            {error && (
                <p
                    id={errorId}
                    role="alert"
                    className="text-xs text-destructive"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
