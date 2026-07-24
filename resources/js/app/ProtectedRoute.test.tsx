import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import { ProtectedRoute, GuestRoute } from './ProtectedRoute';
import { useAuth } from '@/features/auth/AuthProvider';
import type { Session } from '@/features/auth/types';

vi.mock('@/features/auth/AuthProvider', () => ({ useAuth: vi.fn() }));

function mockAuth(overrides: Partial<ReturnType<typeof useAuth>>) {
  vi.mocked(useAuth).mockReturnValue({
    status: 'guest',
    session: null,
    login: vi.fn(),
    logout: vi.fn(),
    switchBranch: vi.fn(),
    refresh: vi.fn(),
    can: vi.fn(),
    ...overrides,
  });
}

function makeSession(mustReset: boolean): Session {
  return {
    user: {
      id: 1, name: 'Jane', email: 'jane@example.com', phone: null, avatar_path: null,
      role: 'Teacher', is_super_admin: false, must_reset_password: mustReset,
    },
    organization: null,
    active_branch: null,
    branches: [],
    permissions: [],
  };
}

function renderAt(path: string) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <Routes>
        <Route path="/login" element={<div>Login page</div>} />
        <Route path="/" element={<div>Home</div>} />
        <Route
          path="/users"
          element={
            <ProtectedRoute>
              <div>Users page</div>
            </ProtectedRoute>
          }
        />
        <Route
          path="/dashboard"
          element={
            <ProtectedRoute>
              <div>Dashboard page</div>
            </ProtectedRoute>
          }
        />
      </Routes>
    </MemoryRouter>,
  );
}

describe('ProtectedRoute', () => {
  it('shows a loading splash while auth status is resolving', () => {
    mockAuth({ status: 'loading' });
    renderAt('/dashboard');
    expect(screen.getByText(/Loading DecentEdu/)).toBeInTheDocument();
  });

  it('redirects a guest to /login', () => {
    mockAuth({ status: 'guest' });
    renderAt('/dashboard');
    expect(screen.getByText('Login page')).toBeInTheDocument();
  });

  it('renders the protected content for an authenticated user', () => {
    mockAuth({ status: 'authed', session: makeSession(false) });
    renderAt('/dashboard');
    expect(screen.getByText('Dashboard page')).toBeInTheDocument();
  });

  it('forces a must-reset-password user to /users instead of any other protected route', () => {
    mockAuth({ status: 'authed', session: makeSession(true) });
    renderAt('/dashboard');
    expect(screen.getByText('Users page')).toBeInTheDocument();
  });

  it('lets a must-reset-password user reach /users itself without redirect looping', () => {
    mockAuth({ status: 'authed', session: makeSession(true) });
    renderAt('/users');
    expect(screen.getByText('Users page')).toBeInTheDocument();
  });
});

describe('GuestRoute', () => {
  function renderGuestAt(path: string) {
    return render(
      <MemoryRouter initialEntries={[path]}>
        <Routes>
          <Route path="/" element={<div>Home</div>} />
          <Route
            path="/login"
            element={
              <GuestRoute>
                <div>Login form</div>
              </GuestRoute>
            }
          />
        </Routes>
      </MemoryRouter>,
    );
  }

  it('shows the login form for a guest', () => {
    mockAuth({ status: 'guest' });
    renderGuestAt('/login');
    expect(screen.getByText('Login form')).toBeInTheDocument();
  });

  it('redirects an authenticated user away from /login', () => {
    mockAuth({ status: 'authed', session: makeSession(false) });
    renderGuestAt('/login');
    expect(screen.getByText('Home')).toBeInTheDocument();
  });
});
