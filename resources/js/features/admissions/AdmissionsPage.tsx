import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  Plus, Search, UserPlus, GraduationCap, Pencil, Trash2, MoreVertical,
  CheckCircle2, Clock, XCircle, Hourglass, Users,
} from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { ConfirmDialog } from '@/components/Modal';
import { listClassConfigs } from '@/features/academic/api';
import {
  listApplications, listYears, getStats, setApplicationStatus, deleteApplication,
  type AdmissionApplication, type ApplicationStatus,
} from './api';
import { statusMeta, STATUS_TRANSITIONS } from './types';
import { ConvertDialog } from './ConvertDialog';
import { AdmissionSetupPanel } from './AdmissionSetupPanel';

type Tab = 'applications' | 'setup';

const control = 'rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';

import { useToast } from '@/components/Toast';
import { toApiError } from '@/lib/api';

export default function AdmissionsPage() {
  const qc = useQueryClient();
  const navigate = useNavigate();
  const globalToast = useToast();
  const [tab, setTab] = useState<Tab>('applications');
  const [search, setSearch] = useState('');
  const [yearId, setYearId] = useState<number>(0);
  const [status, setStatus] = useState<string>('');
  const [page, setPage] = useState(1);

  const [converting, setConverting] = useState<AdmissionApplication | null>(null);
  const [deleting, setDeleting] = useState<AdmissionApplication | null>(null);

  const { data: years = [] } = useQuery({ queryKey: ['admission-years'], queryFn: listYears });
  const { data: classConfigs = [] } = useQuery({ queryKey: ['class-configs'], queryFn: listClassConfigs });

  const { data: stats } = useQuery({
    queryKey: ['admission-stats', yearId],
    queryFn: () => getStats(yearId || undefined),
    enabled: tab === 'applications',
  });

  const filters = useMemo(() => ({
    search: search || undefined,
    admission_year_id: yearId || undefined,
    status: status || undefined,
    page,
    per_page: 25,
  }), [search, yearId, status, page]);

  const { data: resp, isLoading } = useQuery({
    queryKey: ['admission-applications', filters],
    queryFn: () => listApplications(filters),
    enabled: tab === 'applications',
  });

  const applications = resp?.data ?? [];
  const pagination = resp?.meta?.pagination;

  const statusMutation = useMutation({
    mutationFn: ({ id, next }: { id: number; next: ApplicationStatus }) => setApplicationStatus(id, next),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admission-applications'] });
      qc.invalidateQueries({ queryKey: ['admission-stats'] });
      globalToast.success('Application status updated successfully');
    },
    onError: (err) => globalToast.error(toApiError(err).message),
  });

  const delMutation = useMutation({
    mutationFn: (id: number) => deleteApplication(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admission-applications'] });
      qc.invalidateQueries({ queryKey: ['admission-stats'] });
      setDeleting(null);
      globalToast.success('Application deleted successfully');
    },
    onError: (err) => globalToast.error(toApiError(err).message),
  });

  const resetPage = () => setPage(1);

  const kpis = [
    { key: 'total', label: 'Total', value: stats?.total ?? 0, icon: Users, tone: 'brand' as const },
    { key: 'pending', label: 'Pending', value: stats?.pending ?? 0, icon: Clock, tone: 'neutral' as const },
    { key: 'selected', label: 'Selected', value: stats?.selected ?? 0, icon: CheckCircle2, tone: 'success' as const },
    { key: 'waiting', label: 'Waiting', value: stats?.waiting ?? 0, icon: Hourglass, tone: 'warning' as const },
    { key: 'rejected', label: 'Rejected', value: stats?.rejected ?? 0, icon: XCircle, tone: 'danger' as const },
    { key: 'admitted', label: 'Admitted', value: stats?.admitted ?? 0, icon: GraduationCap, tone: 'brand' as const },
  ];

  return (
    <div className="space-y-6">

      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-[26px] font-bold tracking-tight text-fg">Admissions</h1>
          <p className="mt-1 text-[14px] text-muted">
            {tab === 'applications'
              ? `${pagination?.total ?? 0} application${pagination?.total === 1 ? '' : 's'}`
              : 'Admission years & seat quotas'}
          </p>
        </div>
        {tab === 'applications' && (
          <Button onClick={() => navigate('/admissions/applications/new')}><Plus size={16} />New application</Button>
        )}
      </div>

      {/* Tabs */}
      <div className="flex gap-2 border-b border-border">
        <TabButton active={tab === 'applications'} onClick={() => setTab('applications')} icon={<UserPlus size={18} />}>Applications</TabButton>
        <TabButton active={tab === 'setup'} onClick={() => setTab('setup')} icon={<GraduationCap size={18} />}>Setup</TabButton>
      </div>

      {tab === 'setup' ? (
        <AdmissionSetupPanel />
      ) : (
        <>
          {/* KPI cards */}
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            {kpis.map((k) => (
              <Card key={k.key} className="p-4">
                <div className="flex items-center gap-2 text-muted">
                  <k.icon size={16} />
                  <span className="text-[12.5px] font-medium">{k.label}</span>
                </div>
                <div className="mt-1.5 text-[24px] font-bold tracking-tight text-fg">{k.value}</div>
              </Card>
            ))}
          </div>

          {/* Filters */}
          <Card className="p-4">
            <div className="flex flex-wrap items-center gap-3">
              <div className="relative min-w-[240px] flex-1">
                <Search size={18} className="absolute left-3 top-1/2 -translate-y-1/2 text-faint" />
                <input
                  className={`${control} w-full pl-10`}
                  placeholder="Search name, application no, father, mobile…"
                  value={search}
                  onChange={(e) => { setSearch(e.target.value); resetPage(); }}
                />
              </div>
              <select className={control} value={yearId} onChange={(e) => { setYearId(Number(e.target.value)); resetPage(); }}>
                <option value={0}>All years</option>
                {years.map((y) => <option key={y.id} value={y.id}>{y.title}</option>)}
              </select>
              <select className={control} value={status} onChange={(e) => { setStatus(e.target.value); resetPage(); }}>
                <option value="">All statuses</option>
                {['pending', 'selected', 'waiting', 'rejected', 'admitted'].map((s) => (
                  <option key={s} value={s}>{statusMeta(s as ApplicationStatus).label}</option>
                ))}
              </select>
            </div>
          </Card>

          {/* Table */}
          <Card className="overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-left text-[13.5px]">
                <thead>
                  <tr className="border-b border-border text-[12px] uppercase tracking-wide text-faint">
                    <th className="px-5 py-3 font-semibold">Applicant</th>
                    <th className="px-5 py-3 font-semibold">Class</th>
                    <th className="px-5 py-3 font-semibold">Quota</th>
                    <th className="px-5 py-3 font-semibold">Score</th>
                    <th className="px-5 py-3 font-semibold">Status</th>
                    <th className="px-5 py-3 text-right font-semibold">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {isLoading ? (
                    <tr><td colSpan={6} className="px-5 py-10 text-center text-muted">Loading applications…</td></tr>
                  ) : applications.length === 0 ? (
                    <tr><td colSpan={6} className="px-5 py-10 text-center text-muted">No applications match these filters.</td></tr>
                  ) : (
                    applications.map((a, index) => (
                      <Row
                        key={a.id}
                        app={a}
                        isLast={index >= applications.length - 2}
                        onEdit={() => navigate(`/admissions/applications/${a.id}/edit`)}
                        onConvert={() => setConverting(a)}
                        onDelete={() => setDeleting(a)}
                        onStatus={(next) => statusMutation.mutate({ id: a.id, next })}
                      />
                    ))
                  )}
                </tbody>
              </table>
            </div>

            {pagination && pagination.last_page > 1 && (
              <div className="flex items-center justify-between border-t border-border px-5 py-3 text-[13px] text-muted">
                <span>Page {pagination.current_page} of {pagination.last_page}</span>
                <div className="flex gap-2">
                  <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>Previous</Button>
                  <Button variant="outline" size="sm" disabled={page >= pagination.last_page} onClick={() => setPage((p) => p + 1)}>Next</Button>
                </div>
              </div>
            )}
          </Card>
        </>
      )}

      {converting && (
        <ConvertDialog
          application={converting}
          classConfigs={classConfigs}
          onClose={() => setConverting(null)}
          onConverted={(r) => { setConverting(null); globalToast.success(`Admitted — student ${r.student_uid} created.`); }}
        />
      )}

      <ConfirmDialog
        open={!!deleting}
        onClose={() => setDeleting(null)}
        onConfirm={() => deleting && delMutation.mutate(deleting.id)}
        busy={delMutation.isPending}
        title="Delete application"
        message={`Delete application ${deleting?.application_no} (${deleting?.name})? This cannot be undone.`}
      />
    </div>
  );
}

