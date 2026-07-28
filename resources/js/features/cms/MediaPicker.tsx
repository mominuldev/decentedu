import { useEffect, useRef, useState, type DragEvent } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    UploadCloud, Loader2, FileUp, Search, Check, Video, FileText, Trash2, Pencil,
} from 'lucide-react';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { Button } from '@/components/ui';
import { cn } from '@/lib/cn';
import {
    pickerAssets, uploadAssets, deleteAsset, updateAsset, getAsset, inputCls,
    type AssetCategory, type AssetPayload,
} from './api';
import { Field } from './PagesPanel';
import { formatBytes } from '@/lib/format';
import { toApiError } from '@/lib/api';

const MAX_FILE_MB = 20;
type MediaType = 'image' | 'video' | 'document';
const TYPE_OPTIONS: { value: 'all' | MediaType; label: string }[] = [
    { value: 'all', label: 'All Types' },
    { value: 'image', label: 'Image' },
    { value: 'video', label: 'Video' },
    { value: 'document', label: 'Document' },
];

function bucketOf(mime: string | null): MediaType {
    if (mime?.startsWith('video/')) return 'video';
    if (mime?.startsWith('image/')) return 'image';
    return 'document';
}

type Tab = 'browse' | 'upload';

/**
 * Dialog for choosing one or several assets from the media library. Supports
 * inline upload, drag and drop, replacing existing asset, searching, and filtering.
 */
export function MediaPicker({
    open,
    onClose,
    onPick,
    multiple = false,
    imageOnly = false,
    category = 'cms',
    initialAssetId = null,
}: {
    open: boolean;
    onClose: () => void;
    onPick: (assets: AssetPayload[]) => void;
    multiple?: boolean;
    imageOnly?: boolean;
    category?: AssetCategory;
    initialAssetId?: string | number | null;
}) {
    const qc = useQueryClient();
    const [search, setSearch] = useState('');
    const [typeFilter, setTypeFilter] = useState<'all' | MediaType>(imageOnly ? 'image' : 'all');
    const [selected, setSelected] = useState<AssetPayload[]>([]);
    const [deletingAsset, setDeletingAsset] = useState<AssetPayload | null>(null);
    const [editingAsset, setEditingAsset] = useState<AssetPayload | null>(null);

    const { data: library = [], isLoading } = useQuery({
        queryKey: ['media-picker', category, search, typeFilter, imageOnly],
        queryFn: () => pickerAssets({
            category,
            search: search || undefined,
            type: typeFilter === 'all' ? (imageOnly ? 'image' : undefined) : typeFilter,
        }),
        enabled: open,
    });

    const [tab, setTab] = useState<Tab>('browse');

    // Opening the picker on an already-set field (the "Replace" button) shows the
    // existing asset pre-selected in Browse Library, ready to keep or swap.
    const { data: currentAsset } = useQuery({
        queryKey: ['media-asset', initialAssetId],
        queryFn: () => getAsset(initialAssetId!),
        enabled: open && !!initialAssetId,
    });

    const preselectedRef = useRef(false);
    useEffect(() => {
        if (open && currentAsset && !preselectedRef.current) {
            preselectedRef.current = true;
            setSelected([currentAsset]);
        }
        if (!open) {
            preselectedRef.current = false;
            setSelected([]);
        }
    }, [open, currentAsset]);

    const deleteMutation = useMutation({
        mutationFn: (id: number) => deleteAsset(id),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['media-picker', category] });
            if (deletingAsset) {
                setSelected((s) => s.filter((a) => a.id !== deletingAsset.id));
            }
            setDeletingAsset(null);
        },
    });

    const toggle = (asset: AssetPayload) => {
        if (multiple) {
            setSelected((s) => (s.some((a) => a.id === asset.id) ? s.filter((a) => a.id !== asset.id) : [...s, asset]));
        } else {
            setSelected([asset]);
        }
    };

    if (!open) return null;

    const singleSelected = !multiple && selected.length > 0 ? selected[0] : null;

    return (
        <>
            <Modal
                open={open}
                onClose={onClose}
                title="Select Media"
                width="max-w-3xl"
                footer={
                    <>
                        {singleSelected && (
                            <div className="mr-auto flex gap-2">
                                <Button variant="outline" onClick={() => setEditingAsset(singleSelected)}>
                                    <Pencil size={14} /> Edit
                                </Button>
                                <Button
                                    variant="outline"
                                    className="text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10"
                                    onClick={() => setDeletingAsset(singleSelected)}
                                >
                                    <Trash2 size={14} /> Delete
                                </Button>
                            </div>
                        )}
                        <Button variant="outline" onClick={onClose}>Cancel</Button>
                        <Button
                            onClick={() => {
                                onPick(selected);
                                onClose();
                            }}
                            disabled={selected.length === 0}
                        >
                            {multiple ? `Add ${selected.length || ''} selected` : 'Select'}
                        </Button>
                    </>
                }
            >
                <div className="-mt-1 mb-4 flex items-center gap-5 border-b border-border">
                    {(['browse', 'upload'] as Tab[]).map((t) => (
                        <button
                            key={t} type="button" onClick={() => setTab(t)}
                            className={cn(
                                'border-b-2 pb-2.5 text-[12.5px] font-semibold uppercase tracking-wide transition-colors',
                                tab === t ? 'border-brand-600 text-brand-600' : 'border-transparent text-faint hover:text-fg',
                            )}
                        >
                            {t === 'browse' ? 'Browse Library' : 'Upload Files'}
                        </button>
                    ))}
                </div>

                {tab === 'browse' ? (
                    <BrowseLibrary
                        items={library}
                        isLoading={isLoading}
                        search={search}
                        onSearch={setSearch}
                        typeFilter={typeFilter}
                        onTypeFilter={setTypeFilter}
                        selectedIds={selected.map((s) => s.id)}
                        onPick={toggle}
                        onEdit={setEditingAsset}
                        onDelete={setDeletingAsset}
                    />
                ) : (
                    <UploadFiles
                        category={category}
                        multiple={multiple}
                        onUploaded={(newAssets) => {
                            if (multiple) {
                                setSelected((s) => [...s, ...newAssets]);
                            } else if (newAssets[0]) {
                                setSelected([newAssets[0]]);
                            }
                            qc.invalidateQueries({ queryKey: ['media-picker', category] });
                            setTab('browse');
                        }}
                    />
                )}
            </Modal>

            <ConfirmDialog
                open={!!deletingAsset}
                onClose={() => setDeletingAsset(null)}
                onConfirm={() => deletingAsset && deleteMutation.mutate(deletingAsset.id)}
                busy={deleteMutation.isPending}
                title="Delete asset"
                message={`Delete "${deletingAsset?.name}"? This cannot be undone.`}
            />

            {editingAsset && (
                <EditAssetModal
                    asset={editingAsset}
                    onClose={() => setEditingAsset(null)}
                    onSaved={(updated) => {
                        qc.invalidateQueries({ queryKey: ['media-picker', category] });
                        setSelected((s) => s.map((a) => (a.id === updated.id ? updated : a)));
                        setEditingAsset(null);
                    }}
                />
            )}
        </>
    );
}

