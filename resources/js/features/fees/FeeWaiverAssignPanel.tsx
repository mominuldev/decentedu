import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2, Plus, Trash2 } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { toApiError } from '@/lib/api';
import { listStudents } from '@/features/students/api';
import { listSetup as listAcademicSetup } from '@/features/academic/api';
import { listSetup, listWaiverConfigs, assignWaiver, removeWaiver } from './api';

export function FeeWaiverAssignPanel() {
    const { data: students } = useQuery({ queryKey: ['students', 'waiver-picker'], queryFn: () => listStudents({ per_page: 200 }) });
    const { data: years = [] } = useQuery({ queryKey: ['academic-setup', 'academic-years'], queryFn: () => listAcademicSetup('academic-years') });
    const { data: waivers = [] } = useQuery({ queryKey: ['fees-setup', 'waivers'], queryFn: () => listSetup('waivers') });
    const { data: subHeads = [] } = useQuery({ queryKey: ['fees-setup', 'sub-heads'], queryFn: () => listSetup('sub-heads') });

    const [studentId, setStudentId] = useState(0);
    const qc = useQueryClient();

    const key = ['waiver-configs', studentId];
    const { data: rows = [], isLoading } = useQuery({ queryKey: key, queryFn: () => listWaiverConfigs(studentId), enabled: !!studentId });

    const [feeWaiverId, setFeeWaiverId] = useState(0);
    const [feeSubHeadId, setFeeSubHeadId] = useState(0);
    const [academicYearId, setAcademicYearId] = useState(0);

    const assign = useMutation({
        mutationFn: () => assignWaiver({
            student_id: studentId, fee_waiver_id: feeWaiverId,
            fee_sub_head_id: feeSubHeadId || null, academic_year_id: academicYearId,
        }),
        onSuccess: () => { qc.invalidateQueries({ queryKey: key }); setFeeWaiverId(0); setFeeSubHeadId(0); },
    });

    const remove = useMutation({
        mutationFn: (id: number) => removeWaiver(id),
        onSuccess: () => qc.invalidateQueries({ queryKey: key }),
    });

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Waiver assignment</h3>
                    <p className="text-[12.5px] text-muted">Assign a scholarship/discount to a student, for one sub-head or all fees</p>
                </div>
                <select value={studentId} onChange={(e) => setStudentId(Number(e.target.value))}
                    className="min-w-[220px] rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
                    <option value={0} disabled>Select a student</option>
                    {students?.data.map((s) => <option key={s.id} value={s.id}>{s.name} ({s.student_uid})</option>)}
                </select>
            </div>

            {studentId > 0 && (
                <>
                    <div className="flex flex-wrap items-end gap-2 border-t border-border px-5 py-4">
                        <select value={feeWaiverId} onChange={(e) => setFeeWaiverId(Number(e.target.value))}
                            className="rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
                            <option value={0} disabled>Waiver</option>
                            {waivers.map((w) => <option key={w.id} value={w.id}>{w.name} ({w.type === 'percentage' ? `${w.value}%` : w.value})</option>)}
                        </select>
                        <select value={feeSubHeadId} onChange={(e) => setFeeSubHeadId(Number(e.target.value))}
                            className="rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
                            <option value={0}>All fees</option>
                            {subHeads.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                        </select>
                        <select value={academicYearId} onChange={(e) => setAcademicYearId(Number(e.target.value))}
                            className="rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
                            <option value={0} disabled>Year</option>
                            {years.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
                        </select>
                        <Button onClick={() => assign.mutate()} disabled={assign.isPending || !feeWaiverId || !academicYearId}>
                            {assign.isPending ? <Loader2 size={16} className="animate-spin" /> : <Plus size={16} />} Assign
                        </Button>
                    </div>
                    {assign.isError && <p className="px-5 pb-2 text-[13px] text-rose-500">{toApiError(assign.error).message}</p>}

                    <div className="overflow-x-auto border-t border-border">
                        {isLoading ? (
                            <div className="flex items-center justify-center gap-2 py-10 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                        ) : rows.length === 0 ? (
                            <div className="flex items-center justify-center py-10 text-[13.5px] text-muted">No waivers assigned yet</div>
                        ) : (
                            <table className="w-full min-w-[520px] text-left text-[13.5px]">
                                <tbody>
                                    {rows.map((r) => (
                                        <tr key={r.id} className="border-b border-border last:border-0">
                                            <td className="px-5 py-2.5 font-medium text-fg">{r.fee_waiver_name}</td>
                                            <td className="px-5 py-2.5"><Badge tone="brand">{r.fee_sub_head_name}</Badge></td>
                                            <td className="px-5 py-2.5 text-right">
                                                <button onClick={() => remove.mutate(r.id)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500" aria-label="Remove">
                                                    <Trash2 size={16} />
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </>
            )}
        </Card>
    );
}
