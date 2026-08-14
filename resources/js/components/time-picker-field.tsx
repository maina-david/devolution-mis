import { useMemo, useState } from 'react';
import SearchableSelect from '@/components/searchable-select';
import { Label } from '@/components/ui/label';
import { interpolate, useCommonCopy } from '@/hooks/use-localization';

export default function TimePickerField({
    id,
    name,
    label,
    defaultValue = '09:00',
    value: controlledValue,
    onValueChange,
    error,
    required = false,
}: {
    id?: string;
    name?: string;
    label: string;
    defaultValue?: string;
    value?: string;
    onValueChange?: (value: string) => void;
    error?: string;
    required?: boolean;
}) {
    const copy = useCommonCopy();
    const [internalValue, setInternalValue] = useState(defaultValue);
    const value = controlledValue ?? internalValue;
    const controlId = id ?? name ?? 'time';
    const [hour = '09', minute = '00'] = value.split(':');
    const hours = useMemo(
        () =>
            Array.from({ length: 24 }, (_, index) =>
                String(index).padStart(2, '0'),
            ),
        [],
    );
    const minutes = useMemo(
        () =>
            Array.from({ length: 60 }, (_, index) =>
                String(index).padStart(2, '0'),
            ),
        [],
    );
    const update = (nextHour: string, nextMinute: string) => {
        const nextValue = `${nextHour}:${nextMinute}`;

        if (controlledValue === undefined) {
            setInternalValue(nextValue);
        }

        onValueChange?.(nextValue);
    };

    return (
        <div className="flex flex-col gap-2">
            <Label>{label}</Label>
            {name && (
                <input
                    type="hidden"
                    name={name}
                    value={value}
                    required={required}
                />
            )}
            <div className="grid grid-cols-2 gap-2">
                <SearchableSelect
                    id={`${controlId}-hour`}
                    label={interpolate(copy.hour_label, { label })}
                    options={hours.map((item) => ({ id: item, name: item }))}
                    value={hour}
                    onValueChange={(nextHour) => update(nextHour, minute)}
                />
                <SearchableSelect
                    id={`${controlId}-minute`}
                    label={interpolate(copy.minute_label, { label })}
                    options={minutes.map((item) => ({ id: item, name: item }))}
                    value={minute}
                    onValueChange={(nextMinute) => update(hour, nextMinute)}
                />
            </div>
            {error && (
                <p role="alert" className="text-xs text-destructive">
                    {error}
                </p>
            )}
        </div>
    );
}
