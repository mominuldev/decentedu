import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Loader2, Inbox } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { listLedgerAccounts, createLedgerAccount, updateLedgerAccount, deleteLedgerAccount, type LedgerAccountRow } from './api';

const typeLabels: Record<string, string> = { asset: 'Asset', liability: 'Liability', income: 'Income', expense: 'Expense', equity: 'Equity' };
const typeTones: Record<string, 'brand' | 'success' | 'warning' | 'danger' | 'sky'> = {
    asset: 'sky', liability: 'warning', income: 'success', expense: 'danger', equity: 'brand',
};

export function LedgerAccountsPanel() {
    const qc = useQueryClient();
    const { data: rows = [], isLoading } = useQuery({ queryKey: ['ledger-accounts'], queryFn: () => listLedgerAccounts() });

    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<LedgerAccountRow | null>(null);
    const [deleting, setDeleting] = useState<LedgerAccountRow | null>(null);

    const invalidate = () => qc.invalidateQueries({ queryKey: ['ledger-accounts'] });
    const del = useMutation({
        mutationFn: (id: number) => deleteLedgerAccount(id),
        onSuccess: () => { invalidate(); setDeleting(null); },
    });

    return (
        <Card>
            <div className="flex items-center justify-between px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Chart of accounts</h3>
                    <p className="text-[12.5px] text-muted">System accounts (Cash, fee-head income…) are auto-provisioned and cannot be edited</p>
                </div>
                <Button onClick={() => setCreating(true)}><Plus size={16} /> Add account</Button>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : rows.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                        <div className="grid h-12 w-12 place-items-center rounded-2xl bg-surface-2 text-faint"><Inbox size={22} /></div>
                        <p className="text-[14px] font-medium text-fg">No ledger accounts yet</p>
                    </div>
                ) : (
                    <table className="w-full min-w-[640px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Code</th>
                                <th className="px-5 py-2.5 font-semibold">Name</th>
                                <th className="px-5 py-2.5 font-semibold">Type</th>
                                <th className="px-5 py-2.5 font-semibold">Opening balance</th>
                                <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={r.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                    <td className="px-5 py-3 font-mono text-[12.5px] text-muted">{r.code}</td>
                                    <td className="px-5 py-3 font-medium text-fg">
                                        {r.name} {r.is_system && <Badge tone="neutral" className="ml-1.5">System</Badge>}
                                    </td>
                                    <td className="px-5 py-3"><Badge tone={typeTones[r.type]}>{typeLabels[r.type]}</Badge></td>
                                    <td className="tnum px-5 py-3 text-muted">{r.opening_balance}</td>
                                    <td className="px-5 py-3">
                                        {!r.is_system && (
                                            <div className="flex justify-end gap-1">
                                                <button onClick={() => setEditing(r)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600" aria-label="Edit">
                                                    <Pencil size={16} />
                                                </button>
                                                <button onClick={() => setDeleting(r)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500" aria-label="Delete">
                                                    <Trash2 size={16} />
                                                </button>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {(creating || editing) && (
                <LedgerAccountForm row={editing} onClose={() => { setCreating(false); setEditing(null); }} onSaved={() => { invalidate(); setCreating(false); setEditing(null); }} />
            )}

            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={() => deleting && del.mutate(deleting.id)}
                busy={del.isPending}
                title="Delete ledger account"
                message={`Are you sure you want to delete "${deleting?.name}"?`}
            />
        </Card>
    );
}

function LedgerAccountForm({ row, onClose, onSaved }: { row: LedgerAccountRow | null; onClose: () => void; onSaved: () => void }) {
    const [name, setName] = useState(row?.name ?? '');
    const [code, setCode] = useState(row?.code ?? '');
    const [type, setType] = useState(row?.type ?? 'expense');
    const [openingBalance, setOpeningBalance] = useState(row?.opening_balance ?? '0');
    const [error, setError] = useState<string | null>(null);

    const save = useMutation({
        mutationFn: () => {
            const payload = { name, code, type, opening_balance: Number(openingBalance) };
            return row ? updateLedgerAccount(row.id, payload) : createLedgerAccount(payload);
        },
        onSuccess: onSaved,
        onError: (e) => setError(toApiError(e).message),
    });

    const inputCls = 'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';

    return (
        <Modal
            open onClose={onClose} title={row ? 'Edit ledger account' : 'Add ledger account'}
            footer={
                <>
                    <Button variant="outline" onClick={onClose} disabled={save.isPending}>Cancel</Button>
                    <Button onClick={() => { setError(null); save.mutate(); }} disabled={save.isPending || !name || !code}>
                        {save.isPending ? <Loader2 size={16} className="animate-spin" /> : null} Save
                    </Button>
                </>
            }
        >
            {error && <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
            <div className="space-y-4">
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Name</label>
                    <input value={name} onChange={(e) => setName(e.target.value)} className={inputCls} />
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Code</label>
                    <input value={code} onChange={(e) => setCode(e.target.value)} className={inputCls} />
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Type</label>
                    <select value={type} onChange={(e) => setType(e.target.value as typeof type)} className={inputCls}>
                        <option value="asset">Asset</option>
                        <option value="liability">Liability</option>
                        <option value="income">Income</option>
                        <option value="expense">Expense</option>
                        <option value="equity">Equity</option>
                    </select>
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Opening balance</label>
                    <input type="number" value={openingBalance} onChange={(e) => setOpeningBalance(e.target.value)} className={inputCls} />
                </div>
            </div>
        </Modal>
    );
}
