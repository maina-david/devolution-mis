import { router, usePage } from '@inertiajs/react';
import { useConnectionStatus, useEchoNotification } from '@laravel/echo-react';
import { useCallback, useEffect } from 'react';

type ProgrammeNotification = {
    id: string;
    type: string;
    title: string;
    message: string;
    category: string;
    url: string | null;
};

export default function NotificationRealtimeSync() {
    const page = usePage();
    const userId = page.props.auth.user?.id;

    return userId ? (
        <AuthenticatedNotificationRealtimeSync userId={userId} />
    ) : null;
}

function AuthenticatedNotificationRealtimeSync({ userId }: { userId: string }) {
    const page = usePage();
    const connectionStatus = useConnectionStatus();
    const refresh = useCallback(() => {
        router.reload({
            only:
                page.component === 'notifications/index'
                    ? ['notificationSummary', 'notifications']
                    : ['notificationSummary'],
        });
    }, [page.component]);

    useEchoNotification<ProgrammeNotification>(
        `App.Models.User.${userId}`,
        refresh,
        [],
        [refresh],
    );

    useEffect(() => {
        if (!['disconnected', 'failed'].includes(connectionStatus)) {
            return;
        }

        const fallback = window.setInterval(refresh, 30_000);

        return () => window.clearInterval(fallback);
    }, [connectionStatus, refresh]);

    return null;
}
