import { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { Loader2, Plus, X, BookOpen } from 'lucide-react';
import { Modal } from '@/components/Modal';
import { Button } from '@/components/ui';
import { Card } from '@/components/ui';
import { FileUpload } from '@/components/FileUpload';
import { toApiError } from '@/lib/api';
import { cn } from '@/lib/cn';
import { createEmployee, updateEmployee, type Employee, type CreateEmployeeRequest } from './api';
import { GENDER_OPTIONS, STATUS_OPTIONS } from './types';
import { listDesignations, listHrSections } from './api';
import { listSetup, listClassConfigs } from '@/features/academic/api';

interface EmployeeFormProps {
  employee: Employee | null;
  onClose: () => void;
  onSaved: () => void;
}

export function EmployeeForm({ employee, onClose, onSaved }: EmployeeFormProps) {
  // Fetch setup data for dropdowns
  const { data: designations = [] } = useQuery({
    queryKey: ['hr-setup', 'designations'],
    queryFn: () => listDesignations(),
  });

  const { data: hrSections = [] } = useQuery({
    queryKey: ['hr-setup', 'hr-sections'],
    queryFn: () => listHrSections(),
  });

  const { data: subjects = [] } = useQuery({
    queryKey: ['setup', 'subjects'],
    queryFn: () => listSetup('subjects'),
  });

  const { data: classConfigs = [] } = useQuery({
    queryKey: ['class-configs'],
    queryFn: () => listClassConfigs(),
  });

  const [form, setForm] = useState(() => initializeForm(employee));
  const [subjectAssignments, setSubjectAssignments] = useState(() =>
    employee?.subject_teachers?.map(st => ({
      subject_id: st.subject_id,
      class_config_id: st.class_config_id,
    })) || []
  );

  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [error, setError] = useState<string | null>(null);
  const [showAssignmentForm, setShowAssignmentForm] = useState(false);
  const [newAssignment, setNewAssignment] = useState({
    subject_id: 0,
    class_config_id: 0,
  });

  const saveMutation = useMutation({
    mutationFn: (payload: CreateEmployeeRequest) =>
      employee ? updateEmployee(employee.id, payload) : createEmployee(payload),
    onSuccess: onSaved,
    onError: (e) => {
      const apiError = toApiError(e);
      setError(apiError.errors ? null : apiError.message);
      setErrors(apiError.errors ?? {});
    },
  });

  const handleSubmit = () => {
    setError(null);
    setErrors({});

    const payload: CreateEmployeeRequest = {
      ...form,
      designation_id: Number(form.designation_id),
      hr_section_id: form.hr_section_id ? Number(form.hr_section_id) : undefined,
      joining_date: form.joining_date,
      subject_assignments: subjectAssignments.length > 0 ? subjectAssignments : undefined,
    };

    saveMutation.mutate(payload);
  };

  const addSubjectAssignment = () => {
    if (newAssignment.subject_id && newAssignment.class_config_id) {
      setSubjectAssignments([...subjectAssignments, { ...newAssignment }]);
      setNewAssignment({ subject_id: 0, class_config_id: 0 });
      setShowAssignmentForm(false);
    }
  };

  const removeSubjectAssignment = (index: number) => {
    setSubjectAssignments(subjectAssignments.filter((_, i) => i !== index));
  };

  const setField = <K extends keyof typeof form>(field: K, value: typeof form[K]) => {
    setForm(prev => ({ ...prev, [field]: value }));
  };

  return (
    <Modal
      open
      onClose={onClose}
      title={employee ? 'Edit Employee' : 'Add New Employee'}
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saveMutation.isPending}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={saveMutation.isPending}>
            {saveMutation.isPending && <Loader2 size={16} className="animate-spin" />}
            {employee ? 'Save Changes' : 'Create Employee'}
          </Button>
        </>
      }
      width="max-w-3xl"
    >
      {error && (
        <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700">
          {error}
        </div>
      )}

      <div className="max-h-[70vh] space-y-6 overflow-y-auto px-1">
        {/* Basic Information */}
        <div>
          <h3 className="text-sm font-semibold text-fg mb-4">Basic Information</h3>
          <div className="grid gap-4 sm:grid-cols-2">
            <FormField
              label="Employee UID"
              value={form.employee_uid}
              onChange={(v) => setField('employee_uid', v)}
              error={errors.employee_uid?.[0]}
              required
            />
            <FormField
              label="Name"
              value={form.name}
              onChange={(v) => setField('name', v)}
              error={errors.name?.[0]}
              required
            />
            <FormField
              label="Name (Bangla)"
              value={form.name_bn}
              onChange={(v) => setField('name_bn', v)}
            />
            <SelectField
              label="Gender"
              value={form.sex}
              onChange={(v) => setField('sex', v as any)}
              options={GENDER_OPTIONS}
              error={errors.sex?.[0]}
              required
            />
            <FormField label="Religion" value={form.religion} onChange={(v) => setField('religion', v)} />
            <FormField
              label="Date of Birth"
              type="date"
              value={form.dob}
              onChange={(v) => setField('dob', v)}
            />
          </div>
        </div>

        {/* Employment Details */}
        <div>
          <h3 className="text-sm font-semibold text-fg mb-4">Employment Details</h3>
          <div className="grid gap-4 sm:grid-cols-2">
            <SelectField
              label="Designation"
              value={form.designation_id}
              onChange={(v) => setField('designation_id', v as any)}
              options={designations.map(d => ({ value: d.id, label: d.name }))}
              error={errors.designation_id?.[0]}
              required
            />
            <SelectField
              label="Department"
              value={form.hr_section_id}
              onChange={(v) => setField('hr_section_id', v as any)}
              options={hrSections.map(h => ({ value: h.id, label: h.name }))}
              placeholder="Optional"
            />
            <SelectField
              label="Employment Type"
              value={form.employment_type}
              onChange={(v) => setField('employment_type', v as any)}
              options={[
                { value: 'permanent', label: 'Permanent' },
                { value: 'contract', label: 'Contract' },
                { value: 'temporary', label: 'Temporary' },
              ]}
            />
            <FormField
              label="Joining Date"
              type="date"
              value={form.joining_date}
              onChange={(v) => setField('joining_date', v)}
              error={errors.joining_date?.[0]}
              required
            />
            <SelectField
              label="Status"
              value={form.status}
              onChange={(v) => setField('status', v as any)}
              options={STATUS_OPTIONS}
            />
            <FormField
              label="Leaving Date"
              type="date"
              value={form.leaving_date}
              onChange={(v) => setField('leaving_date', v)}
            />
          </div>
        </div>

        {/* Contact & Address */}
        <div>
          <h3 className="text-sm font-semibold text-fg mb-4">Contact & Address</h3>
          <div className="grid gap-4 sm:grid-cols-2">
            <FormField label="Mobile" value={form.mobile} onChange={(v) => setField('mobile', v)} />
            <FormField label="Email" value={form.email} onChange={(v) => setField('email', v)} />
            <FormField label="NID" value={form.nid} onChange={(v) => setField('nid', v)} />
            <FileUpload label="Photo" category="photo" value={form.photo_path || null} onChange={(v) => setField('photo_path', v ?? '')} />
          </div>
          <TextAreaField
            label="Present Address"
            value={form.present_address}
            onChange={(v) => setField('present_address', v)}
          />
          <TextAreaField
            label="Permanent Address"
            value={form.permanent_address}
            onChange={(v) => setField('permanent_address', v)}
          />
        </div>

        {/* Qualifications */}
        <div>
          <h3 className="text-sm font-semibold text-fg mb-4">Qualifications</h3>
          <TextAreaField
            label="Educational Qualifications"
            value={form.qualifications?.join('\n') || ''}
            onChange={(v) => setField('qualifications', v.split('\n').filter(q => q.trim()))}
            placeholder="Enter each qualification on a new line"
          />
        </div>

        {/* Subject Assignments (for teachers) */}
        <div>
          <div className="mb-4 flex items-center justify-between">
            <h3 className="text-sm font-semibold text-fg">Subject Assignments</h3>
            <Button size="sm" onClick={() => setShowAssignmentForm(true)}>
              <Plus size={14} />
              Add Assignment
            </Button>
          </div>

          {subjectAssignments.length > 0 ? (
            <div className="space-y-2">
              {subjectAssignments.map((assignment, index) => {
                const subjectName = subjects.find(s => s.id === assignment.subject_id)?.name || 'Unknown';
                const className = classConfigs.find(c => c.id === assignment.class_config_id)?.label || 'Unknown';
                return (
                  <div key={index} className="flex items-center justify-between rounded-lg border border-border p-3">
                    <div className="flex items-center gap-3">
                      <BookOpen size={16} className="text-faint" />
                      <span className="font-medium text-fg">{subjectName}</span>
                      <span className="text-sm text-muted">→ {className}</span>
                    </div>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => removeSubjectAssignment(index)}
                    >
                      <X size={14} />
                    </Button>
                  </div>
                );
              })}
            </div>
          ) : (
            <p className="text-sm text-muted">No subject assignments added (for teachers only)</p>
          )}

          {showAssignmentForm && (
            <Card className="mt-3 p-4">
              <div className="grid gap-3 sm:grid-cols-2">
                <SelectField
                  label="Subject"
                  value={newAssignment.subject_id}
                  onChange={(v) => setNewAssignment({ ...newAssignment, subject_id: v as any })}
                  options={subjects.map(s => ({ value: s.id, label: s.name }))}
                  required
                />
                <SelectField
                  label="Class"
                  value={newAssignment.class_config_id}
                  onChange={(v) => setNewAssignment({ ...newAssignment, class_config_id: v as any })}
                  options={classConfigs.map(c => ({ value: c.id, label: c.label }))}
                  required
                />
              </div>
              <div className="mt-4 flex justify-end gap-2">
                <Button variant="outline" size="sm" onClick={() => setShowAssignmentForm(false)}>
                  Cancel
                </Button>
                <Button size="sm" onClick={addSubjectAssignment}>
                  Add Assignment
                </Button>
              </div>
            </Card>
          )}
        </div>
      </div>
    </Modal>
  );
}

