import { Form, Head, usePage } from '@inertiajs/react';
import { KeyRound, LockKeyhole, Pencil } from 'lucide-react';
import { useRef } from 'react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import type { Props as ManagePasskeysProps } from '@/components/manage-passkeys';
import ManagePasskeys from '@/components/manage-passkeys';
import type { Props as ManageTwoFactorProps } from '@/components/manage-two-factor';
import ManageTwoFactor from '@/components/manage-two-factor';
import PasswordInput from '@/components/password-input';
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
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { edit } from '@/routes/security';

type Props = { passwordRules: string } & ManagePasskeysProps &
    ManageTwoFactorProps;

export default function Security(props: Props) {
    const { localization } = usePage().props;
    const copy = localization.settingsSecurity;
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    return (
        <>
            <Head title={copy.security_settings} />
            <h1 className="sr-only">{copy.security_settings}</h1>

            <div className="grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardHeader className="flex-row items-start justify-between gap-4">
                        <div className="flex flex-col gap-1.5">
                            <CardTitle className="flex items-center gap-2">
                                <LockKeyhole aria-hidden="true" />{' '}
                                {copy.password}
                            </CardTitle>
                            <CardDescription>
                                {copy.password_description}
                            </CardDescription>
                        </div>
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button variant="outline">
                                    <Pencil
                                        data-icon="inline-start"
                                        aria-hidden="true"
                                    />{' '}
                                    {copy.change}
                                </Button>
                            </SheetTrigger>
                            <SheetContent className="overflow-y-auto sm:max-w-lg">
                                <SheetHeader>
                                    <SheetTitle>
                                        {copy.change_password}
                                    </SheetTitle>
                                    <SheetDescription>
                                        {copy.change_password_description}
                                    </SheetDescription>
                                </SheetHeader>
                                <Form
                                    {...SecurityController.update.form()}
                                    options={{ preserveScroll: true }}
                                    resetOnError={[
                                        'password',
                                        'password_confirmation',
                                        'current_password',
                                    ]}
                                    resetOnSuccess
                                    onError={(errors) => {
                                        if (errors.password) {
                                            passwordInput.current?.focus();
                                        } else if (errors.current_password) {
                                            currentPasswordInput.current?.focus();
                                        }
                                    }}
                                    className="flex min-h-0 flex-1 flex-col"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <FieldGroup className="px-4">
                                                <PasswordField
                                                    id="current_password"
                                                    label={
                                                        copy.current_password
                                                    }
                                                    name="current_password"
                                                    autoComplete="current-password"
                                                    error={
                                                        errors.current_password
                                                    }
                                                    inputRef={
                                                        currentPasswordInput
                                                    }
                                                />
                                                <PasswordField
                                                    id="password"
                                                    label={copy.new_password}
                                                    name="password"
                                                    autoComplete="new-password"
                                                    error={errors.password}
                                                    inputRef={passwordInput}
                                                    passwordRules={
                                                        props.passwordRules
                                                    }
                                                />
                                                <PasswordField
                                                    id="password_confirmation"
                                                    label={
                                                        copy.confirm_new_password
                                                    }
                                                    name="password_confirmation"
                                                    autoComplete="new-password"
                                                    error={
                                                        errors.password_confirmation
                                                    }
                                                    passwordRules={
                                                        props.passwordRules
                                                    }
                                                />
                                            </FieldGroup>
                                            <SheetFooter>
                                                <Button
                                                    type="submit"
                                                    disabled={processing}
                                                    aria-busy={processing}
                                                    data-test="update-password-button"
                                                >
                                                    {copy.update_password}
                                                </Button>
                                            </SheetFooter>
                                        </>
                                    )}
                                </Form>
                            </SheetContent>
                        </Sheet>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm text-muted-foreground">
                            {copy.password_protection_notice}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <KeyRound aria-hidden="true" />{' '}
                            {copy.authentication_posture}
                        </CardTitle>
                        <CardDescription>
                            {copy.authentication_posture_description}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2 text-sm">
                        <p>
                            {copy.two_factor_authentication}
                            {copy.field_separator}{' '}
                            <strong>
                                {props.twoFactorEnabled
                                    ? copy.enabled
                                    : copy.not_enabled}
                            </strong>
                        </p>
                        <p>
                            {copy.registered_passkeys}
                            {copy.field_separator}{' '}
                            <strong>
                                {(props.passkeys?.length ?? 0).toLocaleString(
                                    localization.current,
                                )}
                            </strong>
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <ManageTwoFactor {...props} />
                <ManagePasskeys {...props} />
            </div>
        </>
    );
}

function PasswordField({
    id,
    label,
    name,
    autoComplete,
    error,
    inputRef,
    passwordRules,
}: {
    id: string;
    label: string;
    name: string;
    autoComplete: string;
    error?: string;
    inputRef?: React.Ref<HTMLInputElement>;
    passwordRules?: string;
}) {
    return (
        <Field data-invalid={Boolean(error)}>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <PasswordInput
                id={id}
                ref={inputRef}
                name={name}
                autoComplete={autoComplete}
                passwordrules={passwordRules}
                aria-invalid={Boolean(error)}
                aria-describedby={error ? `${id}-error` : undefined}
            />
            <FieldError id={`${id}-error`}>{error}</FieldError>
        </Field>
    );
}

function SecurityLayout() {
    const copy = usePage().props.localization.settingsSecurity;

    return { breadcrumbs: [{ title: copy.security_settings, href: edit() }] };
}

Security.layout = SecurityLayout;
