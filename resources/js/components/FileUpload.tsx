import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { UploadCloud, ImageOff, X } from 'lucide-react';
import { MediaPicker } from '@/features/cms/MediaPicker';
import { assetFileUrl, getAsset, type AssetCategory, type AssetPayload } from '@/features/cms/api';

const MAX_FILE_MB = 20;

/** `value`/`onChange` carry the asset's numeric id (as a string or number), not a raw file path. */
export function FileUpload({
    label, category = 'photo', value, previewUrl, onChange, imageOnly = false, hint,
}: {
    label: string;
    category?: AssetCategory;
    value: string | number | null;
    previewUrl?: string | null;
    onChange: (assetId: string | null, asset?: AssetPayload | null) => void;
    imageOnly?: boolean;
    hint?: string;
}) {
    const qc = useQueryClient();
    const [open, setOpen] = useState(false);
    const [failedSrc, setFailedSrc] = useState<string | null>(null);
    const stringVal = value ? String(value) : null;

    // Callers holding the full asset pass `previewUrl`; the rest only know the id, so resolve it.
    // Picking seeds this same cache entry, which is what makes the new thumbnail appear at once.
    const { data: assetData } = useQuery({
        queryKey: ['media-asset', stringVal],
        queryFn: () => getAsset(stringVal!),
        enabled: !!stringVal && !previewUrl,
    });

    // Public 'cms' assets are served straight off the storage disk, so the auth-gated /file route
    // is only a valid guess for the private categories — otherwise wait for the fetched payload.
    const fallbackSrc = stringVal && category !== 'cms' ? assetFileUrl(stringVal, 'thumb') : null;
    const activeSrc = previewUrl ?? assetData?.thumb_url ?? assetData?.url ?? fallbackSrc;

    // Tracking *which* src failed (rather than a boolean) means a guess that 404s doesn't
    // permanently blank the field — the real URL, once resolved, gets its own chance to load.
    const showImage = !!stringVal && !!activeSrc && activeSrc !== failedSrc;

    return (
        <div>
            <label className="mb-1.5 block text-[13px] font-medium text-fg">{label}</label>
            <div className="flex items-center gap-3">
                <div className="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-xl border border-border-strong bg-surface-2 text-faint">
                    {showImage ? (
                        <img
                            src={activeSrc} alt="" className="h-full w-full object-cover"
                            onError={() => setFailedSrc(activeSrc)}
                        />
                    ) : <ImageOff size={20} />}
                </div>
                <div className="flex flex-col gap-1.5">
                    <div className="flex gap-1.5">
                        <button
                            type="button" onClick={() => setOpen(true)}
                            className="flex items-center gap-1.5 rounded-lg border border-border-strong px-3 py-1.5 text-[12.5px] font-medium text-fg hover:bg-surface-2"
                        >
                            <UploadCloud size={14} />
                            {stringVal ? 'Replace' : 'Upload'}
                        </button>
                        {stringVal && (
                            <button
                                type="button" onClick={() => onChange(null)}
                                className="flex items-center gap-1 rounded-lg border border-border-strong px-2.5 py-1.5 text-[12.5px] text-muted hover:bg-surface-2 hover:text-rose-500"
                                aria-label="Remove"
                            >
                                <X size={14} />
                            </button>
                        )}
                    </div>
                    <p className="text-[11.5px] text-faint">
                        {hint ?? (imageOnly ? 'Images' : 'Images, videos or documents')}, up to {MAX_FILE_MB}MB
                    </p>
                </div>
            </div>

            {open && (
                <MediaPicker
                    open={open}
                    category={category}
                    imageOnly={imageOnly}
                    initialAssetId={stringVal}
                    onClose={() => setOpen(false)}
                    onPick={(assets) => {
                        const asset = assets[0];
                        if (!asset) return;
                        qc.setQueryData(['media-asset', String(asset.id)], asset);
                        setFailedSrc(null);
                        onChange(String(asset.id), asset);
                    }}
                />
            )}
        </div>
    );
}
