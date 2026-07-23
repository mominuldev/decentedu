import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Loader2, Inbox, ChevronLeft, ChevronRight } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { listAuditLogs } from './api';

const ACTION_TONE: Record<string, 'success' | 'brand' | 'danger' | 'warning'> = {
    created: 'success',
    updated: 'brand',
    deleted: 'danger',
    bulk_saved: 'warning',
    general_process: 'warning',
    final_process: 'warning',
    merit_process: 'warning',
};

export default function AuditLogPage() {
    const [page, setPage] = useState(1);
    const { data, isLoading } = useQuery({ queryKey: ['audit-logs', page], queryFn: () => listAuditLogs(page) });

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-[26px] font-bold tracking-tight text-fg">Audit Log</h1>
                <p className="mt-1 text-[14px] text-muted">Who changed what, and when — marks, fees, vouchers, users, and permissions.</p>
            </div>

            <Card>
                <div className="overflow-x-auto">
                    {isLoading ? (
                        <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                    ) : !data || data.rows.length === 0 ? (
                        <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                            <div className="grid h-12 w-12 place-items-center rounded-2xl bg-surface-2 text-faint"><Inbox size={22} /></div>
                            <p className="text-[14px] font-medium text-fg">No activity recorded yet</p>
                        </div>
                    ) : (
                        <table className="w-full min-w-[760px] text-left text-[13.5px]">
                            <thead>
                                <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                    <th className="px-5 py-2.5 font-semibold">When</th>
                                    <th className="px-5 py-2.5 font-semibold">User</th>
                                    <th className="px-5 py-2.5 font-semibold">Model</th>
                                    <th className="px-5 py-2.5 font-semibold">Action</th>
                                    <th className="px-5 py-2.5 font-semibold">Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                {data.rows.map((r) => (
                                    <tr key={r.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                        <td className="whitespace-nowrap px-5 py-3 text-muted">{r.created_at}</td>
                                        <td className="px-5 py-3 text-fg">{r.user ?? '—'}</td>
                                        <td className="px-5 py-3 text-muted">{r.auditable_type} #{r.auditable_id}</td>
                                        <td className="px-5 py-3"><Badge tone={ACTION_TONE[r.action] ?? 'brand'}>{r.action}</Badge></td>
                                        <td className="max-w-[360px] truncate px-5 py-3 font-mono text-[12px] text-faint">{JSON.stringify(r.changes)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
                {data && data.pagination.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-border px-5 py-3.5">
                        <p className="text-[12.5px] text-muted">Page {data.pagination.current_page} of {data.pagination.last_page} · {data.pagination.total} entries</p>
                        <div className="flex gap-1.5">
                            <Button variant="outline" onClick={() => setPage((p) => p - 1)} disabled={page <= 1}><ChevronLeft size={16} /> Prev</Button>
                            <Button variant="outline" onClick={() => setPage((p) => p + 1)} disabled={page >= data.pagination.last_page}>Next <ChevronRight size={16} /></Button>
                        </div>
                    </div>
                )}
            </Card>
        </div>
    );
}
