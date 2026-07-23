import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Loader2 } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { cn } from '@/lib/cn';
import { classConfigOptions } from '@/features/academic/api';
import { listGrades, createGrade, updateGrade, deleteGrade, type Grade } from './api';

export function GradesPanel() {
    const { data: options } = useQuery({ queryKey: ['class-config-options'], queryFn: classConfigOptions });
    const [classId, setClassId] = useState(0);
    const qc = useQueryClient();

    const { data: grades = [], isLoading } = useQuery({
        queryKey: ['grades', classId],
        queryFn: () => listGrades(classId),
        enabled: !!classId,
    });

    const [editing, setEditing] = useState<Grade | null>(null);
    const [creating, setCreating] = useState(false);
    const [deleting, setDeleting] = useState<Grade | null>(null);
    const invalidate = () => qc.invalidateQueries({ queryKey: ['grades', classId] });

    const del = useMutation({
        mutationFn: (id: number) => deleteGrade(id),
        onSuccess: () => { invalidate(); setDeleting(null); },
    });

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Grade scale</h3>
                    <p className="text-[12.5px] text-muted">Percentage range → grade point, per class</p>
                </div>
                <div className="flex gap-2">
                    <select
                        value={classId}
                        onChange={(e) => setClassId(Number(e.target.value))}
                        className="rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25"
                    >
                        <option value={0} disabled>Select a class</option>
                        {options?.classes.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                    {classId > 0 && <Button onClick={() => setCreating(true)}><Plus size={16} /> Add grade</Button>}
                </div>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {!classId ? (
                    <div className="flex items-center justify-center py-16 text-[14px] font-medium text-fg">Select a class to view its grade scale</div>
                ) : isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : (
                    <table className="w-full min-w-[520px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Grade</th>
                                <th className="px-5 py-2.5 font-semibold">Grade point</th>
                                <th className="px-5 py-2.5 font-semibold">Mark range (%)</th>
                                <th className="px-5 py-2.5 font-semibold">Status</th>
                                <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {grades.map((g) => (
                                <tr key={g.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                    <td className="px-5 py-3 font-medium text-fg">{g.name}</td>
                                    <td className="tnum px-5 py-3 text-muted">{g.grade_point}</td>
                                    <td className="tnum px-5 py-3 text-muted">{g.mark_from} – {g.mark_to}</td>
                                    <td className="px-5 py-3"><Badge tone={g.status ? 'success' : 'neutral'}>{g.status ? 'Active' : 'Inactive'}</Badge></td>
                                    <td className="px-5 py-3">
                                        <div className="flex justify-end gap-1">
                                            <button onClick={() => setEditing(g)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600" aria-label="Edit"><Pencil size={16} /></button>
                                            <button onClick={() => setDeleting(g)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500" aria-label="Delete"><Trash2 size={16} /></button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {(creating || editing) && (
                <GradeForm
                    classId={classId}
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
                title="Delete grade"
                message={`Remove grade "${deleting?.name}"?`}
            />
        </Card>
    );
}

function GradeForm({ classId, row, onClose, onSaved }: { classId: number; row: Grade | null; onClose: () => void; onSaved: () => void }) {
    const [form, setForm] = useState({
        name: row?.name ?? '',
        grade_point: row?.grade_point ?? '',
        mark_from: row?.mark_from ?? '',
        mark_to: row?.mark_to ?? '',
    });
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [error, setError] = useState<string | null>(null);

    const save = useMutation({
        mutationFn: () => {
            const payload = { class_id: classId, ...form };
            return row ? updateGrade(row.id, payload) : createGrade(payload);
        },
        onSuccess: onSaved,
        onError: (e) => { const a = toApiError(e); setError(a.errors ? null : a.message); setErrors(a.errors ?? {}); },
    });

    const inputCls = (name: string) => cn(
        'w-full rounded-xl border bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25',
        errors[name] ? 'border-rose-400' : 'border-border-strong',
    );

    return (
        <Modal
            open onClose={onClose}
            title={row ? 'Edit grade' : 'Add grade'}
            footer={
                <>
                    <Button variant="outline" onClick={onClose} disabled={save.isPending}>Cancel</Button>
                    <Button onClick={() => { setError(null); setErrors({}); save.mutate(); }} disabled={save.isPending}>
                        {save.isPending ? <Loader2 size={16} className="animate-spin" /> : null}
                        {row ? 'Save changes' : 'Create'}
                    </Button>
                </>
            }
        >
            {error && <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
            <div className="space-y-4">
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Grade name <span className="text-rose-500">*</span></label>
                    <input value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} placeholder="e.g. A+" className={inputCls('name')} />
                    {errors.name && <p className="mt-1.5 text-[12px] text-rose-500">{errors.name[0]}</p>}
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Grade point <span className="text-rose-500">*</span></label>
                    <input type="number" step="0.01" value={form.grade_point} onChange={(e) => setForm((f) => ({ ...f, grade_point: e.target.value }))} className={inputCls('grade_point')} />
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">From (%) <span className="text-rose-500">*</span></label>
                        <input type="number" step="0.01" value={form.mark_from} onChange={(e) => setForm((f) => ({ ...f, mark_from: e.target.value }))} className={inputCls('mark_from')} />
                    </div>
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">To (%) <span className="text-rose-500">*</span></label>
                        <input type="number" step="0.01" value={form.mark_to} onChange={(e) => setForm((f) => ({ ...f, mark_to: e.target.value }))} className={inputCls('mark_to')} />
                    </div>
                </div>
                {errors.mark_to && <p className="text-[12px] text-rose-500">{errors.mark_to[0]}</p>}
            </div>
        </Modal>
    );
}
