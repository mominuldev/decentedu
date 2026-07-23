import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Loader2, Inbox } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { cn } from '@/lib/cn';
import { listIdCardTemplates, createIdCardTemplate, updateIdCardTemplate, deleteIdCardTemplate, ID_CARD_FIELDS, type IdCardTemplateRow, type IdCardField } from './api';

const inputCls = 'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';

export function IdCardTemplatesPanel() {
    const qc = useQueryClient();
    const { data: rows = [], isLoading } = useQuery({ queryKey: ['id-card-templates'], queryFn: () => listIdCardTemplates() });
    const [editing, setEditing] = useState<IdCardTemplateRow | null>(null);
    const [creating, setCreating] = useState(false);
    const [deleting, setDeleting] = useState<IdCardTemplateRow | null>(null);

    const invalidate = () => qc.invalidateQueries({ queryKey: ['id-card-templates'] });
    const del = useMutation({ mutationFn: (id: number) => deleteIdCardTemplate(id), onSuccess: () => { invalidate(); setDeleting(null); } });

    return (
        <Card>
            <div className="flex items-center justify-between px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">ID card templates</h3>
                    <p className="text-[12.5px] text-muted">Choose which fields to show — no visual layout builder.</p>
                </div>
                <Button onClick={() => setCreating(true)}><Plus size={16} /> Add template</Button>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : rows.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                        <div className="grid h-12 w-12 place-items-center rounded-2xl bg-surface-2 text-faint"><Inbox size={22} /></div>
                        <p className="text-[14px] font-medium text-fg">No templates yet</p>
                    </div>
                ) : (
                    <table className="w-full min-w-[560px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Name</th>
                                <th className="px-5 py-2.5 font-semibold">Holder</th>
                                <th className="px-5 py-2.5 font-semibold">Fields</th>
                                <th className="px-5 py-2.5 font-semibold">Status</th>
                                <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={r.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                    <td className="px-5 py-3 font-medium text-fg">{r.name}</td>
                                    <td className="px-5 py-3 text-muted capitalize">{r.holder_type}</td>
                                    <td className="max-w-[280px] truncate px-5 py-3 text-muted">{r.fields.join(', ')}</td>
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

            {(creating || editing) && <TemplateForm row={editing} onClose={() => { setCreating(false); setEditing(null); }} onSaved={() => { invalidate(); setCreating(false); setEditing(null); }} />}
            <ConfirmDialog open={!!deleting} onClose={() => setDeleting(null)} onConfirm={() => deleting && del.mutate(deleting.id)} busy={del.isPending}
                title="Delete template" message={`Are you sure you want to delete "${deleting?.name}"?`} />
        </Card>
    );
}

function TemplateForm({ row, onClose, onSaved }: { row: IdCardTemplateRow | null; onClose: () => void; onSaved: () => void }) {
    const [name, setName] = useState(row?.name ?? '');
    const [holderType, setHolderType] = useState<IdCardTemplateRow['holder_type']>(row?.holder_type ?? 'student');
    const [fields, setFields] = useState<IdCardField[]>(row?.fields ?? ['photo', 'name', 'roll', 'class']);
    const [showQr, setShowQr] = useState(row?.show_qr ?? false);
    const [primaryColor, setPrimaryColor] = useState(row?.primary_color ?? '#5343e0');
    const [status, setStatus] = useState(row?.status ?? true);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [error, setError] = useState<string | null>(null);

    const payload = { name, holder_type: holderType, fields, show_qr: showQr, primary_color: primaryColor, status };
    const save = useMutation({
        mutationFn: () => (row ? updateIdCardTemplate(row.id, payload) : createIdCardTemplate(payload)),
        onSuccess: onSaved,
        onError: (e) => { const a = toApiError(e); setError(a.errors ? null : a.message); setErrors(a.errors ?? {}); },
    });

    const toggleField = (f: IdCardField) => setFields((fs) => (fs.includes(f) ? fs.filter((x) => x !== f) : [...fs, f]));

    return (
        <Modal
            open onClose={onClose} title={row ? 'Edit template' : 'Add template'}
            footer={<>
                <Button variant="outline" onClick={onClose} disabled={save.isPending}>Cancel</Button>
                <Button onClick={() => { setError(null); setErrors({}); save.mutate(); }} disabled={save.isPending}>
                    {save.isPending ? <Loader2 size={16} className="animate-spin" /> : null} {row ? 'Save changes' : 'Create template'}
                </Button>
            </>}
        >
            {error && <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
            <div className="space-y-4">
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Name <span className="text-rose-500">*</span></label>
                    <input value={name} onChange={(e) => setName(e.target.value)} className={inputCls} />
                    {errors.name && <p className="mt-1.5 text-[12px] text-rose-500">{errors.name[0]}</p>}
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Holder type</label>
                    <select value={holderType} onChange={(e) => setHolderType(e.target.value as IdCardTemplateRow['holder_type'])} className={inputCls}>
                        <option value="student">Student</option>
                        <option value="employee">Employee</option>
                    </select>
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Fields to show <span className="text-rose-500">*</span></label>
                    <div className="grid grid-cols-3 gap-2">
                        {ID_CARD_FIELDS.map((f) => (
                            <label key={f} className={cn('flex cursor-pointer select-none items-center gap-2 rounded-lg border px-2.5 py-1.5 text-[12.5px] capitalize', fields.includes(f) ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300' : 'border-border-strong text-muted')}>
                                <input type="checkbox" checked={fields.includes(f)} onChange={() => toggleField(f)} className="h-3.5 w-3.5 rounded border-border-strong text-brand-600 focus:ring-brand-500" />
                                {f.replace('_', ' ')}
                            </label>
                        ))}
                    </div>
                    {errors.fields && <p className="mt-1.5 text-[12px] text-rose-500">{errors.fields[0]}</p>}
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">Primary color</label>
                        <input type="color" value={primaryColor} onChange={(e) => setPrimaryColor(e.target.value)} className="h-10 w-full rounded-xl border border-border-strong bg-surface px-1.5" />
                    </div>
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">Status</label>
                        <select value={status ? '1' : '0'} onChange={(e) => setStatus(e.target.value === '1')} className={inputCls}>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <label className="flex cursor-pointer select-none items-center gap-2.5 text-[13.5px] text-fg">
                    <input type="checkbox" checked={showQr} onChange={(e) => setShowQr(e.target.checked)} className="h-4 w-4 rounded border-border-strong text-brand-600 focus:ring-brand-500" />
                    Show QR code placeholder
                </label>
            </div>
        </Modal>
    );
}
