import { Form, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { update } from '@/routes/platform-settings';

export default function PlatformSettingRowAction({
    settingId,
    value,
}: {
    settingId: string;
    value?: string | null;
}) {
    const copy = usePage().props.localization.programmeWorkspace;

    return (
        <Form
            {...update.form({ setting: settingId })}
            className="ml-auto flex w-64 gap-2"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid flex-1 gap-1">
                        <Input
                            name="value"
                            defaultValue={value ?? ''}
                            required
                            aria-label={copy.setting_value}
                            aria-invalid={Boolean(errors.value)}
                            aria-describedby={
                                errors.value
                                    ? `setting-${settingId}-value-error`
                                    : undefined
                            }
                        />
                        <InputError
                            id={`setting-${settingId}-value-error`}
                            message={errors.value}
                            className="text-xs"
                        />
                    </div>
                    <Button
                        type="submit"
                        size="sm"
                        disabled={processing}
                        aria-busy={processing}
                    >
                        {copy.save}
                    </Button>
                </>
            )}
        </Form>
    );
}
