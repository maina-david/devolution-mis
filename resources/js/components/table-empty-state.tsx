import { Database } from 'lucide-react';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';

export default function TableEmptyState({
    title = 'No records found',
    description = 'No records match the current filters.',
}: {
    title?: string;
    description?: string;
}) {
    return (
        <Empty className="border-0 py-10" role="status">
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <Database aria-hidden="true" />
                </EmptyMedia>
                <EmptyTitle>{title}</EmptyTitle>
                <EmptyDescription>{description}</EmptyDescription>
            </EmptyHeader>
        </Empty>
    );
}
