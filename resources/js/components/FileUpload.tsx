import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { UploadCloud, ImageOff, X } from 'lucide-react';
import { MediaPicker } from '@/features/cms/MediaPicker';
import { assetFileUrl, getAsset, type AssetCategory, type AssetPayload } from '@/features/cms/api';

const MAX_FILE_MB = 20;

/** `value`/`onChange` carry the asset's numeric id (as a string or number), not a raw file path. */
export function FileUpload({
    label, category = 'photo', value, previewUrl, onChange,
}: {
    label: string;
    category?: AssetCategory;
    value: string | number | null;
    previewUrl?: string | null;
    onChange: (assetId: string | null, asset?: AssetPayload | null) => void;
}) {
    const [open, setOpen] = useState(false);
    const [broken, setBroken] = useState(false);
    const [localPreview, setLocalPreview] = useState<string | null>(null);
    const stringVal = value ? String(value) : null;

    const { data: assetData } = useQuery({
        queryKey: ['media-asset', stringVal],
        queryFn: () => getAsset(stringVal!),
        enabled: !!stringVal && !previewUrl && !localPreview,
    });

    useEffect(() => {
        setBroken(false);
        setLocalPreview(null);
    }, [value]);

    const activeSrc = localPreview ?? previewUrl ?? assetData?.thumb_url ?? assetData?.url ?? (stringVal ? assetFileUrl(stringVal, 'thumb') : null);

    return (
        <div>
            <label className="mb-1.5 block text-[13px] font-medium text-fg">{label}</label>
            <div className="flex items-center gap-3">
                <div className="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-xl border border-border-strong bg-surface-2 text-faint">
                    {stringVal && activeSrc && !broken ? (
                        <img
                            src={activeSrc} alt="" className="h-full w-full object-cover"
                            onError={() => setBroken(true)}
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
                                type="button" onClick={() => { setLocalPreview(null); onChange(null); }}
                                className="flex items-center gap-1 rounded-lg border border-border-strong px-2.5 py-1.5 text-[12.5px] text-muted hover:bg-surface-2 hover:text-rose-500"
                                aria-label="Remove"
                            >
                                <X size={14} />
                            </button>
                        )}
                    </div>
                    <p className="text-[11.5px] text-faint">Images, videos or documents, up to {MAX_FILE_MB}MB</p>
                </div>
            </div>

            {open && (
                <MediaPicker
                    open={open}
                    category={category}
                    initialAssetId={stringVal}
                    onClose={() => setOpen(false)}
                    onPick={(assets) => {
                        if (assets[0]) {
                            const a = assets[0];
                            setLocalPreview(a.thumb_url ?? a.url ?? null);
                            setBroken(false);
                            onChange(String(a.id), a);
                        }
                    }}
                />
            )}
        </div>
    );
}
