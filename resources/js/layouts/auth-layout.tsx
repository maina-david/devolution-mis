import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({
    name,
    title = '',
    description = '',
    children,
}: {
    name?: string;
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <AuthLayoutTemplate name={name} title={title} description={description}>
            {children}
        </AuthLayoutTemplate>
    );
}
