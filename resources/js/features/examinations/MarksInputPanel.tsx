import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2, Save } from 'lucide-react';
import { Card, Button } from '@/components/ui';
import { toApiError } from '@/lib/api';
import { listSetup as listAcademicSetup, listClassConfigs } from '@/features/academic/api';
import { listSetup, markConfigOptions, marksGrid, saveMarks } from './api';

export function MarksInputPanel() {
    const { data: years = [] } = useQuery({ queryKey: ['exam-academic-years'], queryFn: () => listAcademicSetup('academic-years') });
    const { data: classConfigs = [] } = useQuery({ queryKey: ['class-configs'], queryFn: listClassConfigs });
    const { data: exams = [] } = useQuery({ queryKey: ['exam-setup', 'exams'], queryFn: () => listSetup('exams') });
    const { data: subjectOptions } = useQuery({ queryKey: ['mark-config-options'], queryFn: markConfigOptions });

    const [academicYearId, setAcademicYearId] = useState(0);
    const [classConfigId, setClassConfigId] = useState(0);
    const [examId, setExamId] = useState(0);
    const [subjectId, setSubjectId] = useState(0);
    const qc = useQueryClient();

    useEffect(() => {
        const current = years.find((y) => y.is_current);
        if (current && !academicYearId) setAcademicYearId(current.id);
    }, [years, academicYearId]);

    const ready = !!academicYearId && !!classConfigId && !!examId && !!subjectId;
    const gridKey = ['marks-grid', academicYearId, classConfigId, examId, subjectId];

    const { data: grid, isLoading, isError, error: gridError } = useQuery({
        queryKey: gridKey,
        queryFn: () => marksGrid({ academic_year_id: academicYearId, class_config_id: classConfigId, exam_id: examId, subject_id: subjectId }),
        enabled: ready,
        retry: false,
    });

    const [entries, setEntries] = useState<Record<number, { is_absent: boolean; marks: Record<number, string> }>>({});

    useEffect(() => {
        if (!grid) return;
        const next: Record<number, { is_absent: boolean; marks: Record<number, string> }> = {};
        for (const s of grid.students) {
            const marks: Record<number, string> = {};
            for (const c of grid.components) marks[c.mark_config_id] = s.marks[c.mark_config_id] != null ? String(s.marks[c.mark_config_id]) : '';
            next[s.student_id] = { is_absent: s.is_absent, marks };
        }
        setEntries(next);
    }, [grid]);

    const setAbsent = (studentId: number, absent: boolean) => setEntries((e) => ({ ...e, [studentId]: { ...e[studentId], is_absent: absent } }));
    const setMark = (studentId: number, markConfigId: number, value: string) =>
        setEntries((e) => ({ ...e, [studentId]: { ...e[studentId], marks: { ...e[studentId]?.marks, [markConfigId]: value } } }));

    const save = useMutation({
        mutationFn: () => saveMarks({
            exam_id: examId,
            entries: Object.entries(entries).map(([studentId, e]) => ({
                student_id: Number(studentId),
                is_absent: e.is_absent,
                marks: Object.entries(e.marks)
                    .filter(([, v]) => v !== '')
                    .map(([markConfigId, v]) => ({ mark_config_id: Number(markConfigId), obtained: Number(v) })),
            })),
        }),
        onSuccess: () => qc.invalidateQueries({ queryKey: gridKey }),
    });

    const selectCls = 'rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Marks input</h3>
                    <p className="text-[12.5px] text-muted">Enter marks per component for one class × exam × subject</p>
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
                    <select value={subjectId} onChange={(e) => setSubjectId(Number(e.target.value))} className={selectCls}>
                        <option value={0} disabled>Subject</option>
                        {subjectOptions?.subjects.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                    </select>
                </div>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {!ready ? (
                    <div className="flex items-center justify-center py-16 text-[14px] font-medium text-fg">Select session, class, exam and subject</div>
                ) : isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : isError ? (
                    <div className="flex items-center justify-center py-16 text-[13.5px] text-rose-500">{toApiError(gridError).message}</div>
                ) : grid && grid.students.length > 0 ? (
                    <table className="w-full min-w-[560px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Roll</th>
                                <th className="px-5 py-2.5 font-semibold">Name</th>
                                {grid.components.map((c) => (
                                    <th key={c.mark_config_id} className="px-3 py-2.5 font-semibold">{c.short_code_name} <span className="text-faint">/{c.total_marks}</span></th>
                                ))}
                                <th className="px-3 py-2.5 font-semibold">Absent</th>
                            </tr>
                        </thead>
                        <tbody>
                            {grid.students.map((s) => {
                                const entry = entries[s.student_id];

                                return (
                                    <tr key={s.student_id} className="border-b border-border last:border-0">
                                        <td className="tnum px-5 py-2 text-muted">{s.roll}</td>
                                        <td className="px-5 py-2 font-medium text-fg">{s.name}</td>
                                        {grid.components.map((c) => (
                                            <td key={c.mark_config_id} className="p-1.5">
                                                <input
                                                    type="number"
                                                    disabled={entry?.is_absent}
                                                    value={entry?.marks[c.mark_config_id] ?? ''}
                                                    onChange={(e) => setMark(s.student_id, c.mark_config_id, e.target.value)}
                                                    className="w-20 rounded-lg border border-border-strong bg-surface px-2 py-1.5 text-[13px] text-fg outline-none focus:border-brand-500 disabled:opacity-40"
                                                />
                                            </td>
                                        ))}
                                        <td className="px-3 py-2">
                                            <input type="checkbox" checked={!!entry?.is_absent} onChange={(e) => setAbsent(s.student_id, e.target.checked)}
                                                className="h-4 w-4 rounded border-border-strong text-brand-600 focus:ring-brand-500" />
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                ) : (
                    <div className="flex items-center justify-center py-16 text-[14px] font-medium text-fg">No students enrolled for this class</div>
                )}
            </div>

            {ready && grid && grid.students.length > 0 && (
                <div className="flex items-center justify-end gap-3 border-t border-border px-5 py-4">
                    {save.isError && <span className="text-[13px] text-rose-500">{toApiError(save.error).message}</span>}
                    {save.isSuccess && <span className="text-[13px] text-emerald-600">Saved.</span>}
                    <Button onClick={() => save.mutate()} disabled={save.isPending}>
                        {save.isPending ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />} Save marks
                    </Button>
                </div>
            )}
        </Card>
    );
}