function initializeForm(employee: Employee | null) {
  return {
    employee_uid: employee?.employee_uid || '',
    name: employee?.name || '',
    name_bn: employee?.name_bn || '',
    sex: employee?.sex || 'male',
    religion: employee?.religion || '',
    dob: employee?.dob?.split('T')[0] || '',
    designation_id: employee?.designation?.id || 0,
    hr_section_id: employee?.hr_section?.id || 0,
    mobile: employee?.mobile || '',
    email: employee?.email || '',
    nid: employee?.nid || '',
    photo_path: employee?.photo_path || '',
    present_address: employee?.present_address || '',
    permanent_address: employee?.permanent_address || '',
    joining_date: employee?.joining_date?.split('T')[0] || '',
    leaving_date: employee?.leaving_date?.split('T')[0] || '',
    employment_type: employee?.employment_type || 'permanent',
    status: employee?.status || 'active',
    qualifications: employee?.qualifications || [],
  };
}

// Helper Components
function FormField({
  label,
  value,
  onChange,
  error,
  type = 'text',
  required = false,
}: {
  label: string;
  value: string | number;
  onChange: (value: string) => void;
  error?: string;
  type?: 'text' | 'number' | 'date';
  required?: boolean;
}) {
  return (
    <div>
      <label className="mb-1.5 block text-[13px] font-medium text-fg">
        {label}
        {required && <span className="text-rose-500"> *</span>}
      </label>
      <input
        type={type}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className={cn(
          'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none placeholder:text-faint focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25',
          error ? 'border-rose-400' : ''
        )}
      />
      {error && <p className="mt-1 text-[12px] text-rose-500">{error}</p>}
    </div>
  );
}

