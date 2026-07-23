import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2, Monitor, LogOut } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { toApiError } from '@/lib/api';
import { cn } from '@/lib/cn';
import { useAuth } from '@/features/auth/AuthProvider';
import { listSessions, revokeSession, changePassword } from './api';

export function SessionsPanel() {
    const qc = useQueryClient();
    const { data: sessions = [], isLoading } = useQuery({ queryKey: ['sessions'], queryFn: listSessions });
    const revoke = useMutation({
        mutationFn: (id: string) => revokeSession(id),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['sessions'] }),
    });

    return (
        <div className="space-y-6">
            <ChangePasswordCard />

            <Card>
                <div className="px-5 py-4">
                    <h3 className="text-[15px] font-semibold text-fg">Active sessions</h3>
                    <p className="text-[12.5px] text-muted">Devices currently signed in to your account.</p>
                </div>
                <div className="border-t border-border">
                    {isLoading ? (
                        <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                    ) : (
                        <ul className="divide-y divide-border">
                            {sessions.map((s) => (
                                <li key={s.id} className="flex items-center justify-between gap-3 px-5 py-3.5">
                                    <div className="flex items-center gap-3">
                                        <div className="grid h-9 w-9 place-items-center rounded-xl bg-surface-2 text-faint"><Monitor size={16} /></div>
                                        <div>
                                            <p className="text-[13.5px] font-medium text-fg">{s.ip_address ?? 'Unknown IP'} {s.is_current && <Badge tone="brand" className="ml-1.5">This device</Badge>}</p>
                                            <p className="max-w-[420px] truncate text-[12px] text-faint">{s.user_agent ?? '—'} · last active {s.last_active}</p>
                                        </div>
                                    </div>
                                    {!s.is_current && (
                                        <button onClick={() => revoke.mutate(s.id)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500" aria-label="Revoke session">
                                            <LogOut size={16} />
                                        </button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </Card>
        </div>
    );
}

function ChangePasswordCard() {
    const { refresh } = useAuth();
    const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '' });
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [error, setError] = useState<string | null>(null);

    const save = useMutation({
        mutationFn: () => changePassword(form),
        onSuccess: () => { setForm({ current_password: '', password: '', password_confirmation: '' }); setError(null); setErrors({}); refresh(); },
        onError: (e) => { const a = toApiError(e); setError(a.errors ? null : a.message); setErrors(a.errors ?? {}); },
    });

    const inputCls = (hasError: boolean) => cn(
        'w-full rounded-xl border bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none placeholder:text-faint focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25',
        hasError ? 'border-rose-400' : 'border-border-strong',
    );

    return (
        <Card>
            <div className="px-5 py-4">
                <h3 className="text-[15px] font-semibold text-fg">Change password</h3>
                <p className="text-[12.5px] text-muted">At least 8 characters, mixed case and a number; checked against known breaches.</p>
            </div>
            <div className="grid gap-4 border-t border-border px-5 py-4 sm:grid-cols-3">
                {error && <div className="sm:col-span-3 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Current password</label>
                    <input type="password" value={form.current_password} onChange={(e) => setForm((f) => ({ ...f, current_password: e.target.value }))} className={inputCls(!!errors.current_password)} />
                    {errors.current_password && <p className="mt-1.5 text-[12px] text-rose-500">{errors.current_password[0]}</p>}
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">New password</label>
                    <input type="password" value={form.password} onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))} className={inputCls(!!errors.password)} />
                    {errors.password && <p className="mt-1.5 text-[12px] text-rose-500">{errors.password[0]}</p>}
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Confirm new password</label>
                    <input type="password" value={form.password_confirmation} onChange={(e) => setForm((f) => ({ ...f, password_confirmation: e.target.value }))} className={inputCls(false)} />
                </div>
            </div>
            <div className="flex items-center justify-end gap-2 border-t border-border px-5 py-3.5">
                {save.isSuccess && <Badge tone="success">Password updated</Badge>}
                <Button onClick={() => save.mutate()} disabled={save.isPending}>
                    {save.isPending ? <Loader2 size={16} className="animate-spin" /> : null} Update password
                </Button>
            </div>
        </Card>
    );
}
