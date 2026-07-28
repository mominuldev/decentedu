import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Loader2, Plus, X } from 'lucide-react';
import { Card, Button } from '@/components/ui';
import { toApiError } from '@/lib/api';
import { useToast } from '@/components/Toast';
import { FileUpload } from '@/components/FileUpload';
import { MediaPicker } from './MediaPicker';
import { RichTextEditor } from './RichTextEditor';
import { Field, Loading } from './PagesPanel';
import {
    getGallery, createGallery, updateGallery, inputCls, labelCls,
    type GalleryPayload, type AssetPayload,
} from './api';

const EMPTY: GalleryPayload = {
    title: '',
    slug: '',
    description: '',
    cover_asset_id: null,
    images: [],
    status: 'published',
};

export default function GalleryFormPage() {
    const { slug: slugParam, id: idParam } = useParams<{ slug?: string; id?: string }>();
    const slugOrId = slugParam || idParam || null;
    const [galleryId, setGalleryId] = useState<number | null>(null);
    const navigate = useNavigate();
    const qc = useQueryClient();
    const toast = useToast();

    const [form, setForm] = useState<GalleryPayload>(EMPTY);
    const [coverAsset, setCoverAsset] = useState<AssetPayload | null>(null);
    const [galleryAssets, setGalleryAssets] = useState<AssetPayload[]>([]);
    const [pickerOpen, setPickerOpen] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [error, setError] = useState<string | null>(null);

    const back = () => navigate('/cms?tab=gallery');

    const { isLoading } = useQuery({
        queryKey: ['cms-gallery', slugOrId],
        queryFn: async () => {
            const g = await getGallery(slugOrId!);
            setGalleryId(g.id);
            setForm({
                title: g.title,
                slug: g.slug,
                description: g.description ?? '',
                cover_asset_id: g.cover_asset_id,
                images: g.image_ids ?? g.images?.map((i) => i.id) ?? [],
                status: g.status,
            });
            setCoverAsset(g.cover_asset);
            setGalleryAssets(g.images ?? []);
            return g;
        },
        enabled: slugOrId !== null,
    });

    const save = useMutation({
        mutationFn: () => {
            const payload: GalleryPayload = {
                ...form,
                cover_asset_id: coverAsset?.id ?? form.cover_asset_id ?? null,
                images: galleryAssets.map((a) => a.id),
            };
            const target = galleryId ?? slugOrId;
            return target ? updateGallery(target, payload) : createGallery(payload);
        },
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['cms-galleries'] });
            toast.success(slugOrId ? 'Gallery updated successfully' : 'Gallery created successfully');
            back();
        },
        onError: (e) => {
            const err = toApiError(e);
            setError(err.message);
            setErrors(err.errors ?? {});
            toast.error(err.message || 'Could not save gallery');
        },
    });

    const set = (patch: Partial<GalleryPayload>) => setForm((f) => ({ ...f, ...patch }));

    const removeImage = (assetId: number) => {
        setGalleryAssets((prev) => prev.filter((a) => a.id !== assetId));
        setForm((f) => ({ ...f, images: (f.images ?? []).filter((x) => x !== assetId) }));
    };

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <Button variant="outline" onClick={back} disabled={save.isPending}>
                        <ArrowLeft size={16} /> Back
                    </Button>
                    <div>
                        <h1 className="text-[22px] font-bold tracking-tight text-fg">{slugOrId ? 'Edit gallery' : 'New gallery'}</h1>
                        <p className="mt-0.5 text-[13.5px] text-muted">
                            {slugOrId ? `Editing “${form.title}”` : 'Create a photo & video gallery for the public site'}
                        </p>
                    </div>
                </div>
                <div className="flex gap-2">
                    <Button variant="outline" onClick={back} disabled={save.isPending}>Cancel</Button>
                    <Button onClick={() => save.mutate()} disabled={save.isPending}>
                        {save.isPending && <Loader2 size={16} className="animate-spin" />}
                        {save.isPending ? 'Saving…' : slugOrId ? 'Save changes' : 'Create gallery'}
                    </Button>
                </div>
            </div>

            {slugOrId !== null && isLoading ? (
                <Loading />
            ) : (
                <Card className="p-6">
                    <div className="space-y-6">
                        {error && (
                            <p className="rounded-lg bg-rose-50 px-3 py-2 text-[13px] text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
                                {error}
                            </p>
                        )}

                        <Field label="Title" error={errors.title}>
                            <input
                                className={inputCls}
                                value={form.title}
                                onChange={(e) => set({ title: e.target.value })}
                                placeholder="e.g. Annual Sports Day 2026"
                            />
                        </Field>

                        <div className="grid grid-cols-2 gap-4">
                            <Field label="Slug (optional)" error={errors.slug}>
                                <input
                                    className={inputCls}
                                    value={form.slug ?? ''}
                                    onChange={(e) => set({ slug: e.target.value })}
                                    placeholder="auto-generated from title"
                                />
                            </Field>

                            <Field label="Status" error={errors.status}>
                                <select
                                    className={inputCls}
                                    value={form.status}
                                    onChange={(e) => set({ status: e.target.value as GalleryPayload['status'] })}
                                >
                                    <option value="published">Published</option>
                                    <option value="draft">Draft</option>
                                </select>
                            </Field>
                        </div>

                        <Field label="Description">
                            <RichTextEditor
                                value={form.description ?? ''}
                                onChange={(html) => set({ description: html })}
                            />
                        </Field>

                        <FileUpload
                            label="Cover image"
                            category="cms"
                            value={coverAsset?.id ?? form.cover_asset_id ?? null}
                            previewUrl={coverAsset?.thumb_url ?? coverAsset?.url ?? null}
                            onChange={(assetId, asset) => {
                                set({ cover_asset_id: assetId ? Number(assetId) : null });
                                setCoverAsset(asset ?? null);
                            }}
                        />

                        <div>
                            <label className={labelCls}>Gallery Photos</label>
                            <div className="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4 md:grid-cols-6">
                                {galleryAssets.map((asset) => (
                                    <div key={asset.id} className="group relative aspect-square overflow-hidden rounded-xl border border-border bg-surface-2">
                                        <img src={asset.thumb_url ?? asset.url ?? ''} alt={asset.name} className="h-full w-full object-cover" />
                                        <button
                                            type="button"
                                            onClick={() => removeImage(asset.id)}
                                            className="absolute right-1.5 top-1.5 grid h-6 w-6 place-items-center rounded-full bg-slate-950/65 text-white transition-colors hover:bg-rose-600"
                                            aria-label="Remove photo"
                                        >
                                            <X size={13} />
                                        </button>
                                        <div className="absolute inset-x-0 bottom-0 bg-slate-950/60 p-1 text-[10.5px] text-white truncate text-center opacity-0 transition-opacity group-hover:opacity-100">
                                            {asset.name}
                                        </div>
                                    </div>
                                ))}

                                <button
                                    type="button"
                                    onClick={() => setPickerOpen(true)}
                                    className="flex aspect-square flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed border-border-strong bg-surface-2 p-3 text-faint transition-colors hover:border-brand-500 hover:text-brand-600"
                                >
                                    <Plus size={22} />
                                    <span className="text-[12px] font-medium">Add photos</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </Card>
            )}

            {pickerOpen && (
                <MediaPicker
                    open={pickerOpen}
                    category="cms"
                    multiple
                    onClose={() => setPickerOpen(false)}
                    onPick={(selectedAssets) => {
                        const existingIds = galleryAssets.map((a) => a.id);
                        const mergedAssets = [...galleryAssets];

                        selectedAssets.forEach((a) => {
                            if (!existingIds.includes(a.id)) {
                                existingIds.push(a.id);
                                mergedAssets.push(a);
                            }
                        });

                        setGalleryAssets(mergedAssets);
                        setForm((f) => ({ ...f, images: existingIds }));
                    }}
                />
            )}
        </div>
    );
}
