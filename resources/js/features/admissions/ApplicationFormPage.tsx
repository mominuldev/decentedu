import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery } from '@tanstack/react-query';
import { Loader2, ArrowLeft } from 'lucide-react';
import { Button, Card } from '@/components/ui';
import { toApiError } from '@/lib/api';
import { cn } from '@/lib/cn';
import { listClassConfigs } from '@/features/academic/api';
import { GENDER_OPTIONS, RELIGION_OPTIONS, STATUS_TRANSITIONS, statusMeta } from './types';
import {
  createApplication, updateApplication, getApplication, listYears, listQuotas,
  type AdmissionApplication,
} from './api';

type FormState = {
  admission_year_id: number;
  class_config_id: number;
  quota_id: number | '';
  application_no: string;
  name: string;
  name_bn: string;
  sex: 'male' | 'female' | 'other';
  religion: string;
  blood_group: string;
  dob: string;
  birth_certificate_no: string;
  fathers_name: string;
  father_nid: string;
  father_mobile: string;
  mothers_name: string;
  mother_nid: string;
  mother_mobile: string;
  mobile: string;
  guardian_mobile: string;
  present_address: string;
  permanent_address: string;
  score: string;
  status: AdmissionApplication['status'];
  remarks: string;
};

export default function ApplicationFormPage() {
  const { id } = useParams<{ id: string }>();
  const isEdit = Boolean(id);

  const { data: application = null, isLoading } = useQuery({
    queryKey: ['admission-application', id],
    queryFn: () => getApplication(Number(id)),
    enabled: isEdit,
  });

  if (isEdit && isLoading) {
    return (
      <div className="flex items-center justify-center py-24 text-faint">
        <Loader2 size={24} className="animate-spin" />
      </div>
    );
  }

  return <ApplicationFormBody application={application} />;
}

function initForm(a: AdmissionApplication | null, defaultYearId: number): FormState {
  return {
    admission_year_id: a?.admission_year_id ?? defaultYearId,
    class_config_id: a?.class_config_id ?? 0,
    quota_id: a?.quota_id ?? '',
    application_no: a?.application_no ?? '',
    name: a?.name ?? '',
    name_bn: a?.name_bn ?? '',
    sex: a?.sex ?? 'male',
    religion: a?.religion ?? '',
    blood_group: a?.blood_group ?? '',
    dob: a?.dob ? a.dob.slice(0, 10) : '',
    birth_certificate_no: a?.birth_certificate_no ?? '',
    fathers_name: a?.fathers_name ?? '',
    father_nid: a?.father_nid ?? '',
    father_mobile: a?.father_mobile ?? a?.guardian_mobile ?? '',
    mothers_name: a?.mothers_name ?? '',
    mother_nid: a?.mother_nid ?? '',
    mother_mobile: a?.mother_mobile ?? '',
    mobile: a?.mobile ?? '',
    guardian_mobile: a?.guardian_mobile ?? '',
    present_address: a?.present_address ?? '',
    permanent_address: a?.permanent_address ?? '',
    score: a?.score != null ? String(a.score) : '',
    status: a?.status && a.status !== 'admitted' ? a.status : 'pending',
    remarks: a?.remarks ?? '',
  };
}

