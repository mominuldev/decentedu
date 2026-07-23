import axios from 'axios';

/** Shared axios client for the Sanctum cookie-based SPA session. */
export const api = axios.create({
    baseURL: '/',
    withCredentials: true,
    withXSRFToken: true,
    headers: { Accept: 'application/json' },
});

/** Prime the CSRF cookie before a state-changing auth request. */
export async function csrf() {
    await api.get('/sanctum/csrf-cookie');
}

export interface ApiError {
    message: string;
    error_code?: string;
    errors?: Record<string, string[]>;
}

/** Normalise an axios error into our envelope shape. */
export function toApiError(e: unknown): ApiError {
    if (axios.isAxiosError(e) && e.response?.data) {
        return e.response.data as ApiError;
    }
    return { message: 'Network error. Please try again.' };
}
