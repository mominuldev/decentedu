import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2, Save } from 'lucide-react';
import { Card, Button } from '@/components/ui';
import { toApiError } from '@/lib/api';
import { getWebsiteSettings, updateWebsiteSettings, type WebsiteSettingsRow } from './api';

const inputCls = 'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';

export function SettingsPanel() {
    const qc = useQueryClient();
    const { data, isLoading } = useQuery({ queryKey: ['cms-website-settings'], queryFn: getWebsiteSettings });
    const [form, setForm] = useState<Partial<WebsiteSettingsRow>>({});
    const [error, setError] = useState<string | null>(null);
    const [saved, setSaved] = useState(false);

    useEffect(() => { if (data) setForm(data); }, [data]);

    const save = useMutation({
        mutationFn: () => updateWebsiteSettings(form),
        onSuccess: () => { qc.invalidateQueries({ queryKey: ['cms-website-settings'] }); setSaved(true); setTimeout(() => setSaved(false), 2000); },
        onError: (e) => setError(toApiError(e).message),
    });

    const set = (k: keyof WebsiteSettingsRow, v: string) => setForm((f) => ({ ...f, [k]: v }));

    if (isLoading) return <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>;

    return (
        <Card className="max-w-xl">
            <div className="px-5 py-4">
                <h3 className="text-[15px] font-semibold text-fg">Website settings</h3>
                <p className="text-[12.5px] text-muted">Basic public-site info shown in the header/footer.</p>
            </div>
            <div className="space-y-4 border-t border-border px-5 py-5">
                {error && <div className="rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
                {saved && <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-[13px] text-emerald-700 dark:border-emerald-500/25 dark:bg-emerald-500/10 dark:text-emerald-300">Saved.</div>}

                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Site title</label>
                    <input value={form.site_title ?? ''} onChange={(e) => set('site_title', e.target.value)} className={inputCls} />
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Tagline</label>
                    <input value={form.tagline ?? ''} onChange={(e) => set('tagline', e.target.value)} className={inputCls} />
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Address</label>
                    <textarea rows={2} value={form.address ?? ''} onChange={(e) => set('address', e.target.value)} className={inputCls} />
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">Phone</label>
                        <input value={form.phone ?? ''} onChange={(e) => set('phone', e.target.value)} className={inputCls} />
                    </div>
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">Email</label>
                        <input value={form.email ?? ''} onChange={(e) => set('email', e.target.value)} className={inputCls} />
                    </div>
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Meta description</label>
                    <textarea rows={2} value={form.meta_description ?? ''} onChange={(e) => set('meta_description', e.target.value)} className={inputCls} />
                </div>

                <Button onClick={() => { setError(null); save.mutate(); }} disabled={save.isPending}>
                    {save.isPending ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />} Save settings
                </Button>
            </div>
        </Card>
    );
}
