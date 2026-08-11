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
    const teamSlug = usePage().props.currentTeam!.slug;
    const routeArguments = {
        current_team: teamSlug,
        assessment: assessment.id,
    };
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
                    <Link href={index.url(teamSlug)}>
                        <ArrowLeft data-icon="inline-start" />
                        Assessments
                    </Link>
                </Button>
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div>
                            <p className="text-xs font-bold tracking-[0.16em] uppercase">
                                Governed county assessment
                            </p>
                            <CountyIdentity
                                county={assessment.county}
                                inverse
                                className="mt-4"
                            />
                            <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                {assessment.county.name} ·{' '}
                                {assessment.cycle.name}
                            </h1>
                            <p className="mt-3 text-sm opacity-80">
                                {assessment.scorecard
                                    ? `${assessment.scorecard.name} v${assessment.scorecard.version}`
                                    : 'Legacy scorecard'}{' '}
                                · {assessment.cycle.periodStart ?? '—'} to{' '}
                                {assessment.cycle.periodEnd ?? '—'}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="secondary">
                                {assessment.status.replaceAll('_', ' ')}
                            </Badge>
                            <Badge variant="secondary">
                                {assessment.completeness}% complete
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
                            <CardDescription>Computed score</CardDescription>
                            <CardTitle>
                                {assessment.score ?? 'Not calculated'}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>
                                Reference-data lineage
                            </CardDescription>
                            <CardTitle className="text-base">
                                {assessment.referenceRelease
                                    ? `Release v${assessment.referenceRelease.version}`
                                    : 'Legacy unpinned'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-xs text-muted-foreground">
                            <p>
                                Created by{' '}
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
                            <CardDescription>Open findings</CardDescription>
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
                            <CardDescription>Appeals</CardDescription>
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
                                    Calculate verified result
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
                                        Publish immutable result
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
                                        Attestor title
                                    </Label>
                                    <Input
                                        id="attestor-title"
                                        name="attestor_title"
                                        required
                                    />
                                </div>
                                <div className="grid min-w-72 flex-1 gap-1">
                                    <Label htmlFor="attestation-statement">
                                        Attestation statement
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
                                    Attest submission
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
                            teamSlug={teamSlug}
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
                                            {fn.code} · {fn.name}
                                        </CardTitle>
                                        <CardDescription>
                                            {fn.weight}% total score weight
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
                                            {theme.code} · {theme.name}
                                        </h2>
                                        {theme.standards.map((standard) => (
                                            <div
                                                key={standard.id}
                                                className="flex flex-col gap-3 rounded-lg border p-4"
                                            >
                                                <div>
                                                    <h3 className="font-medium">
                                                        {standard.code} ·{' '}
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
                                                            teamSlug={teamSlug}
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
                            <CardTitle>Governance record</CardTitle>
                            <CardDescription>
                                Attestations, findings and appeals retained with
                                the assessment.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 lg:grid-cols-3">
                            <div className="flex flex-col gap-2">
                                <h3 className="font-medium">Attestations</h3>
                                {assessment.attestations.map((item) => (
                                    <p key={item.id} className="text-sm">
                                        <CheckCircle2
                                            className="mr-1 inline size-4"
                                            aria-hidden="true"
                                        />
                                        {item.attestor_title} ·{' '}
                                        {item.content_checksum.slice(0, 12)}
                                    </p>
                                ))}
                            </div>
                            <div className="flex flex-col gap-2">
                                <h3 className="font-medium">Findings</h3>
                                {assessment.findings.map((item) => (
                                    <p key={item.id} className="text-sm">
                                        {item.code} · {item.title}{' '}
                                        <Badge variant="outline">
                                            {item.status}
                                        </Badge>
                                    </p>
                                ))}
                            </div>
                            <div className="flex flex-col gap-2">
                                <h3 className="font-medium">Appeals</h3>
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
                    teamSlug={teamSlug}
                />
                <AssessmentCorrectivePlans
                    teamSlug={teamSlug}
                    assessmentId={assessment.id}
                    plans={assessment.correctivePlans}
                    options={assessment.correctiveOptions}
                    capabilities={capabilities}
                />
                {assessment.publication && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                Published result and county benchmark
                            </CardTitle>
                            <CardDescription>
                                Immutable checksum{' '}
                                {assessment.publication.checksum} ·{' '}
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
                                            {item.code} {item.name}:{' '}
                                            {item.score}
                                        </Badge>
                                    ),
                                )}
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left">
                                            <th className="p-2">Rank</th>
                                            <th className="p-2">County</th>
                                            <th className="p-2">Score</th>
                                            <th className="p-2">Band</th>
                                            <th className="p-2">Percentile</th>
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
    teamSlug,
}: {
    assessment: Props['assessment'];
    capabilities: Record<string, boolean>;
    teamSlug: string;
}) {
    return (
        <div className="grid gap-4 lg:grid-cols-2">
            {assessment.findings
                .filter((item) => item.status !== 'resolved')
                .map((finding) => (
                    <Card key={finding.id}>
                        <CardHeader>
                            <CardTitle>{finding.code} response</CardTitle>
                            <CardDescription>
                                {finding.title} · {finding.severity}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {capabilities.submit && (
                                <Form
                                    {...respondFinding.form({
                                        current_team: teamSlug,
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
                                    <Button type="submit">Respond</Button>
                                </Form>
                            )}
                            {capabilities.review && finding.county_response && (
                                <Form
                                    {...resolveFinding.form({
                                        current_team: teamSlug,
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
                                        Resolve
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
                                    <CardTitle>Appeal adjudication</CardTitle>
                                    <CardDescription>
                                        {appeal.grounds}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Form
                                        {...decideAppeal.form({
                                            current_team: teamSlug,
                                            assessment: assessment.id,
                                            appeal: appeal.id,
                                        })}
                                        resetOnSuccess
                                        className="grid gap-3"
                                    >
                                        <Label
                                            htmlFor={`appeal-status-${appeal.id}`}
                                        >
                                            Decision
                                        </Label>
                                        <select
                                            id={`appeal-status-${appeal.id}`}
                                            name="status"
                                            className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                            required
                                        >
                                            <option value="rejected">
                                                Reject
                                            </option>
                                            <option value="upheld">
                                                Uphold
                                            </option>
                                            <option value="partially_upheld">
                                                Partially uphold
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
                                            Record decision
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
    teamSlug,
    capabilities,
}: {
    criterion: Criterion;
    assessmentId: string;
    teamSlug: string;
    capabilities: Record<string, boolean>;
}) {
    const args = {
        current_team: teamSlug,
        assessment: assessmentId,
        criterion: criterion.id,
    };

    return (
        <article className="grid gap-4 rounded-md bg-muted/40 p-4 xl:grid-cols-[1fr_22rem]">
            <div className="flex flex-col gap-3">
                <div>
                    <p className="font-medium">
                        {criterion.code} · {criterion.name}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Weight {criterion.weight}% · maximum{' '}
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
                            {requirement.code}: {requirement.verifiedDocuments}/
                            {requirement.minimumDocuments} verified
                        </Badge>
                    ))}
                </div>
                <p className="text-sm">
                    Submitted {criterion.submittedScore ?? '—'} · verified{' '}
                    {criterion.verifiedScore ?? '—'} · override{' '}
                    {criterion.overrideScore ?? '—'} · weighted{' '}
                    {criterion.weightedScore ?? '—'}
                </p>
            </div>
            <div className="flex flex-col gap-3">
                {capabilities.score && (
                    <Form
                        {...storeScore.form(args)}
                        className="grid grid-cols-[6rem_1fr_auto] gap-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Input
                                    name="score"
                                    type="number"
                                    min="0"
                                    max={criterion.maximumScore}
                                    step="0.01"
                                    aria-label={`Score for ${criterion.name}`}
                                    aria-invalid={Boolean(errors.score)}
                                    required
                                />
                                <Input
                                    name="rationale"
                                    minLength={20}
                                    aria-label={`Scoring rationale for ${criterion.name}`}
                                    placeholder="Evidence-based rationale"
                                    required
                                />
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={processing}
                                >
                                    Submit
                                </Button>
                            </>
                        )}
                    </Form>
                )}
                {capabilities.review && criterion.resultId && (
                    <Form
                        {...verify.form({
                            current_team: teamSlug,
                            assessment: assessmentId,
                            result: criterion.resultId,
                        })}
                        className="grid grid-cols-[6rem_1fr_auto] gap-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Input
                                    name="score"
                                    type="number"
                                    min="0"
                                    max={criterion.maximumScore}
                                    step="0.01"
                                    aria-label={`Verified score for ${criterion.name}`}
                                    aria-invalid={Boolean(errors.score)}
                                    required
                                />
                                <Input
                                    name="rationale"
                                    minLength={20}
                                    aria-label={`Verification rationale for ${criterion.name}`}
                                    placeholder="Independent verification rationale"
                                    required
                                />
                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="outline"
                                    disabled={processing}
                                >
                                    Verify
                                </Button>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </article>
    );
}
