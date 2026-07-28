import { useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    Trash2, Upload, Loader2, Search, Pencil, LayoutGrid, List as ListIcon, File as FileIcon, Image as ImageIcon,
} from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { cn } from '@/lib/cn';
import { formatBytes } from '@/lib/format';
import { useToast } from '@/components/Toast';
import {
    listAssets, getMediaStats, uploadAssets, updateAsset, deleteAsset,
    inputCls, type AssetRow,
} from './api';
import { Field, Loading, Empty } from './PagesPanel';

const TYPE_OPTIONS = [
    { value: 'all', label: 'All Types' },
    { value: 'image', label: 'Images' },
    { value: 'video', label: 'Videos' },
    { value: 'audio', label: 'Audio' },
    { value: 'document', label: 'Documents' },
];
const PER_PAGE_OPTIONS = [20, 40, 60, 100];
const selectCls = 'rounded-xl border border-border-strong bg-surface px-3 py-2 text-[13px] outline-none focus:border-brand-500';

export function MediaPanel() {
    const qc = useQueryClient();
    const toast = useToast();
    const [search, setSearch] = useState('');
    const [type, setType] = useState('all');
    const [perPage, setPerPage] = useState(20);
    const [page, setPage] = useState(1);
    const [view, setView] = useState<'grid' | 'list'>('grid');
    const [editingAsset, setEditingAsset] = useState<AssetRow | null>(null);
    const [deletingAsset, setDeletingAsset] = useState<AssetRow | null>(null);
    const fileRef = useRef<HTMLInputElement>(null);

    const { data: stats } = useQuery({ queryKey: ['cms-media-stats'], queryFn: () => getMediaStats() });
    const { data: response, isLoading } = useQuery({
        queryKey: ['cms-assets', search, type, perPage, page],
        queryFn: () => listAssets({ search: search || undefined, type, per_page: perPage, page }),
    });
    const assets = response?.data ?? [];
    const pagination = response?.pagination;

    const invalidate = () => {
        qc.invalidateQueries({ queryKey: ['cms-assets'] });
        qc.invalidateQueries({ queryKey: ['cms-media-stats'] });
    };
    const upload = useMutation({
        mutationFn: (files: FileList) => uploadAssets(files, null),
        onSuccess: () => {
            invalidate();
            toast.success('Assets uploaded successfully');
        },
        onError: (err) => toast.error(toApiError(err).message),
    });
    const delAsset = useMutation({
        mutationFn: (id: number) => deleteAsset(id),
        onSuccess: () => {
            invalidate();
            setDeletingAsset(null);
            toast.success('Asset deleted successfully');
        },
        onError: (err) => toast.error(toApiError(err).message),
    });

    const setSearchAndReset = (v: string) => { setSearch(v); setPage(1); };
    const setTypeAndReset = (v: string) => { setType(v); setPage(1); };
    const setPerPageAndReset = (v: number) => { setPerPage(v); setPage(1); };

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Media Library</h3>
                    <div className="mt-1.5 flex items-center gap-2">
                        <Badge tone="brand">{(stats?.files ?? 0).toLocaleString()} Files</Badge>
                        <Badge tone="sky">{formatBytes(stats?.size_bytes ?? 0)}</Badge>
                    </div>
                </div>
                <input ref={fileRef} type="file" multiple hidden onChange={(e) => e.target.files?.length && upload.mutate(e.target.files)} />
                <Button onClick={() => fileRef.current?.click()} disabled={upload.isPending}>
                    {upload.isPending ? <Loader2 size={16} className="animate-spin" /> : <Upload size={16} />} Upload files
                </Button>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border px-5 py-3">
                <div className="relative min-w-[200px] flex-1">
                    <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-faint" />
                    <input value={search} onChange={(e) => setSearchAndReset(e.target.value)} placeholder="Search media…" className={cn(inputCls, 'py-2 pl-9')} />
                </div>
                <div className="flex items-center gap-2">
                    <select value={type} onChange={(e) => setTypeAndReset(e.target.value)} className={selectCls}>
                        {TYPE_OPTIONS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                    </select>
                    <select value={perPage} onChange={(e) => setPerPageAndReset(Number(e.target.value))} className={selectCls}>
                        {PER_PAGE_OPTIONS.map((n) => <option key={n} value={n}>{n} / page</option>)}
                    </select>
                    <div className="flex items-center gap-0.5 rounded-xl border border-border-strong p-0.5">
                        <button onClick={() => setView('grid')} className={cn('grid h-8 w-8 place-items-center rounded-lg', view === 'grid' ? 'bg-brand-600 text-white' : 'text-faint hover:text-fg')} aria-label="Grid view">
                            <LayoutGrid size={15} />
                        </button>
                        <button onClick={() => setView('list')} className={cn('grid h-8 w-8 place-items-center rounded-lg', view === 'list' ? 'bg-brand-600 text-white' : 'text-faint hover:text-fg')} aria-label="List view">
                            <ListIcon size={15} />
                        </button>
                    </div>
                </div>
            </div>

            <div className="border-t border-border">
                {isLoading ? <Loading /> : assets.length === 0 ? <Empty label="No media found" /> : view === 'grid' ? (
                    <div className="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                        {assets.map((a) => (
                            <div key={a.id} className="group overflow-hidden rounded-xl border border-border bg-surface-2">
                                <div className="relative aspect-square">
                                    {a.thumb_url || a.url ? <img src={a.thumb_url ?? a.url ?? ''} alt={a.alt ?? a.name} className="h-full w-full object-cover" />
                                        : <span className="grid h-full place-items-center px-2 text-center text-[11px] text-muted">{a.mime_type}</span>}
                                    <div className="absolute inset-x-0 bottom-0 flex justify-end gap-1 bg-slate-950/50 p-1 opacity-0 transition group-hover:opacity-100">
                                        <button onClick={() => setEditingAsset(a)} className="rounded-lg p-1.5 text-white hover:bg-white/20"><Pencil size={14} /></button>
                                        <button onClick={() => setDeletingAsset(a)} className="rounded-lg p-1.5 text-white hover:bg-white/20"><Trash2 size={14} /></button>
                                    </div>
                                </div>
                                <p className="truncate px-2 pt-1.5 text-[12px] text-fg">{a.name}</p>
                                <p className="truncate px-2 pb-1.5 text-[11px] text-faint">{formatBytes(a.size ?? 0)}</p>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[720px] text-left text-[13.5px]">
                            <thead>
                                <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                    <th className="px-5 py-2.5 font-semibold">File</th>
                                    <th className="px-5 py-2.5 font-semibold">Type</th>
                                    <th className="px-5 py-2.5 font-semibold">Size</th>
                                    <th className="px-5 py-2.5 font-semibold">Uploaded</th>
                                    <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {assets.map((a) => (
                                    <tr key={a.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                        <td className="px-5 py-3">
                                            <div className="flex items-center gap-3">
                                                <div className="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-lg border border-border bg-surface-2">
                                                    {a.thumb_url || a.url
                                                        ? <img src={a.thumb_url ?? a.url ?? ''} alt={a.alt ?? a.name} className="h-full w-full object-cover" />
                                                        : (a.mime_type?.startsWith('image/') ? <ImageIcon size={15} className="text-faint" /> : <FileIcon size={15} className="text-faint" />)}
                                                </div>
                                                <span className="truncate font-medium text-fg">{a.name}</span>
                                            </div>
                                        </td>
                                        <td className="px-5 py-3 text-muted">{a.mime_type ?? '—'}</td>
                                        <td className="px-5 py-3 text-muted">{formatBytes(a.size ?? 0)}</td>
                                        <td className="px-5 py-3 text-muted">{a.created_at ? new Date(a.created_at).toLocaleDateString() : '—'}</td>
                                        <td className="px-5 py-3">
                                            <div className="flex justify-end gap-1">
                                                <button onClick={() => setEditingAsset(a)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600" aria-label="Edit"><Pencil size={16} /></button>
                                                <button onClick={() => setDeletingAsset(a)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500" aria-label="Delete"><Trash2 size={16} /></button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {pagination && pagination.last_page > 1 && (
                <div className="flex items-center justify-between border-t border-border px-5 py-3">
                    <div className="text-[12.5px] text-muted">
                        Showing {((pagination.current_page - 1) * pagination.per_page) + 1} to{' '}
                        {Math.min(pagination.current_page * pagination.per_page, pagination.total)} of{' '}
                        {pagination.total} files
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm" onClick={() => setPage(pagination.current_page - 1)} disabled={pagination.current_page === 1}>
                            Previous
                        </Button>
                        <span className="text-[13px] text-muted">Page {pagination.current_page} of {pagination.last_page}</span>
                        <Button variant="outline" size="sm" onClick={() => setPage(pagination.current_page + 1)} disabled={pagination.current_page === pagination.last_page}>
                            Next
                        </Button>
                    </div>
                </div>
            )}

            {editingAsset && <AssetForm asset={editingAsset} onClose={() => setEditingAsset(null)} onSaved={() => { invalidate(); setEditingAsset(null); }} />}
            <ConfirmDialog open={!!deletingAsset} onClose={() => setDeletingAsset(null)} onConfirm={() => deletingAsset && delAsset.mutate(deletingAsset.id)} busy={delAsset.isPending} title="Delete asset" message={`Delete "${deletingAsset?.name}"?`} />
        </Card>
    );
}

function AssetForm({ asset, onClose, onSaved }: { asset: AssetRow; onClose: () => void; onSaved: () => void }) {
    const [form, setForm] = useState({ name: asset.name, alt_text: asset.alt ?? '', caption: asset.caption ?? '' });
    const [error, setError] = useState<string | null>(null);
    const save = useMutation({ mutationFn: () => updateAsset(asset.id, form), onSuccess: onSaved, onError: (e) => setError(toApiError(e).message) });
    const set = (p: Partial<typeof form>) => setForm((f) => ({ ...f, ...p }));
    return (
        <Modal open onClose={onClose} title="Edit asset"
            footer={<><Button variant="outline" onClick={onClose}>Cancel</Button><Button onClick={() => save.mutate()} disabled={save.isPending}>Save</Button></>}>
            <div className="space-y-4">
                {error && <p className="text-[13px] text-rose-600">{error}</p>}
                {(asset.thumb_url || asset.url) && <img src={asset.preview_url ?? asset.url ?? ''} alt="" className="max-h-40 rounded-xl object-contain" />}
                <Field label="Name"><input className={inputCls} value={form.name} onChange={(e) => set({ name: e.target.value })} /></Field>
                <Field label="Alt text"><input className={inputCls} value={form.alt_text} onChange={(e) => set({ alt_text: e.target.value })} /></Field>
                <Field label="Caption"><input className={inputCls} value={form.caption} onChange={(e) => set({ caption: e.target.value })} /></Field>
            </div>
        </Modal>
    );
}
