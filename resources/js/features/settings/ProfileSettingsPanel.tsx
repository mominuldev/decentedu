import { useState, useEffect } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Loader2, User as UserIcon, Save } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Toast, type ToastState } from '@/components/Toast';
import { cn } from '@/lib/cn';
import { toApiError } from '@/lib/api';
import { useAuth } from '@/features/auth/AuthProvider';
import { SessionsPanel } from '@/features/users/SessionsPanel';
import { updateProfile, type ProfilePayload } from './api';

export function ProfileSettingsPanel() {
    const { session, refresh } = useAuth();
    const user = session?.user;
    const [toast, setToast] = useState<ToastState | null>(null);

    const [form, setForm] = useState<ProfilePayload>({
        name: user?.name ?? '',
        email: user?.email ?? '',
        phone: user?.phone ?? '',
        avatar_path: user?.avatar_path ?? '',
    });

    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [globalError, setGlobalError] = useState<string | null>(null);

    useEffect(() => {
        if (user) {
            setForm({
                name: user.name ?? '',
                email: user.email ?? '',
                phone: user.phone ?? '',
                avatar_path: user.avatar_path ?? '',
            });
        }
    }, [user]);

    const profileMutation = useMutation({
        mutationFn: () => updateProfile(form),
        onSuccess: () => {
            setGlobalError(null);
            setErrors({});
            setToast({ tone: 'success', message: 'Profile updated successfully.' });
            refresh();
        },
        onError: (e) => {
            const err = toApiError(e);
            setGlobalError(err.errors ? null : err.message);
            setErrors(err.errors ?? {});
            setToast({ tone: 'error', message: err.message || 'Failed to update profile.' });
        },
    });

    const inputCls = (hasError?: boolean) =>
        cn(
            'w-full rounded-xl border bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none placeholder:text-faint focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25',
            hasError ? 'border-rose-400' : 'border-border-strong',
        );

    const labelCls = 'mb-1.5 block text-[13px] font-medium text-fg';

    return (
        <div className="space-y-6">
            <Toast toast={toast} onClose={() => setToast(null)} />

            {globalError && (
                <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-[13.5px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">
                    {globalError}
                </div>
            )}

            {/* Profile Info Form */}
            <Card>
                <div className="flex items-center gap-3 px-5 py-4 border-b border-border">
                    <div className="grid h-9 w-9 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                        <UserIcon size={18} />
                    </div>
                    <div>
                        <h3 className="text-[15px] font-semibold text-fg">Personal Profile</h3>
                        <p className="text-[12.5px] text-muted">Update your display name, contact email, and profile avatar.</p>
                    </div>
                </div>

                <div className="grid gap-4 px-5 py-5 sm:grid-cols-2">
                    <div>
                        <label className={labelCls}>Full Name *</label>
                        <input
                            type="text"
                            value={form.name}
                            onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                            className={inputCls(!!errors.name)}
                            placeholder="John Doe"
                        />
                        {errors.name && <p className="mt-1 text-[12px] text-rose-500">{errors.name[0]}</p>}
                    </div>

                    <div>
                        <label className={labelCls}>Email Address *</label>
                        <input
                            type="email"
                            value={form.email}
                            onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
                            className={inputCls(!!errors.email)}
                            placeholder="admin@school.edu"
                        />
                        {errors.email && <p className="mt-1 text-[12px] text-rose-500">{errors.email[0]}</p>}
                    </div>

                    <div>
                        <label className={labelCls}>Phone Number</label>
                        <input
                            type="text"
                            value={form.phone ?? ''}
                            onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))}
                            className={inputCls(!!errors.phone)}
                            placeholder="+880 1700-000000"
                        />
                        {errors.phone && <p className="mt-1 text-[12px] text-rose-500">{errors.phone[0]}</p>}
                    </div>

                    <div>
                        <label className={labelCls}>Avatar Image URL</label>
                        <input
                            type="text"
                            value={form.avatar_path ?? ''}
                            onChange={(e) => setForm((f) => ({ ...f, avatar_path: e.target.value }))}
                            className={inputCls(!!errors.avatar_path)}
                            placeholder="/avatars/user.jpg"
                        />
                    </div>
                </div>

                <div className="flex items-center justify-between border-t border-border px-5 py-3.5">
                    {profileMutation.isSuccess ? <Badge tone="success">Profile updated</Badge> : <span />}
                    <Button onClick={() => profileMutation.mutate()} disabled={profileMutation.isPending}>
                        {profileMutation.isPending ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />}
                        Update Profile
                    </Button>
                </div>
            </Card>

            {/* Password Change and Sessions */}
            <SessionsPanel />
        </div>
    );
}
