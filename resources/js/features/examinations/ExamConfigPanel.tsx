import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Pencil, Loader2 } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Modal } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { classConfigOptions } from '@/features/academic/api';
import { listExamConfigs, saveExamConfig, listSetup, type ExamConfig } from './api';

export function ExamConfigPanel() {
    const qc = useQueryClient();
    const { data: options } = useQuery({ queryKey: ['class-config-options'], queryFn: classConfigOptions });
    const { data: exams = [] } = useQuery({ queryKey: ['exam-setup', 'exams'], queryFn: () => listSetup('exams') });
    const { data: configs = [], isLoading } = useQuery({ queryKey: ['exam-configs'], queryFn: listExamConfigs });

    const [editing, setEditing] = useState<ExamConfig | { class_id: number } | null>(null);
    const invalidate = () => qc.invalidateQueries({ queryKey: ['exam-configs'] });

    return (
        <Card>
            <div className="px-5 py-4">
                <h3 className="text-[15px] font-semibold text-fg">Exam config</h3>
                <p className="text-[12.5px] text-muted">Per class: which exams count toward the result, and how merit/position is computed</p>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : (
                    <table className="w-full min-w-[640px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Class</th>
                                <th className="px-5 py-2.5 font-semibold">Merit basis</th>
                                <th className="px-5 py-2.5 font-semibold">Ranking</th>
                                <th className="px-5 py-2.5 font-semibold">Exams</th>
                                <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {options?.classes.map((c) => {
                                const config = configs.find((cfg) => cfg.class_id === c.id);

                                return (
                                    <tr key={c.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                        <td className="px-5 py-3 font-medium text-fg">{c.name}</td>
                                        <td className="px-5 py-3 text-muted">{config ? (config.merit_basis === 'grade_point' ? 'Grade point (GPA)' : 'Total mark') : '—'}</td>
                                        <td className="px-5 py-3 text-muted">{config ? (config.merit_sequential ? 'Sequential' : 'Standard (tied ranks)') : '—'}</td>
                                        <td className="px-5 py-3">
                                            {config && config.exam_names.length > 0
                                                ? config.exam_names.map((n) => <Badge key={n} tone="brand" className="mr-1">{n}</Badge>)
                                                : <span className="text-faint">Not configured</span>}
                                        </td>
                                        <td className="px-5 py-3">
                                            <div className="flex justify-end">
                                                <button
                                                    onClick={() => setEditing(config ?? { class_id: c.id })}
                                                    className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600"
                                                    aria-label="Configure"
                                                >
                                                    <Pencil size={16} />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                )}
            </div>

            {editing && (
                <ExamConfigForm
                    classId={editing.class_id}
                    className={options?.classes.find((c) => c.id === editing.class_id)?.name ?? ''}
                    row={'id' in editing ? editing : null}
                    exams={exams}
                    onClose={() => setEditing(null)}
                    onSaved={() => { invalidate(); setEditing(null); }}
                />
            )}
        </Card>
    );
}

function ExamConfigForm({
    classId, className, row, exams, onClose, onSaved,
}: {
    classId: number;
    className: string;
    row: ExamConfig | null;
    exams: { id: number; name: string }[];
    onClose: () => void;
    onSaved: () => void;
}) {
    const [meritBasis, setMeritBasis] = useState(row?.merit_basis ?? 'grade_point');
    const [sequential, setSequential] = useState(row?.merit_sequential ?? true);
    const [examIds, setExamIds] = useState<number[]>(row?.exam_ids ?? []);
    const [error, setError] = useState<string | null>(null);

    const toggleExam = (id: number) => setExamIds((ids) => (ids.includes(id) ? ids.filter((x) => x !== id) : [...ids, id]));

    const save = useMutation({
        mutationFn: () => saveExamConfig({ class_id: classId, merit_basis: meritBasis, merit_sequential: sequential, exam_ids: examIds }),
        onSuccess: onSaved,
        onError: (e) => setError(toApiError(e).message),
    });

    return (
        <Modal
            open onClose={onClose}
            title={`Exam config — ${className}`}
            footer={
                <>
                    <Button variant="outline" onClick={onClose} disabled={save.isPending}>Cancel</Button>
                    <Button onClick={() => { setError(null); save.mutate(); }} disabled={save.isPending || examIds.length === 0}>
                        {save.isPending ? <Loader2 size={16} className="animate-spin" /> : null}
                        Save
                    </Button>
                </>
            }
        >
            {error && <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
            <div className="space-y-4">
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Merit basis</label>
                    <select value={meritBasis} onChange={(e) => setMeritBasis(e.target.value as 'total_mark' | 'grade_point')}
                        className="w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
                        <option value="grade_point">Grade point (GPA)</option>
                        <option value="total_mark">Total mark</option>
                    </select>
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Ranking</label>
                    <select value={sequential ? '1' : '0'} onChange={(e) => setSequential(e.target.value === '1')}
                        className="w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
                        <option value="1">Sequential (distinct consecutive ranks)</option>
                        <option value="0">Standard (tied students share a rank)</option>
                    </select>
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Exams that count toward this class's result</label>
                    <div className="space-y-2 rounded-xl border border-border-strong p-3">
                        {exams.map((e) => (
                            <label key={e.id} className="flex cursor-pointer select-none items-center gap-2.5 text-[13.5px] text-fg">
                                <input type="checkbox" checked={examIds.includes(e.id)} onChange={() => toggleExam(e.id)}
                                    className="h-4 w-4 rounded border-border-strong text-brand-600 focus:ring-brand-500" />
                                {e.name}
                            </label>
                        ))}
                    </div>
                </div>
            </div>
        </Modal>
    );
}
