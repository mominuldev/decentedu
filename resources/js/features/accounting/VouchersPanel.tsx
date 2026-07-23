import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Loader2, Inbox, Trash2 } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Modal } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { listVouchers, createVoucher, listLedgerAccounts } from './api';

const typeTones: Record<string, 'brand' | 'success' | 'warning' | 'danger'> = {
    receive: 'success', payment: 'danger', contra: 'warning', journal: 'brand',
};

export function VouchersPanel() {
    const qc = useQueryClient();
    const [type, setType] = useState('');
    const { data: rows = [], isLoading } = useQuery({ queryKey: ['vouchers', type], queryFn: () => listVouchers(type ? { type } : {}) });
    const [creating, setCreating] = useState(false);

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Vouchers</h3>
                    <p className="text-[12.5px] text-muted">Receive vouchers from fee collections post automatically; add payment/contra/journal entries manually</p>
                </div>
                <div className="flex gap-2">
                    <select value={type} onChange={(e) => setType(e.target.value)}
                        className="rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
                        <option value="">All types</option>
                        <option value="receive">Receive</option>
                        <option value="payment">Payment</option>
                        <option value="contra">Contra</option>
                        <option value="journal">Journal</option>
                    </select>
                    <Button onClick={() => setCreating(true)}><Plus size={16} /> New voucher</Button>
                </div>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : rows.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                        <div className="grid h-12 w-12 place-items-center rounded-2xl bg-surface-2 text-faint"><Inbox size={22} /></div>
                        <p className="text-[14px] font-medium text-fg">No vouchers yet</p>
                    </div>
                ) : (
                    <table className="w-full min-w-[640px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Voucher</th>
                                <th className="px-5 py-2.5 font-semibold">Type</th>
                                <th className="px-5 py-2.5 font-semibold">Date</th>
                                <th className="px-5 py-2.5 font-semibold">Note</th>
                                <th className="px-5 py-2.5 font-semibold">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((v) => (
                                <tr key={v.id} className="border-b border-border last:border-0">
                                    <td className="px-5 py-3 font-medium text-fg">{v.voucher_no}</td>
                                    <td className="px-5 py-3"><Badge tone={typeTones[v.type]} className="capitalize">{v.type}</Badge></td>
                                    <td className="px-5 py-3 text-muted">{v.date}</td>
                                    <td className="px-5 py-3 text-muted">{v.note ?? '—'}</td>
                                    <td className="tnum px-5 py-3 font-medium text-fg">{v.total}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {creating && (
                <VoucherForm onClose={() => setCreating(false)} onSaved={() => { qc.invalidateQueries({ queryKey: ['vouchers'] }); setCreating(false); }} />
            )}
        </Card>
    );
}

type EntryRow = { ledger_account_id: number; debit: string; credit: string };

function VoucherForm({ onClose, onSaved }: { onClose: () => void; onSaved: () => void }) {
    const { data: ledgers = [] } = useQuery({ queryKey: ['ledger-accounts'], queryFn: () => listLedgerAccounts() });
    const [type, setType] = useState('payment');
    const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
    const [note, setNote] = useState('');
    const [entries, setEntries] = useState<EntryRow[]>([{ ledger_account_id: 0, debit: '', credit: '' }, { ledger_account_id: 0, debit: '', credit: '' }]);
    const [error, setError] = useState<string | null>(null);

    const totalDebit = entries.reduce((s, e) => s + (Number(e.debit) || 0), 0);
    const totalCredit = entries.reduce((s, e) => s + (Number(e.credit) || 0), 0);
    const balanced = totalDebit > 0 && Math.abs(totalDebit - totalCredit) < 0.01;

    const save = useMutation({
        mutationFn: () => createVoucher({
            type, date, note: note || undefined,
            entries: entries.filter((e) => e.ledger_account_id > 0).map((e) => ({ ledger_account_id: e.ledger_account_id, debit: Number(e.debit) || 0, credit: Number(e.credit) || 0 })),
        }),
        onSuccess: onSaved,
        onError: (e) => setError(toApiError(e).message),
    });

    const setEntry = (i: number, patch: Partial<EntryRow>) => setEntries((rows) => rows.map((r, idx) => (idx === i ? { ...r, ...patch } : r)));
    const inputCls = 'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';

    return (
        <Modal
            open onClose={onClose} title="New voucher" width="max-w-2xl"
            footer={
                <>
                    <Button variant="outline" onClick={onClose} disabled={save.isPending}>Cancel</Button>
                    <Button onClick={() => { setError(null); save.mutate(); }} disabled={save.isPending || !balanced}>
                        {save.isPending ? <Loader2 size={16} className="animate-spin" /> : null} Post voucher
                    </Button>
                </>
            }
        >
            {error && <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
            <div className="space-y-4">
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">Type</label>
                        <select value={type} onChange={(e) => setType(e.target.value)} className={inputCls}>
                            <option value="payment">Payment</option>
                            <option value="receive">Receive</option>
                            <option value="contra">Contra</option>
                            <option value="journal">Journal</option>
                        </select>
                    </div>
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">Date</label>
                        <input type="date" value={date} onChange={(e) => setDate(e.target.value)} className={inputCls} />
                    </div>
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Note</label>
                    <input value={note} onChange={(e) => setNote(e.target.value)} className={inputCls} />
                </div>

                <div>
                    <div className="mb-1.5 flex items-center justify-between">
                        <label className="text-[13px] font-medium text-fg">Entries</label>
                        <button type="button" onClick={() => setEntries((r) => [...r, { ledger_account_id: 0, debit: '', credit: '' }])}
                            className="text-[12.5px] font-medium text-brand-600 hover:underline">+ Add row</button>
                    </div>
                    <div className="space-y-2">
                        {entries.map((row, i) => (
                            <div key={i} className="flex items-center gap-2">
                                <select value={row.ledger_account_id} onChange={(e) => setEntry(i, { ledger_account_id: Number(e.target.value) })} className={inputCls}>
                                    <option value={0} disabled>Ledger account</option>
                                    {ledgers.map((l) => <option key={l.id} value={l.id}>{l.name}</option>)}
                                </select>
                                <input type="number" placeholder="Debit" value={row.debit} onChange={(e) => setEntry(i, { debit: e.target.value })}
                                    className="w-28 rounded-xl border border-border-strong bg-surface px-3 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500" />
                                <input type="number" placeholder="Credit" value={row.credit} onChange={(e) => setEntry(i, { credit: e.target.value })}
                                    className="w-28 rounded-xl border border-border-strong bg-surface px-3 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500" />
                                {entries.length > 2 && (
                                    <button type="button" onClick={() => setEntries((r) => r.filter((_, idx) => idx !== i))} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500">
                                        <Trash2 size={16} />
                                    </button>
                                )}
                            </div>
                        ))}
                    </div>
                    <div className={`mt-2 text-[13px] ${balanced ? 'text-emerald-600' : 'text-rose-500'}`}>
                        Debit {totalDebit.toFixed(2)} · Credit {totalCredit.toFixed(2)} {!balanced && '— must be equal and greater than zero'}
                    </div>
                </div>
            </div>
        </Modal>
    );
}
