import { FileImage, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import {
    Carousel,
    CarouselContent,
    CarouselItem,
    CarouselNext,
    CarouselPrevious,
} from '@/components/ui/carousel';
import type { CarouselApi } from '@/components/ui/carousel';
import { Marker, MarkerContent, MarkerIcon } from '@/components/ui/marker';

export type PreviewItem = {
    id: string;
    title: string;
    url: string;
    mimeType: string;
    checksum?: string | null;
    version?: string | null;
    source?: string | null;
    uploadedBy?: string | null;
};

export default function DocumentPreviewCarousel({
    items,
    pageLabel,
    verifiedLabel,
    previousLabel,
    nextLabel,
    separator,
}: {
    items: PreviewItem[];
    pageLabel: (page: number, total: number) => string;
    verifiedLabel: string;
    previousLabel: string;
    nextLabel: string;
    separator: string;
}) {
    const [current, setCurrent] = useState(1);

    const bindApi = (nextApi: CarouselApi) => {
        setCurrent((nextApi?.selectedScrollSnap() ?? 0) + 1);
        nextApi?.on('select', () =>
            setCurrent(nextApi.selectedScrollSnap() + 1),
        );
    };

    if (items.length === 0) {
        return null;
    }

    return (
        <Carousel setApi={bindApi} className="px-10">
            <CarouselContent>
                {items.map((item) => (
                    <CarouselItem key={item.id}>
                        <figure className="flex flex-col gap-3">
                            {item.mimeType.startsWith('image/') ? (
                                <img
                                    src={item.url}
                                    alt={item.title}
                                    className="max-h-[65dvh] w-full rounded-md object-contain"
                                />
                            ) : (
                                <iframe
                                    src={item.url}
                                    title={item.title}
                                    className="h-[65dvh] w-full rounded-md border"
                                />
                            )}
                            <figcaption className="flex flex-col gap-1">
                                <Marker>
                                    <MarkerIcon>
                                        <FileImage />
                                    </MarkerIcon>
                                    <MarkerContent>
                                        {pageLabel(current, items.length)}
                                    </MarkerContent>
                                </Marker>
                                {item.checksum && (
                                    <Marker>
                                        <MarkerIcon>
                                            <ShieldCheck />
                                        </MarkerIcon>
                                        <MarkerContent>
                                            {verifiedLabel} {separator}{' '}
                                            {item.checksum}
                                        </MarkerContent>
                                    </Marker>
                                )}
                                {item.source && (
                                    <Marker>
                                        <MarkerContent>
                                            {item.source}
                                        </MarkerContent>
                                    </Marker>
                                )}
                                {item.uploadedBy && (
                                    <Marker>
                                        <MarkerContent>
                                            {item.uploadedBy}
                                        </MarkerContent>
                                    </Marker>
                                )}
                            </figcaption>
                        </figure>
                    </CarouselItem>
                ))}
            </CarouselContent>
            {items.length > 1 && (
                <>
                    <CarouselPrevious aria-label={previousLabel} />
                    <CarouselNext aria-label={nextLabel} />
                </>
            )}
        </Carousel>
    );
}
