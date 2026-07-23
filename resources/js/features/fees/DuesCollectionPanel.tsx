import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2, Receipt } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { toApiError } from '@/lib/api';
import { listStudents } from '@/features/students/api';
import { studentDues, collectFees } from './api';

export function DuesCollectionPanel() {
    const { data: students } = useQuery({ queryKey: ['students', 'fee-collection-picker'], queryFn: () => listStudents({ per_page: 200 }) });
    const [studentId, setStudentId] = useState(0);
    const [paymentMethod, setPaymentMethod] = useState('cash');
    const [amounts, setAmounts] = useState<Record<number, string>>({});
    const [receipt, setReceipt] = useState<{ receipt_no: string; voucher_id: number | null; total_amount: string } | null>(null);
    const qc = useQueryClient();

    const duesKey = ['fee-dues', studentId];
    const { data: dues = [], isLoading } = useQuery({
        queryKey: duesKey,
        queryFn: () => studentDues(studentId),
        enabled: !!studentId,
    });

    useEffect(() => {
        setAmounts(Object.fromEntries(dues.map((d) => [d.student_fee_id, ''])));
        setReceipt(null);
    }, [dues]);

    const collect = useMutation({
        mutationFn: () => collectFees({
            student_id: studentId,
            payment_method: paymentMethod,
            items: Object.entries(amounts).filter(([, v]) => Number(v) > 0).map(([id, amount]) => ({ student_fee_id: Number(id), amount: Number(amount) })),
        }),
        onSuccess: (row) => {
            setReceipt({ receipt_no: row.receipt_no, voucher_id: row.voucher_id, total_amount: row.total_amount });
            qc.invalidateQueries({ queryKey: duesKey });
            qc.invalidateQueries({ queryKey: ['fee-collections'] });
        },
    });

    const totalToCollect = Object.values(amounts).reduce((sum, v) => sum + (Number(v) || 0), 0);

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Dues &amp; collection</h3>
                    <p className="text-[12.5px] text-muted">Partial payments are allowed — enter any amount up to the due balance</p>
                </div>
                <select value={studentId} onChange={(e) => setStudentId(Number(e.target.value))}
                    className="min-w-[220px] rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
                    <option value={0} disabled>Select a student</option>
                    {students?.data.map((s) => <option key={s.id} value={s.id}>{s.name} ({s.student_uid})</option>)}
                </select>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {!studentId ? (
                    <div className="flex items-center justify-center py-16 text-[14px] font-medium text-fg">Select a student</div>
                ) : isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : dues.length === 0 ? (
                    <div className="flex items-center justify-center py-16 text-[14px] font-medium text-fg">No outstanding dues for this student</div>
                ) : (
                    <table className="w-full min-w-[720px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Fee</th>
                                <th className="px-5 py-2.5 font-semibold">Due date</th>
                                <th className="px-5 py-2.5 font-semibold">Payable</th>
                                <th className="px-5 py-2.5 font-semibold">Paid so far</th>
                                <th className="px-5 py-2.5 font-semibold">Due amount</th>
                                <th className="px-5 py-2.5 font-semibold">Collect now</th>
                            </tr>
                        </thead>
                        <tbody>
                            {dues.map((d) => (
                                <tr key={d.student_fee_id} className="border-b border-border last:border-0">
                                    <td className="px-5 py-2">
                                        <div className="font-medium text-fg">{d.fee_sub_head_name}</div>
                                        <div className="text-[12px] text-faint">{d.fee_head_name}</div>
                                    </td>
                                    <td className="px-5 py-2 text-muted">
                                        {d.due_date ?? '—'}
                                        {d.is_overdue && <Badge tone="danger" className="ml-2">Overdue{d.fine_amount === '0.00' ? ` · +${d.projected_fine} fine` : ''}</Badge>}
                                    </td>
                                    <td className="tnum px-5 py-2 text-muted">{d.payable_amount}</td>
                                    <td className="tnum px-5 py-2 text-muted">{d.paid_amount}</td>
                                    <td className="tnum px-5 py-2 font-medium text-fg">{d.due_amount}</td>
                                    <td className="p-1.5">
                                        <input type="number" value={amounts[d.student_fee_id] ?? ''} placeholder="0.00" max={d.due_amount}
                                            onChange={(e) => setAmounts((a) => ({ ...a, [d.student_fee_id]: e.target.value }))}
                                            className="w-28 rounded-lg border border-border-strong bg-surface px-2.5 py-1.5 text-[13px] text-fg outline-none focus:border-brand-500" />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {studentId && dues.length > 0 && (
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-4">
                    <select value={paymentMethod} onChange={(e) => setPaymentMethod(e.target.value)}
                        className="rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
                        <option value="cash">Cash</option>
                        <option value="bank">Bank</option>
                        <option value="mobile_banking">Mobile banking</option>
                        <option value="cheque">Cheque</option>
                    </select>
                    <div className="flex items-center gap-3">
                        {collect.isError && <span className="text-[13px] text-rose-500">{toApiError(collect.error).message}</span>}
                        <span className="text-[13.5px] font-medium text-fg">Total: {totalToCollect.toFixed(2)}</span>
                        <Button onClick={() => collect.mutate()} disabled={collect.isPending || totalToCollect <= 0}>
                            {collect.isPending ? <Loader2 size={16} className="animate-spin" /> : <Receipt size={16} />} Collect
                        </Button>
                    </div>
                </div>
            )}

            {receipt && (
                <div className="mx-5 mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-[13.5px] text-emerald-800 dark:border-emerald-500/25 dark:bg-emerald-500/10 dark:text-emerald-300">
                    Collected {receipt.total_amount} — Receipt <strong>{receipt.receipt_no}</strong>
                    {receipt.voucher_id && ' — posted to the ledger.'}
                </div>
            )}
        </Card>
    );
}
