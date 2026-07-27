import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Loader2, Inbox, Search, ChevronUp, ChevronDown, ChevronsUpDown } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { useToast } from '@/components/Toast';
import { listPages, deletePage, inputCls, labelCls, type PageRow, type SortDir } from './api';

export function PagesPanel() {
    const qc = useQueryClient();
    const navigate = useNavigate();
    const toast = useToast();
    const [search, setSearch] = useState('');
    const { sort, dir, onSort } = useSort('path', 'asc');
    const { data: rows = [], isLoading } = useQuery({
        queryKey: ['cms-pages', search, sort, dir],
        queryFn: () => listPages({ search: search || undefined, sort, direction: dir }),
    });
    const [deleting, setDeleting] = useState<PageRow | null>(null);

    const invalidate = () => qc.invalidateQueries({ queryKey: ['cms-pages'] });
    const del = useMutation({
        mutationFn: (id: number) => deletePage(id),
        onSuccess: () => {
            invalidate();
            setDeleting(null);
            toast.success('Page deleted successfully');
        },
        onError: (err) => toast.error(toApiError(err).message),
    });

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Pages</h3>
                    <p className="text-[12.5px] text-muted">{rows.length} page{rows.length === 1 ? '' : 's'}</p>
                </div>
                <div className="flex items-center gap-2">
                    <div className="relative">
                        <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-faint" />
                        <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search pages…" className={`${inputCls} pl-9 w-56`} />
                    </div>
                    <Button onClick={() => navigate('/cms/pages/new')}><Plus size={16} /> Add page</Button>
                </div>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {isLoading ? (
                    <Loading />
                ) : rows.length === 0 ? (
                    <Empty label={search ? 'No pages match your search' : 'No pages yet'} />
                ) : (
                    <table className="w-full min-w-[640px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <SortTh label="Title" col="title" sort={sort} dir={dir} onSort={onSort} />
                                <SortTh label="Path" col="path" sort={sort} dir={dir} onSort={onSort} />
                                <SortTh label="Template" col="template" sort={sort} dir={dir} onSort={onSort} />
                                <SortTh label="Status" col="status" sort={sort} dir={dir} onSort={onSort} />
                                <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={r.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                    <td className="px-5 py-3 font-medium text-fg">{r.title}</td>
                                    <td className="px-5 py-3 text-muted">/{r.path}</td>
                                    <td className="px-5 py-3 text-muted capitalize">{r.template}</td>
                                    <td className="px-5 py-3"><Badge tone={r.status === 'published' ? 'success' : 'neutral'}>{r.status}</Badge></td>
                                    <td className="px-5 py-3">
                                        <div className="flex justify-end gap-1">
                                            <button onClick={() => navigate(`/cms/pages/${r.id}/edit`)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600" aria-label="Edit"><Pencil size={16} /></button>
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
                title="Delete page" message={`Delete "${deleting?.title}" and all its child pages?`} />
        </Card>
    );
}

/* ---- shared bits reused by other panels via export ---------------------- */

export function useSort(defaultCol: string, defaultDir: SortDir = 'asc') {
    const [sort, setSort] = useState(defaultCol);
    const [dir, setDir] = useState<SortDir>(defaultDir);
    const onSort = (col: string) => {
        if (col === sort) setDir((d) => (d === 'asc' ? 'desc' : 'asc'));
        else { setSort(col); setDir('asc'); }
    };
    return { sort, dir, onSort };
}
export function SortTh({ label, col, sort, dir, onSort }: { label: string; col: string; sort: string; dir: SortDir; onSort: (col: string) => void }) {
    const active = sort === col;
    return (
        <th className="px-5 py-2.5 font-semibold">
            <button onClick={() => onSort(col)} className={`inline-flex items-center gap-1 uppercase tracking-wide ${active ? 'text-fg' : 'hover:text-fg'}`}>
                {label}
                {active ? (dir === 'asc' ? <ChevronUp size={13} /> : <ChevronDown size={13} />) : <ChevronsUpDown size={13} className="opacity-40" />}
            </button>
        </th>
    );
}
export function Tabs<T extends string>({ tab, setTab, tabs }: { tab: T; setTab: (t: T) => void; tabs: [T, string][] }) {
    return (
        <div className="flex gap-1 border-b border-border">
            {tabs.map(([k, label]) => (
                <button key={k} onClick={() => setTab(k)}
                    className={`-mb-px rounded-t-lg px-3.5 py-2 text-[13px] font-medium ${tab === k ? 'border-b-2 border-brand-600 text-brand-700 dark:text-brand-300' : 'text-muted hover:text-fg'}`}>
                    {label}
                </button>
            ))}
        </div>
    );
}
export function Field({ label, error, children }: { label: string; error?: string[]; children: React.ReactNode }) {
    return (
        <div>
            <label className={labelCls}>{label}</label>
            {children}
            {error?.[0] && <p className="mt-1 text-[12px] text-rose-600">{error[0]}</p>}
        </div>
    );
}
export function Loading() {
    return <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>;
}
export function Empty({ label }: { label: string }) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
            <div className="grid h-12 w-12 place-items-center rounded-2xl bg-surface-2 text-faint"><Inbox size={22} /></div>
            <p className="text-[14px] font-medium text-fg">{label}</p>
        </div>
    );
}
