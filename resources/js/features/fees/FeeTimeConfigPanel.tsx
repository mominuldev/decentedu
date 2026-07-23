import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2, Save } from 'lucide-react';
import { Card, Button } from '@/components/ui';
import { toApiError } from '@/lib/api';
import { listSetup as listAcademicSetup } from '@/features/academic/api';
import { listFeeTimeConfigs, saveFeeTimeConfigs } from './api';

type Row = { due_date: string; fine_amount: string };

export function FeeTimeConfigPanel() {
    const { data: years = [] } = useQuery({ queryKey: ['academic-setup', 'academic-years'], queryFn: () => listAcademicSetup('academic-years') });
    const [academicYearId, setAcademicYearId] = useState(0);
    const qc = useQueryClient();

    const queryKey = ['fee-time-configs', academicYearId];
    const { data: rows = [], isLoading } = useQuery({
        queryKey,
        queryFn: () => listFeeTimeConfigs(academicYearId),
        enabled: !!academicYearId,
    });

    const [values, setValues] = useState<Record<number, Row>>({});
    useEffect(() => {
        setValues(Object.fromEntries(rows.map((r) => [r.fee_sub_head_id, { due_date: r.due_date ?? '', fine_amount: r.fine_amount ?? '0' }])));
    }, [rows]);

    const save = useMutation({
        mutationFn: () => saveFeeTimeConfigs({
            academic_year_id: academicYearId,
            items: Object.entries(values)
                .filter(([, v]) => v.due_date !== '')
                .map(([id, v]) => ({ fee_sub_head_id: Number(id), due_date: v.due_date, fine_amount: Number(v.fine_amount || 0) })),
        }),
        onSuccess: () => qc.invalidateQueries({ queryKey }),
    });

    const ready = !!academicYearId;

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Due date &amp; fine</h3>
                    <p className="text-[12.5px] text-muted">Flat fine charged once a sub-head becomes overdue</p>
                </div>
                <select value={academicYearId} onChange={(e) => setAcademicYearId(Number(e.target.value))}
                    className="rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
                    <option value={0} disabled>Select a year</option>
                    {years.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
                </select>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {!ready ? (
                    <div className="flex items-center justify-center py-16 text-[14px] font-medium text-fg">Select an academic year</div>
                ) : isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : (
                    <table className="w-full min-w-[560px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Fee head</th>
                                <th className="px-5 py-2.5 font-semibold">Sub-head</th>
                                <th className="px-5 py-2.5 font-semibold">Due date</th>
                                <th className="px-5 py-2.5 font-semibold">Flat fine</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={r.fee_sub_head_id} className="border-b border-border last:border-0">
                                    <td className="px-5 py-2 text-muted">{r.fee_head_name ?? '—'}</td>
                                    <td className="px-5 py-2 font-medium text-fg">{r.fee_sub_head_name}</td>
                                    <td className="p-1.5">
                                        <input type="date" value={values[r.fee_sub_head_id]?.due_date ?? ''}
                                            onChange={(e) => setValues((v) => ({ ...v, [r.fee_sub_head_id]: { ...v[r.fee_sub_head_id], due_date: e.target.value } }))}
                                            className="rounded-lg border border-border-strong bg-surface px-2.5 py-1.5 text-[13px] text-fg outline-none focus:border-brand-500" />
                                    </td>
                                    <td className="p-1.5">
                                        <input type="number" value={values[r.fee_sub_head_id]?.fine_amount ?? ''} placeholder="0.00"
                                            onChange={(e) => setValues((v) => ({ ...v, [r.fee_sub_head_id]: { ...v[r.fee_sub_head_id], fine_amount: e.target.value } }))}
                                            className="w-28 rounded-lg border border-border-strong bg-surface px-2.5 py-1.5 text-[13px] text-fg outline-none focus:border-brand-500" />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {ready && !isLoading && (
                <div className="flex items-center justify-end gap-3 border-t border-border px-5 py-4">
                    {save.isError && <span className="text-[13px] text-rose-500">{toApiError(save.error).message}</span>}
                    {save.isSuccess && <span className="text-[13px] text-emerald-600">Saved.</span>}
                    <Button onClick={() => save.mutate()} disabled={save.isPending}>
                        {save.isPending ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />} Save
                    </Button>
                </div>
            )}
        </Card>
    );
}
