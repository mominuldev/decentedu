import { useState } from 'react';
import { Image as ImageIcon, X } from 'lucide-react';
import { Button } from '@/components/ui';
import { MediaPicker } from './MediaPicker';
import { inputCls, labelCls, type AssetPayload, type SeoData } from './api';

const ROBOTS = ['index,follow', 'index,nofollow', 'noindex,follow', 'noindex,nofollow'];

export function SeoFields({
    value,
    ogImage,
    onChange,
}: {
    value: SeoData;
    ogImage: AssetPayload | null;
    onChange: (seo: SeoData, ogImage: AssetPayload | null) => void;
}) {
    const [picking, setPicking] = useState(false);
    const set = (patch: Partial<SeoData>) => onChange({ ...value, ...patch }, ogImage);

    return (
        <div className="space-y-4">
            <div>
                <label className={labelCls}>Meta title</label>
                <input className={inputCls} value={value.meta_title ?? ''} onChange={(e) => set({ meta_title: e.target.value })} />
            </div>
            <div>
                <label className={labelCls}>Meta description</label>
                <textarea rows={2} className={inputCls} value={value.meta_description ?? ''} onChange={(e) => set({ meta_description: e.target.value })} />
            </div>
            <div className="grid grid-cols-2 gap-3">
                <div>
                    <label className={labelCls}>Canonical URL</label>
                    <input className={inputCls} value={value.canonical_url ?? ''} onChange={(e) => set({ canonical_url: e.target.value })} placeholder="https://…" />
                </div>
                <div>
                    <label className={labelCls}>Robots</label>
                    <select className={inputCls} value={value.robots ?? ''} onChange={(e) => set({ robots: e.target.value || null })}>
                        <option value="">Default (index,follow)</option>
                        {ROBOTS.map((r) => <option key={r} value={r}>{r}</option>)}
                    </select>
                </div>
            </div>
            <div>
                <label className={labelCls}>OG title</label>
                <input className={inputCls} value={value.og_title ?? ''} onChange={(e) => set({ og_title: e.target.value })} />
            </div>
            <div>
                <label className={labelCls}>OG description</label>
                <textarea rows={2} className={inputCls} value={value.og_description ?? ''} onChange={(e) => set({ og_description: e.target.value })} />
            </div>
            <div>
                <label className={labelCls}>OG image</label>
                {ogImage ? (
                    <div className="flex items-center gap-3 rounded-xl border border-border p-2">
                        <img src={ogImage.thumb_url ?? ogImage.url ?? ''} alt="" className="h-12 w-12 rounded-lg object-cover" />
                        <span className="flex-1 truncate text-[13px] text-fg">{ogImage.name}</span>
                        <button type="button" onClick={() => onChange({ ...value, og_image_asset_id: null }, null)}
                            className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500"><X size={16} /></button>
                    </div>
                ) : (
                    <Button variant="outline" size="sm" type="button" onClick={() => setPicking(true)}><ImageIcon size={15} /> Choose image</Button>
                )}
            </div>

            <MediaPicker open={picking} onClose={() => setPicking(false)}
                onPick={(a) => a[0] && onChange({ ...value, og_image_asset_id: a[0].id }, a[0])} />
        </div>
    );
}
