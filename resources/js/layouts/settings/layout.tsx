import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';

export default function SettingsLayout({ children }: PropsWithChildren) {
    return (
        <div className="flex flex-col gap-8 px-4 py-6 sm:px-6 lg:px-8">
            <Heading
                title="Settings"
                description="Manage your profile and account settings"
            />
            <section className="flex w-full min-w-0 flex-col gap-12">
                {children}
            </section>
        </div>
    );
}
