import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { Ellipsis, KeyRound, Pencil, ShieldCheck, Users } from 'lucide-react';
import { useState } from 'react';
import TableEmptyState from '@/components/table-empty-state';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { interpolate } from '@/hooks/use-localization';
import { index } from '@/routes/access-control';
import { update as updateRole } from '@/routes/access-control/roles';
import { update as updateUserPermissions } from '@/routes/access-control/user-permissions';

type PermissionOption = { value: string; label: string; group: string };
type Role = {
    id: string;
    name: string;
    label: string;
    userCount: number;
    permissions: string[];
};
type User = {
    id: string;
    name: string;
    email: string;
    role: string;
    rolePermissions: string[];
    directPermissions: string[];
    effectivePermissions: string[];
};
type PaginationLink = { url: string | null; label: string; active: boolean };

function useAccessControlCopy(): Record<string, string> {
    return usePage().props.localization.accessControl;
}

export default function AccessControl({
    roles,
    permissions,
    users,
    filters,
}: {
    roles: Role[];
    permissions: PermissionOption[];
    users: {
        data: User[];
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
        from: number | null;
        to: number | null;
        links: PaginationLink[];
    };
    filters: { search: string; per_page: number };
}) {
    const copy = useAccessControlCopy();
    const [selectedRole, setSelectedRole] = useState<Role | null>(null);
    const [selectedUser, setSelectedUser] = useState<User | null>(null);

    const search = (value: string) => {
        router.get(
            index.url(),
            { search: value, per_page: filters.per_page },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title={copy.head_title} />
            <main className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <p>{copy.eyebrow}</p>
                    <h1>{copy.title}</h1>
                    <p>{copy.description}</p>
                </section>

                <div className="grid gap-4 md:grid-cols-3">
                    <Metric
                        icon={ShieldCheck}
                        label={copy.programme_roles}
                        value={roles.length}
                    />
                    <Metric
                        icon={KeyRound}
                        label={copy.permission_definitions}
                        value={permissions.length}
                    />
                    <Metric
                        icon={Users}
                        label={copy.users_in_register}
                        value={users.total}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{copy.role_matrix}</CardTitle>
                        <CardDescription>
                            {copy.role_matrix_description}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {roles.map((role) => (
                            <div
                                key={role.id}
                                className="flex flex-col gap-4 rounded-lg border p-4"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <h2 className="font-medium text-foreground">
                                            {role.label}
                                        </h2>
                                        <p className="text-sm text-muted-foreground">
                                            {role.userCount} {copy.users}{' '}
                                            {copy.separator}{' '}
                                            {role.permissions.length}{' '}
                                            {copy.permissions}
                                        </p>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setSelectedRole(role)}
                                    >
                                        <Pencil data-icon="inline-start" />{' '}
                                        {copy.edit}
                                    </Button>
                                </div>
                                <p className="line-clamp-3 text-sm text-muted-foreground">
                                    {role.permissions
                                        .map(humanize)
                                        .join(', ') ||
                                        copy.no_inherited_permissions}
                                </p>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{copy.direct_exceptions}</CardTitle>
                        <CardDescription>
                            {copy.direct_exceptions_description}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <Input
                            defaultValue={filters.search}
                            onChange={(event) => search(event.target.value)}
                            placeholder={copy.search_placeholder}
                            aria-label={copy.search_users}
                        />
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-16">
                                            {copy.number}
                                        </TableHead>
                                        <TableHead>{copy.user}</TableHead>
                                        <TableHead>{copy.role}</TableHead>
                                        <TableHead>{copy.inherited}</TableHead>
                                        <TableHead>{copy.direct}</TableHead>
                                        <TableHead>{copy.effective}</TableHead>
                                        <TableHead className="w-16">
                                            <span className="sr-only">
                                                {copy.actions}
                                            </span>
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {users.data.map((user, rowIndex) => (
                                        <TableRow key={user.id}>
                                            <TableCell>
                                                {(users.currentPage - 1) *
                                                    users.perPage +
                                                    rowIndex +
                                                    1}
                                            </TableCell>
                                            <TableCell>
                                                <p className="font-medium text-foreground">
                                                    {user.name}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {user.email}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                {humanize(user.role)}
                                            </TableCell>
                                            <TableCell>
                                                {user.rolePermissions.length}
                                            </TableCell>
                                            <TableCell>
                                                {user.directPermissions.length}
                                            </TableCell>
                                            <TableCell>
                                                {
                                                    user.effectivePermissions
                                                        .length
                                                }
                                            </TableCell>
                                            <TableCell>
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={interpolate(
                                                                copy.actions_for_user,
                                                                {
                                                                    user: user.name,
                                                                },
                                                            )}
                                                        >
                                                            <Ellipsis />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem
                                                            onSelect={() =>
                                                                setSelectedUser(
                                                                    user,
                                                                )
                                                            }
                                                        >
                                                            {
                                                                copy.manage_direct_permissions
                                                            }
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {users.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={7}>
                                                <TableEmptyState
                                                    title={copy.no_users}
                                                    description={
                                                        copy.no_users_description
                                                    }
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ) : null}
                                </TableBody>
                            </Table>
                        </div>
                        <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground">
                            <span>
                                {copy.showing} {users.from ?? 0}
                                {copy.range_separator}
                                {users.to ?? 0} {copy.of} {users.total}
                            </span>
                            <div className="flex gap-1">
                                {users.links.map((link, linkIndex) =>
                                    link.url ? (
                                        <Button
                                            key={`${link.label}-${linkIndex}`}
                                            asChild
                                            size="sm"
                                            variant={
                                                link.active
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                        >
                                            <Link
                                                href={link.url}
                                                preserveScroll
                                                preserveState
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                            />
                                        </Button>
                                    ) : null,
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </main>

            <PermissionSheet
                open={selectedRole !== null}
                onOpenChange={(open) => !open && setSelectedRole(null)}
                title={
                    selectedRole
                        ? `${copy.edit} ${selectedRole.label}`
                        : copy.edit_role
                }
                description={copy.role_sheet_description}
                action={
                    selectedRole
                        ? updateRole.form({ role: selectedRole.name })
                        : undefined
                }
                permissions={permissions}
                defaults={selectedRole?.permissions ?? []}
            />
            <PermissionSheet
                open={selectedUser !== null}
                onOpenChange={(open) => !open && setSelectedUser(null)}
                title={
                    selectedUser
                        ? `${copy.direct_permissions} ${copy.separator} ${selectedUser.name}`
                        : copy.direct_permissions
                }
                description={copy.user_sheet_description}
                action={
                    selectedUser
                        ? updateUserPermissions.form({
                              programmeUser: selectedUser.id,
                          })
                        : undefined
                }
                permissions={permissions}
                defaults={selectedUser?.directPermissions ?? []}
                inherited={selectedUser?.rolePermissions ?? []}
            />
        </>
    );
}

function PermissionSheet({
    open,
    onOpenChange,
    title,
    description,
    action,
    permissions,
    defaults,
    inherited = [],
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    action?:
        | ReturnType<typeof updateRole.form>
        | ReturnType<typeof updateUserPermissions.form>;
    permissions: PermissionOption[];
    defaults: string[];
    inherited?: string[];
}) {
    const copy = useAccessControlCopy();

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="overflow-y-auto sm:max-w-2xl">
                <SheetHeader>
                    <SheetTitle>{title}</SheetTitle>
                    <SheetDescription>{description}</SheetDescription>
                </SheetHeader>
                {action ? (
                    <Form
                        {...action}
                        options={{ preserveScroll: true }}
                        onSuccess={() => onOpenChange(false)}
                        className="flex min-h-0 flex-1 flex-col"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="flex flex-col gap-6 px-4">
                                    <Field
                                        data-invalid={Boolean(
                                            errors.permissions,
                                        )}
                                    >
                                        <FieldLabel>
                                            {copy.permissions}
                                        </FieldLabel>
                                        <div className="grid gap-3 rounded-lg border p-3 sm:grid-cols-2">
                                            {permissions.map((permission) => (
                                                <label
                                                    key={permission.value}
                                                    className="flex items-start gap-3 rounded-md p-2 hover:bg-muted"
                                                >
                                                    <Checkbox
                                                        name="permissions[]"
                                                        value={permission.value}
                                                        defaultChecked={defaults.includes(
                                                            permission.value,
                                                        )}
                                                    />
                                                    <span>
                                                        <span className="block text-sm font-medium text-foreground">
                                                            {permission.label}
                                                        </span>
                                                        <span className="block text-xs text-muted-foreground">
                                                            {permission.group}
                                                            {inherited.includes(
                                                                permission.value,
                                                            )
                                                                ? ` ${copy.separator} ${copy.inherited_by_role}`
                                                                : ''}
                                                        </span>
                                                    </span>
                                                </label>
                                            ))}
                                        </div>
                                        <FieldError>
                                            {errors.permissions}
                                        </FieldError>
                                    </Field>
                                    <Field
                                        data-invalid={Boolean(errors.reason)}
                                    >
                                        <FieldLabel htmlFor="permission-change-reason">
                                            {copy.business_reason}
                                        </FieldLabel>
                                        <Textarea
                                            id="permission-change-reason"
                                            name="reason"
                                            required
                                            minLength={20}
                                            placeholder={
                                                copy.reason_placeholder
                                            }
                                        />
                                        <FieldError>{errors.reason}</FieldError>
                                    </Field>
                                </div>
                                <SheetFooter>
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        aria-busy={processing}
                                    >
                                        {copy.save_changes}
                                    </Button>
                                </SheetFooter>
                            </>
                        )}
                    </Form>
                ) : null}
            </SheetContent>
        </Sheet>
    );
}

function Metric({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof ShieldCheck;
    label: string;
    value: number;
}) {
    return (
        <Card>
            <CardContent className="flex items-start justify-between p-5">
                <div>
                    <p className="text-sm font-medium text-muted-foreground">
                        {label}
                    </p>
                    <p className="mt-2 text-3xl font-bold text-foreground">
                        {value.toLocaleString()}
                    </p>
                </div>
                <span className="rounded-lg bg-primary/10 p-2 text-primary">
                    <Icon aria-hidden="true" />
                </span>
            </CardContent>
        </Card>
    );
}

function humanize(value: string): string {
    return value
        .replaceAll(/[-:]/gu, ' ')
        .replace(/\b\w/gu, (letter) => letter.toUpperCase());
}

function AccessControlLayout() {
    const copy = useAccessControlCopy();

    return { breadcrumbs: [{ title: copy.title, href: index() }] };
}

AccessControl.layout = AccessControlLayout;
