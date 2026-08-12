import { Form } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { useState } from 'react';
import type { CountyIdentityValue } from '@/components/county-identity';
import FormSheet from '@/components/form-sheet';
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
    const [role, setRole] = useState(roles[0]?.value ?? '');
    const isCountyRole = ['county-official', 'county-admin'].includes(role);
    const isPortfolioRole = [
        'assessor',
        'development-partner',
        'top-management',
    ].includes(role);

    return (
        <FormSheet
            title="Grant programme access"
            description="Create an administrator-approved identity and send password setup instructions."
            triggerLabel="Grant access"
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
                            <Label htmlFor="access-name">Name</Label>
                            <Input
                                id="access-name"
                                name="name"
                                required
                                aria-invalid={!!errors.name}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="access-email">Official email</Label>
                            <Input
                                id="access-email"
                                name="email"
                                type="email"
                                required
                                aria-invalid={!!errors.email}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="access-role">Programme role</Label>
                            <SearchableSelect
                                id="access-role"
                                name="role"
                                label=""
                                value={role}
                                onValueChange={setRole}
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
                                    label="Home county"
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
                                    label="Assigned county portfolio"
                                    options={counties}
                                    error={errors.assigned_county_ids}
                                />
                            </div>
                        )}
                        <div className="md:col-span-2">
                            <Button
                                type="submit"
                                disabled={processing || !role}
                            >
                                <UserPlus aria-hidden="true" />
                                Grant access
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}
