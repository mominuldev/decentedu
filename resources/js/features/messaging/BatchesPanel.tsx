import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Loader2, Inbox, X } from 'lucide-react';
import { Card, Badge } from '@/components/ui';
import { listBatches, getBatch, type BatchRow } from './api';

const statusTone: Record<BatchRow['status'], 'success' | 'warning' | 'danger' | 'neutral'> = {
    completed: 'success', processing: 'warning', queued: 'neutral', failed: 'danger',
};

export function BatchesPanel() {
    const { data: rows = [], isLoading } = useQuery({ queryKey: ['sms-batches'], queryFn: listBatches, refetchInterval: 5000 });
    const [openId, setOpenId] = useState<number | null>(null);
    const { data: detail } = useQuery({ queryKey: ['sms-batch', openId], queryFn: () => getBatch(openId as number), enabled: openId !== null });

    return (
        <Card>
            <div className="px-5 py-4">
                <h3 className="text-[15px] font-semibold text-fg">Delivery reports</h3>
                <p className="text-[12.5px] text-muted">{rows.length} batch{rows.length === 1 ? '' : 'es'}</p>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : rows.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                        <div className="grid h-12 w-12 place-items-center rounded-2xl bg-surface-2 text-faint"><Inbox size={22} /></div>
                        <p className="text-[14px] font-medium text-fg">No batches sent yet</p>
                    </div>
                ) : (
                    <table className="w-full min-w-[640px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">#</th>
                                <th className="px-5 py-2.5 font-semibold">Audience</th>
                                <th className="px-5 py-2.5 font-semibold">Recipients</th>
                                <th className="px-5 py-2.5 font-semibold">Sent / Failed</th>
                                <th className="px-5 py-2.5 font-semibold">Cost</th>
                                <th className="px-5 py-2.5 font-semibold">Status</th>
                                <th className="px-5 py-2.5 font-semibold">Sent at</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={r.id} onClick={() => setOpenId(r.id)} className="cursor-pointer border-b border-border last:border-0 hover:bg-surface-2/50">
                                    <td className="px-5 py-3 text-muted">{r.id}</td>
                                    <td className="px-5 py-3 text-muted capitalize">{r.audience_type.replace('_', ' ')}</td>
                                    <td className="tnum px-5 py-3 text-muted">{r.total_recipients}</td>
                                    <td className="tnum px-5 py-3 text-muted">{r.sent_count} / {r.failed_count}</td>
                                    <td className="tnum px-5 py-3 text-muted">{r.total_cost}</td>
                                    <td className="px-5 py-3"><Badge tone={statusTone[r.status]}>{r.status}</Badge></td>
                                    <td className="px-5 py-3 text-muted">{new Date(r.created_at).toLocaleString()}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {openId !== null && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" onClick={() => setOpenId(null)} />
                    <div className="relative max-h-[80vh] w-full max-w-2xl overflow-y-auto rounded-2xl border border-border bg-surface shadow-[var(--shadow-pop)]">
                        <div className="flex items-center justify-between border-b border-border px-5 py-4">
                            <h3 className="text-[16px] font-semibold text-fg">Batch #{openId} delivery report</h3>
                            <button onClick={() => setOpenId(null)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-fg"><X size={18} /></button>
                        </div>
                        <div className="px-5 py-5">
                            {!detail ? (
                                <div className="flex items-center justify-center gap-2 py-10 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                            ) : (
                                <table className="w-full text-left text-[13.5px]">
                                    <thead>
                                        <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                            <th className="py-2 font-semibold">Recipient</th>
                                            <th className="py-2 font-semibold">Phone</th>
                                            <th className="py-2 font-semibold">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {detail.messages.map((m) => (
                                            <tr key={m.id} className="border-b border-border last:border-0">
                                                <td className="py-2 text-fg">{m.recipient_name ?? '—'}</td>
                                                <td className="tnum py-2 text-muted">{m.recipient_phone}</td>
                                                <td className="py-2"><Badge tone={m.status === 'sent' ? 'success' : m.status === 'failed' ? 'danger' : 'neutral'}>{m.status}</Badge></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </Card>
    );
}
