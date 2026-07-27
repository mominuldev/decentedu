import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Search, MapPin } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { useToast } from '@/components/Toast';
import { listEvents, deleteEvent, inputCls, type EventRow } from './api';
import { Loading, Empty, SortTh, useSort } from './PagesPanel';

const fmtDateTime = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString(undefined, { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';

export function EventsPanel() {
    const qc = useQueryClient();
    const navigate = useNavigate();
    const toast = useToast();
    const [statusFilter, setStatusFilter] = useState('');
    const [search, setSearch] = useState('');
    const { sort, dir, onSort } = useSort('starts_at', 'desc');
    const { data: rows = [], isLoading } = useQuery({
        queryKey: ['cms-events', statusFilter, search, sort, dir],
        queryFn: () => listEvents({ status: statusFilter || undefined, search: search || undefined, sort, direction: dir }),
    });
    const [deleting, setDeleting] = useState<EventRow | null>(null);

    const invalidate = () => qc.invalidateQueries({ queryKey: ['cms-events'] });
    const del = useMutation({
        mutationFn: (id: number) => deleteEvent(id),
        onSuccess: () => {
            invalidate();
            setDeleting(null);
            toast.success('Event deleted successfully');
        },
        onError: (err) => toast.error(toApiError(err).message),
    });

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Events</h3>
                    <p className="text-[12.5px] text-muted">{rows.length} event{rows.length === 1 ? '' : 's'}</p>
                </div>
                <div className="flex items-center gap-2">
                    <div className="relative">
                        <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-faint" />
                        <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search events…" className={`${inputCls} pl-9 w-56`} />
                    </div>
                    <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className="rounded-xl border border-border-strong bg-surface px-3 py-2 text-[13px] outline-none focus:border-brand-500">
                        <option value="">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                        <option value="scheduled">Scheduled</option>
                    </select>
                    <Button onClick={() => navigate('/cms/events/new')}><Plus size={16} /> Add event</Button>
                </div>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {isLoading ? <Loading /> : rows.length === 0 ? <Empty label={search ? 'No events match your search' : 'No events yet'} /> : (
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
                                    <td className="px-5 py-3"><Badge tone={r.status === 'published' ? 'success' : 'neutral'}>{r.status}</Badge></td>
                                    <td className="px-5 py-3">
                                        <div className="flex justify-end gap-1">
                                            <button onClick={() => navigate(`/cms/events/${r.id}/edit`)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600" aria-label="Edit"><Pencil size={16} /></button>
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
                title="Delete event" message={`Delete "${deleting?.title}"?`} />
        </Card>
    );
}
