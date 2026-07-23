import { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { Download, FileSpreadsheet, Loader2 } from 'lucide-react';
import { Button, Card } from '@/components/ui';
import { downloadReport } from '@/features/reporting/api';
import { trialBalance, incomeStatement } from './api';

export function AccountingReportsPanel() {
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');

    const { data: tb, isLoading: tbLoading } = useQuery({ queryKey: ['trial-balance', from, to], queryFn: () => trialBalance({ from: from || undefined, to: to || undefined }) });
    const { data: is, isLoading: isLoading2 } = useQuery({ queryKey: ['income-statement', from, to], queryFn: () => incomeStatement({ from: from || undefined, to: to || undefined }) });

    const rangeParams = { from: from || undefined, to: to || undefined };
    const downloadTb = useMutation({ mutationFn: (format: 'pdf' | 'excel') => downloadReport('trial-balance', format, rangeParams) });
    const downloadIs = useMutation({ mutationFn: (format: 'pdf' | 'excel') => downloadReport('income-statement', format, rangeParams) });

    return (
        <div className="space-y-6">
            <Card>
                <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                    <h3 className="text-[15px] font-semibold text-fg">Date range</h3>
                    <div className="flex gap-2">
                        <input type="date" value={from} onChange={(e) => setFrom(e.target.value)}
                            className="rounded-xl border border-border-strong bg-surface px-3 py-2 text-[13.5px] text-fg outline-none focus:border-brand-500" />
                        <input type="date" value={to} onChange={(e) => setTo(e.target.value)}
                            className="rounded-xl border border-border-strong bg-surface px-3 py-2 text-[13.5px] text-fg outline-none focus:border-brand-500" />
                    </div>
                </div>
            </Card>

            <Card>
                <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                    <h3 className="text-[15px] font-semibold text-fg">Trial balance</h3>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => downloadTb.mutate('pdf')} disabled={downloadTb.isPending}>
                            {downloadTb.isPending ? <Loader2 size={16} className="animate-spin" /> : <Download size={16} />} PDF
                        </Button>
                        <Button variant="outline" onClick={() => downloadTb.mutate('excel')} disabled={downloadTb.isPending}>
                            <FileSpreadsheet size={16} /> Excel
                        </Button>
                    </div>
                </div>
                {downloadTb.isError && <div className="border-t border-border px-5 py-3 text-[13.5px] text-rose-500">{downloadTb.error?.message}</div>}
                <div className="overflow-x-auto border-t border-border">
                    {tbLoading ? (
                        <div className="flex items-center justify-center gap-2 py-10 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                    ) : (
                        <table className="w-full min-w-[560px] text-left text-[13.5px]">
                            <thead>
                                <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                    <th className="px-5 py-2.5 font-semibold">Account</th>
                                    <th className="px-5 py-2.5 font-semibold">Debit</th>
                                    <th className="px-5 py-2.5 font-semibold">Credit</th>
                                    <th className="px-5 py-2.5 font-semibold">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                {tb?.rows.map((r) => (
                                    <tr key={r.ledger_account_id} className="border-b border-border last:border-0">
                                        <td className="px-5 py-2.5 font-medium text-fg">{r.name}</td>
                                        <td className="tnum px-5 py-2.5 text-muted">{r.debit}</td>
                                        <td className="tnum px-5 py-2.5 text-muted">{r.credit}</td>
                                        <td className="tnum px-5 py-2.5 font-medium text-fg">{r.balance} <span className="text-faint">{r.balance_side === 'debit' ? 'Dr' : 'Cr'}</span></td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr className="border-t border-border font-semibold text-fg">
                                    <td className="px-5 py-2.5">Total</td>
                                    <td className="tnum px-5 py-2.5">{tb?.total_debit}</td>
                                    <td className="tnum px-5 py-2.5">{tb?.total_credit}</td>
                                    <td />
                                </tr>
                            </tfoot>
                        </table>
                    )}
                </div>
            </Card>

            <Card>
                <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                    <h3 className="text-[15px] font-semibold text-fg">Income statement</h3>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => downloadIs.mutate('pdf')} disabled={downloadIs.isPending}>
                            {downloadIs.isPending ? <Loader2 size={16} className="animate-spin" /> : <Download size={16} />} PDF
                        </Button>
                        <Button variant="outline" onClick={() => downloadIs.mutate('excel')} disabled={downloadIs.isPending}>
                            <FileSpreadsheet size={16} /> Excel
                        </Button>
                    </div>
                </div>
                {downloadIs.isError && <div className="border-t border-border px-5 py-3 text-[13.5px] text-rose-500">{downloadIs.error?.message}</div>}
                <div className="border-t border-border px-5 py-4">
                    {isLoading2 ? (
                        <div className="flex items-center justify-center gap-2 py-10 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                    ) : (
                        <div className="grid gap-6 sm:grid-cols-2">
                            <div>
                                <div className="mb-2 text-[12.5px] font-semibold uppercase tracking-wide text-faint">Income</div>
                                {is?.income.map((i) => (
                                    <div key={i.code} className="flex justify-between border-b border-border py-1.5 text-[13.5px] last:border-0">
                                        <span className="text-muted">{i.name}</span><span className="tnum font-medium text-fg">{i.amount}</span>
                                    </div>
                                ))}
                                <div className="flex justify-between pt-2 text-[13.5px] font-semibold text-fg">
                                    <span>Total income</span><span>{is?.total_income}</span>
                                </div>
                            </div>
                            <div>
                                <div className="mb-2 text-[12.5px] font-semibold uppercase tracking-wide text-faint">Expense</div>
                                {is?.expense.map((i) => (
                                    <div key={i.code} className="flex justify-between border-b border-border py-1.5 text-[13.5px] last:border-0">
                                        <span className="text-muted">{i.name}</span><span className="tnum font-medium text-fg">{i.amount}</span>
                                    </div>
                                ))}
                                <div className="flex justify-between pt-2 text-[13.5px] font-semibold text-fg">
                                    <span>Total expense</span><span>{is?.total_expense}</span>
                                </div>
                            </div>
                        </div>
                    )}
                    {!isLoading2 && (
                        <div className="mt-4 flex justify-between border-t border-border pt-4 text-[15px] font-bold text-fg">
                            <span>Net</span><span>{is?.net}</span>
                        </div>
                    )}
                </div>
            </Card>
        </div>
    );
}
