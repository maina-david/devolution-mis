import { Form, Head, setLayoutProps, usePage } from '@inertiajs/react';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';

export default function ConfirmPassword() {
    const copy = usePage().props.localization.authentication;

    setLayoutProps({
        title: copy.confirm_password,
        description: copy.confirm_password_description,
    });

    return (
        <>
            <Head title={copy.confirm_password} />

            <PasskeyVerify
                routes={{
                    options: confirmOptions(),
                    submit: confirmStore(),
                }}
                label={copy.confirm_with_passkey}
                loadingLabel={copy.confirming}
                separator={copy.or_confirm_with_password}
            />

            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password">{copy.password}</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                placeholder={copy.password}
                                autoComplete="current-password"
                                autoFocus
                                required
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

                        <div className="flex items-center">
                            <Button
                                className="w-full"
                                disabled={processing}
                                data-test="confirm-password-button"
                            >
                                {processing && <Spinner />}
                                {copy.confirm_password}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </>
    );
}
