import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Loader2, Inbox } from 'lucide-react';
import { Card, Badge } from '@/components/ui';
import { Modal } from '@/components/Modal';
import { listStudents } from '@/features/students/api';
import { listCollections, getCollection } from './api';

export function ReceiptsPanel() {
    const { data: students } = useQuery({ queryKey: ['students', 'receipts-picker'], queryFn: () => listStudents({ per_page: 200 }) });
    const [studentId, setStudentId] = useState(0);
    const [viewing, setViewing] = useState<number | null>(null);

    const { data: rows = [], isLoading } = useQuery({
        queryKey: ['fee-collections', studentId],
        queryFn: () => listCollections(studentId ? { student_id: studentId } : {}),
    });

    const { data: detail } = useQuery({ queryKey: ['fee-collection', viewing], queryFn: () => getCollection(viewing!), enabled: !!viewing });

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Receipts</h3>
                    <p className="text-[12.5px] text-muted">Every fee collection, with the voucher it posted</p>
                </div>
                <select value={studentId} onChange={(e) => setStudentId(Number(e.target.value))}
                    className="min-w-[220px] rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
                    <option value={0}>All students</option>
                    {students?.data.map((s) => <option key={s.id} value={s.id}>{s.name} ({s.student_uid})</option>)}
                </select>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : rows.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                        <div className="grid h-12 w-12 place-items-center rounded-2xl bg-surface-2 text-faint"><Inbox size={22} /></div>
                        <p className="text-[14px] font-medium text-fg">No receipts yet</p>
                    </div>
                ) : (
                    <table className="w-full min-w-[640px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Receipt</th>
                                <th className="px-5 py-2.5 font-semibold">Student</th>
                                <th className="px-5 py-2.5 font-semibold">Date</th>
                                <th className="px-5 py-2.5 font-semibold">Method</th>
                                <th className="px-5 py-2.5 font-semibold">Amount</th>
                                <th className="px-5 py-2.5 font-semibold">GL</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={r.id} onClick={() => setViewing(r.id)} className="cursor-pointer border-b border-border last:border-0 hover:bg-surface-2/50">
                                    <td className="px-5 py-3 font-medium text-fg">{r.receipt_no}</td>
                                    <td className="px-5 py-3 text-muted">{r.student_name}</td>
                                    <td className="px-5 py-3 text-muted">{new Date(r.collected_at).toLocaleString()}</td>
                                    <td className="px-5 py-3 text-muted capitalize">{r.payment_method.replace('_', ' ')}</td>
                                    <td className="tnum px-5 py-3 font-medium text-fg">{r.total_amount}</td>
                                    <td className="px-5 py-3">
                                        {r.voucher_id ? <Badge tone="success">Posted</Badge> : <Badge tone="neutral">—</Badge>}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            <Modal open={!!viewing} onClose={() => setViewing(null)} title={`Receipt ${detail?.receipt_no ?? ''}`}>
                {!detail ? (
                    <div className="flex items-center justify-center py-8"><Loader2 size={18} className="animate-spin" /></div>
                ) : (
                    <div className="space-y-3 text-[13.5px]">
                        <div className="flex justify-between text-muted"><span>Student</span><span className="font-medium text-fg">{detail.student_name}</span></div>
                        <div className="flex justify-between text-muted"><span>Collected at</span><span className="font-medium text-fg">{new Date(detail.collected_at).toLocaleString()}</span></div>
                        <div className="flex justify-between text-muted"><span>Method</span><span className="font-medium capitalize text-fg">{detail.payment_method.replace('_', ' ')}</span></div>
                        {detail.voucher_no && <div className="flex justify-between text-muted"><span>Voucher</span><span className="font-medium text-fg">{detail.voucher_no}</span></div>}
                        <div className="border-t border-border pt-3">
                            {detail.items.map((i, idx) => (
                                <div key={idx} className="flex justify-between py-1">
                                    <span className="text-muted">{i.fee_sub_head_name}{Number(i.fine_paid) > 0 && ` (incl. ${i.fine_paid} fine)`}</span>
                                    <span className="tnum font-medium text-fg">{i.amount}</span>
                                </div>
                            ))}
                        </div>
                        <div className="flex justify-between border-t border-border pt-3 text-[14.5px] font-semibold text-fg">
                            <span>Total</span><span>{detail.total_amount}</span>
                        </div>
                    </div>
                )}
            </Modal>
        </Card>
    );
}
