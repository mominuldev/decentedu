import { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { Download, FileSpreadsheet, Loader2, Search } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { toApiError } from '@/lib/api';
import { classConfigOptions, listClassConfigs } from '@/features/academic/api';
import { downloadReport } from '@/features/reporting/api';
import { listSetup, marksheet, tabulationSheet, meritList, failList } from './api';

type ReportKind = 'marksheet' | 'tabulation' | 'merit-class' | 'merit-section' | 'fail-class' | 'fail-section';

const kinds: { key: ReportKind; label: string }[] = [
    { key: 'marksheet', label: 'Marksheet' },
    { key: 'tabulation', label: 'Tabulation sheet' },
    { key: 'merit-class', label: 'Merit list (class-wise)' },
    { key: 'merit-section', label: 'Merit list (section-wise)' },
    { key: 'fail-class', label: 'Fail list (class-wise)' },
    { key: 'fail-section', label: 'Fail list (section-wise)' },
];

/** Maps a ReportsPanel kind to its ReportRegistry key + the params the report endpoint expects. */
const reportKeys: Record<ReportKind, string> = {
    marksheet: 'marksheet',
    tabulation: 'tabulation-sheet',
    'merit-class': 'merit-list',
    'merit-section': 'merit-list',
    'fail-class': 'fail-list',
    'fail-section': 'fail-list',
};

export function ReportsPanel() {
    const { data: classConfigs = [] } = useQuery({ queryKey: ['class-configs'], queryFn: listClassConfigs });
    const { data: options } = useQuery({ queryKey: ['class-config-options'], queryFn: classConfigOptions });
    const { data: exams = [] } = useQuery({ queryKey: ['exam-setup', 'exams'], queryFn: () => listSetup('exams') });

    const [kind, setKind] = useState<ReportKind>('marksheet');
    const [classConfigId, setClassConfigId] = useState(0);
    const [classId, setClassId] = useState(0);
    const [examId, setExamId] = useState(0);

    const needsClass = kind === 'merit-class' || kind === 'fail-class';

    const marksheetQ = useMutation({ mutationFn: () => marksheet({ class_config_id: classConfigId, exam_id: examId }) });
    const tabulationQ = useMutation({ mutationFn: () => tabulationSheet({ class_config_id: classConfigId, exam_id: examId }) });
    const meritQ = useMutation({
        mutationFn: () => meritList(needsClass ? { class_id: classId, exam_id: examId } : { class_config_id: classConfigId, exam_id: examId }),
    });
    const failQ = useMutation({
        mutationFn: () => failList(needsClass ? { class_id: classId, exam_id: examId } : { class_config_id: classConfigId, exam_id: examId }),
    });

    const active = kind === 'marksheet' ? marksheetQ : kind === 'tabulation' ? tabulationQ : kind.startsWith('merit') ? meritQ : failQ;
    const ready = needsClass ? !!classId && !!examId : !!classConfigId && !!examId;
    const reportParams = needsClass ? { class_id: classId, exam_id: examId } : { class_config_id: classConfigId, exam_id: examId };
    const supportsExcel = kind !== 'tabulation';

    const downloadPdf = useMutation({ mutationFn: () => downloadReport(reportKeys[kind], 'pdf', reportParams) });
    const downloadExcel = useMutation({ mutationFn: () => downloadReport(reportKeys[kind], 'excel', reportParams) });

    const selectCls = 'rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Reports</h3>
                    <p className="text-[12.5px] text-muted">Run general/final/merit process first so these have data</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <select value={kind} onChange={(e) => setKind(e.target.value as ReportKind)} className={selectCls}>
                        {kinds.map((k) => <option key={k.key} value={k.key}>{k.label}</option>)}
                    </select>
                    {needsClass ? (
                        <select value={classId} onChange={(e) => setClassId(Number(e.target.value))} className={selectCls}>
                            <option value={0} disabled>Class</option>
                            {options?.classes.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    ) : (
                        <select value={classConfigId} onChange={(e) => setClassConfigId(Number(e.target.value))} className={selectCls}>
                            <option value={0} disabled>Class (section)</option>
                            {classConfigs.map((c) => <option key={c.id} value={c.id}>{c.label}</option>)}
                        </select>
                    )}
                    <select value={examId} onChange={(e) => setExamId(Number(e.target.value))} className={selectCls}>
                        <option value={0} disabled>Exam</option>
                        {exams.map((e) => <option key={e.id} value={e.id}>{e.name}</option>)}
                    </select>
                    <Button onClick={() => active.mutate()} disabled={!ready || active.isPending}>
                        {active.isPending ? <Loader2 size={16} className="animate-spin" /> : <Search size={16} />} Run
                    </Button>
                    <Button variant="outline" onClick={() => downloadPdf.mutate()} disabled={!ready || downloadPdf.isPending}>
                        {downloadPdf.isPending ? <Loader2 size={16} className="animate-spin" /> : <Download size={16} />} PDF
                    </Button>
                    {supportsExcel && (
                        <Button variant="outline" onClick={() => downloadExcel.mutate()} disabled={!ready || downloadExcel.isPending}>
                            {downloadExcel.isPending ? <Loader2 size={16} className="animate-spin" /> : <FileSpreadsheet size={16} />} Excel
                        </Button>
                    )}
                </div>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {active.isError && <div className="p-5 text-[13.5px] text-rose-500">{toApiError(active.error).message}</div>}
                {(downloadPdf.isError || downloadExcel.isError) && (
                    <div className="p-5 text-[13.5px] text-rose-500">{(downloadPdf.error ?? downloadExcel.error)?.message ?? 'Download failed.'}</div>
                )}

                {kind === 'marksheet' && marksheetQ.data && (
                    <div className="divide-y divide-border">
                        {marksheetQ.data.map((row) => (
                            <div key={row.student_id} className="p-5">
                                <div className="mb-2 flex items-center justify-between">
                                    <p className="font-semibold text-fg">{row.name}</p>
                                    <div className="flex items-center gap-2 text-[13px]">
                                        <span className="text-muted">Total: {row.total_obtained}/{row.total_marks}</span>
                                        {row.gpa != null && <span className="text-muted">GPA {row.gpa}</span>}
                                        <Badge tone={row.is_pass ? 'success' : 'danger'}>{row.is_pass ? 'Pass' : 'Fail'}</Badge>
                                    </div>
                                </div>
                                <table className="w-full text-left text-[13px]">
                                    <thead>
                                        <tr className="text-[11px] uppercase tracking-wide text-faint">
                                            <th className="py-1 font-semibold">Subject</th>
                                            <th className="py-1 font-semibold">Obtained</th>
                                            <th className="py-1 font-semibold">Grade</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {row.subjects.map((s, i) => (
                                            <tr key={i}>
                                                <td className="py-1 text-fg">{s.subject_name}</td>
                                                <td className="tnum py-1 text-muted">{s.is_absent ? 'Absent' : `${s.obtained_marks}/${s.total_marks}`}</td>
                                                <td className="py-1 text-muted">{s.grade ?? '—'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ))}
                    </div>
                )}

                {kind === 'tabulation' && tabulationQ.data && (
                    <table className="w-full min-w-[640px] text-left text-[13px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Student</th>
                                {tabulationQ.data.subjects.map((s) => <th key={s.id} className="px-3 py-2.5 font-semibold">{s.name}</th>)}
                            </tr>
                        </thead>
                        <tbody>
                            {tabulationQ.data.rows.map((r) => (
                                <tr key={r.student_id} className="border-b border-border last:border-0">
                                    <td className="px-5 py-2 font-medium text-fg">{r.name}</td>
                                    {tabulationQ.data.subjects.map((s) => (
                                        <td key={s.id} className="tnum px-3 py-2 text-muted">{r.marks[s.id] ?? '—'}</td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}

                {kind.startsWith('merit') && meritQ.data && (
                    <table className="w-full min-w-[520px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Position</th>
                                <th className="px-5 py-2.5 font-semibold">Name</th>
                                <th className="px-5 py-2.5 font-semibold">Section</th>
                                <th className="px-5 py-2.5 font-semibold">Total</th>
                                <th className="px-5 py-2.5 font-semibold">GPA</th>
                            </tr>
                        </thead>
                        <tbody>
                            {meritQ.data.map((r) => (
                                <tr key={r.student_id} className="border-b border-border last:border-0">
                                    <td className="tnum px-5 py-2 text-muted">{r.position ?? '—'}</td>
                                    <td className="px-5 py-2 font-medium text-fg">{r.name}</td>
                                    <td className="px-5 py-2 text-muted">{r.section ?? '—'}</td>
                                    <td className="tnum px-5 py-2 text-muted">{r.total_obtained}</td>
                                    <td className="tnum px-5 py-2 text-muted">{r.gpa ?? '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}

                {kind.startsWith('fail') && failQ.data && (
                    <table className="w-full min-w-[520px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Name</th>
                                <th className="px-5 py-2.5 font-semibold">Section</th>
                                <th className="px-5 py-2.5 font-semibold">Total</th>
                                <th className="px-5 py-2.5 font-semibold">Failed subjects</th>
                            </tr>
                        </thead>
                        <tbody>
                            {failQ.data.map((r) => (
                                <tr key={r.student_id} className="border-b border-border last:border-0">
                                    <td className="px-5 py-2 font-medium text-fg">{r.name}</td>
                                    <td className="px-5 py-2 text-muted">{r.section ?? '—'}</td>
                                    <td className="tnum px-5 py-2 text-muted">{r.total_obtained}</td>
                                    <td className="px-5 py-2 text-muted">{r.failed_subjects.join(', ')}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </Card>
    );
}
