import { Form, usePage } from '@inertiajs/react';
import { ClipboardPlusIcon, UploadIcon } from 'lucide-react';
import { useState } from 'react';
import GovernedAttachmentInput from '@/components/governed-attachment-input';
import InputError from '@/components/input-error';
import ResponsivePanel from '@/components/responsive-panel';
import SearchableSelect from '@/components/searchable-select';
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
    const [open, setOpen] = useState(false);
    const [assessmentId, setAssessmentId] = useState(assessments[0]?.id ?? '');
    const copy = usePage().props.localization.evidence;

    if (!assessmentId) {
        return null;
    }

    return (
        <ResponsivePanel
            open={open}
            onOpenChange={setOpen}
            title={copy.upload_evidence}
            description={copy.upload_evidence_description}
            trigger={
                <Button type="button" variant="outline">
                    <ClipboardPlusIcon aria-hidden="true" />
                    {copy.upload_evidence}
                </Button>
            }
        >
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
                            <Label htmlFor="evidence-title">{copy.title}</Label>
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
                        <GovernedAttachmentInput
                            id="evidence-document"
                            name="document"
                            label={copy.document}
                            accept=".pdf,.jpg,.jpeg,.png,.webp,.tif,.tiff,.doc,.docx,.xls,.xlsx,.csv,.txt"
                            required
                            error={errors.document}
                            progress={progress?.percentage}
                            help={copy.attachment_help}
                            chooseLabel={copy.choose_file}
                            removeLabel={copy.remove_file}
                            selectedLabel={copy.selected_file}
                            securityLabel={copy.attachment_security}
                        />
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
        </ResponsivePanel>
    );
}
