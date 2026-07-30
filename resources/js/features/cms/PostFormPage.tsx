import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Loader2 } from 'lucide-react';
import { Card, Button } from '@/components/ui';
import { toApiError } from '@/lib/api';
import {
    getPost, getPostMeta, createPost, updatePost,
    inputCls, type PostPayload, type SeoData, type AssetPayload, type EditorBlock,
} from './api';
import { BlockEditor } from './BlockEditor';
import { SeoFields } from './SeoFields';
import { RichTextEditor } from './RichTextEditor';
import { Tabs, Field, Loading } from './PagesPanel';
import { FileUpload } from '@/components/FileUpload';
import { useToast } from '@/components/Toast';

const EMPTY: PostPayload = { title: '', status: 'draft', body: '', terms: [], tags: [], blocks: [], seo: {} };

export default function PostFormPage() {
    const { slug: slugParam, id: idParam } = useParams<{ slug?: string; id?: string }>();
    const slugOrId = slugParam || idParam || null;
    const [postId, setPostId] = useState<number | null>(null);
    const navigate = useNavigate();
    const qc = useQueryClient();
    const toast = useToast();

    const [tab, setTab] = useState<'content' | 'blocks' | 'seo'>('content');
    const [form, setForm] = useState<PostPayload>(EMPTY);
    const [seo, setSeo] = useState<SeoData>({});
    const [ogImage, setOgImage] = useState<AssetPayload | null>(null);
    const [featuredAsset, setFeaturedAsset] = useState<AssetPayload | null>(null);
    const [blocks, setBlocks] = useState<EditorBlock[]>([]);
    const [tagInput, setTagInput] = useState('');
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [error, setError] = useState<string | null>(null);

    const back = () => navigate('/cms?tab=posts');

    const { data: meta } = useQuery({ queryKey: ['cms-post-meta'], queryFn: getPostMeta });
    const { isLoading } = useQuery({
        queryKey: ['cms-post', slugOrId],
        queryFn: async () => {
            const p = await getPost(slugOrId!);
            setPostId(p.id);
            setForm({
                title: p.title, slug: p.slug, excerpt: p.excerpt, body: p.body, status: p.status,
                is_featured: p.is_featured, published_at: p.published_at, featured_asset_id: p.featured_asset_id,
                terms: p.terms, tags: p.tags,
            });
            setSeo(p.seo ?? {});
            setOgImage(p.seo_og_image);
            setFeaturedAsset(p.featured_asset);
            setBlocks(p.blocks);
            return p;
        },
        enabled: slugOrId !== null,
    });

    const save = useMutation({
        mutationFn: () => {
            const payload: PostPayload = { ...form, seo, blocks };
            const target = postId ?? slugOrId;
            return target ? updatePost(target, payload) : createPost(payload);
        },
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['cms-posts'] });
            toast.success('Post saved successfully');
            back();
        },
        onError: (e) => {
            const err = toApiError(e);
            setError(err.message);
            setErrors(err.errors ?? {});
            toast.error(err.message || 'Could not save post');
        },
    });

    const set = (patch: Partial<PostPayload>) => setForm((f) => ({ ...f, ...patch }));
    const toggleTerm = (tid: number) => set({ terms: form.terms?.includes(tid) ? form.terms.filter((x) => x !== tid) : [...(form.terms ?? []), tid] });
    const addTag = (t: string) => { const v = t.trim(); if (v && !form.tags?.includes(v)) set({ tags: [...(form.tags ?? []), v] }); setTagInput(''); };

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <Button variant="outline" onClick={back} disabled={save.isPending}><ArrowLeft size={16} /> Back</Button>
                    <div>
                        <h1 className="text-[22px] font-bold tracking-tight text-fg">{slugOrId ? 'Edit post' : 'New post'}</h1>
                        <p className="mt-0.5 text-[13.5px] text-muted">{slugOrId ? `Editing “${form.title}”` : 'Create a new post for the public site'}</p>
                    </div>
                </div>
                <div className="flex gap-2">
                    <Button variant="outline" onClick={back} disabled={save.isPending}>Cancel</Button>
                    <Button onClick={() => save.mutate()} disabled={save.isPending}>
                        {save.isPending && <Loader2 size={16} className="animate-spin" />}
                        {save.isPending ? 'Saving…' : 'Save post'}
                    </Button>
                </div>
            </div>

            {slugOrId !== null && isLoading ? <Loading /> : (
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
                                    <Field label="Status" error={errors.status}>
                                        <select className={inputCls} value={form.status} onChange={(e) => set({ status: e.target.value as PostPayload['status'] })}>
                                            {(meta?.statuses ?? []).map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                                        </select>
                                    </Field>
                                </div>
                                <Field label="Excerpt">
                                    <textarea rows={2} className={inputCls} value={form.excerpt ?? ''} onChange={(e) => set({ excerpt: e.target.value })} />
                                </Field>
                                <Field label="Body">
                                    <RichTextEditor value={form.body ?? ''} onChange={(html) => set({ body: html })} />
                                </Field>
                                <FileUpload
                                    label="Featured image"
                                    category="cms"
                                    imageOnly
                                    value={form.featured_asset_id ?? null}
                                    previewUrl={featuredAsset?.thumb_url ?? featuredAsset?.url ?? null}
                                    onChange={(assetId, asset) => {
                                        set({ featured_asset_id: assetId ? Number(assetId) : null });
                                        setFeaturedAsset(asset ?? null);
                                    }}
                                />
                                <label className="flex items-center gap-2 text-[13px] text-fg">
                                    <input type="checkbox" checked={!!form.is_featured} onChange={(e) => set({ is_featured: e.target.checked })} /> Featured post
                                </label>
                                <Field label="Categories">
                                    <div className="flex flex-wrap gap-1.5">
                                        {(meta?.terms ?? []).map((t) => (
                                            <button key={t.id} type="button" onClick={() => toggleTerm(t.id)}
                                                className={`rounded-full px-3 py-1 text-[12.5px] ring-1 ring-inset ${form.terms?.includes(t.id) ? 'bg-brand-600 text-white ring-brand-600' : 'bg-surface-2 text-muted ring-border'}`}>
                                                {t.name}
                                            </button>
                                        ))}
                                        {(meta?.terms ?? []).length === 0 && <span className="text-[12.5px] text-muted">No terms — create a taxonomy first.</span>}
                                    </div>
                                </Field>
                                <Field label="Tags">
                                    <div className="flex flex-wrap items-center gap-1.5">
                                        {form.tags?.map((t) => (
                                            <span key={t} className="inline-flex items-center gap-1 rounded-full bg-surface-2 px-2.5 py-1 text-[12.5px] text-fg">
                                                {t}<button type="button" onClick={() => set({ tags: form.tags?.filter((x) => x !== t) })} className="text-faint hover:text-rose-500">×</button>
                                            </span>
                                        ))}
                                        <input value={tagInput} onChange={(e) => setTagInput(e.target.value)}
                                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addTag(tagInput); } }}
                                            placeholder="Add tag…" className="min-w-[120px] flex-1 bg-transparent px-1 py-1 text-[13px] outline-none" />
                                    </div>
                                </Field>
                            </div>
                        )}

                        {tab === 'blocks' && <BlockEditor blocks={blocks} blockTypes={meta?.block_types ?? []} onChange={setBlocks} />}

                        {tab === 'seo' && <SeoFields value={seo} ogImage={ogImage} onChange={(s, img) => { setSeo(s); setOgImage(img); }} />}
                    </div>
                </Card>
            )}
        </div>
    );
}
