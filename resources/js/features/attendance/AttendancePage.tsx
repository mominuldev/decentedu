import { useState } from 'react';
import { cn } from '@/lib/cn';
import { TakeStudentAttendance } from './TakeStudentAttendance';
import { TakeEmployeeAttendance } from './TakeEmployeeAttendance';
import { AttendanceReports } from './AttendanceReports';
import { DevicesPanel } from './DevicesPanel';
import { DeviceMapPanel } from './DeviceMapPanel';
import { TimeConfigPanel } from './TimeConfigPanel';
import { HolidaysPanel } from './HolidaysPanel';

interface Tab {
    key: string;
    label: string;
    render: () => React.ReactNode;
}

const tabs: Tab[] = [
    { key: 'take-students', label: 'Student Attendance', render: () => <TakeStudentAttendance /> },
    { key: 'take-employees', label: 'Staff Attendance', render: () => <TakeEmployeeAttendance /> },
    { key: 'reports', label: 'Reports', render: () => <AttendanceReports /> },
    { key: 'devices', label: 'Devices', render: () => <DevicesPanel /> },
    { key: 'device-maps', label: 'Device Map', render: () => <DeviceMapPanel /> },
    { key: 'time-configs', label: 'Time Config', render: () => <TimeConfigPanel /> },
    { key: 'holidays', label: 'Holidays', render: () => <HolidaysPanel /> },
];

export default function AttendancePage() {
    const [active, setActive] = useState('take-students');
    const current = tabs.find((t) => t.key === active) ?? tabs[0];

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-[26px] font-bold tracking-tight text-fg">Attendance</h1>
                <p className="mt-1 text-[14px] text-muted">
                    Take daily attendance, manage biometric devices, and review attendance reports.
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
