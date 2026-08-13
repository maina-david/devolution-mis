import SearchableSelect from '@/components/searchable-select';

export default function StaticSearchableSelect({
    id,
    name,
    values,
    defaultValue,
    value,
    onValueChange,
    label = '',
    error,
    labels,
}: {
    id: string;
    name: string;
    values: string[];
    defaultValue?: string;
    value?: string;
    onValueChange?: (value: string) => void;
    label?: string;
    error?: string;
    labels?: Record<string, string>;
}) {
    return (
        <SearchableSelect
            id={id}
            name={name}
            label={label}
            error={error}
            defaultValue={defaultValue ?? values[0]}
            value={value}
            onValueChange={onValueChange}
            options={values.map((value) => ({
                id: value,
                name: labels?.[value] ?? value.replaceAll('_', ' '),
            }))}
        />
    );
}
