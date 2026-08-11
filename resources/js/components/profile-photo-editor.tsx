import { router, useForm } from '@inertiajs/react';
import { Camera, ImageMinus, Upload } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Slider } from '@/components/ui/slider';
import { useInitials } from '@/hooks/use-initials';
import { destroy, store } from '@/routes/profile/photo';

const OUTPUT_SIZE = 512;

type CropState = {
    zoom: number;
    horizontal: number;
    vertical: number;
};

export default function ProfilePhotoEditor({
    name,
    avatar,
    hasPhoto,
    updatedAt,
}: {
    name: string;
    avatar?: string | null;
    hasPhoto: boolean;
    updatedAt: string | null;
}) {
    const initials = useInitials();
    const fileInput = useRef<HTMLInputElement>(null);
    const canvas = useRef<HTMLCanvasElement>(null);
    const sourceImage = useRef<HTMLImageElement | null>(null);
    const [sheetOpen, setSheetOpen] = useState(false);
    const [sourceUrl, setSourceUrl] = useState<string | null>(null);
    const [clientError, setClientError] = useState<string | null>(null);
    const [crop, setCrop] = useState<CropState>({
        zoom: 1,
        horizontal: 0,
        vertical: 0,
    });
    const form = useForm<{ photo: File | null }>({ photo: null });

    const drawCrop = useCallback(() => {
        const context = canvas.current?.getContext('2d');
        const image = sourceImage.current;

        if (!context || !image) {
            return;
        }

        const scale =
            Math.max(OUTPUT_SIZE / image.width, OUTPUT_SIZE / image.height) *
            crop.zoom;
        const scaledWidth = image.width * scale;
        const scaledHeight = image.height * scale;
        const overflowX = Math.max(0, scaledWidth - OUTPUT_SIZE);
        const overflowY = Math.max(0, scaledHeight - OUTPUT_SIZE);
        const offsetX = ((crop.horizontal + 100) / 200) * overflowX;
        const offsetY = ((crop.vertical + 100) / 200) * overflowY;

        context.clearRect(0, 0, OUTPUT_SIZE, OUTPUT_SIZE);
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, OUTPUT_SIZE, OUTPUT_SIZE);
        context.drawImage(image, -offsetX, -offsetY, scaledWidth, scaledHeight);
    }, [crop]);

    useEffect(drawCrop, [drawCrop]);

    useEffect(() => {
        if (sheetOpen && sourceUrl) {
            requestAnimationFrame(drawCrop);
        }
    }, [drawCrop, sheetOpen, sourceUrl]);

    useEffect(() => {
        return () => {
            if (sourceUrl) {
                URL.revokeObjectURL(sourceUrl);
            }
        };
    }, [sourceUrl]);

    const selectFile = (file: File | undefined) => {
        setClientError(null);
        form.clearErrors();

        if (!file) {
            return;
        }

        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            setClientError('Choose a JPEG, PNG, or WebP image.');

            return;
        }

        if (file.size > 10 * 1024 * 1024) {
            setClientError('Choose an image smaller than 10 MB.');

            return;
        }

        if (sourceUrl) {
            URL.revokeObjectURL(sourceUrl);
        }

        const nextUrl = URL.createObjectURL(file);
        const image = new Image();
        image.onload = () => {
            if (image.width < 256 || image.height < 256) {
                URL.revokeObjectURL(nextUrl);
                setClientError('Choose an image at least 256 by 256 pixels.');

                return;
            }

            sourceImage.current = image;
            setCrop({ zoom: 1, horizontal: 0, vertical: 0 });
            setSourceUrl(nextUrl);
            setSheetOpen(true);
            requestAnimationFrame(drawCrop);
        };
        image.onerror = () => {
            URL.revokeObjectURL(nextUrl);
            setClientError('The selected image could not be opened.');
        };
        image.src = nextUrl;
    };

    const uploadCrop = () => {
        if (!canvas.current) {
            return;
        }

        canvas.current.toBlob(
            (blob) => {
                if (!blob) {
                    setClientError('The cropped image could not be prepared.');

                    return;
                }

                const photo = new File([blob], 'profile-photo.jpg', {
                    type: 'image/jpeg',
                });
                form.setData('photo', photo);
                form.transform(() => ({ photo }));
                form.post(store.url(), {
                    forceFormData: true,
                    preserveScroll: true,
                    onSuccess: () => {
                        setSheetOpen(false);
                        form.reset();
                    },
                });
            },
            'image/jpeg',
            0.92,
        );
    };

    return (
        <>
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                <Avatar className="size-24">
                    {avatar ? <AvatarImage src={avatar} alt={name} /> : null}
                    <AvatarFallback className="text-xl">
                        {initials(name)}
                    </AvatarFallback>
                </Avatar>
                <div className="flex flex-1 flex-col gap-2">
                    <div>
                        <p className="font-medium text-foreground">{name}</p>
                        <p className="text-sm text-muted-foreground">
                            {updatedAt
                                ? `Updated ${new Date(updatedAt).toLocaleString()}`
                                : 'No profile photo uploaded'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => fileInput.current?.click()}
                        >
                            <Camera data-icon="inline-start" />
                            {hasPhoto ? 'Replace and crop' : 'Upload and crop'}
                        </Button>
                        {hasPhoto ? (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() =>
                                    router.delete(destroy.url(), {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                <ImageMinus data-icon="inline-start" /> Remove
                            </Button>
                        ) : null}
                    </div>
                    <Input
                        ref={fileInput}
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        className="sr-only"
                        aria-label="Choose a profile photo"
                        onChange={(event) =>
                            selectFile(event.target.files?.[0])
                        }
                    />
                    {clientError ? (
                        <p role="alert" className="text-sm text-destructive">
                            {clientError}
                        </p>
                    ) : null}
                </div>
            </div>

            <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
                <SheetContent className="overflow-y-auto sm:max-w-xl">
                    <SheetHeader>
                        <SheetTitle>Crop profile photo</SheetTitle>
                        <SheetDescription>
                            Adjust the zoom and position. The saved photo will
                            match the square preview exactly.
                        </SheetDescription>
                    </SheetHeader>
                    <div className="flex flex-col gap-6 px-4">
                        <div className="aspect-square overflow-hidden rounded-xl border bg-muted">
                            <canvas
                                ref={canvas}
                                width={OUTPUT_SIZE}
                                height={OUTPUT_SIZE}
                                className="size-full"
                                role="img"
                                aria-label="Cropped profile photo preview"
                            />
                        </div>
                        <FieldGroup>
                            <Field>
                                <FieldLabel htmlFor="photo-zoom">
                                    Zoom · {crop.zoom.toFixed(1)}×
                                </FieldLabel>
                                <Slider
                                    id="photo-zoom"
                                    min={1}
                                    max={3}
                                    step={0.1}
                                    value={[crop.zoom]}
                                    onValueChange={(values) => {
                                        const zoom = values[0];

                                        if (zoom === undefined) {
                                            return;
                                        }

                                        setCrop((current) => ({
                                            ...current,
                                            zoom,
                                        }));
                                    }}
                                    aria-label="Photo zoom"
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="photo-horizontal">
                                    Horizontal position
                                </FieldLabel>
                                <Slider
                                    id="photo-horizontal"
                                    min={-100}
                                    max={100}
                                    value={[crop.horizontal]}
                                    onValueChange={(values) => {
                                        const horizontal = values[0];

                                        if (horizontal === undefined) {
                                            return;
                                        }

                                        setCrop((current) => ({
                                            ...current,
                                            horizontal,
                                        }));
                                    }}
                                    aria-label="Horizontal photo position"
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="photo-vertical">
                                    Vertical position
                                </FieldLabel>
                                <Slider
                                    id="photo-vertical"
                                    min={-100}
                                    max={100}
                                    value={[crop.vertical]}
                                    onValueChange={(values) => {
                                        const vertical = values[0];

                                        if (vertical === undefined) {
                                            return;
                                        }

                                        setCrop((current) => ({
                                            ...current,
                                            vertical,
                                        }));
                                    }}
                                    aria-label="Vertical photo position"
                                />
                                <FieldDescription>
                                    Use arrow keys on each control for precise
                                    keyboard adjustment.
                                </FieldDescription>
                            </Field>
                        </FieldGroup>
                        <InputError message={form.errors.photo} />
                        {form.progress ? (
                            <div
                                className="flex flex-col gap-2"
                                role="status"
                                aria-live="polite"
                            >
                                <Progress value={form.progress.percentage} />
                                <p className="text-sm text-muted-foreground">
                                    Uploading {form.progress.percentage}%
                                </p>
                            </div>
                        ) : null}
                    </div>
                    <SheetFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => fileInput.current?.click()}
                            disabled={form.processing}
                        >
                            <Upload data-icon="inline-start" /> Choose another
                        </Button>
                        <Button
                            type="button"
                            onClick={uploadCrop}
                            disabled={form.processing || !sourceUrl}
                            aria-busy={form.processing}
                        >
                            Save cropped photo
                        </Button>
                    </SheetFooter>
                </SheetContent>
            </Sheet>
        </>
    );
}
