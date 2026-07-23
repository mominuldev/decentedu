import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Send, Loader2 } from 'lucide-react';
import { Card, Button } from '@/components/ui';
import { toApiError } from '@/lib/api';
import { cn } from '@/lib/cn';
import { classConfigOptions, listClassConfigs } from '@/features/academic/api';
import { listTemplates, listContacts, sendSms, type AudienceType } from './api';

const inputCls = 'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';

export function SendPanel() {
    const qc = useQueryClient();
    const { data: templates = [] } = useQuery({ queryKey: ['sms-templates'], queryFn: listTemplates });
    const { data: options } = useQuery({ queryKey: ['academic-class-config-options'], queryFn: classConfigOptions });
    const { data: classConfigs = [] } = useQuery({ queryKey: ['academic-class-configs'], queryFn: listClassConfigs });
    const { data: contacts = [] } = useQuery({ queryKey: ['contacts', ''], queryFn: () => listContacts() });

    const [audienceType, setAudienceType] = useState<AudienceType>('class');
    const [classId, setClassId] = useState<number | ''>('');
    const [classConfigId, setClassConfigId] = useState<number | ''>('');
    const [contactIds, setContactIds] = useState<number[]>([]);
    const [numbersText, setNumbersText] = useState('');
    const [templateId, setTemplateId] = useState<number | ''>('');
    const [message, setMessage] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState<string | null>(null);

    const send = useMutation({
        mutationFn: async () => {
            const numbers = numbersText.split(/[\n,]/).map((s) => s.trim()).filter(Boolean).map((phone) => ({ phone }));
            return sendSms({
                audience_type: audienceType,
                message,
                template_id: templateId || null,
                class_id: audienceType === 'class' ? Number(classId) : undefined,
                class_config_id: audienceType === 'section' ? Number(classConfigId) : undefined,
                contact_ids: audienceType === 'contact' ? contactIds : undefined,
                numbers: audienceType === 'custom_numbers' ? numbers : undefined,
            });
        },
        onSuccess: (batch) => {
            setSuccess(`Batch #${batch.id} queued for ${batch.total_recipients} recipient${batch.total_recipients === 1 ? '' : 's'}.`);
            setMessage('');
            qc.invalidateQueries({ queryKey: ['sms-batches'] });
            qc.invalidateQueries({ queryKey: ['sms-balance'] });
        },
        onError: (e) => setError(toApiError(e).message),
    });

    const onTemplateChange = (id: number | '') => {
        setTemplateId(id);
        const t = templates.find((t) => t.id === id);
        if (t) setMessage(t.message);
    };

    return (
        <Card className="max-w-2xl">
            <div className="px-5 py-4">
                <h3 className="text-[15px] font-semibold text-fg">Send SMS</h3>
                <p className="text-[12.5px] text-muted">Balance is debited when the batch is queued.</p>
            </div>
            <div className="space-y-4 border-t border-border px-5 py-5">
                {error && <div className="rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
                {success && <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-[13px] text-emerald-700 dark:border-emerald-500/25 dark:bg-emerald-500/10 dark:text-emerald-300">{success}</div>}

                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Audience</label>
                    <select value={audienceType} onChange={(e) => setAudienceType(e.target.value as AudienceType)} className={inputCls}>
                        <option value="class">Whole class (all sections)</option>
                        <option value="section">One section</option>
                        <option value="contact">Phone book contacts</option>
                        <option value="custom_numbers">Custom numbers</option>
                    </select>
                </div>

                {audienceType === 'class' && (
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">Class</label>
                        <select value={classId} onChange={(e) => setClassId(e.target.value ? Number(e.target.value) : '')} className={inputCls}>
                            <option value="">Select a class</option>
                            {options?.classes.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    </div>
                )}

                {audienceType === 'section' && (
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">Section</label>
                        <select value={classConfigId} onChange={(e) => setClassConfigId(e.target.value ? Number(e.target.value) : '')} className={inputCls}>
                            <option value="">Select a section</option>
                            {classConfigs.map((c) => <option key={c.id} value={c.id}>{c.label}</option>)}
                        </select>
                    </div>
                )}

                {audienceType === 'contact' && (
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">Contacts</label>
                        <select multiple value={contactIds.map(String)} onChange={(e) => setContactIds(Array.from(e.target.selectedOptions, (o) => Number(o.value)))}
                            className={cn(inputCls, 'h-32')}>
                            {contacts.map((c) => <option key={c.id} value={c.id}>{c.name} — {c.phone}</option>)}
                        </select>
                    </div>
                )}

                {audienceType === 'custom_numbers' && (
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">Numbers (one per line or comma-separated)</label>
                        <textarea rows={3} value={numbersText} onChange={(e) => setNumbersText(e.target.value)} className={inputCls} placeholder={'01712345678\n01812345678'} />
                    </div>
                )}

                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Template (optional)</label>
                    <select value={templateId} onChange={(e) => onTemplateChange(e.target.value ? Number(e.target.value) : '')} className={inputCls}>
                        <option value="">No template — write below</option>
                        {templates.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                    </select>
                </div>

                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Message <span className="text-rose-500">*</span></label>
                    <textarea rows={3} value={message} onChange={(e) => setMessage(e.target.value)} className={inputCls} />
                </div>

                <Button onClick={() => { setError(null); setSuccess(null); send.mutate(); }} disabled={send.isPending || !message.trim()}>
                    {send.isPending ? <Loader2 size={16} className="animate-spin" /> : <Send size={16} />} Send
                </Button>
            </div>
        </Card>
    );
}