function ApplicationFormBody({ application }: { application: AdmissionApplication | null }) {
  const navigate = useNavigate();
  const toast = useToast();
  const { data: years = [] } = useQuery({ queryKey: ['admission-years'], queryFn: listYears });
  const { data: quotas = [] } = useQuery({ queryKey: ['admission-quotas'], queryFn: listQuotas });
  const { data: classConfigs = [] } = useQuery({ queryKey: ['class-configs'], queryFn: listClassConfigs });

  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (application) {
      setForm({
        admission_year_id: application.admission_year_id,
        class_config_id: application.class_config_id,
        quota_id: application.quota_id ?? '',
        application_no: application.application_no ?? '',
        name: application.name,
        name_bn: application.name_bn ?? '',
        sex: application.sex,
        religion: application.religion ?? '',
        blood_group: application.blood_group ?? '',
        dob: application.dob ? application.dob.slice(0, 10) : '',
        birth_certificate_no: application.birth_certificate_no ?? '',
        fathers_name: application.fathers_name ?? '',
        father_nid: application.father_nid ?? '',
        father_mobile: application.father_mobile ?? '',
        mothers_name: application.mothers_name ?? '',
        mother_nid: application.mother_nid ?? '',
        mother_mobile: application.mother_mobile ?? '',
        mobile: application.mobile ?? '',
        guardian_mobile: application.guardian_mobile ?? '',
        present_address: application.present_address ?? '',
        permanent_address: application.permanent_address ?? '',
        score: application.score != null ? String(application.score) : '',
        status: application.status,
        remarks: application.remarks ?? '',
      });
    } else if (years.length > 0 && !form.admission_year_id) {
      const openYear = years.find((y) => y.status === 'open') ?? years[0];
      setForm(f => ({ ...f, admission_year_id: openYear.id }));
    }
  }, [application, years]);

  if (isEdit && isLoadingApplication) {
    return (
      <div className="flex items-center justify-center py-24 text-faint">
        <Loader2 size={24} className="animate-spin" />
      </div>
    );
  }

  const set = <K extends keyof FormState>(key: K, value: FormState[K]) =>
    setForm((prev) => ({ ...prev, [key]: value }));

  const onCancel = () => navigate('/admissions');

  const saveMutation = useMutation({
    mutationFn: () => {
      const payload = {
        admission_year_id: Number(form.admission_year_id),
        class_config_id: Number(form.class_config_id),
        quota_id: form.quota_id === '' ? null : Number(form.quota_id),
        application_no: form.application_no || undefined,
        name: form.name,
        name_bn: form.name_bn || null,
        sex: form.sex,
        religion: form.religion || null,
        blood_group: form.blood_group || null,
        dob: form.dob || null,
        birth_certificate_no: form.birth_certificate_no,
        fathers_name: form.fathers_name,
        father_nid: form.father_nid,
        father_mobile: form.father_mobile,
        mothers_name: form.mothers_name,
        mother_nid: form.mother_nid,
        mother_mobile: form.mother_mobile || null,
        mobile: form.mobile || null,
        guardian_mobile: form.guardian_mobile || form.father_mobile || null,
        present_address: form.present_address || null,
        permanent_address: form.permanent_address || null,
        score: form.score === '' ? null : Number(form.score),
        status: form.status,
        remarks: form.remarks || null,
      };
      return application ? updateApplication(application.id, payload) : createApplication(payload);
    },
    onSuccess: () => {
      toast.success(application ? 'Application updated successfully' : 'Application created successfully');
      navigate('/admissions');
    },
    onError: (e) => {
      const apiError = toApiError(e);
      setError(apiError.errors ? null : apiError.message);
      setErrors(apiError.errors ?? {});
      toast.error(apiError.message || 'Could not save application');
    },
  });

  const handleSubmit = () => {
    setError(null);
    setErrors({});
    if (!form.admission_year_id || !form.class_config_id || !form.name || !form.birth_certificate_no || !form.fathers_name || !form.father_nid || !form.father_mobile || !form.mothers_name || !form.mother_nid) {
      setError('Admission year, class, applicant name, birth certificate number, father info (name, NID, mobile), mother name and mother NID are required.');
      return;
    }
    saveMutation.mutate();
  };

  const busy = saveMutation.isPending;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <Button variant="outline" onClick={onCancel} disabled={busy}>
            <ArrowLeft size={16} />
            Back
          </Button>
          <div>
            <h1 className="text-[22px] font-bold tracking-tight text-fg">
              {application ? 'Edit Application' : 'New Application'}
            </h1>
            <p className="mt-0.5 text-[13.5px] text-muted">
              {application ? `Editing ${application.application_no} · ${application.name}` : 'Register a prospective student’s application'}
            </p>
          </div>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" onClick={onCancel} disabled={busy}>Cancel</Button>
          <Button onClick={handleSubmit} disabled={busy}>
            {busy && <Loader2 size={16} className="animate-spin" />}
            {application ? 'Save Changes' : 'Create Application'}
          </Button>
        </div>
      </div>

      {error && (
        <div className="rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">
          {error}
        </div>
      )}

      {/* Admission details */}
      <Card className="p-6">
        <h3 className="mb-4 text-sm font-semibold text-fg">Admission Details</h3>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <SelectField
            label="Admission Year" required
            value={form.admission_year_id}
            onChange={(v) => set('admission_year_id', Number(v))}
            options={years.map((y) => ({ value: y.id, label: y.title + (y.status === 'closed' ? ' (closed)' : '') }))}
            placeholder="Select year"
            error={errors.admission_year_id?.[0]}
          />
          <SelectField
            label="Applied Class" required
            value={form.class_config_id}
            onChange={(v) => set('class_config_id', Number(v))}
            options={classConfigs.map((c) => ({ value: c.id, label: c.label }))}
            placeholder="Select class"
            error={errors.class_config_id?.[0]}
          />
          <SelectField
            label="Quota"
            value={form.quota_id}
            onChange={(v) => set('quota_id', v === '' ? '' : Number(v))}
            options={quotas.map((q) => ({ value: q.id, label: q.name }))}
            placeholder="None"
          />
          <FormField
            label="Application No." value={form.application_no}
            onChange={(v) => set('application_no', v)} placeholder="Auto (APP-0001)"
            error={errors.application_no?.[0]}
          />
          <FormField
            label="Merit Score" type="number" value={form.score}
            onChange={(v) => set('score', v)} error={errors.score?.[0]}
          />
          <SelectField
            label="Status" value={form.status}
            onChange={(v) => set('status', v as FormState['status'])}
            options={STATUS_TRANSITIONS.map((s) => ({ value: s, label: statusMeta(s).label }))}
          />
        </div>
      </Card>

      {/* Applicant */}
      <Card className="p-6">
        <h3 className="mb-4 text-sm font-semibold text-fg">Applicant</h3>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <FormField label="Applicant Name" required value={form.name} onChange={(v) => set('name', v)} error={errors.name?.[0]} />
          <FormField label="Name (Bangla)" value={form.name_bn} onChange={(v) => set('name_bn', v)} />
          <SelectField label="Gender" required value={form.sex} onChange={(v) => set('sex', v as FormState['sex'])} options={GENDER_OPTIONS.map((o) => ({ value: o.value, label: o.label }))} error={errors.sex?.[0]} />
          <SelectField label="Religion" value={form.religion} onChange={(v) => set('religion', v)} options={RELIGION_OPTIONS.map((o) => ({ value: o.value, label: o.label }))} placeholder="Select religion" error={errors.religion?.[0]} />
          <FormField label="Date of Birth" type="date" value={form.dob} onChange={(v) => set('dob', v)} />
          <FormField label="Birth Certificate Number" required value={form.birth_certificate_no} onChange={(v) => set('birth_certificate_no', v)} error={errors.birth_certificate_no?.[0]} />
          <FormField label="Blood Group" value={form.blood_group} onChange={(v) => set('blood_group', v)} />
        </div>
      </Card>

      {/* Parents & contact */}
      <Card className="p-6">
        <h3 className="mb-4 text-sm font-semibold text-fg">Parents & Contact</h3>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <FormField label="Father's Name" required value={form.fathers_name} onChange={(v) => set('fathers_name', v)} error={errors.fathers_name?.[0]} />
          <FormField label="Father's NID" required value={form.father_nid} onChange={(v) => set('father_nid', v)} error={errors.father_nid?.[0]} />
          <FormField label="Father's Mobile Number" required value={form.father_mobile} onChange={(v) => set('father_mobile', v)} error={errors.father_mobile?.[0]} />
          <FormField label="Mother's Name" required value={form.mothers_name} onChange={(v) => set('mothers_name', v)} error={errors.mothers_name?.[0]} />
          <FormField label="Mother's NID" required value={form.mother_nid} onChange={(v) => set('mother_nid', v)} error={errors.mother_nid?.[0]} />
          <FormField label="Mother's Mobile Number" value={form.mother_mobile} onChange={(v) => set('mother_mobile', v)} error={errors.mother_mobile?.[0]} />
          <FormField label="Applicant Mobile" value={form.mobile} onChange={(v) => set('mobile', v)} />
          <FormField label="Guardian Mobile" value={form.guardian_mobile} onChange={(v) => set('guardian_mobile', v)} />
        </div>
        <div className="mt-4 grid gap-4 sm:grid-cols-2">
          <TextAreaField label="Present Address" value={form.present_address} onChange={(v) => set('present_address', v)} />
          <TextAreaField label="Permanent Address" value={form.permanent_address} onChange={(v) => set('permanent_address', v)} />
        </div>
        <div className="mt-4">
          <FormField label="Remarks" value={form.remarks} onChange={(v) => set('remarks', v)} />
        </div>
      </Card>

      <div className="flex justify-end gap-2">
        <Button variant="outline" onClick={onCancel} disabled={busy}>Cancel</Button>
        <Button onClick={handleSubmit} disabled={busy}>
          {busy && <Loader2 size={16} className="animate-spin" />}
          {application ? 'Save Changes' : 'Create Application'}
        </Button>
      </div>
    </div>
  );
}

