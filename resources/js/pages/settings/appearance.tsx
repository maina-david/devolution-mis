import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    const copy = usePage().props.localization.settingsProfile;
    setLayoutProps({
        breadcrumbs: [
            { title: copy.appearance_settings, href: editAppearance() },
        ],
    });

    return (
        <>
            <Head title={copy.appearance_settings} />

            <h1 className="sr-only">{copy.appearance_settings}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={copy.appearance_settings}
                    description={copy.appearance_settings_description}
                />
                <AppearanceTabs />
            </div>
        </>
    );
}

Appearance.layout = { breadcrumbs: [] };
