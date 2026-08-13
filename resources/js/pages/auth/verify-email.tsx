// Components
import { Form, Head, setLayoutProps, usePage } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    const copy = usePage().props.localization.authentication;

    setLayoutProps({
        title: copy.email_verification,
        description: copy.email_verification_description,
    });

    return (
        <>
            <Head title={copy.email_verification} />

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {copy.new_verification_link_sent}
                </div>
            )}

            <Form {...send.form()} className="space-y-6 text-center">
                {({ processing }) => (
                    <>
                        <Button disabled={processing} variant="secondary">
                            {processing && <Spinner />}
                            {copy.resend_verification_email}
                        </Button>

                        <TextLink
                            href={logout()}
                            className="mx-auto block text-sm"
                        >
                            {copy.log_out}
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}
