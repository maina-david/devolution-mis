import { Form, usePage } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { interpolate } from '@/hooks/use-localization';
import { store } from '@/routes/evidence';

export default function EvidenceUploadForm({
    assessments,
}: {
    assessments: Array<{ id: string; label: string }>;
}) {
    const [assessmentId, setAssessmentId] = useState(assessments[0]?.id ?? '');
    const copy = usePage().props.localization.evidence;

    if (!assessmentId) {
        return null;
    }

    return (
        <section className="rounded-xl border border-border bg-card p-5 shadow-xs sm:p-6">
            <div className="flex items-start gap-3">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-[#147a55]/10 text-[#147a55]">
                    <Upload className="size-5" aria-hidden="true" />
                </span>
                <div>
                    <h2 className="font-bold text-foreground">
                        {copy.upload_evidence}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {copy.upload_evidence_description}
                    </p>
                </div>
            </div>
            <Form
                {...store.form({ assessment: assessmentId })}
                className="mt-5 grid gap-4 lg:grid-cols-[1.2fr_1fr_1fr_1fr_1.2fr_auto] lg:items-end"
                resetOnSuccess
            >
                {({ processing, errors, progress }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="assessment">
                                {copy.assessment}
                            </Label>
                            <select
                                id="assessment"
                                value={assessmentId}
                                onChange={(event) =>
                                    setAssessmentId(event.target.value)
                                }
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground"
                            >
                                {assessments.map((assessment) => (
                                    <option
                                        key={assessment.id}
                                        value={assessment.id}
                                    >
                                        {assessment.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="evidence-title">{copy.title}</Label>
                            <Input
                                id="evidence-title"
                                name="title"
                                required
                                aria-invalid={!!errors.title}
                                aria-describedby={
                                    errors.title
                                        ? 'evidence-title-error'
                                        : undefined
                                }
                            />
                            <InputError
                                id="evidence-title-error"
                                message={errors.title}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="evidence-category">
                                {copy.category}
                            </Label>
                            <Input
                                id="evidence-category"
                                name="category"
                                placeholder={copy.category_placeholder}
                                required
                                aria-invalid={!!errors.category}
                                aria-describedby={
                                    errors.category
                                        ? 'evidence-category-error'
                                        : undefined
                                }
                            />
                            <InputError
                                id="evidence-category-error"
                                message={errors.category}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="evidence-source-type">
                                {copy.source_type}
                            </Label>
                            <select
                                id="evidence-source-type"
                                name="source_type"
                                defaultValue="digital"
                                aria-invalid={!!errors.source_type}
                                aria-describedby={
                                    errors.source_type
                                        ? 'evidence-source-type-error'
                                        : undefined
                                }
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground"
                            >
                                <option value="digital">
                                    {copy.digital_file}
                                </option>
                                <option value="scanned">
                                    {copy.scanned_copy}
                                </option>
                            </select>
                            <InputError
                                id="evidence-source-type-error"
                                message={errors.source_type}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="evidence-document">
                                {copy.document}
                            </Label>
                            <Input
                                id="evidence-document"
                                name="document"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png,.webp,.tif,.tiff,.doc,.docx,.xls,.xlsx,.csv,.txt"
                                required
                                aria-invalid={!!errors.document}
                                aria-describedby={
                                    errors.document
                                        ? 'evidence-document-error'
                                        : undefined
                                }
                            />
                            <InputError
                                id="evidence-document-error"
                                message={errors.document}
                            />
                        </div>
                        <Button
                            type="submit"
                            disabled={processing}
                            aria-busy={processing}
                        >
                            <Upload aria-hidden="true" />
                            {progress
                                ? interpolate(copy.uploading, {
                                      percentage: progress.percentage ?? 0,
                                  })
                                : copy.upload}
                        </Button>
                    </>
                )}
            </Form>
        </section>
    );
}
