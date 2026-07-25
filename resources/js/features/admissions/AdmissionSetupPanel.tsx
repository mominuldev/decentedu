import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, CalendarRange, Ticket } from 'lucide-react';
import { Card, CardHeader, Button, Badge, IconButton } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import {
  listYears, createYear, updateYear, deleteYear,
  listQuotas, createQuota, updateQuota, deleteQuota,
  type AdmissionYear, type Quota,
} from './api';

const field = 'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none placeholder:text-faint focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';
const label = 'block text-[13px] font-medium text-muted mb-1.5';

export function AdmissionSetupPanel() {
  return (
    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <YearsCard />
      <QuotasCard />
    </div>
  );
}

/* ---- Admission years ----------------------------------------------------- */
function YearsCard() {
  const qc = useQueryClient();
  const { data: years = [], isLoading } = useQuery({ queryKey: ['admission-years'], queryFn: listYears });
  const [editing, setEditing] = useState<AdmissionYear | null>(null);
  const [adding, setAdding] = useState(false);
  const [deleting, setDeleting] = useState<AdmissionYear | null>(null);
  const [error, setError] = useState<string | null>(null);

  const del = useMutation({
    mutationFn: (id: number) => deleteYear(id),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['admission-years'] }); setDeleting(null); },
    onError: (e) => setError(toApiError(e).message),
  });

  return (
    <Card>
      <CardHeader
        title="Admission years"
        subtitle="Drives that applications belong to"
        action={<Button size="sm" onClick={() => { setError(null); setAdding(true); }}><Plus size={15} />Add</Button>}
      />
      <div className="px-5 pb-5 pt-3">
        {error && <p className="mb-3 text-[13px] text-rose-600">{error}</p>}
        {isLoading ? (
          <p className="py-6 text-center text-[13.5px] text-muted">Loading…</p>
        ) : years.length === 0 ? (
          <EmptyRow icon={<CalendarRange size={20} />} text="No admission years yet." />
        ) : (
          <ul className="divide-y divide-border">
            {years.map((y) => (
              <li key={y.id} className="flex items-center justify-between gap-3 py-3">
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <span className="truncate text-[14px] font-medium text-fg">{y.title}</span>
                    <Badge tone={y.status === 'open' ? 'success' : 'neutral'} size="sm">{y.status}</Badge>
                  </div>
                  <p className="mt-0.5 text-[12.5px] text-muted">
                    {y.academic_year?.name ? `Session ${y.academic_year.name} · ` : ''}
                    {y.applications_count ?? 0} application{(y.applications_count ?? 0) === 1 ? '' : 's'}
                  </p>
                </div>
                <div className="flex shrink-0 items-center gap-1">
                  <IconButton onClick={() => { setError(null); setEditing(y); }} aria-label="Edit"><Pencil size={15} /></IconButton>
                  <IconButton onClick={() => { setError(null); setDeleting(y); }} aria-label="Delete"><Trash2 size={15} /></IconButton>
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>

      {(adding || editing) && (
        <YearForm year={editing} onClose={() => { setAdding(false); setEditing(null); }} />
      )}
      <ConfirmDialog
        open={!!deleting}
        onClose={() => setDeleting(null)}
        onConfirm={() => deleting && del.mutate(deleting.id)}
        busy={del.isPending}
        title="Delete admission year"
        message={`Delete "${deleting?.title}"? This cannot be undone.`}
      />
    </Card>
  );
}

function YearForm({ year, onClose }: { year: AdmissionYear | null; onClose: () => void }) {
  const qc = useQueryClient();
  const isEdit = !!year;
  const [title, setTitle] = useState(year?.title ?? '');
  const [status, setStatus] = useState<'open' | 'closed'>(year?.status ?? 'open');
  const [startDate, setStartDate] = useState(year?.start_date?.slice(0, 10) ?? '');
  const [endDate, setEndDate] = useState(year?.end_date?.slice(0, 10) ?? '');
  const [error, setError] = useState<string | null>(null);

  const save = useMutation({
    mutationFn: () => {
      const payload = { title, status, start_date: startDate || null, end_date: endDate || null };
      return isEdit ? updateYear(year!.id, payload) : createYear(payload);
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['admission-years'] }); onClose(); },
    onError: (e) => setError(toApiError(e).message),
  });

  return (
    <Modal
      open onClose={onClose} title={isEdit ? 'Edit admission year' : 'New admission year'}
      footer={<>
        <Button variant="outline" onClick={onClose} disabled={save.isPending}>Cancel</Button>
        <Button onClick={() => { setError(null); if (!title.trim()) { setError('Title is required.'); return; } save.mutate(); }} disabled={save.isPending}>
          {save.isPending ? 'Saving…' : 'Save'}
        </Button>
      </>}
    >
      {error && <p className="mb-3 text-[13px] text-rose-600">{error}</p>}
      <div className="space-y-4">
        <div><label className={label}>Title *</label><input className={field} value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Admission 2026" /></div>
        <div><label className={label}>Status</label>
          <select className={field} value={status} onChange={(e) => setStatus(e.target.value as 'open' | 'closed')}>
            <option value="open">Open</option><option value="closed">Closed</option>
          </select>
        </div>
        <div className="grid grid-cols-2 gap-3">
          <div><label className={label}>Start date</label><input type="date" className={field} value={startDate} onChange={(e) => setStartDate(e.target.value)} /></div>
          <div><label className={label}>End date</label><input type="date" className={field} value={endDate} onChange={(e) => setEndDate(e.target.value)} /></div>
        </div>
      </div>
    </Modal>
  );
}

/* ---- Quotas -------------------------------------------------------------- */
function QuotasCard() {
  const qc = useQueryClient();
  const { data: quotas = [], isLoading } = useQuery({ queryKey: ['admission-quotas'], queryFn: listQuotas });
  const [editing, setEditing] = useState<Quota | null>(null);
  const [adding, setAdding] = useState(false);
  const [deleting, setDeleting] = useState<Quota | null>(null);
  const [error, setError] = useState<string | null>(null);

  const del = useMutation({
    mutationFn: (id: number) => deleteQuota(id),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['admission-quotas'] }); setDeleting(null); },
    onError: (e) => setError(toApiError(e).message),
  });

  return (
    <Card>
      <CardHeader
        title="Seat quotas"
        subtitle="Reservation categories & caps"
        action={<Button size="sm" onClick={() => { setError(null); setAdding(true); }}><Plus size={15} />Add</Button>}
      />
      <div className="px-5 pb-5 pt-3">
        {error && <p className="mb-3 text-[13px] text-rose-600">{error}</p>}
        {isLoading ? (
          <p className="py-6 text-center text-[13.5px] text-muted">Loading…</p>
        ) : quotas.length === 0 ? (
          <EmptyRow icon={<Ticket size={20} />} text="No quotas yet." />
        ) : (
          <ul className="divide-y divide-border">
            {quotas.map((q) => (
              <li key={q.id} className="flex items-center justify-between gap-3 py-3">
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <span className="truncate text-[14px] font-medium text-fg">{q.name}</span>
                    {!q.status && <Badge tone="neutral" size="sm">inactive</Badge>}
                  </div>
                  <p className="mt-0.5 text-[12.5px] text-muted">
                    {q.capacity != null ? `${q.capacity} seats` : 'Uncapped'}
                    {q.description ? ` · ${q.description}` : ''}
                  </p>
                </div>
                <div className="flex shrink-0 items-center gap-1">
                  <IconButton onClick={() => { setError(null); setEditing(q); }} aria-label="Edit"><Pencil size={15} /></IconButton>
                  <IconButton onClick={() => { setError(null); setDeleting(q); }} aria-label="Delete"><Trash2 size={15} /></IconButton>
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>

      {(adding || editing) && (
        <QuotaForm quota={editing} onClose={() => { setAdding(false); setEditing(null); }} />
      )}
      <ConfirmDialog
        open={!!deleting}
        onClose={() => setDeleting(null)}
        onConfirm={() => deleting && del.mutate(deleting.id)}
        busy={del.isPending}
        title="Delete quota"
        message={`Delete "${deleting?.name}"? This cannot be undone.`}
      />
    </Card>
  );
}

function QuotaForm({ quota, onClose }: { quota: Quota | null; onClose: () => void }) {
  const qc = useQueryClient();
  const isEdit = !!quota;
  const [name, setName] = useState(quota?.name ?? '');
  const [description, setDescription] = useState(quota?.description ?? '');
  const [capacity, setCapacity] = useState(quota?.capacity != null ? String(quota.capacity) : '');
  const [status, setStatus] = useState(quota?.status ?? true);
  const [error, setError] = useState<string | null>(null);

  const save = useMutation({
    mutationFn: () => {
      const payload = { name, description: description || null, capacity: capacity === '' ? null : Number(capacity), status };
      return isEdit ? updateQuota(quota!.id, payload) : createQuota(payload);
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['admission-quotas'] }); onClose(); },
    onError: (e) => setError(toApiError(e).message),
  });

  return (
    <Modal
      open onClose={onClose} title={isEdit ? 'Edit quota' : 'New quota'}
      footer={<>
        <Button variant="outline" onClick={onClose} disabled={save.isPending}>Cancel</Button>
        <Button onClick={() => { setError(null); if (!name.trim()) { setError('Name is required.'); return; } save.mutate(); }} disabled={save.isPending}>
          {save.isPending ? 'Saving…' : 'Save'}
        </Button>
      </>}
    >
      {error && <p className="mb-3 text-[13px] text-rose-600">{error}</p>}
      <div className="space-y-4">
        <div><label className={label}>Name *</label><input className={field} value={name} onChange={(e) => setName(e.target.value)} placeholder="Freedom Fighter" /></div>
        <div><label className={label}>Description</label><input className={field} value={description} onChange={(e) => setDescription(e.target.value)} /></div>
        <div className="grid grid-cols-2 gap-3">
          <div><label className={label}>Capacity</label><input type="number" min={0} className={field} value={capacity} onChange={(e) => setCapacity(e.target.value)} placeholder="Uncapped" /></div>
          <div><label className={label}>Status</label>
            <select className={field} value={status ? '1' : '0'} onChange={(e) => setStatus(e.target.value === '1')}>
              <option value="1">Active</option><option value="0">Inactive</option>
            </select>
          </div>
        </div>
      </div>
    </Modal>
  );
}

function EmptyRow({ icon, text }: { icon: React.ReactNode; text: string }) {
  return (
    <div className="flex flex-col items-center gap-2 py-8 text-center text-muted">
      <div className="grid h-10 w-10 place-items-center rounded-xl bg-surface-2 text-faint">{icon}</div>
      <p className="text-[13.5px]">{text}</p>
    </div>
  );
}
