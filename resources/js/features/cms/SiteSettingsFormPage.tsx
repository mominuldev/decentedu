import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Loader2, Globe, Mail, Phone, MapPin, Download, Upload } from 'lucide-react';
import { Card, Button } from '@/components/ui';
import { toApiError } from '@/lib/api';
import {
    getSiteSettings, updateSiteSettings, resetSiteSettings, exportSiteSettings, importSiteSettings,
    inputCls, labelCls, type SiteSettingDetail, type SiteSettingPayload, type AssetPayload,
} from './api';
import { MediaPicker } from './MediaPicker';
import { Field, Loading } from './PagesPanel';
import { useToast } from '@/components/Toast';

const EMPTY: SiteSettingPayload = {
    site_title: '',
    site_tagline: '',
    site_description: '',
    header_logo_asset_id: null,
    footer_logo_asset_id: null,
    favicon_asset_id: null,
    contact_email: '',
    contact_phone: '',
    contact_address: '',
    facebook_url: '',
    twitter_url: '',
    linkedin_url: '',
    youtube_url: '',
    instagram_url: '',
    google_analytics_code: '',
    google_tag_manager_code: '',
    meta_keywords: '',
    additional_settings: null,
};

export default function SiteSettingsFormPage() {
    const navigate = useNavigate();
    const qc = useQueryClient();
    const toast = useToast();

    const [form, setForm] = useState<SiteSettingPayload>(EMPTY);
    const [headerLogo, setHeaderLogo] = useState<AssetPayload | null>(null);
    const [footerLogo, setFooterLogo] = useState<AssetPayload | null>(null);
    const [favicon, setFavicon] = useState<AssetPayload | null>(null);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [error, setError] = useState<string | null>(null);
    const [headerPickerOpen, setHeaderPickerOpen] = useState(false);
    const [footerPickerOpen, setFooterPickerOpen] = useState(false);
    const [faviconPickerOpen, setFaviconPickerOpen] = useState(false);
    const [showResetConfirm, setShowResetConfirm] = useState(false);
    const [importFile, setImportFile] = useState<File | null>(null);
    const [showImportConfirm, setShowImportConfirm] = useState(false);

    const back = () => navigate('/cms?tab=settings');

    const { isLoading } = useQuery({
        queryKey: ['cms-site-settings'],
        queryFn: async () => {
            const settings = await getSiteSettings();
            hydrate(settings);
            return settings;
        },
    });

    const hydrate = (s: SiteSettingDetail) => {
        setForm({
            site_title: s.site_title,
            site_tagline: s.site_tagline || '',
            site_description: s.site_description || '',
            header_logo_asset_id: s.header_logo_asset_id || undefined,
            footer_logo_asset_id: s.footer_logo_asset_id || undefined,
            favicon_asset_id: s.favicon_asset_id || undefined,
            contact_email: s.contact_email || '',
            contact_phone: s.contact_phone || '',
            contact_address: s.contact_address || '',
            facebook_url: s.facebook_url || '',
            twitter_url: s.twitter_url || '',
            linkedin_url: s.linkedin_url || '',
            youtube_url: s.youtube_url || '',
            instagram_url: s.instagram_url || '',
            google_analytics_code: s.google_analytics_code || '',
            google_tag_manager_code: s.google_tag_manager_code || '',
            meta_keywords: s.meta_keywords || '',
            additional_settings: s.additional_settings || undefined,
        });
        setHeaderLogo(s.header_logo);
        setFooterLogo(s.footer_logo);
        setFavicon(s.favicon);
    };

    const save = useMutation({
        mutationFn: () => {
            const payload: SiteSettingPayload = {
                ...form,
                header_logo_asset_id: headerLogo?.id ?? null,
                footer_logo_asset_id: footerLogo?.id ?? null,
                favicon_asset_id: favicon?.id ?? null,
            };
            return updateSiteSettings(payload);
        },
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['cms-site-settings'] });
            toast.success('Site settings saved successfully');
            back();
        },
        onError: (e) => {
            const err = toApiError(e);
            setError(err.message);
            setErrors(err.errors ?? {});
            toast.error(err.message || 'Could not save site settings');
        },
    });

    const reset = useMutation({
        mutationFn: () => resetSiteSettings(),
        onSuccess: (data) => {
            hydrate(data);
            setShowResetConfirm(false);
            toast.success('Site settings reset to defaults');
        },
        onError: (e) => {
            const err = toApiError(e);
            toast.error(err.message || 'Could not reset site settings');
        },
    });

    const exportSettings = useMutation({
        mutationFn: () => exportSiteSettings(),
        onSuccess: () => {
            toast.success('Site settings exported successfully');
        },
        onError: (e) => {
            const err = toApiError(e);
            toast.error(err.message || 'Could not export site settings');
        },
    });

    const importSettings = useMutation({
        mutationFn: (file: File) => importSiteSettings(file),
        onSuccess: (data) => {
            hydrate(data);
            setShowImportConfirm(false);
            setImportFile(null);
            qc.invalidateQueries({ queryKey: ['cms-site-settings'] });
            toast.success('Site settings imported successfully');
        },
        onError: (e) => {
            const err = toApiError(e);
            setError(err.message);
            setErrors(err.errors ?? {});
            toast.error(err.message || 'Could not import site settings');
        },
    });

    const set = (patch: Partial<SiteSettingPayload>) => setForm((f) => ({ ...f, ...patch }));

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <Button variant="outline" onClick={back} disabled={save.isPending}>
                        <ArrowLeft size={16} /> Back
                    </Button>
                    <div>
                        <h1 className="text-[22px] font-bold tracking-tight text-fg">Site Settings</h1>
                        <p className="mt-0.5 text-[13.5px] text-muted">Manage your site's global settings and branding</p>
                    </div>
                </div>
                <div className="flex gap-2">
                    <Button
                        variant="outline"
                        onClick={() => exportSettings.mutate()}
                        disabled={exportSettings.isPending || save.isPending}
                    >
                        <Download size={16} className="mr-2" />
                        {exportSettings.isPending ? 'Exporting…' : 'Export CSV'}
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() => document.getElementById('import-file-input')?.click()}
                        disabled={importSettings.isPending || save.isPending}
                    >
                        <Upload size={16} className="mr-2" />
                        {importSettings.isPending ? 'Importing…' : 'Import CSV'}
                    </Button>
                    <input
                        id="import-file-input"
                        type="file"
                        accept=".csv"
                        className="hidden"
                        onChange={(e) => {
                            const file = e.target.files?.[0];
                            if (file) {
                                setImportFile(file);
                                setShowImportConfirm(true);
                            }
                        }}
                    />
                    <Button
                        variant="outline"
                        onClick={() => setShowResetConfirm(true)}
                        disabled={save.isPending || reset.isPending}
                        className="text-rose-600 hover:text-rose-700 hover:bg-rose-50"
                    >
                        Reset to Defaults
                    </Button>
                    <Button variant="outline" onClick={back} disabled={save.isPending}>
                        Cancel
                    </Button>
                    <Button onClick={() => save.mutate()} disabled={save.isPending}>
                        {save.isPending && <Loader2 size={16} className="animate-spin" />}
                        {save.isPending ? 'Saving…' : 'Save Settings'}
                    </Button>
                </div>
            </div>

            {isLoading ? <Loading /> : (
                <div className="space-y-6">
                    {error && (
                        <p className="rounded-lg bg-rose-50 px-3 py-2 text-[13px] text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
                            {error}
                        </p>
                    )}

                    {/* Basic Site Information */}
                    <Card className="p-5">
                        <h3 className="text-[15px] font-semibold text-fg mb-4 flex items-center gap-2">
                            <Globe size={16} /> Basic Information
                        </h3>
                        <div className="space-y-4">
                            <Field label="Site Title" error={errors.site_title}>
                                <input className={inputCls} value={form.site_title} onChange={(e) => set({ site_title: e.target.value })} />
                            </Field>
                            <Field label="Site Tagline" error={errors.site_tagline}>
                                <input className={inputCls} value={form.site_tagline} onChange={(e) => set({ site_tagline: e.target.value })} placeholder="A short tagline for your site" />
                            </Field>
                            <Field label="Site Description" error={errors.site_description}>
                                <textarea className={`${inputCls} min-h-[100px] resize-y`} value={form.site_description} onChange={(e) => set({ site_description: e.target.value })} placeholder="Brief description of your site" />
                            </Field>
                        </div>
                    </Card>

                    {/* Logo and Branding */}
                    <Card className="p-5">
                        <h3 className="text-[15px] font-semibold text-fg mb-4">Logo and Branding</h3>
                        <div className="space-y-4">
                            <div>
                                <label className={labelCls}>Header Logo</label>
                                <div className="flex items-center gap-3">
                                    {headerLogo && (
                                        <div className="relative h-12 w-32 overflow-hidden rounded border border-border">
                                            <img src={headerLogo.url || undefined} alt="" className="h-full w-full object-contain" />
                                        </div>
                                    )}
                                    <Button type="button" variant="outline" onClick={() => setHeaderPickerOpen(true)}>
                                        {headerLogo ? 'Change Header Logo' : 'Select Header Logo'}
                                    </Button>
                                    {headerLogo && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setHeaderLogo(null)}
                                            className="text-rose-600 hover:bg-rose-50"
                                        >
                                            Remove
                                        </Button>
                                    )}
                                </div>
                            </div>

                            <div>
                                <label className={labelCls}>Footer Logo</label>
                                <div className="flex items-center gap-3">
                                    {footerLogo && (
                                        <div className="relative h-12 w-32 overflow-hidden rounded border border-border">
                                            <img src={footerLogo.url || undefined} alt="" className="h-full w-full object-contain" />
                                        </div>
                                    )}
                                    <Button type="button" variant="outline" onClick={() => setFooterPickerOpen(true)}>
                                        {footerLogo ? 'Change Footer Logo' : 'Select Footer Logo'}
                                    </Button>
                                    {footerLogo && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setFooterLogo(null)}
                                            className="text-rose-600 hover:bg-rose-50"
                                        >
                                            Remove
                                        </Button>
                                    )}
                                </div>
                            </div>

                            <div>
                                <label className={labelCls}>Favicon</label>
                                <div className="flex items-center gap-3">
                                    {favicon && (
                                        <div className="relative h-8 w-8 overflow-hidden rounded border border-border">
                                            <img src={favicon.url || undefined} alt="" className="h-full w-full object-contain" />
                                        </div>
                                    )}
                                    <Button type="button" variant="outline" onClick={() => setFaviconPickerOpen(true)}>
                                        {favicon ? 'Change Favicon' : 'Select Favicon'}
                                    </Button>
                                    {favicon && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setFavicon(null)}
                                            className="text-rose-600 hover:bg-rose-50"
                                        >
                                            Remove
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </div>
                    </Card>

                    {/* Contact Information */}
                    <Card className="p-5">
                        <h3 className="text-[15px] font-semibold text-fg mb-4 flex items-center gap-2">
                            <Mail size={16} /> Contact Information
                        </h3>
                        <div className="space-y-4">
                            <Field label="Contact Email" error={errors.contact_email}>
                                <input type="email" className={inputCls} value={form.contact_email} onChange={(e) => set({ contact_email: e.target.value })} />
                            </Field>
                            <Field label="Contact Phone" error={errors.contact_phone}>
                                <input type="tel" className={inputCls} value={form.contact_phone} onChange={(e) => set({ contact_phone: e.target.value })} />
                            </Field>
                            <Field label="Contact Address" error={errors.contact_address}>
                                <div className="relative">
                                    <MapPin size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-faint" />
                                    <textarea className={`${inputCls} pl-9 min-h-[80px] resize-y`} value={form.contact_address} onChange={(e) => set({ contact_address: e.target.value })} />
                                </div>
                            </Field>
                        </div>
                    </Card>

                    {/* Social Media */}
                    <Card className="p-5">
                        <h3 className="text-[15px] font-semibold text-fg mb-4">Social Media</h3>
                        <div className="space-y-4">
                            <Field label="Facebook URL" error={errors.facebook_url}>
                                <input type="url" className={inputCls} value={form.facebook_url} onChange={(e) => set({ facebook_url: e.target.value })} placeholder="https://facebook.com/your-page" />
                            </Field>
                            <Field label="Twitter URL" error={errors.twitter_url}>
                                <input type="url" className={inputCls} value={form.twitter_url} onChange={(e) => set({ twitter_url: e.target.value })} placeholder="https://twitter.com/your-account" />
                            </Field>
                            <Field label="LinkedIn URL" error={errors.linkedin_url}>
                                <input type="url" className={inputCls} value={form.linkedin_url} onChange={(e) => set({ linkedin_url: e.target.value })} placeholder="https://linkedin.com/company/your-company" />
                            </Field>
                            <Field label="YouTube URL" error={errors.youtube_url}>
                                <input type="url" className={inputCls} value={form.youtube_url} onChange={(e) => set({ youtube_url: e.target.value })} placeholder="https://youtube.com/@your-channel" />
                            </Field>
                            <Field label="Instagram URL" error={errors.instagram_url}>
                                <input type="url" className={inputCls} value={form.instagram_url} onChange={(e) => set({ instagram_url: e.target.value })} placeholder="https://instagram.com/your-account" />
                            </Field>
                        </div>
                    </Card>

                    {/* SEO and Analytics */}
                    <Card className="p-5">
                        <h3 className="text-[15px] font-semibold text-fg mb-4">SEO and Analytics</h3>
                        <div className="space-y-4">
                            <Field label="Meta Keywords" error={errors.meta_keywords}>
                                <input className={inputCls} value={form.meta_keywords} onChange={(e) => set({ meta_keywords: e.target.value })} placeholder="keyword1, keyword2, keyword3" />
                            </Field>
                            <Field label="Google Analytics Code" error={errors.google_analytics_code}>
                                <textarea className={`${inputCls} min-h-[80px] resize-y font-mono text-[12px]`} value={form.google_analytics_code} onChange={(e) => set({ google_analytics_code: e.target.value })} placeholder="Paste your Google Analytics tracking code here" />
                            </Field>
                            <Field label="Google Tag Manager Code" error={errors.google_tag_manager_code}>
                                <textarea className={`${inputCls} min-h-[80px] resize-y font-mono text-[12px]`} value={form.google_tag_manager_code} onChange={(e) => set({ google_tag_manager_code: e.target.value })} placeholder="Paste your Google Tag Manager code here" />
                            </Field>
                        </div>
                    </Card>
                </div>
            )}

            {/* Media Pickers */}
            {headerPickerOpen && (
                <MediaPicker
                    onClose={() => setHeaderPickerOpen(false)}
                    onSelect={(asset) => {
                        setHeaderLogo(asset);
                        setHeaderPickerOpen(false);
                    }}
                />
            )}
            {footerPickerOpen && (
                <MediaPicker
                    onClose={() => setFooterPickerOpen(false)}
                    onSelect={(asset) => {
                        setFooterLogo(asset);
                        setFooterPickerOpen(false);
                    }}
                />
            )}
            {faviconPickerOpen && (
                <MediaPicker
                    onClose={() => setFaviconPickerOpen(false)}
                    onSelect={(asset) => {
                        setFavicon(asset);
                        setFaviconPickerOpen(false);
                    }}
                />
            )}

            {/* Reset Confirmation Modal */}
            {showResetConfirm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <Card className="w-full max-w-md p-6">
                        <h3 className="text-[17px] font-semibold text-fg mb-2">Reset to Defaults?</h3>
                        <p className="text-[13px] text-muted mb-6">
                            This will reset all site settings to their default values. This action cannot be undone.
                        </p>
                        <div className="flex gap-3 justify-end">
                            <Button variant="outline" onClick={() => setShowResetConfirm(false)} disabled={reset.isPending}>
                                Cancel
                            </Button>
                            <Button
                                onClick={() => reset.mutate()}
                                disabled={reset.isPending}
                                className="bg-rose-600 text-white hover:bg-rose-700"
                            >
                                {reset.isPending ? 'Resetting…' : 'Reset Settings'}
                            </Button>
                        </div>
                    </Card>
                </div>
            )}

            {/* Import Confirmation Modal */}
            {showImportConfirm && importFile && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <Card className="w-full max-w-md p-6">
                        <h3 className="text-[17px] font-semibold text-fg mb-2">Import Settings from CSV?</h3>
                        <p className="text-[13px] text-muted mb-2">
                            You are about to import settings from:
                        </p>
                        <p className="text-[12px] font-mono bg-surface-2 p-2 rounded mb-4">
                            {importFile.name}
                        </p>
                        <p className="text-[13px] text-rose-600 dark:text-rose-400 mb-6">
                            This will overwrite your current site settings. This action cannot be undone.
                        </p>
                        <div className="flex gap-3 justify-end">
                            <Button variant="outline" onClick={() => {
                                setShowImportConfirm(false);
                                setImportFile(null);
                            }} disabled={importSettings.isPending}>
                                Cancel
                            </Button>
                            <Button
                                onClick={() => importSettings.mutate(importFile)}
                                disabled={importSettings.isPending}
                                className="bg-brand-600 text-white hover:bg-brand-700"
                            >
                                {importSettings.isPending ? 'Importing…' : 'Import Settings'}
                            </Button>
                        </div>
                    </Card>
                </div>
            )}
        </div>
    );
}