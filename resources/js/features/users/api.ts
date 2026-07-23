import { api } from '@/lib/api';

const base = '/api/v1';

export interface UserBranch {
    id: number;
    name: string;
    is_default: boolean;
}
export interface UserRow {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    status: boolean;
    must_reset_password: boolean;
    role: string | null;
    branches: UserBranch[];
}
export interface UserPayload {
    name: string;
    email: string;
    phone?: string | null;
    status?: boolean;
    branch_ids: number[];
    default_branch_id?: number | null;
    role: string;
}

export async function listUsers(): Promise<UserRow[]> {
    const { data } = await api.get(`${base}/users`);
    return data.data as UserRow[];
}
export async function createUser(payload: UserPayload): Promise<UserRow & { temporary_password: string }> {
    const { data } = await api.post(`${base}/users`, payload);
    return data.data;
}
export async function updateUser(id: number, payload: Partial<UserPayload>): Promise<UserRow> {
    const { data } = await api.put(`${base}/users/${id}`, payload);
    return data.data as UserRow;
}
export async function deactivateUser(id: number): Promise<UserRow> {
    const { data } = await api.post(`${base}/users/${id}/deactivate`);
    return data.data as UserRow;
}
export async function forceResetUser(id: number): Promise<void> {
    await api.post(`${base}/users/${id}/force-reset`);
}

export interface RoleRow {
    id: number;
    name: string;
    permissions: string[];
}
export async function listRoles(): Promise<RoleRow[]> {
    const { data } = await api.get(`${base}/roles`);
    return data.data as RoleRow[];
}
export async function listPermissions(): Promise<string[]> {
    const { data } = await api.get(`${base}/roles/permissions`);
    return data.data as string[];
}
export async function updateRolePermissions(id: number, permissions: string[]): Promise<RoleRow> {
    const { data } = await api.put(`${base}/roles/${id}/permissions`, { permissions });
    return data.data as RoleRow;
}

export interface SessionRow {
    id: string;
    ip_address: string | null;
    user_agent: string | null;
    last_active: string;
    is_current: boolean;
}
export async function listSessions(): Promise<SessionRow[]> {
    const { data } = await api.get(`${base}/auth/sessions`);
    return data.data as SessionRow[];
}
export async function revokeSession(id: string): Promise<void> {
    await api.delete(`${base}/auth/sessions/${id}`);
}
export async function changePassword(payload: { current_password: string; password: string; password_confirmation: string }): Promise<void> {
    await api.post(`${base}/auth/change-password`, payload);
}
