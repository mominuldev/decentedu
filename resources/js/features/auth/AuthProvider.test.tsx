import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { AuthProvider, useAuth } from './AuthProvider';
import * as authApi from './api';
import type { Session } from './types';

vi.mock('./api');

function makeSession(overrides: Partial<Session> = {}): Session {
  return {
    user: {
      id: 1,
      name: 'Jane Doe',
      email: 'jane@example.com',
      phone: null,
      avatar_path: null,
      role: 'Teacher',
      is_super_admin: false,
      must_reset_password: false,
    },
    organization: { id: 1, name: 'Test Org' },
    active_branch: { id: 1, name: 'Main Branch', name_bn: null, code: 'MAIN', is_default: true },
    branches: [],
    permissions: ['students.manage'],
    ...overrides,
  };
}

function Probe() {
  const { status, session, can, login, logout } = useAuth();
  return (
    <div>
      <span data-testid="status">{status}</span>
      <span data-testid="can-students">{String(can('students.manage'))}</span>
      <span data-testid="can-fees">{String(can('fees.manage'))}</span>
      <span data-testid="user-name">{session?.user.name ?? ''}</span>
      <button onClick={() => login('jane@example.com', 'secret', false)}>Log in</button>
      <button onClick={() => { logout().catch(() => {}); }}>Log out</button>
    </div>
  );
}

describe('AuthProvider', () => {
  it('resolves to authed status when fetchMe succeeds', async () => {
    vi.mocked(authApi.fetchMe).mockResolvedValue(makeSession());

    render(
      <AuthProvider>
        <Probe />
      </AuthProvider>,
    );

    expect(screen.getByTestId('status')).toHaveTextContent('loading');
    await waitFor(() => expect(screen.getByTestId('status')).toHaveTextContent('authed'));
    expect(screen.getByTestId('user-name')).toHaveTextContent('Jane Doe');
  });

  it('resolves to guest status when fetchMe rejects', async () => {
    vi.mocked(authApi.fetchMe).mockRejectedValue(new Error('unauthenticated'));

    render(
      <AuthProvider>
        <Probe />
      </AuthProvider>,
    );

    await waitFor(() => expect(screen.getByTestId('status')).toHaveTextContent('guest'));
  });

  it('can() grants only permissions the session actually has', async () => {
    vi.mocked(authApi.fetchMe).mockResolvedValue(makeSession({ permissions: ['students.manage'] }));

    render(
      <AuthProvider>
        <Probe />
      </AuthProvider>,
    );

    await waitFor(() => expect(screen.getByTestId('status')).toHaveTextContent('authed'));
    expect(screen.getByTestId('can-students')).toHaveTextContent('true');
    expect(screen.getByTestId('can-fees')).toHaveTextContent('false');
  });

  it('can() always returns true for a super admin regardless of the permissions list', async () => {
    vi.mocked(authApi.fetchMe).mockResolvedValue(
      makeSession({ permissions: [], user: { ...makeSession().user, is_super_admin: true } }),
    );

    render(
      <AuthProvider>
        <Probe />
      </AuthProvider>,
    );

    await waitFor(() => expect(screen.getByTestId('status')).toHaveTextContent('authed'));
    expect(screen.getByTestId('can-fees')).toHaveTextContent('true');
  });

  it('can() returns false for anyone before a session has loaded', () => {
    vi.mocked(authApi.fetchMe).mockReturnValue(new Promise(() => {}));

    render(
      <AuthProvider>
        <Probe />
      </AuthProvider>,
    );

    expect(screen.getByTestId('can-students')).toHaveTextContent('false');
  });

  it('login() sets the session and flips status to authed', async () => {
    vi.mocked(authApi.fetchMe).mockRejectedValue(new Error('unauthenticated'));
    vi.mocked(authApi.login).mockResolvedValue(makeSession({ user: { ...makeSession().user, name: 'Logged In User' } }));

    render(
      <AuthProvider>
        <Probe />
      </AuthProvider>,
    );

    await waitFor(() => expect(screen.getByTestId('status')).toHaveTextContent('guest'));
    fireEvent.click(screen.getByText('Log in'));

    await waitFor(() => expect(screen.getByTestId('status')).toHaveTextContent('authed'));
    expect(screen.getByTestId('user-name')).toHaveTextContent('Logged In User');
  });

  it('logout() clears the session even if the API call fails', async () => {
    vi.mocked(authApi.fetchMe).mockResolvedValue(makeSession());
    vi.mocked(authApi.logout).mockRejectedValue(new Error('network error'));

    render(
      <AuthProvider>
        <Probe />
      </AuthProvider>,
    );

    await waitFor(() => expect(screen.getByTestId('status')).toHaveTextContent('authed'));
    fireEvent.click(screen.getByText('Log out'));

    await waitFor(() => expect(screen.getByTestId('status')).toHaveTextContent('guest'));
    expect(screen.getByTestId('user-name')).toHaveTextContent('');
  });
});