function EditAssetModal({
    asset, onClose, onSaved,
}: {
    asset: AssetPayload;
    onClose: () => void;
    onSaved: (asset: AssetPayload) => void;
}) {
    const [form, setForm] = useState({ name: asset.name, alt_text: asset.alt ?? '', caption: asset.caption ?? '' });
    const [error, setError] = useState<string | null>(null);
    const save = useMutation({
        mutationFn: () => updateAsset(asset.id, form),
        onSuccess: onSaved,
        onError: (e) => setError(toApiError(e).message),
    });
    const set = (p: Partial<typeof form>) => setForm((f) => ({ ...f, ...p }));

    return (
        <Modal
            open
            onClose={onClose}
            title="Edit asset"
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>Cancel</Button>
                    <Button onClick={() => save.mutate()} disabled={save.isPending}>
                        {save.isPending ? <Loader2 size={16} className="animate-spin" /> : null} Save
                    </Button>
                </>
            }
        >
            <div className="space-y-4">
                {error && <p className="text-[13px] text-rose-600">{error}</p>}
                {(asset.thumb_url || asset.url) && (
                    <img src={asset.preview_url ?? asset.thumb_url ?? asset.url ?? ''} alt="" className="max-h-40 rounded-xl object-contain" />
                )}
                <Field label="Name"><input className={inputCls} value={form.name} onChange={(e) => set({ name: e.target.value })} /></Field>
                <Field label="Alt text"><input className={inputCls} value={form.alt_text} onChange={(e) => set({ alt_text: e.target.value })} /></Field>
                <Field label="Caption"><input className={inputCls} value={form.caption} onChange={(e) => set({ caption: e.target.value })} /></Field>
            </div>
        </Modal>
    );
}

function TypeThumb({ type, size = 22 }: { type: MediaType; size?: number }) {
    if (type === 'video') return <Video size={size} />;
    if (type === 'document') return <FileText size={size} />;
    return null;
}

