import { Fragment, useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2, Save } from 'lucide-react';
import { Card, Button } from '@/components/ui';
import { toApiError } from '@/lib/api';
import { listClassConfigs } from '@/features/academic/api';
import { listSetup, markConfigOptions, listMarkConfigs, saveMarkConfigs } from './api';

type Cell = { total: string; pass: string };

export function MarkConfigPanel() {
    const { data: classConfigs = [] } = useQuery({ queryKey: ['class-configs'], queryFn: listClassConfigs });
    const { data: exams = [] } = useQuery({ queryKey: ['exam-setup', 'exams'], queryFn: () => listSetup('exams') });
    const { data: options } = useQuery({ queryKey: ['mark-config-options'], queryFn: markConfigOptions });

    const [classConfigId, setClassConfigId] = useState(0);
    const [examId, setExamId] = useState(0);
    const qc = useQueryClient();

    const { data: existing = [], isLoading } = useQuery({
        queryKey: ['mark-configs', classConfigId, examId],
        queryFn: () => listMarkConfigs({ class_config_id: classConfigId, exam_id: examId }),
        enabled: !!classConfigId && !!examId,
    });

    const [grid, setGrid] = useState<Record<number, Record<number, Cell>>>({});

    useEffect(() => {
        if (!options) return;
        const next: Record<number, Record<number, Cell>> = {};
        for (const s of options.subjects) {
            next[s.id] = {};
            for (const sc of options.short_codes) {
                const found = existing.find((m) => m.subject_id === s.id && m.short_code_id === sc.id);
                next[s.id][sc.id] = { total: found?.total_marks ?? '', pass: found?.pass_mark ?? '' };
            }
        }
        setGrid(next);
    }, [existing, options]);

    const setCell = (subjectId: number, shortCodeId: number, key: keyof Cell, value: string) => {
        setGrid((g) => ({ ...g, [subjectId]: { ...g[subjectId], [shortCodeId]: { ...g[subjectId]?.[shortCodeId], [key]: value } } }));
    };

    const save = useMutation({
        mutationFn: () => {
            const items = Object.entries(grid).flatMap(([subjectId, byShortCode]) =>
                Object.entries(byShortCode)
                    .filter(([, cell]) => cell.total !== '' && cell.pass !== '')
                    .map(([shortCodeId, cell]) => ({
                        subject_id: Number(subjectId),
                        short_code_id: Number(shortCodeId),
                        total_marks: Number(cell.total),
                        pass_mark: Number(cell.pass),
                    })),
            );

            return saveMarkConfigs({ class_config_id: classConfigId, exam_id: examId, items });
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['mark-configs', classConfigId, examId] }),
    });

    const ready = !!classConfigId && !!examId && !!options;
    const totalFor = (subjectId: number) => options?.short_codes.reduce((sum, sc) => sum + (Number(grid[subjectId]?.[sc.id]?.total) || 0), 0) ?? 0;

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Mark config</h3>
                    <p className="text-[12.5px] text-muted">Total &amp; pass mark per subject component, for one class × exam</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <select value={classConfigId} onChange={(e) => setClassConfigId(Number(e.target.value))}
                        className="rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
                        <option value={0} disabled>Select a class</option>
                        {classConfigs.map((c) => <option key={c.id} value={c.id}>{c.label}</option>)}
                    </select>
                    <select value={examId} onChange={(e) => setExamId(Number(e.target.value))}
                        className="rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
                        <option value={0} disabled>Select an exam</option>
                        {exams.map((e) => <option key={e.id} value={e.id}>{e.name}</option>)}
                    </select>
                </div>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {!ready ? (
                    <div className="flex items-center justify-center py-16 text-[14px] font-medium text-fg">Select a class and an exam</div>
                ) : isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : (
                    <table className="w-full min-w-[640px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Subject</th>
                                {options.short_codes.map((sc) => (
                                    <th key={sc.id} className="px-3 py-2.5 font-semibold" colSpan={2}>{sc.name} (Total / Pass)</th>
                                ))}
                                <th className="px-3 py-2.5 font-semibold">Subject total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {options.subjects.map((s) => (
                                <tr key={s.id} className="border-b border-border last:border-0">
                                    <td className="px-5 py-2 font-medium text-fg">{s.name}</td>
                                    {options.short_codes.map((sc) => (
                                        <Fragment key={sc.id}>
                                            <td className="p-1.5">
                                                <input type="number" value={grid[s.id]?.[sc.id]?.total ?? ''} placeholder="Total"
                                                    onChange={(e) => setCell(s.id, sc.id, 'total', e.target.value)}
                                                    className="w-20 rounded-lg border border-border-strong bg-surface px-2 py-1.5 text-[13px] text-fg outline-none focus:border-brand-500" />
                                            </td>
                                            <td className="p-1.5">
                                                <input type="number" value={grid[s.id]?.[sc.id]?.pass ?? ''} placeholder="Pass"
                                                    onChange={(e) => setCell(s.id, sc.id, 'pass', e.target.value)}
                                                    className="w-20 rounded-lg border border-border-strong bg-surface px-2 py-1.5 text-[13px] text-fg outline-none focus:border-brand-500" />
                                            </td>
                                        </Fragment>
                                    ))}
                                    <td className="tnum px-3 py-2 text-muted">{totalFor(s.id)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {ready && !isLoading && (
                <div className="flex items-center justify-end gap-3 border-t border-border px-5 py-4">
                    {save.isError && <span className="text-[13px] text-rose-500">{toApiError(save.error).message}</span>}
                    {save.isSuccess && <span className="text-[13px] text-emerald-600">Saved.</span>}
                    <Button onClick={() => save.mutate()} disabled={save.isPending}>
                        {save.isPending ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />} Save configuration
                    </Button>
                </div>
            )}
        </Card>
    );
}
