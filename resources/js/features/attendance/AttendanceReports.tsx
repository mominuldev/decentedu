import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Loader2 } from 'lucide-react';
import { Card, Badge } from '@/components/ui';
import { cn } from '@/lib/cn';
import { listClassConfigs } from '@/features/academic/api';
import { studentAttendanceReport, employeeAttendanceReport } from './api';

const todayIso = () => new Date().toISOString().slice(0, 10);
const weekAgoIso = () => new Date(Date.now() - 6 * 86400000).toISOString().slice(0, 10);

export function AttendanceReports() {
    const [scope, setScope] = useState<'students' | 'employees'>('students');
    const [from, setFrom] = useState(weekAgoIso());
    const [to, setTo] = useState(todayIso());

    const { data: classConfigs = [] } = useQuery({ queryKey: ['class-configs'], queryFn: listClassConfigs });
    const [classConfigId, setClassConfigId] = useState(0);

    const studentQuery = useQuery({
        queryKey: ['student-attendance-report', classConfigId, from, to],
        queryFn: () => studentAttendanceReport(classConfigId, from, to),
        enabled: scope === 'students' && !!classConfigId,
    });
    const employeeQuery = useQuery({
        queryKey: ['employee-attendance-report', from, to],
        queryFn: () => employeeAttendanceReport(from, to),
        enabled: scope === 'employees',
    });

    const rows = scope === 'students' ? (studentQuery.data ?? []) : (employeeQuery.data ?? []);
    const isLoading = scope === 'students' ? studentQuery.isLoading : employeeQuery.isLoading;

    const inputCls = 'rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Attendance report</h3>
                    <p className="text-[12.5px] text-muted">Per-person totals over a date range</p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <div className="flex rounded-xl border border-border-strong p-0.5">
                        {(['students', 'employees'] as const).map((s) => (
                            <button
                                key={s}
                                onClick={() => setScope(s)}
                                className={cn('rounded-lg px-3 py-1.5 text-[13px] font-medium capitalize', scope === s ? 'bg-brand-600 text-white' : 'text-muted hover:text-fg')}
                            >
                                {s}
                            </button>
                        ))}
                    </div>
                    {scope === 'students' && (
                        <select value={classConfigId} onChange={(e) => setClassConfigId(Number(e.target.value))} className={inputCls}>
                            <option value={0} disabled>Select a class</option>
                            {classConfigs.map((c) => <option key={c.id} value={c.id}>{c.label}</option>)}
                        </select>
                    )}
                    <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className={cn(inputCls, 'tnum')} />
                    <span className="text-muted">–</span>
                    <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className={cn(inputCls, 'tnum')} />
                </div>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {scope === 'students' && !classConfigId ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                        <p className="text-[14px] font-medium text-fg">Select a class to view its report</p>
                    </div>
                ) : isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : rows.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                        <p className="text-[14px] font-medium text-fg">No attendance recorded in this range</p>
                    </div>
                ) : (
                    <table className="w-full min-w-[640px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Name</th>
                                <th className="px-5 py-2.5 font-semibold">Present</th>
                                <th className="px-5 py-2.5 font-semibold">Absent</th>
                                <th className="px-5 py-2.5 font-semibold">Late</th>
                                <th className="px-5 py-2.5 font-semibold">Leave</th>
                                <th className="px-5 py-2.5 font-semibold">Half day</th>
                                <th className="px-5 py-2.5 font-semibold">Total days</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={r.student_id ?? r.employee_id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                    <td className="px-5 py-3 font-medium text-fg">{r.name}</td>
                                    <td className="px-5 py-3"><Badge tone="success">{r.present}</Badge></td>
                                    <td className="px-5 py-3"><Badge tone="danger">{r.absent}</Badge></td>
                                    <td className="px-5 py-3"><Badge tone="warning">{r.late}</Badge></td>
                                    <td className="px-5 py-3"><Badge tone="sky">{r.leave}</Badge></td>
                                    <td className="px-5 py-3"><Badge tone="neutral">{r.half_day}</Badge></td>
                                    <td className="tnum px-5 py-3 text-muted">{r.total_days}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </Card>
    );
}
