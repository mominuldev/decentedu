import { api, csrf } from '@/lib/api';
import type { Session } from './types';

export async function login(email: string, password: string, remember: boolean): Promise<Session> {
    await csrf();
    const { data } = await api.post('/api/v1/auth/login', { email, password, remember });
    return data.data as Session;
}

export async function fetchMe(): Promise<Session> {
    const { data } = await api.get('/api/v1/auth/me');
    return data.data as Session;
}

export async function logout(): Promise<void> {
    await api.post('/api/v1/auth/logout');
}

export async function switchBranch(branchId: number): Promise<Session> {
    const { data } = await api.post('/api/v1/branch/switch', { branch_id: branchId });
    return data.data as Session;
}
