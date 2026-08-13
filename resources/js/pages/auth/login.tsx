import { Form, Head, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { help } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    const copy = usePage().props.localization.copy;

    return (
        <>
            <Head title={copy.logIn} />

            <PasskeyVerify />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {copy.emailAddress}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoComplete="email"
                                    placeholder={copy.workEmailPlaceholder}
                                    aria-invalid={Boolean(errors.email)}
                                    aria-describedby={
                                        errors.email ? 'email-error' : undefined
                                    }
                                />
                                <InputError
                                    id="email-error"
                                    message={errors.email}
                                />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">
                                        {copy.password}
                                    </Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-sm"
                                        >
                                            {copy.forgotPassword}
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autoComplete="current-password"
                                    placeholder={copy.password}
                                    aria-invalid={Boolean(errors.password)}
                                    aria-describedby={
                                        errors.password
                                            ? 'password-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="password-error"
                                    message={errors.password}
                                />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    aria-label={copy.rememberMe}
                                />
                                <Label htmlFor="remember">
                                    {copy.rememberMe}
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 min-h-11 w-full font-semibold"
                                disabled={processing}
                                aria-busy={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                {copy.logIn}
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            {copy.administratorGrantedAccess}{' '}
                            <TextLink href={help()}>{copy.getHelp}</TextLink>
                        </div>
                    </>
                )}
            </Form>

            {status && (
                <div
                    role="status"
                    aria-live="polite"
                    className="mb-4 text-center text-sm font-medium text-primary"
                >
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    name: 'login',
};
