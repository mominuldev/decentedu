import type { ReactNode } from 'react';
import { createBrowserRouter } from 'react-router-dom';
import { DashboardLayout } from '@/layouts/DashboardLayout';
import { ProtectedRoute, GuestRoute } from '@/app/ProtectedRoute';
import LoginPage from '@/features/auth/LoginPage';
import DashboardPage from '@/features/dashboard/DashboardPage';
import AcademicPage from '@/features/academic/AcademicPage';
import StudentsPage from '@/features/students/StudentsPage';
import HrPage from '@/features/hr/HrPage';
import Placeholder from '@/features/misc/Placeholder';

// Protected page = auth gate + dashboard chrome.
const page = (el: ReactNode) => (
    <ProtectedRoute>
        <DashboardLayout>{el}</DashboardLayout>
    </ProtectedRoute>
);

const stub = (title: string, phase: string) => page(<Placeholder title={title} phase={phase} />);

export const router = createBrowserRouter([
    { path: '/login', element: <GuestRoute><LoginPage /></GuestRoute> },

    { path: '/', element: page(<DashboardPage />) },
    { path: '/admissions', element: stub('Admissions', 'Phase 4') },
    { path: '/students', element: page(<StudentsPage />) },
    { path: '/academic', element: page(<AcademicPage />) },
    { path: '/hr', element: page(<HrPage />) },
    { path: '/attendance', element: stub('Attendance', 'Phase 5') },
    { path: '/routines', element: stub('Routines', 'Phase 5') },
    { path: '/exams', element: stub('Examinations', 'Phase 6') },
    { path: '/results', element: stub('Results', 'Phase 6') },
    { path: '/fees', element: stub('Fees', 'Phase 7') },
    { path: '/accounting', element: stub('Accounting', 'Phase 7') },
    { path: '/messaging', element: stub('SMS & Notices', 'Phase 8') },
    { path: '/credentials', element: stub('Credentials', 'Phase 8') },
    { path: '/website', element: stub('Website', 'Phase 8') },
    { path: '/users', element: stub('Users & Roles', 'Phase 1–2') },
    { path: '/settings', element: stub('Settings', 'Phase 2') },
    { path: '*', element: stub('Page not found', 'Unknown route') },
]);
