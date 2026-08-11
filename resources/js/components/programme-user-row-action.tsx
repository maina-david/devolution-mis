import { Form } from '@inertiajs/react';
import { UserX } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { destroy } from '@/routes/programme-users';

export default function ProgrammeUserRowAction({
    teamSlug,
    userId,
    isCurrentUser,
}: {
    teamSlug: string;
    userId: string;
    isCurrentUser: boolean;
}) {
    if (isCurrentUser) {
        return (
            <span className="text-xs text-muted-foreground">Current user</span>
        );
    }

    return (
        <Form
            {...destroy.form({ current_team: teamSlug, programmeUser: userId })}
        >
            {({ processing }) => (
                <Button
                    type="submit"
                    size="sm"
                    variant="outline"
                    disabled={processing}
                >
                    <UserX aria-hidden="true" />
                    Deactivate
                </Button>
            )}
        </Form>
    );
}
