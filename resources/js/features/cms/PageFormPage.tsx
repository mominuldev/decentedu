import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, CheckCircle2, Loader2, XCircle } from 'lucide-react';
import { Card, Button } from '@/components/ui';
import { toApiError } from '@/lib/api';
import {
    getPage, getPageMeta, createPage, updatePage,
    inputCls, type PageDetail, type PagePayload, type SeoData, type AssetPayload, type EditorBlock,
} from './api';
import { BlockEditor } from './BlockEditor';
import { SeoFields } from './SeoFields';
import { Tabs, Field, Loading } from './PagesPanel';

const EMPTY: PagePayload = { title: '', template: 'default', status: 'draft', blocks: [], seo: {} };

export default function PageFormPage() {
    const { id: idParam } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const qc = useQueryClient();

    // Track the id locally so a freshly-created page switches into edit mode
    // (subsequent saves become updates) without a route remount that would reset the tab.
    const [id, setId] = useState<number | null>(idParam ? Number(idParam) : null);
    const [tab, setTab] = useState<'content' | 'blocks' | 'seo'>('content');
    const [form, setForm] = useState<PagePayload>(EMPTY);
    const [seo, setSeo] = useState<SeoData>({});
    const [ogImage, setOgImage] = useState<AssetPayload | null>(null);
    const [blocks, setBlocks] = useState<EditorBlock[]>([]);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [error, setError] = useState<string | null>(null);
    const [toast, setToast] = useState<{ tone: 'success' | 'error'; message: string } | null>(null);

    useEffect(() => {
        if (!toast) return;
        const t = setTimeout(() => setToast(null), 4000);
        return () => clearTimeout(t);
    }, [toast]);

    const back = () => navigate('/cms?tab=pages');

    const { data: meta } = useQuery({ queryKey: ['cms-page-meta'], queryFn: getPageMeta });
    const { isLoading } = useQuery({
        queryKey: ['cms-page', id],
        queryFn: async () => {
            const p = await getPage(id!);
            hydrate(p);
            return p;
        },
        enabled: id !== null,
    });

    const hydrate = (p: PageDetail) => {
        setForm({
            title: p.title, slug: p.slug, parent_id: p.parent_id, template: p.template, excerpt: p.excerpt,
            status: p.status, published_at: p.published_at, position: p.position, featured_asset_id: p.featured_asset_id,
        });
        setSeo(p.seo ?? {});
        setOgImage(p.seo_og_image);
        setBlocks(p.blocks);
    };

    const save = useMutation({
        mutationFn: () => {
            const payload: PagePayload = { ...form, seo, blocks };
            return id ? updatePage(id, payload) : createPage(payload);
        },
        onSuccess: (page) => {
            setError(null);
            setErrors({});
            qc.invalidateQueries({ queryKey: ['cms-pages'] });
            if (id === null) {
                // Newly created: move into edit mode and sync the URL without remounting,
                // so the user stays on the tab they were editing.
                setId(page.id);
                hydrate(page);
                window.history.replaceState(null, '', `/cms/${page.id}/edit`);
            } else {
                qc.invalidateQueries({ queryKey: ['cms-page', id] });
            }
            setToast({ tone: 'success', message: 'Page saved' });
        },
        onError: (e) => {
            const err = toApiError(e);
            setError(err.message);
            setErrors(err.errors ?? {});
            setToast({ tone: 'error', message: err.message || 'Could not save page' });
        },
    });

    const set = (patch: Partial<PagePayload>) => setForm((f) => ({ ...f, ...patch }));
    const blockTypes = meta?.block_types ?? [];

    return (
        <div className="space-y-6">
            {toast && (
                <div
                    role="status"
                    className={`fixed right-5 top-5 z-50 flex items-center gap-2 rounded-lg px-4 py-2.5 text-[13.5px] font-medium shadow-lg ${
                        toast.tone === 'success'
                            ? 'bg-emerald-600 text-white'
                            : 'bg-rose-600 text-white'
                    }`}
                >
                    {toast.tone === 'success' ? <CheckCircle2 size={16} /> : <XCircle size={16} />}
                    {toast.message}
                </div>
            )}
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <Button variant="outline" onClick={back} disabled={save.isPending}><ArrowLeft size={16} /> Back</Button>
                    <div>
                        <h1 className="text-[22px] font-bold tracking-tight text-fg">{id ? 'Edit page' : 'New page'}</h1>
                        <p className="mt-0.5 text-[13.5px] text-muted">{id ? `Editing “${form.title}”` : 'Create a new page for the public site'}</p>
                    </div>
                </div>
                <div className="flex gap-2">
                    <Button variant="outline" onClick={back} disabled={save.isPending}>Cancel</Button>
                    <Button onClick={() => save.mutate()} disabled={save.isPending}>
                        {save.isPending && <Loader2 size={16} className="animate-spin" />}
                        {save.isPending ? 'Saving…' : 'Save page'}
                    </Button>
                </div>
            </div>

            {id !== null && isLoading ? <Loading /> : (
                <Card className="p-5">
                    <div className="space-y-4">
                        {error && <p className="rounded-lg bg-rose-50 px-3 py-2 text-[13px] text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">{error}</p>}
                        <Tabs tab={tab} setTab={setTab} tabs={[['content', 'Content'], ['blocks', 'Blocks'], ['seo', 'SEO']]} />

                        {tab === 'content' && (
                            <div className="space-y-4">
                                <Field label="Title" error={errors.title}>
                                    <input className={inputCls} value={form.title} onChange={(e) => set({ title: e.target.value })} />
                                </Field>
                                <div className="grid grid-cols-2 gap-3">
                                    <Field label="Slug (optional)" error={errors.slug}>
                                        <input className={inputCls} value={form.slug ?? ''} onChange={(e) => set({ slug: e.target.value })} placeholder="auto from title" />
                                    </Field>
                                    <Field label="Parent page">
                                        <select className={inputCls} value={form.parent_id ?? ''} onChange={(e) => set({ parent_id: e.target.value ? Number(e.target.value) : null })}>
                                            <option value="">— None (top level) —</option>
                                            {meta?.parents.filter((p) => p.id !== id).map((p) => <option key={p.id} value={p.id}>{p.title} (/{p.path})</option>)}
                                        </select>
                                    </Field>
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <Field label="Template">
                                        <select className={inputCls} value={form.template} onChange={(e) => set({ template: e.target.value })}>
                                            {meta?.templates.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                                        </select>
                                    </Field>
                                    <Field label="Status" error={errors.status}>
                                        <select className={inputCls} value={form.status} onChange={(e) => set({ status: e.target.value as PagePayload['status'] })}>
                                            {(meta?.statuses ?? []).map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                                        </select>
                                    </Field>
                                </div>
                                <Field label="Excerpt">
                                    <textarea rows={2} className={inputCls} value={form.excerpt ?? ''} onChange={(e) => set({ excerpt: e.target.value })} />
                                </Field>
                            </div>
                        )}

                        {tab === 'blocks' && <BlockEditor blocks={blocks} blockTypes={blockTypes} onChange={setBlocks} />}

                        {tab === 'seo' && <SeoFields value={seo} ogImage={ogImage} onChange={(s, img) => { setSeo(s); setOgImage(img); }} />}
                    </div>
                </Card>
            )}
        </div>
    );
}
