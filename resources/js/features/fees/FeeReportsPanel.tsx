import { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { Download, FileSpreadsheet, Loader2 } from 'lucide-react';
import { Button, Card } from '@/components/ui';
import { listSetup as listAcademicSetup } from '@/features/academic/api';
import { downloadReport } from '@/features/reporting/api';
import { dailyCollectionReport, duesSummaryReport } from './api';

export function FeeReportsPanel() {
    const today = new Date().toISOString().slice(0, 10);
    const [from, setFrom] = useState(today);
    const [to, setTo] = useState(today);

    const { data: years = [] } = useQuery({ queryKey: ['academic-setup', 'academic-years'], queryFn: () => listAcademicSetup('academic-years') });
    const [academicYearId, setAcademicYearId] = useState(0);

    const { data: daily, isLoading: dailyLoading } = useQuery({ queryKey: ['fee-report-daily', from, to], queryFn: () => dailyCollectionReport({ from, to }) });
    const { data: dues, isLoading: duesLoading } = useQuery({
        queryKey: ['fee-report-dues', academicYearId],
        queryFn: () => duesSummaryReport(academicYearId),
        enabled: !!academicYearId,
    });

    const downloadDaily = useMutation({ mutationFn: (format: 'pdf' | 'excel') => downloadReport('fee-daily-collection', format, { from, to }) });
    // Dues summary is a queued report — downloadReport() polls the artifact until it's ready.
    const downloadDues = useMutation({ mutationFn: (format: 'pdf' | 'excel') => downloadReport('fee-dues-summary', format, { academic_year_id: academicYearId }) });

    return (
        <div className="space-y-6">
            <Card>
                <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                    <h3 className="text-[15px] font-semibold text-fg">Daily collection</h3>
                    <div className="flex gap-2">
                        <input type="date" value={from} onChange={(e) => setFrom(e.target.value)}
                            className="rounded-xl border border-border-strong bg-surface px-3 py-2 text-[13.5px] text-fg outline-none focus:border-brand-500" />
                        <input type="date" value={to} onChange={(e) => setTo(e.target.value)}
                            className="rounded-xl border border-border-strong bg-surface px-3 py-2 text-[13.5px] text-fg outline-none focus:border-brand-500" />
                        <Button variant="outline" onClick={() => downloadDaily.mutate('pdf')} disabled={downloadDaily.isPending}>
                            {downloadDaily.isPending ? <Loader2 size={16} className="animate-spin" /> : <Download size={16} />} PDF
                        </Button>
                        <Button variant="outline" onClick={() => downloadDaily.mutate('excel')} disabled={downloadDaily.isPending}>
                            <FileSpreadsheet size={16} /> Excel
                        </Button>
                    </div>
                </div>
                {downloadDaily.isError && <div className="border-t border-border px-5 py-3 text-[13.5px] text-rose-500">{downloadDaily.error?.message}</div>}
                <div className="border-t border-border px-5 py-4">
                    {dailyLoading ? (
                        <div className="flex items-center justify-center gap-2 py-6 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                    ) : (
                        <>
                            <div className="mb-3 flex gap-6 text-[13.5px]">
                                <div><span className="text-muted">Total collected</span> <span className="font-semibold text-fg">{daily?.total_collected}</span></div>
                                <div><span className="text-muted">Receipts</span> <span className="font-semibold text-fg">{daily?.receipts_count}</span></div>
                            </div>
                            {daily?.by_head.map((h) => (
                                <div key={h.fee_head_name} className="flex justify-between border-b border-border py-1.5 text-[13.5px] last:border-0">
                                    <span className="text-muted">{h.fee_head_name}</span>
                                    <span className="tnum font-medium text-fg">{h.amount}</span>
                                </div>
                            ))}
                        </>
                    )}
                </div>
            </Card>

            <Card>
                <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                    <h3 className="text-[15px] font-semibold text-fg">Dues summary</h3>
                    <select value={academicYearId} onChange={(e) => setAcademicYearId(Number(e.target.value))}
                        className="rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
                        <option value={0} disabled>Select a year</option>
                        {years.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
                    </select>
                    <Button variant="outline" onClick={() => downloadDues.mutate('pdf')} disabled={!academicYearId || downloadDues.isPending}>
                        {downloadDues.isPending ? <Loader2 size={16} className="animate-spin" /> : <Download size={16} />} PDF
                    </Button>
                    <Button variant="outline" onClick={() => downloadDues.mutate('excel')} disabled={!academicYearId || downloadDues.isPending}>
                        <FileSpreadsheet size={16} /> Excel
                    </Button>
                </div>
                {downloadDues.isError && <div className="border-t border-border px-5 py-3 text-[13.5px] text-rose-500">{downloadDues.error?.message}</div>}
                <div className="border-t border-border px-5 py-4">
                    {!academicYearId ? (
                        <div className="py-6 text-center text-[13.5px] text-muted">Select an academic year</div>
                    ) : duesLoading ? (
                        <div className="flex items-center justify-center gap-2 py-6 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                    ) : dues?.length === 0 ? (
                        <div className="py-6 text-center text-[13.5px] text-muted">No outstanding dues</div>
                    ) : (
                        dues?.map((d) => (
                            <div key={d.class_config_id} className="flex justify-between border-b border-border py-1.5 text-[13.5px] last:border-0">
                                <span className="text-muted">{d.class_label} <span className="text-faint">({d.students_with_dues} students)</span></span>
                                <span className="tnum font-medium text-fg">{d.total_due}</span>
                            </div>
                        ))
                    )}
                </div>
            </Card>
        </div>
    );
}
