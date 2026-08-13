import { Form, usePage } from '@inertiajs/react';
import { ClipboardPlusIcon, UploadIcon } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import SearchableSelect from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { interpolate } from '@/hooks/use-localization';
import { store } from '@/routes/evidence';

export default function EvidenceUploadForm({
    assessments,
}: {
    assessments: Array<{ id: string; label: string }>;
}) {
    const [open, setOpen] = useState(false);
    const [assessmentId, setAssessmentId] = useState(assessments[0]?.id ?? '');
    const copy = usePage().props.localization.evidence;

    if (!assessmentId) {
        return null;
    }

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <Button type="button" variant="outline">
                    <ClipboardPlusIcon aria-hidden="true" />
                    {copy.upload_evidence}
                </Button>
            </SheetTrigger>
            <SheetContent className="overflow-y-auto sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>{copy.upload_evidence}</SheetTitle>
                    <SheetDescription>
                        {copy.upload_evidence_description}
                    </SheetDescription>
                </SheetHeader>
                <Form
                    {...store.form({ assessment: assessmentId })}
                    className="grid gap-5 px-4 pb-6"
                    resetOnSuccess
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing, errors, progress }) => (
                        <>
                            <SearchableSelect
                                id="assessment-evidence-assessment"
                                label={copy.assessment}
                                options={assessments.map((assessment) => ({
                                    id: assessment.id,
                                    name: assessment.label,
                                }))}
                                value={assessmentId}
                                onValueChange={setAssessmentId}
                            />
                            <div className="grid gap-2">
                                <Label htmlFor="evidence-title">
                                    {copy.title}
                                </Label>
                                <Input
                                    id="evidence-title"
                                    name="title"
                                    required
                                    aria-invalid={Boolean(errors.title)}
                                />
                                <InputError message={errors.title} />
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
                                    aria-invalid={Boolean(errors.category)}
                                />
                                <InputError message={errors.category} />
                            </div>
                            <SearchableSelect
                                id="evidence-source-type"
                                name="source_type"
                                label={copy.source_type}
                                options={[
                                    {
                                        id: 'digital',
                                        name: copy.digital_file,
                                    },
                                    {
                                        id: 'scanned',
                                        name: copy.scanned_copy,
                                    },
                                ]}
                                defaultValue="digital"
                                error={errors.source_type}
                            />
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
                                    aria-invalid={Boolean(errors.document)}
                                />
                                <InputError message={errors.document} />
                            </div>
                            <Button
                                type="submit"
                                disabled={processing}
                                aria-busy={processing}
                            >
                                <UploadIcon aria-hidden="true" />
                                {progress
                                    ? interpolate(copy.uploading, {
                                          percentage: progress.percentage ?? 0,
                                      })
                                    : copy.upload}
                            </Button>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}
