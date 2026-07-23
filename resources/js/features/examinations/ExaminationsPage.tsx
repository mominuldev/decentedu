import { useState } from 'react';
import { cn } from '@/lib/cn';
import { SetupResource, type FieldDef } from './SetupResource';
import { GradesPanel } from './GradesPanel';
import { ExamConfigPanel } from './ExamConfigPanel';
import { MarkConfigPanel } from './MarkConfigPanel';
import { FourthSubjectPanel } from './FourthSubjectPanel';
import { ClassTeacherPanel } from './ClassTeacherPanel';
import { SignaturesPanel } from './SignaturesPanel';
import { AdmitInstructionsPanel } from './AdmitInstructionsPanel';
import { ExamRoutinePanel } from './ExamRoutinePanel';
import { MarksInputPanel } from './MarksInputPanel';
import { AdmitPanel } from './AdmitPanel';

const examTypeField: FieldDef = {
    name: 'type',
    label: 'Exam type',
    type: 'select',
    required: true,
    options: [
        { value: 'weekly', label: 'Weekly' },
        { value: 'monthly', label: 'Monthly' },
        { value: 'final', label: 'Final' },
        { value: 'grand_final', label: 'Grand Final' },
    ],
};

interface Tab { key: string; label: string; render: () => React.ReactNode }

const setupTabs: Tab[] = [
    { key: 'exams', label: 'Exams', render: () => <SetupResource resource="exams" singular="Exam" extraFields={[examTypeField]} /> },
    { key: 'short-codes', label: 'Short Codes', render: () => <SetupResource resource="short-codes" singular="Short code" /> },
    { key: 'grades', label: 'Grades', render: () => <GradesPanel /> },
];

const configTabs: Tab[] = [
    { key: 'exam-config', label: 'Exam Config', render: () => <ExamConfigPanel /> },
    { key: 'mark-config', label: 'Mark Config', render: () => <MarkConfigPanel /> },
    { key: 'fourth-subject', label: 'Fourth Subject', render: () => <FourthSubjectPanel /> },
    { key: 'class-teacher', label: 'Class Teacher', render: () => <ClassTeacherPanel /> },
    { key: 'signatures', label: 'Signatures', render: () => <SignaturesPanel /> },
    { key: 'admit-instructions', label: 'Admit Instructions', render: () => <AdmitInstructionsPanel /> },
];

const topTabs = [
    { key: 'setup', label: 'Setup', tabs: setupTabs },
    { key: 'config', label: 'Configuration', tabs: configTabs },
    { key: 'routine', label: 'Exam Routine', tabs: [{ key: 'routine', label: 'Exam Routine', render: () => <ExamRoutinePanel /> }] },
    { key: 'marks', label: 'Marks Input', tabs: [{ key: 'marks', label: 'Marks Input', render: () => <MarksInputPanel /> }] },
    { key: 'admit', label: 'Admit', tabs: [{ key: 'admit', label: 'Admit', render: () => <AdmitPanel /> }] },
];

function PillTabs({ tabs, active, onChange }: { tabs: Tab[]; active: string; onChange: (key: string) => void }) {
    if (tabs.length <= 1) return null;

    return (
        <div className="flex flex-wrap gap-1.5">
            {tabs.map((t) => (
                <button
                    key={t.key}
                    onClick={() => onChange(t.key)}
                    className={cn(
                        'rounded-full px-3 py-1.5 text-[13px] font-medium transition-colors',
                        active === t.key ? 'bg-brand-600 text-white' : 'bg-surface-2 text-muted hover:text-fg',
                    )}
                >
                    {t.label}
                </button>
            ))}
        </div>
    );
}

export default function ExaminationsPage() {
    const [activeTop, setActiveTop] = useState('setup');
    const [activeSub, setActiveSub] = useState<Record<string, string>>({});

    const top = topTabs.find((t) => t.key === activeTop) ?? topTabs[0];
    const subKey = activeSub[top.key] ?? top.tabs[0].key;
    const currentSub = top.tabs.find((t) => t.key === subKey) ?? top.tabs[0];

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-[26px] font-bold tracking-tight text-fg">Examinations</h1>
                <p className="mt-1 text-[14px] text-muted">
                    Exam setup, mark configuration, exam routine, marks input and exam-day documents.
                </p>
            </div>

            <div className="flex flex-wrap gap-1.5 border-b border-border">
                {topTabs.map((t) => (
                    <button
                        key={t.key}
                        onClick={() => setActiveTop(t.key)}
                        className={cn(
                            'relative -mb-px rounded-t-lg px-3.5 py-2.5 text-[13.5px] font-medium transition-colors',
                            activeTop === t.key
                                ? 'border-b-2 border-brand-600 text-brand-700 dark:text-brand-300'
                                : 'text-muted hover:text-fg',
                        )}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            <PillTabs tabs={top.tabs} active={subKey} onChange={(key) => setActiveSub((s) => ({ ...s, [top.key]: key }))} />

            {currentSub.render()}
        </div>
    );
}
