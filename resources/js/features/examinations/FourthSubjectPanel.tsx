import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2, Save } from 'lucide-react';
import { Card, Button } from '@/components/ui';
import { toApiError } from '@/lib/api';
import { listSetup as listAcademicSetup, listClassConfigs } from '@/features/academic/api';
import { markConfigOptions, fourthSubjectRoster, saveFourthSubjects } from './api';

export function FourthSubjectPanel() {
    const { data: years = [] } = useQuery({ queryKey: ['exam-academic-years'], queryFn: () => listAcademicSetup('academic-years') });
    const { data: classConfigs = [] } = useQuery({ queryKey: ['class-configs'], queryFn: listClassConfigs });
    const { data: subjectOptions } = useQuery({ queryKey: ['mark-config-options'], queryFn: markConfigOptions });

    const [academicYearId, setAcademicYearId] = useState(0);
    const [classConfigId, setClassConfigId] = useState(0);
    const qc = useQueryClient();

    useEffect(() => {
        const current = years.find((y) => y.is_current);
        if (current && !academicYearId) setAcademicYearId(current.id);
    }, [years, academicYearId]);

    const ready = !!academicYearId && !!classConfigId;
    const rosterKey = ['fourth-subjects', academicYearId, classConfigId];

    const { data: roster = [], isLoading } = useQuery({
        queryKey: rosterKey,
        queryFn: () => fourthSubjectRoster({ academic_year_id: academicYearId, class_config_id: classConfigId }),
        enabled: ready,
    });

    const [assignments, setAssignments] = useState<Record<number, number>>({});
    useEffect(() => {
        setAssignments(Object.fromEntries(roster.filter((r) => r.subject_id).map((r) => [r.student_id, r.subject_id as number])));
    }, [roster]);

    const save = useMutation({
        mutationFn: () => saveFourthSubjects({
            academic_year_id: academicYearId,
            class_config_id: classConfigId,
            assignments: Object.entries(assignments)
                .filter(([, subjectId]) => subjectId > 0)
                .map(([studentId, subjectId]) => ({ student_id: Number(studentId), subject_id: subjectId })),
        }),
        onSuccess: () => qc.invalidateQueries({ queryKey: rosterKey }),
    });

    const selectCls = 'rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Fourth / optional subject</h3>
                    <p className="text-[12.5px] text-muted">Assign each student's optional subject (GPA bonus applies above grade point 2.0)</p>
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
                </div>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {!ready ? (
                    <div className="flex items-center justify-center py-16 text-[14px] font-medium text-fg">Select session and class</div>
                ) : isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : roster.length === 0 ? (
                    <div className="flex items-center justify-center py-16 text-[14px] font-medium text-fg">No students enrolled</div>
                ) : (
                    <table className="w-full min-w-[480px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Roll</th>
                                <th className="px-5 py-2.5 font-semibold">Name</th>
                                <th className="px-5 py-2.5 font-semibold">Fourth subject</th>
                            </tr>
                        </thead>
                        <tbody>
                            {roster.map((r) => (
                                <tr key={r.student_id} className="border-b border-border last:border-0">
                                    <td className="tnum px-5 py-2 text-muted">{r.roll}</td>
                                    <td className="px-5 py-2 font-medium text-fg">{r.name}</td>
                                    <td className="p-1.5">
                                        <select
                                            value={assignments[r.student_id] ?? 0}
                                            onChange={(e) => setAssignments((a) => ({ ...a, [r.student_id]: Number(e.target.value) }))}
                                            className="w-52 rounded-lg border border-border-strong bg-surface px-2 py-1.5 text-[13px] text-fg outline-none focus:border-brand-500"
                                        >
                                            <option value={0}>— None —</option>
                                            {subjectOptions?.subjects.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                                        </select>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {ready && roster.length > 0 && (
                <div className="flex items-center justify-end gap-3 border-t border-border px-5 py-4">
                    {save.isError && <span className="text-[13px] text-rose-500">{toApiError(save.error).message}</span>}
                    {save.isSuccess && <span className="text-[13px] text-emerald-600">Saved.</span>}
                    <Button onClick={() => save.mutate()} disabled={save.isPending}>
                        {save.isPending ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />} Save assignments
                    </Button>
                </div>
            )}
        </Card>
    );
}
