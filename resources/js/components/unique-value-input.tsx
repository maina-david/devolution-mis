import { useHttp } from '@inertiajs/react';
import { CheckCircle2, LoaderCircle } from 'lucide-react';
import { useState } from 'react';
import UniqueValueController from '@/actions/App/Http/Controllers/UniqueValueController';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type UniqueResource = 'organizations' | 'sectors' | 'programmes';
type UniqueField = 'code' | 'name';

export default function UniqueValueInput({
    id,
    name,
    label,
    resource,
    field,
    teamSlug,
    serverError,
    required = false,
}: {
    id: string;
    name: string;
    label: string;
    resource: UniqueResource;
    field: UniqueField;
    teamSlug: string;
    serverError?: string;
    required?: boolean;
}) {
    const [value, setValue] = useState('');
    const [result, setResult] = useState<{
        available: boolean;
        message: string;
    } | null>(null);
    const request = useHttp<
        { resource: UniqueResource; field: UniqueField; value: string },
        { available: boolean; message: string }
    >(
        () => UniqueValueController(teamSlug),
        () => ({ resource, field, value: value.trim() }),
    );
    const feedbackId = `${id}-availability`;

    const checkAvailability = async (): Promise<void> => {
        if (value.trim() === '') {
            setResult(null);

            return;
        }

        const response = await request.submit();

        if (response) {
            setResult(response);
        }
    };

    const feedback = serverError ?? result?.message;
    const invalid = Boolean(serverError) || result?.available === false;

    return (
        <div className="flex flex-col gap-2">
            <Label htmlFor={id}>{label}</Label>
            <div className="relative">
                <Input
                    id={id}
                    name={name}
                    value={value}
                    required={required}
                    aria-invalid={invalid}
                    aria-describedby={feedback ? feedbackId : undefined}
                    className="pr-9"
                    onChange={(event) => {
                        setValue(event.target.value);
                        setResult(null);
                    }}
                    onBlur={() => void checkAvailability()}
                />
                {request.processing && (
                    <LoaderCircle
                        aria-label={`Checking ${label.toLocaleLowerCase()}`}
                        className="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-muted-foreground"
                    />
                )}
                {!request.processing && result?.available && (
                    <CheckCircle2
                        aria-hidden="true"
                        className="absolute top-1/2 right-3 size-4 -translate-y-1/2 text-primary"
                    />
                )}
            </div>
            {serverError ? (
                <InputError id={feedbackId} message={serverError} />
            ) : (
                feedback && (
                    <p
                        id={feedbackId}
                        role={result?.available ? 'status' : 'alert'}
                        className={cn(
                            'text-xs',
                            result?.available
                                ? 'text-muted-foreground'
                                : 'text-destructive',
                        )}
                    >
                        {feedback}
                    </p>
                )
            )}
        </div>
    );
}
