import { Form, Link, router, usePage } from '@inertiajs/react';
import {
    ChevronRightIcon,
    FileArchiveIcon,
    FolderIcon,
    FolderOpenIcon,
    FolderPenIcon,
    FolderPlusIcon,
    Grid2X2Icon,
    ListIcon,
    Trash2Icon,
    UploadIcon,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
import DatePickerField from '@/components/date-picker-field';
import InputError from '@/components/input-error';
import SearchableSelect from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { interpolate } from '@/hooks/use-localization';
import { index as evidenceIndex } from '@/routes/evidence';
import {
    move as moveDocuments,
    store as storeDocument,
} from '@/routes/evidence/repository/documents';
import {
    destroy,
    store as storeFolder,
    update,
} from '@/routes/evidence/repository/folders';

export type RepositoryFolder = {
    id: string;
    parentId: string | null;
    name: string;
    countyId: string | null;
    county: CountyIdentityValue | null;
    documentCount: number;
};

export type DocumentRepository = {
    currentFolderId: string | null;
    breadcrumbs: Array<{ id: string; name: string }>;
    folders: RepositoryFolder[];
    scopes: Array<{ id: string; name: string }>;
    storageBytes: number;
};

type RepositoryFilters = {
    from?: string;
    to?: string;
    search?: string;
    cycle_id?: string;
    status?: string;
    folder_id?: string;
};

export default function DocumentRepositoryManager({
    repository,
    filters,
    canUpload,
    canManage,
    viewMode,
    onViewModeChange,
}: {
    repository: DocumentRepository;
    filters: RepositoryFilters;
    canUpload: boolean;
    canManage: boolean;
    viewMode: 'grid' | 'list';
    onViewModeChange: (mode: 'grid' | 'list') => void;
}) {
    const copy = usePage().props.localization.documentRepository;
    const [uploadOpen, setUploadOpen] = useState(false);
    const [queuedFile, setQueuedFile] = useState<File | null>(null);
    const [uploadDropActive, setUploadDropActive] = useState(false);
    const currentFolder = repository.folders.find(
        (folder) => folder.id === repository.currentFolderId,
    );
    const visibleFolders = useMemo(
        () =>
            repository.folders
                .filter(
                    (folder) => folder.parentId === repository.currentFolderId,
                )
                .sort((left, right) => left.name.localeCompare(right.name)),
        [repository.currentFolderId, repository.folders],
    );
    const fileCount = currentFolder?.documentCount ?? 0;
    const folderHref = (folderId?: string) =>
        evidenceIndex.url({
            query: {
                ...filters,
                folder_id: folderId,
                page: undefined,
            },
        });
    const moveDroppedDocuments = (
        event: React.DragEvent<HTMLElement>,
        folderId: string,
    ) => {
        event.preventDefault();
        const serializedIds = event.dataTransfer.getData(
            'application/x-idmis-document-ids',
        );

        try {
            const ids: unknown = JSON.parse(serializedIds);

            if (
                !Array.isArray(ids) ||
                !ids.every((id) => typeof id === 'string')
            ) {
                return;
            }

            router.patch(
                moveDocuments.url(),
                { ids, folder_id: folderId },
                { preserveScroll: true },
            );
        } catch {
            return;
        }
    };

    return (
        <Card>
            <CardHeader className="border-b">
                <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            <FileArchiveIcon
                                className="size-5 text-primary"
                                aria-hidden="true"
                            />
                            {copy.title}
                        </CardTitle>
                        <CardDescription className="mt-1 max-w-3xl">
                            {copy.description}
                        </CardDescription>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <ToggleGroup
                            type="single"
                            value={viewMode}
                            onValueChange={(value) => {
                                if (value === 'grid' || value === 'list') {
                                    onViewModeChange(value);
                                }
                            }}
                            variant="outline"
                            aria-label={copy.view_layout}
                        >
                            <ToggleGroupItem
                                value="grid"
                                aria-label={copy.grid_view}
                            >
                                <Grid2X2Icon aria-hidden="true" />
                            </ToggleGroupItem>
                            <ToggleGroupItem
                                value="list"
                                aria-label={copy.list_view}
                            >
                                <ListIcon aria-hidden="true" />
                            </ToggleGroupItem>
                        </ToggleGroup>
                        {canManage && (
                            <FolderCreateSheet
                                folders={repository.folders}
                                scopes={repository.scopes}
                                currentFolder={currentFolder}
                            />
                        )}
                        {canUpload && repository.folders.length > 0 && (
                            <RepositoryUploadSheet
                                folders={repository.folders}
                                currentFolderId={repository.currentFolderId}
                                open={uploadOpen}
                                onOpenChange={setUploadOpen}
                                queuedFile={queuedFile}
                                onQueuedFileChange={setQueuedFile}
                            />
                        )}
                        {canManage && currentFolder && (
                            <FolderManageSheet
                                folder={currentFolder}
                                folders={repository.folders}
                            />
                        )}
                    </div>
                </div>
            </CardHeader>
            <CardContent className="p-0">
                <nav
                    aria-label={copy.folder_tree}
                    className="flex flex-wrap items-center gap-1 border-b px-5 py-3 text-sm"
                >
                    <Link
                        href={folderHref()}
                        className="rounded-md px-2 py-1 font-medium text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        {copy.all_files}
                    </Link>
                    {repository.breadcrumbs.map((folder) => (
                        <span key={folder.id} className="contents">
                            <ChevronRightIcon
                                className="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <Link
                                href={folderHref(folder.id)}
                                aria-current={
                                    folder.id === repository.currentFolderId
                                        ? 'page'
                                        : undefined
                                }
                                className="rounded-md px-2 py-1 font-medium text-foreground hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                {folder.name}
                            </Link>
                        </span>
                    ))}
                </nav>

                {canUpload && repository.folders.length > 0 && (
                    <button
                        type="button"
                        className="m-4 grid w-[calc(100%_-_2rem)] gap-2 rounded-lg border border-dashed p-5 text-center transition-colors hover:border-primary hover:bg-accent/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none data-[active=true]:border-primary data-[active=true]:bg-accent/60"
                        data-active={uploadDropActive}
                        aria-label={copy.drop_file_here}
                        onClick={() => setUploadOpen(true)}
                        onDragEnter={(event) => {
                            event.preventDefault();
                            setUploadDropActive(true);
                        }}
                        onDragOver={(event) => {
                            event.preventDefault();
                            event.dataTransfer.dropEffect = 'copy';
                        }}
                        onDragLeave={(event) => {
                            if (
                                !event.currentTarget.contains(
                                    event.relatedTarget as Node,
                                )
                            ) {
                                setUploadDropActive(false);
                            }
                        }}
                        onDrop={(event) => {
                            event.preventDefault();
                            setUploadDropActive(false);
                            const file = event.dataTransfer.files.item(0);

                            if (file) {
                                setQueuedFile(file);
                                setUploadOpen(true);
                            }
                        }}
                    >
                        <UploadIcon
                            className="mx-auto text-primary"
                            aria-hidden="true"
                        />
                        <span className="font-medium">
                            {copy.drop_file_here}
                        </span>
                        <span className="text-sm text-muted-foreground">
                            {copy.secure_upload_notice}
                        </span>
                    </button>
                )}

                <div className="grid lg:grid-cols-[minmax(15rem,0.32fr)_1fr]">
                    <div className="border-b p-4 lg:border-r lg:border-b-0">
                        <div className="mb-3 flex items-center justify-between gap-3">
                            <h3 className="text-sm font-semibold">
                                {copy.folders}
                            </h3>
                            <Badge variant="secondary">
                                {visibleFolders.length}
                            </Badge>
                        </div>
                        {visibleFolders.length > 0 ? (
                            <div className="grid gap-1">
                                {visibleFolders.map((folder) => (
                                    <Link
                                        key={folder.id}
                                        href={folderHref(folder.id)}
                                        className="group flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        onDragOver={(event) => {
                                            if (canManage) {
                                                event.preventDefault();
                                                event.dataTransfer.dropEffect =
                                                    'move';
                                            }
                                        }}
                                        onDrop={(event) => {
                                            if (canManage) {
                                                moveDroppedDocuments(
                                                    event,
                                                    folder.id,
                                                );
                                            }
                                        }}
                                    >
                                        <FolderIcon
                                            className="size-5 shrink-0 text-primary"
                                            aria-hidden="true"
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate font-medium">
                                                {folder.name}
                                            </span>
                                            {folder.county ? (
                                                <CountyIdentity
                                                    county={folder.county}
                                                    compact
                                                />
                                            ) : (
                                                <span className="block truncate text-xs text-muted-foreground">
                                                    {copy.national_scope}
                                                </span>
                                            )}
                                        </span>
                                        <Badge variant="outline">
                                            {folder.documentCount}
                                        </Badge>
                                    </Link>
                                ))}
                            </div>
                        ) : (
                            <p className="py-5 text-sm text-muted-foreground">
                                {copy.no_folders}
                            </p>
                        )}
                    </div>
                    <div className="flex min-h-48 items-center justify-center p-6">
                        {currentFolder ? (
                            <div className="w-full text-center">
                                <FolderOpenIcon
                                    className="mx-auto size-10 text-primary"
                                    aria-hidden="true"
                                />
                                <h3 className="mt-3 text-lg font-semibold">
                                    {currentFolder.name}
                                </h3>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {interpolate(copy.files_count, {
                                        count: fileCount,
                                    })}
                                    {' · '}
                                    {interpolate(copy.storage_used, {
                                        size: formatBytes(
                                            repository.storageBytes,
                                        ),
                                    })}
                                </p>
                            </div>
                        ) : (
                            <Empty className="border-0">
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <FolderOpenIcon aria-hidden="true" />
                                    </EmptyMedia>
                                    <EmptyTitle>{copy.all_files}</EmptyTitle>
                                    <EmptyDescription>
                                        {copy.upload_description}
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function FolderCreateSheet({
    folders,
    scopes,
    currentFolder,
}: {
    folders: RepositoryFolder[];
    scopes: Array<{ id: string; name: string }>;
    currentFolder?: RepositoryFolder;
}) {
    const copy = usePage().props.localization.documentRepository;
    const [open, setOpen] = useState(false);
    const [parentId, setParentId] = useState(currentFolder?.id ?? '');
    const [scopeId, setScopeId] = useState(currentFolder?.countyId ?? '');

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <Button type="button" variant="outline">
                    <FolderPlusIcon aria-hidden="true" />
                    {copy.new_folder}
                </Button>
            </SheetTrigger>
            <SheetContent className="overflow-y-auto sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>{copy.create_folder}</SheetTitle>
                    <SheetDescription>{copy.description}</SheetDescription>
                </SheetHeader>
                <Form
                    {...storeFolder.form()}
                    className="grid gap-5 px-4 pb-6"
                    onSuccess={() => setOpen(false)}
                    resetOnSuccess
                >
                    {({ processing, errors }) => (
                        <>
                            <SearchableSelect
                                id="repository-parent-folder"
                                name="parent_id"
                                label={copy.parent_folder}
                                options={folders.map((folder) => ({
                                    id: folder.id,
                                    name: `${folder.county?.name ?? copy.national_scope} · ${folder.name}`,
                                }))}
                                optional
                                value={parentId}
                                onValueChange={setParentId}
                                error={errors.parent_id}
                            />
                            {!parentId && (
                                <SearchableSelect
                                    id="repository-county-scope"
                                    label={copy.county_scope}
                                    options={scopes.map((scope) => ({
                                        id:
                                            scope.id === ''
                                                ? 'national'
                                                : scope.id,
                                        name: scope.name,
                                    }))}
                                    value={
                                        scopeId === '' ? 'national' : scopeId
                                    }
                                    onValueChange={(value) =>
                                        setScopeId(
                                            value === 'national' ? '' : value,
                                        )
                                    }
                                    error={errors.county_id}
                                />
                            )}
                            <input
                                type="hidden"
                                name="county_id"
                                value={parentId ? '' : scopeId}
                            />
                            <div className="grid gap-2">
                                <Label htmlFor="repository-folder-name">
                                    {copy.folder_name}
                                </Label>
                                <Input
                                    id="repository-folder-name"
                                    name="name"
                                    required
                                    maxLength={120}
                                    aria-invalid={Boolean(errors.name)}
                                />
                                <InputError message={errors.name} />
                            </div>
                            <Button
                                type="submit"
                                disabled={processing}
                                aria-busy={processing}
                            >
                                {copy.create_folder}
                            </Button>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}

function RepositoryUploadSheet({
    folders,
    currentFolderId,
    open,
    onOpenChange,
    queuedFile,
    onQueuedFileChange,
}: {
    folders: RepositoryFolder[];
    currentFolderId: string | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    queuedFile: File | null;
    onQueuedFileChange: (file: File | null) => void;
}) {
    const copy = usePage().props.localization.documentRepository;
    const [dragActive, setDragActive] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (!open || !queuedFile || !fileInputRef.current) {
            return;
        }

        const transfer = new DataTransfer();
        transfer.items.add(queuedFile);
        fileInputRef.current.files = transfer.files;
    }, [open, queuedFile]);

    return (
        <Sheet
            open={open}
            onOpenChange={(nextOpen) => {
                onOpenChange(nextOpen);

                if (!nextOpen) {
                    onQueuedFileChange(null);
                }
            }}
        >
            <SheetTrigger asChild>
                <Button type="button">
                    <UploadIcon aria-hidden="true" />
                    {copy.upload_file}
                </Button>
            </SheetTrigger>
            <SheetContent className="overflow-y-auto sm:max-w-xl">
                <SheetHeader>
                    <SheetTitle>{copy.upload_file}</SheetTitle>
                    <SheetDescription>
                        {copy.upload_description}
                    </SheetDescription>
                </SheetHeader>
                <Form
                    {...storeDocument.form()}
                    className="grid gap-5 px-4 pb-6"
                    onSuccess={() => {
                        onQueuedFileChange(null);
                        onOpenChange(false);
                    }}
                    resetOnSuccess
                >
                    {({ processing, errors, progress }) => (
                        <>
                            <SearchableSelect
                                id="repository-upload-folder"
                                name="folder_id"
                                label={copy.destination_folder}
                                options={folders.map((folder) => ({
                                    id: folder.id,
                                    name: `${folder.county?.name ?? copy.national_scope} · ${folder.name}`,
                                }))}
                                defaultValue={currentFolderId ?? ''}
                                error={errors.folder_id}
                            />
                            <div className="grid gap-2">
                                <Label htmlFor="repository-document-title">
                                    {copy.document_title}
                                </Label>
                                <Input
                                    id="repository-document-title"
                                    name="title"
                                    required
                                    maxLength={255}
                                    defaultValue={queuedFile?.name.replace(
                                        /\.[^.]+$/,
                                        '',
                                    )}
                                    aria-invalid={Boolean(errors.title)}
                                />
                                <InputError message={errors.title} />
                            </div>
                            <div className="grid gap-2 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="repository-category">
                                        {copy.category}
                                    </Label>
                                    <Input
                                        id="repository-category"
                                        name="category"
                                        required
                                        maxLength={100}
                                        aria-invalid={Boolean(errors.category)}
                                    />
                                    <InputError message={errors.category} />
                                </div>
                                <SearchableSelect
                                    id="repository-source-type"
                                    name="source_type"
                                    label={copy.source_type}
                                    options={[
                                        {
                                            id: 'digital',
                                            name: copy.digital_file,
                                        },
                                        {
                                            id: 'scanned',
                                            name: copy.scanned_copy,
                                        },
                                    ]}
                                    defaultValue="digital"
                                    error={errors.source_type}
                                />
                            </div>
                            <DatePickerField
                                name="document_date"
                                label={copy.document_date}
                                error={errors.document_date}
                            />
                            <div className="grid gap-2">
                                <Label htmlFor="repository-description">
                                    {copy.description_label}
                                </Label>
                                <Textarea
                                    id="repository-description"
                                    name="description"
                                    maxLength={5000}
                                    aria-invalid={Boolean(errors.description)}
                                />
                                <InputError message={errors.description} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="repository-tags">
                                    {copy.tags}
                                </Label>
                                <Input
                                    id="repository-tags"
                                    name="tags"
                                    placeholder={copy.tags_placeholder}
                                    maxLength={1000}
                                    aria-invalid={Boolean(errors.tags)}
                                />
                                <InputError message={errors.tags} />
                            </div>
                            <div
                                className="grid gap-3 rounded-lg border border-dashed p-5 text-center data-[active=true]:border-primary data-[active=true]:bg-accent/50"
                                data-active={dragActive}
                                onDragEnter={(event) => {
                                    event.preventDefault();
                                    setDragActive(true);
                                }}
                                onDragOver={(event) => event.preventDefault()}
                                onDragLeave={(event) => {
                                    if (
                                        !event.currentTarget.contains(
                                            event.relatedTarget as Node,
                                        )
                                    ) {
                                        setDragActive(false);
                                    }
                                }}
                                onDrop={(event) => {
                                    event.preventDefault();
                                    setDragActive(false);

                                    const file =
                                        event.dataTransfer.files.item(0);

                                    if (!file || !fileInputRef.current) {
                                        return;
                                    }

                                    const transfer = new DataTransfer();
                                    transfer.items.add(file);
                                    fileInputRef.current.files = transfer.files;
                                    onQueuedFileChange(file);
                                }}
                            >
                                <UploadIcon
                                    className="mx-auto text-primary"
                                    aria-hidden="true"
                                />
                                <div>
                                    <p className="font-medium">
                                        {copy.drop_file_here}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {queuedFile?.name ??
                                            copy.secure_upload_notice}
                                    </p>
                                </div>
                                <Input
                                    ref={fileInputRef}
                                    id="repository-file"
                                    name="document"
                                    type="file"
                                    required
                                    accept=".pdf,.jpg,.jpeg,.png,.webp,.tif,.tiff,.doc,.docx,.xls,.xlsx,.csv,.txt,.mp3,.mp4,.webm,.ogg,.wav"
                                    aria-invalid={Boolean(errors.document)}
                                    onChange={(event) =>
                                        onQueuedFileChange(
                                            event.currentTarget.files?.item(
                                                0,
                                            ) ?? null,
                                        )
                                    }
                                />
                                <InputError message={errors.document} />
                            </div>
                            <Button
                                type="submit"
                                disabled={processing}
                                aria-busy={processing}
                            >
                                <UploadIcon aria-hidden="true" />
                                {progress
                                    ? interpolate(copy.uploading, {
                                          percentage: progress.percentage ?? 0,
                                      })
                                    : copy.upload_file}
                            </Button>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}

function FolderManageSheet({
    folder,
    folders,
}: {
    folder: RepositoryFolder;
    folders: RepositoryFolder[];
}) {
    const copy = usePage().props.localization.documentRepository;
    const [open, setOpen] = useState(false);

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <Button type="button" variant="outline">
                    <FolderPenIcon aria-hidden="true" />
                    {copy.edit_folder}
                </Button>
            </SheetTrigger>
            <SheetContent className="overflow-y-auto sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>{copy.edit_folder}</SheetTitle>
                    <SheetDescription>
                        {copy.delete_description}
                    </SheetDescription>
                </SheetHeader>
                <Form
                    {...update.form(folder.id)}
                    className="grid gap-5 px-4"
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <SearchableSelect
                                id="repository-edit-parent"
                                name="parent_id"
                                label={copy.parent_folder}
                                options={folders
                                    .filter(
                                        (candidate) =>
                                            candidate.id !== folder.id &&
                                            candidate.countyId ===
                                                folder.countyId,
                                    )
                                    .map((candidate) => ({
                                        id: candidate.id,
                                        name: candidate.name,
                                    }))}
                                optional
                                defaultValue={folder.parentId ?? ''}
                                error={errors.parent_id}
                            />
                            <div className="grid gap-2">
                                <Label htmlFor="repository-edit-name">
                                    {copy.folder_name}
                                </Label>
                                <Input
                                    id="repository-edit-name"
                                    name="name"
                                    defaultValue={folder.name}
                                    required
                                    maxLength={120}
                                    aria-invalid={Boolean(errors.name)}
                                />
                                <InputError message={errors.name} />
                            </div>
                            <Button
                                type="submit"
                                disabled={processing}
                                aria-busy={processing}
                            >
                                {copy.save_folder}
                            </Button>
                        </>
                    )}
                </Form>
                <div className="mx-4 border-t pt-5">
                    <Form {...destroy.form(folder.id)}>
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                                aria-busy={processing}
                            >
                                <Trash2Icon aria-hidden="true" />
                                {copy.confirm_delete_folder}
                            </Button>
                        )}
                    </Form>
                </div>
            </SheetContent>
        </Sheet>
    );
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const units = ['KB', 'MB', 'GB', 'TB'];
    const exponent = Math.min(
        Math.floor(Math.log(bytes) / Math.log(1024)),
        units.length,
    );

    return `${(bytes / 1024 ** exponent).toFixed(1)} ${units[exponent - 1]}`;
}
