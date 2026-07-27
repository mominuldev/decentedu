import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2 } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { useToast } from '@/components/Toast';
import {
    listRedirects, createRedirect, updateRedirect, deleteRedirect,
    inputCls, type RedirectRow,
} from './api';
import { Field, Loading, Empty } from './PagesPanel';

export function RedirectsPanel() {
    const qc = useQueryClient();
    const toast = useToast();
    const { data: rows = [], isLoading } = useQuery({ queryKey: ['cms-redirects'], queryFn: () => listRedirects() });
    const [editing, setEditing] = useState<RedirectRow | null>(null);
    const [creating, setCreating] = useState(false);
    const [deleting, setDeleting] = useState<RedirectRow | null>(null);

    const invalidate = () => qc.invalidateQueries({ queryKey: ['cms-redirects'] });
    const del = useMutation({
        mutationFn: (id: number) => deleteRedirect(id),
        onSuccess: () => {
            invalidate();
            setDeleting(null);
            toast.success('Redirect deleted successfully');
        },
        onError: (err) => toast.error(toApiError(err).message),
    });

    return (
        <Card>
            <div className="flex items-center justify-between px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Redirects</h3>
                    <p className="text-[12.5px] text-muted">{rows.length} rule{rows.length === 1 ? '' : 's'}</p>
                </div>
                <Button onClick={() => setCreating(true)}><Plus size={16} /> Add redirect</Button>
            </div>
            <div className="overflow-x-auto border-t border-border">
                {isLoading ? <Loading /> : rows.length === 0 ? <Empty label="No redirects yet" /> : (
                    <table className="w-full min-w-[560px] text-left text-[13.5px]">
                        <thead><tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                            <th className="px-5 py-2.5 font-semibold">From</th><th className="px-5 py-2.5 font-semibold">To</th>
                            <th className="px-5 py-2.5 font-semibold">Code</th><th className="px-5 py-2.5 font-semibold">Hits</th>
                            <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                        </tr></thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={r.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                    <td className="px-5 py-3 font-medium text-fg">/{r.from_path}</td>
                                    <td className="px-5 py-3 text-muted">/{r.to_path}</td>
                                    <td className="px-5 py-3"><Badge tone={r.status_code === 301 ? 'brand' : 'neutral'}>{r.status_code}</Badge></td>
                                    <td className="px-5 py-3 text-muted">{r.hits}</td>
                                    <td className="px-5 py-3"><div className="flex justify-end gap-1">
                                        <button onClick={() => setEditing(r)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600"><Pencil size={16} /></button>
                                        <button onClick={() => setDeleting(r)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500"><Trash2 size={16} /></button>
                                    </div></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {(creating || editing) && <RedirectForm row={editing} onClose={() => { setCreating(false); setEditing(null); }} onSaved={() => { invalidate(); setCreating(false); setEditing(null); }} />}
            <ConfirmDialog open={!!deleting} onClose={() => setDeleting(null)} onConfirm={() => deleting && del.mutate(deleting.id)} busy={del.isPending} title="Delete redirect" message={`Delete redirect from "/${deleting?.from_path}"?`} />
        </Card>
    );
}

function RedirectForm({ row, onClose, onSaved }: { row: RedirectRow | null; onClose: () => void; onSaved: () => void }) {
    const toast = useToast();
    const [form, setForm] = useState({ from_path: row?.from_path ?? '', to_path: row?.to_path ?? '', status_code: row?.status_code ?? 301, is_active: row?.is_active ?? true });
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [error, setError] = useState<string | null>(null);
    const save = useMutation({
        mutationFn: () => row ? updateRedirect(row.id, form) : createRedirect(form),
        onSuccess: () => {
            toast.success(row ? 'Redirect updated successfully' : 'Redirect created successfully');
            onSaved();
        },
        onError: (e) => {
            const err = toApiError(e);
            setError(err.message);
            setErrors(err.errors ?? {});
            toast.error(err.message || 'Could not save redirect');
        },
    });
    const set = (p: Partial<typeof form>) => setForm((f) => ({ ...f, ...p }));
    return (
        <Modal open onClose={onClose} title={row ? 'Edit redirect' : 'New redirect'}
            footer={<><Button variant="outline" onClick={onClose}>Cancel</Button><Button onClick={() => save.mutate()} disabled={save.isPending}>Save</Button></>}>
            <div className="space-y-4">
                {error && <p className="text-[13px] text-rose-600">{error}</p>}
                <Field label="From path" error={errors.from_path}><input className={inputCls} value={form.from_path} onChange={(e) => set({ from_path: e.target.value })} placeholder="old-page" /></Field>
                <Field label="To path" error={errors.to_path}><input className={inputCls} value={form.to_path} onChange={(e) => set({ to_path: e.target.value })} placeholder="new-page" /></Field>
                <Field label="Status code">
                    <select className={inputCls} value={form.status_code} onChange={(e) => set({ status_code: Number(e.target.value) })}>
                        <option value={301}>301 — Permanent</option>
                        <option value={302}>302 — Temporary</option>
                    </select>
                </Field>
            </div>
        </Modal>
    );
}
