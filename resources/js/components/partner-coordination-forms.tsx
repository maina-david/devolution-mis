import { Form } from '@inertiajs/react';
import { Handshake, Landmark, WalletCards } from 'lucide-react';
import {
    storeAgreement,
    storeContribution,
    storeProfile,
} from '@/actions/App/Http/Controllers/PartnerCoordinationController';
import DatePickerField from '@/components/date-picker-field';
import FormSheet from '@/components/form-sheet';
import ReferenceCatalogSelect from '@/components/reference-catalog-select';
import SearchableMultiSelect from '@/components/searchable-multi-select';
import SearchableSelect from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Option = {
    id: string;
    name: string;
    code?: string;
    email?: string;
    title?: string;
};
type Props = {
    teamSlug: string;
    capabilities: { manage: boolean; submitData: boolean };
    organizations: Option[];
    counties: Option[];
    sectors: Option[];
    users: Option[];
    partners: Option[];
    projects: Option[];
};

export default function PartnerCoordinationForms(props: Props) {
    return (
        <div className="grid gap-5 xl:grid-cols-3">
            {props.capabilities.manage && <ProfileForm {...props} />}
            {props.capabilities.manage && <AgreementForm {...props} />}
            {props.capabilities.submitData && <ContributionForm {...props} />}
        </div>
    );
}

