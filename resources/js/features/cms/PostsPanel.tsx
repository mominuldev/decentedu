import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Loader2, Inbox } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { listPosts, createPost, updatePost, deletePost, POST_TYPES, type PostRow } from './api';

const inputCls = 'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';

export function PostsPanel() {
    const qc = useQueryClient();
    const [typeFilter, setTypeFilter] = useState('');
    const { data: rows = [], isLoading } = useQuery({ queryKey: ['cms-posts', typeFilter], queryFn: () => listPosts({ type: typeFilter || undefined }) });
    const [editing, setEditing] = useState<PostRow | null>(null);
    const [creating, setCreating] = useState(false);
    const [deleting, setDeleting] = useState<PostRow | null>(null);

    const invalidate = () => qc.invalidateQueries({ queryKey: ['cms-posts'] });
    const del = useMutation({ mutationFn: (id: number) => deletePost(id), onSuccess: () => { invalidate(); setDeleting(null); } });

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Posts</h3>
                    <p className="text-[12.5px] text-muted">{rows.length} post{rows.length === 1 ? '' : 's'}</p>
                </div>
                <div className="flex items-center gap-2">
                    <select value={typeFilter} onChange={(e) => setTypeFilter(e.target.value)} className="rounded-xl border border-border-strong bg-surface px-3 py-2 text-[13px] outline-none focus:border-brand-500">
                        <option value="">All types</option>
                        {POST_TYPES.map((t) => <option key={t} value={t}>{t.replace('_', ' ')}</option>)}
                    </select>
                    <Button onClick={() => setCreating(true)}><Plus size={16} /> Add post</Button>
                </div>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : rows.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                        <div className="grid h-12 w-12 place-items-center rounded-2xl bg-surface-2 text-faint"><Inbox size={22} /></div>
                        <p className="text-[14px] font-medium text-fg">No posts yet</p>
                    </div>
                ) : (
                    <table className="w-full min-w-[640px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Title</th>
                                <th className="px-5 py-2.5 font-semibold">Type</th>
                                <th className="px-5 py-2.5 font-semibold">Slug</th>
                                <th className="px-5 py-2.5 font-semibold">Status</th>
                                <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={r.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                    <td className="px-5 py-3 font-medium text-fg">{r.title}</td>
                                    <td className="px-5 py-3 text-muted capitalize">{r.type.replace('_', ' ')}</td>
                                    <td className="px-5 py-3 text-muted">{r.slug}</td>
                                    <td className="px-5 py-3"><Badge tone={r.status === 'published' ? 'success' : 'neutral'}>{r.status}</Badge></td>
                                    <td className="px-5 py-3">
                                        <div className="flex justify-end gap-1">
                                            <button onClick={() => setEditing(r)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600" aria-label="Edit"><Pencil size={16} /></button>
                                            <button onClick={() => setDeleting(r)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500" aria-label="Delete"><Trash2 size={16} /></button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {(creating || editing) && <PostForm row={editing} onClose={() => { setCreating(false); setEditing(null); }} onSaved={() => { invalidate(); setCreating(false); setEditing(null); }} />}
            <ConfirmDialog open={!!deleting} onClose={() => setDeleting(null)} onConfirm={() => deleting && del.mutate(deleting.id)} busy={del.isPending}
                title="Delete post" message={`Are you sure you want to delete "${deleting?.title}"?`} />
        </Card>
    );
}

function PostForm({ row, onClose, onSaved }: { row: PostRow | null; onClose: () => void; onSaved: () => void }) {
    const [form, setForm] = useState({
        type: row?.type ?? 'notice', title: row?.title ?? '', body: row?.body ?? '',
        description: row?.description ?? '', keywords: row?.keywords ?? '', status: row?.status ?? 'draft',
    });
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [error, setError] = useState<string | null>(null);

    const save = useMutation({
        mutationFn: () => (row ? updatePost(row.id, form) : createPost(form)),
        onSuccess: onSaved,
        onError: (e) => { const a = toApiError(e); setError(a.errors ? null : a.message); setErrors(a.errors ?? {}); },
    });

    const set = (k: string, v: string) => setForm((f) => ({ ...f, [k]: v }));

    return (
        <Modal
            open onClose={onClose} title={row ? 'Edit post' : 'Add post'} width="max-w-3xl"
            footer={<>
                <Button variant="outline" onClick={onClose} disabled={save.isPending}>Cancel</Button>
                <Button onClick={() => { setError(null); setErrors({}); save.mutate(); }} disabled={save.isPending}>
                    {save.isPending ? <Loader2 size={16} className="animate-spin" /> : null} {row ? 'Save changes' : 'Create post'}
                </Button>
            </>}
        >
            {error && <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
            <div className="grid grid-cols-2 gap-4">
                <div className="space-y-4">
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="mb-1.5 block text-[13px] font-medium text-fg">Type</label>
                            <select value={form.type} onChange={(e) => set('type', e.target.value)} className={inputCls}>
                                {POST_TYPES.map((t) => <option key={t} value={t}>{t.replace('_', ' ')}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1.5 block text-[13px] font-medium text-fg">Status</label>
                            <select value={form.status} onChange={(e) => set('status', e.target.value)} className={inputCls}>
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">Title <span className="text-rose-500">*</span></label>
                        <input value={form.title} onChange={(e) => set('title', e.target.value)} className={inputCls} />
                        {errors.title && <p className="mt-1.5 text-[12px] text-rose-500">{errors.title[0]}</p>}
                    </div>
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">
                            Body <span className="ml-1 font-normal text-faint">(HTML is sanitized on save — script tags/handlers are stripped)</span>
                        </label>
                        <textarea rows={8} value={form.body} onChange={(e) => set('body', e.target.value)} className={inputCls} />
                    </div>
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">Description</label>
                        <textarea rows={2} value={form.description} onChange={(e) => set('description', e.target.value)} className={inputCls} />
                    </div>
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">Keywords</label>
                        <input value={form.keywords} onChange={(e) => set('keywords', e.target.value)} className={inputCls} />
                    </div>
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Preview</label>
                    <div className="prose prose-sm h-full max-h-[420px] overflow-y-auto rounded-xl border border-border-strong bg-surface-2 p-4 text-fg" dangerouslySetInnerHTML={{ __html: form.body || '<p class="text-faint">Nothing to preview yet.</p>' }} />
                </div>
            </div>
        </Modal>
    );
}
