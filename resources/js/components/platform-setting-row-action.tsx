import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { update } from '@/routes/platform-settings';

export default function PlatformSettingRowAction({
    teamSlug,
    settingId,
    value,
}: {
    teamSlug: string;
    settingId: string;
    value?: string | null;
}) {
    return (
        <Form
            {...update.form({ current_team: teamSlug, setting: settingId })}
            className="ml-auto flex w-64 gap-2"
        >
            {({ processing }) => (
                <>
                    <Input
                        name="value"
                        defaultValue={value ?? ''}
                        required
                        aria-label="Setting value"
                    />
                    <Button type="submit" size="sm" disabled={processing}>
                        Save
                    </Button>
                </>
            )}
        </Form>
    );
}
