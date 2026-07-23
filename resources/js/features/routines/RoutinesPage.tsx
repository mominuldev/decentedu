import { useState } from 'react';
import { cn } from '@/lib/cn';
import { PeriodsPanel } from './PeriodsPanel';
import { ClassRoutinePanel } from './ClassRoutinePanel';
import { TeacherRoutinePanel } from './TeacherRoutinePanel';

interface Tab {
    key: string;
    label: string;
    render: () => React.ReactNode;
}

const tabs: Tab[] = [
    { key: 'class-routine', label: 'Class Routine', render: () => <ClassRoutinePanel /> },
    { key: 'teacher-routine', label: 'Teacher Routine', render: () => <TeacherRoutinePanel /> },
    { key: 'periods', label: 'Periods', render: () => <PeriodsPanel /> },
];

export default function RoutinesPage() {
    const [active, setActive] = useState('class-routine');
    const current = tabs.find((t) => t.key === active) ?? tabs[0];

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-[26px] font-bold tracking-tight text-fg">Routines</h1>
                <p className="mt-1 text-[14px] text-muted">
                    Build the weekly class timetable, check a teacher's schedule, and manage the shared period slots.
                </p>
            </div>

            <div className="flex flex-wrap gap-1.5 border-b border-border">
                {tabs.map((t) => (
                    <button
                        key={t.key}
                        onClick={() => setActive(t.key)}
                        className={cn(
                            'relative -mb-px rounded-t-lg px-3.5 py-2.5 text-[13.5px] font-medium transition-colors',
                            active === t.key
                                ? 'border-b-2 border-brand-600 text-brand-700 dark:text-brand-300'
                                : 'text-muted hover:text-fg',
                        )}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            {current.render()}
        </div>
    );
}
