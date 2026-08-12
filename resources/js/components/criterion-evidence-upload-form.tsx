import { Form } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useMemo, useState } from 'react';
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

    if (!selected) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Upload criterion evidence</CardTitle>
                <CardDescription>
                    Files are stored privately and count toward completeness
                    only after independent verification.
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
                                    Evidence requirement
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
                                    Title
                                </Label>
                                <Input
                                    id="criterion-evidence-title"
                                    name="title"
                                    required
                                    aria-invalid={Boolean(errors.title)}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="criterion-evidence-category">
                                    Category
                                </Label>
                                <select
                                    id="criterion-evidence-category"
                                    name="category"
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                    required
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
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="criterion-evidence-source">
                                    Source
                                </Label>
                                <select
                                    id="criterion-evidence-source"
                                    name="source_type"
                                    defaultValue="digital"
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    <option value="digital">
                                        Born digital
                                    </option>
                                    <option value="scanned">
                                        Scanned copy
                                    </option>
                                </select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="criterion-evidence-file">
                                    Document
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
                                />
                            </div>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="xl:col-start-6"
                            >
                                <Upload data-icon="inline-start" />
                                {progress
                                    ? `Uploading ${progress.percentage}%`
                                    : 'Upload evidence'}
                            </Button>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}
