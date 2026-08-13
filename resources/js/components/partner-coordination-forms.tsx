import { Form, usePage } from '@inertiajs/react';
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

function ProfileForm({ organizations, counties, sectors, users }: Props) {
    const copy = usePage().props.localization.partnerCoordination;

    return (
        <FormSheet
            title={copy.form_register_partner}
            triggerLabel={copy.form_register_partner}
            icon={Handshake}
            description={copy.form_register_partner_description}
        >
            <Form action={storeProfile()} className="grid gap-4" resetOnSuccess>
                {({ errors, processing }) => (
                    <>
                        <Field
                            label={copy.form_organization}
                            error={errors.organization_id}
                        >
                            <Select
                                name="organization_id"
                                options={organizations}
                            />
                        </Field>
                        <Field
                            label={copy.form_partner_type}
                            error={errors.partner_type}
                        >
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
                            label={copy.form_country}
                            catalog="country-name"
                            error={errors.country}
                            optional
                        />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label={copy.form_focal_point}
                                error={errors.focal_point_name}
                            >
                                <Input name="focal_point_name" required />
                            </Field>
                            <Field
                                label={copy.form_focal_email}
                                error={errors.focal_point_email}
                            >
                                <Input
                                    name="focal_point_email"
                                    type="email"
                                    required
                                />
                            </Field>
                        </div>
                        <Field label={copy.form_website} error={errors.website}>
                            <Input
                                name="website"
                                type="url"
                                placeholder="https://"
                            />
                        </Field>
                        <Field
                            label={copy.form_county_portfolio}
                            error={errors.county_ids}
                        >
                            <MultiSelect
                                name="county_ids[]"
                                options={counties}
                            />
                        </Field>
                        <Field
                            label={copy.form_sector_portfolio}
                            error={errors.sector_ids}
                        >
                            <MultiSelect
                                name="sector_ids[]"
                                options={sectors}
                            />
                        </Field>
                        <Field
                            label={copy.form_authorized_partner_users}
                            error={errors.user_ids}
                        >
                            <MultiSelect
                                name="user_ids[]"
                                options={users}
                                optional
                            />
                        </Field>
                        <Field
                            label={copy.form_strategic_priorities}
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
                            aria-busy={processing}
                        >
                            {copy.form_create_partner_profile}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function AgreementForm({ partners }: Props) {
    const copy = usePage().props.localization.partnerCoordination;

    return (
        <FormSheet
            title={copy.form_register_agreement}
            triggerLabel={copy.form_register_agreement}
            icon={Landmark}
            description={copy.form_register_agreement_description}
        >
            <Form
                action={storeAgreement()}
                className="grid gap-4"
                resetOnSuccess
            >
                {({ errors, processing }) => (
                    <>
                        <Field
                            label={copy.form_partner}
                            error={errors.partner_profile_id}
                        >
                            <Select
                                name="partner_profile_id"
                                options={partners}
                            />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label={copy.form_reference}
                                error={errors.reference}
                            >
                                <Input name="reference" required />
                            </Field>
                            <Field
                                label={copy.form_agreement_type}
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
                        <Field label={copy.form_title} error={errors.title}>
                            <Input name="title" required />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <DatePickerField
                                name="starts_on"
                                label={copy.form_starts_on}
                                error={errors.starts_on}
                                required
                            />
                            <DatePickerField
                                name="ends_on"
                                label={copy.form_ends_on}
                                error={errors.ends_on}
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label={copy.form_committed_value}
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
                                label={copy.form_currency}
                                catalog="currency"
                                error={errors.currency}
                            />
                        </div>
                        <Field
                            label={copy.form_document_reference}
                            error={errors.document_reference}
                        >
                            <Input
                                name="document_reference"
                                placeholder={
                                    copy.form_document_reference_placeholder
                                }
                            />
                        </Field>
                        <Field label={copy.form_summary} error={errors.summary}>
                            <textarea
                                name="summary"
                                required
                                className={textareaClass}
                            />
                        </Field>
                        <Button
                            type="submit"
                            disabled={processing || partners.length === 0}
                            aria-busy={processing}
                        >
                            {copy.form_register_agreement}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function ContributionForm({ partners, projects }: Props) {
    const copy = usePage().props.localization.partnerCoordination;

    return (
        <FormSheet
            title={copy.form_report_contribution}
            triggerLabel={copy.form_report_contribution}
            icon={WalletCards}
            description={copy.form_report_contribution_description}
        >
            <Form
                action={storeContribution()}
                className="grid gap-4"
                resetOnSuccess
            >
                {({ errors, processing }) => (
                    <>
                        <Field
                            label={copy.form_partner}
                            error={errors.partner_profile_id}
                        >
                            <Select
                                name="partner_profile_id"
                                options={partners}
                            />
                        </Field>
                        <Field
                            label={copy.form_project}
                            error={errors.devolution_project_id}
                        >
                            <Select
                                name="devolution_project_id"
                                options={projects}
                            />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label={copy.form_financial_year}
                                error={errors.financial_year}
                            >
                                <Input
                                    name="financial_year"
                                    defaultValue="2026/2027"
                                    required
                                />
                            </Field>
                            <Field
                                label={copy.form_contribution_type}
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
                                label={copy.form_committed}
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
                                label={copy.form_disbursed}
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
                                label={copy.form_in_kind_value}
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
                                label={copy.form_currency}
                                catalog="currency"
                                error={errors.currency}
                            />
                        </div>
                        <Field
                            label={copy.form_source_system}
                            error={errors['provenance.source_system']}
                        >
                            <Input
                                name="provenance[source_system]"
                                defaultValue={copy.form_partner_portal}
                                required
                            />
                        </Field>
                        <input
                            type="hidden"
                            name="provenance[captured_at]"
                            value={new Date().toISOString()}
                        />
                        <input type="hidden" name="status" value="committed" />
                        <Field
                            label={copy.form_description}
                            error={errors.description}
                        >
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
                            aria-busy={processing}
                        >
                            {copy.form_record_contribution}
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
