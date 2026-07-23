import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2, Pencil, Plus, Trash2 } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { listSignatures, createSignature, updateSignature, deleteSignature, type SignatureRow } from './api';

export function SignaturesPanel() {
    const { data: rows = [], isLoading } = useQuery({ queryKey: ['signatures'], queryFn: listSignatures });
    const qc = useQueryClient();

    const [editing, setEditing] = useState<SignatureRow | null>(null);
    const [creating, setCreating] = useState(false);
    const [deleting, setDeleting] = useState<SignatureRow | null>(null);
    const invalidate = () => qc.invalidateQueries({ queryKey: ['signatures'] });

    const del = useMutation({
        mutationFn: (id: number) => deleteSignature(id),
        onSuccess: () => { invalidate(); setDeleting(null); },
    });

    return (
        <Card>
            <div className="flex items-center justify-between px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Signatures</h3>
                    <p className="text-[12.5px] text-muted">Named signatures printed on marksheets and admit cards</p>
                </div>
                <Button onClick={() => setCreating(true)}><Plus size={16} /> Add signature</Button>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : rows.length === 0 ? (
                    <div className="flex items-center justify-center py-16 text-[14px] font-medium text-fg">No signatures yet</div>
                ) : (
                    <table className="w-full min-w-[520px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Position</th>
                                <th className="px-5 py-2.5 font-semibold">Name</th>
                                <th className="px-5 py-2.5 font-semibold">Designation</th>
                                <th className="px-5 py-2.5 font-semibold">Status</th>
                                <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((s) => (
                                <tr key={s.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                    <td className="px-5 py-3 capitalize text-muted">{s.position}</td>
                                    <td className="px-5 py-3 font-medium text-fg">{s.person_name}</td>
                                    <td className="px-5 py-3 text-muted">{s.designation}</td>
                                    <td className="px-5 py-3"><Badge tone={s.status ? 'success' : 'neutral'}>{s.status ? 'Active' : 'Inactive'}</Badge></td>
                                    <td className="px-5 py-3">
                                        <div className="flex justify-end gap-1">
                                            <button onClick={() => setEditing(s)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600" aria-label="Edit"><Pencil size={16} /></button>
                                            <button onClick={() => setDeleting(s)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500" aria-label="Delete"><Trash2 size={16} /></button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {(creating || editing) && (
                <SignatureForm
                    row={editing}
                    onClose={() => { setCreating(false); setEditing(null); }}
                    onSaved={() => { invalidate(); setCreating(false); setEditing(null); }}
                />
            )}

            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={() => deleting && del.mutate(deleting.id)}
                busy={del.isPending}
                title="Delete signature"
                message={`Remove signature "${deleting?.person_name}"?`}
            />
        </Card>
    );
}

function SignatureForm({ row, onClose, onSaved }: { row: SignatureRow | null; onClose: () => void; onSaved: () => void }) {
    const [form, setForm] = useState({
        position: row?.position ?? 'left',
        person_name: row?.person_name ?? '',
        designation: row?.designation ?? '',
    });
    const [error, setError] = useState<string | null>(null);

    const save = useMutation({
        mutationFn: () => (row ? updateSignature(row.id, form) : createSignature(form)),
        onSuccess: onSaved,
        onError: (e) => setError(toApiError(e).message),
    });

    const inputCls = 'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';

    return (
        <Modal
            open onClose={onClose}
            title={row ? 'Edit signature' : 'Add signature'}
            footer={
                <>
                    <Button variant="outline" onClick={onClose} disabled={save.isPending}>Cancel</Button>
                    <Button onClick={() => { setError(null); save.mutate(); }} disabled={save.isPending || !form.person_name || !form.designation}>
                        {save.isPending ? <Loader2 size={16} className="animate-spin" /> : null}
                        {row ? 'Save changes' : 'Create'}
                    </Button>
                </>
            }
        >
            {error && <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
            <div className="space-y-4">
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Position</label>
                    <select value={form.position} onChange={(e) => setForm((f) => ({ ...f, position: e.target.value }))} className={inputCls}>
                        <option value="left">Left</option>
                        <option value="middle">Middle</option>
                        <option value="right">Right</option>
                    </select>
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Name <span className="text-rose-500">*</span></label>
                    <input value={form.person_name} onChange={(e) => setForm((f) => ({ ...f, person_name: e.target.value }))} className={inputCls} />
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Designation <span className="text-rose-500">*</span></label>
                    <input value={form.designation} onChange={(e) => setForm((f) => ({ ...f, designation: e.target.value }))} className={inputCls} />
                </div>
            </div>
        </Modal>
    );
}
