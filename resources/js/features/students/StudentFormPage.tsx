import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery } from '@tanstack/react-query';
import { Loader2, X, Plus, ArrowLeft } from 'lucide-react';
import { Button, Card } from '@/components/ui';
import { toApiError } from '@/lib/api';
import { cn } from '@/lib/cn';
import { createStudent, updateStudent, getStudent, type Student, type CreateStudentRequest } from './api';
import {
  type StudentFormData,
  GENDER_OPTIONS,
  STATUS_OPTIONS,
  RELATIONSHIP_OPTIONS,
  BLOOD_GROUP_OPTIONS,
} from './types';
import { listSetup, listClassConfigs } from '@/features/academic/api';

export default function StudentFormPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const isEdit = Boolean(id);

  const { data: student = null, isLoading: isLoadingStudent } = useQuery({
    queryKey: ['student', id],
    queryFn: () => getStudent(Number(id)),
    enabled: isEdit,
  });

  if (isEdit && isLoadingStudent) {
    return (
      <div className="flex items-center justify-center py-24 text-faint">
        <Loader2 size={24} className="animate-spin" />
      </div>
    );
  }

  return (
    <StudentFormBody
      student={student}
      onCancel={() => navigate('/students')}
      onSaved={() => navigate('/students')}
    />
  );
}

