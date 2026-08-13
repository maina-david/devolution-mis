import { Form, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import PasswordInput from '@/components/password-input';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';

export default function DeleteUser() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const copy = usePage().props.localization.settingsProfile;

    return (
        <Card>
            <CardHeader>
                <CardTitle>{copy.account_lifecycle}</CardTitle>
                <CardDescription>
                    {copy.account_lifecycle_description}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Alert variant="destructive">
                    <AlertTitle>{copy.delete_account}</AlertTitle>
                    <AlertDescription>
                        {copy.delete_account_warning}
                    </AlertDescription>
                </Alert>

                <Sheet>
                    <SheetTrigger asChild>
                        <Button
                            variant="destructive"
                            className="mt-4"
                            data-test="delete-user-button"
                        >
                            {copy.delete_account}
                        </Button>
                    </SheetTrigger>
                    <SheetContent
                        className="sm:max-w-lg"
                        onOpenAutoFocus={(event) => {
                            event.preventDefault();
                            passwordInput.current?.focus();
                        }}
                    >
                        <SheetHeader>
                            <SheetTitle>
                                {copy.confirm_account_deletion}
                            </SheetTitle>
                            <SheetDescription>
                                {copy.confirm_account_deletion_description}
                            </SheetDescription>
                        </SheetHeader>

                        <Form
                            {...ProfileController.destroy.form()}
                            options={{
                                preserveScroll: true,
                            }}
                            onError={() => passwordInput.current?.focus()}
                            resetOnSuccess
                            className="flex min-h-0 flex-1 flex-col"
                        >
                            {({ resetAndClearErrors, processing, errors }) => (
                                <>
                                    <FieldGroup className="px-4">
                                        <Field
                                            data-invalid={Boolean(
                                                errors.password,
                                            )}
                                        >
                                            <FieldLabel htmlFor="delete-password">
                                                {copy.current_password}
                                            </FieldLabel>
                                            <PasswordInput
                                                id="delete-password"
                                                name="password"
                                                ref={passwordInput}
                                                placeholder={
                                                    copy.current_password
                                                }
                                                autoComplete="current-password"
                                                aria-invalid={Boolean(
                                                    errors.password,
                                                )}
                                                aria-describedby={
                                                    errors.password
                                                        ? 'delete-password-error'
                                                        : undefined
                                                }
                                            />
                                            <FieldError id="delete-password-error">
                                                {errors.password}
                                            </FieldError>
                                        </Field>
                                    </FieldGroup>

                                    <SheetFooter>
                                        <SheetClose asChild>
                                            <Button
                                                variant="secondary"
                                                type="button"
                                                onClick={() =>
                                                    resetAndClearErrors()
                                                }
                                            >
                                                {copy.cancel}
                                            </Button>
                                        </SheetClose>

                                        <Button
                                            variant="destructive"
                                            disabled={processing}
                                            type="submit"
                                            aria-busy={processing}
                                            data-test="confirm-delete-user-button"
                                        >
                                            {processing
                                                ? copy.deleting_account
                                                : copy.delete_account}
                                        </Button>
                                    </SheetFooter>
                                </>
                            )}
                        </Form>
                    </SheetContent>
                </Sheet>
            </CardContent>
        </Card>
    );
}
