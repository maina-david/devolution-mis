import { Form, Head, Link, router } from '@inertiajs/react';
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
            <Head title="Roles and permissions" />
            <main className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <p>Identity and access governance</p>
                    <h1>Roles & permissions</h1>
                    <p>
                        Govern role inheritance and exceptional direct user
                        permissions from one audited, least-privilege control
                        plane.
                    </p>
                </section>

                <div className="grid gap-4 md:grid-cols-3">
                    <Metric
                        icon={ShieldCheck}
                        label="Programme roles"
                        value={roles.length}
                    />
                    <Metric
                        icon={KeyRound}
                        label="Permission definitions"
                        value={permissions.length}
                    />
                    <Metric
                        icon={Users}
                        label="Users in register"
                        value={users.total}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Role permission matrix</CardTitle>
                        <CardDescription>
                            Role permissions are inherited by every user
                            assigned to that role. Changes are audited.
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
                                            {role.userCount} users ·{' '}
                                            {role.permissions.length}{' '}
                                            permissions
                                        </p>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setSelectedRole(role)}
                                    >
                                        <Pencil data-icon="inline-start" /> Edit
                                    </Button>
                                </div>
                                <p className="line-clamp-3 text-sm text-muted-foreground">
                                    {role.permissions
                                        .map(humanize)
                                        .join(', ') ||
                                        'No inherited permissions'}
                                </p>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Direct permission exceptions</CardTitle>
                        <CardDescription>
                            Direct grants are explicit exceptions layered over
                            the role. Use them sparingly and record a reason.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <Input
                            defaultValue={filters.search}
                            onChange={(event) => search(event.target.value)}
                            placeholder="Search users by name or email"
                            aria-label="Search users"
                        />
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-16">
                                            No.
                                        </TableHead>
                                        <TableHead>User</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead>Inherited</TableHead>
                                        <TableHead>Direct</TableHead>
                                        <TableHead>Effective</TableHead>
                                        <TableHead className="w-16">
                                            <span className="sr-only">
                                                Actions
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
                                                            aria-label={`Actions for ${user.name}`}
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
                                                            Manage direct
                                                            permissions
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
                                                    title="No users found"
                                                    description="No authorized users match the active search."
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ) : null}
                                </TableBody>
                            </Table>
                        </div>
                        <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground">
                            <span>
                                Showing {users.from ?? 0}–{users.to ?? 0} of{' '}
                                {users.total}
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
                    selectedRole ? `Edit ${selectedRole.label}` : 'Edit role'
                }
                description="Select the complete inherited permission set for this role."
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
                        ? `Direct permissions · ${selectedUser.name}`
                        : 'Direct permissions'
                }
                description="Select only exceptional permissions. Role-inherited permissions remain unchanged."
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
                                        <FieldLabel>Permissions</FieldLabel>
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
                                                                ? ' · inherited by role'
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
                                            Business reason
                                        </FieldLabel>
                                        <Textarea
                                            id="permission-change-reason"
                                            name="reason"
                                            required
                                            minLength={20}
                                            placeholder="Record the approved operational reason for this access change."
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
                                        Save permission changes
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

AccessControl.layout = (props: {
    routeContext?: { key: any; slug: any } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Roles & permissions',
            href: props.routeContext ? index() : '/',
        },
    ],
});
