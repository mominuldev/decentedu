import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2, Save, Calculator } from 'lucide-react';
import { Card, Button } from '@/components/ui';
import { toApiError } from '@/lib/api';
import { listClassConfigs, listSetup as listAcademicSetup } from '@/features/academic/api';
import { listFeeConfigs, saveFeeConfigs, assessFees } from './api';

export function FeeConfigPanel() {
    const { data: classConfigs = [] } = useQuery({ queryKey: ['class-configs'], queryFn: listClassConfigs });
    const { data: years = [] } = useQuery({ queryKey: ['academic-setup', 'academic-years'], queryFn: () => listAcademicSetup('academic-years') });

    const [classConfigId, setClassConfigId] = useState(0);
    const [academicYearId, setAcademicYearId] = useState(0);
    const qc = useQueryClient();

    const queryKey = ['fee-configs', classConfigId, academicYearId];
    const { data: rows = [], isLoading } = useQuery({
        queryKey,
        queryFn: () => listFeeConfigs({ class_config_id: classConfigId, academic_year_id: academicYearId }),
        enabled: !!classConfigId && !!academicYearId,
    });

    const [amounts, setAmounts] = useState<Record<number, string>>({});
    useEffect(() => {
        setAmounts(Object.fromEntries(rows.map((r) => [r.fee_sub_head_id, r.amount ?? ''])));
    }, [rows]);

    const save = useMutation({
        mutationFn: () => saveFeeConfigs({
            class_config_id: classConfigId,
            academic_year_id: academicYearId,
            items: Object.entries(amounts).filter(([, v]) => v !== '').map(([id, amount]) => ({ fee_sub_head_id: Number(id), amount: Number(amount) })),
        }),
        onSuccess: () => qc.invalidateQueries({ queryKey }),
    });

    const assess = useMutation({
        mutationFn: () => assessFees({ class_config_id: classConfigId, academic_year_id: academicYearId }),
    });

    const ready = !!classConfigId && !!academicYearId;

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Fee structure</h3>
                    <p className="text-[12.5px] text-muted">Payable amount per sub-head, for one class × academic year</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <select value={classConfigId} onChange={(e) => setClassConfigId(Number(e.target.value))}
                        className="rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
                        <option value={0} disabled>Select a class</option>
                        {classConfigs.map((c) => <option key={c.id} value={c.id}>{c.label}</option>)}
                    </select>
                    <select value={academicYearId} onChange={(e) => setAcademicYearId(Number(e.target.value))}
                        className="rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
                        <option value={0} disabled>Select a year</option>
                        {years.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
                    </select>
                </div>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {!ready ? (
                    <div className="flex items-center justify-center py-16 text-[14px] font-medium text-fg">Select a class and an academic year</div>
                ) : isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : (
                    <table className="w-full min-w-[520px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Fee head</th>
                                <th className="px-5 py-2.5 font-semibold">Sub-head</th>
                                <th className="px-5 py-2.5 font-semibold">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={r.fee_sub_head_id} className="border-b border-border last:border-0">
                                    <td className="px-5 py-2 text-muted">{r.fee_head_name ?? '—'}</td>
                                    <td className="px-5 py-2 font-medium text-fg">{r.fee_sub_head_name}</td>
                                    <td className="p-1.5">
                                        <input type="number" value={amounts[r.fee_sub_head_id] ?? ''} placeholder="0.00"
                                            onChange={(e) => setAmounts((a) => ({ ...a, [r.fee_sub_head_id]: e.target.value }))}
                                            className="w-32 rounded-lg border border-border-strong bg-surface px-2.5 py-1.5 text-[13px] text-fg outline-none focus:border-brand-500" />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {ready && !isLoading && (
                <div className="flex flex-wrap items-center justify-end gap-3 border-t border-border px-5 py-4">
                    {save.isError && <span className="text-[13px] text-rose-500">{toApiError(save.error).message}</span>}
                    {save.isSuccess && <span className="text-[13px] text-emerald-600">Saved.</span>}
                    {assess.isError && <span className="text-[13px] text-rose-500">{toApiError(assess.error).message}</span>}
                    {assess.isSuccess && <span className="text-[13px] text-emerald-600">{assess.data.student_fees_assessed} student fee(s) assessed.</span>}
                    <Button variant="outline" onClick={() => assess.mutate()} disabled={assess.isPending}>
                        {assess.isPending ? <Loader2 size={16} className="animate-spin" /> : <Calculator size={16} />} Assess students
                    </Button>
                    <Button onClick={() => save.mutate()} disabled={save.isPending}>
                        {save.isPending ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />} Save structure
                    </Button>
                </div>
            )}
        </Card>
    );
}
