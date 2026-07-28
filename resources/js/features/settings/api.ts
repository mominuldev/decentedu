import { api } from '@/lib/api';

const base = '/api/v1';

export interface BranchSettingsConfig {
    timezone: string;
    currency_symbol: string;
    date_format: string;
    sms_sender_id: string;
    header_notice: string | null;
    auto_student_id: boolean;
}

export interface BranchSettingsRow {
    id: number;
    organization_id: number;
    name: string;
    name_bn: string | null;
    code: string | null;
    address: string | null;
    phone: string | null;
    email: string | null;
    logo_path: string | null;
    status: boolean;
    settings: BranchSettingsConfig;
}

export interface BranchSettingsPayload {
    name: string;
    name_bn?: string | null;
    code?: string | null;
    address?: string | null;
    phone?: string | null;
    email?: string | null;
    logo_path?: string | null;
    settings?: Partial<BranchSettingsConfig>;
}

export interface SystemSettingsData {
    php_version: string;
    laravel_version: string;
    db_driver: string;
    cache_driver: string;
    session_driver: string;
    environment: string;
    server_time: string;
    timezone: string;
    active_branch: {
        id: number | null;
        name: string | null;
        code: string | null;
    };
    counts: {
        students: number;
        employees: number;
        users: number;
    };
}

export interface ProfilePayload {
    name: string;
    email: string;
    phone?: string | null;
    avatar_path?: string | null;
}

// ---- Branch management (org-level) ----

export interface BranchRow {
    id: number;
    organization_id: number;
    name: string;
    name_bn: string | null;
    code: string | null;
    address: string | null;
    phone: string | null;
    email: string | null;
    logo_path: string | null;
    status: boolean;
}

export interface BranchPayload {
    name: string;
    name_bn?: string | null;
    code?: string | null;
    address?: string | null;
    phone?: string | null;
    email?: string | null;
    logo_path?: string | null;
    status?: boolean;
}

export async function fetchBranchSettings(): Promise<BranchSettingsRow> {
    const { data } = await api.get(`${base}/settings/branch`);
    return data.data as BranchSettingsRow;
}

export async function updateBranchSettings(payload: BranchSettingsPayload): Promise<BranchSettingsRow> {
    const { data } = await api.put(`${base}/settings/branch`, payload);
    return data.data as BranchSettingsRow;
}

export async function fetchSystemSettings(): Promise<SystemSettingsData> {
    const { data } = await api.get(`${base}/settings/system`);
    return data.data as SystemSettingsData;
}

export async function updateProfile(payload: ProfilePayload): Promise<any> {
    const { data } = await api.put(`${base}/settings/profile`, payload);
    return data.data;
}

export async function listBranches(): Promise<BranchRow[]> {
    const { data } = await api.get(`${base}/settings/branches`);
    return data.data as BranchRow[];
}

export async function createBranch(payload: BranchPayload): Promise<BranchRow> {
    const { data } = await api.post(`${base}/settings/branches`, payload);
    return data.data as BranchRow;
}

export async function updateBranch(id: number, payload: BranchPayload): Promise<BranchRow> {
    const { data } = await api.put(`${base}/settings/branches/${id}`, payload);
    return data.data as BranchRow;
}
