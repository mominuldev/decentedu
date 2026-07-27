import { useState } from 'react';
import { Image as ImageIcon, X, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui';
import { cn } from '@/lib/cn';
import { MediaPicker } from './MediaPicker';
import { BlockEditor } from './BlockEditor';
import { inputCls, labelCls, type AssetPayload, type BlockTypeOption, type EditorBlock } from './api';

type Payload = Record<string, unknown>;
type Props = { payload: Payload; onChange: (p: Payload) => void };

/** Small helpers reused across forms. */
function Text({ label, value, onChange, ...rest }: { label: string; value: unknown; onChange: (v: string) => void } & Record<string, unknown>) {
    return (
        <div>
            <label className={labelCls}>{label}</label>
            <input className={inputCls} value={(value as string) ?? ''} onChange={(e) => onChange(e.target.value)} {...rest} />
        </div>
    );
}
function Area({ label, value, onChange, row }: { label: string; value: unknown; onChange: (v: string) => void; row?: number }) {
    return (
        <div>
            <label className={labelCls}>{label}</label>
            <textarea rows={row ?? 3} className={inputCls} value={(value as string) ?? ''} onChange={(e) => onChange(e.target.value)} />
        </div>
    );
}
function Select({ label, value, options, onChange }: { label: string; value: unknown; options: string[]; onChange: (v: string) => void }) {
    return (
        <div>
            <label className={labelCls}>{label}</label>
            <select className={inputCls} value={(value as string) ?? ''} onChange={(e) => onChange(e.target.value)}>
                {options.map((o) => <option key={o} value={o}>{o}</option>)}
            </select>
        </div>
    );
}

/** Single-asset picker with a preview stored under `previewKey` in the payload. */
function AssetField({ label, payload, idKey, previewKey, onChange }: { label: string; payload: Payload; idKey: string; previewKey: string; onChange: (p: Payload) => void }) {
    const [open, setOpen] = useState(false);
    const preview = payload[previewKey] as AssetPayload | undefined;
    return (
        <div>
            <label className={labelCls}>{label}</label>
            {preview ? (
                <div className="flex items-center gap-3 rounded-xl border border-border p-2">
                    <img src={preview.thumb_url ?? preview.url ?? ''} alt="" className="h-12 w-12 rounded-lg object-cover" />
                    <span className="flex-1 truncate text-[13px] text-fg">{preview.name}</span>
                    <button type="button" onClick={() => onChange({ ...payload, [idKey]: null, [previewKey]: null })}
                        className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500"><X size={16} /></button>
                </div>
            ) : (
                <Button variant="outline" size="sm" type="button" onClick={() => setOpen(true)}><ImageIcon size={15} /> Choose image</Button>
            )}
            <MediaPicker open={open} onClose={() => setOpen(false)}
                onPick={(a) => a[0] && onChange({ ...payload, [idKey]: a[0].id, [previewKey]: a[0] })} />
        </div>
    );
}

export function BlockFields({ type, payload, onChange, blockTypes, depth = 0 }: Props & { type: string; blockTypes: BlockTypeOption[]; depth?: number }) {
    const set = (patch: Payload) => onChange({ ...payload, ...patch });

    switch (type) {
        case 'hero':
            return (
                <div className="space-y-3">
                    <Text label="EIIN" value={payload.eiin} onChange={(v) => set({ eiin: v })} />
                    <Text label="Heading" value={payload.heading} onChange={(v) => set({ heading: v })} />
                    <Area label="Subtitle" value={payload.subtitle} onChange={(v) => set({ subtitle: v })} />
                    <div className="grid grid-cols-2 gap-3">
                        <Text label="CTA One label" value={payload.cta_one_label} onChange={(v) => set({ cta_one_label: v })} />
                        <Text label="CTA One URL" value={payload.cta_one_url} onChange={(v) => set({ cta_one_url: v })} />
                        <Text label="CTA Two label" value={payload.cta_two_label} onChange={(v) => set({ cta_two_label: v })} />
                        <Text label="CTA Two URL" value={payload.cta_two_url} onChange={(v) => set({ cta_two_url: v })} />
                    </div>
                    <AssetField label="Image" payload={payload} idKey="image_asset_id" previewKey="image_asset_id_preview" onChange={onChange} />
                    <Text label="Image caption" value={payload.image_caption} onChange={(v) => set({ image_caption: v })} />
                    <div className="grid grid-cols-2 gap-3">
                        <Text label="Batch text" value={payload.batch_text} onChange={(v) => set({ batch_text: v })} />
                        <Text label="Founding period text" value={payload.founding_period_text} onChange={(v) => set({ founding_period_text: v })} />
                    </div>
                    <CountersField payload={payload} onChange={onChange} />
                </div>
            );
        case 'page_header':
            return (
                <div className="space-y-3">
                    <Text label="Subtitle" value={payload.subtitle} onChange={(v) => set({ subtitle: v })} />
                    <Text label="Heading" value={payload.heading} onChange={(v) => set({ heading: v })} />
                    <Area label="Description" value={payload.description} onChange={(v) => set({ description: v })} />
                </div>
            );
        case 'rich_text':
            return <Area label="Content (HTML)" row={10} value={payload.content} onChange={(v) => set({ content: v })} />;
        case 'html':
            return <Area label="Custom HTML" row={10} value={payload.html} onChange={(v) => set({ html: v })} />;
        case 'image':
            return (
                <div className="space-y-3">
                    <AssetField label="Image" payload={payload} idKey="asset_id" previewKey="asset_id_preview" onChange={onChange} />
                    <Text label="Caption" value={payload.caption} onChange={(v) => set({ caption: v })} />
                    <Text label="Link URL" value={payload.link_url} onChange={(v) => set({ link_url: v })} />
                    <Select label="Alignment" value={payload.alignment ?? 'full'} options={['left', 'center', 'right', 'full']} onChange={(v) => set({ alignment: v })} />
                </div>
            );
        case 'gallery':
            return <GalleryField payload={payload} onChange={onChange} />;
        case 'video_embed':
            return (
                <div className="space-y-3">
                    <Text label="Video URL" value={payload.url} onChange={(v) => set({ url: v })} placeholder="https://youtube.com/…" />
                    <Text label="Title" value={payload.title} onChange={(v) => set({ title: v })} />
                    <Select label="Aspect ratio" value={payload.aspect_ratio ?? '16:9'} options={['16:9', '4:3', '1:1', '9:16']} onChange={(v) => set({ aspect_ratio: v })} />
                </div>
            );
        case 'cta':
            return (
                <div className="space-y-3">
                    <Text label="Heading" value={payload.heading} onChange={(v) => set({ heading: v })} />
                    <Area label="Text" value={payload.text} onChange={(v) => set({ text: v })} />
                    <div className="grid grid-cols-2 gap-3">
                        <Text label="Button label" value={payload.button_label} onChange={(v) => set({ button_label: v })} />
                        <Text label="Button URL" value={payload.button_url} onChange={(v) => set({ button_url: v })} />
                    </div>
                    <Select label="Style" value={payload.style ?? 'primary'} options={['primary', 'secondary', 'outline']} onChange={(v) => set({ style: v })} />
                </div>
            );
        case 'faq':
            return <FaqField payload={payload} onChange={onChange} />;
        case 'posts_list':
            return (
                <div className="space-y-3">
                    <Text label="Heading" value={payload.heading} onChange={(v) => set({ heading: v })} />
                    <Select label="Mode" value={payload.mode ?? 'latest'} options={['latest', 'featured', 'term']} onChange={(v) => set({ mode: v })} />
                    {payload.mode === 'term' && <Text label="Term ID" value={payload.term_id} onChange={(v) => set({ term_id: Number(v) || null })} type="number" />}
                    <Text label="Limit" value={payload.limit ?? 3} onChange={(v) => set({ limit: Number(v) || 3 })} type="number" />
                </div>
            );
        case 'divider':
            return (
                <div className="grid grid-cols-2 gap-3">
                    <Select label="Style" value={payload.style ?? 'line'} options={['line', 'space']} onChange={(v) => set({ style: v })} />
                    <Select label="Size" value={payload.size ?? 'md'} options={['sm', 'md', 'lg']} onChange={(v) => set({ size: v })} />
                </div>
            );

        case 'section':
            return (
                <div className="space-y-3">
                    <Text label="Name" value={payload.name} onChange={(v) => set({ name: v })} />
                    <Select label="HTML tag" value={payload.tag ?? 'section'} options={['div', 'section', 'article', 'aside', 'header', 'footer']} onChange={(v) => set({ tag: v })} />
                    <Text label="Background color" value={payload.background_color} onChange={(v) => set({ background_color: v })} placeholder="#ffffff" />
                    <div>
                        <label className={labelCls}>Nested blocks</label>
                        <BlockEditor
                            blocks={(payload.blocks as EditorBlock[]) ?? []}
                            blockTypes={blockTypes.filter((b) => b.type !== 'section')}
                            depth={depth + 1}
                            onChange={(blocks) => set({ blocks })}
                        />
                    </div>
                </div>
            );
        default:
            return <p className="text-[13px] text-muted">No editor for “{type}”.</p>;
    }
}

function GalleryField({ payload, onChange }: Props) {
    const [open, setOpen] = useState(false);
    const previews = (payload.asset_previews as AssetPayload[]) ?? [];
    const remove = (id: number) => {
        const ids = ((payload.asset_ids as number[]) ?? []).filter((x) => x !== id);
        onChange({ ...payload, asset_ids: ids, asset_previews: previews.filter((p) => p.id !== id) });
    };
    return (
        <div className="space-y-2">
            <label className={labelCls}>Images</label>
            <div className="flex flex-wrap gap-2">
                {previews.map((p) => (
                    <div key={p.id} className="relative h-16 w-16 overflow-hidden rounded-lg border border-border">
                        <img src={p.thumb_url ?? p.url ?? ''} alt="" className="h-full w-full object-cover" />
                        <button type="button" onClick={() => remove(p.id)} className="absolute right-0.5 top-0.5 rounded-full bg-slate-950/60 p-0.5 text-white"><X size={12} /></button>
                    </div>
                ))}
                <Button variant="outline" size="sm" type="button" onClick={() => setOpen(true)}><Plus size={15} /> Add</Button>
            </div>
            <MediaPicker open={open} onClose={() => setOpen(false)} multiple
                onPick={(assets) => {
                    const existing = (payload.asset_ids as number[]) ?? [];
                    const merged = [...previews];
                    const ids = [...existing];
                    assets.forEach((a) => { if (!ids.includes(a.id)) { ids.push(a.id); merged.push(a); } });
                    onChange({ ...payload, asset_ids: ids, asset_previews: merged });
                }} />
        </div>
    );
}

function FaqField({ payload, onChange }: Props) {
    const items = (payload.items as { question: string; answer: string }[]) ?? [];
    const setItems = (next: typeof items) => onChange({ ...payload, items: next });
    return (
        <div className="space-y-3">
            <Text label="Heading" value={payload.heading} onChange={(v) => onChange({ ...payload, heading: v })} />
            {items.map((it, i) => (
                <div key={i} className="space-y-2 rounded-xl border border-border p-3">
                    <div className="flex items-center justify-between">
                        <span className="text-[12px] font-medium text-muted">Item {i + 1}</span>
                        <button type="button" onClick={() => setItems(items.filter((_, x) => x !== i))} className="text-faint hover:text-rose-500"><Trash2 size={14} /></button>
                    </div>
                    <input className={inputCls} placeholder="Question" value={it.question} onChange={(e) => setItems(items.map((x, xi) => xi === i ? { ...x, question: e.target.value } : x))} />
                    <textarea rows={2} className={inputCls} placeholder="Answer" value={it.answer} onChange={(e) => setItems(items.map((x, xi) => xi === i ? { ...x, answer: e.target.value } : x))} />
                </div>
            ))}
            <Button variant="outline" size="sm" type="button" onClick={() => setItems([...items, { question: '', answer: '' }])}><Plus size={15} /> Add item</Button>
        </div>
    );
}

function CountersField({ payload, onChange }: Props) {
    const counters = (payload.counters as { title: string; subtitle: string }[]) ?? [];
    const setCounters = (next: typeof counters) => onChange({ ...payload, counters: next });
    return (
        <div className="space-y-2">
            <label className={labelCls}>Counters</label>
            {counters.map((it, i) => (
                <div key={i} className="space-y-2 rounded-xl border border-border p-3">
                    <div className="flex items-center justify-between">
                        <span className="text-[12px] font-medium text-muted">Counter {i + 1}</span>
                        <button type="button" onClick={() => setCounters(counters.filter((_, x) => x !== i))} className="text-faint hover:text-rose-500"><Trash2 size={14} /></button>
                    </div>
                    <input className={inputCls} placeholder="Title" value={it.title} onChange={(e) => setCounters(counters.map((x, xi) => xi === i ? { ...x, title: e.target.value } : x))} />
                    <input className={inputCls} placeholder="Subtitle" value={it.subtitle} onChange={(e) => setCounters(counters.map((x, xi) => xi === i ? { ...x, subtitle: e.target.value } : x))} />
                </div>
            ))}
            <Button variant="outline" size="sm" type="button" onClick={() => setCounters([...counters, { title: '', subtitle: '' }])}><Plus size={15} /> Add counter</Button>
        </div>
    );
}

export const cnHelper = cn;
