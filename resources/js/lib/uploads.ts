import { api } from '@/lib/api';

export type UploadCategory = 'photo' | 'logo' | 'image';

export async function uploadFile(file: File, category: UploadCategory): Promise<string> {
    const form = new FormData();
    form.append('file', file);
    form.append('category', category);
    const { data } = await api.post('/api/v1/uploads', form, { headers: { 'Content-Type': 'multipart/form-data' } });
    return data.data.path as string;
}

/** Same-origin, cookie-authenticated — safe to use directly as an <img src>. */
export function uploadUrl(path: string): string {
    return `/api/v1/uploads/${path}`;
}
