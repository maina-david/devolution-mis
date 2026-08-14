import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Calculator,
    CheckCircle2,
    FileCheck2,
    Scale,
} from 'lucide-react';
import AssessmentCorrectivePlans from '@/components/assessment-corrective-plans';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
import CriterionEvidenceUploadForm from '@/components/criterion-evidence-upload-form';
import Questionnaire from '@/components/questionnaire';
import { Badge } from '@/components/ui/badge';
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
import { attest, calculate, index, publish } from '@/routes/assessments';
import { decide as decideAppeal } from '@/routes/assessments/appeals';
import {
    store as storeScore,
    verify,
} from '@/routes/assessments/criteria/scores';
import {
    respond as respondFinding,
    resolve as resolveFinding,
} from '@/routes/assessments/findings';

type Requirement = {
    id: string;
    code: string;
    name: string;
    minimumDocuments: number;
    verifiedDocuments: number;
    allowedCategories: string[];
    acceptedMimeTypes: string[];
};
type Criterion = {
    id: string;
    code: string;
    name: string;
    maximumScore: string;
    weight: string;
    submittedScore: string | null;
    verifiedScore: string | null;
    overrideScore: string | null;
    weightedScore: string | null;
    resultId: string | null;
    requirements: Requirement[];
};
type Props = {
    assessment: {
        id: string;
        county: CountyIdentityValue;
        cycle: {
            code: string;
            name: string;
            periodStart: string | null;
            periodEnd: string | null;
        };
        scorecard: {
            name: string;
            version: number;
            checksum: string | null;
        } | null;
        referenceRelease: {
            version: number;
            effectiveFrom: string | null;
            checksum: string;
        } | null;
        createdBy: string | null;
        status: string;
        score: string | null;
        completeness: string;
        attestationStatus: string;
        functions: Array<{
            id: string;
            code: string;
            name: string;
            weight: string;
            themes: Array<{
                id: string;
                code: string;
                name: string;
                standards: Array<{
                    id: string;
                    code: string;
                    name: string;
                    normReference: string | null;
                    criteria: Criterion[];
                }>;
            }>;
        }>;
        findings: Array<{
            id: string;
            code: string;
            severity: string;
            status: string;
            title: string;
            county_response?: string | null;
        }>;
        appeals: Array<{
            id: string;
            status: string;
            grounds: string;
            requested_remedy: string;
            decision?: string | null;
        }>;
        attestations: Array<{
            id: string;
            attestor_title: string;
            statement: string;
            content_checksum: string;
        }>;
        publication: {
            id: string;
            score: string;
            performanceBand: string;
            checksum: string;
            functionProfile: Array<{
                code: string;
                name: string;
                score: number;
            }>;
            publishedAt: string | null;
        } | null;
        rankings: Array<{
            publicationId: string;
            assessmentId: string;
            countyId: string;
            county: string;
            countyIdentity: CountyIdentityValue;
            score: string;
            performanceBand: string;
            rank: number;
            percentile: number;
        }>;
        correctivePlans: React.ComponentProps<
            typeof AssessmentCorrectivePlans
        >['plans'];
        correctiveOptions: React.ComponentProps<
            typeof AssessmentCorrectivePlans
        >['options'];
    };
    capabilities: Record<string, boolean>;
};

