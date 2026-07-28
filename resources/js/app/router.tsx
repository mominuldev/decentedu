import type { ReactNode } from 'react';
import { createBrowserRouter } from 'react-router-dom';
import { DashboardLayout } from '@/layouts/DashboardLayout';
import { ProtectedRoute, GuestRoute } from '@/app/ProtectedRoute';
import LoginPage from '@/features/auth/LoginPage';
import ForgotPasswordPage from '@/features/auth/ForgotPasswordPage';
import ResetPasswordPage from '@/features/auth/ResetPasswordPage';
import DashboardPage from '@/features/dashboard/DashboardPage';
import AcademicPage from '@/features/academic/AcademicPage';
import AdmissionsPage from '@/features/admissions/AdmissionsPage';
import ApplicationFormPage from '@/features/admissions/ApplicationFormPage';
import StudentsPage from '@/features/students/StudentsPage';
import StudentFormPage from '@/features/students/StudentFormPage';
import BulkRegisterPage from '@/features/students/BulkRegisterPage';
import HrPage from '@/features/hr/HrPage';
import RoutinesPage from '@/features/routines/RoutinesPage';
import AttendancePage from '@/features/attendance/AttendancePage';
import ExaminationsPage from '@/features/examinations/ExaminationsPage';
import ResultsPage from '@/features/examinations/ResultsPage';
import FeesPage from '@/features/fees/FeesPage';
import AccountingPage from '@/features/accounting/AccountingPage';
import MessagingPage from '@/features/messaging/MessagingPage';
import CredentialsPage from '@/features/credentials/CredentialsPage';
import CmsPage from '@/features/cms/CmsPage';
import PageFormPage from '@/features/cms/PageFormPage';
import PostFormPage from '@/features/cms/PostFormPage';
import NoticeFormPage from '@/features/cms/NoticeFormPage';
import EventFormPage from '@/features/cms/EventFormPage';
import GalleryFormPage from '@/features/cms/GalleryFormPage';
import UsersPage from '@/features/users/UsersPage';
import AuditLogPage from '@/features/audit/AuditLogPage';
import SettingsPage from '@/features/settings/SettingsPage';
import PrintClassRoutinePage from '@/features/routines/PrintClassRoutinePage';
import PrintStudentListPage from '@/features/students/PrintStudentListPage';
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
    { path: '/forgot-password', element: <GuestRoute><ForgotPasswordPage /></GuestRoute> },
    { path: '/reset-password', element: <GuestRoute><ResetPasswordPage /></GuestRoute> },

    { path: '/', element: page(<DashboardPage />) },
    { path: '/admissions', element: page(<AdmissionsPage />) },
    { path: '/admissions/applications/new', element: page(<ApplicationFormPage />) },
    { path: '/admissions/applications/:id/edit', element: page(<ApplicationFormPage />) },
    { path: '/students', element: page(<StudentsPage />) },
    { path: '/students/new', element: page(<StudentFormPage />) },
    { path: '/students/bulk-register', element: page(<BulkRegisterPage />) },
    { path: '/students/:id/edit', element: page(<StudentFormPage />) },
    { path: '/academic', element: page(<AcademicPage />) },
    { path: '/hr', element: page(<HrPage />) },
    { path: '/attendance', element: page(<AttendancePage />) },
    { path: '/routines', element: page(<RoutinesPage />) },
    { path: '/exams', element: page(<ExaminationsPage />) },
    { path: '/results', element: page(<ResultsPage />) },
    { path: '/fees', element: page(<FeesPage />) },
    { path: '/accounting', element: page(<AccountingPage />) },
    { path: '/messaging', element: page(<MessagingPage />) },
    { path: '/credentials', element: page(<CredentialsPage />) },
    { path: '/cms', element: page(<CmsPage />) },
    { path: '/cms/pages/new', element: page(<PageFormPage />) },
    { path: '/cms/pages/:slug/edit', element: page(<PageFormPage />) },
    { path: '/cms/posts/new', element: page(<PostFormPage />) },
    { path: '/cms/posts/:slug/edit', element: page(<PostFormPage />) },
    { path: '/cms/notices/new', element: page(<NoticeFormPage />) },
    { path: '/cms/notices/:slug/edit', element: page(<NoticeFormPage />) },
    { path: '/cms/events/new', element: page(<EventFormPage />) },
    { path: '/cms/events/:slug/edit', element: page(<EventFormPage />) },
    { path: '/cms/galleries/new', element: page(<GalleryFormPage />) },
    { path: '/cms/galleries/:slug/edit', element: page(<GalleryFormPage />) },
    { path: '/users', element: page(<UsersPage />) },
    { path: '/audit-log', element: page(<AuditLogPage />) },
    { path: '/print/routine/:classConfigId', element: <ProtectedRoute><PrintClassRoutinePage /></ProtectedRoute> },
    { path: '/print/students', element: <ProtectedRoute><PrintStudentListPage /></ProtectedRoute> },
    { path: '/settings', element: page(<SettingsPage />) },
    { path: '*', element: stub('Page not found', 'Unknown route') },
]);
