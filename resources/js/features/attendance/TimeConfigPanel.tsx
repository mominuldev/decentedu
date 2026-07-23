import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Loader2, Inbox } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { cn } from '@/lib/cn';
import { listClassConfigs } from '@/features/academic/api';
import { listTimeConfigs, createTimeConfig, updateTimeConfig, deleteTimeConfig, type TimeConfig } from './api';

export function TimeConfigPanel() {
    const qc = useQueryClient();
    const { data: configs = [], isLoading } = useQuery({ queryKey: ['time-configs'], queryFn: () => listTimeConfigs() });
    const { data: classConfigs = [] } = useQuery({ queryKey: ['class-configs'], queryFn: listClassConfigs });

    const [editing, setEditing] = useState<TimeConfig | null>(null);
    const [creating, setCreating] = useState(false);
    const [deleting, setDeleting] = useState<TimeConfig | null>(null);
    const invalidate = () => qc.invalidateQueries({ queryKey: ['time-configs'] });
    const del = useMutation({ mutationFn: (id: number) => deleteTimeConfig(id), onSuccess: () => { invalidate(); setDeleting(null); } });

    return (
        <Card>
            <div className="flex items-center justify-between px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Time configuration</h3>
                    <p className="text-[12.5px] text-muted">Expected in/out time and the grace period before "late"</p>
                </div>
                <Button onClick={() => setCreating(true)}><Plus size={16} /> Add config</Button>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : configs.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                        <div className="grid h-12 w-12 place-items-center rounded-2xl bg-surface-2 text-faint"><Inbox size={22} /></div>
                        <p className="text-[14px] font-medium text-fg">No time configs yet</p>
                    </div>
                ) : (
                    <table className="w-full min-w-[640px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Scope</th>
                                <th className="px-5 py-2.5 font-semibold">In time</th>
                                <th className="px-5 py-2.5 font-semibold">Out time</th>
                                <th className="px-5 py-2.5 font-semibold">Late after</th>
                                <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {configs.map((c) => (
                                <tr key={c.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                    <td className="px-5 py-3">
                                        <Badge tone={c.applicable_to === 'student' ? 'brand' : 'sky'}>{c.applicable_to}</Badge>
                                        <span className="ml-2 text-muted">{c.class_label ?? 'All classes'}</span>
                                    </td>
                                    <td className="tnum px-5 py-3 text-muted">{c.in_time}</td>
                                    <td className="tnum px-5 py-3 text-muted">{c.out_time}</td>
                                    <td className="tnum px-5 py-3 text-muted">{c.late_after}</td>
                                    <td className="px-5 py-3">
                                        <div className="flex justify-end gap-1">
                                            <button onClick={() => setEditing(c)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600" aria-label="Edit"><Pencil size={16} /></button>
                                            <button onClick={() => setDeleting(c)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500" aria-label="Delete"><Trash2 size={16} /></button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {(creating || editing) && (
                <TimeConfigForm
                    classConfigs={classConfigs}
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
                title="Delete time config"
                message="Delete this time configuration?"
            />
        </Card>
    );
}

function TimeConfigForm({
    classConfigs, row, onClose, onSaved,
}: {
    classConfigs: { id: number; label: string }[];
    row: TimeConfig | null;
    onClose: () => void;
    onSaved: () => void;
}) {
    const [form, setForm] = useState({
        applicable_to: row?.applicable_to ?? 'student',
        class_config_id: row?.class_config_id ?? 0,
        in_time: row?.in_time ?? '09:00',
        out_time: row?.out_time ?? '16:00',
        late_after: row?.late_after ?? '09:10',
    });
    const [error, setError] = useState<string | null>(null);

    const payload = () => ({ ...form, class_config_id: form.class_config_id || null });

    const save = useMutation({
        mutationFn: () => (row ? updateTimeConfig(row.id, payload()) : createTimeConfig(payload())),
        onSuccess: onSaved,
        onError: (e) => setError(toApiError(e).message),
    });

    const inputCls = 'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';

    return (
        <Modal
            open onClose={onClose}
            title={row ? 'Edit time config' : 'Add time config'}
            footer={
                <>
                    <Button variant="outline" onClick={onClose} disabled={save.isPending}>Cancel</Button>
                    <Button onClick={() => { setError(null); save.mutate(); }} disabled={save.isPending}>
                        {save.isPending ? <Loader2 size={16} className="animate-spin" /> : null}
                        {row ? 'Save changes' : 'Create config'}
                    </Button>
                </>
            }
        >
            {error && <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
            <div className="space-y-4">
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Applies to</label>
                    <select value={form.applicable_to} onChange={(e) => setForm((f) => ({ ...f, applicable_to: e.target.value as 'student' | 'employee' }))} className={inputCls}>
                        <option value="student">Students</option>
                        <option value="employee">Employees</option>
                    </select>
                </div>
                {form.applicable_to === 'student' && (
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">Class (optional — leave blank for the branch default)</label>
                        <select value={form.class_config_id} onChange={(e) => setForm((f) => ({ ...f, class_config_id: Number(e.target.value) }))} className={inputCls}>
                            <option value={0}>All classes (default)</option>
                            {classConfigs.map((c) => <option key={c.id} value={c.id}>{c.label}</option>)}
                        </select>
                    </div>
                )}
                <div className="grid grid-cols-3 gap-3">
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">In time</label>
                        <input type="time" value={form.in_time} onChange={(e) => setForm((f) => ({ ...f, in_time: e.target.value }))} className={cn(inputCls, 'tnum')} />
                    </div>
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">Out time</label>
                        <input type="time" value={form.out_time} onChange={(e) => setForm((f) => ({ ...f, out_time: e.target.value }))} className={cn(inputCls, 'tnum')} />
                    </div>
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">Late after</label>
                        <input type="time" value={form.late_after} onChange={(e) => setForm((f) => ({ ...f, late_after: e.target.value }))} className={cn(inputCls, 'tnum')} />
                    </div>
                </div>
            </div>
        </Modal>
    );
}
