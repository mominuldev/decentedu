import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Loader2, Image as ImageIcon, X } from 'lucide-react';
import { Card, Button } from '@/components/ui';
import { toApiError } from '@/lib/api';
import {
    getEvent, getEventMeta, createEvent, updateEvent,
    inputCls, type EventDetail, type EventPayload, type AssetPayload,
} from './api';
import { RichTextEditor } from './RichTextEditor';
import { MediaPicker } from './MediaPicker';
import { Field, Loading } from './PagesPanel';

const toLocalInput = (iso: string | null) => (iso ? iso.slice(0, 16) : '');
const EMPTY: EventPayload = { title: '', status: 'draft', body: '', starts_at: '', ends_at: '', location: '', terms: [] };

export default function EventFormPage() {
    const { id: idParam } = useParams<{ id: string }>();
    const id = idParam ? Number(idParam) : null;
    const navigate = useNavigate();
    const qc = useQueryClient();

    const [form, setForm] = useState<EventPayload>(EMPTY);
    const [cover, setCover] = useState<AssetPayload | null>(null);
    const [pickerOpen, setPickerOpen] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [error, setError] = useState<string | null>(null);

    const back = () => navigate('/cms?tab=events');

    const { data: meta } = useQuery({ queryKey: ['cms-event-meta'], queryFn: getEventMeta });
    const { isLoading } = useQuery({
        queryKey: ['cms-event', id],
        queryFn: async () => { const e = await getEvent(id!); hydrate(e); return e; },
        enabled: id !== null,
    });

    const hydrate = (e: EventDetail) => {
        setForm({ title: e.title, slug: e.slug, body: e.body ?? '', starts_at: toLocalInput(e.starts_at), ends_at: toLocalInput(e.ends_at), location: e.location ?? '', status: e.status, published_at: e.published_at, terms: e.terms });
        setCover(e.featured_asset);
    };

    const save = useMutation({
        mutationFn: () => { const payload: EventPayload = { ...form, ends_at: form.ends_at || null, featured_asset_id: cover?.id ?? null }; return id ? updateEvent(id, payload) : createEvent(payload); },
        onSuccess: () => { qc.invalidateQueries({ queryKey: ['cms-events'] }); back(); },
        onError: (e) => { const err = toApiError(e); setError(err.message); setErrors(err.errors ?? {}); },
    });

    const set = (patch: Partial<EventPayload>) => setForm((f) => ({ ...f, ...patch }));
    const toggleTerm = (tid: number) => set({ terms: form.terms?.includes(tid) ? form.terms.filter((x) => x !== tid) : [...(form.terms ?? []), tid] });

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <Button variant="outline" onClick={back} disabled={save.isPending}><ArrowLeft size={16} /> Back</Button>
                    <div>
                        <h1 className="text-[22px] font-bold tracking-tight text-fg">{id ? 'Edit event' : 'New event'}</h1>
                        <p className="mt-0.5 text-[13.5px] text-muted">{id ? `Editing “${form.title}”` : 'Publish an upcoming event for the public site'}</p>
                    </div>
                </div>
                <div className="flex gap-2">
                    <Button variant="outline" onClick={back} disabled={save.isPending}>Cancel</Button>
                    <Button onClick={() => save.mutate()} disabled={save.isPending}>
                        {save.isPending && <Loader2 size={16} className="animate-spin" />}
                        {save.isPending ? 'Saving…' : 'Save event'}
                    </Button>
                </div>
            </div>

            {id !== null && isLoading ? <Loading /> : (
                <Card className="p-5">
                    <div className="space-y-4">
                        {error && <p className="rounded-lg bg-rose-50 px-3 py-2 text-[13px] text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">{error}</p>}

                        <Field label="Title" error={errors.title}>
                            <input className={inputCls} value={form.title} onChange={(e) => set({ title: e.target.value })} />
                        </Field>
                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Starts at" error={errors.starts_at}>
                                <input type="datetime-local" className={inputCls} value={form.starts_at} onChange={(e) => set({ starts_at: e.target.value })} />
                            </Field>
                            <Field label="Ends at (optional)" error={errors.ends_at}>
                                <input type="datetime-local" className={inputCls} value={form.ends_at ?? ''} onChange={(e) => set({ ends_at: e.target.value })} />
                            </Field>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Location (optional)" error={errors.location}>
                                <input className={inputCls} value={form.location ?? ''} onChange={(e) => set({ location: e.target.value })} placeholder="e.g. School auditorium" />
                            </Field>
                            <Field label="Status" error={errors.status}>
                                <select className={inputCls} value={form.status} onChange={(e) => set({ status: e.target.value as EventPayload['status'] })}>
                                    {(meta?.statuses ?? []).map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                                </select>
                            </Field>
                        </div>
                        <Field label="Slug (optional)" error={errors.slug}>
                            <input className={inputCls} value={form.slug ?? ''} onChange={(e) => set({ slug: e.target.value })} placeholder="auto from title" />
                        </Field>
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
                        <Field label="Cover image (optional)">
                            {cover ? (
                                <div className="flex items-center justify-between gap-3 rounded-xl border border-border bg-surface-2 p-2">
                                    <span className="inline-flex min-w-0 items-center gap-2.5">
                                        {cover.thumb_url || cover.url
                                            ? <img src={cover.thumb_url ?? cover.url ?? ''} alt={cover.alt ?? cover.name} className="h-11 w-11 shrink-0 rounded-lg object-cover" />
                                            : <span className="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-surface text-faint"><ImageIcon size={16} /></span>}
                                        <span className="truncate text-[13px] text-fg">{cover.name}</span>
                                    </span>
                                    <button type="button" onClick={() => setCover(null)} className="shrink-0 rounded-lg p-1 text-faint hover:text-rose-500" aria-label="Remove cover"><X size={16} /></button>
                                </div>
                            ) : (
                                <Button variant="outline" onClick={() => setPickerOpen(true)}><ImageIcon size={15} /> Choose image</Button>
                            )}
                        </Field>
                        <Field label="Body (optional)">
                            <RichTextEditor value={form.body ?? ''} onChange={(html) => set({ body: html })} />
                        </Field>
                    </div>
                </Card>
            )}

            <MediaPicker open={pickerOpen} onClose={() => setPickerOpen(false)} imageOnly
                onPick={(assets) => { if (assets[0]) setCover(assets[0]); }} />
        </div>
    );
}
