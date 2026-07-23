export interface BranchLite {
    id: number;
    name: string;
    name_bn: string | null;
    code: string | null;
    is_default: boolean;
}

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    avatar_path: string | null;
    role: string | null;
    is_super_admin: boolean;
    must_reset_password: boolean;
}

export interface Session {
    user: AuthUser;
    organization: { id: number; name: string } | null;
    active_branch: BranchLite | null;
    branches: BranchLite[];
    permissions: string[];
}
