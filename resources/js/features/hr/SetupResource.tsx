import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Loader2, Inbox } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { cn } from '@/lib/cn';

export interface FieldDef {
  name: string;
  label: string;
  type: 'text' | 'number' | 'textarea' | 'checkbox';
  required?: boolean;
  placeholder?: string;
}

interface SetupResourceProps {
  resource: string;
  singular: string;
  listFn: () => Promise<any[]>;
  createFn: (payload: any) => Promise<any>;
  fields: FieldDef[];
}

export function SetupResource({
  resource,
  singular,
  listFn,
  createFn,
  fields,
}: SetupResourceProps) {
  const qc = useQueryClient();

  const { data: rows = [], isLoading } = useQuery({
    queryKey: ['hr-setup', resource],
    queryFn: listFn,
  });

  const [editing, setEditing] = useState<any | null>(null);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<any | null>(null);

  const invalidate = () => qc.invalidateQueries({ queryKey: ['hr-setup', resource] });

  const del = useMutation({
    mutationFn: (id: number) => fetch(`/api/v1/hr/${resource}/${id}`, { method: 'DELETE' }),
    onSuccess: () => { invalidate(); setDeleting(null); },
  });

  return (
    <Card>
      <div className="flex items-center justify-between px-5 py-4">
        <div>
          <h3 className="text-[15px] font-semibold text-fg">{singular} list</h3>
          <p className="text-[12.5px] text-muted">{rows.length} record{rows.length === 1 ? '' : 's'} in this branch</p>
        </div>
        <Button onClick={() => setCreating(true)}><Plus size={16} /> Add {singular.toLowerCase()}</Button>
      </div>

      <div className="overflow-x-auto border-t border-border">
        {isLoading ? (
          <div className="flex items-center justify-center gap-2 py-16 text-muted">
            <Loader2 size={18} className="animate-spin" /> Loading…
          </div>
        ) : rows.length === 0 ? (
          <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
            <div className="grid h-12 w-12 place-items-center rounded-2xl bg-surface-2 text-faint"><Inbox size={22} /></div>
            <p className="text-[14px] font-medium text-fg">No {singular.toLowerCase()} yet</p>
            <p className="text-[13px] text-muted">Add your first {singular.toLowerCase()} to get started.</p>
          </div>
        ) : (
          <table className="w-full min-w-[560px] text-left text-[13.5px]">
            <thead>
              <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                <th className="px-5 py-2.5 font-semibold">Name</th>
                {fields.filter(f => f.name !== 'name' && f.name !== 'name_bn').map((f) => (
                  <th key={f.name} className="px-5 py-2.5 font-semibold">{f.label}</th>
                ))}
                <th className="px-5 py-2.5 font-semibold">Status</th>
                <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                  <td className="px-5 py-3">
                    <div className="font-medium text-fg">{r.name}</div>
                    {r.name_bn && <div className="text-[12px] text-faint">{r.name_bn}</div>}
                  </td>
                  {fields.filter(f => f.name !== 'name' && f.name !== 'name_bn').map((f) => (
                    <td key={f.name} className="px-5 py-3 text-muted">
                      {f.type === 'checkbox'
                        ? (r[f.name] ? <Badge tone="brand">Yes</Badge> : <span className="text-faint">—</span>)
                        : f.type === 'textarea'
                          ? (r[f.name] ? <span className="truncate max-w-[200px] block">{r[f.name]}</span> : <span className="text-faint">—</span>)
                          : ((r[f.name] as string) || '—')}
                    </td>
                  ))}
                  <td className="px-5 py-3">
                    <Badge tone={r.status ? 'success' : 'neutral'}>{r.status ? 'Active' : 'Inactive'}</Badge>
                  </td>
                  <td className="px-5 py-3">
                    <div className="flex justify-end gap-1">
                      <button onClick={() => setEditing(r)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600" aria-label="Edit">
                        <Pencil size={16} />
                      </button>
                      <button onClick={() => setDeleting(r)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500" aria-label="Delete">
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

      {(creating || editing) && (
        <SetupForm
          fields={fields}
          row={editing}
          onClose={() => { setCreating(false); setEditing(null); }}
          onSaved={() => { invalidate(); setCreating(false); setEditing(null); }}
          createFn={createFn}
        />
      )}

      <ConfirmDialog
        open={!!deleting}
        onClose={() => setDeleting(null)}
        onConfirm={() => deleting && del.mutate(deleting.id)}
        busy={del.isPending}
        title={`Delete ${singular.toLowerCase()}`}
        message={`Are you sure you want to delete "${deleting?.name}"? This can be restored by an administrator.`}
      />
    </Card>
  );
}

function defaults(fields: FieldDef[], row: any | null): Record<string, unknown> {
  const out: Record<string, unknown> = { status: row ? row.status : true };
  for (const f of fields) {
    if (f.name !== 'description') { // Skip description for defaults
      out[f.name] = row ? (row[f.name] ?? (f.type === 'checkbox' ? false : '')) : (f.type === 'checkbox' ? false : f.type === 'number' ? 0 : '');
    }
  }
  return out;
}

function SetupForm({
  fields, row, onClose, onSaved, createFn,
}: {
  fields: FieldDef[];
  row: any | null;
  onClose: () => void;
  onSaved: (payload: any) => void;
  createFn: (payload: any) => Promise<any>;
}) {
  const [form, setForm] = useState<Record<string, unknown>>(() => defaults(fields, row));
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [error, setError] = useState<string | null>(null);

  const saveMutation = useMutation({
    mutationFn: () => createFn(form),
    onSuccess: () => onSaved(form),
    onError: (e) => { const a = toApiError(e); setError(a.errors ? null : a.message); setErrors(a.errors ?? {}); },
  });

  const set = (k: string, v: unknown) => setForm((f) => ({ ...f, [k]: v }));

  return (
    <Modal
      open
      onClose={onClose}
      title={row ? `Edit ${row.name || 'Item'}` : `Add new item`}
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saveMutation.isPending}>Cancel</Button>
          <Button onClick={() => { setError(null); setErrors({}); saveMutation.mutate(); }} disabled={saveMutation.isPending}>
            {saveMutation.isPending ? <Loader2 size={16} className="animate-spin" /> : null}
            {row ? 'Save changes' : 'Create'}
          </Button>
        </>
      }
    >
      {error && <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
      <div className="space-y-4">
        {fields.map((f) => (
          <div key={f.name}>
            {f.type === 'checkbox' ? (
              <label className="flex cursor-pointer select-none items-center gap-2.5 text-[13.5px] text-fg">
                <input type="checkbox" checked={!!form[f.name]} onChange={(e) => set(f.name, e.target.checked)}
                  className="h-4 w-4 rounded border-border-strong text-brand-600 focus:ring-brand-500" />
                {f.label}
              </label>
            ) : f.type === 'textarea' ? (
              <>
                <label className="mb-1.5 block text-[13px] font-medium text-fg">
                  {f.label}{f.required && <span className="text-rose-500"> *</span>}
                </label>
                <textarea
                  value={String(form[f.name] ?? '')}
                  onChange={(e) => set(f.name, e.target.value)}
                  placeholder={f.placeholder}
                  rows={3}
                  className={cn(
                    'w-full rounded-xl border bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none placeholder:text-faint focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25',
                    errors[f.name] ? 'border-rose-400' : 'border-border-strong',
                  )}
                />
              </>
            ) : (
              <>
                <label className="mb-1.5 block text-[13px] font-medium text-fg">
                  {f.label}{f.required && <span className="text-rose-500"> *</span>}
                </label>
                <input
                  type={f.type === 'number' ? 'number' : 'text'}
                  value={String(form[f.name] ?? '')}
                  onChange={(e) => set(f.name, f.type === 'number' ? Number(e.target.value) : e.target.value)}
                  placeholder={f.placeholder}
                  className={cn(
                    'w-full rounded-xl border bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none placeholder:text-faint focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25',
                    errors[f.name] ? 'border-rose-400' : 'border-border-strong',
                  )}
                />
              </>
            )}
            {errors[f.name] && <p className="mt-1.5 text-[12px] text-rose-500">{errors[f.name][0]}</p>}
          </div>
        ))}

        <div>
          <label className="mb-1.5 block text-[13px] font-medium text-fg">Status</label>
          <select
            value={form.status ? '1' : '0'} onChange={(e) => set('status', e.target.value === '1')}
            className="w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25"
          >
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </div>
      </div>
    </Modal>
  );
}