function BrowseLibrary({
    items, isLoading, search, onSearch, typeFilter, onTypeFilter, selectedIds, onPick, onEdit, onDelete,
}: {
    items: AssetPayload[];
    isLoading: boolean;
    search: string;
    onSearch: (v: string) => void;
    typeFilter: 'all' | MediaType;
    onTypeFilter: (v: 'all' | MediaType) => void;
    selectedIds: number[];
    onPick: (item: AssetPayload) => void;
    onEdit: (item: AssetPayload) => void;
    onDelete: (item: AssetPayload) => void;
}) {
    return (
        <div>
            <div className="mb-4 flex items-center gap-2">
                <div className="relative flex-1">
                    <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-faint" />
                    <input
                        value={search} onChange={(e) => onSearch(e.target.value)} placeholder="Search media…"
                        className="w-full rounded-xl border border-border-strong bg-surface px-3 py-2 pl-9 text-[13px] outline-none focus:border-brand-500"
                    />
                </div>
                <select
                    value={typeFilter} onChange={(e) => onTypeFilter(e.target.value as 'all' | MediaType)}
                    className="rounded-xl border border-border-strong bg-surface px-3 py-2 text-[13px] outline-none focus:border-brand-500"
                >
                    {TYPE_OPTIONS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                </select>
            </div>

            {isLoading ? (
                <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
            ) : items.length === 0 ? (
                <p className="py-16 text-center text-[14px] text-muted">
                    {search || typeFilter !== 'all' ? 'No files match your filters.' : 'No previous uploads yet — switch to "Upload Files" to add one.'}
                </p>
            ) : (
                <div className="grid max-h-[46vh] grid-cols-3 gap-3 overflow-y-auto pr-1 sm:grid-cols-4 md:grid-cols-6">
                    {items.map((item) => {
                        const isSel = selectedIds.includes(item.id);
                        const type = bucketOf(item.mime_type);
                        return (
                            <button
                                key={item.id} type="button" onClick={() => onPick(item)}
                                className={cn(
                                    'group overflow-hidden rounded-xl border-2 bg-surface-2 text-left',
                                    isSel ? 'border-brand-500' : 'border-border hover:border-brand-300',
                                )}
                            >
                                <div className="relative flex aspect-square items-center justify-center">
                                    {item.thumb_url || item.url ? (
                                        <img src={item.thumb_url ?? item.url ?? ''} alt={item.name} className="h-full w-full object-cover" />
                                    ) : (
                                        <span className="text-faint"><TypeThumb type={type} size={26} /></span>
                                    )}
                                    {isSel && (
                                        <span className="absolute right-1.5 top-1.5 grid h-5 w-5 place-items-center rounded-full bg-brand-600 text-white">
                                            <Check size={12} />
                                        </span>
                                    )}
                                    <span className="absolute left-1.5 top-1.5 flex gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                        <span
                                            role="button" tabIndex={0}
                                            onClick={(e) => { e.stopPropagation(); onEdit(item); }}
                                            className="grid h-5 w-5 place-items-center rounded-full bg-slate-950/60 text-white hover:bg-brand-600"
                                            aria-label="Edit"
                                        >
                                            <Pencil size={11} />
                                        </span>
                                        <span
                                            role="button" tabIndex={0}
                                            onClick={(e) => { e.stopPropagation(); onDelete(item); }}
                                            className="grid h-5 w-5 place-items-center rounded-full bg-slate-950/60 text-white hover:bg-rose-600"
                                            aria-label="Delete"
                                        >
                                            <Trash2 size={11} />
                                        </span>
                                    </span>
                                </div>
                                <p className="truncate px-2 pt-1.5 text-[11.5px] text-fg">{item.name}</p>
                                <p className="truncate px-2 pb-1.5 text-[11px] text-faint">{formatBytes(item.size ?? 0)}</p>
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

function UploadFiles({
    category, multiple, onUploaded,
}: {
    category: AssetCategory;
    multiple: boolean;
    onUploaded: (assets: AssetPayload[]) => void;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [dragOver, setDragOver] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function handleFiles(files: FileList | File[] | undefined) {
        if (!files || files.length === 0) return;
        setError(null);
        setUploading(true);
        try {
            const uploaded = await uploadAssets(files, null, category);
            onUploaded(uploaded);
        } catch (err) {
            setError(toApiError(err).message);
        } finally {
            setUploading(false);
        }
    }

    function onDrop(e: DragEvent<HTMLDivElement>) {
        e.preventDefault();
        setDragOver(false);
        if (e.dataTransfer.files?.length) {
            handleFiles(e.dataTransfer.files);
        }
    }

    return (
        <div>
            <div
                onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
                onDragLeave={() => setDragOver(false)}
                onDrop={onDrop}
                className={cn(
                    'flex min-h-[260px] flex-col items-center justify-center rounded-2xl border-2 border-dashed px-6 py-10 text-center transition-colors',
                    dragOver ? 'border-brand-500 bg-brand-50 dark:bg-brand-500/10' : 'border-border-strong bg-surface-2',
                )}
            >
                {uploading ? (
                    <>
                        <Loader2 size={36} className="animate-spin text-brand-600" />
                        <p className="mt-3 text-[14px] font-medium text-fg">Uploading…</p>
                    </>
                ) : (
                    <>
                        <div className="grid h-14 w-14 place-items-center rounded-full bg-brand-100 text-brand-600 dark:bg-brand-500/15">
                            <UploadCloud size={26} />
                        </div>
                        <p className="mt-4 text-[15px] font-semibold text-fg">Drop files here</p>
                        <p className="mt-1 text-[13px] text-faint">or</p>
                        <Button className="mt-3" onClick={() => inputRef.current?.click()}>
                            <FileUp size={15} /> Choose files
                        </Button>
                        <p className="mt-4 text-[11.5px] text-faint">Maximum file size: {MAX_FILE_MB}MB</p>
                    </>
                )}
                <input
                    ref={inputRef} type="file" multiple={multiple} hidden
                    onChange={(e) => e.target.files?.length && handleFiles(e.target.files)}
                />
            </div>
            {error && <p className="mt-3 text-[12.5px] text-rose-500">{error}</p>}
        </div>
    );
}
