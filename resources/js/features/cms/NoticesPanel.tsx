import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Star, Search, Paperclip } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { ConfirmDialog } from '@/components/Modal';
import { listNotices, deleteNotice, inputCls, type NoticeRow } from './api';
import { Loading, Empty, SortTh, useSort } from './PagesPanel';

const fmtDate = (iso: string | null) =>
    iso ? new Date(iso).toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

export function NoticesPanel() {
    const qc = useQueryClient();
    const navigate = useNavigate();
    const [statusFilter, setStatusFilter] = useState('');
    const [search, setSearch] = useState('');
    const { sort, dir, onSort } = useSort('notice_date', 'desc');
    const { data: rows = [], isLoading } = useQuery({
        queryKey: ['cms-notices', statusFilter, search, sort, dir],
        queryFn: () => listNotices({ status: statusFilter || undefined, search: search || undefined, sort, direction: dir }),
    });
    const [deleting, setDeleting] = useState<NoticeRow | null>(null);

    const invalidate = () => qc.invalidateQueries({ queryKey: ['cms-notices'] });
    const del = useMutation({ mutationFn: (id: number) => deleteNotice(id), onSuccess: () => { invalidate(); setDeleting(null); } });

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Notices</h3>
                    <p className="text-[12.5px] text-muted">{rows.length} notice{rows.length === 1 ? '' : 's'}</p>
                </div>
                <div className="flex items-center gap-2">
                    <div className="relative">
                        <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-faint" />
                        <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search notices…" className={`${inputCls} pl-9 w-56`} />
                    </div>
                    <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className="rounded-xl border border-border-strong bg-surface px-3 py-2 text-[13px] outline-none focus:border-brand-500">
                        <option value="">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                        <option value="scheduled">Scheduled</option>
                    </select>
                    <Button onClick={() => navigate('/cms/notices/new')}><Plus size={16} /> Add notice</Button>
                </div>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {isLoading ? <Loading /> : rows.length === 0 ? <Empty label={search ? 'No notices match your search' : 'No notices yet'} /> : (
                    <table className="w-full min-w-[720px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <SortTh label="Date" col="notice_date" sort={sort} dir={dir} onSort={onSort} />
                                <SortTh label="Title" col="title" sort={sort} dir={dir} onSort={onSort} />
                                <th className="px-5 py-2.5 font-semibold">Category</th>
                                <th className="px-5 py-2.5 font-semibold">File</th>
                                <SortTh label="Status" col="status" sort={sort} dir={dir} onSort={onSort} />
                                <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={r.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                    <td className="whitespace-nowrap px-5 py-3 text-muted">{fmtDate(r.notice_date)}</td>
                                    <td className="px-5 py-3 font-medium text-fg">
                                        <span className="inline-flex items-center gap-1.5">{r.is_important && <Star size={13} className="text-amber-500" fill="currentColor" />}{r.title}</span>
                                    </td>
                                    <td className="px-5 py-3 text-muted">{r.categories.join(', ') || '—'}</td>
                                    <td className="px-5 py-3 text-muted">
                                        {r.attachment?.url
                                            ? <a href={r.attachment.url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-brand-600 hover:underline"><Paperclip size={14} /> File</a>
                                            : '—'}
                                    </td>
                                    <td className="px-5 py-3"><Badge tone={r.status === 'published' ? 'success' : 'neutral'}>{r.status}</Badge></td>
                                    <td className="px-5 py-3">
                                        <div className="flex justify-end gap-1">
                                            <button onClick={() => navigate(`/cms/notices/${r.id}/edit`)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600" aria-label="Edit"><Pencil size={16} /></button>
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
                title="Delete notice" message={`Delete "${deleting?.title}"?`} />
        </Card>
    );
}
