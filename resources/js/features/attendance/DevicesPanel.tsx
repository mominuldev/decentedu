import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Loader2, Inbox, RefreshCw } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { listDevices, createDevice, updateDevice, deleteDevice, processPunches, type AttendanceDevice } from './api';

export function DevicesPanel() {
    const qc = useQueryClient();
    const { data: devices = [], isLoading } = useQuery({ queryKey: ['attendance-devices'], queryFn: listDevices });

    const [editing, setEditing] = useState<AttendanceDevice | null>(null);
    const [creating, setCreating] = useState(false);
    const [deleting, setDeleting] = useState<AttendanceDevice | null>(null);
    const invalidate = () => qc.invalidateQueries({ queryKey: ['attendance-devices'] });

    const del = useMutation({ mutationFn: (id: number) => deleteDevice(id), onSuccess: () => { invalidate(); setDeleting(null); } });
    const sync = useMutation({ mutationFn: processPunches });

    return (
        <Card>
            <div className="flex items-center justify-between px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Biometric devices</h3>
                    <p className="text-[12.5px] text-muted">Registered scanners at each gate/room</p>
                </div>
                <div className="flex items-center gap-2">
                    <Button variant="outline" onClick={() => sync.mutate()} disabled={sync.isPending}>
                        {sync.isPending ? <Loader2 size={16} className="animate-spin" /> : <RefreshCw size={16} />}
                        Sync punches now
                    </Button>
                    <Button onClick={() => setCreating(true)}><Plus size={16} /> Add device</Button>
                </div>
            </div>
            {sync.isSuccess && (
                <div className="mx-5 mb-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-[13px] text-emerald-700 dark:border-emerald-500/25 dark:bg-emerald-500/10 dark:text-emerald-300">
                    Punch processing queued — resolved attendance will appear shortly.
                </div>
            )}

            <div className="overflow-x-auto border-t border-border">
                {isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : devices.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                        <div className="grid h-12 w-12 place-items-center rounded-2xl bg-surface-2 text-faint"><Inbox size={22} /></div>
                        <p className="text-[14px] font-medium text-fg">No devices yet</p>
                    </div>
                ) : (
                    <table className="w-full min-w-[600px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Device</th>
                                <th className="px-5 py-2.5 font-semibold">Device UID</th>
                                <th className="px-5 py-2.5 font-semibold">Location</th>
                                <th className="px-5 py-2.5 font-semibold">Status</th>
                                <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {devices.map((d) => (
                                <tr key={d.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                    <td className="px-5 py-3 font-medium text-fg">{d.name}</td>
                                    <td className="px-5 py-3 text-muted">{d.device_uid}</td>
                                    <td className="px-5 py-3 text-muted">{d.location ?? '—'}</td>
                                    <td className="px-5 py-3"><Badge tone={d.status ? 'success' : 'neutral'}>{d.status ? 'Active' : 'Inactive'}</Badge></td>
                                    <td className="px-5 py-3">
                                        <div className="flex justify-end gap-1">
                                            <button onClick={() => setEditing(d)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600" aria-label="Edit"><Pencil size={16} /></button>
                                            <button onClick={() => setDeleting(d)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500" aria-label="Delete"><Trash2 size={16} /></button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {(creating || editing) && (
                <DeviceForm row={editing} onClose={() => { setCreating(false); setEditing(null); }} onSaved={() => { invalidate(); setCreating(false); setEditing(null); }} />
            )}

            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={() => deleting && del.mutate(deleting.id)}
                busy={del.isPending}
                title="Delete device"
                message={`Delete "${deleting?.name}"? Its mappings will also be removed.`}
            />
        </Card>
    );
}

function DeviceForm({ row, onClose, onSaved }: { row: AttendanceDevice | null; onClose: () => void; onSaved: () => void }) {
    const [form, setForm] = useState({
        name: row?.name ?? '',
        device_uid: row?.device_uid ?? '',
        location: row?.location ?? '',
        protocol: row?.protocol ?? 'generic',
    });
    const [error, setError] = useState<string | null>(null);

    const save = useMutation({
        mutationFn: () => (row ? updateDevice(row.id, form) : createDevice(form)),
        onSuccess: onSaved,
        onError: (e) => setError(toApiError(e).message),
    });

    const inputCls = 'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';

    return (
        <Modal
            open onClose={onClose}
            title={row ? 'Edit device' : 'Add device'}
            footer={
                <>
                    <Button variant="outline" onClick={onClose} disabled={save.isPending}>Cancel</Button>
                    <Button onClick={() => { setError(null); save.mutate(); }} disabled={save.isPending || !form.name || !form.device_uid}>
                        {save.isPending ? <Loader2 size={16} className="animate-spin" /> : null}
                        {row ? 'Save changes' : 'Create device'}
                    </Button>
                </>
            }
        >
            {error && <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
            <div className="space-y-4">
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Name <span className="text-rose-500">*</span></label>
                    <input value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} placeholder="e.g. Main Gate Scanner" className={inputCls} />
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Device UID <span className="text-rose-500">*</span></label>
                    <input value={form.device_uid} onChange={(e) => setForm((f) => ({ ...f, device_uid: e.target.value }))} placeholder="e.g. DEV-01" className={inputCls} />
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Location</label>
                    <input value={form.location} onChange={(e) => setForm((f) => ({ ...f, location: e.target.value }))} className={inputCls} />
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Protocol</label>
                    <select value={form.protocol} onChange={(e) => setForm((f) => ({ ...f, protocol: e.target.value as 'zkteco' | 'generic' }))} className={inputCls}>
                        <option value="generic">Generic</option>
                        <option value="zkteco">ZKTeco</option>
                    </select>
                </div>
            </div>
        </Modal>
    );
}
