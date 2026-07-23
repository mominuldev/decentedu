import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CheckCheck, Loader2, Save } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { toApiError } from '@/lib/api';
import { cn } from '@/lib/cn';
import { employeeRoster, takeEmployeeAttendance, STATUSES, type AttendanceStatus } from './api';

const todayIso = () => new Date().toISOString().slice(0, 10);

export function TakeEmployeeAttendance() {
    const [date, setDate] = useState(todayIso());
    const qc = useQueryClient();

    const { data: roster = [], isLoading } = useQuery({
        queryKey: ['employee-roster', date],
        queryFn: () => employeeRoster(date),
    });

    const [entries, setEntries] = useState<Record<number, { status: AttendanceStatus; remarks: string }>>({});
    const [saved, setSaved] = useState(false);

    useEffect(() => {
        const next: Record<number, { status: AttendanceStatus; remarks: string }> = {};
        for (const r of roster) next[r.employee_id] = { status: r.status, remarks: r.remarks ?? '' };
        setEntries(next);
        setSaved(false);
    }, [roster]);

    const setStatus = (id: number, status: AttendanceStatus) => { setEntries((e) => ({ ...e, [id]: { ...e[id], status } })); setSaved(false); };
    const setRemarks = (id: number, remarks: string) => { setEntries((e) => ({ ...e, [id]: { ...e[id], remarks } })); setSaved(false); };
    const markAllPresent = () => {
        setEntries((e) => Object.fromEntries(Object.keys(e).map((id) => [id, { ...e[Number(id)], status: 'present' as AttendanceStatus }])));
        setSaved(false);
    };

    const counts = useMemo(() => {
        const c: Record<AttendanceStatus, number> = { present: 0, absent: 0, late: 0, leave: 0, half_day: 0 };
        for (const v of Object.values(entries)) c[v.status]++;
        return c;
    }, [entries]);

    const save = useMutation({
        mutationFn: () => takeEmployeeAttendance(
            date,
            roster.map((r) => ({ employee_id: r.employee_id, status: entries[r.employee_id].status, remarks: entries[r.employee_id].remarks || null })),
        ),
        onSuccess: () => { qc.invalidateQueries({ queryKey: ['employee-roster', date] }); setSaved(true); },
    });

    const selCls = 'rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Staff attendance</h3>
                    <p className="text-[12.5px] text-muted">All active employees for the selected date</p>
                </div>
                <input type="date" value={date} onChange={(e) => setDate(e.target.value)} className={cn(selCls, 'tnum')} />
            </div>

            {roster.length > 0 && (
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3">
                    <div className="flex flex-wrap gap-2 text-[12.5px]">
                        <Badge tone="success">{counts.present} present</Badge>
                        <Badge tone="danger">{counts.absent} absent</Badge>
                        <Badge tone="warning">{counts.late} late</Badge>
                        <Badge tone="sky">{counts.leave} leave</Badge>
                        <Badge tone="neutral">{counts.half_day} half day</Badge>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" onClick={markAllPresent}><CheckCheck size={16} /> Mark all present</Button>
                        <Button onClick={() => save.mutate()} disabled={save.isPending}>
                            {save.isPending ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />}
                            {saved ? 'Saved' : 'Save attendance'}
                        </Button>
                    </div>
                </div>
            )}

            <div className="overflow-x-auto border-t border-border">
                {isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : roster.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                        <p className="text-[14px] font-medium text-fg">No active employees found</p>
                    </div>
                ) : (
                    <table className="w-full min-w-[720px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Employee</th>
                                <th className="px-5 py-2.5 font-semibold">Status</th>
                                <th className="px-5 py-2.5 font-semibold">Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            {roster.map((r) => {
                                const entry = entries[r.employee_id];
                                if (!entry) return null;
                                return (
                                    <tr key={r.employee_id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                        <td className="px-5 py-3">
                                            <div className="font-medium text-fg">{r.name}</div>
                                            {r.name_bn && <div className="text-[12px] text-faint">{r.name_bn}</div>}
                                        </td>
                                        <td className="px-5 py-3">
                                            <div className="flex flex-wrap gap-1">
                                                {STATUSES.map((s) => (
                                                    <button
                                                        key={s.value}
                                                        onClick={() => setStatus(r.employee_id, s.value)}
                                                        className={cn(
                                                            'rounded-lg px-2 py-1 text-[11.5px] font-medium ring-1 ring-inset transition-colors',
                                                            entry.status === s.value ? statusTone[s.value] : 'text-muted ring-border hover:bg-surface-2',
                                                        )}
                                                    >
                                                        {s.label}
                                                    </button>
                                                ))}
                                            </div>
                                        </td>
                                        <td className="px-5 py-3">
                                            <input
                                                value={entry.remarks}
                                                onChange={(e) => setRemarks(r.employee_id, e.target.value)}
                                                placeholder="Optional"
                                                className="w-full rounded-lg border border-border-strong bg-surface px-2.5 py-1.5 text-[13px] text-fg outline-none focus:border-brand-500"
                                            />
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                )}
            </div>

            {save.isError && (
                <div className="mx-5 mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">
                    {toApiError(save.error).message}
                </div>
            )}
        </Card>
    );
}

const statusTone: Record<AttendanceStatus, string> = {
    present: 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/25',
    absent: 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/25',
    late: 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/25',
    leave: 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/25',
    half_day: 'bg-surface-2 text-fg ring-border',
};
