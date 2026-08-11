import { Form, Head, Link, usePage } from '@inertiajs/react';
import { BadgeCheck, Building2, Pencil, ShieldCheck } from 'lucide-react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
import DeleteUser from '@/components/delete-user';
import ProfilePhotoEditor from '@/components/profile-photo-editor';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { index as teamsIndex } from '@/routes/teams';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
    profile: {
        role: string;
        county: CountyIdentityValue | null;
        assignedCounties: CountyIdentityValue[];
        teams: Array<{ id: string; name: string }>;
        hasPhoto: boolean;
        photoUpdatedAt: string | null;
        accountCreatedAt: string | null;
    };
};

export default function Profile({ mustVerifyEmail, status, profile }: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <>
            <Head title="Profile settings" />
            <h1 className="sr-only">Profile settings</h1>

            <div className="grid gap-6 xl:grid-cols-3">
                <Card className="xl:col-span-2">
                    <CardHeader>
                        <CardTitle>Profile photo</CardTitle>
                        <CardDescription>
                            Upload, position and crop the photo shown in your
                            authenticated account header.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ProfilePhotoEditor
                            name={auth.user.name}
                            avatar={auth.user.avatar}
                            hasPhoto={profile.hasPhoto}
                            updatedAt={profile.photoUpdatedAt}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Account status</CardTitle>
                        <CardDescription>
                            Your governed identity and verification state.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Role
                            </p>
                            <Badge className="mt-1" variant="secondary">
                                {profile.role}
                            </Badge>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Email verification
                            </p>
                            <p className="mt-1 flex items-center gap-2 font-medium">
                                <BadgeCheck aria-hidden="true" />
                                {auth.user.email_verified_at
                                    ? 'Verified'
                                    : 'Pending verification'}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Account created
                            </p>
                            <p className="mt-1 font-medium">
                                {profile.accountCreatedAt
                                    ? new Date(
                                          profile.accountCreatedAt,
                                      ).toLocaleDateString()
                                    : 'Not recorded'}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card className="xl:col-span-2">
                    <CardHeader className="flex-row items-start justify-between gap-4">
                        <div className="flex flex-col gap-1.5">
                            <CardTitle>Personal information</CardTitle>
                            <CardDescription>
                                Keep your account name and email address
                                current.
                            </CardDescription>
                        </div>
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button variant="outline">
                                    <Pencil data-icon="inline-start" /> Edit
                                </Button>
                            </SheetTrigger>
                            <SheetContent className="overflow-y-auto sm:max-w-lg">
                                <SheetHeader>
                                    <SheetTitle>
                                        Edit personal information
                                    </SheetTitle>
                                    <SheetDescription>
                                        Changing your email may require it to be
                                        verified again.
                                    </SheetDescription>
                                </SheetHeader>
                                <Form
                                    {...ProfileController.update.form()}
                                    options={{ preserveScroll: true }}
                                    className="flex min-h-0 flex-1 flex-col"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <FieldGroup className="px-4">
                                                <Field
                                                    data-invalid={Boolean(
                                                        errors.name,
                                                    )}
                                                >
                                                    <FieldLabel htmlFor="name">
                                                        Full name
                                                    </FieldLabel>
                                                    <Input
                                                        id="name"
                                                        defaultValue={
                                                            auth.user.name
                                                        }
                                                        name="name"
                                                        required
                                                        autoComplete="name"
                                                        aria-invalid={Boolean(
                                                            errors.name,
                                                        )}
                                                        aria-describedby={
                                                            errors.name
                                                                ? 'name-error'
                                                                : undefined
                                                        }
                                                    />
                                                    <FieldError id="name-error">
                                                        {errors.name}
                                                    </FieldError>
                                                </Field>
                                                <Field
                                                    data-invalid={Boolean(
                                                        errors.email,
                                                    )}
                                                >
                                                    <FieldLabel htmlFor="email">
                                                        Email address
                                                    </FieldLabel>
                                                    <Input
                                                        id="email"
                                                        type="email"
                                                        defaultValue={
                                                            auth.user.email
                                                        }
                                                        name="email"
                                                        required
                                                        autoComplete="username"
                                                        aria-invalid={Boolean(
                                                            errors.email,
                                                        )}
                                                        aria-describedby={
                                                            errors.email
                                                                ? 'email-error'
                                                                : undefined
                                                        }
                                                    />
                                                    <FieldError id="email-error">
                                                        {errors.email}
                                                    </FieldError>
                                                </Field>
                                            </FieldGroup>
                                            <SheetFooter>
                                                <Button
                                                    type="submit"
                                                    disabled={processing}
                                                    aria-busy={processing}
                                                    data-test="update-profile-button"
                                                >
                                                    Save changes
                                                </Button>
                                            </SheetFooter>
                                        </>
                                    )}
                                </Form>
                            </SheetContent>
                        </Sheet>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    Full name
                                </dt>
                                <dd className="mt-1 font-medium">
                                    {auth.user.name}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    Email address
                                </dt>
                                <dd className="mt-1 font-medium break-all">
                                    {auth.user.email}
                                </dd>
                            </div>
                        </dl>

                        {mustVerifyEmail &&
                        auth.user.email_verified_at === null ? (
                            <div className="mt-5 rounded-lg border p-4">
                                <p className="text-sm text-muted-foreground">
                                    Your email address is unverified.{' '}
                                    <Link
                                        href={send()}
                                        as="button"
                                        className="font-medium text-foreground underline underline-offset-4"
                                    >
                                        Re-send the verification email
                                    </Link>
                                    .
                                </p>
                                {status === 'verification-link-sent' ? (
                                    <p
                                        role="status"
                                        className="mt-2 text-sm font-medium text-foreground"
                                    >
                                        A new verification link has been sent.
                                    </p>
                                ) : null}
                            </div>
                        ) : null}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Access scope</CardTitle>
                        <CardDescription>
                            Assigned by an authorized administrator.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Home county
                            </p>
                            <div className="mt-2">
                                {profile.county ? (
                                    <CountyIdentity county={profile.county} />
                                ) : (
                                    <p className="font-medium">
                                        National / portfolio
                                    </p>
                                )}
                            </div>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Assigned counties
                            </p>
                            <p className="mt-1 font-medium">
                                {profile.assignedCounties.length
                                    ? profile.assignedCounties
                                          .map((county) => county.name)
                                          .join(', ')
                                    : 'None'}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Workspaces
                            </p>
                            <p className="mt-1 font-medium">
                                {profile.teams
                                    .map((team) => team.name)
                                    .join(', ') || 'None'}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card className="xl:col-span-3">
                    <CardHeader>
                        <CardTitle>Account controls</CardTitle>
                        <CardDescription>
                            Continue to the complete security, workspace and
                            display controls for this account.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 md:grid-cols-3">
                        <Button variant="outline" asChild>
                            <Link href={editSecurity()}>
                                <ShieldCheck data-icon="inline-start" />
                                Password, MFA and passkeys
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={teamsIndex()}>
                                <Building2 data-icon="inline-start" />
                                Teams and memberships
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={editAppearance()}>
                                Appearance and theme
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <DeleteUser />
        </>
    );
}

Profile.layout = {
    breadcrumbs: [{ title: 'Profile settings', href: edit() }],
};
