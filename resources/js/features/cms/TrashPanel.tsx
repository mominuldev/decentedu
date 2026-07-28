import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { RotateCcw, Trash2, Search } from 'lucide-react';
import { Card, Badge } from '@/components/ui';
import { ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { useToast } from '@/components/Toast';
import {
    listPages, restorePage, forceDeletePage,
    listPosts, restorePost, forceDeletePost,
    listNotices, restoreNotice, forceDeleteNotice,
    listEvents, restoreEvent, forceDeleteEvent,
    listGalleries, restoreGallery, forceDeleteGallery,
    inputCls,
} from './api';
import { Loading, Empty, SortTh, useSort } from './PagesPanel';

type TrashType = 'all' | 'page' | 'post' | 'gallery' | 'notice' | 'event';

interface TrashedItem {
    id: number;
    type: 'page' | 'post' | 'gallery' | 'notice' | 'event';
    typeLabel: string;
    title: string;
    info: string;
    status: string;
    deletedAt: string | null;
}

export function TrashPanel() {
    const qc = useQueryClient();
    const toast = useToast();
    const [typeFilter, setTypeFilter] = useState<TrashType>('all');
    const [search, setSearch] = useState('');
    const { sort, dir, onSort } = useSort('title', 'asc');
    const [deletingItem, setDeletingItem] = useState<TrashedItem | null>(null);

    const pagesQ = useQuery({
        queryKey: ['cms-pages-trashed', search],
        queryFn: () => listPages({ status: 'trashed', search: search || undefined }),
        enabled: typeFilter === 'all' || typeFilter === 'page',
    });

    const postsQ = useQuery({
        queryKey: ['cms-posts-trashed', search],
        queryFn: () => listPosts({ status: 'trashed', search: search || undefined }),
        enabled: typeFilter === 'all' || typeFilter === 'post',
    });

    const galleriesQ = useQuery({
        queryKey: ['cms-galleries-trashed', search],
        queryFn: () => listGalleries({ status: 'trashed', search: search || undefined }),
        enabled: typeFilter === 'all' || typeFilter === 'gallery',
    });

    const noticesQ = useQuery({
        queryKey: ['cms-notices-trashed', search],
        queryFn: () => listNotices({ status: 'trashed', search: search || undefined }),
        enabled: typeFilter === 'all' || typeFilter === 'notice',
    });

    const eventsQ = useQuery({
        queryKey: ['cms-events-trashed', search],
        queryFn: () => listEvents({ status: 'trashed', search: search || undefined }),
        enabled: typeFilter === 'all' || typeFilter === 'event',
    });

    const isLoading = pagesQ.isLoading || postsQ.isLoading || galleriesQ.isLoading || noticesQ.isLoading || eventsQ.isLoading;

    const items: TrashedItem[] = [];

    if (typeFilter === 'all' || typeFilter === 'page') {
        (pagesQ.data ?? []).forEach((p) => items.push({ id: p.id, type: 'page', typeLabel: 'Page', title: p.title, info: `/${p.path}`, status: p.status, deletedAt: p.deleted_at }));
    }
    if (typeFilter === 'all' || typeFilter === 'post') {
        (postsQ.data ?? []).forEach((p) => items.push({ id: p.id, type: 'post', typeLabel: 'Post', title: p.title, info: p.categories?.join(', ') || 'Post', status: p.status, deletedAt: p.deleted_at }));
    }
    if (typeFilter === 'all' || typeFilter === 'gallery') {
        (galleriesQ.data ?? []).forEach((g) => items.push({ id: g.id, type: 'gallery', typeLabel: 'Gallery', title: g.title, info: `${g.images?.length ?? 0} photos`, status: g.status, deletedAt: null }));
    }
    if (typeFilter === 'all' || typeFilter === 'notice') {
        (noticesQ.data ?? []).forEach((n) => items.push({ id: n.id, type: 'notice', typeLabel: 'Notice', title: n.title, info: n.notice_date ?? 'Notice', status: n.status, deletedAt: null }));
    }
    if (typeFilter === 'all' || typeFilter === 'event') {
        (eventsQ.data ?? []).forEach((e) => items.push({ id: e.id, type: 'event', typeLabel: 'Event', title: e.title, info: e.location ?? 'Event', status: e.status, deletedAt: e.deleted_at }));
    }

    const invalidateAll = () => {
        qc.invalidateQueries({ queryKey: ['cms-pages-trashed'] });
        qc.invalidateQueries({ queryKey: ['cms-posts-trashed'] });
        qc.invalidateQueries({ queryKey: ['cms-galleries-trashed'] });
        qc.invalidateQueries({ queryKey: ['cms-notices-trashed'] });
        qc.invalidateQueries({ queryKey: ['cms-events-trashed'] });
        qc.invalidateQueries({ queryKey: ['cms-pages'] });
        qc.invalidateQueries({ queryKey: ['cms-posts'] });
        qc.invalidateQueries({ queryKey: ['cms-galleries'] });
        qc.invalidateQueries({ queryKey: ['cms-notices'] });
        qc.invalidateQueries({ queryKey: ['cms-events'] });
    };

    const restore = useMutation({
        mutationFn: async (item: TrashedItem) => {
            if (item.type === 'page') await restorePage(item.id);
            else if (item.type === 'post') await restorePost(item.id);
            else if (item.type === 'gallery') await restoreGallery(item.id);
            else if (item.type === 'notice') await restoreNotice(item.id);
            else if (item.type === 'event') await restoreEvent(item.id);
        },
        onSuccess: (_, item) => {
            invalidateAll();
            toast.success(`${item.typeLabel} "${item.title}" restored successfully`);
        },
        onError: (err) => toast.error(toApiError(err).message),
    });

    const forceDel = useMutation({
        mutationFn: async (item: TrashedItem) => {
            if (item.type === 'page') await forceDeletePage(item.id);
            else if (item.type === 'post') await forceDeletePost(item.id);
            else if (item.type === 'gallery') await forceDeleteGallery(item.id);
            else if (item.type === 'notice') await forceDeleteNotice(item.id);
            else if (item.type === 'event') await forceDeleteEvent(item.id);
        },
        onSuccess: (_, item) => {
            invalidateAll();
            setDeletingItem(null);
            toast.success(`${item.typeLabel} "${item.title}" permanently deleted`);
        },
        onError: (err) => toast.error(toApiError(err).message),
    });

    const typeBadgeTone = (t: TrashedItem['type']) => {
        switch (t) {
            case 'page': return 'brand';
            case 'post': return 'info';
            case 'gallery': return 'warning';
            case 'notice': return 'neutral';
            case 'event': return 'success';
        }
    };

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Trash</h3>
                    <p className="text-[12.5px] text-muted">{items.length} item{items.length === 1 ? '' : 's'} in trash</p>
                </div>
                <div className="flex items-center gap-2">
                    <div className="relative">
                        <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-faint" />
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search trashed items…"
                            className={`${inputCls} pl-9 w-56`}
                        />
                    </div>
                    <select
                        value={typeFilter}
                        onChange={(e) => setTypeFilter(e.target.value as TrashType)}
                        className="rounded-xl border border-border-strong bg-surface px-3 py-2 text-[13px] outline-none focus:border-brand-500"
                    >
                        <option value="all">All Content Types</option>
                        <option value="page">Pages</option>
                        <option value="post">Posts</option>
                        <option value="gallery">Galleries</option>
                        <option value="notice">Notices</option>
                        <option value="event">Events</option>
                    </select>
                </div>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {isLoading ? (
                    <Loading />
                ) : items.length === 0 ? (
                    <Empty label={search ? 'No trashed items match your search' : 'Trash is empty'} />
                ) : (
                    <table className="w-full min-w-[640px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Type</th>
                                <SortTh label="Title" col="title" sort={sort} dir={dir} onSort={onSort} />
                                <th className="px-5 py-2.5 font-semibold">Details</th>
                                <th className="px-5 py-2.5 font-semibold">Status</th>
                                <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((r) => (
                                <tr key={`${r.type}-${r.id}`} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                    <td className="px-5 py-3">
                                        <Badge tone={typeBadgeTone(r.type)}>{r.typeLabel}</Badge>
                                    </td>
                                    <td className="px-5 py-3 font-medium text-fg">
                                        <div>{r.title}</div>
                                    </td>
                                    <td className="px-5 py-3 text-muted">{r.info}</td>
                                    <td className="px-5 py-3">
                                        <Badge tone="danger">Trashed</Badge>
                                    </td>
                                    <td className="px-5 py-3">
                                        <div className="flex justify-end gap-1">
                                            <button
                                                onClick={() => restore.mutate(r)}
                                                disabled={restore.isPending}
                                                className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-emerald-600 disabled:opacity-50"
                                                title="Restore item"
                                                aria-label="Restore"
                                            >
                                                <RotateCcw size={16} />
                                            </button>
                                            <button
                                                onClick={() => setDeletingItem(r)}
                                                className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-600"
                                                title="Permanently delete"
                                                aria-label="Delete permanently"
                                            >
                                                <Trash2 size={16} />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            <ConfirmDialog
                open={!!deletingItem}
                onClose={() => setDeletingItem(null)}
                onConfirm={() => deletingItem && forceDel.mutate(deletingItem)}
                busy={forceDel.isPending}
                title={`Permanently delete ${deletingItem?.typeLabel.toLowerCase() ?? 'item'}`}
                message={`Permanently delete "${deletingItem?.title}"? This action cannot be undone.`}
            />
        </Card>
    );
}
