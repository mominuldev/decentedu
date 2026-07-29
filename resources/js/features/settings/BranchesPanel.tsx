import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Building2, Loader2, Inbox } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { Toast, type ToastState } from '@/components/Toast';
import { toApiError } from '@/lib/api';
import { cn } from '@/lib/cn';
import { useAuth } from '@/features/auth/AuthProvider';
import {
    listBranches, createBranch, updateBranch, deleteBranch,
    type BranchRow, type BranchPayload,
} from './api';

export function BranchesPanel() {
    const qc = useQueryClient();
    const { session } = useAuth();
    const isSuperAdmin = session?.user?.is_super_admin ?? false;

    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<BranchRow | null>(null);
    const [deleting, setDeleting] = useState<BranchRow | null>(null);
    const [toast, setToast] = useState<ToastState | null>(null);

    const { data: branches = [], isLoading } = useQuery({
        queryKey: ['settings', 'branches'],
        queryFn: listBranches,
    });

    const invalidate = () => qc.invalidateQueries({ queryKey: ['settings', 'branches'] });

    const destroy = useMutation({
        mutationFn: (id: number) => deleteBranch(id),
        onSuccess: () => {
            invalidate();
            setDeleting(null);
            setToast({ tone: 'success', message: 'Branch deleted successfully.' });
        },
        onError: (e: any) => {
            setDeleting(null);
            const msg = e?.response?.data?.message || 'Failed to delete branch.';
            setToast({ tone: 'error', message: msg });
        },
    });

    return (
        <div className="space-y-6">
            <Toast toast={toast} onClose={() => setToast(null)} />

            <Card>
                <div className="flex items-center justify-between px-5 py-4">
                    <div className="flex items-center gap-3">
                        <div className="grid h-9 w-9 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                            <Building2 size={18} />
                        </div>
                        <div>
                            <h3 className="text-[15px] font-semibold text-fg">All Branches</h3>
                            <p className="text-[12.5px] text-muted">
                                {branches.length} branch{branches.length === 1 ? '' : 'es'} in your organisation
                            </p>
                        </div>
                    </div>
                    {isSuperAdmin && (
                        <Button onClick={() => setCreating(true)}>
                            <Plus size={16} /> Add Branch
                        </Button>
                    )}
                </div>

                <div className="overflow-x-auto border-t border-border">
                    {isLoading ? (
                        <div className="flex items-center justify-center gap-2 py-16 text-muted">
                            <Loader2 size={18} className="animate-spin" /> Loading&hellip;
                        </div>
                    ) : branches.length === 0 ? (
                        <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                            <div className="grid h-12 w-12 place-items-center rounded-2xl bg-surface-2 text-faint">
                                <Inbox size={22} />
                            </div>
                            <p className="text-[14px] font-medium text-fg">No branches yet</p>
                            {isSuperAdmin && (
                                <p className="text-[13px] text-muted">
                                    Click <strong>Add Branch</strong> to create your first branch.
                                </p>
                            )}
                        </div>
                    ) : (
                        <table className="w-full min-w-[640px] text-left text-[13.5px]">
                            <thead>
                                <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                    <th className="px-5 py-2.5 font-semibold">Name</th>
                                    <th className="px-5 py-2.5 font-semibold">Code</th>
                                    <th className="px-5 py-2.5 font-semibold">Phone</th>
                                    <th className="px-5 py-2.5 font-semibold">Email</th>
                                    <th className="px-5 py-2.5 font-semibold">Status</th>
                                    <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {branches.map((b) => (
                                    <tr key={b.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                        <td className="px-5 py-3">
                                            <div className="flex items-center gap-2.5">
                                                <span className="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-brand-50 text-[11px] font-bold text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
                                                    {b.name.slice(0, 1).toUpperCase()}
                                                </span>
                                                <div>
                                                    <p className="font-medium text-fg">{b.name}</p>
                                                    {b.name_bn && <p className="text-[12px] text-muted">{b.name_bn}</p>}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-5 py-3">
                                            {b.code
                                                ? <span className="rounded-md bg-surface-2 px-2 py-0.5 font-mono text-[12px] text-fg">{b.code}</span>
                                                : <span className="text-faint">—</span>
                                            }
                                        </td>
                                        <td className="px-5 py-3 text-muted">{b.phone ?? '—'}</td>
                                        <td className="px-5 py-3 text-muted">{b.email ?? '—'}</td>
                                        <td className="px-5 py-3">
                                            <Badge tone={b.status ? 'success' : 'neutral'}>
                                                {b.status ? 'Active' : 'Inactive'}
                                            </Badge>
                                        </td>
                                        <td className="px-5 py-3">
                                            <div className="flex justify-end gap-1">
                                                <button
                                                    onClick={() => setEditing(b)}
                                                    className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600"
                                                    aria-label={`Edit ${b.name}`}
                                                >
                                                    <Pencil size={16} />
                                                </button>
                                                {isSuperAdmin && (
                                                    <button
                                                        onClick={() => setDeleting(b)}
                                                        className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500"
                                                        aria-label={`Delete ${b.name}`}
                                                    >
                                                        <Trash2 size={16} />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </Card>

            {(creating || editing) && (
                <BranchForm
                    row={editing}
                    onClose={() => { setCreating(false); setEditing(null); }}
                    onSaved={(msg) => {
                        invalidate();
                        setCreating(false);
                        setEditing(null);
                        setToast({ tone: 'success', message: msg });
                    }}
                    onError={(msg) => setToast({ tone: 'error', message: msg })}
                />
            )}

            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={() => deleting && destroy.mutate(deleting.id)}
                busy={destroy.isPending}
                title="Delete branch"
                message={`"${deleting?.name}" will be permanently removed and all its data will be inaccessible. This cannot be undone. Continue?`}
            />
        </div>
    );
}

function BranchForm({
    row, onClose, onSaved, onError,
}: {
    row: BranchRow | null;
    onClose: () => void;
    onSaved: (message: string) => void;
    onError: (message: string) => void;
}) {
    const [form, setForm] = useState<BranchPayload>({
        name: row?.name ?? '',
        name_bn: row?.name_bn ?? '',
        code: row?.code ?? '',
        phone: row?.phone ?? '',
        email: row?.email ?? '',
        address: row?.address ?? '',
        status: row?.status ?? true,
    });
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [globalError, setGlobalError] = useState<string | null>(null);

    const save = useMutation({
        mutationFn: () => (row ? updateBranch(row.id, form) : createBranch(form)),
        onSuccess: () => {
            onSaved(row ? `"${form.name}" updated successfully.` : `"${form.name}" branch created successfully.`);
        },
        onError: (e) => {
            const err = toApiError(e);
            setGlobalError(err.errors ? null : err.message);
            setErrors(err.errors ?? {});
            onError(err.message || 'Something went wrong.');
        },
    });

    const inputCls = (hasError?: boolean) => cn(
        'w-full rounded-xl border bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none placeholder:text-faint focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25',
        hasError ? 'border-rose-400' : 'border-border-strong',
    );
    const labelCls = 'mb-1.5 block text-[13px] font-medium text-fg';

    return (
        <Modal
            open
            onClose={onClose}
            title={row ? `Edit — ${row.name}` : 'Add New Branch'}
            width="max-w-xl"
            footer={
                <>
                    <Button variant="outline" onClick={onClose} disabled={save.isPending}>Cancel</Button>
                    <Button
                        onClick={() => { setGlobalError(null); setErrors({}); save.mutate(); }}
                        disabled={save.isPending}
                    >
                        {save.isPending ? <Loader2 size={16} className="animate-spin" /> : null}
                        {row ? 'Save Changes' : 'Create Branch'}
                    </Button>
                </>
            }
        >
            {globalError && (
                <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">
                    {globalError}
                </div>
            )}

            <div className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label className={labelCls}>Branch Name <span className="text-rose-500">*</span></label>
                        <input
                            value={form.name}
                            onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                            className={inputCls(!!errors.name)}
                            placeholder="e.g. Main Campus"
                        />
                        {errors.name && <p className="mt-1.5 text-[12px] text-rose-500">{errors.name[0]}</p>}
                    </div>

                    <div>
                        <label className={labelCls}>Bengali Name (বাংলা নাম)</label>
                        <input
                            value={form.name_bn ?? ''}
                            onChange={(e) => setForm((f) => ({ ...f, name_bn: e.target.value }))}
                            className={inputCls(!!errors.name_bn)}
                            placeholder="যেমন: প্রধান ক্যাম্পাস"
                        />
                    </div>

                    <div>
                        <label className={labelCls}>Branch Code</label>
                        <input
                            value={form.code ?? ''}
                            onChange={(e) => setForm((f) => ({ ...f, code: e.target.value.toUpperCase() }))}
                            className={inputCls(!!errors.code)}
                            placeholder="e.g. MAIN"
                        />
                        {errors.code && <p className="mt-1.5 text-[12px] text-rose-500">{errors.code[0]}</p>}
                    </div>

                    <div>
                        <label className={labelCls}>Phone</label>
                        <input
                            value={form.phone ?? ''}
                            onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))}
                            className={inputCls(!!errors.phone)}
                            placeholder="+880 1700-000000"
                        />
                    </div>

                    <div className="sm:col-span-2">
                        <label className={labelCls}>Email</label>
                        <input
                            type="email"
                            value={form.email ?? ''}
                            onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
                            className={inputCls(!!errors.email)}
                            placeholder="info@branch.edu"
                        />
                        {errors.email && <p className="mt-1.5 text-[12px] text-rose-500">{errors.email[0]}</p>}
                    </div>

                    <div className="sm:col-span-2">
                        <label className={labelCls}>Physical Address</label>
                        <textarea
                            rows={2}
                            value={form.address ?? ''}
                            onChange={(e) => setForm((f) => ({ ...f, address: e.target.value }))}
                            className={inputCls(!!errors.address)}
                            placeholder="House #12, Road #4, Sector #7, Dhaka"
                        />
                    </div>

                    <div>
                        <label className={labelCls}>Status</label>
                        <select
                            value={form.status ? '1' : '0'}
                            onChange={(e) => setForm((f) => ({ ...f, status: e.target.value === '1' }))}
                            className={inputCls()}
                        >
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
        </Modal>
    );
}
