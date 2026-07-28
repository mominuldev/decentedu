import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Loader2, Paperclip, X } from 'lucide-react';
import { Card, Button } from '@/components/ui';
import { toApiError } from '@/lib/api';
import {
    getNotice, getNoticeMeta, createNotice, updateNotice,
    inputCls, type NoticeDetail, type NoticePayload, type AssetPayload,
} from './api';
import { RichTextEditor } from './RichTextEditor';
import { MediaPicker } from './MediaPicker';
import { Field, Loading } from './PagesPanel';
import { useToast } from '@/components/Toast';

const today = () => new Date().toISOString().slice(0, 10);
const EMPTY: NoticePayload = { title: '', status: 'draft', body: '', notice_date: today(), is_important: false, terms: [] };

export default function NoticeFormPage() {
    const { slug: slugParam, id: idParam } = useParams<{ slug?: string; id?: string }>();
    const slugOrId = slugParam || idParam || null;
    const [noticeId, setNoticeId] = useState<number | null>(null);
    const navigate = useNavigate();
    const qc = useQueryClient();
    const toast = useToast();

    const [form, setForm] = useState<NoticePayload>(EMPTY);
    const [attachment, setAttachment] = useState<AssetPayload | null>(null);
    const [pickerOpen, setPickerOpen] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [error, setError] = useState<string | null>(null);

    const back = () => navigate('/cms?tab=notices');

    const { data: meta } = useQuery({ queryKey: ['cms-notice-meta'], queryFn: getNoticeMeta });
    const { isLoading } = useQuery({
        queryKey: ['cms-notice', slugOrId],
        queryFn: async () => { const n = await getNotice(slugOrId!); setNoticeId(n.id); hydrate(n); return n; },
        enabled: slugOrId !== null,
    });

    const hydrate = (n: NoticeDetail) => {
        setForm({ title: n.title, slug: n.slug, body: n.body ?? '', notice_date: n.notice_date ?? today(), is_important: n.is_important, status: n.status, published_at: n.published_at, attachment_asset_id: n.attachment_asset_id, terms: n.terms });
        setAttachment(n.attachment);
    };

    const save = useMutation({
        mutationFn: () => {
            const payload: NoticePayload = { ...form, attachment_asset_id: attachment?.id ?? null };
            const target = noticeId ?? slugOrId;
            return target ? updateNotice(target, payload) : createNotice(payload);
        },
        onSuccess: () => { 
            qc.invalidateQueries({ queryKey: ['cms-notices'] }); 
            toast.success('Notice saved successfully');
            back(); 
        },
        onError: (e) => { 
            const err = toApiError(e); 
            setError(err.message); 
            setErrors(err.errors ?? {}); 
            toast.error(err.message || 'Could not save notice');
        },
    });

    const set = (patch: Partial<NoticePayload>) => setForm((f) => ({ ...f, ...patch }));
    const toggleTerm = (tid: number) => set({ terms: form.terms?.includes(tid) ? form.terms.filter((x) => x !== tid) : [...(form.terms ?? []), tid] });

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <Button variant="outline" onClick={back} disabled={save.isPending}><ArrowLeft size={16} /> Back</Button>
                    <div>
                        <h1 className="text-[22px] font-bold tracking-tight text-fg">{slugOrId ? 'Edit notice' : 'New notice'}</h1>
                        <p className="mt-0.5 text-[13.5px] text-muted">{slugOrId ? `Editing “${form.title}”` : 'Publish a dated notice, with an optional PDF/Excel download'}</p>
                    </div>
                </div>
                <div className="flex gap-2">
                    <Button variant="outline" onClick={back} disabled={save.isPending}>Cancel</Button>
                    <Button onClick={() => save.mutate()} disabled={save.isPending}>
                        {save.isPending && <Loader2 size={16} className="animate-spin" />}
                        {save.isPending ? 'Saving…' : 'Save notice'}
                    </Button>
                </div>
            </div>

            {slugOrId !== null && isLoading ? <Loading /> : (
                <Card className="p-5">
                    <div className="space-y-4">
                        {error && <p className="rounded-lg bg-rose-50 px-3 py-2 text-[13px] text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">{error}</p>}

                        <Field label="Title" error={errors.title}>
                            <input className={inputCls} value={form.title} onChange={(e) => set({ title: e.target.value })} />
                        </Field>
                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Notice date" error={errors.notice_date}>
                                <input type="date" className={inputCls} value={form.notice_date} onChange={(e) => set({ notice_date: e.target.value })} />
                            </Field>
                            <Field label="Status" error={errors.status}>
                                <select className={inputCls} value={form.status} onChange={(e) => set({ status: e.target.value as NoticePayload['status'] })}>
                                    {(meta?.statuses ?? []).map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                                </select>
                            </Field>
                        </div>
                        <Field label="Slug (optional)" error={errors.slug}>
                            <input className={inputCls} value={form.slug ?? ''} onChange={(e) => set({ slug: e.target.value })} placeholder="auto from title" />
                        </Field>
                        <label className="flex items-center gap-2 text-[13px] text-fg">
                            <input type="checkbox" checked={!!form.is_important} onChange={(e) => set({ is_important: e.target.checked })} /> Mark as important
                        </label>
                        <Field label="Category">
                            <div className="flex flex-wrap gap-1.5">
                                {(meta?.terms ?? []).map((t) => (
                                    <button key={t.id} type="button" onClick={() => toggleTerm(t.id)}
                                        className={`rounded-full px-3 py-1 text-[12.5px] ring-1 ring-inset ${form.terms?.includes(t.id) ? 'bg-brand-600 text-white ring-brand-600' : 'bg-surface-2 text-muted ring-border'}`}>
                                        {t.name}
                                    </button>
                                ))}
                                {(meta?.terms ?? []).length === 0 && <span className="text-[12.5px] text-muted">No categories — create a taxonomy first under the Taxonomies tab.</span>}
                            </div>
                        </Field>
                        <Field label="Attachment (PDF / Excel)" error={errors.attachment_asset_id}>
                            {attachment ? (
                                <div className="flex items-center justify-between gap-3 rounded-xl border border-border bg-surface-2 px-3.5 py-2.5">
                                    <span className="inline-flex min-w-0 items-center gap-2 text-[13px] text-fg">
                                        <Paperclip size={15} className="shrink-0 text-faint" />
                                        <span className="truncate">{attachment.name}</span>
                                    </span>
                                    <button type="button" onClick={() => setAttachment(null)} className="shrink-0 rounded-lg p-1 text-faint hover:text-rose-500" aria-label="Remove attachment"><X size={16} /></button>
                                </div>
                            ) : (
                                <Button variant="outline" onClick={() => setPickerOpen(true)}><Paperclip size={15} /> Choose file</Button>
                            )}
                        </Field>
                        <Field label="Body (optional)">
                            <RichTextEditor value={form.body ?? ''} onChange={(html) => set({ body: html })} />
                        </Field>
                    </div>
                </Card>
            )}

            <MediaPicker open={pickerOpen} onClose={() => setPickerOpen(false)} imageOnly={false}
                onPick={(assets) => { if (assets[0]) setAttachment(assets[0]); }} />
        </div>
    );
}
