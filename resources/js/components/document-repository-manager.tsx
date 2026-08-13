import { Form, Link, usePage } from '@inertiajs/react';
import {
    ChevronRightIcon,
    FileArchiveIcon,
    FolderIcon,
    FolderOpenIcon,
    FolderPenIcon,
    FolderPlusIcon,
    Trash2Icon,
    UploadIcon,
} from 'lucide-react';
import { useMemo, useState } from 'react';
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
import { interpolate } from '@/hooks/use-localization';
import { index as evidenceIndex } from '@/routes/evidence';
import { store as storeDocument } from '@/routes/evidence/repository/documents';
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
}: {
    repository: DocumentRepository;
    filters: RepositoryFilters;
    canUpload: boolean;
    canManage: boolean;
}) {
    const copy = usePage().props.localization.documentRepository;
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
}: {
    folders: RepositoryFolder[];
    currentFolderId: string | null;
}) {
    const copy = usePage().props.localization.documentRepository;
    const [open, setOpen] = useState(false);

    return (
        <Sheet open={open} onOpenChange={setOpen}>
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
                    onSuccess={() => setOpen(false)}
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
                            <div className="grid gap-2">
                                <Label htmlFor="repository-file">
                                    {copy.file}
                                </Label>
                                <Input
                                    id="repository-file"
                                    name="document"
                                    type="file"
                                    required
                                    accept=".pdf,.jpg,.jpeg,.png,.webp,.tif,.tiff,.doc,.docx,.xls,.xlsx,.csv,.txt,.mp3,.mp4,.webm,.ogg,.wav"
                                    aria-invalid={Boolean(errors.document)}
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
