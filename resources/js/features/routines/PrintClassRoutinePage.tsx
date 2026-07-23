import { useMemo } from 'react';
import { useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Printer, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui';
import { useAuth } from '@/features/auth/AuthProvider';
import { classRoutine, routineOptions, DAYS } from './api';
import { listClassConfigs } from '@/features/academic/api';

/**
 * Standalone routed print view (doc 08: "dedicated /print/* routes ... where a live view
 * suffices") — no server PDF, just print CSS (.print-area, resources/css/app.css) + window.print().
 */
export default function PrintClassRoutinePage() {
    const { classConfigId } = useParams<{ classConfigId: string }>();
    const id = Number(classConfigId);
    const { session } = useAuth();

    const { data: classConfigs = [] } = useQuery({ queryKey: ['class-configs'], queryFn: listClassConfigs });
    const { data: slots = [], isLoading } = useQuery({ queryKey: ['class-routine', id], queryFn: () => classRoutine(id), enabled: !!id });
    const { data: options } = useQuery({ queryKey: ['routine-options', id], queryFn: () => routineOptions(id), enabled: !!id });

    const grid = useMemo(() => {
        const map = new Map<string, typeof slots[number]>();
        for (const s of slots) map.set(`${s.day_of_week}:${s.period_id}`, s);
        return map;
    }, [slots]);

    const classLabel = classConfigs.find((c) => c.id === id)?.label ?? `Class #${id}`;

    if (isLoading || !options) {
        return <div className="flex min-h-screen items-center justify-center gap-2 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>;
    }

    return (
        <div className="min-h-screen bg-bg">
            <div className="flex items-center justify-between border-b border-border px-6 py-4 print:hidden">
                <h1 className="text-[16px] font-semibold text-fg">Class Routine — {classLabel}</h1>
                <Button onClick={() => window.print()}><Printer size={16} /> Print</Button>
            </div>

            <div className="print-area mx-auto max-w-4xl bg-white p-8 text-slate-900">
                <div className="mb-6 text-center">
                    <h2 className="text-xl font-bold">{session?.active_branch?.name}</h2>
                    <p className="text-sm text-slate-600">Weekly Class Routine — {classLabel}</p>
                </div>
                <table className="w-full table-fixed border-collapse text-left text-[12px]">
                    <thead>
                        <tr>
                            <th className="border border-slate-300 px-2 py-2 font-semibold">Day</th>
                            {options.periods.map((p) => (
                                <th key={p.id} className="border border-slate-300 px-2 py-2 font-semibold">
                                    <div>{p.name}</div>
                                    <div className="font-normal text-slate-500">{p.start_time}–{p.end_time}</div>
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {DAYS.map((d) => (
                            <tr key={d.value}>
                                <td className="border border-slate-300 px-2 py-2 font-medium">{d.label}</td>
                                {options.periods.map((p) => {
                                    const slot = grid.get(`${d.value}:${p.id}`);
                                    return (
                                        <td key={p.id} className="border border-slate-300 px-2 py-1.5 align-top">
                                            {slot ? (
                                                <>
                                                    <div className="font-semibold">{slot.subject_name}</div>
                                                    <div className="text-slate-500">{slot.employee_name ?? '—'}</div>
                                                    {slot.room && <div className="text-slate-400">{slot.room}</div>}
                                                </>
                                            ) : (
                                                <span className="text-slate-300">—</span>
                                            )}
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
