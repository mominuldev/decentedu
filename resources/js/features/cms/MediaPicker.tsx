import { useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2, Search, Upload, Check } from 'lucide-react';
import { Modal } from '@/components/Modal';
import { Button } from '@/components/ui';
import { cn } from '@/lib/cn';
import { pickerAssets, uploadAssets, inputCls, type AssetPayload } from './api';

/**
 * Dialog for choosing one or several assets from the media library. Supports
 * inline upload. `multiple` toggles single- vs multi-select behaviour.
 */
export function MediaPicker({
    open,
    onClose,
    onPick,
    multiple = false,
    imageOnly = true,
}: {
    open: boolean;
    onClose: () => void;
    onPick: (assets: AssetPayload[]) => void;
    multiple?: boolean;
    imageOnly?: boolean;
}) {
    const qc = useQueryClient();
    const [search, setSearch] = useState('');
    const [selected, setSelected] = useState<AssetPayload[]>([]);
    const fileRef = useRef<HTMLInputElement>(null);

    const { data: assets = [], isLoading } = useQuery({
        queryKey: ['cms-picker', search, imageOnly],
        queryFn: () => pickerAssets({ search: search || undefined, type: imageOnly ? 'image' : undefined }),
        enabled: open,
    });

    const upload = useMutation({
        mutationFn: (files: FileList) => uploadAssets(files, null),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['cms-picker'] }),
    });

    const toggle = (asset: AssetPayload) => {
        if (multiple) {
            setSelected((s) => (s.some((a) => a.id === asset.id) ? s.filter((a) => a.id !== asset.id) : [...s, asset]));
        } else {
            onPick([asset]);
            onClose();
        }
    };

    return (
        <Modal open={open} onClose={onClose} title="Media library" width="max-w-3xl"
            footer={multiple ? (
                <>
                    <Button variant="outline" onClick={onClose}>Cancel</Button>
                    <Button onClick={() => { onPick(selected); onClose(); }} disabled={selected.length === 0}>
                        Add {selected.length || ''} selected
                    </Button>
                </>
            ) : undefined}
        >
            <div className="mb-4 flex items-center gap-2">
                <div className="relative flex-1">
                    <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-faint" />
                    <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search assets…"
                        className={cn(inputCls, 'pl-9')} />
                </div>
                <input ref={fileRef} type="file" multiple hidden accept={imageOnly ? 'image/*' : undefined}
                    onChange={(e) => e.target.files?.length && upload.mutate(e.target.files)} />
                <Button variant="outline" onClick={() => fileRef.current?.click()} disabled={upload.isPending}>
                    {upload.isPending ? <Loader2 size={15} className="animate-spin" /> : <Upload size={15} />} Upload
                </Button>
            </div>

            {isLoading ? (
                <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
            ) : assets.length === 0 ? (
                <p className="py-16 text-center text-[14px] text-muted">No assets yet — upload one to get started.</p>
            ) : (
                <div className="grid max-h-[50vh] grid-cols-3 gap-2.5 overflow-y-auto sm:grid-cols-4">
                    {assets.map((a) => {
                        const isSel = selected.some((s) => s.id === a.id);
                        return (
                            <button key={a.id} onClick={() => toggle(a)} type="button"
                                className={cn('group relative aspect-square overflow-hidden rounded-xl border-2 bg-surface-2',
                                    isSel ? 'border-brand-500' : 'border-border hover:border-brand-300')}>
                                {a.thumb_url || a.url ? (
                                    <img src={a.thumb_url ?? a.url ?? ''} alt={a.alt ?? a.name} className="h-full w-full object-cover" />
                                ) : (
                                    <span className="grid h-full place-items-center px-1 text-center text-[10px] text-muted">{a.name}</span>
                                )}
                                {isSel && <span className="absolute right-1 top-1 grid h-5 w-5 place-items-center rounded-full bg-brand-600 text-white"><Check size={12} /></span>}
                            </button>
                        );
                    })}
                </div>
            )}
        </Modal>
    );
}
