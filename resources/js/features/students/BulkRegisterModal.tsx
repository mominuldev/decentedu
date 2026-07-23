import { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { Loader2, Upload, X, Check, AlertCircle } from 'lucide-react';
import { Modal } from '@/components/Modal';
import { Button } from '@/components/ui';
import { useAuth } from '@/features/auth/AuthProvider';
import { toApiError } from '@/lib/api';
import { bulkRegister, type BulkRegisterRequest } from './api';
import { listSetup, listClassConfigs } from '@/features/academic/api';
import { GENDER_OPTIONS } from './types';

interface BulkRegisterModalProps {
  onClose: () => void;
  onSaved: () => void;
}

export function BulkRegisterModal({ onClose, onSaved }: BulkRegisterModalProps) {
  const { session } = useAuth();

  // Fetch setup data
  const { data: academicYears = [] } = useQuery({
    queryKey: ['setup', 'academic-years'],
    queryFn: () => listSetup('academic-years'),
  });

  const { data: classConfigs = [] } = useQuery({
    queryKey: ['class-configs'],
    queryFn: () => listClassConfigs(),
  });

  const [academicYearId, setAcademicYearId] = useState<number>(0);
  const [classConfigId, setClassConfigId] = useState<number>(0);
  const [students, setStudents] = useState<Array<{
    student_uid: string;
    name: string;
    sex: 'male' | 'female' | 'other';
    fathers_name: string;
    mothers_name: string;
    roll: string;
    mobile?: string;
  }>>([
    {
      student_uid: '',
      name: '',
      sex: 'male',
      fathers_name: '',
      mothers_name: '',
      roll: '',
      mobile: '',
    },
  ]);

  const [result, setResult] = useState<{
    created: Array<{ index: number; student_uid: string; id: number }>;
    failed: Array<{ index: number; student_uid: string; error: string }>;
    summary: { total: number; created_count: number; failed_count: number };
  } | null>(null);

  const [error, setError] = useState<string | null>(null);

  const bulkMutation = useMutation({
    mutationFn: (payload: BulkRegisterRequest) => bulkRegister(payload),
    onSuccess: (data) => {
      setResult(data);
    },
    onError: (e) => {
      const apiError = toApiError(e);
      setError(apiError.message);
    },
  });

  const handleSubmit = () => {
    setError(null);
    setResult(null);

    if (!academicYearId || !classConfigId) {
      setError('Please select academic year and class');
      return;
    }

    const validStudents = students.filter(
      (s) => s.student_uid && s.name && s.fathers_name && s.mothers_name && s.roll
    );

    if (validStudents.length === 0) {
      setError('Please add at least one student with required fields');
      return;
    }

    bulkMutation.mutate({
      academic_year_id: academicYearId,
      class_config_id: classConfigId,
      students: validStudents,
    });
  };

  const addStudentRow = () => {
    setStudents([
      ...students,
      {
        student_uid: '',
        name: '',
        sex: 'male',
        fathers_name: '',
        mothers_name: '',
        roll: '',
        mobile: '',
      },
    ]);
  };

  const removeStudentRow = (index: number) => {
    setStudents(students.filter((_, i) => i !== index));
  };

  const updateStudent = (
    index: number,
    field: keyof typeof students[0],
    value: string | 'male' | 'female' | 'other'
  ) => {
    const updated = [...students];
    updated[index] = { ...updated[index], [field]: value };
    setStudents(updated);
  };

  const resetAndClose = () => {
    setResult(null);
    setError(null);
    setStudents([
      {
        student_uid: '',
        name: '',
        sex: 'male',
        fathers_name: '',
        mothers_name: '',
        roll: '',
        mobile: '',
      },
    ]);
    onClose();
  };

  const handleDone = () => {
    if (result) {
      onSaved();
      resetAndClose();
    }
  };

  return (
    <Modal
      open
      onClose={resetAndClose}
      title="Bulk Register Students"
      footer={
        result ? (
          <>
            <Button variant="outline" onClick={resetAndClose}>
              Close
            </Button>
            <Button onClick={handleDone}>
              Done
            </Button>
          </>
        ) : (
          <>
            <Button variant="outline" onClick={resetAndClose}>
              Cancel
            </Button>
            <Button
              onClick={handleSubmit}
              disabled={bulkMutation.isPending || students.length === 0}
            >
              {bulkMutation.isPending && <Loader2 size={16} className="animate-spin" />}
              Register Students
            </Button>
          </>
        )
      }
    >
      {result ? (
        <div className="space-y-4">
          <div className="flex items-center gap-3 rounded-xl bg-green-50 p-4">
            <Check className="h-5 w-5 text-green-600" />
            <div className="text-sm">
              <span className="font-semibold text-green-900">
                {result.summary.created_count}
              </span>{' '}
              students registered successfully
              {result.summary.failed_count > 0 && (
                <span className="text-green-700">
                  , {result.summary.failed_count} failed
                </span>
              )}
            </div>
          </div>

          {result.failed.length > 0 && (
            <div className="rounded-xl border border-amber-200 bg-amber-50 p-4">
              <h4 className="text-sm font-semibold text-amber-900 mb-2">Failed Registrations</h4>
              <div className="space-y-2">
                {result.failed.map((failure, index) => (
                  <div key={index} className="flex items-start gap-2 text-sm">
                    <AlertCircle className="h-4 w-4 text-amber-600 mt-0.5" />
                    <div>
                      <span className="font-medium text-amber-900">{failure.student_uid}</span>
                      <span className="text-amber-700">: {failure.error}</span>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          <div className="rounded-xl border border-border p-4">
            <h4 className="text-sm font-semibold text-fg mb-2">Successfully Registered</h4>
            <div className="space-y-1 text-sm">
              {result.created.map((student, index) => (
                <div key={index} className="text-muted">
                  {student.student_uid} → ID: {student.id}
                </div>
              ))}
            </div>
          </div>
        </div>
      ) : (
        <div className="space-y-6">
          {error && (
            <div className="rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700">
              {error}
            </div>
          )}

          {/* Target Class Selection */}
          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <label className="mb-1.5 block text-[13px] font-medium text-fg">
                Academic Year <span className="text-rose-500">*</span>
              </label>
              <select
                value={academicYearId}
                onChange={(e) => setAcademicYearId(Number(e.target.value))}
                className="w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25"
              >
                <option value="">Select academic year</option>
                {academicYears.map((year) => (
                  <option key={year.id} value={year.id}>
                    {year.name}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="mb-1.5 block text-[13px] font-medium text-fg">
                Class <span className="text-rose-500">*</span>
              </label>
              <select
                value={classConfigId}
                onChange={(e) => setClassConfigId(Number(e.target.value))}
                className="w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25"
              >
                <option value="">Select class</option>
                {classConfigs.map((config) => (
                  <option key={config.id} value={config.id}>
                    {config.label}
                  </option>
                ))}
              </select>
            </div>
          </div>

          {/* Students Table */}
          <div>
            <div className="mb-3 flex items-center justify-between">
              <h3 className="text-sm font-semibold text-fg">Students Data</h3>
              <Button size="sm" onClick={addStudentRow}>
                <Upload size={14} />
                Add Row
              </Button>
            </div>

            <div className="overflow-x-auto rounded-xl border border-border">
              <table className="w-full text-left text-[13px]">
                <thead className="bg-surface-2">
                  <tr className="text-[11px] uppercase tracking-wide text-faint">
                    <th className="px-3 py-2 font-semibold">UID *</th>
                    <th className="px-3 py-2 font-semibold">Name *</th>
                    <th className="px-3 py-2 font-semibold">Sex *</th>
                    <th className="px-3 py-2 font-semibold">Father *</th>
                    <th className="px-3 py-2 font-semibold">Mother *</th>
                    <th className="px-3 py-2 font-semibold">Roll *</th>
                    <th className="px-3 py-2 font-semibold">Mobile</th>
                    <th className="px-3 py-2 font-semibold"></th>
                  </tr>
                </thead>
                <tbody>
                  {students.map((student, index) => (
                    <tr key={index} className="border-t border-border">
                      <td className="px-3 py-2">
                        <input
                          type="text"
                          value={student.student_uid}
                          onChange={(e) => updateStudent(index, 'student_uid', e.target.value)}
                          placeholder="UID"
                          className="w-full rounded-lg border border-border bg-surface px-2 py-1.5 text-[13px] text-fg outline-none focus:border-brand-500"
                        />
                      </td>
                      <td className="px-3 py-2">
                        <input
                          type="text"
                          value={student.name}
                          onChange={(e) => updateStudent(index, 'name', e.target.value)}
                          placeholder="Name"
                          className="w-full rounded-lg border border-border bg-surface px-2 py-1.5 text-[13px] text-fg outline-none focus:border-brand-500"
                        />
                      </td>
                      <td className="px-3 py-2">
                        <select
                          value={student.sex}
                          onChange={(e) => updateStudent(index, 'sex', e.target.value as any)}
                          className="w-full rounded-lg border border-border bg-surface px-2 py-1.5 text-[13px] text-fg outline-none focus:border-brand-500"
                        >
                          {GENDER_OPTIONS.map((opt) => (
                            <option key={opt.value} value={opt.value}>
                              {opt.label}
                            </option>
                          ))}
                        </select>
                      </td>
                      <td className="px-3 py-2">
                        <input
                          type="text"
                          value={student.fathers_name}
                          onChange={(e) => updateStudent(index, 'fathers_name', e.target.value)}
                          placeholder="Father"
                          className="w-full rounded-lg border border-border bg-surface px-2 py-1.5 text-[13px] text-fg outline-none focus:border-brand-500"
                        />
                      </td>
                      <td className="px-3 py-2">
                        <input
                          type="text"
                          value={student.mothers_name}
                          onChange={(e) => updateStudent(index, 'mothers_name', e.target.value)}
                          placeholder="Mother"
                          className="w-full rounded-lg border border-border bg-surface px-2 py-1.5 text-[13px] text-fg outline-none focus:border-brand-500"
                        />
                      </td>
                      <td className="px-3 py-2">
                        <input
                          type="text"
                          value={student.roll}
                          onChange={(e) => updateStudent(index, 'roll', e.target.value)}
                          placeholder="Roll"
                          className="w-full rounded-lg border border-border bg-surface px-2 py-1.5 text-[13px] text-fg outline-none focus:border-brand-500"
                        />
                      </td>
                      <td className="px-3 py-2">
                        <input
                          type="text"
                          value={student.mobile}
                          onChange={(e) => updateStudent(index, 'mobile', e.target.value)}
                          placeholder="Mobile"
                          className="w-full rounded-lg border border-border bg-surface px-2 py-1.5 text-[13px] text-fg outline-none focus:border-brand-500"
                        />
                      </td>
                      <td className="px-3 py-2">
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => removeStudentRow(index)}
                        >
                          <X size={14} />
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {students.length === 0 && (
              <div className="mt-4 text-center text-sm text-muted">
                No students added. Click "Add Row" to start.
              </div>
            )}
          </div>

          <div className="rounded-lg bg-surface-2 p-3 text-xs text-muted">
            <strong className="text-fg">Tip:</strong> You can also prepare your data in a spreadsheet
            and paste it here. Make sure each student has at least: UID, Name, Sex, Father's Name,
            Mother's Name, and Roll Number.
          </div>
        </div>
      )}
    </Modal>
  );
}