import { Form, usePage } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useMemo, useState } from 'react';
import GovernedAttachmentInput from '@/components/governed-attachment-input';
import InputError from '@/components/input-error';
import SearchableSelect from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { interpolate } from '@/hooks/use-localization';
import { store } from '@/routes/evidence';

type RequirementOption = {
    id: string;
    criterionId: string;
    label: string;
    allowedCategories: string[];
    acceptedMimeTypes: string[];
};

export default function CriterionEvidenceUploadForm({
    assessmentId,
    requirements,
}: {
    assessmentId: string;
    requirements: RequirementOption[];
}) {
    const [requirementId, setRequirementId] = useState(
        requirements[0]?.id ?? '',
    );
    const selected = useMemo(
        () => requirements.find((item) => item.id === requirementId),
        [requirementId, requirements],
    );
    const copy = usePage().props.localization.evidence;

    if (!selected) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>{copy.upload_criterion_evidence}</CardTitle>
                <CardDescription>
                    {copy.upload_criterion_evidence_description}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    {...store.form({ assessment: assessmentId })}
                    resetOnSuccess
                    className="grid gap-4 lg:grid-cols-2 xl:grid-cols-6 xl:items-end"
                >
                    {({ processing, errors, progress }) => (
                        <>
                            <div className="xl:col-span-2">
                                <SearchableSelect
                                    id="evidence-requirement"
                                    label={copy.evidence_requirement}
                                    options={requirements.map((item) => ({
                                        id: item.id,
                                        name: item.label,
                                    }))}
                                    value={requirementId}
                                    onValueChange={setRequirementId}
                                />
                            </div>
                            <input
                                type="hidden"
                                name="assessment_criterion_id"
                                value={selected.criterionId}
                            />
                            <input
                                type="hidden"
                                name="criterion_evidence_requirement_id"
                                value={selected.id}
                            />
                            <div className="grid gap-2">
                                <Label htmlFor="criterion-evidence-title">
                                    {copy.title}
                                </Label>
                                <Input
                                    id="criterion-evidence-title"
                                    name="title"
                                    required
                                    aria-invalid={Boolean(errors.title)}
                                    aria-describedby={
                                        errors.title
                                            ? 'criterion-evidence-title-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="criterion-evidence-title-error"
                                    message={errors.title}
                                />
                            </div>
                            <SearchableSelect
                                id="criterion-evidence-category"
                                name="category"
                                label={copy.category}
                                options={selected.allowedCategories.map(
                                    (category) => ({
                                        id: category,
                                        name: category,
                                    }),
                                )}
                                error={errors.category}
                            />
                            <SearchableSelect
                                id="criterion-evidence-source"
                                name="source_type"
                                label={copy.source}
                                options={[
                                    { id: 'digital', name: copy.digital_file },
                                    { id: 'scanned', name: copy.scanned_copy },
                                ]}
                                defaultValue="digital"
                                error={errors.source_type}
                            />
                            <GovernedAttachmentInput
                                id="criterion-evidence-file"
                                name="document"
                                label={copy.document}
                                accept={selected.acceptedMimeTypes.join(',')}
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
                                className="xl:col-start-6"
                            >
                                <Upload
                                    data-icon="inline-start"
                                    aria-hidden="true"
                                />
                                {progress
                                    ? interpolate(copy.uploading, {
                                          percentage: progress.percentage ?? 0,
                                      })
                                    : copy.upload_evidence}
                            </Button>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}