export default function AssessmentShow({ assessment, capabilities }: Props) {
    const copy = usePage().props.localization.assessmentRecord;
    const routeArguments = { assessment: assessment.id };
    const evidenceRequirements = assessment.functions.flatMap((fn) =>
        fn.themes.flatMap((theme) =>
            theme.standards.flatMap((standard) =>
                standard.criteria.flatMap((criterion) =>
                    criterion.requirements.map((requirement) => ({
                        id: requirement.id,
                        criterionId: criterion.id,
                        label: `${fn.code} · ${criterion.code} · ${requirement.name}`,
                        allowedCategories: requirement.allowedCategories,
                        acceptedMimeTypes: requirement.acceptedMimeTypes,
                    })),
                ),
            ),
        ),
    );

    return (
        <>
            <Head title={`${assessment.county.name} assessment`} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <Button variant="ghost" asChild className="self-start">
                    <Link href={index.url()}>
                        <ArrowLeft data-icon="inline-start" />
                        {copy.assessments}
                    </Link>
                </Button>
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div>
                            <p className="text-xs font-bold tracking-[0.16em] uppercase">
                                {copy.governed_county_assessment}
                            </p>
                            <CountyIdentity
                                county={assessment.county}
                                inverse
                                className="mt-4"
                            />
                            <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                {assessment.county.name} {copy.separator}{' '}
                                {assessment.cycle.name}
                            </h1>
                            <p className="mt-3 text-sm opacity-80">
                                {assessment.scorecard
                                    ? `${assessment.scorecard.name} v${assessment.scorecard.version}`
                                    : 'Legacy scorecard'}{' '}
                                {copy.separator}{' '}
                                {assessment.cycle.periodStart ?? '—'} {copy.to}{' '}
                                {assessment.cycle.periodEnd ?? '—'}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="secondary">
                                {assessment.status.replaceAll('_', ' ')}
                            </Badge>
                            <Badge variant="secondary">
                                {assessment.completeness}
                                {copy.percent_complete}
                            </Badge>
                            <Badge variant="secondary">
                                {assessment.attestationStatus}
                            </Badge>
                        </div>
                    </div>
                </section>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Card>
                        <CardHeader>
                            <CardDescription>
                                {copy.computed_score}
                            </CardDescription>
                            <CardTitle>
                                {assessment.score ?? 'Not calculated'}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>
                                {copy.reference_data_lineage}
                            </CardDescription>
                            <CardTitle className="text-base">
                                {assessment.referenceRelease
                                    ? `Release v${assessment.referenceRelease.version}`
                                    : 'Legacy unpinned'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-xs text-muted-foreground">
                            <p>
                                {copy.created_by}{' '}
                                {assessment.createdBy ?? 'Legacy unrecorded'}
                            </p>
                            <p
                                className="truncate font-mono"
                                title={
                                    assessment.referenceRelease?.checksum ??
                                    undefined
                                }
                            >
                                {assessment.referenceRelease?.checksum ??
                                    'Checksum unavailable'}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>
                                {copy.open_findings}
                            </CardDescription>
                            <CardTitle>
                                {
                                    assessment.findings.filter(
                                        (finding) =>
                                            finding.status !== 'resolved',
                                    ).length
                                }
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>{copy.appeals}</CardDescription>
                            <CardTitle>{assessment.appeals.length}</CardTitle>
                        </CardHeader>
                    </Card>
                </div>
                <div className="flex flex-wrap gap-3">
                    {capabilities.score && (
                        <Form {...calculate.form(routeArguments)}>
                            {({ processing }) => (
                                <Button disabled={processing}>
                                    <Calculator data-icon="inline-start" />
                                    {copy.calculate_verified_result}
                                </Button>
                            )}
                        </Form>
                    )}
                    {capabilities.approve &&
                        assessment.status === 'approved' &&
                        !assessment.publication && (
                            <Form {...publish.form(routeArguments)}>
                                {({ processing }) => (
                                    <Button disabled={processing}>
                                        <FileCheck2 data-icon="inline-start" />
                                        {copy.publish_immutable_result}
                                    </Button>
                                )}
                            </Form>
                        )}
                    {capabilities.submit &&
                        assessment.completeness === '100.00' &&
                        assessment.attestationStatus !== 'attested' && (
                            <Form
                                {...attest.form(routeArguments)}
                                className="flex flex-wrap items-end gap-2"
                            >
                                <div className="grid gap-1">
                                    <Label htmlFor="attestor-title">
                                        {copy.attestor_title}
                                    </Label>
                                    <Input
                                        id="attestor-title"
                                        name="attestor_title"
                                        required
                                    />
                                </div>
                                <div className="grid min-w-72 flex-1 gap-1">
                                    <Label htmlFor="attestation-statement">
                                        {copy.attestation_statement}
                                    </Label>
                                    <Input
                                        id="attestation-statement"
                                        name="statement"
                                        minLength={30}
                                        required
                                    />
                                </div>
                                <Button type="submit">
                                    <FileCheck2 data-icon="inline-start" />
                                    {copy.attest_submission}
                                </Button>
                            </Form>
                        )}
                </div>
                {capabilities.upload &&
                    [
                        'draft',
                        'evidence_collection',
                        'submitted',
                        'under_assessment',
                    ].includes(assessment.status) &&
                    evidenceRequirements.length > 0 && (
                        <CriterionEvidenceUploadForm
                            assessmentId={assessment.id}
                            requirements={evidenceRequirements}
                        />
                    )}
                <div className="flex flex-col gap-5">
                    {assessment.functions.map((fn) => (
                        <Card key={fn.id}>
                            <CardHeader>
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        <CardTitle>
                                            {fn.code} {copy.separator} {fn.name}
                                        </CardTitle>
                                        <CardDescription>
                                            {fn.weight}
                                            {copy.percent_total_score_weight}
                                        </CardDescription>
                                    </div>
                                    <Scale aria-hidden="true" />
                                </div>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-5">
                                {fn.themes.map((theme) => (
                                    <section
                                        key={theme.id}
                                        aria-labelledby={`theme-${theme.id}`}
                                        className="flex flex-col gap-3"
                                    >
                                        <h2
                                            id={`theme-${theme.id}`}
                                            className="font-semibold"
                                        >
                                            {theme.code} {copy.separator}{' '}
                                            {theme.name}
                                        </h2>
                                        {theme.standards.map((standard) => (
                                            <div
                                                key={standard.id}
                                                className="flex flex-col gap-3 rounded-lg border p-4"
                                            >
                                                <div>
                                                    <h3 className="font-medium">
                                                        {standard.code}{' '}
                                                        {copy.separator}{' '}
                                                        {standard.name}
                                                    </h3>
                                                    {standard.normReference && (
                                                        <p className="text-sm text-muted-foreground">
                                                            {
                                                                standard.normReference
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                                {standard.criteria.map(
                                                    (criterion) => (
                                                        <CriterionPanel
                                                            key={criterion.id}
                                                            criterion={
                                                                criterion
                                                            }
                                                            assessmentId={
                                                                assessment.id
                                                            }
                                                            capabilities={
                                                                capabilities
                                                            }
                                                        />
                                                    ),
                                                )}
                                            </div>
                                        ))}
                                    </section>
                                ))}
                            </CardContent>
                        </Card>
                    ))}
                </div>
                {(assessment.findings.length > 0 ||
                    assessment.appeals.length > 0 ||
                    assessment.attestations.length > 0) && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{copy.governance_record}</CardTitle>
                            <CardDescription>
                                {copy.governance_record_description}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 lg:grid-cols-3">
                            <div className="flex flex-col gap-2">
                                <h3 className="font-medium">
                                    {copy.attestations}
                                </h3>
                                {assessment.attestations.map((item) => (
                                    <p key={item.id} className="text-sm">
                                        <CheckCircle2
                                            className="mr-1 inline size-4"
                                            aria-hidden="true"
                                        />
                                        {item.attestor_title} {copy.separator}{' '}
                                        {item.content_checksum.slice(0, 12)}
                                    </p>
                                ))}
                            </div>
                            <div className="flex flex-col gap-2">
                                <h3 className="font-medium">{copy.findings}</h3>
                                {assessment.findings.map((item) => (
                                    <p key={item.id} className="text-sm">
                                        {item.code} {copy.separator}{' '}
                                        {item.title}{' '}
                                        <Badge variant="outline">
                                            {item.status}
                                        </Badge>
                                    </p>
                                ))}
                            </div>
                            <div className="flex flex-col gap-2">
                                <h3 className="font-medium">{copy.appeals}</h3>
                                {assessment.appeals.map((item) => (
                                    <p key={item.id} className="text-sm">
                                        {item.grounds}{' '}
                                        <Badge variant="outline">
                                            {item.status}
                                        </Badge>
                                    </p>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}
                <GovernanceActions
                    assessment={assessment}
                    capabilities={capabilities}
                />
                <AssessmentCorrectivePlans
                    assessmentId={assessment.id}
                    plans={assessment.correctivePlans}
                    options={assessment.correctiveOptions}
                    capabilities={capabilities}
                />
                {assessment.publication && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {copy.published_result_county_benchmark}
                            </CardTitle>
                            <CardDescription>
                                {copy.immutable_checksum}{' '}
                                {assessment.publication.checksum}{' '}
                                {copy.separator}{' '}
                                {assessment.publication.performanceBand}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            <div className="flex flex-wrap gap-2">
                                {assessment.publication.functionProfile.map(
                                    (item) => (
                                        <Badge
                                            key={item.code}
                                            variant="secondary"
                                        >
                                            {item.code} {item.name}
                                            {copy.colon} {item.score}
                                        </Badge>
                                    ),
                                )}
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left">
                                            <th className="p-2">{copy.rank}</th>
                                            <th className="p-2">
                                                {copy.county}
                                            </th>
                                            <th className="p-2">
                                                {copy.score}
                                            </th>
                                            <th className="p-2">{copy.band}</th>
                                            <th className="p-2">
                                                {copy.percentile}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {assessment.rankings.map((row) => (
                                            <tr
                                                key={row.publicationId}
                                                className="border-b"
                                            >
                                                <td className="p-2">
                                                    {row.rank}
                                                </td>
                                                <td className="p-2">
                                                    <CountyIdentity
                                                        county={
                                                            row.countyIdentity
                                                        }
                                                        compact
                                                    />
                                                </td>
                                                <td className="p-2">
                                                    {row.score}
                                                </td>
                                                <td className="p-2">
                                                    {row.performanceBand}
                                                </td>
                                                <td className="p-2">
                                                    {row.percentile}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

function GovernanceActions({
    assessment,
    capabilities,
}: {
    assessment: Props['assessment'];
    capabilities: Record<string, boolean>;
}) {
    const copy = usePage().props.localization.assessmentRecord;

    return (
        <div className="grid gap-4 lg:grid-cols-2">
            {assessment.findings
                .filter((item) => item.status !== 'resolved')
                .map((finding) => (
                    <Card key={finding.id}>
                        <CardHeader>
                            <CardTitle>
                                {finding.code} {copy.response}
                            </CardTitle>
                            <CardDescription>
                                {finding.title} {copy.separator}{' '}
                                {finding.severity}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {capabilities.submit && (
                                <Form
                                    {...respondFinding.form({
                                        assessment: assessment.id,
                                        finding: finding.id,
                                    })}
                                    resetOnSuccess
                                    className="flex gap-2"
                                >
                                    <Input
                                        name="response"
                                        minLength={20}
                                        aria-label={`County response to ${finding.code}`}
                                        placeholder="Evidence-backed county response"
                                        required
                                    />
                                    <Button type="submit">
                                        {copy.respond}
                                    </Button>
                                </Form>
                            )}
                            {capabilities.review && finding.county_response && (
                                <Form
                                    {...resolveFinding.form({
                                        assessment: assessment.id,
                                        finding: finding.id,
                                    })}
                                    resetOnSuccess
                                    className="flex gap-2"
                                >
                                    <Input
                                        name="resolution"
                                        minLength={20}
                                        aria-label={`Resolution for ${finding.code}`}
                                        placeholder="Verification resolution"
                                        required
                                    />
                                    <Button type="submit" variant="outline">
                                        {copy.resolve}
                                    </Button>
                                </Form>
                            )}
                        </CardContent>
                    </Card>
                ))}
            {assessment.appeals
                .filter((item) =>
                    ['submitted', 'under_review'].includes(item.status),
                )
                .map(
                    (appeal) =>
                        capabilities.approve && (
                            <Card key={appeal.id}>
                                <CardHeader>
                                    <CardTitle>
                                        {copy.appeal_adjudication}
                                    </CardTitle>
                                    <CardDescription>
                                        {appeal.grounds}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Form
                                        {...decideAppeal.form({
                                            assessment: assessment.id,
                                            appeal: appeal.id,
                                        })}
                                        resetOnSuccess
                                        className="grid gap-3"
                                    >
                                        <Label
                                            htmlFor={`appeal-status-${appeal.id}`}
                                        >
                                            {copy.decision}
                                        </Label>
                                        <select
                                            id={`appeal-status-${appeal.id}`}
                                            name="status"
                                            className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                            required
                                        >
                                            <option value="rejected">
                                                {copy.reject}
                                            </option>
                                            <option value="upheld">
                                                {copy.uphold}
                                            </option>
                                            <option value="partially_upheld">
                                                {copy.partially_uphold}
                                            </option>
                                        </select>
                                        <Input
                                            name="decision"
                                            minLength={30}
                                            aria-label="Appeal decision rationale"
                                            placeholder="Documented adjudication rationale"
                                            required
                                        />
                                        <Button type="submit">
                                            {copy.record_decision}
                                        </Button>
                                    </Form>
                                </CardContent>
                            </Card>
                        ),
                )}
        </div>
    );
}

function CriterionPanel({
    criterion,
    assessmentId,
    capabilities,
}: {
    criterion: Criterion;
    assessmentId: string;
    capabilities: Record<string, boolean>;
}) {
    const copy = usePage().props.localization.assessmentRecord;
    const args = { assessment: assessmentId, criterion: criterion.id };
    const evidenceComplete = criterion.requirements.every(
        (requirement) =>
            requirement.verifiedDocuments >= requirement.minimumDocuments,
    );

    return (
        <article className="grid gap-4 rounded-md bg-muted/40 p-4 xl:grid-cols-[1fr_22rem]">
            <div className="flex flex-col gap-3">
                <div>
                    <p className="font-medium">
                        {criterion.code} {copy.separator} {criterion.name}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {copy.weight} {criterion.weight}
                        {copy.percent} {copy.separator} {copy.maximum}{' '}
                        {criterion.maximumScore}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {criterion.requirements.map((requirement) => (
                        <Badge
                            key={requirement.id}
                            variant={
                                requirement.verifiedDocuments >=
                                requirement.minimumDocuments
                                    ? 'secondary'
                                    : 'outline'
                            }
                        >
                            {requirement.code}
                            {copy.colon} {requirement.verifiedDocuments}
                            {copy.slash}
                            {requirement.minimumDocuments} {copy.verified}
                        </Badge>
                    ))}
                </div>
                <p className="text-sm">
                    {copy.submitted} {criterion.submittedScore ?? '—'}{' '}
                    {copy.separator} {copy.verified}{' '}
                    {criterion.verifiedScore ?? '—'} {copy.separator}{' '}
                    {copy.override} {criterion.overrideScore ?? '—'}{' '}
                    {copy.separator} {copy.weighted}{' '}
                    {criterion.weightedScore ?? '—'}
                </p>
            </div>
            <div className="flex flex-col gap-3">
                {capabilities.score && (
                    <Form
                        {...storeScore.form(args)}
                        className="flex flex-col gap-3"
                    >
                        {({ processing }) => (
                            <>
                                <Questionnaire
                                    storageKey={[
                                        'assessment',
                                        assessmentId,
                                        'criterion',
                                        criterion.id,
                                        'score',
                                    ].join(':')}
                                    questions={[
                                        {
                                            id: [
                                                'criterion-score',
                                                criterion.id,
                                            ].join('-'),
                                            name: 'score',
                                            label: interpolate(copy.score_for, {
                                                criterion: criterion.name,
                                            }),
                                            type: 'number',
                                            min: 0,
                                            max: Number(criterion.maximumScore),
                                            step: 0.01,
                                            required: true,
                                        },
                                        {
                                            id: [
                                                'criterion-rationale',
                                                criterion.id,
                                            ].join('-'),
                                            name: 'rationale',
                                            label: interpolate(
                                                copy.scoring_rationale_for,
                                                { criterion: criterion.name },
                                            ),
                                            type: 'textarea',
                                            minLength: 20,
                                            placeholder:
                                                copy.evidence_based_rationale,
                                            required: true,
                                        },
                                    ]}
                                    evidenceComplete={evidenceComplete}
                                    progressLabel={(complete, total) =>
                                        interpolate(
                                            copy.questionnaire_progress,
                                            { complete, total },
                                        )
                                    }
                                    autosavedLabel={copy.draft_autosaved}
                                    evidenceReadyLabel={
                                        copy.mandatory_evidence_ready
                                    }
                                    evidenceRequiredLabel={
                                        copy.mandatory_evidence_required
                                    }
                                />
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={processing}
                                >
                                    {copy.submit}
                                </Button>
                            </>
                        )}
                    </Form>
                )}
                {capabilities.review && criterion.resultId && (
                    <Form
                        {...verify.form({
                            assessment: assessmentId,
                            result: criterion.resultId,
                        })}
                        className="flex flex-col gap-3"
                    >
                        {({ processing }) => (
                            <>
                                <Questionnaire
                                    storageKey={[
                                        'assessment',
                                        assessmentId,
                                        'criterion',
                                        criterion.id,
                                        'verification',
                                    ].join(':')}
                                    questions={[
                                        {
                                            id: [
                                                'criterion-verified-score',
                                                criterion.id,
                                            ].join('-'),
                                            name: 'score',
                                            label: interpolate(
                                                copy.verified_score_for,
                                                { criterion: criterion.name },
                                            ),
                                            type: 'number',
                                            min: 0,
                                            max: Number(criterion.maximumScore),
                                            step: 0.01,
                                            required: true,
                                        },
                                        {
                                            id: [
                                                'criterion-verification-rationale',
                                                criterion.id,
                                            ].join('-'),
                                            name: 'rationale',
                                            label: interpolate(
                                                copy.verification_rationale_for,
                                                { criterion: criterion.name },
                                            ),
                                            type: 'textarea',
                                            minLength: 20,
                                            placeholder:
                                                copy.independent_verification_rationale,
                                            required: true,
                                        },
                                    ]}
                                    evidenceComplete={evidenceComplete}
                                    progressLabel={(complete, total) =>
                                        interpolate(
                                            copy.questionnaire_progress,
                                            { complete, total },
                                        )
                                    }
                                    autosavedLabel={copy.draft_autosaved}
                                    evidenceReadyLabel={
                                        copy.mandatory_evidence_ready
                                    }
                                    evidenceRequiredLabel={
                                        copy.mandatory_evidence_required
                                    }
                                />
                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="outline"
                                    disabled={processing}
                                >
                                    {copy.verify}
                                </Button>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </article>
    );
}
