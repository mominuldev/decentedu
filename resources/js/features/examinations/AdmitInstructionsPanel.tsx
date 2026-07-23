import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2, Save } from 'lucide-react';
import { Card, Button } from '@/components/ui';
import { toApiError } from '@/lib/api';
import { getAdmitInstructions, saveAdmitInstructions, type AdmitInstructions } from './api';

export function AdmitInstructionsPanel() {
    const { data, isLoading } = useQuery({ queryKey: ['admit-instructions'], queryFn: getAdmitInstructions });
    const qc = useQueryClient();
    const [form, setForm] = useState<AdmitInstructions>({ instruction1: '', instruction2: '', instruction3: '', instruction4: '' });

    useEffect(() => { if (data) setForm(data); }, [data]);

    const save = useMutation({
        mutationFn: () => saveAdmitInstructions(form),
        onSuccess: (d) => { setForm(d); qc.invalidateQueries({ queryKey: ['admit-instructions'] }); },
    });

    const textCls = 'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';

    return (
        <Card>
            <div className="px-5 py-4">
                <h3 className="text-[15px] font-semibold text-fg">Admit card instructions</h3>
                <p className="text-[12.5px] text-muted">Free-text instructions printed on every admit card</p>
            </div>

            <div className="border-t border-border p-5">
                {isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-10 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : (
                    <div className="space-y-4">
                        {([1, 2, 3, 4] as const).map((n) => (
                            <div key={n}>
                                <label className="mb-1.5 block text-[13px] font-medium text-fg">Instruction {n}</label>
                                <textarea
                                    rows={2}
                                    value={form[`instruction${n}` as keyof AdmitInstructions] ?? ''}
                                    onChange={(e) => setForm((f) => ({ ...f, [`instruction${n}`]: e.target.value }))}
                                    className={textCls}
                                />
                            </div>
                        ))}

                        <div className="flex items-center justify-end gap-3">
                            {save.isError && <span className="text-[13px] text-rose-500">{toApiError(save.error).message}</span>}
                            {save.isSuccess && <span className="text-[13px] text-emerald-600">Saved.</span>}
                            <Button onClick={() => save.mutate()} disabled={save.isPending}>
                                {save.isPending ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />} Save
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </Card>
    );
}
