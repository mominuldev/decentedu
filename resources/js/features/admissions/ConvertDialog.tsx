import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { GraduationCap } from 'lucide-react';
import { Modal } from '@/components/Modal';
import { Button } from '@/components/ui';
import { toApiError } from '@/lib/api';
import { listSetup, type SetupRow, type ClassConfigRow } from '@/features/academic/api';
import { convertApplication, type AdmissionApplication } from './api';

interface Props {
  application: AdmissionApplication;
  classConfigs: ClassConfigRow[];
  onClose: () => void;
  onConverted: (result: { student_id: number; student_uid: string }) => void;
}

const field = 'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none placeholder:text-faint focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';
const label = 'block text-[13px] font-medium text-muted mb-1.5';

export function ConvertDialog({ application, classConfigs, onClose, onConverted }: Props) {
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [studentUid, setStudentUid] = useState('');
  const [roll, setRoll] = useState('');
  const [academicYearId, setAcademicYearId] = useState<number>(0);
  const [classConfigId, setClassConfigId] = useState<number>(application.class_config_id);

  const { data: years } = useQuery({
    queryKey: ['academic-years-setup'],
    queryFn: () => listSetup('academic-years'),
  });

  // Default to the current academic year once options load.
  if (academicYearId === 0 && years?.length) {
    const current = (years as SetupRow[]).find((y) => y.is_current) ?? years[0];
    if (current) setAcademicYearId(current.id);
  }

  const mutation = useMutation({
    mutationFn: () => convertApplication(application.id, {
      student_uid: studentUid,
      academic_year_id: academicYearId,
      class_config_id: classConfigId || undefined,
      roll,
    }),
    onSuccess: (result) => {
      qc.invalidateQueries({ queryKey: ['admission-applications'] });
      qc.invalidateQueries({ queryKey: ['admission-stats'] });
      onConverted(result);
    },
    onError: (e) => setError(toApiError(e).message),
  });

  const submit = () => {
    setError(null);
    if (!studentUid.trim() || !roll.trim() || !academicYearId) {
      setError('Student ID, roll and academic year are required.');
      return;
    }
    mutation.mutate();
  };

  return (
    <Modal
      open
      onClose={onClose}
      title="Admit applicant → create student"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={mutation.isPending}>Cancel</Button>
          <Button onClick={submit} disabled={mutation.isPending}>
            <GraduationCap size={16} />
            {mutation.isPending ? 'Admitting…' : 'Admit & enrol'}
          </Button>
        </>
      }
    >
      {error && (
        <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">
          {error}
        </div>
      )}

      <p className="mb-4 text-[13.5px] text-muted">
        Converting <span className="font-semibold text-fg">{application.name}</span>{' '}
        ({application.application_no}) into an active student. Bio details carry over automatically —
        just assign an ID, class and roll.
      </p>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <label className={label}>Student ID / admission no. *</label>
          <input className={field} value={studentUid} onChange={(e) => setStudentUid(e.target.value)} placeholder="e.g. 2026-0421" />
        </div>
        <div>
          <label className={label}>Roll *</label>
          <input className={field} value={roll} onChange={(e) => setRoll(e.target.value)} />
        </div>
        <div>
          <label className={label}>Academic year *</label>
          <select className={field} value={academicYearId} onChange={(e) => setAcademicYearId(Number(e.target.value))}>
            <option value={0}>Select year…</option>
            {(years ?? []).map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
          </select>
        </div>
        <div>
          <label className={label}>Enrolment class</label>
          <select className={field} value={classConfigId} onChange={(e) => setClassConfigId(Number(e.target.value))}>
            {classConfigs.map((c) => <option key={c.id} value={c.id}>{c.label}</option>)}
          </select>
        </div>
      </div>
    </Modal>
  );
}
