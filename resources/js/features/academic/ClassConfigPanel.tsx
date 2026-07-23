import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Loader2, Inbox } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { useAuth } from '@/features/auth/AuthProvider';
import { toApiError } from '@/lib/api';
import { cn } from '@/lib/cn';
import {
    listClassConfigs, classConfigOptions, createClassConfig, updateClassConfig, deleteClassConfig,
    type ClassConfigRow, type ConfigOptions,
} from './api';

export function ClassConfigPanel() {
    const { session } = useAuth();
    const branchId = session?.active_branch?.id;
    const qc = useQueryClient();

    const { data: rows = [], isLoading } = useQuery({
        queryKey: ['class-configs', branchId],
        queryFn: listClassConfigs,
    });
    const { data: options } = useQuery({
        queryKey: ['class-config-options', branchId],
        queryFn: classConfigOptions,
    });

    const [editing, setEditing] = useState<ClassConfigRow | null>(null);
    const [creating, setCreating] = useState(false);
    const [deleting, setDeleting] = useState<ClassConfigRow | null>(null);
    const invalidate = () => qc.invalidateQueries({ queryKey: ['class-configs'] });

    const del = useMutation({
        mutationFn: (id: number) => deleteClassConfig(id),
        onSuccess: () => { invalidate(); setDeleting(null); },
    });

    return (
        <Card>
            <div className="flex items-center justify-between px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Class configuration</h3>
                    <p className="text-[12.5px] text-muted">The concrete teaching unit — Class × Section × Shift</p>
                </div>
                <Button onClick={() => setCreating(true)} disabled={!options}><Plus size={16} /> Add config</Button>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : rows.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                        <div className="grid h-12 w-12 place-items-center rounded-2xl bg-surface-2 text-faint"><Inbox size={22} /></div>
                        <p className="text-[14px] font-medium text-fg">No class configs yet</p>
                        <p className="text-[13px] text-muted">Combine a class, section and shift to define a teaching unit.</p>
                    </div>
                ) : (
                    <table className="w-full min-w-[520px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Configuration</th>
                                <th className="px-5 py-2.5 font-semibold">Class</th>
                                <th className="px-5 py-2.5 font-semibold">Section</th>
                                <th className="px-5 py-2.5 font-semibold">Shift</th>
                                <th className="px-5 py-2.5 font-semibold">Status</th>
                                <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={r.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                    <td className="px-5 py-3 font-medium text-fg">{r.label}</td>
                                    <td className="px-5 py-3 text-muted">{r.class_name}</td>
                                    <td className="px-5 py-3 text-muted">{r.section_name}</td>
                                    <td className="px-5 py-3 text-muted">{r.shift_name}</td>
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

            {(creating || editing) && options && (
                <ConfigForm
                    options={options}
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
                title="Delete class config"
                message={`Delete "${deleting?.label}"? Students and results reference this unit.`}
            />
        </Card>
    );
}

function ConfigForm({
    options, row, onClose, onSaved,
}: {
    options: ConfigOptions;
    row: ClassConfigRow | null;
    onClose: () => void;
    onSaved: () => void;
}) {
    const [form, setForm] = useState({
        class_id: row?.class_id ?? 0,
        shift_id: row?.shift_id ?? 0,
        section_id: row?.section_id ?? 0,
        serial: row?.serial ?? 0,
        status: row?.status ?? true,
    });
    const [error, setError] = useState<string | null>(null);

    const save = useMutation({
        mutationFn: () => (row ? updateClassConfig(row.id, form) : createClassConfig(form)),
        onSuccess: onSaved,
        onError: (e) => setError(toApiError(e).message),
    });

    const selCls = 'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';
    const sel = (k: 'class_id' | 'shift_id' | 'section_id', label: string, opts: { id: number; name: string }[]) => (
        <div>
            <label className="mb-1.5 block text-[13px] font-medium text-fg">{label} <span className="text-rose-500">*</span></label>
            <select value={form[k]} onChange={(e) => setForm((f) => ({ ...f, [k]: Number(e.target.value) }))} className={selCls}>
                <option value={0} disabled>Select {label.toLowerCase()}</option>
                {opts.map((o) => <option key={o.id} value={o.id}>{o.name}</option>)}
            </select>
        </div>
    );

    return (
        <Modal
            open onClose={onClose}
            title={row ? 'Edit class config' : 'Add class config'}
            footer={
                <>
                    <Button variant="outline" onClick={onClose} disabled={save.isPending}>Cancel</Button>
                    <Button
                        onClick={() => { setError(null); save.mutate(); }}
                        disabled={save.isPending || !form.class_id || !form.shift_id || !form.section_id}
                    >
                        {save.isPending ? <Loader2 size={16} className="animate-spin" /> : null}
                        {row ? 'Save changes' : 'Create config'}
                    </Button>
                </>
            }
        >
            {error && <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
            <div className="space-y-4">
                {sel('class_id', 'Class', options.classes)}
                {sel('section_id', 'Section', options.sections)}
                {sel('shift_id', 'Shift', options.shifts)}
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Serial</label>
                    <input type="number" value={form.serial} onChange={(e) => setForm((f) => ({ ...f, serial: Number(e.target.value) }))} className={cn(selCls)} />
                </div>
            </div>
        </Modal>
    );
}
