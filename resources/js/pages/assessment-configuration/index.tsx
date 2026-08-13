import { Form, Head, router, usePage } from '@inertiajs/react';
import { ClipboardList, FileJson2, Gauge, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import DatePickerField from '@/components/date-picker-field';
import FormSheet from '@/components/form-sheet';
import SearchableSelect from '@/components/searchable-select';
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
import { index } from '@/routes/assessment-configuration';
import { store as storeCycle } from '@/routes/assessment-configuration/cycles';
import { store as storeScorecard } from '@/routes/assessment-configuration/scorecards';
import {
    publish,
    store as storeVersion,
} from '@/routes/assessment-configuration/scorecards/versions';

type PageData<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Version = {
    id: string;
    version: number;
    status: string;
    calculationMethod: string;
    functionCount: number;
    checksum: string | null;
    publishedAt: string | null;
};

type Props = {
    filters: { search?: string };
    scorecards: PageData<{
        id: string;
        code: string;
        name: string;
        description: string | null;
        status: string;
        versions: Version[];
    }>;
    cycles: PageData<{
        id: string;
        code: string;
        name: string;
        scorecard: string | null;
        periodStart: string;
        periodEnd: string;
        status: string;
    }>;
    publishedVersions: Array<{ id: string; label: string }>;
};

const devolvedFunctions = [
    'Agriculture',
    'County health services',
    'Pollution and public nuisance control',
    'Cultural activities and public amenities',
    'County transport',
    'Animal control and welfare',
    'Trade development and regulation',
    'County planning and development',
    'Pre-primary education and vocational training',
    'Natural resources and environmental conservation',
    'County public works and services',
    'Firefighting and disaster management',
    'Control of drugs and pornography',
    'Community participation in county governance',
];

function useAssessmentConfigurationCopy(): Record<string, string> {
    return usePage().props.localization.assessmentConfiguration;
}

function baselineConfiguration() {
    return {
        change_notes: 'Initial fourteen-function digital scorecard baseline.',
        calculation_method: 'mcda',
        mcda_configuration: {
            normalization: 'percentage',
            aggregation: 'weighted_sum',
            missing_data: 'incomplete',
        },
        performance_thresholds: [
            {
                label: 'Exceeds standard',
                minimum: 85,
                maximum: 100,
                color: 'green',
            },
            {
                label: 'Meets standard',
                minimum: 70,
                maximum: 84.9999,
                color: 'blue',
            },
            {
                label: 'Needs improvement',
                minimum: 0,
                maximum: 69.9999,
                color: 'amber',
            },
        ],
        functions: devolvedFunctions.map((name, index) => ({
            code: `F${String(index + 1).padStart(2, '0')}`,
            name,
            description: `Capacity, productivity and service-delivery assessment for ${name.toLowerCase()}.`,
            function_type: 'devolved',
            weight: index === devolvedFunctions.length - 1 ? 7.1423 : 7.1429,
            sequence: index + 1,
            thematic_areas: [
                {
                    code: `F${String(index + 1).padStart(2, '0')}-EN`,
                    name: 'Institutional capacity and service delivery',
                    description:
                        'Governance, capacity, productivity and results enablers.',
                    weight: 100,
                    sequence: 1,
                    standards: [
                        {
                            code: `F${String(index + 1).padStart(2, '0')}-S01`,
                            name: 'Approved sector standard and service-delivery norm',
                            norm_reference:
                                'Applicable national and county sector standard; owner approval required before publication.',
                            description:
                                'Measures conformity, implementation and evidenced results.',
                            weight: 100,
                            sequence: 1,
                            criteria: [
                                {
                                    code: `F${String(index + 1).padStart(2, '0')}-C01`,
                                    name: 'Documented compliance and demonstrated service-delivery result',
                                    description:
                                        'Evidence must demonstrate both institutional compliance and an attributable result.',
                                    weight: 100,
                                    maximum_score: 100,
                                    scoring_method: 'scale',
                                    formula: {
                                        type: 'linear',
                                        minimum: 0,
                                        maximum: 100,
                                    },
                                    thresholds: [
                                        {
                                            label: 'Meets standard',
                                            minimum: 70,
                                        },
                                    ],
                                    is_mandatory: true,
                                    sequence: 1,
                                    evidence_requirements: [
                                        {
                                            code: `F${String(index + 1).padStart(2, '0')}-E01`,
                                            name: 'Approved compliance and results evidence',
                                            description:
                                                'Signed policy, plan, report, register, audit evidence or equivalent primary record.',
                                            minimum_documents: 1,
                                            allowed_categories: [
                                                'policy',
                                                'plan',
                                                'report',
                                                'audit',
                                                'register',
                                            ],
                                            accepted_mime_types: [
                                                'application/pdf',
                                                'image/jpeg',
                                                'image/png',
                                            ],
                                            requires_verification: true,
                                            is_mandatory: true,
                                        },
                                    ],
                                },
                            ],
                        },
                    ],
                },
            ],
        })),
    };
}

function ScorecardForm() {
    const copy = useAssessmentConfigurationCopy();

    return (
        <FormSheet
            title={copy.new_scorecard}
            description={copy.new_scorecard_description}
            triggerLabel={copy.create_scorecard}
            icon={ClipboardList}
        >
            <Form
                {...storeScorecard.form()}
                resetOnSuccess
                className="grid gap-4 pt-4 sm:grid-cols-2"
            >
                {({ processing }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="scorecard-code">{copy.code}</Label>
                            <Input id="scorecard-code" name="code" required />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="scorecard-name">{copy.name}</Label>
                            <Input id="scorecard-name" name="name" required />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="scorecard-description">
                                {copy.description_label}
                            </Label>
                            <Input
                                id="scorecard-description"
                                name="description"
                            />
                        </div>
                        <input type="hidden" name="status" value="active" />
                        <Button
                            type="submit"
                            disabled={processing}
                            className="sm:col-span-2"
                        >
                            {copy.create_scorecard}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function CycleForm({ versions }: { versions: Props['publishedVersions'] }) {
    const copy = useAssessmentConfigurationCopy();

    return (
        <FormSheet
            title={copy.new_cycle}
            description={copy.new_cycle_description}
            triggerLabel={copy.create_cycle}
            icon={Gauge}
            size="xl"
            triggerDisabled={versions.length === 0}
            triggerTitle={
                versions.length === 0 ? copy.publish_before_cycle : undefined
            }
        >
            <Form
                {...storeCycle.form()}
                resetOnSuccess
                className="grid gap-4 pt-4 sm:grid-cols-2"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="cycle-code">{copy.code}</Label>
                            <Input id="cycle-code" name="code" required />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="cycle-name">{copy.name}</Label>
                            <Input id="cycle-name" name="name" required />
                        </div>
                        <div className="sm:col-span-2">
                            <SearchableSelect
                                id="cycle-scorecard"
                                name="assessment_scorecard_version_id"
                                label={copy.released_scorecard}
                                options={versions.map((version) => ({
                                    id: version.id,
                                    name: version.label,
                                }))}
                                error={errors.assessment_scorecard_version_id}
                            />
                        </div>
                        <DatePickerField
                            name="period_start"
                            label={copy.period_start}
                            error={errors.period_start}
                            required
                        />
                        <DatePickerField
                            name="period_end"
                            label={copy.period_end}
                            error={errors.period_end}
                            required
                        />
                        <DatePickerField
                            name="submission_opens_at"
                            label={copy.submission_opens}
                            error={errors.submission_opens_at}
                            includeTime
                        />
                        <DatePickerField
                            name="submission_closes_at"
                            label={copy.submission_closes}
                            error={errors.submission_closes_at}
                            includeTime
                        />
                        <input type="hidden" name="status" value="planned" />
                        <Button
                            type="submit"
                            disabled={processing}
                            className="sm:col-span-2"
                        >
                            {copy.create_cycle}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function VersionComposer({ scorecardId }: { scorecardId: string }) {
    const copy = useAssessmentConfigurationCopy();
    const [configuration, setConfiguration] = useState(() =>
        JSON.stringify(baselineConfiguration(), null, 2),
    );
    const [error, setError] = useState<string | null>(null);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        try {
            const payload = JSON.parse(configuration);
            setError(null);
            router.post(storeVersion.url([scorecardId]), payload, {
                preserveScroll: true,
            });
        } catch {
            setError(copy.valid_json_error);
        }
    }

    return (
        <FormSheet
            title={copy.compose_version_title}
            description={copy.compose_version_description}
            triggerLabel={copy.compose_version}
            icon={FileJson2}
            size="xl"
        >
            <form onSubmit={submit} className="space-y-3 pt-4">
                <p className="text-xs leading-5 text-muted-foreground">
                    {copy.baseline_notice}
                </p>
                <textarea
                    value={configuration}
                    onChange={(event) => setConfiguration(event.target.value)}
                    className="min-h-96 w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs"
                    aria-label={copy.scorecard_json}
                />
                {error && <p className="text-sm text-destructive">{error}</p>}
                <Button type="submit" size="sm">
                    <FileJson2 data-icon="inline-start" />{' '}
                    {copy.save_draft_version}
                </Button>
            </form>
        </FormSheet>
    );
}

function Pagination({
    page,
    label,
}: {
    page: PageData<unknown>;
    label: string;
}) {
    const copy = useAssessmentConfigurationCopy();

    if (page.last_page <= 1) {
        return null;
    }

    return (
        <nav
            className="flex items-center justify-between gap-3"
            aria-label={`${label} ${copy.pagination}`}
        >
            <p className="text-sm text-muted-foreground">
                {copy.page} {page.current_page} {copy.of} {page.last_page}{' '}
                {copy.separator} {page.total} {copy.total}
            </p>
            <div className="flex gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={!page.prev_page_url}
                    onClick={() =>
                        page.prev_page_url &&
                        router.visit(page.prev_page_url, {
                            preserveScroll: true,
                            preserveState: true,
                        })
                    }
                >
                    {copy.previous}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={!page.next_page_url}
                    onClick={() =>
                        page.next_page_url &&
                        router.visit(page.next_page_url, {
                            preserveScroll: true,
                            preserveState: true,
                        })
                    }
                >
                    {copy.next}
                </Button>
            </div>
        </nav>
    );
}

export default function AssessmentConfiguration({
    filters,
    scorecards,
    cycles,
    publishedVersions,
}: Props) {
    const copy = useAssessmentConfigurationCopy();

    function search(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        router.get(
            index.url(),
            { search: data.get('search')?.toString() ?? '' },
            { preserveState: true },
        );
    }

    return (
        <>
            <Head title={copy.head_title} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                        {copy.eyebrow}
                    </p>
                    <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                        {copy.title}
                    </h1>
                    <p className="mt-3 max-w-3xl text-sm leading-6 text-[#c7d6dd] sm:text-base">
                        {copy.description}
                    </p>
                </section>

                <div className="flex flex-wrap gap-3">
                    <ScorecardForm />
                    <CycleForm versions={publishedVersions} />
                </div>

                <form onSubmit={search} className="flex max-w-xl gap-2">
                    <Input
                        name="search"
                        defaultValue={filters.search}
                        placeholder={copy.search_scorecards}
                    />
                    <Button type="submit" variant="outline">
                        <Search data-icon="inline-start" /> {copy.search}
                    </Button>
                </form>

                <div className="space-y-4">
                    {scorecards.data.map((scorecard) => (
                        <Card key={scorecard.id}>
                            <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <ClipboardList aria-hidden="true" />{' '}
                                        {scorecard.name}
                                    </CardTitle>
                                    <CardDescription>
                                        {scorecard.code} {copy.separator}{' '}
                                        {scorecard.description ??
                                            copy.no_description}
                                    </CardDescription>
                                </div>
                                <Badge>
                                    {translateValue(copy, scorecard.status)}
                                </Badge>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-wrap gap-2">
                                    {scorecard.versions.map((version) => (
                                        <span
                                            key={version.id}
                                            className="inline-flex items-center gap-2"
                                        >
                                            <Badge
                                                variant={
                                                    version.status ===
                                                    'published'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {copy.version_prefix}
                                                {version.version}{' '}
                                                {copy.separator}{' '}
                                                {version.functionCount}{' '}
                                                {copy.functions}{' '}
                                                {copy.separator}{' '}
                                                {translateValue(
                                                    copy,
                                                    version.status,
                                                )}
                                                {version.checksum
                                                    ? ` · ${version.checksum.slice(0, 8)}`
                                                    : ''}
                                            </Badge>
                                            {version.status === 'draft' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.patch(
                                                            publish.url([
                                                                scorecard.id,
                                                                version.id,
                                                            ]),
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <Gauge data-icon="inline-start" />{' '}
                                                    {copy.publish}
                                                </Button>
                                            )}
                                        </span>
                                    ))}
                                </div>
                                {!scorecard.versions.some(
                                    (version) => version.status === 'draft',
                                ) && (
                                    <VersionComposer
                                        scorecardId={scorecard.id}
                                    />
                                )}
                            </CardContent>
                        </Card>
                    ))}
                    <Pagination page={scorecards} label={copy.scorecards} />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{copy.assessment_cycles}</CardTitle>
                        <CardDescription>
                            {copy.assessment_cycles_description}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {cycles.data.map((cycle) => (
                            <div
                                key={cycle.id}
                                className="flex flex-col justify-between gap-2 rounded-lg border p-3 sm:flex-row sm:items-center"
                            >
                                <div>
                                    <p className="font-semibold">
                                        {cycle.name}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {cycle.code} {copy.separator}{' '}
                                        {cycle.periodStart} {copy.to}{' '}
                                        {cycle.periodEnd} {copy.separator}{' '}
                                        {cycle.scorecard ?? copy.no_scorecard}
                                    </p>
                                </div>
                                <Badge variant="outline">
                                    {translateValue(copy, cycle.status)}
                                </Badge>
                            </div>
                        ))}
                        <Pagination
                            page={cycles}
                            label={copy.assessment_cycles}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function translateValue(copy: Record<string, string>, value: string): string {
    return copy[`value_${value}`] ?? value;
}
