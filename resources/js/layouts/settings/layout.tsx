import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { useCommonCopy } from '@/hooks/use-localization';

export default function SettingsLayout({ children }: PropsWithChildren) {
    const copy = useCommonCopy();

    return (
        <div className="flex flex-col gap-8 px-4 py-6 sm:px-6 lg:px-8">
            <Heading
                title={copy.settings}
                description={copy.settings_description}
            />
            <section className="flex w-full min-w-0 flex-col gap-12">
                {children}
            </section>
        </div>
    );
}
