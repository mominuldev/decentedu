import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Loader2, Inbox } from 'lucide-react';
import { Card, Badge } from '@/components/ui';
import { listEmployees } from '@/features/hr/api';
import { teacherRoutine, DAYS } from './api';

export function TeacherRoutinePanel() {
    const { data: employeeList } = useQuery({
        queryKey: ['teachers', 'routine-picker'],
        queryFn: () => listEmployees({ teachers_only: true, per_page: 200 }),
    });
    const employees = employeeList?.data ?? [];
    const [employeeId, setEmployeeId] = useState<number>(0);

    const { data: slots = [], isLoading } = useQuery({
        queryKey: ['teacher-routine', employeeId],
        queryFn: () => teacherRoutine(employeeId),
        enabled: !!employeeId,
    });

    const byDay = useMemo(() => {
        const map = new Map<number, typeof slots>();
        for (const d of DAYS) map.set(d.value, []);
        for (const s of slots) map.get(s.day_of_week)?.push(s);
        for (const list of map.values()) {
            list.sort((a, b) => (a.period_name ?? '').localeCompare(b.period_name ?? '', undefined, { numeric: true }));
        }
        return map;
    }, [slots]);

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Teacher routine</h3>
                    <p className="text-[12.5px] text-muted">A teacher's full weekly schedule across all classes</p>
                </div>
                <select
                    value={employeeId}
                    onChange={(e) => setEmployeeId(Number(e.target.value))}
                    className="rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25"
                >
                    <option value={0} disabled>Select a teacher</option>
                    {employees.map((e) => <option key={e.id} value={e.id}>{e.name}</option>)}
                </select>
            </div>

            <div className="border-t border-border">
                {!employeeId ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                        <p className="text-[14px] font-medium text-fg">Select a teacher to view their routine</p>
                    </div>
                ) : isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : slots.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                        <div className="grid h-12 w-12 place-items-center rounded-2xl bg-surface-2 text-faint"><Inbox size={22} /></div>
                        <p className="text-[14px] font-medium text-fg">No classes assigned</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                        {DAYS.map((d) => (
                            <div key={d.value} className="rounded-xl border border-border p-4">
                                <h4 className="mb-2 text-[13px] font-semibold text-fg">{d.label}</h4>
                                {(byDay.get(d.value) ?? []).length === 0 ? (
                                    <p className="text-[12.5px] text-faint">No classes</p>
                                ) : (
                                    <ul className="space-y-2">
                                        {byDay.get(d.value)!.map((s) => (
                                            <li key={s.id} className="rounded-lg bg-surface-2 px-2.5 py-2">
                                                <div className="flex items-center justify-between gap-2">
                                                    <span className="text-[12.5px] font-medium text-fg">{s.subject_name}</span>
                                                    <Badge tone="brand">{s.period_name}</Badge>
                                                </div>
                                                <div className="text-[11.5px] text-muted">{s.class_label}{s.room ? ` · ${s.room}` : ''}</div>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </Card>
    );
}
