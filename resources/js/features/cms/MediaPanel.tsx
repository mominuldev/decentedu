import { useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Trash2, Upload, Folder, FolderOpen, Loader2, Search, Pencil } from 'lucide-react';
import { Card, Button } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { cn } from '@/lib/cn';
import {
    listFolders, createFolder, deleteFolder, listAssets, uploadAssets, updateAsset, deleteAsset,
    inputCls, type FolderRow, type AssetRow,
} from './api';
import { Field, Loading, Empty } from './PagesPanel';

export function MediaPanel() {
    const qc = useQueryClient();
    const [folderId, setFolderId] = useState<number | null>(null);
    const [search, setSearch] = useState('');
    const [creatingFolder, setCreatingFolder] = useState(false);
    const [editingAsset, setEditingAsset] = useState<AssetRow | null>(null);
    const [deletingAsset, setDeletingAsset] = useState<AssetRow | null>(null);
    const [deletingFolder, setDeletingFolder] = useState<FolderRow | null>(null);
    const fileRef = useRef<HTMLInputElement>(null);

    const { data: folders = [] } = useQuery({ queryKey: ['cms-folders'], queryFn: listFolders });
    const { data: assets = [], isLoading } = useQuery({
        queryKey: ['cms-assets', folderId, search],
        queryFn: () => listAssets({ folder: folderId, search: search || undefined }),
    });

    const invalidate = () => qc.invalidateQueries({ queryKey: ['cms-assets'] });
    const upload = useMutation({ mutationFn: (files: FileList) => uploadAssets(files, folderId), onSuccess: invalidate });
    const delAsset = useMutation({ mutationFn: (id: number) => deleteAsset(id), onSuccess: () => { invalidate(); setDeletingAsset(null); } });
    const delFolder = useMutation({ mutationFn: (id: number) => deleteFolder(id), onSuccess: () => { qc.invalidateQueries({ queryKey: ['cms-folders'] }); invalidate(); setDeletingFolder(null); if (deletingFolder?.id === folderId) setFolderId(null); } });

    return (
        <div className="grid gap-4 lg:grid-cols-[260px_1fr]">
            <Card>
                <div className="flex items-center justify-between px-5 py-4">
                    <h3 className="text-[15px] font-semibold text-fg">Folders</h3>
                    <Button size="sm" onClick={() => setCreatingFolder(true)}><Plus size={15} /></Button>
                </div>
                <div className="border-t border-border">
                    <button onClick={() => setFolderId(null)} className={cn('flex w-full items-center gap-2 border-b border-border px-5 py-2.5 text-left text-[13.5px] hover:bg-surface-2/50', folderId === null && 'bg-surface-2/60')}>
                        <FolderOpen size={15} className="text-faint" /> All media
                    </button>
                    {folders.map((f) => (
                        <div key={f.id} className={cn('group flex items-center border-b border-border last:border-0 hover:bg-surface-2/50', folderId === f.id && 'bg-surface-2/60')}>
                            <button onClick={() => setFolderId(f.id)} className="flex flex-1 items-center gap-2 px-5 py-2.5 text-left text-[13.5px] text-fg">
                                <Folder size={15} className="text-faint" /> {f.name} <span className="text-faint">({f.assets_count})</span>
                            </button>
                            <button onClick={() => setDeletingFolder(f)} className="mr-2 rounded-lg p-1.5 text-faint opacity-0 hover:text-rose-500 group-hover:opacity-100"><Trash2 size={14} /></button>
                        </div>
                    ))}
                </div>
            </Card>

            <Card>
                <div className="flex flex-wrap items-center justify-between gap-2 px-5 py-4">
                    <div className="relative flex-1 min-w-[180px]">
                        <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-faint" />
                        <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search…" className={cn(inputCls, 'pl-9')} />
                    </div>
                    <input ref={fileRef} type="file" multiple hidden onChange={(e) => e.target.files?.length && upload.mutate(e.target.files)} />
                    <Button onClick={() => fileRef.current?.click()} disabled={upload.isPending}>
                        {upload.isPending ? <Loader2 size={16} className="animate-spin" /> : <Upload size={16} />} Upload
                    </Button>
                </div>
                <div className="border-t border-border p-4">
                    {isLoading ? <Loading /> : assets.length === 0 ? <Empty label="No media here" /> : (
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
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
                                    <p className="truncate px-2 py-1.5 text-[12px] text-fg">{a.name}</p>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </Card>

            {creatingFolder && <FolderForm onClose={() => setCreatingFolder(false)} onSaved={() => { qc.invalidateQueries({ queryKey: ['cms-folders'] }); setCreatingFolder(false); }} />}
            {editingAsset && <AssetForm asset={editingAsset} folders={folders} onClose={() => setEditingAsset(null)} onSaved={() => { invalidate(); setEditingAsset(null); }} />}
            <ConfirmDialog open={!!deletingAsset} onClose={() => setDeletingAsset(null)} onConfirm={() => deletingAsset && delAsset.mutate(deletingAsset.id)} busy={delAsset.isPending} title="Delete asset" message={`Delete "${deletingAsset?.name}"?`} />
            <ConfirmDialog open={!!deletingFolder} onClose={() => setDeletingFolder(null)} onConfirm={() => deletingFolder && delFolder.mutate(deletingFolder.id)} busy={delFolder.isPending} title="Delete folder" message={`Delete "${deletingFolder?.name}"? Its contents move to the parent folder.`} />
        </div>
    );
}

function FolderForm({ onClose, onSaved }: { onClose: () => void; onSaved: () => void }) {
    const [name, setName] = useState('');
    const [error, setError] = useState<string | null>(null);
    const save = useMutation({ mutationFn: () => createFolder({ name }), onSuccess: onSaved, onError: (e) => setError(toApiError(e).message) });
    return (
        <Modal open onClose={onClose} title="New folder"
            footer={<><Button variant="outline" onClick={onClose}>Cancel</Button><Button onClick={() => save.mutate()} disabled={save.isPending}>Create</Button></>}>
            {error && <p className="mb-3 text-[13px] text-rose-600">{error}</p>}
            <Field label="Folder name"><input className={inputCls} value={name} onChange={(e) => setName(e.target.value)} /></Field>
        </Modal>
    );
}

function AssetForm({ asset, folders, onClose, onSaved }: { asset: AssetRow; folders: FolderRow[]; onClose: () => void; onSaved: () => void }) {
    const [form, setForm] = useState({ name: asset.name, alt_text: asset.alt ?? '', caption: asset.caption ?? '', media_folder_id: asset.folder?.id ?? null as number | null });
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
                <Field label="Folder">
                    <select className={inputCls} value={form.media_folder_id ?? ''} onChange={(e) => set({ media_folder_id: e.target.value ? Number(e.target.value) : null })}>
                        <option value="">— None —</option>
                        {folders.map((f) => <option key={f.id} value={f.id}>{f.name}</option>)}
                    </select>
                </Field>
            </div>
        </Modal>
    );
}
