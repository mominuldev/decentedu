import type { ReactNode } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { GraduationCap } from 'lucide-react';
import { useAuth } from '@/features/auth/AuthProvider';

function Splash() {
    return (
        <div className="grid min-h-screen place-items-center bg-bg">
            <div className="flex flex-col items-center gap-3">
                <div className="grid h-12 w-12 animate-pulse place-items-center rounded-2xl bg-brand-600 text-white">
                    <GraduationCap size={24} />
                </div>
                <div className="text-[13px] text-muted">Loading DecentEdu…</div>
            </div>
        </div>
    );
}

/** Gate for authenticated screens. */
export function ProtectedRoute({ children }: { children: ReactNode }) {
    const { status, session } = useAuth();
    const location = useLocation();
    if (status === 'loading') return <Splash />;
    if (status === 'guest') return <Navigate to="/login" replace />;
    if (session?.user.must_reset_password && location.pathname !== '/users') {
        return <Navigate to="/users" replace />;
    }
    return <>{children}</>;
}

/** Keep authenticated users away from the login screen. */
export function GuestRoute({ children }: { children: ReactNode }) {
    const { status } = useAuth();
    if (status === 'loading') return <Splash />;
    if (status === 'authed') return <Navigate to="/" replace />;
    return <>{children}</>;
}
