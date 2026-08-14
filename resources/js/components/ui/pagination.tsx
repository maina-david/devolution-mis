import * as React from 'react';
import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import { buttonVariants } from '@/components/ui/button';
import { useCommonCopy } from '@/hooks/use-localization';

function Pagination(props: React.ComponentProps<'nav'>) { const copy = useCommonCopy(); return <nav aria-label={copy.pagination} className={cn('flex w-full justify-center', props.className)} {...props} />; }
function PaginationContent(props: React.ComponentProps<'ul'>) { return <ul className={cn('flex items-center gap-1', props.className)} {...props} />; }
function PaginationItem(props: React.ComponentProps<'li'>) { return <li {...props} />; }
function PaginationLink({ className, isActive, ...props }: React.ComponentProps<'a'> & { isActive?: boolean }) { return <a aria-current={isActive ? 'page' : undefined} className={cn(buttonVariants({ variant: isActive ? 'outline' : 'ghost', size: 'icon' }), className)} {...props} />; }
function PaginationPrevious(props: React.ComponentProps<typeof PaginationLink>) { const copy = useCommonCopy(); return <PaginationLink aria-label={copy.previous_page} {...props}><ChevronLeftIcon /><span className="sr-only">{copy.previous}</span></PaginationLink>; }
function PaginationNext(props: React.ComponentProps<typeof PaginationLink>) { const copy = useCommonCopy(); return <PaginationLink aria-label={copy.next_page} {...props}><ChevronRightIcon /><span className="sr-only">{copy.next}</span></PaginationLink>; }

export { Pagination, PaginationContent, PaginationItem, PaginationLink, PaginationNext, PaginationPrevious };
