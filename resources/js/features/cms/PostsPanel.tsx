import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Star, Search } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { useToast } from '@/components/Toast';
import { listPosts, deletePost, inputCls, type PostRow } from './api';
import { Loading, Empty, SortTh, useSort } from './PagesPanel';

export function PostsPanel() {
    const qc = useQueryClient();
    const navigate = useNavigate();
    const toast = useToast();
    const [statusFilter, setStatusFilter] = useState('');
    const [search, setSearch] = useState('');
    const { sort, dir, onSort } = useSort('published_at', 'desc');
    const { data: rows = [], isLoading } = useQuery({
        queryKey: ['cms-posts', statusFilter, search, sort, dir],
        queryFn: () => listPosts({ status: statusFilter || undefined, search: search || undefined, sort, direction: dir }),
    });
    const [deleting, setDeleting] = useState<PostRow | null>(null);

    const invalidate = () => qc.invalidateQueries({ queryKey: ['cms-posts'] });
    const del = useMutation({
        mutationFn: (id: number) => deletePost(id),
        onSuccess: () => {
            invalidate();
            setDeleting(null);
            toast.success('Post deleted successfully');
        },
        onError: (err) => toast.error(toApiError(err).message),
    });

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Posts</h3>
                    <p className="text-[12.5px] text-muted">{rows.length} post{rows.length === 1 ? '' : 's'}</p>
                </div>
                <div className="flex items-center gap-2">
                    <div className="relative">
                        <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-faint" />
                        <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search posts…" className={`${inputCls} pl-9 w-56`} />
                    </div>
                    <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className="rounded-xl border border-border-strong bg-surface px-3 py-2 text-[13px] outline-none focus:border-brand-500">
                        <option value="">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                        <option value="scheduled">Scheduled</option>
                    </select>
                    <Button onClick={() => navigate('/cms/posts/new')}><Plus size={16} /> Add post</Button>
                </div>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {isLoading ? <Loading /> : rows.length === 0 ? <Empty label={search ? 'No posts match your search' : 'No posts yet'} /> : (
                    <table className="w-full min-w-[640px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <SortTh label="Title" col="title" sort={sort} dir={dir} onSort={onSort} />
                                <th className="px-5 py-2.5 font-semibold">Categories</th>
                                <th className="px-5 py-2.5 font-semibold">Author</th>
                                <SortTh label="Status" col="status" sort={sort} dir={dir} onSort={onSort} />
                                <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={r.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                    <td className="px-5 py-3 font-medium text-fg">
                                        <span className="inline-flex items-center gap-1.5">{r.is_featured && <Star size={13} className="text-amber-500" fill="currentColor" />}{r.title}</span>
                                    </td>
                                    <td className="px-5 py-3 text-muted">{r.categories.join(', ') || '—'}</td>
                                    <td className="px-5 py-3 text-muted">{r.author ?? '—'}</td>
                                    <td className="px-5 py-3"><Badge tone={r.status === 'published' ? 'success' : 'neutral'}>{r.status}</Badge></td>
                                    <td className="px-5 py-3">
                                        <div className="flex justify-end gap-1">
                                            <button onClick={() => navigate(`/cms/posts/${r.id}/edit`)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600" aria-label="Edit"><Pencil size={16} /></button>
                                            <button onClick={() => setDeleting(r)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500" aria-label="Delete"><Trash2 size={16} /></button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            <ConfirmDialog open={!!deleting} onClose={() => setDeleting(null)} onConfirm={() => deleting && del.mutate(deleting.id)} busy={del.isPending}
                title="Delete post" message={`Delete "${deleting?.title}"?`} />
        </Card>
    );
}
