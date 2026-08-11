import { Form } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/evidence';

export default function EvidenceUploadForm({
    teamSlug,
    assessments,
}: {
    teamSlug: string;
    assessments: Array<{ id: string; label: string }>;
}) {
    const [assessmentId, setAssessmentId] = useState(assessments[0]?.id ?? '');

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
                        Upload evidence
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Upload a scanned copy or a born-digital file. Files are
                        stored privately and linked to the selected assessment
                        cycle.
                    </p>
                </div>
            </div>
            <Form
                {...store.form({
                    current_team: teamSlug,
                    assessment: assessmentId,
                })}
                className="mt-5 grid gap-4 lg:grid-cols-[1.2fr_1fr_1fr_1fr_1.2fr_auto] lg:items-end"
                resetOnSuccess
            >
                {({ processing, errors, progress }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="assessment">Assessment</Label>
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
                            <Label htmlFor="evidence-title">Title</Label>
                            <Input
                                id="evidence-title"
                                name="title"
                                required
                                aria-invalid={!!errors.title}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="evidence-category">Category</Label>
                            <Input
                                id="evidence-category"
                                name="category"
                                placeholder="ADP, CIDP…"
                                required
                                aria-invalid={!!errors.category}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="evidence-source-type">
                                Source type
                            </Label>
                            <select
                                id="evidence-source-type"
                                name="source_type"
                                defaultValue="digital"
                                aria-invalid={!!errors.source_type}
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground"
                            >
                                <option value="digital">Digital file</option>
                                <option value="scanned">Scanned copy</option>
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="evidence-document">Document</Label>
                            <Input
                                id="evidence-document"
                                name="document"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png,.webp,.tif,.tiff,.doc,.docx,.xls,.xlsx,.csv,.txt"
                                required
                                aria-invalid={!!errors.document}
                            />
                        </div>
                        <Button type="submit" disabled={processing}>
                            <Upload aria-hidden="true" />
                            {progress
                                ? `Uploading ${progress.percentage}%`
                                : 'Upload'}
                        </Button>
                    </>
                )}
            </Form>
        </section>
    );
}
