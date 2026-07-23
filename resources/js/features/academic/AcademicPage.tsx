import { useState } from 'react';
import { cn } from '@/lib/cn';
import { SetupResource, type FieldDef } from './SetupResource';
import { ClassConfigPanel } from './ClassConfigPanel';

interface Tab {
    key: string;
    label: string;
    render: () => React.ReactNode;
}

const yearExtra: FieldDef[] = [{ name: 'is_current', label: 'Current session', type: 'checkbox' }];
const subjectExtra: FieldDef[] = [{ name: 'code', label: 'Code', type: 'text', placeholder: 'e.g. 101' }];

const tabs: Tab[] = [
    { key: 'academic-years', label: 'Academic Year', render: () => <SetupResource resource="academic-years" singular="Academic Year" extraFields={yearExtra} /> },
    { key: 'classes', label: 'Classes', render: () => <SetupResource resource="classes" singular="Class" /> },
    { key: 'shifts', label: 'Shifts', render: () => <SetupResource resource="shifts" singular="Shift" /> },
    { key: 'sections', label: 'Sections', render: () => <SetupResource resource="sections" singular="Section" /> },
    { key: 'groups', label: 'Groups', render: () => <SetupResource resource="groups" singular="Group" /> },
    { key: 'categories', label: 'Categories', render: () => <SetupResource resource="categories" singular="Category" /> },
    { key: 'subjects', label: 'Subjects', render: () => <SetupResource resource="subjects" singular="Subject" extraFields={subjectExtra} /> },
    { key: 'class-config', label: 'Class Config', render: () => <ClassConfigPanel /> },
];

export default function AcademicPage() {
    const [active, setActive] = useState('classes');
    const current = tabs.find((t) => t.key === active) ?? tabs[0];

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-[26px] font-bold tracking-tight text-fg">Academic Setup</h1>
                <p className="mt-1 text-[14px] text-muted">
                    Define the academic building blocks for this branch — sessions, classes, sections and the
                    class configurations everything else hangs off.
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
