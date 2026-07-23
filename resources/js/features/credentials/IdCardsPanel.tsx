import { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { Loader2, IdCard as IdCardIcon } from 'lucide-react';
import { Card, Button } from '@/components/ui';
import { toApiError } from '@/lib/api';
import { listClassConfigs } from '@/features/academic/api';
import { listEmployees } from '@/features/hr/api';
import { listIdCardTemplates, generateIdCards, type CardData, type IdCardTemplateRow } from './api';
import { PrintableDocument } from './PrintableDocument';

const inputCls = 'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';

export function IdCardsPanel() {
    const { data: templates = [] } = useQuery({ queryKey: ['id-card-templates'], queryFn: () => listIdCardTemplates() });
    const { data: classConfigs = [] } = useQuery({ queryKey: ['academic-class-configs'], queryFn: listClassConfigs });
    const { data: employeesResponse } = useQuery({ queryKey: ['employees-all'], queryFn: () => listEmployees({ per_page: 500 }) });
    const employees = employeesResponse?.data ?? [];

    const [templateId, setTemplateId] = useState<number | ''>('');
    const [classConfigId, setClassConfigId] = useState<number | ''>('');
    const [employeeIds, setEmployeeIds] = useState<number[]>([]);
    const [error, setError] = useState<string | null>(null);
    const [result, setResult] = useState<{ template: IdCardTemplateRow; cards: CardData[] } | null>(null);

    const template = templates.find((t) => t.id === templateId);

    const generate = useMutation({
        mutationFn: () => generateIdCards({
            template_id: Number(templateId),
            class_config_id: template?.holder_type === 'student' ? Number(classConfigId) : undefined,
            ids: template?.holder_type === 'employee' ? employeeIds : undefined,
        }),
        onSuccess: (data) => setResult(data),
        onError: (e) => setError(toApiError(e).message),
    });

    return (
        <Card className="max-w-2xl">
            <div className="px-5 py-4">
                <h3 className="text-[15px] font-semibold text-fg">Generate ID cards</h3>
                <p className="text-[12.5px] text-muted">Bulk-print a batch of student or employee ID cards.</p>
            </div>
            <div className="space-y-4 border-t border-border px-5 py-5">
                {error && <div className="rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}

                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Template</label>
                    <select value={templateId} onChange={(e) => setTemplateId(e.target.value ? Number(e.target.value) : '')} className={inputCls}>
                        <option value="">Select a template</option>
                        {templates.map((t) => <option key={t.id} value={t.id}>{t.name} ({t.holder_type})</option>)}
                    </select>
                </div>

                {template?.holder_type === 'student' && (
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">Section</label>
                        <select value={classConfigId} onChange={(e) => setClassConfigId(e.target.value ? Number(e.target.value) : '')} className={inputCls}>
                            <option value="">Select a section</option>
                            {classConfigs.map((c) => <option key={c.id} value={c.id}>{c.label}</option>)}
                        </select>
                    </div>
                )}

                {template?.holder_type === 'employee' && (
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">Employees</label>
                        <select multiple value={employeeIds.map(String)} onChange={(e) => setEmployeeIds(Array.from(e.target.selectedOptions, (o) => Number(o.value)))} className={`${inputCls} h-32`}>
                            {employees.map((e) => <option key={e.id} value={e.id}>{e.name}</option>)}
                        </select>
                    </div>
                )}

                <Button onClick={() => { setError(null); generate.mutate(); }} disabled={generate.isPending || !templateId}>
                    {generate.isPending ? <Loader2 size={16} className="animate-spin" /> : <IdCardIcon size={16} />} Generate
                </Button>
            </div>

            {result && <IdCardsPrintView template={result.template} cards={result.cards} onClose={() => setResult(null)} />}
        </Card>
    );
}

function IdCardsPrintView({ template, cards, onClose }: { template: IdCardTemplateRow; cards: CardData[]; onClose: () => void }) {
    return (
        <PrintableDocument title={`ID cards — ${template.name} (${cards.length})`} onClose={onClose}>
            <div className="grid grid-cols-2 gap-4">
                {cards.map((c, i) => (
                    <div key={i} className="rounded-xl border-2 p-4" style={{ borderColor: template.primary_color ?? '#5343e0' }}>
                        <div className="flex items-center gap-3">
                            <div className="grid h-16 w-16 shrink-0 place-items-center rounded-lg bg-slate-100 text-[10px] text-slate-400">
                                {c.photo ? <img src={c.photo} alt="" className="h-full w-full rounded-lg object-cover" /> : 'Photo'}
                            </div>
                            <div className="min-w-0">
                                {template.fields.includes('name') && <p className="truncate font-semibold">{c.name}</p>}
                                {template.fields.includes('roll') && c.roll != null && <p className="text-xs text-slate-600">Roll: {c.roll}</p>}
                                {template.fields.includes('class') && c.class && <p className="text-xs text-slate-600">{c.class}</p>}
                                {template.fields.includes('designation') && c.designation && <p className="text-xs text-slate-600">{c.designation}</p>}
                            </div>
                        </div>
                        <div className="mt-2 space-y-0.5 text-[11px] text-slate-600">
                            {template.fields.includes('blood_group') && c.blood_group && <p>Blood group: {c.blood_group}</p>}
                            {template.fields.includes('address') && c.address && <p className="truncate">{c.address}</p>}
                            {template.fields.includes('guardian') && c.guardian && <p>Guardian: {c.guardian}</p>}
                            {template.fields.includes('mobile') && c.mobile && <p>{c.mobile}</p>}
                        </div>
                    </div>
                ))}
            </div>
        </PrintableDocument>
    );
}
