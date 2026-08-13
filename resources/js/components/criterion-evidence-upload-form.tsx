import { Form, usePage } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
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
                            <div className="grid gap-2 xl:col-span-2">
                                <Label htmlFor="evidence-requirement">
                                    {copy.evidence_requirement}
                                </Label>
                                <select
                                    id="evidence-requirement"
                                    value={requirementId}
                                    onChange={(event) =>
                                        setRequirementId(event.target.value)
                                    }
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    {requirements.map((item) => (
                                        <option key={item.id} value={item.id}>
                                            {item.label}
                                        </option>
                                    ))}
                                </select>
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
                            <div className="grid gap-2">
                                <Label htmlFor="criterion-evidence-category">
                                    {copy.category}
                                </Label>
                                <select
                                    id="criterion-evidence-category"
                                    name="category"
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                    required
                                    aria-invalid={Boolean(errors.category)}
                                    aria-describedby={
                                        errors.category
                                            ? 'criterion-evidence-category-error'
                                            : undefined
                                    }
                                >
                                    {selected.allowedCategories.map(
                                        (category) => (
                                            <option
                                                key={category}
                                                value={category}
                                            >
                                                {category}
                                            </option>
                                        ),
                                    )}
                                </select>
                                <InputError
                                    id="criterion-evidence-category-error"
                                    message={errors.category}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="criterion-evidence-source">
                                    {copy.source}
                                </Label>
                                <select
                                    id="criterion-evidence-source"
                                    name="source_type"
                                    defaultValue="digital"
                                    aria-invalid={Boolean(errors.source_type)}
                                    aria-describedby={
                                        errors.source_type
                                            ? 'criterion-evidence-source-error'
                                            : undefined
                                    }
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    <option value="digital">
                                        {copy.digital_file}
                                    </option>
                                    <option value="scanned">
                                        {copy.scanned_copy}
                                    </option>
                                </select>
                                <InputError
                                    id="criterion-evidence-source-error"
                                    message={errors.source_type}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="criterion-evidence-file">
                                    {copy.document}
                                </Label>
                                <Input
                                    id="criterion-evidence-file"
                                    name="document"
                                    type="file"
                                    accept={selected.acceptedMimeTypes.join(
                                        ',',
                                    )}
                                    required
                                    aria-invalid={Boolean(errors.document)}
                                    aria-describedby={
                                        errors.document
                                            ? 'criterion-evidence-file-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="criterion-evidence-file-error"
                                    message={errors.document}
                                />
                            </div>
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
