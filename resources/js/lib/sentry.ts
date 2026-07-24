import * as Sentry from '@sentry/react';

/** No-ops when VITE_SENTRY_DSN isn't set — safe to always call, including in local dev. */
export function initSentry() {
    const dsn = import.meta.env.VITE_SENTRY_DSN;
    if (!dsn) return;

    Sentry.init({
        dsn,
        environment: import.meta.env.MODE,
        tracesSampleRate: 0.1,
        integrations: [Sentry.browserTracingIntegration()],
    });
}