function ProfileForm({
    teamSlug,
    organizations,
    counties,
    sectors,
    users,
}: Props) {
    return (
        <FormSheet
            title="Register partner"
            triggerLabel="Register partner"
            icon={Handshake}
            description="Define the organization, authorised representatives, counties, sectors and delivery modalities."
        >
            <Form
                action={storeProfile(teamSlug)}
                className="grid gap-4"
                resetOnSuccess
            >
                {({ errors, processing }) => (
                    <>
                        <Field
                            label="Organization"
                            error={errors.organization_id}
                        >
                            <Select
                                name="organization_id"
                                options={organizations}
                            />
                        </Field>
                        <Field label="Partner type" error={errors.partner_type}>
                            <StaticSelect
                                name="partner_type"
                                values={[
                                    'bilateral',
                                    'multilateral',
                                    'foundation',
                                    'ngo',
                                    'private_sector',
                                    'government_agency',
                                    'other',
                                ]}
                            />
                        </Field>
                        <ReferenceCatalogSelect
                            id="partner-country"
                            name="country"
                            label="Country"
                            catalog="country-name"
                            error={errors.country}
                            optional
                        />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label="Focal point"
                                error={errors.focal_point_name}
                            >
                                <Input name="focal_point_name" required />
                            </Field>
                            <Field
                                label="Focal email"
                                error={errors.focal_point_email}
                            >
                                <Input
                                    name="focal_point_email"
                                    type="email"
                                    required
                                />
                            </Field>
                        </div>
                        <Field label="Website" error={errors.website}>
                            <Input
                                name="website"
                                type="url"
                                placeholder="https://"
                            />
                        </Field>
                        <Field
                            label="County portfolio"
                            error={errors.county_ids}
                        >
                            <MultiSelect
                                name="county_ids[]"
                                options={counties}
                            />
                        </Field>
                        <Field
                            label="Sector portfolio"
                            error={errors.sector_ids}
                        >
                            <MultiSelect
                                name="sector_ids[]"
                                options={sectors}
                            />
                        </Field>
                        <Field
                            label="Authorised partner users"
                            error={errors.user_ids}
                        >
                            <MultiSelect
                                name="user_ids[]"
                                options={users}
                                optional
                            />
                        </Field>
                        <Field
                            label="Strategic priorities"
                            error={errors.strategic_priorities}
                        >
                            <textarea
                                name="strategic_priorities"
                                className={textareaClass}
                            />
                        </Field>
                        <Button
                            type="submit"
                            disabled={processing || organizations.length === 0}
                        >
                            Create partner profile
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function AgreementForm({ teamSlug, partners }: Props) {
    return (
        <FormSheet
            title="Register agreement"
            triggerLabel="Register agreement"
            icon={Landmark}
            description="Catalogue MoUs, financing instruments and cooperation frameworks."
        >
            <Form
                action={storeAgreement(teamSlug)}
                className="grid gap-4"
                resetOnSuccess
            >
                {({ errors, processing }) => (
                    <>
                        <Field
                            label="Partner"
                            error={errors.partner_profile_id}
                        >
                            <Select
                                name="partner_profile_id"
                                options={partners}
                            />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Reference" error={errors.reference}>
                                <Input name="reference" required />
                            </Field>
                            <Field
                                label="Agreement type"
                                error={errors.agreement_type}
                            >
                                <StaticSelect
                                    name="agreement_type"
                                    values={[
                                        'mou',
                                        'partnership_framework',
                                        'financing_agreement',
                                        'cooperation_agreement',
                                        'other',
                                    ]}
                                />
                            </Field>
                        </div>
                        <Field label="Title" error={errors.title}>
                            <Input name="title" required />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <DatePickerField
                                name="starts_on"
                                label="Starts on"
                                error={errors.starts_on}
                                required
                            />
                            <DatePickerField
                                name="ends_on"
                                label="Ends on"
                                error={errors.ends_on}
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label="Committed value"
                                error={errors.committed_value}
                            >
                                <Input
                                    name="committed_value"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                />
                            </Field>
                            <ReferenceCatalogSelect
                                id="partner-agreement-currency"
                                name="currency"
                                label="Currency"
                                catalog="currency"
                                error={errors.currency}
                            />
                        </div>
                        <Field
                            label="Document reference"
                            error={errors.document_reference}
                        >
                            <Input
                                name="document_reference"
                                placeholder="Repository record or secure URL"
                            />
                        </Field>
                        <Field label="Summary" error={errors.summary}>
                            <textarea
                                name="summary"
                                required
                                className={textareaClass}
                            />
                        </Field>
                        <Button
                            type="submit"
                            disabled={processing || partners.length === 0}
                        >
                            Register agreement
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function ContributionForm({ teamSlug, partners, projects }: Props) {
    return (
        <FormSheet
            title="Report contribution"
            triggerLabel="Report contribution"
            icon={WalletCards}
            description="Record who funds what, where and how with traceable source provenance."
        >
            <Form
                action={storeContribution(teamSlug)}
                className="grid gap-4"
                resetOnSuccess
            >
                {({ errors, processing }) => (
                    <>
                        <Field
                            label="Partner"
                            error={errors.partner_profile_id}
                        >
                            <Select
                                name="partner_profile_id"
                                options={partners}
                            />
                        </Field>
                        <Field
                            label="Project"
                            error={errors.devolution_project_id}
                        >
                            <Select
                                name="devolution_project_id"
                                options={projects}
                            />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label="Financial year"
                                error={errors.financial_year}
                            >
                                <Input
                                    name="financial_year"
                                    defaultValue="2026/2027"
                                    required
                                />
                            </Field>
                            <Field
                                label="Contribution type"
                                error={errors.contribution_type}
                            >
                                <StaticSelect
                                    name="contribution_type"
                                    values={[
                                        'grant',
                                        'loan',
                                        'technical_assistance',
                                        'in_kind',
                                        'co_financing',
                                        'other',
                                    ]}
                                />
                            </Field>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label="Committed"
                                error={errors.committed_amount}
                            >
                                <Input
                                    name="committed_amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    required
                                />
                            </Field>
                            <Field
                                label="Disbursed"
                                error={errors.disbursed_amount}
                            >
                                <Input
                                    name="disbursed_amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    required
                                />
                            </Field>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label="In-kind value"
                                error={errors.in_kind_value}
                            >
                                <Input
                                    name="in_kind_value"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                />
                            </Field>
                            <ReferenceCatalogSelect
                                id="partner-contribution-currency"
                                name="currency"
                                label="Currency"
                                catalog="currency"
                                error={errors.currency}
                            />
                        </div>
                        <Field
                            label="Source system"
                            error={errors['provenance.source_system']}
                        >
                            <Input
                                name="provenance[source_system]"
                                defaultValue="Partner portal"
                                required
                            />
                        </Field>
                        <input
                            type="hidden"
                            name="provenance[captured_at]"
                            value={new Date().toISOString()}
                        />
                        <input type="hidden" name="status" value="committed" />
                        <Field label="Description" error={errors.description}>
                            <textarea
                                name="description"
                                className={textareaClass}
                            />
                        </Field>
                        <Button
                            type="submit"
                            disabled={
                                processing ||
                                partners.length === 0 ||
                                projects.length === 0
                            }
                        >
                            Record contribution
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}

function Select({ name, options }: { name: string; options: Option[] }) {
    return (
        <SearchableSelect
            id={`partner-${name}`}
            name={name}
            label=""
            options={options.map((option) => ({
                id: option.id,
                name: `${option.code ? `${option.code} · ` : ''}${option.title ?? option.name}${option.email ? ` · ${option.email}` : ''}`,
            }))}
        />
    );
}

function MultiSelect({
    name,
    options,
    optional = false,
}: {
    name: string;
    options: Option[];
    optional?: boolean;
}) {
    return (
        <SearchableMultiSelect
            name={name}
            label=""
            options={options.map((option) => ({
                id: option.id,
                name: `${option.name}${option.email ? ` · ${option.email}` : ''}`,
            }))}
            optional={optional}
        />
    );
}

function StaticSelect({ name, values }: { name: string; values: string[] }) {
    return (
        <SearchableSelect
            id={`partner-${name}`}
            name={name}
            label=""
            defaultValue={values[0]}
            options={values.map((value) => ({
                id: value,
                name: value.replaceAll('_', ' '),
            }))}
        />
    );
}

const textareaClass =
    'min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm';
