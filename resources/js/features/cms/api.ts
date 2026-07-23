import { api } from '@/lib/api';

const base = '/api/v1/cms';

export const POST_TYPES = [
    'page', 'news', 'notice', 'slider', 'teacher', 'staff', 'committee',
    'gallery', 'result', 'homepage_person', 'instruction',
] as const;
export type PostType = (typeof POST_TYPES)[number];

export interface PostRow {
    id: number;
    type: PostType;
    title: string;
    slug: string;
    body: string | null;
    description: string | null;
    keywords: string | null;
    image_path: string | null;
    status: 'draft' | 'published';
    published_at: string | null;
}
export async function listPosts(params: { type?: string; search?: string } = {}): Promise<PostRow[]> {
    const { data } = await api.get(`${base}/posts`, { params: { per_page: 200, ...params } });
    return data.data as PostRow[];
}
export async function createPost(payload: Partial<PostRow>): Promise<PostRow> {
    const { data } = await api.post(`${base}/posts`, payload);
    return data.data as PostRow;
}
export async function updatePost(id: number, payload: Partial<PostRow>): Promise<PostRow> {
    const { data } = await api.put(`${base}/posts/${id}`, payload);
    return data.data as PostRow;
}
export async function deletePost(id: number): Promise<void> {
    await api.delete(`${base}/posts/${id}`);
}

export interface MenuItemRow {
    id: number;
    menu_id: number;
    label: string;
    url: string | null;
    post_id: number | null;
    parent_id: number | null;
    serial: number;
    target: '_self' | '_blank';
    children?: MenuItemRow[];
}
export interface MenuRow {
    id: number;
    name: string;
    location: 'header' | 'footer';
    status: boolean;
    items?: MenuItemRow[];
}
export async function listMenus(): Promise<MenuRow[]> {
    const { data } = await api.get(`${base}/menus`);
    return data.data as MenuRow[];
}
export async function createMenu(payload: Partial<MenuRow>): Promise<MenuRow> {
    const { data } = await api.post(`${base}/menus`, payload);
    return data.data as MenuRow;
}
export async function updateMenu(id: number, payload: Partial<MenuRow>): Promise<MenuRow> {
    const { data } = await api.put(`${base}/menus/${id}`, payload);
    return data.data as MenuRow;
}
export async function deleteMenu(id: number): Promise<void> {
    await api.delete(`${base}/menus/${id}`);
}

export async function listMenuItems(menuId: number): Promise<MenuItemRow[]> {
    const { data } = await api.get(`${base}/menu-items`, { params: { menu_id: menuId } });
    return data.data as MenuItemRow[];
}
export async function createMenuItem(payload: Partial<MenuItemRow>): Promise<MenuItemRow> {
    const { data } = await api.post(`${base}/menu-items`, payload);
    return data.data as MenuItemRow;
}
export async function deleteMenuItem(id: number): Promise<void> {
    await api.delete(`${base}/menu-items/${id}`);
}

export interface WebsiteSettingsRow {
    site_title: string | null;
    tagline: string | null;
    address: string | null;
    phone: string | null;
    email: string | null;
    meta_description: string | null;
    status: boolean;
}
export async function getWebsiteSettings(): Promise<WebsiteSettingsRow> {
    const { data } = await api.get(`${base}/website-settings`);
    return data.data as WebsiteSettingsRow;
}
export async function updateWebsiteSettings(payload: Partial<WebsiteSettingsRow>): Promise<WebsiteSettingsRow> {
    const { data } = await api.put(`${base}/website-settings`, payload);
    return data.data as WebsiteSettingsRow;
}
