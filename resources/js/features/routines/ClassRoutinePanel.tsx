import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2, Plus, Trash2, Printer } from 'lucide-react';
import { Card, Button } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { cn } from '@/lib/cn';
import { listClassConfigs } from '@/features/academic/api';
import {
    classRoutine, routineOptions, createRoutineSlot, updateRoutineSlot, deleteRoutineSlot,
    DAYS, type RoutineSlot, type RoutineOptions,
} from './api';

export function ClassRoutinePanel() {
    const { data: classConfigs = [] } = useQuery({ queryKey: ['class-configs'], queryFn: listClassConfigs });
    const [classConfigId, setClassConfigId] = useState<number>(0);
    const qc = useQueryClient();

    const { data: slots = [], isLoading } = useQuery({
        queryKey: ['class-routine', classConfigId],
        queryFn: () => classRoutine(classConfigId),
        enabled: !!classConfigId,
    });
    const { data: options } = useQuery({
        queryKey: ['routine-options', classConfigId],
        queryFn: () => routineOptions(classConfigId),
        enabled: !!classConfigId,
    });

    const [editing, setEditing] = useState<{ day: number; periodId: number; slot: RoutineSlot | null } | null>(null);
    const [deleting, setDeleting] = useState<RoutineSlot | null>(null);
    const invalidate = () => qc.invalidateQueries({ queryKey: ['class-routine', classConfigId] });

    const del = useMutation({
        mutationFn: (id: number) => deleteRoutineSlot(id),
        onSuccess: () => { invalidate(); setDeleting(null); },
    });

    const grid = useMemo(() => {
        const map = new Map<string, RoutineSlot>();
        for (const s of slots) map.set(`${s.day_of_week}:${s.period_id}`, s);
        return map;
    }, [slots]);

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Class routine</h3>
                    <p className="text-[12.5px] text-muted">Weekly day × period grid — one subject per class per slot</p>
                </div>
                <div className="flex items-center gap-2">
                    <select
                        value={classConfigId}
                        onChange={(e) => setClassConfigId(Number(e.target.value))}
                        className="rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25"
                    >
                        <option value={0} disabled>Select a class</option>
                        {classConfigs.map((c) => <option key={c.id} value={c.id}>{c.label}</option>)}
                    </select>
                    {!!classConfigId && (
                        <a href={`/print/routine/${classConfigId}`} target="_blank" rel="noreferrer">
                            <Button variant="outline"><Printer size={16} /> Print</Button>
                        </a>
                    )}
                </div>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {!classConfigId ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                        <p className="text-[14px] font-medium text-fg">Select a class to view its routine</p>
                    </div>
                ) : isLoading || !options ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : options.periods.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                        <p className="text-[14px] font-medium text-fg">No periods defined for this class's shift</p>
                        <p className="text-[13px] text-muted">Add periods on the Periods tab first.</p>
                    </div>
                ) : (
                    <table className="w-full min-w-[900px] table-fixed text-left text-[13px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="w-28 px-3 py-2.5 font-semibold">Day</th>
                                {options.periods.map((p) => (
                                    <th key={p.id} className="px-2 py-2.5 font-semibold">
                                        <div>{p.name}</div>
                                        <div className="tnum font-normal normal-case text-faint">{p.start_time}–{p.end_time}</div>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {DAYS.map((d) => (
                                <tr key={d.value} className="border-b border-border last:border-0">
                                    <td className="px-3 py-2 font-medium text-fg">{d.label}</td>
                                    {options.periods.map((p) => {
                                        const slot = grid.get(`${d.value}:${p.id}`);
                                        return (
                                            <td key={p.id} className="p-1.5 align-top">
                                                {slot ? (
                                                    <button
                                                        onClick={() => setEditing({ day: d.value, periodId: p.id, slot })}
                                                        className="w-full rounded-lg border border-brand-200 bg-brand-50 px-2 py-1.5 text-left hover:bg-brand-100 dark:border-brand-500/25 dark:bg-brand-500/10 dark:hover:bg-brand-500/15"
                                                    >
                                                        <div className="truncate text-[12.5px] font-semibold text-brand-700 dark:text-brand-300">{slot.subject_name}</div>
                                                        <div className="truncate text-[11.5px] text-muted">{slot.employee_name ?? '—'}</div>
                                                        {slot.room && <div className="truncate text-[11px] text-faint">{slot.room}</div>}
                                                    </button>
                                                ) : (
                                                    <button
                                                        onClick={() => setEditing({ day: d.value, periodId: p.id, slot: null })}
                                                        className="grid w-full place-items-center rounded-lg border border-dashed border-border-strong py-2.5 text-faint hover:border-brand-400 hover:text-brand-600"
                                                        aria-label="Add subject"
                                                    >
                                                        <Plus size={14} />
                                                    </button>
                                                )}
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {editing && options && (
                <SlotForm
                    classConfigId={classConfigId}
                    day={editing.day}
                    periodId={editing.periodId}
                    slot={editing.slot}
                    options={options}
                    onDelete={editing.slot ? () => { setDeleting(editing.slot); setEditing(null); } : undefined}
                    onClose={() => setEditing(null)}
                    onSaved={() => { invalidate(); setEditing(null); }}
                />
            )}

            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={() => deleting && del.mutate(deleting.id)}
                busy={del.isPending}
                title="Remove routine slot"
                message={`Remove ${deleting?.subject_name} from this slot?`}
            />
        </Card>
    );
}

function SlotForm({
    classConfigId, day, periodId, slot, options, onClose, onSaved, onDelete,
}: {
    classConfigId: number;
    day: number;
    periodId: number;
    slot: RoutineSlot | null;
    options: RoutineOptions;
    onClose: () => void;
    onSaved: () => void;
    onDelete?: () => void;
}) {
    const [form, setForm] = useState({
        subject_id: slot?.subject_id ?? 0,
        employee_id: slot?.employee_id ?? 0,
        room: slot?.room ?? '',
    });
    const [error, setError] = useState<string | null>(null);

    const payload = () => ({
        class_config_id: classConfigId,
        period_id: periodId,
        day_of_week: day,
        subject_id: form.subject_id,
        employee_id: form.employee_id || null,
        room: form.room || null,
    });

    const save = useMutation({
        mutationFn: () => (slot ? updateRoutineSlot(slot.id, payload()) : createRoutineSlot(payload())),
        onSuccess: onSaved,
        onError: (e) => setError(toApiError(e).message),
    });

    const inputCls = 'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';
    const dayLabel = DAYS.find((d) => d.value === day)?.label;
    const periodLabel = options.periods.find((p) => p.id === periodId)?.name;

    return (
        <Modal
            open onClose={onClose}
            title={`${dayLabel} · ${periodLabel}`}
            footer={
                <>
                    {onDelete && <Button variant="outline" className="mr-auto text-rose-600 hover:bg-rose-50" onClick={onDelete}><Trash2 size={16} /> Remove</Button>}
                    <Button variant="outline" onClick={onClose} disabled={save.isPending}>Cancel</Button>
                    <Button onClick={() => { setError(null); save.mutate(); }} disabled={save.isPending || !form.subject_id}>
                        {save.isPending ? <Loader2 size={16} className="animate-spin" /> : null}
                        {slot ? 'Save changes' : 'Assign'}
                    </Button>
                </>
            }
        >
            {error && <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
            <div className="space-y-4">
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Subject <span className="text-rose-500">*</span></label>
                    <select value={form.subject_id} onChange={(e) => setForm((f) => ({ ...f, subject_id: Number(e.target.value) }))} className={inputCls}>
                        <option value={0} disabled>Select subject</option>
                        {options.subjects.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                    </select>
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Teacher</label>
                    <select value={form.employee_id} onChange={(e) => setForm((f) => ({ ...f, employee_id: Number(e.target.value) }))} className={inputCls}>
                        <option value={0}>— None —</option>
                        {options.employees.map((e) => <option key={e.id} value={e.id}>{e.name}</option>)}
                    </select>
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Room</label>
                    <input value={form.room} onChange={(e) => setForm((f) => ({ ...f, room: e.target.value }))} placeholder="e.g. 201" className={cn(inputCls)} />
                </div>
            </div>
        </Modal>
    );
}
