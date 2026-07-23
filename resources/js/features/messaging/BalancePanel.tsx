import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2, Wallet } from 'lucide-react';
import { Card, Button } from '@/components/ui';
import { toApiError } from '@/lib/api';
import { getBalance, topupBalance } from './api';

export function BalancePanel() {
    const qc = useQueryClient();
    const { data: balance, isLoading } = useQuery({ queryKey: ['sms-balance'], queryFn: getBalance });
    const [amount, setAmount] = useState('');
    const [error, setError] = useState<string | null>(null);

    const topup = useMutation({
        mutationFn: () => topupBalance(Number(amount)),
        onSuccess: () => { qc.invalidateQueries({ queryKey: ['sms-balance'] }); setAmount(''); },
        onError: (e) => setError(toApiError(e).message),
    });

    return (
        <Card className="max-w-md">
            <div className="flex items-center gap-4 px-5 py-6">
                <div className="grid h-12 w-12 place-items-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300"><Wallet size={22} /></div>
                <div>
                    <p className="text-[13px] text-muted">Current balance</p>
                    <p className="text-[26px] font-bold tabular-nums text-fg">{isLoading ? <Loader2 size={20} className="animate-spin" /> : balance?.toFixed(2)}</p>
                </div>
            </div>
            <div className="space-y-3 border-t border-border px-5 py-5">
                {error && <div className="rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
                <label className="block text-[13px] font-medium text-fg">Top up amount</label>
                <div className="flex gap-2">
                    <input type="number" min="0.01" step="0.01" value={amount} onChange={(e) => setAmount(e.target.value)}
                        className="w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25" />
                    <Button onClick={() => { setError(null); topup.mutate(); }} disabled={topup.isPending || !amount}>
                        {topup.isPending ? <Loader2 size={16} className="animate-spin" /> : null} Top up
                    </Button>
                </div>
            </div>
        </Card>
    );
}
