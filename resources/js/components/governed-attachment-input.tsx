import { FileCheck2, FileUp, ShieldCheck, X } from 'lucide-react';
import { useId, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import {
    Attachment,
    AttachmentAction,
    AttachmentActions,
    AttachmentContent,
    AttachmentDescription,
    AttachmentMedia,
    AttachmentTitle,
} from '@/components/ui/attachment';
import { Button } from '@/components/ui/button';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Marker, MarkerContent, MarkerIcon } from '@/components/ui/marker';

export default function GovernedAttachmentInput({
    id,
    name,
    label,
    accept,
    required = false,
    error,
    progress,
    help,
    chooseLabel,
    removeLabel,
    selectedLabel,
    securityLabel,
}: {
    id?: string;
    name: string;
    label: string;
    accept?: string;
    required?: boolean;
    error?: string;
    progress?: number | null;
    help: string;
    chooseLabel: string;
    removeLabel: string;
    selectedLabel: string;
    securityLabel: string;
}) {
    const generatedId = useId();
    const inputId = id ?? generatedId;
    const inputRef = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const state = error
        ? 'error'
        : progress !== null && progress !== undefined
          ? 'uploading'
          : file
            ? 'done'
            : 'idle';

    return (
        <Field data-invalid={Boolean(error)}>
            <FieldLabel htmlFor={inputId}>{label}</FieldLabel>
            <Input
                ref={inputRef}
                id={inputId}
                name={name}
                type="file"
                accept={accept}
                required={required}
                className="sr-only"
                aria-invalid={Boolean(error)}
                aria-describedby={
                    error ? `${inputId}-error` : `${inputId}-help`
                }
                onChange={(event) => setFile(event.target.files?.[0] ?? null)}
            />
            <Attachment state={state} className="w-full">
                <AttachmentMedia>
                    {file ? <FileCheck2 /> : <FileUp />}
                </AttachmentMedia>
                <AttachmentContent>
                    <AttachmentTitle>
                        {file?.name ?? chooseLabel}
                    </AttachmentTitle>
                    <AttachmentDescription>
                        {file
                            ? `${selectedLabel} · ${formatBytes(file.size)}`
                            : help}
                    </AttachmentDescription>
                </AttachmentContent>
                <AttachmentActions>
                    {file ? (
                        <AttachmentAction
                            type="button"
                            aria-label={removeLabel}
                            onClick={() => {
                                if (inputRef.current) {
                                    inputRef.current.value = '';
                                }

                                setFile(null);
                            }}
                        >
                            <X />
                        </AttachmentAction>
                    ) : (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => inputRef.current?.click()}
                        >
                            <FileUp data-icon="inline-start" />
                            {chooseLabel}
                        </Button>
                    )}
                </AttachmentActions>
            </Attachment>
            <Marker>
                <MarkerIcon>
                    <ShieldCheck />
                </MarkerIcon>
                <MarkerContent>{securityLabel}</MarkerContent>
            </Marker>
            <FieldDescription id={`${inputId}-help`}>{help}</FieldDescription>
            <InputError id={`${inputId}-error`} message={error} />
        </Field>
    );
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    return `${(bytes / 1024 / 1024).toFixed(2)} MB`;
}
