import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Loader2, Inbox, Search } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { cn } from '@/lib/cn';
import { listContacts, createContact, updateContact, deleteContact, type ContactRow } from './api';

const TYPES: { value: ContactRow['type']; label: string }[] = [
    { value: 'student', label: 'Student' },
    { value: 'guardian', label: 'Guardian' },
    { value: 'employee', label: 'Employee' },
    { value: 'custom', label: 'Custom' },
];

export function ContactsPanel() {
    const qc = useQueryClient();
    const [search, setSearch] = useState('');
    const { data: rows = [], isLoading } = useQuery({ queryKey: ['contacts', search], queryFn: () => listContacts({ search: search || undefined }) });
    const [editing, setEditing] = useState<ContactRow | null>(null);
    const [creating, setCreating] = useState(false);
    const [deleting, setDeleting] = useState<ContactRow | null>(null);

    const invalidate = () => qc.invalidateQueries({ queryKey: ['contacts'] });
    const del = useMutation({
        mutationFn: (id: number) => deleteContact(id),
        onSuccess: () => { invalidate(); setDeleting(null); },
    });

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Phone book</h3>
                    <p className="text-[12.5px] text-muted">{rows.length} contact{rows.length === 1 ? '' : 's'}</p>
                </div>
                <div className="flex items-center gap-2">
                    <div className="relative">
                        <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-faint" />
                        <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search name or phone"
                            className="w-56 rounded-xl border border-border-strong bg-surface py-2 pl-9 pr-3 text-[13.5px] outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25" />
                    </div>
                    <Button onClick={() => setCreating(true)}><Plus size={16} /> Add contact</Button>
                </div>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : rows.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                        <div className="grid h-12 w-12 place-items-center rounded-2xl bg-surface-2 text-faint"><Inbox size={22} /></div>
                        <p className="text-[14px] font-medium text-fg">No contacts yet</p>
                    </div>
                ) : (
                    <table className="w-full min-w-[560px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Name</th>
                                <th className="px-5 py-2.5 font-semibold">Phone</th>
                                <th className="px-5 py-2.5 font-semibold">Type</th>
                                <th className="px-5 py-2.5 font-semibold">Status</th>
                                <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={r.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                    <td className="px-5 py-3 font-medium text-fg">{r.name}</td>
                                    <td className="tnum px-5 py-3 text-muted">{r.phone}</td>
                                    <td className="px-5 py-3 text-muted capitalize">{r.type}</td>
                                    <td className="px-5 py-3"><Badge tone={r.status ? 'success' : 'neutral'}>{r.status ? 'Active' : 'Inactive'}</Badge></td>
                                    <td className="px-5 py-3">
                                        <div className="flex justify-end gap-1">
                                            <button onClick={() => setEditing(r)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600" aria-label="Edit"><Pencil size={16} /></button>
                                            <button onClick={() => setDeleting(r)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500" aria-label="Delete"><Trash2 size={16} /></button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {(creating || editing) && (
                <ContactForm row={editing} onClose={() => { setCreating(false); setEditing(null); }} onSaved={() => { invalidate(); setCreating(false); setEditing(null); }} />
            )}

            <ConfirmDialog
                open={!!deleting} onClose={() => setDeleting(null)}
                onConfirm={() => deleting && del.mutate(deleting.id)} busy={del.isPending}
                title="Delete contact" message={`Are you sure you want to delete "${deleting?.name}"?`}
            />
        </Card>
    );
}

function ContactForm({ row, onClose, onSaved }: { row: ContactRow | null; onClose: () => void; onSaved: () => void }) {
    const [form, setForm] = useState({
        name: row?.name ?? '', phone: row?.phone ?? '', type: row?.type ?? 'custom', status: row?.status ?? true,
    });
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [error, setError] = useState<string | null>(null);

    const save = useMutation({
        mutationFn: () => (row ? updateContact(row.id, form) : createContact(form)),
        onSuccess: onSaved,
        onError: (e) => { const a = toApiError(e); setError(a.errors ? null : a.message); setErrors(a.errors ?? {}); },
    });

    const inputCls = (hasError: boolean) => cn(
        'w-full rounded-xl border bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none placeholder:text-faint focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25',
        hasError ? 'border-rose-400' : 'border-border-strong',
    );

    return (
        <Modal
            open onClose={onClose} title={row ? 'Edit contact' : 'Add contact'}
            footer={<>
                <Button variant="outline" onClick={onClose} disabled={save.isPending}>Cancel</Button>
                <Button onClick={() => { setError(null); setErrors({}); save.mutate(); }} disabled={save.isPending}>
                    {save.isPending ? <Loader2 size={16} className="animate-spin" /> : null} {row ? 'Save changes' : 'Create contact'}
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
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Phone <span className="text-rose-500">*</span></label>
                    <input value={form.phone} onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))} className={inputCls(!!errors.phone)} />
                    {errors.phone && <p className="mt-1.5 text-[12px] text-rose-500">{errors.phone[0]}</p>}
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Type</label>
                    <select value={form.type} onChange={(e) => setForm((f) => ({ ...f, type: e.target.value as ContactRow['type'] }))} className={inputCls(false)}>
                        {TYPES.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                    </select>
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Status</label>
                    <select value={form.status ? '1' : '0'} onChange={(e) => setForm((f) => ({ ...f, status: e.target.value === '1' }))} className={inputCls(false)}>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
        </Modal>
    );
}
