export type TeamRole = 'owner' | 'admin' | 'member';

export type Team = {
    id: string;
    name: string;
    slug: string;
    isPersonal: boolean;
    role?: TeamRole;
    roleLabel?: string;
    isCurrent?: boolean;
};
