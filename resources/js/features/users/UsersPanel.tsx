import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, KeyRound, UserX, Loader2, Inbox } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { cn } from '@/lib/cn';
import { useAuth } from '@/features/auth/AuthProvider';
import {
    listUsers, createUser, updateUser, deactivateUser, forceResetUser,
    listRoles, type UserRow, type UserPayload,
} from './api';

export function UsersPanel() {
    const qc = useQueryClient();
    const { session, can } = useAuth();
    const { data: rows = [], isLoading } = useQuery({ queryKey: ['users'], queryFn: listUsers });
    const { data: roles = [] } = useQuery({ queryKey: ['roles'], queryFn: listRoles });
    const [editing, setEditing] = useState<UserRow | null>(null);
    const [creating, setCreating] = useState(false);
    const [deactivating, setDeactivating] = useState<UserRow | null>(null);
    const [temporaryPassword, setTemporaryPassword] = useState<{ email: string; password: string } | null>(null);

    const invalidate = () => qc.invalidateQueries({ queryKey: ['users'] });
    const deactivate = useMutation({
        mutationFn: (id: number) => deactivateUser(id),
        onSuccess: () => { invalidate(); setDeactivating(null); },
    });
    const forceReset = useMutation({ mutationFn: (id: number) => forceResetUser(id) });

    const manage = can('users.manage');

    return (
        <Card>
            <div className="flex items-center justify-between px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Users</h3>
                    <p className="text-[12.5px] text-muted">{rows.length} user{rows.length === 1 ? '' : 's'} in {session?.active_branch?.name}</p>
                </div>
                {manage && <Button onClick={() => setCreating(true)}><Plus size={16} /> Add user</Button>}
            </div>

            <div className="overflow-x-auto border-t border-border">
                {isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : rows.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                        <div className="grid h-12 w-12 place-items-center rounded-2xl bg-surface-2 text-faint"><Inbox size={22} /></div>
                        <p className="text-[14px] font-medium text-fg">No users yet</p>
                    </div>
                ) : (
                    <table className="w-full min-w-[720px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Name</th>
                                <th className="px-5 py-2.5 font-semibold">Email</th>
                                <th className="px-5 py-2.5 font-semibold">Role</th>
                                <th className="px-5 py-2.5 font-semibold">Status</th>
                                {manage && <th className="px-5 py-2.5 text-right font-semibold">Actions</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={r.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                    <td className="px-5 py-3 font-medium text-fg">{r.name}</td>
                                    <td className="px-5 py-3 text-muted">{r.email}</td>
                                    <td className="px-5 py-3 text-muted">{r.role ?? '—'}</td>
                                    <td className="px-5 py-3">
                                        <Badge tone={r.status ? 'success' : 'neutral'}>{r.status ? 'Active' : 'Inactive'}</Badge>
                                        {r.must_reset_password && <Badge tone="warning" className="ml-1.5">Reset pending</Badge>}
                                    </td>
                                    {manage && (
                                        <td className="px-5 py-3">
                                            <div className="flex justify-end gap-1">
                                                <button onClick={() => setEditing(r)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600" aria-label="Edit"><Pencil size={16} /></button>
                                                <button onClick={() => forceReset.mutate(r.id)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600" aria-label="Force password reset"><KeyRound size={16} /></button>
                                                {r.status && (
                                                    <button onClick={() => setDeactivating(r)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500" aria-label="Deactivate"><UserX size={16} /></button>
                                                )}
                                            </div>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {(creating || editing) && (
                <UserForm
                    row={editing}
                    roles={roles.map((r) => r.name)}
                    branches={session?.branches ?? []}
                    onClose={() => { setCreating(false); setEditing(null); }}
                    onSaved={(created) => {
                        invalidate();
                        setCreating(false);
                        setEditing(null);
                        if (created) setTemporaryPassword(created);
                    }}
                />
            )}

            <ConfirmDialog
                open={!!deactivating} onClose={() => setDeactivating(null)}
                onConfirm={() => deactivating && deactivate.mutate(deactivating.id)} busy={deactivate.isPending}
                title="Deactivate user" message={`"${deactivating?.name}" will no longer be able to sign in. Continue?`}
            />

            <Modal
                open={!!temporaryPassword} onClose={() => setTemporaryPassword(null)} title="Temporary password"
                footer={<Button onClick={() => setTemporaryPassword(null)}>Done</Button>}
            >
                <p className="text-[13.5px] text-muted">
                    Share this with <span className="font-medium text-fg">{temporaryPassword?.email}</span> securely — it will not be shown again.
                    They must change it on first login.
                </p>
                <div className="mt-3 rounded-xl border border-border-strong bg-surface-2 px-3.5 py-2.5 text-center font-mono text-[15px] tracking-wide text-fg">
                    {temporaryPassword?.password}
                </div>
            </Modal>
        </Card>
    );
}

function UserForm({
    row, roles, branches, onClose, onSaved,
}: {
    row: UserRow | null;
    roles: string[];
    branches: { id: number; name: string }[];
    onClose: () => void;
    onSaved: (created: { email: string; password: string } | null) => void;
}) {
    const [form, setForm] = useState<UserPayload>({
        name: row?.name ?? '',
        email: row?.email ?? '',
        phone: row?.phone ?? '',
        status: row?.status ?? true,
        branch_ids: row?.branches.map((b) => b.id) ?? branches.map((b) => b.id),
        default_branch_id: row?.branches.find((b) => b.is_default)?.id ?? branches[0]?.id ?? null,
        role: row?.role ?? roles[0] ?? '',
    });
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [error, setError] = useState<string | null>(null);

    const save = useMutation({
        mutationFn: () => (row ? updateUser(row.id, form) : createUser(form)),
        onSuccess: (result) => onSaved(row ? null : { email: form.email, password: (result as unknown as { temporary_password: string }).temporary_password }),
        onError: (e) => { const a = toApiError(e); setError(a.errors ? null : a.message); setErrors(a.errors ?? {}); },
    });

    const inputCls = (hasError: boolean) => cn(
        'w-full rounded-xl border bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none placeholder:text-faint focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25',
        hasError ? 'border-rose-400' : 'border-border-strong',
    );

    const toggleBranch = (id: number) => {
        setForm((f) => {
            const has = f.branch_ids.includes(id);
            const branch_ids = has ? f.branch_ids.filter((b) => b !== id) : [...f.branch_ids, id];
            const default_branch_id = branch_ids.includes(f.default_branch_id ?? -1) ? f.default_branch_id : branch_ids[0] ?? null;
            return { ...f, branch_ids, default_branch_id };
        });
    };

    return (
        <Modal
            open onClose={onClose} title={row ? 'Edit user' : 'Add user'}
            footer={<>
                <Button variant="outline" onClick={onClose} disabled={save.isPending}>Cancel</Button>
                <Button onClick={() => { setError(null); setErrors({}); save.mutate(); }} disabled={save.isPending}>
                    {save.isPending ? <Loader2 size={16} className="animate-spin" /> : null} {row ? 'Save changes' : 'Create user'}
                </Button>
            </>}
        >
            {error && <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
            <div className="space-y-4">
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Name <span className="text-rose-500">*</span></label>
                    <input value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} className={inputCls(!!errors.name)} />
                    {errors.name && <p className="mt-1.5 text-[12px] text-rose-500">{errors.name[0]}</p>}
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Email <span className="text-rose-500">*</span></label>
                    <input type="email" value={form.email} onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))} className={inputCls(!!errors.email)} />
                    {errors.email && <p className="mt-1.5 text-[12px] text-rose-500">{errors.email[0]}</p>}
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Phone</label>
                    <input value={form.phone ?? ''} onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))} className={inputCls(false)} />
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Role <span className="text-rose-500">*</span></label>
                    <select value={form.role} onChange={(e) => setForm((f) => ({ ...f, role: e.target.value }))} className={inputCls(!!errors.role)}>
                        {roles.map((r) => <option key={r} value={r}>{r}</option>)}
                    </select>
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Branches <span className="text-rose-500">*</span></label>
                    <div className="flex flex-wrap gap-1.5">
                        {branches.map((b) => (
                            <button
                                type="button" key={b.id} onClick={() => toggleBranch(b.id)}
                                className={cn(
                                    'rounded-lg border px-3 py-1.5 text-[13px] font-medium transition-colors',
                                    form.branch_ids.includes(b.id)
                                        ? 'border-brand-500 bg-brand-500/10 text-brand-700 dark:text-brand-300'
                                        : 'border-border-strong text-muted hover:text-fg',
                                )}
                            >
                                {b.name}
                            </button>
                        ))}
                    </div>
                    {errors.branch_ids && <p className="mt-1.5 text-[12px] text-rose-500">{errors.branch_ids[0]}</p>}
                </div>
                {row && (
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">Status</label>
                        <select value={form.status ? '1' : '0'} onChange={(e) => setForm((f) => ({ ...f, status: e.target.value === '1' }))} className={inputCls(false)}>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                )}
            </div>
        </Modal>
    );
}
