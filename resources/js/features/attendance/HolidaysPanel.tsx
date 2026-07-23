import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Loader2, Inbox } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { listHolidays, createHoliday, updateHoliday, deleteHoliday, type Holiday } from './api';

export function HolidaysPanel() {
    const qc = useQueryClient();
    const { data: holidays = [], isLoading } = useQuery({ queryKey: ['holidays'], queryFn: () => listHolidays() });

    const [editing, setEditing] = useState<Holiday | null>(null);
    const [creating, setCreating] = useState(false);
    const [deleting, setDeleting] = useState<Holiday | null>(null);
    const invalidate = () => qc.invalidateQueries({ queryKey: ['holidays'] });
    const del = useMutation({ mutationFn: (id: number) => deleteHoliday(id), onSuccess: () => { invalidate(); setDeleting(null); } });

    return (
        <Card>
            <div className="flex items-center justify-between px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Holidays</h3>
                    <p className="text-[12.5px] text-muted">Non-working days for this branch</p>
                </div>
                <Button onClick={() => setCreating(true)}><Plus size={16} /> Add holiday</Button>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : holidays.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                        <div className="grid h-12 w-12 place-items-center rounded-2xl bg-surface-2 text-faint"><Inbox size={22} /></div>
                        <p className="text-[14px] font-medium text-fg">No holidays yet</p>
                    </div>
                ) : (
                    <table className="w-full min-w-[560px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Date</th>
                                <th className="px-5 py-2.5 font-semibold">Title</th>
                                <th className="px-5 py-2.5 font-semibold">Type</th>
                                <th className="px-5 py-2.5 font-semibold">Status</th>
                                <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {holidays.map((h) => (
                                <tr key={h.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                    <td className="tnum px-5 py-3 text-muted">{h.date}</td>
                                    <td className="px-5 py-3">
                                        <div className="font-medium text-fg">{h.title}</div>
                                        {h.name_bn && <div className="text-[12px] text-faint">{h.name_bn}</div>}
                                    </td>
                                    <td className="px-5 py-3"><Badge tone="neutral">{h.type}</Badge></td>
                                    <td className="px-5 py-3"><Badge tone={h.status ? 'success' : 'neutral'}>{h.status ? 'Active' : 'Inactive'}</Badge></td>
                                    <td className="px-5 py-3">
                                        <div className="flex justify-end gap-1">
                                            <button onClick={() => setEditing(h)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600" aria-label="Edit"><Pencil size={16} /></button>
                                            <button onClick={() => setDeleting(h)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500" aria-label="Delete"><Trash2 size={16} /></button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {(creating || editing) && (
                <HolidayForm row={editing} onClose={() => { setCreating(false); setEditing(null); }} onSaved={() => { invalidate(); setCreating(false); setEditing(null); }} />
            )}

            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={() => deleting && del.mutate(deleting.id)}
                busy={del.isPending}
                title="Delete holiday"
                message={`Delete "${deleting?.title}"?`}
            />
        </Card>
    );
}

function HolidayForm({ row, onClose, onSaved }: { row: Holiday | null; onClose: () => void; onSaved: () => void }) {
    const [form, setForm] = useState({
        date: row?.date ?? new Date().toISOString().slice(0, 10),
        title: row?.title ?? '',
        name_bn: row?.name_bn ?? '',
        type: row?.type ?? 'public',
    });
    const [error, setError] = useState<string | null>(null);

    const save = useMutation({
        mutationFn: () => (row ? updateHoliday(row.id, form) : createHoliday(form)),
        onSuccess: onSaved,
        onError: (e) => setError(toApiError(e).message),
    });

    const inputCls = 'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';

    return (
        <Modal
            open onClose={onClose}
            title={row ? 'Edit holiday' : 'Add holiday'}
            footer={
                <>
                    <Button variant="outline" onClick={onClose} disabled={save.isPending}>Cancel</Button>
                    <Button onClick={() => { setError(null); save.mutate(); }} disabled={save.isPending || !form.title}>
                        {save.isPending ? <Loader2 size={16} className="animate-spin" /> : null}
                        {row ? 'Save changes' : 'Create holiday'}
                    </Button>
                </>
            }
        >
            {error && <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
            <div className="space-y-4">
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Date <span className="text-rose-500">*</span></label>
                    <input type="date" value={form.date} onChange={(e) => setForm((f) => ({ ...f, date: e.target.value }))} className={inputCls} />
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Title <span className="text-rose-500">*</span></label>
                    <input value={form.title} onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))} className={inputCls} />
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Title (বাংলা)</label>
                    <input value={form.name_bn} onChange={(e) => setForm((f) => ({ ...f, name_bn: e.target.value }))} className={inputCls} />
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Type</label>
                    <select value={form.type} onChange={(e) => setForm((f) => ({ ...f, type: e.target.value as Holiday['type'] }))} className={inputCls}>
                        <option value="public">Public</option>
                        <option value="weekend">Weekend</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
        </Modal>
    );
}
