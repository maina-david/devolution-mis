import { Database } from 'lucide-react';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { useCommonCopy } from '@/hooks/use-localization';

export default function TableEmptyState({
    title,
    description,
}: {
    title?: string;
    description?: string;
}) {
    const copy = useCommonCopy();

    return (
        <Empty className="border-0 py-10" role="status">
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <Database aria-hidden="true" />
                </EmptyMedia>
                <EmptyTitle>{title ?? copy.no_records_found}</EmptyTitle>
                <EmptyDescription>
                    {description ?? copy.no_records_match_filters}
                </EmptyDescription>
            </EmptyHeader>
        </Empty>
    );
}
