import { Form, usePage } from '@inertiajs/react';
import { UserX } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { destroy } from '@/routes/programme-users';

export default function ProgrammeUserRowAction({
    userId,
    isCurrentUser,
}: {
    userId: string;
    isCurrentUser: boolean;
}) {
    const copy = usePage().props.localization.accessControl;

    if (isCurrentUser) {
        return (
            <span className="text-xs text-muted-foreground">
                {copy.current_user}
            </span>
        );
    }

    return (
        <Form {...destroy.form({ programmeUser: userId })}>
            {({ processing }) => (
                <Button
                    type="submit"
                    size="sm"
                    variant="outline"
                    disabled={processing}
                    aria-busy={processing}
                >
                    <UserX aria-hidden="true" />
                    {copy.deactivate}
                </Button>
            )}
        </Form>
    );
}