function SelectField({
  label,
  value,
  onChange,
  options,
  error,
  placeholder,
  required = false,
}: {
  label: string;
  value: string | number;
  onChange: (value: string) => void;
  options: ReadonlyArray<{ value: string | number; label: string }>;
  error?: string;
  placeholder?: string;
  required?: boolean;
}) {
  return (
    <div>
      <label className="mb-1.5 block text-[13px] font-medium text-fg">
        {label}
        {required && <span className="text-rose-500"> *</span>}
      </label>
      <select
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className={cn(
          'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none placeholder:text-faint focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25',
          error ? 'border-rose-400' : ''
        )}
      >
        {placeholder && <option value="">{placeholder}</option>}
        {options.map((opt) => (
          <option key={opt.value} value={opt.value}>
            {opt.label}
          </option>
        ))}
      </select>
      {error && <p className="mt-1 text-[12px] text-rose-500">{error}</p>}
    </div>
  );
}

function TextAreaField({
  label,
  value,
  onChange,
  placeholder,
  className = '',
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  className?: string;
}) {
  return (
    <div className={className}>
      <label className="mb-1.5 block text-[13px] font-medium text-fg">{label}</label>
      <textarea
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        rows={3}
        className="w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none placeholder:text-faint focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25"
      />
    </div>
  );
}