function StudentFormBody({
  student,
  onCancel,
  onSaved,
}: {
  student: Student | null;
  onCancel: () => void;
  onSaved: () => void;
}) {
  // Fetch setup data for dropdowns
  const { data: academicYears = [] } = useQuery({
    queryKey: ['setup', 'academic-years'],
    queryFn: () => listSetup('academic-years'),
  });

  const { data: classConfigs = [] } = useQuery({
    queryKey: ['class-configs'],
    queryFn: () => listClassConfigs(),
  });

  const { data: groups = [] } = useQuery({
    queryKey: ['setup', 'groups'],
    queryFn: () => listSetup('groups'),
  });

  const { data: categories = [] } = useQuery({
    queryKey: ['setup', 'categories'],
    queryFn: () => listSetup('categories'),
  });

  const [form, setForm] = useState<StudentFormData>(() => initializeForm(student));
  const [guardians, setGuardians] = useState(() =>
    student?.guardians?.map(g => ({
      relationship: g.relationship,
      name: g.name,
      mobile: g.mobile || '',
      email: g.email || '',
      address: g.address || '',
      occupation: g.occupation || '',
      nid: g.nid || '',
      is_emergency_contact: g.is_emergency_contact,
    })) || []
  );
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [error, setError] = useState<string | null>(null);
  const [showGuardianForm, setShowGuardianForm] = useState(false);
  const [newGuardian, setNewGuardian] = useState({
    relationship: 'father' as const,
    name: '',
    mobile: '',
    email: '',
    address: '',
    occupation: '',
    nid: '',
    is_emergency_contact: false,
  });

  const saveMutation = useMutation({
    mutationFn: (payload: CreateStudentRequest) =>
      student ? updateStudent(student.id, payload) : createStudent(payload),
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

    const payload: CreateStudentRequest = {
      ...form,
      academic_year_id: Number(form.academic_year_id),
      class_config_id: Number(form.class_config_id),
      group_id: form.group_id ? Number(form.group_id) : undefined,
      category_id: form.category_id ? Number(form.category_id) : undefined,
      guardians: guardians.length > 0 ? guardians : undefined,
    };

    saveMutation.mutate(payload);
  };

  const addGuardian = () => {
    if (newGuardian.name && newGuardian.relationship) {
      setGuardians([...guardians, { ...newGuardian }]);
      setNewGuardian({
        relationship: 'father',
        name: '',
        mobile: '',
        email: '',
        address: '',
        occupation: '',
        nid: '',
        is_emergency_contact: false,
      });
      setShowGuardianForm(false);
    }
  };

  const removeGuardian = (index: number) => {
    setGuardians(guardians.filter((_, i) => i !== index));
  };

  const setField = <K extends keyof StudentFormData>(field: K, value: StudentFormData[K]) => {
    setForm(prev => ({ ...prev, [field]: value }));
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <Button variant="outline" onClick={onCancel} disabled={saveMutation.isPending}>
            <ArrowLeft size={16} />
            Back
          </Button>
          <div>
            <h1 className="text-[22px] font-bold tracking-tight text-fg">
              {student ? 'Edit Student' : 'Add New Student'}
            </h1>
            <p className="mt-0.5 text-[13.5px] text-muted">
              {student ? `Editing ${student.name}` : 'Fill in the details to register a new student'}
            </p>
          </div>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" onClick={onCancel} disabled={saveMutation.isPending}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={saveMutation.isPending}>
            {saveMutation.isPending && <Loader2 size={16} className="animate-spin" />}
            {student ? 'Save Changes' : 'Create Student'}
          </Button>
        </div>
      </div>

      {error && (
        <div className="rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700">
          {error}
        </div>
      )}

      <div className="space-y-6">
        {/* Basic Information */}
        <Card className="p-6">
          <h3 className="text-sm font-semibold text-fg mb-4">Basic Information</h3>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <FormField
              label="Student UID"
              value={form.student_uid}
              onChange={(v) => setField('student_uid', v)}
              error={errors.student_uid?.[0]}
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
            <SelectField
              label="Status"
              value={form.status}
              onChange={(v) => setField('status', v as any)}
              options={STATUS_OPTIONS}
            />
            <FormField
              label="Date of Birth"
              type="date"
              value={form.dob}
              onChange={(v) => setField('dob', v)}
            />
            <FormField label="Religion" value={form.religion} onChange={(v) => setField('religion', v)} />
            <SelectField
              label="Blood Group"
              value={form.blood_group}
              onChange={(v) => setField('blood_group', v)}
              options={BLOOD_GROUP_OPTIONS}
              placeholder="Select blood group"
            />
          </div>
        </Card>

        {/* Parents Information */}
        <Card className="p-6">
          <h3 className="text-sm font-semibold text-fg mb-4">Parents Information</h3>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <FormField
              label="Father's Name"
              value={form.fathers_name}
              onChange={(v) => setField('fathers_name', v)}
              error={errors.fathers_name?.[0]}
              required
            />
            <FormField
              label="Mother's Name"
              value={form.mothers_name}
              onChange={(v) => setField('mothers_name', v)}
              error={errors.mothers_name?.[0]}
              required
            />
            <FormField
              label="Father's Mobile"
              value={form.father_mobile}
              onChange={(v) => setField('father_mobile', v)}
            />
            <FormField
              label="Mother's Mobile"
              value={form.mother_mobile}
              onChange={(v) => setField('mother_mobile', v)}
            />
          </div>
        </Card>

        {/* Contact & Address */}
        <Card className="p-6">
          <h3 className="text-sm font-semibold text-fg mb-4">Contact & Address</h3>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <FormField label="Student Mobile" value={form.mobile} onChange={(v) => setField('mobile', v)} />
            <FormField label="Photo URL" value={form.photo_path} onChange={(v) => setField('photo_path', v)} />
          </div>
          <div className="mt-4 grid gap-4 sm:grid-cols-2">
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
        </Card>

        {/* Enrollment Information */}
        <Card className="p-6">
          <h3 className="text-sm font-semibold text-fg mb-4">Enrollment Information</h3>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <SelectField
              label="Academic Year"
              value={form.academic_year_id}
              onChange={(v) => setField('academic_year_id', v as any)}
              options={academicYears.map(y => ({ value: y.id, label: y.name }))}
              error={errors.academic_year_id?.[0]}
              required
            />
            <SelectField
              label="Class"
              value={form.class_config_id}
              onChange={(v) => setField('class_config_id', v as any)}
              options={classConfigs.map(c => ({ value: c.id, label: c.label }))}
              error={errors.class_config_id?.[0]}
              required
            />
            <FormField
              label="Roll Number"
              value={form.roll}
              onChange={(v) => setField('roll', v)}
              error={errors.roll?.[0]}
              required
            />
            <SelectField
              label="Group"
              value={form.group_id}
              onChange={(v) => setField('group_id', v as any)}
              options={groups.map(g => ({ value: g.id, label: g.name }))}
              placeholder="Optional"
            />
            <SelectField
              label="Category"
              value={form.category_id}
              onChange={(v) => setField('category_id', v as any)}
              options={categories.map(c => ({ value: c.id, label: c.name }))}
              placeholder="Optional"
            />
          </div>
        </Card>

        {/* Guardians */}
        <Card className="p-6">
          <div className="mb-4 flex items-center justify-between">
            <h3 className="text-sm font-semibold text-fg">Guardians</h3>
            <Button size="sm" onClick={() => setShowGuardianForm(true)}>
              <Plus size={14} />
              Add Guardian
            </Button>
          </div>

          {guardians.length > 0 ? (
            <div className="space-y-2">
              {guardians.map((guardian, index) => (
                <div key={index} className="flex items-center justify-between rounded-lg border border-border p-3">
                  <div className="flex items-center gap-3">
                    <span className="font-medium text-fg">{guardian.name}</span>
                    <span className="text-sm text-muted">({guardian.relationship})</span>
                    {guardian.is_emergency_contact && (
                      <span className="rounded bg-brand-100 px-2 py-0.5 text-xs text-brand-700">
                        Emergency
                      </span>
                    )}
                  </div>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => removeGuardian(index)}
                  >
                    <X size={14} />
                  </Button>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-sm text-muted">No guardians added yet</p>
          )}

          {showGuardianForm && (
            <Card className="mt-3 p-4">
              <div className="grid gap-3 sm:grid-cols-2">
                <SelectField
                  label="Relationship"
                  value={newGuardian.relationship}
                  onChange={(v) => setNewGuardian({ ...newGuardian, relationship: v as any })}
                  options={RELATIONSHIP_OPTIONS}
                  required
                />
                <FormField
                  label="Name"
                  value={newGuardian.name}
                  onChange={(v) => setNewGuardian({ ...newGuardian, name: v })}
                  required
                />
                <FormField
                  label="Mobile"
                  value={newGuardian.mobile}
                  onChange={(v) => setNewGuardian({ ...newGuardian, mobile: v })}
                />
                <FormField
                  label="Email"
                  value={newGuardian.email}
                  onChange={(v) => setNewGuardian({ ...newGuardian, email: v })}
                />
                <FormField
                  label="Occupation"
                  value={newGuardian.occupation}
                  onChange={(v) => setNewGuardian({ ...newGuardian, occupation: v })}
                />
                <FormField
                  label="NID"
                  value={newGuardian.nid}
                  onChange={(v) => setNewGuardian({ ...newGuardian, nid: v })}
                />
                <TextAreaField
                  label="Address"
                  value={newGuardian.address}
                  onChange={(v) => setNewGuardian({ ...newGuardian, address: v })}
                  className="sm:col-span-2"
                />
                <label className="flex cursor-pointer items-center gap-2 text-sm text-fg">
                  <input
                    type="checkbox"
                    checked={newGuardian.is_emergency_contact}
                    onChange={(e) => setNewGuardian({ ...newGuardian, is_emergency_contact: e.target.checked })}
                    className="h-4 w-4 rounded border-border text-brand-600"
                  />
                  Emergency Contact
                </label>
              </div>
              <div className="mt-4 flex justify-end gap-2">
                <Button variant="outline" size="sm" onClick={() => setShowGuardianForm(false)}>
                  Cancel
                </Button>
                <Button size="sm" onClick={addGuardian}>
                  Add Guardian
                </Button>
              </div>
            </Card>
          )}
        </Card>
      </div>

      <div className="flex justify-end gap-2">
        <Button variant="outline" onClick={onCancel} disabled={saveMutation.isPending}>
          Cancel
        </Button>
        <Button onClick={handleSubmit} disabled={saveMutation.isPending}>
          {saveMutation.isPending && <Loader2 size={16} className="animate-spin" />}
          {student ? 'Save Changes' : 'Create Student'}
        </Button>
      </div>
    </div>
  );
}

function initializeForm(student: Student | null): StudentFormData {
  return {
    student_uid: student?.student_uid || '',
    name: student?.name || '',
    name_bn: student?.name_bn || '',
    sex: student?.sex || 'male',
    religion: student?.religion || '',
    blood_group: student?.blood_group || '',
    dob: student?.dob?.split('T')[0] || '',
    fathers_name: student?.fathers_name || '',
    mothers_name: student?.mothers_name || '',
    mobile: student?.mobile || '',
    father_mobile: student?.father_mobile || '',
    mother_mobile: student?.mother_mobile || '',
    photo_path: student?.photo_path || '',
    present_address: student?.present_address || '',
    permanent_address: student?.permanent_address || '',
    status: student?.status || 'active',
    // Get current enrollment if exists, otherwise default values
    academic_year_id: student?.current_enrollment?.academic_year_id || 0,
    class_config_id: student?.current_enrollment?.class_config_id || 0,
    group_id: student?.current_enrollment?.group_id || 0,
    category_id: student?.current_enrollment?.category_id || 0,
    roll: student?.current_enrollment?.roll || '',
    guardians: [],
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
  onChange: (value: string | number) => void;
  options: Array<{ value: string | number; label: string }>;
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
          'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25',
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
  className = '',
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  className?: string;
}) {
  return (
    <div className={className}>
      <label className="mb-1.5 block text-[13px] font-medium text-fg">{label}</label>
      <textarea
        value={value}
        onChange={(e) => onChange(e.target.value)}
        rows={3}
        className="w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none placeholder:text-faint focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25"
      />
    </div>
  );
}
