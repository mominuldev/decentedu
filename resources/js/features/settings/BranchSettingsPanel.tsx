import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Loader2, Save, Building2, Globe } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Toast, type ToastState } from '@/components/Toast';
import { cn } from '@/lib/cn';
import { toApiError } from '@/lib/api';
import { useAuth } from '@/features/auth/AuthProvider';
import { fetchBranchSettings, updateBranchSettings, type BranchSettingsPayload } from './api';

export function BranchSettingsPanel() {
    const qc = useQueryClient();
    const { refresh } = useAuth();
    const [toast, setToast] = useState<ToastState | null>(null);

    const { data: branch, isLoading, error: fetchErr } = useQuery({
        queryKey: ['settings', 'branch'],
        queryFn: fetchBranchSettings,
    });

    const [form, setForm] = useState<BranchSettingsPayload>({
        name: '',
        name_bn: '',
        code: '',
        address: '',
        phone: '',
        email: '',
        logo_path: '',
        settings: {
            timezone: 'Asia/Dhaka',
            currency_symbol: '৳',
            date_format: 'Y-m-d',
            sms_sender_id: 'DecentEdu',
            header_notice: '',
            auto_student_id: true,
        },
    });

    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [globalError, setGlobalError] = useState<string | null>(null);

    useEffect(() => {
        if (branch) {
            setForm({
                name: branch.name ?? '',
                name_bn: branch.name_bn ?? '',
                code: branch.code ?? '',
                address: branch.address ?? '',
                phone: branch.phone ?? '',
                email: branch.email ?? '',
                logo_path: branch.logo_path ?? '',
                settings: {
                    timezone: branch.settings?.timezone ?? 'Asia/Dhaka',
                    currency_symbol: branch.settings?.currency_symbol ?? '৳',
                    date_format: branch.settings?.date_format ?? 'Y-m-d',
                    sms_sender_id: branch.settings?.sms_sender_id ?? 'DecentEdu',
                    header_notice: branch.settings?.header_notice ?? '',
                    auto_student_id: branch.settings?.auto_student_id ?? true,
                },
            });
        }
    }, [branch]);

    const saveMutation = useMutation({
        mutationFn: () => updateBranchSettings(form),
        onSuccess: () => {
            setGlobalError(null);
            setErrors({});
            setToast({ tone: 'success', message: 'Branch settings updated successfully.' });
            qc.invalidateQueries({ queryKey: ['settings', 'branch'] });
            refresh();
        },
        onError: (e) => {
            const err = toApiError(e);
            setGlobalError(err.errors ? null : err.message);
            setErrors(err.errors ?? {});
            setToast({ tone: 'error', message: err.message || 'Failed to update branch settings.' });
        },
    });

    const inputCls = (hasError?: boolean) =>
        cn(
            'w-full rounded-xl border bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none placeholder:text-faint focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25',
            hasError ? 'border-rose-400' : 'border-border-strong',
        );

    const labelCls = 'mb-1.5 block text-[13px] font-medium text-fg';

    if (isLoading) {
        return (
            <Card className="flex items-center justify-center gap-2 py-20 text-muted">
                <Loader2 size={18} className="animate-spin" /> Loading branch settings…
            </Card>
        );
    }

    if (fetchErr) {
        return (
            <Card className="px-5 py-8 text-center text-rose-500">
                Failed to load branch settings. Please refresh the page.
            </Card>
        );
    }

    return (
        <div className="space-y-6">
            <Toast toast={toast} onClose={() => setToast(null)} />

            {globalError && (
                <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-[13.5px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">
                    {globalError}
                </div>
            )}

            {/* Institution Profile */}
            <Card>
                <div className="flex items-center gap-3 px-5 py-4 border-b border-border">
                    <div className="grid h-9 w-9 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                        <Building2 size={18} />
                    </div>
                    <div>
                        <h3 className="text-[15px] font-semibold text-fg">Institution Details</h3>
                        <p className="text-[12.5px] text-muted">Primary name, location, and communication details for this branch.</p>
                    </div>
                </div>

                <div className="grid gap-4 px-5 py-5 sm:grid-cols-2">
                    <div>
                        <label className={labelCls}>Branch Name *</label>
                        <input
                            type="text"
                            value={form.name}
                            onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                            className={inputCls(!!errors.name)}
                            placeholder="e.g. Main Campus"
                        />
                        {errors.name && <p className="mt-1 text-[12px] text-rose-500">{errors.name[0]}</p>}
                    </div>

                    <div>
                        <label className={labelCls}>Bengali Name (বাংলা নাম)</label>
                        <input
                            type="text"
                            value={form.name_bn ?? ''}
                            onChange={(e) => setForm((f) => ({ ...f, name_bn: e.target.value }))}
                            className={inputCls(!!errors.name_bn)}
                            placeholder="যেমন: প্রধান শাখা"
                        />
                    </div>

                    <div>
                        <label className={labelCls}>Branch Code</label>
                        <input
                            type="text"
                            value={form.code ?? ''}
                            onChange={(e) => setForm((f) => ({ ...f, code: e.target.value }))}
                            className={inputCls(!!errors.code)}
                            placeholder="e.g. MAIN"
                        />
                        {errors.code && <p className="mt-1 text-[12px] text-rose-500">{errors.code[0]}</p>}
                    </div>

                    <div>
                        <label className={labelCls}>Email Address</label>
                        <input
                            type="email"
                            value={form.email ?? ''}
                            onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
                            className={inputCls(!!errors.email)}
                            placeholder="info@school.edu"
                        />
                        {errors.email && <p className="mt-1 text-[12px] text-rose-500">{errors.email[0]}</p>}
                    </div>

                    <div>
                        <label className={labelCls}>Phone Number</label>
                        <input
                            type="text"
                            value={form.phone ?? ''}
                            onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))}
                            className={inputCls(!!errors.phone)}
                            placeholder="+880 1700-000000"
                        />
                    </div>

                    <div>
                        <label className={labelCls}>Logo Path / URL</label>
                        <input
                            type="text"
                            value={form.logo_path ?? ''}
                            onChange={(e) => setForm((f) => ({ ...f, logo_path: e.target.value }))}
                            className={inputCls(!!errors.logo_path)}
                            placeholder="/storage/logos/branch.png"
                        />
                    </div>

                    <div className="sm:col-span-2">
                        <label className={labelCls}>Physical Address</label>
                        <textarea
                            rows={2}
                            value={form.address ?? ''}
                            onChange={(e) => setForm((f) => ({ ...f, address: e.target.value }))}
                            className={inputCls(!!errors.address)}
                            placeholder="House #12, Road #4, Sector #7, Uttara, Dhaka"
                        />
                    </div>
                </div>
            </Card>

            {/* Regional & System Defaults */}
            <Card>
                <div className="flex items-center gap-3 px-5 py-4 border-b border-border">
                    <div className="grid h-9 w-9 place-items-center rounded-xl bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400">
                        <Globe size={18} />
                    </div>
                    <div>
                        <h3 className="text-[15px] font-semibold text-fg">Localization & Defaults</h3>
                        <p className="text-[12.5px] text-muted">Currency, timezone, SMS sender info, and automated rules.</p>
                    </div>
                </div>

                <div className="grid gap-4 px-5 py-5 sm:grid-cols-3">
                    <div>
                        <label className={labelCls}>Currency Symbol</label>
                        <input
                            type="text"
                            value={form.settings?.currency_symbol ?? '৳'}
                            onChange={(e) => setForm((f) => ({ ...f, settings: { ...f.settings, currency_symbol: e.target.value } }))}
                            className={inputCls()}
                            placeholder="৳"
                        />
                    </div>

                    <div>
                        <label className={labelCls}>Timezone</label>
                        <select
                            value={form.settings?.timezone ?? 'Asia/Dhaka'}
                            onChange={(e) => setForm((f) => ({ ...f, settings: { ...f.settings, timezone: e.target.value } }))}
                            className={inputCls()}
                        >
                            <option value="Asia/Dhaka">Asia/Dhaka (GMT+6)</option>
                            <option value="UTC">UTC (GMT+0)</option>
                            <option value="Asia/Kolkata">Asia/Kolkata (GMT+5:30)</option>
                        </select>
                    </div>

                    <div>
                        <label className={labelCls}>Date Format</label>
                        <select
                            value={form.settings?.date_format ?? 'Y-m-d'}
                            onChange={(e) => setForm((f) => ({ ...f, settings: { ...f.settings, date_format: e.target.value } }))}
                            className={inputCls()}
                        >
                            <option value="Y-m-d">YYYY-MM-DD (2026-07-28)</option>
                            <option value="d/m/Y">DD/MM/YYYY (28/07/2026)</option>
                            <option value="m/d/Y">MM/DD/YYYY (07/28/2026)</option>
                        </select>
                    </div>

                    <div className="sm:col-span-2">
                        <label className={labelCls}>SMS Sender ID</label>
                        <input
                            type="text"
                            value={form.settings?.sms_sender_id ?? 'DecentEdu'}
                            onChange={(e) => setForm((f) => ({ ...f, settings: { ...f.settings, sms_sender_id: e.target.value } }))}
                            className={inputCls()}
                            placeholder="DecentEdu"
                        />
                    </div>

                    <div className="flex items-center pt-6">
                        <label className="flex items-center gap-2 text-[13.5px] font-medium text-fg cursor-pointer select-none">
                            <input
                                type="checkbox"
                                checked={form.settings?.auto_student_id ?? true}
                                onChange={(e) => setForm((f) => ({ ...f, settings: { ...f.settings, auto_student_id: e.target.checked } }))}
                                className="h-4 w-4 rounded border-border-strong text-brand-600 focus:ring-brand-500"
                            />
                            Auto-generate Student UIDs
                        </label>
                    </div>

                    <div className="sm:col-span-3">
                        <label className={labelCls}>Header Announcement Notice</label>
                        <input
                            type="text"
                            value={form.settings?.header_notice ?? ''}
                            onChange={(e) => setForm((f) => ({ ...f, settings: { ...f.settings, header_notice: e.target.value } }))}
                            className={inputCls()}
                            placeholder="e.g. Admissions for 2026 are now open! Apply online today."
                        />
                    </div>
                </div>

                <div className="flex items-center justify-between border-t border-border px-5 py-3.5">
                    {saveMutation.isSuccess ? <Badge tone="success">Settings saved successfully</Badge> : <span />}
                    <Button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending}>
                        {saveMutation.isPending ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />}
                        Save Branch Settings
                    </Button>
                </div>
            </Card>
        </div>
    );
}
