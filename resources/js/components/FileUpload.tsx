import { useRef, useState } from 'react';
import { Upload, Loader2, X, ImageOff } from 'lucide-react';
import { uploadFile, uploadUrl, type UploadCategory } from '@/lib/uploads';
import { toApiError } from '@/lib/api';

export function FileUpload({
    label, category, value, onChange,
}: {
    label: string;
    category: UploadCategory;
    value: string | null;
    onChange: (path: string | null) => void;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function onPick(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        e.target.value = '';
        if (!file) return;
        setBusy(true);
        setError(null);
        try {
            const path = await uploadFile(file, category);
            onChange(path);
        } catch (err) {
            setError(toApiError(err).message);
        } finally {
            setBusy(false);
        }
    }

    return (
        <div>
            <label className="mb-1.5 block text-[13px] font-medium text-fg">{label}</label>
            <div className="flex items-center gap-3">
                <div className="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-xl border border-border-strong bg-surface-2 text-faint">
                    {value ? <img src={uploadUrl(value)} alt="" className="h-full w-full object-cover" /> : <ImageOff size={20} />}
                </div>
                <div className="flex flex-col gap-1.5">
                    <div className="flex gap-1.5">
                        <button
                            type="button" onClick={() => inputRef.current?.click()} disabled={busy}
                            className="flex items-center gap-1.5 rounded-lg border border-border-strong px-3 py-1.5 text-[12.5px] font-medium text-fg hover:bg-surface-2 disabled:opacity-60"
                        >
                            {busy ? <Loader2 size={14} className="animate-spin" /> : <Upload size={14} />}
                            {value ? 'Replace' : 'Upload'}
                        </button>
                        {value && (
                            <button
                                type="button" onClick={() => onChange(null)}
                                className="flex items-center gap-1 rounded-lg border border-border-strong px-2.5 py-1.5 text-[12.5px] text-muted hover:bg-surface-2 hover:text-rose-500"
                                aria-label="Remove"
                            >
                                <X size={14} />
                            </button>
                        )}
                    </div>
                    <p className="text-[11.5px] text-faint">JPG, PNG or WebP, up to 2MB</p>
                </div>
                <input ref={inputRef} type="file" accept="image/*" className="hidden" onChange={onPick} />
            </div>
            {error && <p className="mt-1.5 text-[12px] text-rose-500">{error}</p>}
        </div>
    );
}
