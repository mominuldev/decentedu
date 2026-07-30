import { useMemo, useState, type ComponentType } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    ArrowLeft, Loader2, Globe, Mail, MapPin, Phone, Download, Upload, Plus, Trash2,
    ChevronUp, ChevronDown, PanelBottom, PanelTop, Image as ImageIcon, Share2, LineChart,
    RotateCcw, Settings2, Link2, AlertCircle,
} from 'lucide-react';
import { Card, Button } from '@/components/ui';
import { Modal } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import {
    getSiteSettings, updateSiteSettings, resetSiteSettings, exportSiteSettings, importSiteSettings,
    listMenus, inputCls, labelCls,
    type SiteSettingDetail, type SiteSettingPayload, type AssetPayload, type FooterMenuColumn,
} from './api';
import { FileUpload } from '@/components/FileUpload';
import { RichTextEditor } from './RichTextEditor';
import { Loading } from './PagesPanel';
import { useToast } from '@/components/Toast';

const EMPTY: SiteSettingPayload = {
    site_title: '',
    site_tagline: '',
    site_description: '',
    header_logo_asset_id: null,
    footer_logo_asset_id: null,
    favicon_asset_id: null,
    eiin: '',
    header_topbar_cta_label: '',
    header_topbar_cta_url: '',
    header_cta_label: '',
    header_cta_url: '',
    contact_email: '',
    contact_phone: '',
    contact_address: '',
    footer_description: '',
    footer_menus: [],
    footer_copyright: '',
    footer_bottom_menu_id: null,
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

/* ------------------------------------------------------------- brand marks */

/**
 * lucide dropped brand icons in v1, so the social rows carry their own marks.
 * Paths are the Simple Icons glyphs (CC0), drawn on the same 24-unit grid as lucide
 * and taking the same `size`/`className` props so they drop into `IconInput` unchanged.
 */
type IconProps = { size?: number | string; className?: string };

function brandIcon(path: string) {
    return function BrandIcon({ size = 24, className }: IconProps) {
        return (
            <svg viewBox="0 0 24 24" width={size} height={size} fill="currentColor" aria-hidden className={className}>
                <path d={path} />
            </svg>
        );
    };
}

const FacebookIcon = brandIcon('M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036 26.805 26.805 0 0 0-.733-.009c-.707 0-1.259.096-1.675.309a1.686 1.686 0 0 0-.679.622c-.258.42-.374.995-.374 1.752v1.297h3.919l-.386 2.103-.287 1.564h-3.246v8.245C19.396 23.238 24 18.179 24 12.044c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.628 3.874 10.35 9.101 11.647Z');
const TwitterIcon = brandIcon('M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932ZM17.61 20.644h2.039L6.486 3.24H4.298Z');
const LinkedinIcon = brandIcon('M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286ZM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065Zm1.782 13.019H3.555V9h3.564v11.452ZM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0Z');
const YoutubeIcon = brandIcon('M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814ZM9.545 15.568V8.432L15.818 12l-6.273 3.568Z');
const InstagramIcon = brandIcon('M12 0C8.74 0 8.333.015 7.053.072 5.775.132 4.905.333 4.14.63c-.789.306-1.459.717-2.126 1.384S.935 3.35.63 4.14C.333 4.905.131 5.775.072 7.053.012 8.333 0 8.74 0 12s.015 3.667.072 4.947c.06 1.277.261 2.148.558 2.913.306.788.717 1.459 1.384 2.126.667.666 1.336 1.079 2.126 1.384.766.296 1.636.499 2.913.558C8.333 23.988 8.74 24 12 24s3.667-.015 4.947-.072c1.277-.06 2.148-.262 2.913-.558.788-.306 1.459-.718 2.126-1.384.666-.667 1.079-1.335 1.384-2.126.296-.765.499-1.636.558-2.913.06-1.28.072-1.687.072-4.947s-.015-3.667-.072-4.947c-.06-1.277-.262-2.149-.558-2.913-.306-.789-.718-1.459-1.384-2.126C21.319 1.347 20.651.935 19.86.63c-.765-.297-1.636-.499-2.913-.558C15.667.012 15.26 0 12 0Zm0 2.16c3.203 0 3.585.016 4.85.071 1.17.055 1.805.249 2.227.415.562.217.96.477 1.382.9.423.423.682.819.9 1.381.163.422.36 1.057.413 2.227.057 1.266.07 1.646.07 4.85s-.015 3.585-.074 4.85c-.061 1.17-.256 1.805-.421 2.227-.224.562-.479.96-.902 1.382-.419.423-.824.682-1.38.9-.42.163-1.065.36-2.235.413-1.274.057-1.649.07-4.859.07-3.211 0-3.586-.015-4.859-.074-1.171-.061-1.816-.256-2.236-.421-.569-.224-.96-.479-1.379-.902-.421-.419-.69-.824-.9-1.38-.165-.42-.359-1.065-.42-2.235-.045-1.26-.061-1.649-.061-4.844 0-3.196.016-3.586.061-4.861.061-1.17.255-1.814.42-2.234.21-.57.479-.96.9-1.381.419-.421.81-.69 1.379-.9.42-.166 1.051-.361 2.221-.421 1.275-.045 1.65-.06 4.859-.06Zm0 3.678a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324ZM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm7.846-10.405a1.441 1.441 0 0 1-2.88 0 1.44 1.44 0 0 1 2.88 0Z');

/* ---------------------------------------------------------------- sections */

type SectionKey = 'general' | 'branding' | 'header' | 'contact' | 'footer' | 'social' | 'seo';

/**
 * One entry per pane in the left-hand nav. `fields` is the list of payload keys the pane
 * owns, which is what lets the nav badge a section with its validation-error count and lets
 * a failed save jump straight to the pane holding the first problem.
 */
const SECTIONS: {
    key: SectionKey;
    label: string;
    icon: ComponentType<IconProps>;
    title: string;
    desc: string;
    fields: string[];
}[] = [
    {
        key: 'general', label: 'General', icon: Globe,
        title: 'General information',
        desc: 'The name and description used across the public site, browser tabs and search results.',
        fields: ['site_title', 'site_tagline', 'site_description', 'eiin'],
    },
    {
        key: 'branding', label: 'Branding', icon: ImageIcon,
        title: 'Logos and favicon',
        desc: 'Images shown in the site header, footer and browser tab.',
        fields: ['header_logo_asset_id', 'footer_logo_asset_id', 'favicon_asset_id'],
    },
    {
        key: 'header', label: 'Header', icon: PanelTop,
        title: 'Header buttons',
        desc: 'Call-to-action buttons in the top bar and main navigation.',
        fields: ['header_topbar_cta_label', 'header_topbar_cta_url', 'header_cta_label', 'header_cta_url'],
    },
    {
        key: 'contact', label: 'Contact', icon: Mail,
        title: 'Contact details',
        desc: 'Shown on the contact page, in the footer and in structured data.',
        fields: ['contact_email', 'contact_phone', 'contact_address'],
    },
    {
        key: 'footer', label: 'Footer', icon: PanelBottom,
        title: 'Footer',
        desc: 'The footer text, link columns and the bottom copyright bar.',
        fields: ['footer_description', 'footer_menus', 'footer_copyright', 'footer_bottom_menu_id'],
    },
    {
        key: 'social', label: 'Social links', icon: Share2,
        title: 'Social links',
        desc: 'Profile links shown as icons in the header and footer. Leave a field empty to hide its icon.',
        fields: ['facebook_url', 'twitter_url', 'linkedin_url', 'youtube_url', 'instagram_url'],
    },
    {
        key: 'seo', label: 'SEO & Analytics', icon: LineChart,
        title: 'SEO and analytics',
        desc: 'Search metadata and third-party tracking snippets.',
        fields: ['meta_keywords', 'google_analytics_code', 'google_tag_manager_code'],
    },
];

const isSectionKey = (v: string | null): v is SectionKey => SECTIONS.some((s) => s.key === v);

/* ---------------------------------------------------------------- local ui */

/** Field wrapper with a required marker, and hint text that an error replaces. */
function Field({ label, error, hint, required, children }: {
    label: string;
    error?: string[];
    hint?: React.ReactNode;
    required?: boolean;
    children: React.ReactNode;
}) {
    return (
        <div>
            <label className={labelCls}>
                {label}
                {required && <span className="ml-0.5 text-rose-500" title="Required">*</span>}
            </label>
            {children}
            {error?.[0]
                ? <p className="mt-1 text-[12px] text-rose-600">{error[0]}</p>
                : hint ? <p className="mt-1 text-[12px] text-muted">{hint}</p> : null}
        </div>
    );
}

/** Grouped sub-panel inside a pane, for fields that only make sense together. */
function Group({ title, hint, children }: { title: string; hint?: string; children: React.ReactNode }) {
    return (
        <div className="rounded-xl border border-border bg-surface-2/50 p-4">
            <p className="text-[13px] font-semibold text-fg">{title}</p>
            {hint && <p className="mt-0.5 mb-3 text-[12px] text-muted">{hint}</p>}
            <div className={hint ? '' : 'mt-3'}>{children}</div>
        </div>
    );
}

/** URL input with a leading platform icon, so the five social rows are scannable. */
function IconInput({ icon: Icon, ...rest }: {
    icon: ComponentType<IconProps>;
} & React.InputHTMLAttributes<HTMLInputElement>) {
    return (
        <div className="relative">
            <Icon size={16} className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-faint" />
            <input {...rest} className={`${inputCls} pl-10`} />
        </div>
    );
}

function MenuItem({ icon: Icon, onClick, disabled, danger, children }: {
    icon: ComponentType<IconProps>;
    onClick: () => void;
    disabled?: boolean;
    danger?: boolean;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            className={`flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-3 py-2 text-left text-[13px] font-medium disabled:cursor-not-allowed disabled:opacity-50 ${
                danger ? 'text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10' : 'text-fg hover:bg-surface-2'
            }`}
        >
            <Icon size={15} className="shrink-0" />
            {children}
        </button>
    );
}

/* ---------------------------------------------------------------- the page */

export default function SiteSettingsFormPage() {
    const navigate = useNavigate();
    const qc = useQueryClient();
    const toast = useToast();
    const [params, setParams] = useSearchParams();

    // Active pane lives in the URL so a reload — or a link shared with a colleague — reopens
    // the same section instead of dropping back to General.
    const sectionParam = params.get('section');
    const section: SectionKey = isSectionKey(sectionParam) ? sectionParam : 'general';
    const goTo = (key: SectionKey) => {
        const next = new URLSearchParams(params);
        next.set('section', key);
        setParams(next, { replace: true });
    };

    const [form, setForm] = useState<SiteSettingPayload>(EMPTY);
    const [headerLogo, setHeaderLogo] = useState<AssetPayload | null>(null);
    const [footerLogo, setFooterLogo] = useState<AssetPayload | null>(null);
    const [favicon, setFavicon] = useState<AssetPayload | null>(null);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [error, setError] = useState<string | null>(null);
    const [pristine, setPristine] = useState<string | null>(null);
    const [menuOpen, setMenuOpen] = useState(false);
    const [showResetConfirm, setShowResetConfirm] = useState(false);
    const [importFile, setImportFile] = useState<File | null>(null);
    const [showImportConfirm, setShowImportConfirm] = useState(false);
    const [showLeaveConfirm, setShowLeaveConfirm] = useState(false);

    const leave = () => navigate('/cms?tab=settings');
    /** Cancel/Back only warns when there is something to lose. */
    const back = () => (dirty ? setShowLeaveConfirm(true) : leave());

    const { isLoading, isError, error: loadError, refetch } = useQuery({
        queryKey: ['cms-site-settings'],
        queryFn: async () => {
            const settings = await getSiteSettings();
            hydrate(settings);
            return settings;
        },
    });

    // Same key MenusPanel uses, so the picker reuses an already-warm list.
    const { data: menus = [] } = useQuery({ queryKey: ['cms-menus'], queryFn: listMenus });

    /** Serialized form state, used to tell "no changes yet" from "unsaved changes". */
    const fingerprint = (
        f: SiteSettingPayload,
        assets: (AssetPayload | null)[],
    ) => JSON.stringify([f, assets.map((a) => a?.id ?? null)]);

    const hydrate = (s: SiteSettingDetail) => {
        const next: SiteSettingPayload = {
            site_title: s.site_title,
            site_tagline: s.site_tagline || '',
            site_description: s.site_description || '',
            header_logo_asset_id: s.header_logo_asset_id || undefined,
            footer_logo_asset_id: s.footer_logo_asset_id || undefined,
            favicon_asset_id: s.favicon_asset_id || undefined,
            eiin: s.eiin || '',
            header_topbar_cta_label: s.header_topbar_cta_label || '',
            header_topbar_cta_url: s.header_topbar_cta_url || '',
            header_cta_label: s.header_cta_label || '',
            header_cta_url: s.header_cta_url || '',
            contact_email: s.contact_email || '',
            contact_phone: s.contact_phone || '',
            contact_address: s.contact_address || '',
            footer_description: s.footer_description || '',
            footer_menus: s.footer_menus ?? [],
            footer_copyright: s.footer_copyright || '',
            footer_bottom_menu_id: s.footer_bottom_menu_id,
            facebook_url: s.facebook_url || '',
            twitter_url: s.twitter_url || '',
            linkedin_url: s.linkedin_url || '',
            youtube_url: s.youtube_url || '',
            instagram_url: s.instagram_url || '',
            google_analytics_code: s.google_analytics_code || '',
            google_tag_manager_code: s.google_tag_manager_code || '',
            meta_keywords: s.meta_keywords || '',
            additional_settings: s.additional_settings || undefined,
        };
        setForm(next);
        setHeaderLogo(s.header_logo);
        setFooterLogo(s.footer_logo);
        setFavicon(s.favicon);
        setPristine(fingerprint(next, [s.header_logo, s.footer_logo, s.favicon]));
    };

    const dirty = pristine !== null && fingerprint(form, [headerLogo, footerLogo, favicon]) !== pristine;

    /** Validation errors grouped by the pane that owns them, keyed on the field root. */
    const errorCounts = useMemo(() => {
        const counts = {} as Record<SectionKey, number>;
        for (const key of Object.keys(errors)) {
            const root = key.split('.')[0];
            const owner = SECTIONS.find((s) => s.fields.includes(root));
            if (owner) counts[owner.key] = (counts[owner.key] ?? 0) + 1;
        }
        return counts;
    }, [errors]);

    /** After a failed save, surface the pane holding the first problem. */
    const jumpToFirstError = (errs: Record<string, string[]>) => {
        const roots = Object.keys(errs).map((k) => k.split('.')[0]);
        const owner = SECTIONS.find((s) => s.fields.some((f) => roots.includes(f)));
        if (owner) goTo(owner.key);
    };

    const save = useMutation({
        mutationFn: () => updateSiteSettings({
            ...form,
            header_logo_asset_id: headerLogo?.id ?? null,
            footer_logo_asset_id: footerLogo?.id ?? null,
            favicon_asset_id: favicon?.id ?? null,
        }),
        onSuccess: (data) => {
            setError(null);
            setErrors({});
            hydrate(data);
            qc.invalidateQueries({ queryKey: ['cms-site-settings'] });
            toast.success('Site settings saved successfully');
        },
        onError: (e) => {
            const err = toApiError(e);
            setError(err.message);
            setErrors(err.errors ?? {});
            jumpToFirstError(err.errors ?? {});
            toast.error(err.message || 'Could not save site settings');
        },
    });

    const reset = useMutation({
        mutationFn: () => resetSiteSettings(),
        onSuccess: (data) => {
            hydrate(data);
            setErrors({});
            setError(null);
            setShowResetConfirm(false);
            toast.success('Site settings reset to defaults');
        },
        onError: (e) => toast.error(toApiError(e).message || 'Could not reset site settings'),
    });

    const exportSettings = useMutation({
        mutationFn: () => exportSiteSettings(),
        onSuccess: () => toast.success('Site settings exported successfully'),
        onError: (e) => toast.error(toApiError(e).message || 'Could not export site settings'),
    });

    const importSettings = useMutation({
        mutationFn: (file: File) => importSiteSettings(file),
        onSuccess: (data) => {
            hydrate(data);
            setErrors({});
            setError(null);
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
    const busy = save.isPending || reset.isPending || importSettings.isPending;

    /* ---- footer menu repeater. Array order is the column order on the public site. ---- */
    const columns: FooterMenuColumn[] = form.footer_menus ?? [];
    const setColumns = (next: FooterMenuColumn[]) => set({ footer_menus: next });
    const patchColumn = (i: number, patch: Partial<FooterMenuColumn>) =>
        setColumns(columns.map((c, x) => (x === i ? { ...c, ...patch } : c)));
    const moveColumn = (i: number, delta: number) => {
        const to = i + delta;
        if (to < 0 || to >= columns.length) return;
        const next = [...columns];
        [next[i], next[to]] = [next[to], next[i]];
        setColumns(next);
    };

    const menuOptions = menus.map((menu) => (
        <option key={menu.id} value={menu.id}>{menu.name} ({menu.items_count})</option>
    ));

    const active = SECTIONS.find((s) => s.key === section)!;

    // Rendered in both the page header and the sticky bottom bar, so the two can never
    // disagree about whether saving is available.
    const saveButton = (
        <Button onClick={() => save.mutate()} disabled={busy || isLoading || isError || !dirty}>
            {save.isPending && <Loader2 size={16} className="animate-spin" />}
            {save.isPending ? 'Saving…' : 'Save changes'}
        </Button>
    );

    return (
        <div className="space-y-6">
            {/* ---- page header ---- */}
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <Button variant="outline" onClick={back} disabled={busy}>
                        <ArrowLeft size={16} /> Back
                    </Button>
                    <div>
                        <h1 className="text-[22px] font-bold tracking-tight text-fg">Site Settings</h1>
                        <p className="mt-0.5 text-[13.5px] text-muted">Global branding, contact details and footer for the public site</p>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    {saveButton}

                    {/* Backup/restore actions are rare and one of them is destructive, so they sit
                        behind a menu instead of competing with Save. */}
                    <div className="relative">
                        <Button variant="outline" onClick={() => setMenuOpen((o) => !o)} disabled={busy}>
                            <Settings2 size={16} /> Advanced <ChevronDown size={14} className={menuOpen ? 'rotate-180 transition-transform' : 'transition-transform'} />
                        </Button>
                        {menuOpen && (
                            <>
                                <div className="fixed inset-0 z-10" onClick={() => setMenuOpen(false)} />
                                <div className="absolute right-0 z-20 mt-1.5 w-60 rounded-xl border border-border bg-surface p-1.5 shadow-[var(--shadow-pop)]">
                                    <MenuItem
                                        icon={Download}
                                        disabled={exportSettings.isPending}
                                        onClick={() => { setMenuOpen(false); exportSettings.mutate(); }}
                                    >
                                        {exportSettings.isPending ? 'Exporting…' : 'Export as CSV'}
                                    </MenuItem>
                                    <MenuItem
                                        icon={Upload}
                                        disabled={importSettings.isPending}
                                        onClick={() => { setMenuOpen(false); document.getElementById('import-file-input')?.click(); }}
                                    >
                                        {importSettings.isPending ? 'Importing…' : 'Import from CSV'}
                                    </MenuItem>
                                    <div className="my-1 border-t border-border" />
                                    <MenuItem icon={RotateCcw} danger onClick={() => { setMenuOpen(false); setShowResetConfirm(true); }}>
                                        Reset to defaults
                                    </MenuItem>
                                </div>
                            </>
                        )}
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
                                e.target.value = '';
                            }}
                        />
                    </div>
                </div>
            </div>

            {isLoading ? <Loading /> : isError ? (
                /* Without this the form would render blank and the save bar would claim
                   "All changes saved", inviting the user to overwrite real settings. */
                <Card className="p-8">
                    <div className="flex flex-col items-center gap-3 text-center">
                        <div className="grid h-11 w-11 place-items-center rounded-2xl bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">
                            <AlertCircle size={22} />
                        </div>
                        <div>
                            <p className="text-[14px] font-medium text-fg">Could not load site settings</p>
                            <p className="mt-0.5 text-[13px] text-muted">{toApiError(loadError).message}</p>
                        </div>
                        <Button variant="outline" onClick={() => refetch()}>Try again</Button>
                    </div>
                </Card>
            ) : (
                <>
                    {error && (
                        <p className="flex items-start gap-2 rounded-lg bg-rose-50 px-3 py-2 text-[13px] text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
                            <AlertCircle size={15} className="mt-0.5 shrink-0" /> {error}
                        </p>
                    )}

                    <div className="grid gap-6 lg:grid-cols-[224px_minmax(0,1fr)]">
                        {/* ---- section nav: scrollable pills on mobile, sticky list on desktop ---- */}
                        <nav
                            aria-label="Settings sections"
                            className="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1 lg:sticky lg:top-20 lg:mx-0 lg:flex-col lg:self-start lg:overflow-visible lg:px-0"
                        >
                            {SECTIONS.map((s) => {
                                const isActive = s.key === section;
                                const count = errorCounts[s.key] ?? 0;
                                return (
                                    <button
                                        key={s.key}
                                        type="button"
                                        onClick={() => goTo(s.key)}
                                        aria-current={isActive ? 'page' : undefined}
                                        className={`flex shrink-0 cursor-pointer items-center gap-2.5 rounded-xl px-3 py-2.5 text-left text-[13.5px] font-medium transition-colors lg:w-full ${
                                            isActive
                                                ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300'
                                                : 'text-muted hover:bg-surface-2 hover:text-fg'
                                        }`}
                                    >
                                        <s.icon size={16} className="shrink-0" />
                                        <span className="whitespace-nowrap lg:truncate">{s.label}</span>
                                        {count > 0 && (
                                            <span className="ml-auto grid h-5 min-w-5 shrink-0 place-items-center rounded-full bg-rose-500 px-1 text-[11px] font-bold text-white">
                                                {count}
                                            </span>
                                        )}
                                    </button>
                                );
                            })}
                        </nav>

                        {/* ---- active pane ---- */}
                        <Card className="p-5">
                            <div className="mb-5 flex items-start gap-3 border-b border-border pb-4">
                                <div className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-surface-2 text-muted">
                                    <active.icon size={17} />
                                </div>
                                <div>
                                    <h2 className="text-[15px] font-semibold text-fg">{active.title}</h2>
                                    <p className="mt-0.5 text-[12.5px] text-muted">{active.desc}</p>
                                </div>
                            </div>

                            {section === 'general' && (
                                <div className="space-y-4">
                                    <Field label="Site Title" required error={errors.site_title} hint="Used in the browser tab and as the fallback SEO title.">
                                        <input className={inputCls} value={form.site_title} onChange={(e) => set({ site_title: e.target.value })} placeholder="Decent Model College" />
                                    </Field>
                                    <Field label="Site Tagline" error={errors.site_tagline} hint="A short line shown beside or below the logo.">
                                        <input className={inputCls} value={form.site_tagline ?? ''} onChange={(e) => set({ site_tagline: e.target.value })} placeholder="Excellence in education since 1985" />
                                    </Field>
                                    <Field
                                        label="Site Description"
                                        error={errors.site_description}
                                        hint={`Used as the default meta description. ${(form.site_description ?? '').length}/160 characters recommended.`}
                                    >
                                        <textarea
                                            className={`${inputCls} min-h-[100px] resize-y`}
                                            value={form.site_description ?? ''}
                                            onChange={(e) => set({ site_description: e.target.value })}
                                            placeholder="Brief description of your institution"
                                        />
                                    </Field>
                                    <Field label="EIIN" error={errors.eiin} hint="Institute EIIN, shown in the site top bar.">
                                        <input className={inputCls} value={form.eiin ?? ''} onChange={(e) => set({ eiin: e.target.value })} placeholder="123456" />
                                    </Field>
                                </div>
                            )}

                            {section === 'branding' && (
                                <div className="space-y-5">
                                    <FileUpload
                                        label="Header Logo"
                                        category="cms"
                                        imageOnly
                                        hint="Wide logo for the site header"
                                        value={headerLogo?.id ?? null}
                                        previewUrl={headerLogo?.thumb_url ?? headerLogo?.url ?? null}
                                        onChange={(_id, asset) => setHeaderLogo(asset ?? null)}
                                    />
                                    <FileUpload
                                        label="Footer Logo"
                                        category="cms"
                                        imageOnly
                                        hint="Often a light version of the header logo"
                                        value={footerLogo?.id ?? null}
                                        previewUrl={footerLogo?.thumb_url ?? footerLogo?.url ?? null}
                                        onChange={(_id, asset) => setFooterLogo(asset ?? null)}
                                    />
                                    <FileUpload
                                        label="Favicon"
                                        category="cms"
                                        imageOnly
                                        hint="Square image (PNG, SVG or ICO)"
                                        value={favicon?.id ?? null}
                                        previewUrl={favicon?.thumb_url ?? favicon?.url ?? null}
                                        onChange={(_id, asset) => setFavicon(asset ?? null)}
                                    />
                                </div>
                            )}

                            {section === 'header' && (
                                <div className="space-y-4">
                                    {/* Both halves are needed for the button to render on the public site. */}
                                    <Group title="Top bar button" hint="Small link in the thin bar above the navigation. Fill in both fields to show it.">
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <Field label="Label" error={errors.header_topbar_cta_label}>
                                                <input className={inputCls} value={form.header_topbar_cta_label ?? ''} onChange={(e) => set({ header_topbar_cta_label: e.target.value })} placeholder="Online Result" />
                                            </Field>
                                            <Field label="Link" error={errors.header_topbar_cta_url}>
                                                <IconInput icon={Link2} value={form.header_topbar_cta_url ?? ''} onChange={(e) => set({ header_topbar_cta_url: e.target.value })} placeholder="/results" />
                                            </Field>
                                        </div>
                                    </Group>
                                    <Group title="Main header button" hint="The primary call-to-action beside the navigation menu. Fill in both fields to show it.">
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <Field label="Label" error={errors.header_cta_label}>
                                                <input className={inputCls} value={form.header_cta_label ?? ''} onChange={(e) => set({ header_cta_label: e.target.value })} placeholder="Apply for Admission" />
                                            </Field>
                                            <Field label="Link" error={errors.header_cta_url}>
                                                <IconInput icon={Link2} value={form.header_cta_url ?? ''} onChange={(e) => set({ header_cta_url: e.target.value })} placeholder="/academics/admission" />
                                            </Field>
                                        </div>
                                    </Group>
                                </div>
                            )}

                            {section === 'contact' && (
                                <div className="space-y-4">
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field label="Contact Email" error={errors.contact_email}>
                                            <IconInput icon={Mail} type="email" value={form.contact_email ?? ''} onChange={(e) => set({ contact_email: e.target.value })} placeholder="info@example.edu.bd" />
                                        </Field>
                                        <Field label="Contact Phone" error={errors.contact_phone}>
                                            <IconInput icon={Phone} type="tel" value={form.contact_phone ?? ''} onChange={(e) => set({ contact_phone: e.target.value })} placeholder="+880 1700 000000" />
                                        </Field>
                                    </div>
                                    <Field label="Contact Address" error={errors.contact_address}>
                                        <div className="relative">
                                            <MapPin size={16} className="pointer-events-none absolute left-3.5 top-3 text-faint" />
                                            <textarea className={`${inputCls} min-h-[80px] resize-y pl-10`} value={form.contact_address ?? ''} onChange={(e) => set({ contact_address: e.target.value })} placeholder="Street, city, district, postcode" />
                                        </div>
                                    </Field>
                                </div>
                            )}

                            {section === 'footer' && (
                                <div className="space-y-5">
                                    <Field label="Footer Description" error={errors.footer_description} hint="Short paragraph shown under the footer logo.">
                                        <textarea
                                            className={`${inputCls} min-h-[90px] resize-y`}
                                            value={form.footer_description ?? ''}
                                            onChange={(e) => set({ footer_description: e.target.value })}
                                            placeholder="A few lines about your institution"
                                        />
                                    </Field>

                                    <div>
                                        <div className="mb-1.5 flex items-center justify-between">
                                            <label className="text-[13px] font-medium text-fg">Footer Link Columns</label>
                                            <span className="text-[12px] text-muted">{columns.length} of 6 used</span>
                                        </div>
                                        <p className="mb-3 text-[12px] text-muted">Each column shows one menu in the footer, in the order listed here.</p>
                                        {errors.footer_menus?.[0] && (
                                            <p className="mb-2 text-[12px] text-rose-600">{errors.footer_menus[0]}</p>
                                        )}

                                        {columns.length === 0 ? (
                                            <div className="rounded-xl border border-dashed border-border px-4 py-6 text-center">
                                                <p className="text-[13px] text-muted">No footer columns yet.</p>
                                                <p className="mt-0.5 text-[12px] text-faint">Add one to show a menu in the site footer.</p>
                                            </div>
                                        ) : (
                                            <div className="space-y-3">
                                                {columns.map((column, i) => (
                                                    <div key={i} className="rounded-xl border border-border bg-surface-2/50 p-3.5">
                                                        <div className="mb-2.5 flex items-center justify-between">
                                                            <span className="flex items-center gap-2 text-[12px] font-semibold text-muted">
                                                                <span className="grid h-5 w-5 place-items-center rounded-md bg-surface text-[11px] font-bold text-fg ring-1 ring-border">
                                                                    {i + 1}
                                                                </span>
                                                                {column.title?.trim() || 'Untitled column'}
                                                            </span>
                                                            <div className="flex items-center gap-1">
                                                                <button type="button" title="Move up" aria-label="Move column up" disabled={i === 0} onClick={() => moveColumn(i, -1)} className="cursor-pointer rounded-md p-1 text-faint hover:bg-surface hover:text-fg disabled:cursor-not-allowed disabled:opacity-40">
                                                                    <ChevronUp size={15} />
                                                                </button>
                                                                <button type="button" title="Move down" aria-label="Move column down" disabled={i === columns.length - 1} onClick={() => moveColumn(i, 1)} className="cursor-pointer rounded-md p-1 text-faint hover:bg-surface hover:text-fg disabled:cursor-not-allowed disabled:opacity-40">
                                                                    <ChevronDown size={15} />
                                                                </button>
                                                                <button type="button" title="Remove column" aria-label="Remove column" onClick={() => setColumns(columns.filter((_, x) => x !== i))} className="cursor-pointer rounded-md p-1 text-faint hover:bg-surface hover:text-rose-500">
                                                                    <Trash2 size={14} />
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div className="grid gap-3 sm:grid-cols-2">
                                                            <Field label="Column Heading" error={errors[`footer_menus.${i}.title`]}>
                                                                <input
                                                                    className={inputCls}
                                                                    value={column.title}
                                                                    onChange={(e) => patchColumn(i, { title: e.target.value })}
                                                                    placeholder="Quick Links"
                                                                />
                                                            </Field>
                                                            <Field label="Menu" error={errors[`footer_menus.${i}.menu_id`]}>
                                                                <select
                                                                    className={inputCls}
                                                                    value={column.menu_id ?? ''}
                                                                    onChange={(e) => patchColumn(i, { menu_id: e.target.value ? Number(e.target.value) : null })}
                                                                >
                                                                    <option value="">Select a menu…</option>
                                                                    {menuOptions}
                                                                </select>
                                                            </Field>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        )}

                                        <Button
                                            variant="outline"
                                            size="sm"
                                            type="button"
                                            className="mt-3 cursor-pointer"
                                            disabled={columns.length >= 6}
                                            onClick={() => setColumns([...columns, { title: '', menu_id: null }])}
                                        >
                                            <Plus size={15} /> Add column
                                        </Button>
                                    </div>

                                    {/* Bottom bar: the copyright line and the small legal menu beside it. */}
                                    <div className="border-t border-border pt-5">
                                        <p className="mb-3 text-[13px] font-semibold text-fg">Bottom bar</p>
                                        <div className="space-y-4">
                                            <Field label="Copyright Text" error={errors.footer_copyright} hint="Leave empty to keep the site's default line.">
                                                <RichTextEditor
                                                    value={form.footer_copyright ?? ''}
                                                    onChange={(html) => set({ footer_copyright: html })}
                                                />
                                            </Field>
                                            <Field label="Bottom Bar Menu" error={errors.footer_bottom_menu_id} hint="Terms, privacy policy and similar links, shown beside the copyright.">
                                                <select
                                                    className={inputCls}
                                                    value={form.footer_bottom_menu_id ?? ''}
                                                    onChange={(e) => set({ footer_bottom_menu_id: e.target.value ? Number(e.target.value) : null })}
                                                >
                                                    <option value="">No menu</option>
                                                    {menuOptions}
                                                </select>
                                            </Field>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {section === 'social' && (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field label="Facebook" error={errors.facebook_url}>
                                        <IconInput icon={FacebookIcon} type="url" value={form.facebook_url ?? ''} onChange={(e) => set({ facebook_url: e.target.value })} placeholder="https://facebook.com/your-page" />
                                    </Field>
                                    <Field label="Twitter / X" error={errors.twitter_url}>
                                        <IconInput icon={TwitterIcon} type="url" value={form.twitter_url ?? ''} onChange={(e) => set({ twitter_url: e.target.value })} placeholder="https://twitter.com/your-account" />
                                    </Field>
                                    <Field label="LinkedIn" error={errors.linkedin_url}>
                                        <IconInput icon={LinkedinIcon} type="url" value={form.linkedin_url ?? ''} onChange={(e) => set({ linkedin_url: e.target.value })} placeholder="https://linkedin.com/company/your-company" />
                                    </Field>
                                    <Field label="YouTube" error={errors.youtube_url}>
                                        <IconInput icon={YoutubeIcon} type="url" value={form.youtube_url ?? ''} onChange={(e) => set({ youtube_url: e.target.value })} placeholder="https://youtube.com/@your-channel" />
                                    </Field>
                                    <Field label="Instagram" error={errors.instagram_url}>
                                        <IconInput icon={InstagramIcon} type="url" value={form.instagram_url ?? ''} onChange={(e) => set({ instagram_url: e.target.value })} placeholder="https://instagram.com/your-account" />
                                    </Field>
                                </div>
                            )}

                            {section === 'seo' && (
                                <div className="space-y-4">
                                    <Field label="Meta Keywords" error={errors.meta_keywords} hint="Comma-separated. Most search engines ignore these, but some directories still read them.">
                                        <input className={inputCls} value={form.meta_keywords ?? ''} onChange={(e) => set({ meta_keywords: e.target.value })} placeholder="college, admission, dhaka" />
                                    </Field>
                                    <Field label="Google Analytics Code" error={errors.google_analytics_code} hint="Paste the full snippet, including its script tags.">
                                        <textarea className={`${inputCls} min-h-[110px] resize-y font-mono text-[12px]`} value={form.google_analytics_code ?? ''} onChange={(e) => set({ google_analytics_code: e.target.value })} placeholder="<!-- Google tag (gtag.js) -->" />
                                    </Field>
                                    <Field label="Google Tag Manager Code" error={errors.google_tag_manager_code} hint="Paste the full snippet, including its script tags.">
                                        <textarea className={`${inputCls} min-h-[110px] resize-y font-mono text-[12px]`} value={form.google_tag_manager_code ?? ''} onChange={(e) => set({ google_tag_manager_code: e.target.value })} placeholder="<!-- Google Tag Manager -->" />
                                    </Field>
                                </div>
                            )}
                        </Card>
                    </div>

                    {/* ---- sticky save bar: reachable from any scroll position ---- */}
                    <div className="sticky bottom-0 z-10 -mx-4 border-t border-border bg-bg/85 px-4 py-3 backdrop-blur-md sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
                        <div className="flex flex-wrap items-center justify-end gap-3">
                            <p className="mr-auto flex items-center gap-2 text-[12.5px] text-muted">
                                {dirty ? (
                                    <>
                                        <span className="h-2 w-2 rounded-full bg-amber-500" />
                                        Unsaved changes
                                    </>
                                ) : (
                                    <>
                                        <span className="h-2 w-2 rounded-full bg-emerald-500" />
                                        All changes saved
                                    </>
                                )}
                            </p>
                            <Button variant="outline" onClick={back} disabled={busy}>Cancel</Button>
                            {saveButton}
                        </div>
                    </div>
                </>
            )}

            {/* ---- confirmations ---- */}
            <Modal
                open={showResetConfirm}
                onClose={() => setShowResetConfirm(false)}
                title="Reset to defaults?"
                footer={
                    <>
                        <Button variant="outline" onClick={() => setShowResetConfirm(false)} disabled={reset.isPending}>Cancel</Button>
                        <Button className="bg-rose-600 hover:bg-rose-700" onClick={() => reset.mutate()} disabled={reset.isPending}>
                            {reset.isPending ? 'Resetting…' : 'Reset settings'}
                        </Button>
                    </>
                }
            >
                <p className="text-[14px] text-muted">
                    Every setting on this page goes back to its default value, including logos, footer columns and
                    tracking codes. This cannot be undone — export a CSV first if you want a backup.
                </p>
            </Modal>

            <Modal
                open={showImportConfirm && importFile !== null}
                onClose={() => { setShowImportConfirm(false); setImportFile(null); }}
                title="Import settings from CSV?"
                footer={
                    <>
                        <Button variant="outline" onClick={() => { setShowImportConfirm(false); setImportFile(null); }} disabled={importSettings.isPending}>Cancel</Button>
                        <Button onClick={() => importFile && importSettings.mutate(importFile)} disabled={importSettings.isPending}>
                            {importSettings.isPending ? 'Importing…' : 'Import settings'}
                        </Button>
                    </>
                }
            >
                <p className="text-[14px] text-muted">Importing from:</p>
                <p className="mt-1.5 rounded-lg bg-surface-2 px-3 py-2 font-mono text-[12px] text-fg">{importFile?.name}</p>
                <p className="mt-3 text-[13px] text-rose-600 dark:text-rose-400">
                    This overwrites your current site settings and cannot be undone.
                </p>
            </Modal>

            <Modal
                open={showLeaveConfirm}
                onClose={() => setShowLeaveConfirm(false)}
                title="Discard unsaved changes?"
                footer={
                    <>
                        <Button variant="outline" onClick={() => setShowLeaveConfirm(false)}>Keep editing</Button>
                        <Button className="bg-rose-600 hover:bg-rose-700" onClick={leave}>Discard changes</Button>
                    </>
                }
            >
                <p className="text-[14px] text-muted">You have edits on this page that have not been saved yet.</p>
            </Modal>
        </div>
    );
}
