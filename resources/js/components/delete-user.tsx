import { Form } from '@inertiajs/react';
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

    return (
        <Card>
            <CardHeader>
                <CardTitle>Account lifecycle</CardTitle>
                <CardDescription>
                    Permanently end access and remove your personal profile
                    photo.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Alert variant="destructive">
                    <AlertTitle>Delete account</AlertTitle>
                    <AlertDescription>
                        This signs you out and removes access. This action
                        cannot be undone without an authorized administrator.
                    </AlertDescription>
                </Alert>

                <Sheet>
                    <SheetTrigger asChild>
                        <Button
                            variant="destructive"
                            className="mt-4"
                            data-test="delete-user-button"
                        >
                            Delete account
                        </Button>
                    </SheetTrigger>
                    <SheetContent className="sm:max-w-lg">
                        <SheetHeader>
                            <SheetTitle>Confirm account deletion</SheetTitle>
                            <SheetDescription>
                                Once your account is deleted, all of its
                                resources and access will be unavailable. Enter
                                your password to confirm.
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
                                                Current password
                                            </FieldLabel>
                                            <PasswordInput
                                                id="delete-password"
                                                name="password"
                                                ref={passwordInput}
                                                placeholder="Current password"
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
                                                Cancel
                                            </Button>
                                        </SheetClose>

                                        <Button
                                            variant="destructive"
                                            disabled={processing}
                                            type="submit"
                                            aria-busy={processing}
                                            data-test="confirm-delete-user-button"
                                        >
                                            Delete account
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
