import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2, Plus, Trash2, Pencil } from 'lucide-react';
import { Card, Button } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { listSetup as listAcademicSetup, listClassConfigs } from '@/features/academic/api';
import {
    listSetup, examRoutineOptions, listExamRoutine, createExamRoutine, updateExamRoutine, deleteExamRoutine,
    type ExamRoutineRow,
} from './api';

export function ExamRoutinePanel() {
    const { data: years = [] } = useQuery({ queryKey: ['exam-academic-years'], queryFn: () => listAcademicSetup('academic-years') });
    const { data: classConfigs = [] } = useQuery({ queryKey: ['class-configs'], queryFn: listClassConfigs });
    const { data: exams = [] } = useQuery({ queryKey: ['exam-setup', 'exams'], queryFn: () => listSetup('exams') });

    const [academicYearId, setAcademicYearId] = useState(0);
    const [classConfigId, setClassConfigId] = useState(0);
    const [examId, setExamId] = useState(0);
    const qc = useQueryClient();

    const ready = !!classConfigId && !!examId;
    const listKey = ['exam-routine', classConfigId, examId];

    const { data: rows = [], isLoading } = useQuery({
        queryKey: listKey,
        queryFn: () => listExamRoutine({ class_config_id: classConfigId, exam_id: examId }),
        enabled: ready,
    });
    const { data: options } = useQuery({
        queryKey: ['exam-routine-options', classConfigId, examId],
        queryFn: () => examRoutineOptions({ class_config_id: classConfigId, exam_id: examId }),
        enabled: ready,
    });

    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<ExamRoutineRow | null>(null);
    const [deleting, setDeleting] = useState<ExamRoutineRow | null>(null);
    const invalidate = () => qc.invalidateQueries({ queryKey: listKey });

    const del = useMutation({
        mutationFn: (id: number) => deleteExamRoutine(id),
        onSuccess: () => { invalidate(); setDeleting(null); },
    });

    const selectCls = 'rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Exam routine</h3>
                    <p className="text-[12.5px] text-muted">Subject-wise exam date, time and room</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <select value={academicYearId} onChange={(e) => setAcademicYearId(Number(e.target.value))} className={selectCls}>
                        <option value={0} disabled>Session</option>
                        {years.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
                    </select>
                    <select value={classConfigId} onChange={(e) => setClassConfigId(Number(e.target.value))} className={selectCls}>
                        <option value={0} disabled>Class</option>
                        {classConfigs.map((c) => <option key={c.id} value={c.id}>{c.label}</option>)}
                    </select>
                    <select value={examId} onChange={(e) => setExamId(Number(e.target.value))} className={selectCls}>
                        <option value={0} disabled>Exam</option>
                        {exams.map((e) => <option key={e.id} value={e.id}>{e.name}</option>)}
                    </select>
                    {ready && <Button onClick={() => setCreating(true)}><Plus size={16} /> Add slot</Button>}
                </div>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {!ready ? (
                    <div className="flex items-center justify-center py-16 text-[14px] font-medium text-fg">Select a class and an exam</div>
                ) : isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : rows.length === 0 ? (
                    <div className="flex items-center justify-center py-16 text-[14px] font-medium text-fg">No exam routine yet</div>
                ) : (
                    <table className="w-full min-w-[640px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Subject</th>
                                <th className="px-5 py-2.5 font-semibold">Date</th>
                                <th className="px-5 py-2.5 font-semibold">Time</th>
                                <th className="px-5 py-2.5 font-semibold">Room</th>
                                <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={r.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                    <td className="px-5 py-3 font-medium text-fg">{r.subject_name}</td>
                                    <td className="tnum px-5 py-3 text-muted">{r.exam_date}</td>
                                    <td className="tnum px-5 py-3 text-muted">{r.start_time}–{r.end_time}</td>
                                    <td className="px-5 py-3 text-muted">{r.room_no ?? '—'}</td>
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
                <RoutineForm
                    academicYearId={academicYearId}
                    classConfigId={classConfigId}
                    examId={examId}
                    subjects={options.subjects}
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
                title="Remove exam routine slot"
                message={`Remove ${deleting?.subject_name} from the routine?`}
            />
        </Card>
    );
}

function RoutineForm({
    academicYearId, classConfigId, examId, subjects, row, onClose, onSaved,
}: {
    academicYearId: number;
    classConfigId: number;
    examId: number;
    subjects: { id: number; name: string }[];
    row: ExamRoutineRow | null;
    onClose: () => void;
    onSaved: () => void;
}) {
    const [form, setForm] = useState({
        subject_id: row?.subject_id ?? 0,
        exam_date: row?.exam_date ?? '',
        start_time: row?.start_time ?? '',
        end_time: row?.end_time ?? '',
        room_no: row?.room_no ?? '',
        exam_session: row?.exam_session ?? '',
    });
    const [error, setError] = useState<string | null>(null);

    const payload = () => ({ academic_year_id: academicYearId, class_config_id: classConfigId, exam_id: examId, ...form });

    const save = useMutation({
        mutationFn: () => (row ? updateExamRoutine(row.id, payload()) : createExamRoutine(payload())),
        onSuccess: onSaved,
        onError: (e) => setError(toApiError(e).message),
    });

    const inputCls = 'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';

    return (
        <Modal
            open onClose={onClose}
            title={row ? 'Edit exam routine slot' : 'Add exam routine slot'}
            footer={
                <>
                    <Button variant="outline" onClick={onClose} disabled={save.isPending}>Cancel</Button>
                    <Button onClick={() => { setError(null); save.mutate(); }} disabled={save.isPending || !form.subject_id || !form.exam_date}>
                        {save.isPending ? <Loader2 size={16} className="animate-spin" /> : null}
                        {row ? 'Save changes' : 'Add'}
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
                        {subjects.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                    </select>
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Date <span className="text-rose-500">*</span></label>
                    <input type="date" value={form.exam_date} onChange={(e) => setForm((f) => ({ ...f, exam_date: e.target.value }))} className={inputCls} />
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">Start time <span className="text-rose-500">*</span></label>
                        <input type="time" value={form.start_time} onChange={(e) => setForm((f) => ({ ...f, start_time: e.target.value }))} className={inputCls} />
                    </div>
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">End time <span className="text-rose-500">*</span></label>
                        <input type="time" value={form.end_time} onChange={(e) => setForm((f) => ({ ...f, end_time: e.target.value }))} className={inputCls} />
                    </div>
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Room</label>
                    <input value={form.room_no} onChange={(e) => setForm((f) => ({ ...f, room_no: e.target.value }))} className={inputCls} />
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Session</label>
                    <input value={form.exam_session} onChange={(e) => setForm((f) => ({ ...f, exam_session: e.target.value }))} placeholder="e.g. Morning" className={inputCls} />
                </div>
            </div>
        </Modal>
    );
}
