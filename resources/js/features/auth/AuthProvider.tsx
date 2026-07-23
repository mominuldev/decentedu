import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react';
import * as authApi from './api';
import type { Session } from './types';

type Status = 'loading' | 'authed' | 'guest';

interface AuthCtx {
    status: Status;
    session: Session | null;
    login: (email: string, password: string, remember: boolean) => Promise<void>;
    logout: () => Promise<void>;
    switchBranch: (branchId: number) => Promise<void>;
    can: (permission: string) => boolean;
}

const Ctx = createContext<AuthCtx | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
    const [status, setStatus] = useState<Status>('loading');
    const [session, setSession] = useState<Session | null>(null);

    useEffect(() => {
        authApi
            .fetchMe()
            .then((s) => { setSession(s); setStatus('authed'); })
            .catch(() => { setSession(null); setStatus('guest'); });
    }, []);

    const login = useCallback(async (email: string, password: string, remember: boolean) => {
        const s = await authApi.login(email, password, remember);
        setSession(s);
        setStatus('authed');
    }, []);

    const logout = useCallback(async () => {
        try { await authApi.logout(); } finally { setSession(null); setStatus('guest'); }
    }, []);

    const switchBranch = useCallback(async (branchId: number) => {
        const s = await authApi.switchBranch(branchId);
        setSession(s);
    }, []);

    const can = useCallback(
        (permission: string) => {
            if (!session) return false;
            if (session.user.is_super_admin || session.permissions.includes('*')) return true;
            return session.permissions.includes(permission);
        },
        [session],
    );

    return <Ctx.Provider value={{ status, session, login, logout, switchBranch, can }}>{children}</Ctx.Provider>;
}

export function useAuth() {
    const ctx = useContext(Ctx);
    if (!ctx) throw new Error('useAuth must be used within AuthProvider');
    return ctx;
}
