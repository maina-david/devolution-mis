import { Form, usePage } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { useState } from 'react';
import type { CountyIdentityValue } from '@/components/county-identity';
import FormSheet from '@/components/form-sheet';
import InputError from '@/components/input-error';
import SearchableMultiSelect from '@/components/searchable-multi-select';
import SearchableSelect from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/programme-users';

type Option = { value: string; label: string };

export default function ProgrammeUserAccessForm({
    roles,
    counties,
}: {
    roles: Option[];
    counties: CountyIdentityValue[];
}) {
    const copy = usePage().props.localization.accessControl;
    const [role, setRole] = useState(roles[0]?.value ?? '');
    const isCountyRole = ['county-official', 'county-admin'].includes(role);
    const isPortfolioRole = [
        'assessor',
        'development-partner',
        'top-management',
    ].includes(role);

    return (
        <FormSheet
            title={copy.grant_programme_access}
            description={copy.grant_programme_access_description}
            triggerLabel={copy.grant_access}
            icon={UserPlus}
            size="xl"
        >
            <Form
                {...store.form()}
                className="grid gap-4 pt-4 md:grid-cols-2"
                resetOnSuccess
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="access-name">{copy.name}</Label>
                            <Input
                                id="access-name"
                                name="name"
                                required
                                aria-invalid={Boolean(errors.name)}
                                aria-describedby={
                                    errors.name
                                        ? 'access-name-error'
                                        : undefined
                                }
                            />
                            <InputError
                                id="access-name-error"
                                message={errors.name}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="access-email">
                                {copy.official_email}
                            </Label>
                            <Input
                                id="access-email"
                                name="email"
                                type="email"
                                required
                                aria-invalid={Boolean(errors.email)}
                                aria-describedby={
                                    errors.email
                                        ? 'access-email-error'
                                        : undefined
                                }
                            />
                            <InputError
                                id="access-email-error"
                                message={errors.email}
                            />
                        </div>
                        <div>
                            <SearchableSelect
                                id="access-role"
                                name="role"
                                label={copy.programme_role}
                                value={role}
                                onValueChange={setRole}
                                error={errors.role}
                                options={roles.map((option) => ({
                                    id: option.value,
                                    name: option.label,
                                }))}
                            />
                        </div>
                        <div className="grid gap-2">
                            {isCountyRole ? (
                                <SearchableSelect
                                    id="access-county"
                                    name="county_id"
                                    label={copy.home_county}
                                    options={counties}
                                    error={errors.county_id}
                                />
                            ) : (
                                <input
                                    id="access-county"
                                    name="county_id"
                                    type="hidden"
                                    value=""
                                />
                            )}
                        </div>
                        {isPortfolioRole && (
                            <div className="md:col-span-2">
                                <SearchableMultiSelect
                                    name="assigned_county_ids[]"
                                    label={copy.assigned_county_portfolio}
                                    options={counties}
                                    error={errors.assigned_county_ids}
                                />
                            </div>
                        )}
                        <div className="md:col-span-2">
                            <Button
                                type="submit"
                                disabled={processing || !role}
                                aria-busy={processing}
                            >
                                <UserPlus aria-hidden="true" />
                                {copy.grant_access}
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}
