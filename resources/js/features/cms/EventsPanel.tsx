import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Search, MapPin, RotateCcw, ArrowLeft } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { useToast } from '@/components/Toast';
import { listEvents, deleteEvent, restoreEvent, forceDeleteEvent, inputCls, type EventRow } from './api';
import { Loading, Empty, SortTh, useSort } from './PagesPanel';
import { cn } from '@/lib/cn';

const fmtDateTime = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString(undefined, { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';

export function EventsPanel() {
    const qc = useQueryClient();
    const navigate = useNavigate();
    const toast = useToast();
    const [statusFilter, setStatusFilter] = useState('');
    const [search, setSearch] = useState('');
    const { sort, dir, onSort } = useSort('starts_at', 'desc');

    const isTrashedView = statusFilter === 'trashed';

    const { data: rows = [], isLoading } = useQuery({
        queryKey: ['cms-events', statusFilter, search, sort, dir],
        queryFn: () => listEvents({ status: statusFilter || undefined, search: search || undefined, sort, direction: dir }),
    });

    const [deleting, setDeleting] = useState<EventRow | null>(null);
    const [forceDeleting, setForceDeleting] = useState<EventRow | null>(null);

    const invalidate = () => qc.invalidateQueries({ queryKey: ['cms-events'] });

    const del = useMutation({
        mutationFn: (id: number) => deleteEvent(id),
        onSuccess: () => {
            invalidate();
            setDeleting(null);
            toast.success('Event moved to trash');
        },
        onError: (err) => toast.error(toApiError(err).message),
    });

    const restore = useMutation({
        mutationFn: (id: number) => restoreEvent(id),
        onSuccess: () => {
            invalidate();
            toast.success('Event restored successfully');
        },
        onError: (err) => toast.error(toApiError(err).message),
    });

    const forceDel = useMutation({
        mutationFn: (id: number) => forceDeleteEvent(id),
        onSuccess: () => {
            invalidate();
            setForceDeleting(null);
            toast.success('Event permanently deleted');
        },
        onError: (err) => toast.error(toApiError(err).message),
    });

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div className="flex items-center gap-3">
                    {isTrashedView && (
                        <button
                            type="button"
                            onClick={() => setStatusFilter('')}
                            className="inline-flex items-center gap-1 rounded-lg border border-border bg-surface px-2.5 py-1.5 text-[13px] font-medium text-fg hover:bg-surface-2 transition-colors cursor-pointer"
                        >
                            <ArrowLeft size={15} /> Back to Events
                        </button>
                    )}
                    <div>
                        <h3 className="text-[15px] font-semibold text-fg">{isTrashedView ? 'Trashed Events' : 'Events'}</h3>
                        <p className="text-[12.5px] text-muted">{rows.length} event{rows.length === 1 ? '' : 's'}</p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <div className="relative">
                        <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-faint" />
                        <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search events…" className={`${inputCls} pl-9 w-56`} />
                    </div>
                    <button
                        type="button"
                        onClick={() => setStatusFilter(isTrashedView ? '' : 'trashed')}
                        className={cn(
                            'inline-flex items-center gap-1.5 rounded-xl border px-3 py-2 text-[13px] font-medium transition-colors cursor-pointer',
                            isTrashedView
                                ? 'border-rose-500 bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300'
                                : 'border-border-strong bg-surface text-fg hover:bg-surface-2'
                        )}
                        title="Toggle trash items"
                    >
                        <Trash2 size={15} />
                        Trash
                    </button>
                    <select
                        value={isTrashedView ? '' : statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value)}
                        disabled={isTrashedView}
                        className="rounded-xl border border-border-strong bg-surface px-3 py-2 text-[13px] outline-none focus:border-brand-500 disabled:opacity-50"
                    >
                        <option value="">All statuses</option>
                        <option value="published">Published</option>
                        <option value="draft">Draft</option>
                        <option value="scheduled">Scheduled</option>
                    </select>
                    <Button onClick={() => navigate('/cms/events/new')}><Plus size={16} /> Add event</Button>
                </div>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {isLoading ? <Loading /> : rows.length === 0 ? <Empty label={search ? 'No events match your search' : isTrashedView ? 'Trash is empty' : 'No events yet'} /> : (
                    <table className="w-full min-w-[720px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <SortTh label="Starts" col="starts_at" sort={sort} dir={dir} onSort={onSort} />
                                <SortTh label="Title" col="title" sort={sort} dir={dir} onSort={onSort} />
                                <th className="px-5 py-2.5 font-semibold">Category</th>
                                <th className="px-5 py-2.5 font-semibold">Location</th>
                                <SortTh label="Status" col="status" sort={sort} dir={dir} onSort={onSort} />
                                <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={r.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                    <td className="whitespace-nowrap px-5 py-3 text-muted">{fmtDateTime(r.starts_at)}</td>
                                    <td className="px-5 py-3 font-medium text-fg">{r.title}</td>
                                    <td className="px-5 py-3 text-muted">{r.categories.join(', ') || '—'}</td>
                                    <td className="px-5 py-3 text-muted">
                                        {r.location ? <span className="inline-flex items-center gap-1"><MapPin size={13} className="text-faint" /> {r.location}</span> : '—'}
                                    </td>
                                    <td className="px-5 py-3">
                                        {r.deleted_at ? (
                                            <Badge tone="danger">Trashed</Badge>
                                        ) : (
                                            <Badge tone={r.status === 'published' ? 'success' : 'neutral'}>{r.status}</Badge>
                                        )}
                                    </td>
                                    <td className="px-5 py-3">
                                        <div className="flex justify-end gap-1">
                                            {isTrashedView ? (
                                                <>
                                                    <button
                                                        onClick={() => restore.mutate(r.id)}
                                                        disabled={restore.isPending}
                                                        className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-emerald-600 disabled:opacity-50"
                                                        title="Restore event"
                                                        aria-label="Restore"
                                                    >
                                                        <RotateCcw size={16} />
                                                    </button>
                                                    <button
                                                        onClick={() => setForceDeleting(r)}
                                                        className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-600"
                                                        title="Permanently delete"
                                                        aria-label="Delete permanently"
                                                    >
                                                        <Trash2 size={16} />
                                                    </button>
                                                </>
                                            ) : (
                                                <>
                                                    <button onClick={() => navigate(`/cms/events/${r.slug || r.id}/edit`)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600" aria-label="Edit"><Pencil size={16} /></button>
                                                    <button onClick={() => setDeleting(r)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500" title="Move to trash" aria-label="Delete"><Trash2 size={16} /></button>
                                                </>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            <ConfirmDialog open={!!deleting} onClose={() => setDeleting(null)} onConfirm={() => deleting && del.mutate(deleting.id)} busy={del.isPending}
                title="Move event to trash" message={`Move "${deleting?.title}" to trash? You can restore it later.`} />

            <ConfirmDialog open={!!forceDeleting} onClose={() => setForceDeleting(null)} onConfirm={() => forceDeleting && forceDel.mutate(forceDeleting.id)} busy={forceDel.isPending}
                title="Permanently delete event" message={`Permanently delete "${forceDeleting?.title}"? This action cannot be undone.`} />
        </Card>
    );
}
