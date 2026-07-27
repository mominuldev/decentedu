import { useState } from 'react';
import { Image as ImageIcon, X, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui';
import { cn } from '@/lib/cn';
import { MediaPicker } from './MediaPicker';
import { BlockEditor } from './BlockEditor';
import { RichTextEditor } from './RichTextEditor';
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
function formatOptionLabel(s: string): string {
    if (!s) return '';
    return s
        .split('_')
        .map((w) => (w ? w.charAt(0).toUpperCase() + w.slice(1).toLowerCase() : ''))
        .join(' ');
}

function Select({ label, value, options, onChange }: { label: string; value: unknown; options: Array<string | { label: string; value: string }>; onChange: (v: string) => void }) {
    return (
        <div>
            <label className={labelCls}>{label}</label>
            <select className={inputCls} value={(value as string) ?? ''} onChange={(e) => onChange(e.target.value)}>
                {options.map((o) => {
                    const val = typeof o === 'string' ? o : o.value;
                    const lbl = typeof o === 'string' ? formatOptionLabel(o) : o.label;
                    return <option key={val} value={val}>{lbl}</option>;
                })}
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
        case 'heading':
            return <HeadingFields payload={payload} onChange={onChange} />;
        case 'card_list':
            return <CardListFields payload={payload} onChange={onChange} />;
        case 'notice_board':
            return <NoticeBoardFields payload={payload} onChange={onChange} />;
        case 'quote':
            return <QuoteFields payload={payload} onChange={onChange} />;
        case 'teachers':
            return <TeachersFields payload={payload} onChange={onChange} />;
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
            return <CtaFields payload={payload} onChange={onChange} />;
        case 'about':
            return <AboutFields payload={payload} onChange={onChange} />;
        case 'milestones_timeline':
            return <MilestonesTimelineFields payload={payload} onChange={onChange} />;
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

/** Simple two-tab strip used inside block forms. */
function Tabs({ tabs, active, onChange }: { tabs: { key: string; label: string }[]; active: string; onChange: (key: string) => void }) {
    return (
        <div className="flex gap-1 rounded-xl bg-surface-2 p-1">
            {tabs.map((t) => (
                <button key={t.key} type="button" onClick={() => onChange(t.key)}
                    className={cn(
                        'flex-1 rounded-lg px-3 py-1.5 text-[13px] font-medium transition-colors',
                        active === t.key ? 'bg-surface text-fg shadow-[var(--shadow-soft)]' : 'text-muted hover:text-fg',
                    )}>
                    {t.label}
                </button>
            ))}
        </div>
    );
}

function HeadingFields({ payload, onChange }: Props) {
    const [tab, setTab] = useState<'content' | 'style'>('content');
    const set = (patch: Payload) => onChange({ ...payload, ...patch });
    return (
        <div className="space-y-4">
            <Tabs tabs={[{ key: 'content', label: 'Content' }, { key: 'style', label: 'Style' }]} active={tab} onChange={(k) => setTab(k as 'content' | 'style')} />
            {tab === 'content' ? (
                <div className="space-y-3">
                    <Text label="Subtitle" value={payload.subtitle} onChange={(v) => set({ subtitle: v })} />
                    <Text label="Title" value={payload.title} onChange={(v) => set({ title: v })} />
                    <Area label="Description" value={payload.description} onChange={(v) => set({ description: v })} />
                    <div className="grid grid-cols-2 gap-3">
                        <Text label="CTA label" value={payload.cta_label} onChange={(v) => set({ cta_label: v })} />
                        <Text label="CTA URL" value={payload.cta_url} onChange={(v) => set({ cta_url: v })} />
                    </div>
                </div>
            ) : (
                <div className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <Select label="Text align" value={payload.text_align ?? 'left'} options={['left', 'center', 'right']} onChange={(v) => set({ text_align: v })} />
                        <Select label="Layout" value={payload.layout ?? 'stacked'} options={['stacked', 'split']} onChange={(v) => set({ layout: v })} />
                        <Select label="CTA variant" value={payload.cta_variant ?? 'primary'} options={['primary', 'secondary', 'outline', 'ghost']} onChange={(v) => set({ cta_variant: v })} />
                        <Select label="CTA target" value={payload.cta_target ?? 'self'} options={['self', 'blank']} onChange={(v) => set({ cta_target: v })} />
                    </div>
                </div>
            )}
        </div>
    );
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

/** Icon asset picker for card list items */
function IconAssetField({
    label,
    item,
    index,
    items,
    onChange,
}: {
    label: string;
    item: {
        icon_asset_id?: number | null;
        icon_asset_id_preview?: AssetPayload;
    };
    index: number;
    items: Array<{
        icon_asset_id?: number | null;
        icon_asset_id_preview?: AssetPayload;
        title?: string;
        description?: string;
        cta_label?: string;
        cta_url?: string;
        cta_target?: string;
        count?: string;
    }>;
    onChange: (next: Array<{
        icon_asset_id?: number | null;
        icon_asset_id_preview?: AssetPayload;
        title?: string;
        description?: string;
        cta_label?: string;
        cta_url?: string;
        cta_target?: string;
        count?: string;
    }>) => void;
}) {
    const [open, setOpen] = useState(false);
    const preview = item.icon_asset_id_preview;

    return (
        <div>
            <label className={labelCls}>{label}</label>
            {preview ? (
                <div className="flex items-center gap-3 rounded-xl border border-border p-2">
                    <img src={preview.thumb_url ?? preview.url ?? ''} alt="" className="h-12 w-12 rounded-lg object-cover" />
                    <span className="flex-1 truncate text-[13px] text-fg">{preview.name}</span>
                    <button
                        type="button"
                        onClick={() => onChange(items.map((x, xi) => xi === index ? { ...x, icon_asset_id: null, icon_asset_id_preview: undefined } : x))}
                        className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500"
                    >
                        <X size={16} />
                    </button>
                </div>
            ) : (
                <Button variant="outline" size="sm" type="button" onClick={() => setOpen(true)}>
                    <ImageIcon size={15} /> Choose icon
                </Button>
            )}
            <MediaPicker
                open={open}
                onClose={() => setOpen(false)}
                onPick={(assets) => {
                    if (assets[0]) {
                        onChange(items.map((x, xi) => xi === index ? { ...x, icon_asset_id: assets[0].id, icon_asset_id_preview: assets[0] } : x));
                    }
                }}
            />
        </div>
    );
}

function CardListFields({ payload, onChange }: Props) {
    const [tab, setTab] = useState<'content' | 'style'>('content');
    const set = (patch: Payload) => onChange({ ...payload, ...patch });

    const variation = (payload.variation as string) ?? (payload.layout as string) ?? 'variation_one';
    const isVariationFour = variation === 'variation_four' || variation === 'variation_4';
    const isVariationFive = variation === 'variation_five' || variation === 'variation_5';

    const items = (payload.items as {
        icon_asset_id?: number | null;
        icon_asset_id_preview?: AssetPayload;
        year?: string;
        title?: string;
        description?: string;
        cta_label?: string;
        cta_url?: string;
        cta_target?: string;
        count?: string;
    }[]) ?? [];

    const setItems = (next: typeof items) => set({ items: next });

    return (
        <div className="space-y-4">
            <Tabs tabs={[{ key: 'content', label: 'Content' }, { key: 'style', label: 'Style' }]} active={tab} onChange={(k) => setTab(k as 'content' | 'style')} />
            {tab === 'content' ? (
                <div className="space-y-3">
                    <Text label="Subtitle" value={payload.subtitle} onChange={(v) => set({ subtitle: v })} />
                    <Text label="Title" value={payload.title} onChange={(v) => set({ title: v })} />
                    <Area label="Description" value={payload.description} onChange={(v) => set({ description: v })} />
                    <div className="space-y-2">
                        <label className={labelCls}>Card Items</label>
                        {items.map((it, i) => (
                            <div key={i} className="space-y-2 rounded-xl border border-border p-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-[12px] font-medium text-muted">Card {i + 1}</span>
                                    <button
                                        type="button"
                                        onClick={() => setItems(items.filter((_, x) => x !== i))}
                                        className="text-faint hover:text-rose-500"
                                    >
                                        <Trash2 size={14} />
                                    </button>
                                </div>
                                {isVariationFive ? (
                                    <>
                                        <Text label="Count" value={it.count} onChange={(v) => setItems(items.map((x, xi) => xi === i ? { ...x, count: v } : x))} placeholder="e.g., 50+" />
                                        <Text label="Title" value={it.title} onChange={(v) => setItems(items.map((x, xi) => xi === i ? { ...x, title: v } : x))} />
                                        <Area label="Description" value={it.description} onChange={(v) => setItems(items.map((x, xi) => xi === i ? { ...x, description: v } : x))} row={2} />
                                    </>
                                ) : (
                                    <>
                                        <div className="grid grid-cols-2 gap-3">
                                            <IconAssetField
                                                label="Icon"
                                                item={it}
                                                index={i}
                                                items={items}
                                                onChange={setItems}
                                            />
                                            <Text label="Count" value={it.count} onChange={(v) => setItems(items.map((x, xi) => xi === i ? { ...x, count: v } : x))} placeholder="e.g., 50+" />
                                        </div>
                                        {isVariationFour && (
                                            <Text label="Year" value={it.year} onChange={(v) => setItems(items.map((x, xi) => xi === i ? { ...x, year: v } : x))} placeholder="e.g., 2024" />
                                        )}
                                        <Text label="Title" value={it.title} onChange={(v) => setItems(items.map((x, xi) => xi === i ? { ...x, title: v } : x))} />
                                        <Area label="Description" value={it.description} onChange={(v) => setItems(items.map((x, xi) => xi === i ? { ...x, description: v } : x))} row={2} />
                                        <div className="grid grid-cols-2 gap-3">
                                            <Text label="CTA Label" value={it.cta_label} onChange={(v) => setItems(items.map((x, xi) => xi === i ? { ...x, cta_label: v } : x))} />
                                            <Text label="CTA URL" value={it.cta_url} onChange={(v) => setItems(items.map((x, xi) => xi === i ? { ...x, cta_url: v } : x))} />
                                        </div>
                                        <Select
                                            label="CTA Target"
                                            value={it.cta_target ?? 'self'}
                                            options={['self', 'blank']}
                                            onChange={(v) => setItems(items.map((x, xi) => xi === i ? { ...x, cta_target: v } : x))}
                                        />
                                    </>
                                )}
                            </div>
                        ))}
                        <Button variant="outline" size="sm" type="button" onClick={() => setItems([...items, { icon_asset_id: null, icon_asset_id_preview: undefined, year: '', title: '', description: '', cta_label: '', cta_url: '', cta_target: 'self', count: '' }])}>
                            <Plus size={15} /> Add card
                        </Button>
                    </div>
                </div>
            ) : (
                <div className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <Select
                            label="Heading align"
                            value={payload.heading_align ?? 'left'}
                            options={['left', 'center', 'right']}
                            onChange={(v) => set({ heading_align: v })}
                        />
                        <Select
                            label="Content text align"
                            value={payload.content_text_align ?? 'left'}
                            options={['left', 'center', 'right']}
                            onChange={(v) => set({ content_text_align: v })}
                        />
                    </div>
                    <Select
                        label="Variation"
                        value={variation}
                        options={['variation_one', 'variation_two', 'variation_three', 'variation_four', 'variation_five']}
                        onChange={(v) => set({ variation: v, layout: v })}
                    />
                </div>
            )}
        </div>
    );
}

function NoticeBoardFields({ payload, onChange }: Props) {
    const [tab, setTab] = useState<'content' | 'style'>('content');
    const set = (patch: Payload) => onChange({ ...payload, ...patch });

    return (
        <div className="space-y-4">
            <Tabs tabs={[{ key: 'content', label: 'Content' }, { key: 'style', label: 'Style' }]} active={tab} onChange={(k) => setTab(k as 'content' | 'style')} />
            {tab === 'content' ? (
                <div className="space-y-3">
                    <Text label="Subtitle" value={payload.subtitle} onChange={(v) => set({ subtitle: v })} />
                    <Text label="Heading" value={payload.heading} onChange={(v) => set({ heading: v })} />
                    <Area label="Description" value={payload.description} onChange={(v) => set({ description: v })} />
                    <div className="grid grid-cols-2 gap-3">
                        <Text label="CTA Label" value={payload.cta_label} onChange={(v) => set({ cta_label: v })} />
                        <Text label="CTA URL" value={payload.cta_url} onChange={(v) => set({ cta_url: v })} />
                    </div>
                    <Text label="Event Box Title" value={payload.event_box_title} onChange={(v) => set({ event_box_title: v })} placeholder="e.g., Upcoming Events" />

                    <div className="rounded-xl border border-border p-3 space-y-3">
                        <h4 className="text-sm font-medium">Notices</h4>
                        <Select
                            label="Source"
                            value={payload.notices_mode ?? 'latest'}
                            options={['latest', 'important', 'selected']}
                            onChange={(v) => set({ notices_mode: v })}
                        />
                        {payload.notices_mode !== 'selected' && (
                            <Text label="Limit" value={payload.notices_limit ?? 5} onChange={(v) => set({ notices_limit: Number(v) || 5 })} type="number" min="1" max="20" />
                        )}
                        {payload.notices_mode === 'selected' && (
                            <p className="text-[13px] text-muted">Enter comma-separated Notice IDs from the CMS module</p>
                        )}
                    </div>

                    <div className="rounded-xl border border-border p-3 space-y-3">
                        <h4 className="text-sm font-medium">Events</h4>
                        <Select
                            label="Source"
                            value={payload.events_mode ?? 'upcoming'}
                            options={['upcoming', 'latest', 'selected']}
                            onChange={(v) => set({ events_mode: v })}
                        />
                        {payload.events_mode !== 'selected' && (
                            <Text label="Limit" value={payload.events_limit ?? 5} onChange={(v) => set({ events_limit: Number(v) || 5 })} type="number" min="1" max="20" />
                        )}
                        {payload.events_mode === 'selected' && (
                            <p className="text-[13px] text-muted">Enter comma-separated Event IDs from the CMS module</p>
                        )}
                    </div>
                </div>
            ) : (
                <div className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <Select label="CTA variant" value={payload.cta_variant ?? 'primary'} options={['primary', 'secondary', 'outline', 'ghost']} onChange={(v) => set({ cta_variant: v })} />
                        <Select label="CTA target" value={payload.cta_target ?? 'self'} options={['self', 'blank']} onChange={(v) => set({ cta_target: v })} />
                    </div>
                </div>
            )}
        </div>
    );
}

function QuoteFields({ payload, onChange }: Props) {
    const [tab, setTab] = useState<'content' | 'style'>('content');
    const set = (patch: Payload) => onChange({ ...payload, ...patch });

    const variant = (payload.variant as string) ?? 'default';
    const showOrganization = variant === 'full_details';

    return (
        <div className="space-y-4">
            <Tabs tabs={[{ key: 'content', label: 'Content' }, { key: 'style', label: 'Style' }]} active={tab} onChange={(k) => setTab(k as 'content' | 'style')} />
            {tab === 'content' ? (
                <div className="space-y-3">
                    <AssetField label="Image" payload={payload} idKey="image_asset_id" previewKey="image_asset_id_preview" onChange={onChange} />
                    <Text label="Image caption" value={payload.image_caption} onChange={(v) => set({ image_caption: v })} />
                    <Area label="Quote message" value={payload.quote_message} onChange={(v) => set({ quote_message: v })} row={3} />
                    <Area label="Description" value={payload.description} onChange={(v) => set({ description: v })} row={3} />
                    <div className="grid grid-cols-2 gap-3">
                        <Text label="Name" value={payload.name} onChange={(v) => set({ name: v })} />
                        <Text label="Designation" value={payload.designation} onChange={(v) => set({ designation: v })} />
                    </div>
                    {showOrganization && <Text label="Organization" value={payload.organization} onChange={(v) => set({ organization: v })} />}
                </div>
            ) : (
                <div className="space-y-3">
                    <Select
                        label="Variant"
                        value={variant}
                        options={['default', 'full_details']}
                        onChange={(v) => {
                            set({ variant: v });
                            // Clear organization when switching away from full_details
                            if (v !== 'full_details') {
                                set({ organization: null });
                            }
                        }}
                    />
                    <Select
                        label="Text align"
                        value={payload.text_align ?? 'center'}
                        options={['left', 'center', 'right']}
                        onChange={(v) => set({ text_align: v })}
                    />
                </div>
            )}
        </div>
    );
}

function TeachersFields({ payload, onChange }: Props) {
    const [tab, setTab] = useState<'content' | 'style'>('content');
    const set = (patch: Payload) => onChange({ ...payload, ...patch });

    return (
        <div className="space-y-4">
            <Tabs tabs={[{ key: 'content', label: 'Content' }, { key: 'style', label: 'Style' }]} active={tab} onChange={(k) => setTab(k as 'content' | 'style')} />
            {tab === 'content' ? (
                <div className="space-y-3">
                    <Text label="Subtitle" value={payload.subtitle} onChange={(v) => set({ subtitle: v })} />
                    <Text label="Title" value={payload.title} onChange={(v) => set({ title: v })} />
                    <Area label="Description" value={payload.description} onChange={(v) => set({ description: v })} />
                    <div className="grid grid-cols-2 gap-3">
                        <Text label="CTA Label" value={payload.cta_label} onChange={(v) => set({ cta_label: v })} />
                        <Text label="CTA URL" value={payload.cta_url} onChange={(v) => set({ cta_url: v })} />
                    </div>

                    <div className="rounded-xl border border-border p-3 space-y-3">
                        <h4 className="text-sm font-medium">Teachers</h4>
                        <Select
                            label="Source"
                            value={payload.teachers_mode ?? 'active'}
                            options={['active', 'latest', 'selected']}
                            onChange={(v) => set({ teachers_mode: v })}
                        />
                        {payload.teachers_mode !== 'selected' && (
                            <Text label="Limit" value={payload.teachers_limit ?? 12} onChange={(v) => set({ teachers_limit: Number(v) || 12 })} type="number" min="1" max="50" />
                        )}
                        {payload.teachers_mode === 'selected' && (
                            <div>
                                <label className={labelCls}>Teacher IDs</label>
                                <Text
                                    label=""
                                    value={Array.isArray(payload.teacher_ids) ? payload.teacher_ids.join(',') : ''}
                                    onChange={(v) => set({ teacher_ids: v.split(',').map(id => id.trim()).filter(id => id).map(id => Number(id)) })}
                                    placeholder="e.g., 1, 5, 12, 18"
                                />
                                <p className="text-[13px] text-muted mt-1">Enter comma-separated Teacher IDs from the HR module</p>
                            </div>
                        )}
                    </div>
                </div>
            ) : (
                <div className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <Select label="Heading align" value={payload.heading_align ?? 'left'} options={['left', 'center', 'right']} onChange={(v) => set({ heading_align: v })} />
                        <Select label="CTA target" value={payload.cta_target ?? 'self'} options={['self', 'blank']} onChange={(v) => set({ cta_target: v })} />
                    </div>
                    <Select label="Layout" value={payload.layout ?? 'grid'} options={['grid', 'list']} onChange={(v) => set({ layout: v })} />
                </div>
            )}
        </div>
    );
}

function AboutFields({ payload, onChange }: Props) {
    const [tab, setTab] = useState<'content' | 'settings'>('content');
    const set = (patch: Payload) => onChange({ ...payload, ...patch });
    const variation = (payload.variation as string) ?? 'variation_one';
    const isVariationThree = variation === 'variation_three' || variation === 'variation_3';

    const items = (payload.items as { label: string; value: string }[]) ?? [];
    const setItems = (next: typeof items) => set({ items: next });

    return (
        <div className="space-y-4">
            <Tabs
                tabs={[{ key: 'content', label: 'Content' }, { key: 'settings', label: 'Settings' }]}
                active={tab}
                onChange={(k) => setTab(k as 'content' | 'settings')}
            />
            {tab === 'content' ? (
                <div className="space-y-4">
                    <Text label="Subtitle" value={payload.subtitle} onChange={(v) => set({ subtitle: v })} />
                    <Text
                        label="Title"
                        value={payload.title ?? payload.heading}
                        onChange={(v) => set({ title: v, heading: v })}
                    />
                    <div>
                        <label className={labelCls}>Content</label>
                        <RichTextEditor
                            value={(payload.content as string) ?? ''}
                            onChange={(html) => set({ content: html })}
                        />
                    </div>

                    {isVariationThree ? (
                        <div className="space-y-3 rounded-xl border border-border p-3">
                            <label className={cn(labelCls, 'font-semibold text-fg')}>Quote Section</label>
                            <Text label="Quote Subtitle" value={payload.quote_subtitle} onChange={(v) => set({ quote_subtitle: v })} />
                            <Area label="Quote Message" value={payload.quote_message} onChange={(v) => set({ quote_message: v })} row={3} />
                            <div className="grid grid-cols-2 gap-3">
                                <Text label="Author" value={payload.author} onChange={(v) => set({ author: v })} />
                                <Text label="Designation" value={payload.designation} onChange={(v) => set({ designation: v })} />
                            </div>
                        </div>
                    ) : (
                        <>
                            <AssetField label="Image" payload={payload} idKey="image_asset_id" previewKey="image_asset_id_preview" onChange={onChange} />
                            <Text label="Image Caption" value={payload.image_caption} onChange={(v) => set({ image_caption: v })} />

                            <div className="space-y-3 rounded-xl border border-border p-3">
                                <Text label="Repeater Title" value={payload.repeater_title} onChange={(v) => set({ repeater_title: v })} />
                                <div className="space-y-2">
                                    <label className={labelCls}>Repeater Items</label>
                                    {items.map((it, i) => (
                                        <div key={i} className="space-y-2 rounded-xl border border-border bg-surface-2/30 p-2.5">
                                            <div className="flex items-center justify-between">
                                                <span className="text-[12px] font-medium text-muted">Item {i + 1}</span>
                                                <button type="button" onClick={() => setItems(items.filter((_, x) => x !== i))} className="text-faint hover:text-rose-500"><Trash2 size={14} /></button>
                                            </div>
                                            <div className="grid grid-cols-2 gap-2">
                                                <input className={inputCls} placeholder="Label" value={it.label ?? ''} onChange={(e) => setItems(items.map((x, xi) => xi === i ? { ...x, label: e.target.value } : x))} />
                                                <input className={inputCls} placeholder="Value" value={it.value ?? ''} onChange={(e) => setItems(items.map((x, xi) => xi === i ? { ...x, value: e.target.value } : x))} />
                                            </div>
                                        </div>
                                    ))}
                                    <Button variant="outline" size="sm" type="button" onClick={() => setItems([...items, { label: '', value: '' }])}><Plus size={15} /> Add item</Button>
                                </div>
                            </div>
                        </>
                    )}

                    <div className="space-y-2 rounded-xl border border-border p-3">
                        <label className={cn(labelCls, 'font-semibold text-fg')}>Call to Action (CTA)</label>
                        <div className="grid grid-cols-2 gap-3">
                            <Text
                                label="Label"
                                value={payload.cta_label}
                                onChange={(v) => set({ cta_label: v })}
                            />
                            <Text
                                label="URL"
                                value={payload.cta_url}
                                onChange={(v) => set({ cta_url: v })}
                            />
                            <Select
                                label="Style"
                                value={payload.cta_variant ?? 'primary'}
                                options={['primary', 'secondary', 'outline', 'ghost']}
                                onChange={(v) => set({ cta_variant: v })}
                            />
                            <Select
                                label="Target"
                                value={payload.cta_target ?? 'self'}
                                options={['self', 'blank']}
                                onChange={(v) => set({ cta_target: v })}
                            />
                        </div>
                    </div>
                </div>
            ) : (
                <div className="space-y-3">
                    <Select
                        label="Variation"
                        value={variation}
                        options={['variation_one', 'variation_two', 'variation_three']}
                        onChange={(v) => set({ variation: v })}
                    />
                </div>
            )}
        </div>
    );
}

function MilestonesTimelineFields({ payload, onChange }: Props) {
    const [tab, setTab] = useState<'content' | 'style'>('content');
    const set = (patch: Payload) => onChange({ ...payload, ...patch });
    const items = (payload.items as { year: string; title: string; description?: string }[]) ?? [];
    const setItems = (next: typeof items) => set({ items: next });

    return (
        <div className="space-y-4">
            <Tabs
                tabs={[{ key: 'content', label: 'Content' }, { key: 'style', label: 'Style' }]}
                active={tab}
                onChange={(k) => setTab(k as 'content' | 'style')}
            />
            {tab === 'content' ? (
                <div className="space-y-3">
                    <Text label="Subtitle" value={payload.subtitle} onChange={(v) => set({ subtitle: v })} />
                    <Text
                        label="Title"
                        value={payload.title ?? payload.heading}
                        onChange={(v) => set({ title: v, heading: v })}
                    />
                    <Area label="Description" value={payload.description} onChange={(v) => set({ description: v })} />
                    <div className="space-y-2">
                        <label className={labelCls}>Milestones</label>
                        {items.map((it, i) => (
                            <div key={i} className="space-y-2 rounded-xl border border-border bg-surface-2/30 p-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-[12px] font-medium text-muted">Milestone {i + 1}</span>
                                    <button
                                        type="button"
                                        onClick={() => setItems(items.filter((_, x) => x !== i))}
                                        className="text-faint hover:text-rose-500"
                                    >
                                        <Trash2 size={14} />
                                    </button>
                                </div>
                                <div className="grid grid-cols-3 gap-2">
                                    <div className="col-span-1">
                                        <input
                                            className={inputCls}
                                            placeholder="Year (e.g. 2020)"
                                            value={it.year ?? ''}
                                            onChange={(e) => setItems(items.map((x, xi) => xi === i ? { ...x, year: e.target.value } : x))}
                                        />
                                    </div>
                                    <div className="col-span-2">
                                        <input
                                            className={inputCls}
                                            placeholder="Milestone Title"
                                            value={it.title ?? ''}
                                            onChange={(e) => setItems(items.map((x, xi) => xi === i ? { ...x, title: e.target.value } : x))}
                                        />
                                    </div>
                                </div>
                                <textarea
                                    rows={2}
                                    className={inputCls}
                                    placeholder="Milestone Description"
                                    value={it.description ?? ''}
                                    onChange={(e) => setItems(items.map((x, xi) => xi === i ? { ...x, description: e.target.value } : x))}
                                />
                            </div>
                        ))}
                        <Button
                            variant="outline"
                            size="sm"
                            type="button"
                            onClick={() => setItems([...items, { year: '', title: '', description: '' }])}
                        >
                            <Plus size={15} /> Add milestone
                        </Button>
                    </div>
                </div>
            ) : (
                <div className="space-y-3">
                    <Select
                        label="Content Align"
                        value={payload.content_align ?? 'center'}
                        options={['left', 'center', 'right']}
                        onChange={(v) => set({ content_align: v })}
                    />
                </div>
            )}
        </div>
    );
}

function CtaFields({ payload, onChange }: Props) {
    const [tab, setTab] = useState<'content' | 'settings'>('content');
    const set = (patch: Payload) => onChange({ ...payload, ...patch });
    const variation = (payload.variation as string) ?? 'variation_one';
    const isVariationTwo = variation === 'variation_two' || variation === 'variation_2';
    const isVariationThree = variation === 'variation_three' || variation === 'variation_3';

    const items = (payload.items as { year: string; title: string }[]) ?? [];
    const setItems = (next: typeof items) => set({ items: next });

    return (
        <div className="space-y-4">
            <Tabs
                tabs={[{ key: 'content', label: 'Content' }, { key: 'settings', label: 'Settings' }]}
                active={tab}
                onChange={(k) => setTab(k as 'content' | 'settings')}
            />
            {tab === 'content' ? (
                <div className="space-y-4">
                    <Text label="Subtitle" value={payload.subtitle} onChange={(v) => set({ subtitle: v })} />
                    <Text
                        label="Title"
                        value={payload.title ?? payload.heading}
                        onChange={(v) => set({ title: v, heading: v })}
                    />
                    <Area
                        label="Description"
                        value={payload.description ?? payload.text}
                        onChange={(v) => set({ description: v, text: v })}
                    />

                    {isVariationThree ? (
                        <div className="space-y-3 rounded-xl border border-border p-3">
                            <label className={cn(labelCls, 'font-semibold text-fg')}>Quote & Author</label>
                            <Area
                                label="Quote Message"
                                value={payload.quote_message}
                                onChange={(v) => set({ quote_message: v })}
                                row={3}
                            />
                            <AssetField
                                label="Author Image"
                                payload={payload}
                                idKey="author_image_asset_id"
                                previewKey="author_image_asset_id_preview"
                                onChange={onChange}
                            />
                            <div className="grid grid-cols-2 gap-3">
                                <Text
                                    label="Author Name"
                                    value={payload.author_name ?? payload.name}
                                    onChange={(v) => set({ author_name: v, name: v })}
                                />
                                <Text
                                    label="Designation"
                                    value={payload.author_designation ?? payload.designation}
                                    onChange={(v) => set({ author_designation: v, designation: v })}
                                />
                            </div>
                            <Area
                                label="Disclaimer"
                                value={payload.disclaimer}
                                onChange={(v) => set({ disclaimer: v })}
                                row={2}
                            />
                        </div>
                    ) : isVariationTwo ? (
                        <div className="space-y-2">
                            <label className={labelCls}>Timeline Repeater</label>
                            {items.map((it, i) => (
                                <div key={i} className="space-y-2 rounded-xl border border-border bg-surface-2/30 p-3">
                                    <div className="flex items-center justify-between">
                                        <span className="text-[12px] font-medium text-muted">Item {i + 1}</span>
                                        <button
                                            type="button"
                                            onClick={() => setItems(items.filter((_, x) => x !== i))}
                                            className="text-faint hover:text-rose-500"
                                        >
                                            <Trash2 size={14} />
                                        </button>
                                    </div>
                                    <div className="grid grid-cols-3 gap-2">
                                        <div className="col-span-1">
                                            <input
                                                className={inputCls}
                                                placeholder="Year (e.g. 2020)"
                                                value={it.year ?? ''}
                                                onChange={(e) => setItems(items.map((x, xi) => xi === i ? { ...x, year: e.target.value } : x))}
                                            />
                                        </div>
                                        <div className="col-span-2">
                                            <input
                                                className={inputCls}
                                                placeholder="Title"
                                                value={it.title ?? ''}
                                                onChange={(e) => setItems(items.map((x, xi) => xi === i ? { ...x, title: e.target.value } : x))}
                                            />
                                        </div>
                                    </div>
                                </div>
                            ))}
                            <Button
                                variant="outline"
                                size="sm"
                                type="button"
                                onClick={() => setItems([...items, { year: '', title: '' }])}
                            >
                                <Plus size={15} /> Add item
                            </Button>
                        </div>
                    ) : (
                        <>
                            <div className="space-y-2 rounded-xl border border-border p-3">
                                <label className={cn(labelCls, 'font-semibold text-fg')}>CTA Primary</label>
                                <div className="grid grid-cols-2 gap-3">
                                    <Text
                                        label="Label"
                                        value={payload.cta_primary_label ?? payload.button_label}
                                        onChange={(v) => set({ cta_primary_label: v, button_label: v })}
                                    />
                                    <Text
                                        label="URL"
                                        value={payload.cta_primary_url ?? payload.button_url}
                                        onChange={(v) => set({ cta_primary_url: v, button_url: v })}
                                    />
                                    <Select
                                        label="Style"
                                        value={payload.cta_primary_variant ?? payload.style ?? 'primary'}
                                        options={['primary', 'secondary', 'outline', 'ghost']}
                                        onChange={(v) => set({ cta_primary_variant: v, style: v })}
                                    />
                                    <Select
                                        label="Target"
                                        value={payload.cta_primary_target ?? payload.button_target ?? 'self'}
                                        options={['self', 'blank']}
                                        onChange={(v) => set({ cta_primary_target: v, button_target: v })}
                                    />
                                </div>
                            </div>
                            <div className="space-y-2 rounded-xl border border-border p-3">
                                <label className={cn(labelCls, 'font-semibold text-fg')}>CTA Secondary</label>
                                <div className="grid grid-cols-2 gap-3">
                                    <Text
                                        label="Label"
                                        value={payload.cta_secondary_label}
                                        onChange={(v) => set({ cta_secondary_label: v })}
                                    />
                                    <Text
                                        label="URL"
                                        value={payload.cta_secondary_url}
                                        onChange={(v) => set({ cta_secondary_url: v })}
                                    />
                                    <Select
                                        label="Style"
                                        value={payload.cta_secondary_variant ?? 'secondary'}
                                        options={['primary', 'secondary', 'outline', 'ghost']}
                                        onChange={(v) => set({ cta_secondary_variant: v })}
                                    />
                                    <Select
                                        label="Target"
                                        value={payload.cta_secondary_target ?? 'self'}
                                        options={['self', 'blank']}
                                        onChange={(v) => set({ cta_secondary_target: v })}
                                    />
                                </div>
                            </div>
                        </>
                    )}
                </div>
            ) : (
                <div className="space-y-3">
                    <Select
                        label="Variation"
                        value={variation}
                        options={[
                            { label: 'Variation One', value: 'variation_one' },
                            { label: 'Variation Two', value: 'variation_two' },
                            { label: 'Variation Three', value: 'variation_three' },
                        ]}
                        onChange={(v) => set({ variation: v })}
                    />
                </div>
            )}
        </div>
    );
}

export const cnHelper = cn;
