import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Trash2, Loader2, Inbox } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { listEmployees } from '@/features/hr/api';
import { listStudents } from '@/features/students/api';
import { listDevices, listDeviceMaps, createDeviceMap, deleteDeviceMap, type DeviceMap } from './api';

export function DeviceMapPanel() {
    const qc = useQueryClient();
    const { data: devices = [] } = useQuery({ queryKey: ['attendance-devices'], queryFn: listDevices });
    const { data: maps = [], isLoading } = useQuery({ queryKey: ['device-maps'], queryFn: () => listDeviceMaps() });

    const [creating, setCreating] = useState(false);
    const [deleting, setDeleting] = useState<DeviceMap | null>(null);
    const invalidate = () => qc.invalidateQueries({ queryKey: ['device-maps'] });
    const del = useMutation({ mutationFn: (id: number) => deleteDeviceMap(id), onSuccess: () => { invalidate(); setDeleting(null); } });

    return (
        <Card>
            <div className="flex items-center justify-between px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Device mappings</h3>
                    <p className="text-[12.5px] text-muted">Link a device's internal user id to a student or employee</p>
                </div>
                <Button onClick={() => setCreating(true)} disabled={!devices.length}><Plus size={16} /> Add mapping</Button>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : maps.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                        <div className="grid h-12 w-12 place-items-center rounded-2xl bg-surface-2 text-faint"><Inbox size={22} /></div>
                        <p className="text-[14px] font-medium text-fg">No mappings yet</p>
                    </div>
                ) : (
                    <table className="w-full min-w-[600px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Device</th>
                                <th className="px-5 py-2.5 font-semibold">External ID</th>
                                <th className="px-5 py-2.5 font-semibold">Mapped to</th>
                                <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {maps.map((m) => (
                                <tr key={m.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                    <td className="px-5 py-3 text-muted">{m.device_name}</td>
                                    <td className="tnum px-5 py-3 text-muted">{m.external_user_id}</td>
                                    <td className="px-5 py-3">
                                        <div className="font-medium text-fg">{m.mappable_name ?? `#${m.mappable_id}`}</div>
                                        <Badge tone={m.mappable_type === 'student' ? 'brand' : 'sky'}>{m.mappable_type}</Badge>
                                    </td>
                                    <td className="px-5 py-3">
                                        <div className="flex justify-end">
                                            <button onClick={() => setDeleting(m)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500" aria-label="Delete"><Trash2 size={16} /></button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {creating && <MapForm devices={devices} onClose={() => setCreating(false)} onSaved={() => { invalidate(); setCreating(false); }} />}

            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={() => deleting && del.mutate(deleting.id)}
                busy={del.isPending}
                title="Remove mapping"
                message={`Unmap "${deleting?.mappable_name}" from ${deleting?.device_name}?`}
            />
        </Card>
    );
}

function MapForm({
    devices, onClose, onSaved,
}: {
    devices: { id: number; name: string }[];
    onClose: () => void;
    onSaved: () => void;
}) {
    const [form, setForm] = useState({
        attendance_device_id: devices[0]?.id ?? 0,
        external_user_id: '',
        mappable_type: 'student' as 'student' | 'employee',
        mappable_id: 0,
    });
    const [error, setError] = useState<string | null>(null);

    const { data: students } = useQuery({ queryKey: ['students', 'device-map-picker'], queryFn: () => listStudents({ per_page: 200 }), enabled: form.mappable_type === 'student' });
    const { data: employees } = useQuery({ queryKey: ['employees', 'device-map-picker'], queryFn: () => listEmployees({ per_page: 200 }), enabled: form.mappable_type === 'employee' });
    const people = form.mappable_type === 'student' ? (students?.data ?? []) : (employees?.data ?? []);

    const save = useMutation({
        mutationFn: () => createDeviceMap(form),
        onSuccess: onSaved,
        onError: (e) => setError(toApiError(e).message),
    });

    const inputCls = 'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';

    return (
        <Modal
            open onClose={onClose}
            title="Add device mapping"
            footer={
                <>
                    <Button variant="outline" onClick={onClose} disabled={save.isPending}>Cancel</Button>
                    <Button onClick={() => { setError(null); save.mutate(); }} disabled={save.isPending || !form.external_user_id || !form.mappable_id}>
                        {save.isPending ? <Loader2 size={16} className="animate-spin" /> : null}
                        Create mapping
                    </Button>
                </>
            }
        >
            {error && <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
            <div className="space-y-4">
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Device <span className="text-rose-500">*</span></label>
                    <select value={form.attendance_device_id} onChange={(e) => setForm((f) => ({ ...f, attendance_device_id: Number(e.target.value) }))} className={inputCls}>
                        {devices.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                    </select>
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">External user id (from device) <span className="text-rose-500">*</span></label>
                    <input value={form.external_user_id} onChange={(e) => setForm((f) => ({ ...f, external_user_id: e.target.value }))} placeholder="e.g. S0001" className={inputCls} />
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Type</label>
                    <select value={form.mappable_type} onChange={(e) => setForm((f) => ({ ...f, mappable_type: e.target.value as 'student' | 'employee', mappable_id: 0 }))} className={inputCls}>
                        <option value="student">Student</option>
                        <option value="employee">Employee</option>
                    </select>
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Person <span className="text-rose-500">*</span></label>
                    <select value={form.mappable_id} onChange={(e) => setForm((f) => ({ ...f, mappable_id: Number(e.target.value) }))} className={inputCls}>
                        <option value={0} disabled>Select {form.mappable_type}</option>
                        {people.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                    </select>
                </div>
            </div>
        </Modal>
    );
}