/* ---- Field helpers ------------------------------------------------------- */
const slugId = (prefix: string, label: string) => `${prefix}-${label.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')}`;

function FormField({
  label, value, onChange, error, type = 'text', required = false, placeholder,
}: {
  label: string;
  value: string | number;
  onChange: (value: string) => void;
  error?: string;
  type?: 'text' | 'number' | 'date';
  required?: boolean;
  placeholder?: string;
}) {
  const id = slugId('field', label);
  return (
    <div>
      <label htmlFor={id} className="mb-1.5 block text-[13px] font-medium text-fg">
        {label}{required && <span className="text-rose-500"> *</span>}
      </label>
      <input
        id={id} type={type} value={value} placeholder={placeholder}
        onChange={(e) => onChange(e.target.value)}
        className={cn(
          'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none placeholder:text-faint focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25',
          error ? 'border-rose-400' : '',
        )}
      />
      {error && <p className="mt-1 text-[12px] text-rose-500">{error}</p>}
    </div>
  );
}

function SelectField({
  label, value, onChange, options, error, placeholder, required = false,
}: {
  label: string;
  value: string | number;
  onChange: (value: string) => void;
  options: ReadonlyArray<{ value: string | number; label: string }>;
  error?: string;
  placeholder?: string;
  required?: boolean;
}) {
  const id = slugId('select', label);
  return (
    <div>
      <label htmlFor={id} className="mb-1.5 block text-[13px] font-medium text-fg">
        {label}{required && <span className="text-rose-500"> *</span>}
      </label>
      <select
        id={id} value={value}
        onChange={(e) => onChange(e.target.value)}
        className={cn(
          'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25',
          error ? 'border-rose-400' : '',
        )}
      >
        {placeholder && <option value="">{placeholder}</option>}
        {options.map((opt) => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
      </select>
      {error && <p className="mt-1 text-[12px] text-rose-500">{error}</p>}
    </div>
  );
}

function TextAreaField({
  label, value, onChange,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
}) {
  return (
    <div>
      <label className="mb-1.5 block text-[13px] font-medium text-fg">{label}</label>
      <textarea
        value={value} rows={3}
        onChange={(e) => onChange(e.target.value)}
        className="w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none placeholder:text-faint focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25"
      />
    </div>
  );
}