function Row({ app, isLast, onEdit, onConvert, onDelete, onStatus }: {
  app: AdmissionApplication;
  isLast?: boolean;
  onEdit: () => void;
  onConvert: () => void;
  onDelete: () => void;
  onStatus: (next: ApplicationStatus) => void;
}) {
  const [menuOpen, setMenuOpen] = useState(false);
  const meta = statusMeta(app.status);
  const admitted = app.status === 'admitted';

  return (
    <tr className="hover:bg-surface-2/50">
      <td className="px-5 py-3">
        <div className="font-medium text-fg">{app.name}</div>
        <div className="text-[12px] text-muted">{app.application_no} · {app.fathers_name}</div>
      </td>
      <td className="px-5 py-3 text-muted">{app.class_config?.name ?? '—'}</td>
      <td className="px-5 py-3 text-muted">{app.quota?.name ?? '—'}</td>
      <td className="px-5 py-3 tabular-nums text-muted">{app.score ?? '—'}</td>
      <td className="px-5 py-3"><Badge tone={meta.tone} size="sm">{meta.label}</Badge></td>
      <td className="px-5 py-3">
        <div className="flex items-center justify-end gap-1">
          {!admitted && app.status !== 'rejected' && (
            <Button size="sm" onClick={onConvert}><GraduationCap size={14} />Admit</Button>
          )}
          {admitted && <span className="text-[12px] text-emerald-600 dark:text-emerald-400">Enrolled</span>}
          {!admitted && (
            <div className="relative">
              <button className="grid h-8 w-8 cursor-pointer place-items-center rounded-lg text-muted hover:bg-surface-2 hover:text-fg" onClick={() => setMenuOpen((o) => !o)} aria-label="More">
                <MoreVertical size={16} />
              </button>
              {menuOpen && (
                <>
                  <div className="fixed inset-0 z-10" onClick={() => setMenuOpen(false)} />
                  <div className={`absolute right-0 z-20 w-44 rounded-xl border border-border bg-surface p-1 shadow-[var(--shadow-pop)] ${isLast ? 'bottom-full mb-1' : 'top-full mt-1'}`}>
                    <MenuItem onClick={() => { setMenuOpen(false); onEdit(); }}><Pencil size={14} />Edit</MenuItem>
                    <div className="my-1 border-t border-border" />
                    <div className="px-2.5 py-1 text-[11px] uppercase tracking-wide text-faint">Set status</div>
                    {STATUS_TRANSITIONS.filter((s) => s !== app.status).map((s) => (
                      <MenuItem key={s} onClick={() => { setMenuOpen(false); onStatus(s); }}>
                        <Badge tone={statusMeta(s).tone} size="sm">{statusMeta(s).label}</Badge>
                      </MenuItem>
                    ))}
                    <div className="my-1 border-t border-border" />
                    <MenuItem onClick={() => { setMenuOpen(false); onDelete(); }} danger><Trash2 size={14} />Delete</MenuItem>
                  </div>
                </>
              )}
            </div>
          )}
        </div>
      </td>
    </tr>
  );
}

function MenuItem({ children, onClick, danger }: { children: React.ReactNode; onClick: () => void; danger?: boolean }) {
  return (
    <button
      onClick={onClick}
      className={`flex w-full cursor-pointer items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-[13px] hover:bg-surface-2 ${danger ? 'text-rose-600 dark:text-rose-400' : 'text-fg'}`}
    >
      {children}
    </button>
  );
}

function TabButton({ active, onClick, icon, children }: { active: boolean; onClick: () => void; icon: React.ReactNode; children: React.ReactNode }) {
  return (
    <button
      onClick={onClick}
      className={`flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium transition-colors ${
        active ? 'border-brand-600 text-brand-700 dark:text-brand-400' : 'border-transparent text-muted hover:text-fg'
      }`}
    >
      {icon}
      {children}
    </button>
  );
}
