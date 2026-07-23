import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Loader2, Inbox } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { cn } from '@/lib/cn';
import { listSetup, type SetupRow } from '@/features/academic/api';
import { listPeriods, createPeriod, updatePeriod, deletePeriod, type Period } from './api';

export function PeriodsPanel() {
    const qc = useQueryClient();
    const { data: periods = [], isLoading } = useQuery({ queryKey: ['periods'], queryFn: () => listPeriods() });
    const { data: shifts = [] } = useQuery({ queryKey: ['setup', 'shifts'], queryFn: () => listSetup('shifts') });

    const [editing, setEditing] = useState<Period | null>(null);
    const [creating, setCreating] = useState(false);
    const [deleting, setDeleting] = useState<Period | null>(null);
    const invalidate = () => qc.invalidateQueries({ queryKey: ['periods'] });

    const del = useMutation({
        mutationFn: (id: number) => deletePeriod(id),
        onSuccess: () => { invalidate(); setDeleting(null); },
    });

    return (
        <Card>
            <div className="flex items-center justify-between px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Periods</h3>
                    <p className="text-[12.5px] text-muted">Timetable slots shared by class routines and period-wise attendance</p>
                </div>
                <Button onClick={() => setCreating(true)} disabled={!shifts.length}><Plus size={16} /> Add period</Button>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : periods.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                        <div className="grid h-12 w-12 place-items-center rounded-2xl bg-surface-2 text-faint"><Inbox size={22} /></div>
                        <p className="text-[14px] font-medium text-fg">No periods yet</p>
                        <p className="text-[13px] text-muted">Add periods for each shift to build the timetable grid.</p>
                    </div>
                ) : (
                    <table className="w-full min-w-[560px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Period</th>
                                <th className="px-5 py-2.5 font-semibold">Shift</th>
                                <th className="px-5 py-2.5 font-semibold">Time</th>
                                <th className="px-5 py-2.5 font-semibold">Status</th>
                                <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {periods.map((p) => (
                                <tr key={p.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                    <td className="px-5 py-3 font-medium text-fg">{p.name}</td>
                                    <td className="px-5 py-3 text-muted">{p.shift_name}</td>
                                    <td className="tnum px-5 py-3 text-muted">{p.start_time}–{p.end_time}</td>
                                    <td className="px-5 py-3"><Badge tone={p.status ? 'success' : 'neutral'}>{p.status ? 'Active' : 'Inactive'}</Badge></td>
                                    <td className="px-5 py-3">
                                        <div className="flex justify-end gap-1">
                                            <button onClick={() => setEditing(p)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600" aria-label="Edit"><Pencil size={16} /></button>
                                            <button onClick={() => setDeleting(p)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500" aria-label="Delete"><Trash2 size={16} /></button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {(creating || editing) && (
                <PeriodForm
                    shifts={shifts}
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
                title="Delete period"
                message={`Delete "${deleting?.name}"? Any routine slots using it will also be removed.`}
            />
        </Card>
    );
}

function PeriodForm({
    shifts, row, onClose, onSaved,
}: {
    shifts: SetupRow[];
    row: Period | null;
    onClose: () => void;
    onSaved: () => void;
}) {
    const [form, setForm] = useState({
        shift_id: row?.shift_id ?? shifts[0]?.id ?? 0,
        name: row?.name ?? '',
        start_time: row?.start_time ?? '09:00',
        end_time: row?.end_time ?? '09:45',
        serial: row?.serial ?? 0,
    });
    const [error, setError] = useState<string | null>(null);

    const save = useMutation({
        mutationFn: () => (row ? updatePeriod(row.id, form) : createPeriod(form)),
        onSuccess: onSaved,
        onError: (e) => setError(toApiError(e).message),
    });

    const inputCls = 'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';

    return (
        <Modal
            open onClose={onClose}
            title={row ? 'Edit period' : 'Add period'}
            footer={
                <>
                    <Button variant="outline" onClick={onClose} disabled={save.isPending}>Cancel</Button>
                    <Button onClick={() => { setError(null); save.mutate(); }} disabled={save.isPending || !form.name || !form.shift_id}>
                        {save.isPending ? <Loader2 size={16} className="animate-spin" /> : null}
                        {row ? 'Save changes' : 'Create period'}
                    </Button>
                </>
            }
        >
            {error && <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
            <div className="space-y-4">
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Shift <span className="text-rose-500">*</span></label>
                    <select value={form.shift_id} onChange={(e) => setForm((f) => ({ ...f, shift_id: Number(e.target.value) }))} className={inputCls}>
                        {shifts.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                    </select>
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Name <span className="text-rose-500">*</span></label>
                    <input value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} placeholder="e.g. Period 1" className={inputCls} />
                </div>
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">Start time</label>
                        <input type="time" value={form.start_time} onChange={(e) => setForm((f) => ({ ...f, start_time: e.target.value }))} className={cn(inputCls, 'tnum')} />
                    </div>
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">End time</label>
                        <input type="time" value={form.end_time} onChange={(e) => setForm((f) => ({ ...f, end_time: e.target.value }))} className={cn(inputCls, 'tnum')} />
                    </div>
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Serial</label>
                    <input type="number" value={form.serial} onChange={(e) => setForm((f) => ({ ...f, serial: Number(e.target.value) }))} className={inputCls} />
                </div>
            </div>
        </Modal>
    );
}
