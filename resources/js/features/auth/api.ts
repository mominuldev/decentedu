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

export async function forgotPassword(email: string): Promise<void> {
    await csrf();
    await api.post('/api/v1/auth/forgot-password', { email });
}

export async function resetPassword(payload: { token: string; email: string; password: string; password_confirmation: string }): Promise<void> {
    await csrf();
    await api.post('/api/v1/auth/reset-password', payload);
}
