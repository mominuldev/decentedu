import { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { Loader2, PlayCircle } from 'lucide-react';
import { Card, Button } from '@/components/ui';
import { toApiError } from '@/lib/api';
import { classConfigOptions, listClassConfigs } from '@/features/academic/api';
import { listSetup, generalProcess, finalProcess, meritProcess } from './api';

export function ResultProcessingPanel() {
    const { data: classConfigs = [] } = useQuery({ queryKey: ['class-configs'], queryFn: listClassConfigs });
    const { data: options } = useQuery({ queryKey: ['class-config-options'], queryFn: classConfigOptions });
    const { data: exams = [] } = useQuery({ queryKey: ['exam-setup', 'exams'], queryFn: () => listSetup('exams') });

    const [classConfigId, setClassConfigId] = useState(0);
    const [classId, setClassId] = useState(0);
    const [examId, setExamId] = useState(0);

    const general = useMutation({
        mutationFn: () => generalProcess({ class_config_id: classConfigId, exam_id: examId }),
    });
    const final = useMutation({
        mutationFn: () => finalProcess({ class_config_id: classConfigId, exam_id: examId }),
    });
    const merit = useMutation({
        mutationFn: () => meritProcess({ class_id: classId, exam_id: examId }),
    });

    const selectCls = 'rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';

    return (
        <div className="space-y-5">
            <Card>
                <div className="px-5 py-4">
                    <h3 className="text-[15px] font-semibold text-fg">1 &amp; 2 · General / Final process</h3>
                    <p className="text-[12.5px] text-muted">
                        General process sums each subject's mark components into a grade for one exam. Final process
                        combines the exams configured in Exam Config (equal weight) into a combined exam like Grand Final.
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2 border-t border-border px-5 py-4">
                    <select value={classConfigId} onChange={(e) => setClassConfigId(Number(e.target.value))} className={selectCls}>
                        <option value={0} disabled>Class (section)</option>
                        {classConfigs.map((c) => <option key={c.id} value={c.id}>{c.label}</option>)}
                    </select>
                    <select value={examId} onChange={(e) => setExamId(Number(e.target.value))} className={selectCls}>
                        <option value={0} disabled>Exam</option>
                        {exams.map((e) => <option key={e.id} value={e.id}>{e.name}</option>)}
                    </select>
                    <Button variant="outline" onClick={() => general.mutate()} disabled={!classConfigId || !examId || general.isPending}>
                        {general.isPending ? <Loader2 size={16} className="animate-spin" /> : <PlayCircle size={16} />} Run general process
                    </Button>
                    <Button variant="outline" onClick={() => final.mutate()} disabled={!classConfigId || !examId || final.isPending}>
                        {final.isPending ? <Loader2 size={16} className="animate-spin" /> : <PlayCircle size={16} />} Run final process
                    </Button>
                </div>
                {(general.isError || general.isSuccess || final.isError || final.isSuccess) && (
                    <div className="border-t border-border px-5 py-3 text-[13px]">
                        {general.isError && <p className="text-rose-500">{toApiError(general.error).message}</p>}
                        {general.isSuccess && <p className="text-emerald-600">General process: {general.data.subject_results_processed} subject results processed.</p>}
                        {final.isError && <p className="text-rose-500">{toApiError(final.error).message}</p>}
                        {final.isSuccess && <p className="text-emerald-600">Final process: {final.data.subject_results_processed} subject results processed.</p>}
                    </div>
                )}
            </Card>

            <Card>
                <div className="px-5 py-4">
                    <h3 className="text-[15px] font-semibold text-fg">3 · Merit process</h3>
                    <p className="text-[12.5px] text-muted">Rolls per-subject results into a GPA (with the 4th-subject bonus) and class/section positions — runs across every section of the class.</p>
                </div>
                <div className="flex flex-wrap items-center gap-2 border-t border-border px-5 py-4">
                    <select value={classId} onChange={(e) => setClassId(Number(e.target.value))} className={selectCls}>
                        <option value={0} disabled>Class</option>
                        {options?.classes.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                    <select value={examId} onChange={(e) => setExamId(Number(e.target.value))} className={selectCls}>
                        <option value={0} disabled>Exam</option>
                        {exams.map((e) => <option key={e.id} value={e.id}>{e.name}</option>)}
                    </select>
                    <Button variant="outline" onClick={() => merit.mutate()} disabled={!classId || !examId || merit.isPending}>
                        {merit.isPending ? <Loader2 size={16} className="animate-spin" /> : <PlayCircle size={16} />} Run merit process
                    </Button>
                </div>
                {(merit.isError || merit.isSuccess) && (
                    <div className="border-t border-border px-5 py-3 text-[13px]">
                        {merit.isError && <p className="text-rose-500">{toApiError(merit.error).message}</p>}
                        {merit.isSuccess && <p className="text-emerald-600">Merit process: {merit.data.students_processed} students processed.</p>}
                    </div>
                )}
            </Card>
        </div>
    );
}
