import { api } from '@/lib/api';

const base = '/api/v1/cms';

/* ------------------------------------------------------------------ shared */

export type ContentStatus = 'draft' | 'published' | 'scheduled' | 'archived';

export interface Option {
    value: string;
    label: string;
}
export interface BlockTypeOption {
    type: string;
    label: string;
}
export interface AssetPayload {
    id: number;
    name: string;
    alt: string | null;
    caption: string | null;
    mime_type: string | null;
    size: number | null;
    url: string | null;
    thumb_url: string | null;
    preview_url: string | null;
    srcset: string | null;
}
export interface SeoData {
    meta_title?: string | null;
    meta_description?: string | null;
    canonical_url?: string | null;
    robots?: string | null;
    og_title?: string | null;
    og_description?: string | null;
    og_image_asset_id?: number | null;
    structured_data?: unknown;
}

/** One block in the admin editor (payload may hold nested `blocks` for sections). */
export interface EditorBlock {
    id: number | null;
    type: string;
    is_visible: boolean;
    payload: Record<string, unknown>;
}

/* -------------------------------------------------------------------- pages */

export interface PageRow {
    id: number;
    title: string;
    slug: string;
    path: string;
    parent_id: number | null;
    template: string;
    status: ContentStatus;
    position: number;
    published_at: string | null;
    updated_by: string | null;
    deleted_at: string | null;
}
export interface PageDetail {
    id: number;
    title: string;
    slug: string;
    path: string;
    parent_id: number | null;
    template: string;
    excerpt: string | null;
    status: ContentStatus;
    published_at: string | null;
    position: number;
    featured_asset_id: number | null;
    featured_asset: AssetPayload | null;
    blocks: EditorBlock[];
    seo: SeoData | null;
    seo_og_image: AssetPayload | null;
}
export interface PageMeta {
    templates: Option[];
    statuses: { value: string; label: string }[];
    block_types: BlockTypeOption[];
    parents: { id: number; title: string; path: string }[];
}
export interface PagePayload {
    title: string;
    slug?: string | null;
    parent_id?: number | null;
    template: string;
    excerpt?: string | null;
    status: ContentStatus;
    published_at?: string | null;
    position?: number | null;
    featured_asset_id?: number | null;
    seo?: SeoData | null;
    blocks?: EditorBlock[];
}

export type SortDir = 'asc' | 'desc';

export async function listPages(params: { search?: string; status?: string; sort?: string; direction?: SortDir } = {}): Promise<PageRow[]> {
    const { data } = await api.get(`${base}/pages`, { params: { per_page: 200, ...params } });
    return data.data as PageRow[];
}
export async function getPageMeta(): Promise<PageMeta> {
    const { data } = await api.get(`${base}/pages/meta`);
    return data.data as PageMeta;
}
export async function getPage(id: number): Promise<PageDetail> {
    const { data } = await api.get(`${base}/pages/${id}`);
    return data.data as PageDetail;
}
export async function createPage(payload: PagePayload): Promise<PageDetail> {
    const { data } = await api.post(`${base}/pages`, payload);
    return data.data as PageDetail;
}
export async function updatePage(id: number, payload: PagePayload): Promise<PageDetail> {
    const { data } = await api.put(`${base}/pages/${id}`, payload);
    return data.data as PageDetail;
}
export async function deletePage(id: number): Promise<void> {
    await api.delete(`${base}/pages/${id}`);
}

/* -------------------------------------------------------------------- posts */

export interface PostRow {
    id: number;
    title: string;
    slug: string;
    status: ContentStatus;
    is_featured: boolean;
    author: string | null;
    published_at: string | null;
    categories: string[];
    deleted_at: string | null;
}
export interface PostDetail {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    body: string | null;
    author_id: number | null;
    status: ContentStatus;
    published_at: string | null;
    is_featured: boolean;
    reading_time: number | null;
    featured_asset_id: number | null;
    featured_asset: AssetPayload | null;
    terms: number[];
    tags: string[];
    blocks: EditorBlock[];
    seo: SeoData | null;
    seo_og_image: AssetPayload | null;
}
export interface PostMeta {
    statuses: { value: string; label: string }[];
    block_types: BlockTypeOption[];
    authors: { id: number; name: string }[];
    terms: { id: number; name: string; taxonomy: string | null }[];
    tags: string[];
}
export interface PostPayload {
    title: string;
    slug?: string | null;
    excerpt?: string | null;
    body?: string | null;
    author_id?: number | null;
    status: ContentStatus;
    published_at?: string | null;
    is_featured?: boolean;
    featured_asset_id?: number | null;
    terms?: number[];
    tags?: string[];
    seo?: SeoData | null;
    blocks?: EditorBlock[];
}

export async function listPosts(params: { search?: string; status?: string; sort?: string; direction?: SortDir } = {}): Promise<PostRow[]> {
    const { data } = await api.get(`${base}/posts`, { params: { per_page: 200, ...params } });
    return data.data as PostRow[];
}
export async function getPostMeta(): Promise<PostMeta> {
    const { data } = await api.get(`${base}/posts/meta`);
    return data.data as PostMeta;
}
export async function getPost(id: number): Promise<PostDetail> {
    const { data } = await api.get(`${base}/posts/${id}`);
    return data.data as PostDetail;
}
export async function createPost(payload: PostPayload): Promise<PostDetail> {
    const { data } = await api.post(`${base}/posts`, payload);
    return data.data as PostDetail;
}
export async function updatePost(id: number, payload: PostPayload): Promise<PostDetail> {
    const { data } = await api.put(`${base}/posts/${id}`, payload);
    return data.data as PostDetail;
}
export async function deletePost(id: number): Promise<void> {
    await api.delete(`${base}/posts/${id}`);
}

/* --------------------------------------------------------------- taxonomies */

export type TaxonomyObjectType = 'post' | 'event' | 'notice';

/** Content types a taxonomy can be assigned to (mirrors App\Enums\Cms\TaxonomyObjectType). */
export const TAXONOMY_OBJECT_TYPES: { value: TaxonomyObjectType; label: string }[] = [
    { value: 'post', label: 'Posts' },
    { value: 'event', label: 'Events' },
    { value: 'notice', label: 'Notices' },
];

export interface TaxonomyRow {
    id: number;
    name: string;
    slug: string;
    hierarchical: boolean;
    object_types: TaxonomyObjectType[] | null;
    terms_count: number;
}
export interface TermRow {
    id: number;
    name: string;
    slug: string;
    parent_id: number | null;
    parent: string | null;
    description: string | null;
    position: number;
    featured_asset_id: number | null;
    posts_count: number;
}
export interface TermPayload {
    taxonomy_id?: number;
    name: string;
    slug?: string | null;
    parent_id?: number | null;
    description?: string | null;
    position?: number | null;
    featured_asset_id?: number | null;
}

export async function listTaxonomies(): Promise<TaxonomyRow[]> {
    const { data } = await api.get(`${base}/taxonomies`);
    return data.data as TaxonomyRow[];
}
export interface TaxonomyPayload {
    name: string;
    hierarchical?: boolean;
    object_types: TaxonomyObjectType[];
}
export async function createTaxonomy(payload: TaxonomyPayload): Promise<TaxonomyRow> {
    const { data } = await api.post(`${base}/taxonomies`, payload);
    return data.data as TaxonomyRow;
}
export async function updateTaxonomy(id: number, payload: TaxonomyPayload): Promise<TaxonomyRow> {
    const { data } = await api.put(`${base}/taxonomies/${id}`, payload);
    return data.data as TaxonomyRow;
}
export async function deleteTaxonomy(id: number): Promise<void> {
    await api.delete(`${base}/taxonomies/${id}`);
}
export async function getTaxonomyTerms(id: number): Promise<{ taxonomy: TaxonomyRow; terms: TermRow[] }> {
    const { data } = await api.get(`${base}/taxonomies/${id}`);
    return data.data as { taxonomy: TaxonomyRow; terms: TermRow[] };
}
export async function createTerm(payload: TermPayload): Promise<TermRow> {
    const { data } = await api.post(`${base}/terms`, payload);
    return data.data as TermRow;
}
export async function updateTerm(id: number, payload: TermPayload): Promise<TermRow> {
    const { data } = await api.put(`${base}/terms/${id}`, payload);
    return data.data as TermRow;
}
export async function deleteTerm(id: number): Promise<void> {
    await api.delete(`${base}/terms/${id}`);
}

/* -------------------------------------------------------------------- media */

export interface FolderRow {
    id: number;
    name: string;
    parent_id: number | null;
    position: number;
    assets_count: number;
}
export interface AssetRow extends AssetPayload {
    folder: { id: number; name: string } | null;
    uploader: string | null;
    created_at: string | null;
}

export async function listFolders(): Promise<FolderRow[]> {
    const { data } = await api.get(`${base}/media-folders`);
    return data.data as FolderRow[];
}
export async function createFolder(payload: { name: string; parent_id?: number | null }): Promise<FolderRow> {
    const { data } = await api.post(`${base}/media-folders`, payload);
    return data.data as FolderRow;
}
export async function updateFolder(id: number, payload: { name: string; parent_id?: number | null }): Promise<FolderRow> {
    const { data } = await api.put(`${base}/media-folders/${id}`, payload);
    return data.data as FolderRow;
}
export async function deleteFolder(id: number): Promise<void> {
    await api.delete(`${base}/media-folders/${id}`);
}
export interface PaginationMeta {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
}
export interface AssetListResponse {
    data: AssetRow[];
    pagination: PaginationMeta;
}
export interface MediaStats {
    files: number;
    size_bytes: number;
}
/** 'cms' (default) is the content-editor library; 'photo'/'logo' back the Student/Employee/Branch pickers. */
export type AssetCategory = 'cms' | 'photo' | 'logo';

export async function listAssets(
    params: { category?: AssetCategory; search?: string; type?: string; page?: number; per_page?: number } = {},
): Promise<AssetListResponse> {
    const { data } = await api.get(`${base}/media`, { params });
    return { data: data.data as AssetRow[], pagination: data.meta.pagination as PaginationMeta };
}
export async function getMediaStats(category?: AssetCategory): Promise<MediaStats> {
    const { data } = await api.get(`${base}/media/stats`, { params: { category } });
    return data.data as MediaStats;
}
export async function pickerAssets(params: { category?: AssetCategory; search?: string; type?: string } = {}): Promise<AssetPayload[]> {
    const { data } = await api.get(`${base}/media/picker`, { params });
    return data.data as AssetPayload[];
}
export async function getAsset(id: number | string): Promise<AssetPayload> {
    const { data } = await api.get(`${base}/media/${id}`);
    return data.data as AssetPayload;
}
export async function uploadAssets(files: FileList | File[], folderId: number | null, category?: AssetCategory): Promise<AssetPayload[]> {
    const form = new FormData();
    Array.from(files).forEach((f) => form.append('files[]', f));
    if (folderId) form.append('folder_id', String(folderId));
    if (category) form.append('category', category);
    const { data } = await api.post(`${base}/media`, form);
    return data.data as AssetPayload[];
}
export async function updateAsset(
    id: number,
    payload: { name: string; alt_text?: string | null; caption?: string | null; media_folder_id?: number | null },
): Promise<AssetPayload> {
    const { data } = await api.put(`${base}/media/${id}`, payload);
    return data.data as AssetPayload;
}
export async function deleteAsset(id: number): Promise<void> {
    await api.delete(`${base}/media/${id}`);
}
/** Deterministic URL for a private-category (photo/logo) asset — no extra fetch needed. */
export function assetFileUrl(id: number | string, conversion?: 'thumb' | 'preview'): string {
    return `${base}/media/${id}/file${conversion ? `?conversion=${conversion}` : ''}`;
}

/* -------------------------------------------------------------------- menus */

export interface MenuRow {
    id: number;
    name: string;
    key: string;
    is_active: boolean;
    items_count: number;
}
export interface MenuTreeItem {
    id: number | null;
    label: string;
    linkable_type: string | null;
    linkable_id: number | null;
    url: string | null;
    target: '_self' | '_blank';
    is_visible: boolean;
    resolved_url?: string | null;
    children: MenuTreeItem[];
}
export interface LinkTarget {
    id: number;
    title?: string;
    name?: string;
    path?: string;
    slug?: string;
}
export interface MenuDetail {
    menu: { id: number; name: string; key: string; is_active: boolean };
    items: MenuTreeItem[];
    link_targets: { pages: LinkTarget[]; posts: LinkTarget[]; terms: LinkTarget[] };
}

export async function listMenus(): Promise<MenuRow[]> {
    const { data } = await api.get(`${base}/menus`);
    return data.data as MenuRow[];
}
export async function createMenu(payload: { name: string; key: string; is_active?: boolean }): Promise<MenuRow> {
    const { data } = await api.post(`${base}/menus`, payload);
    return data.data as MenuRow;
}
export async function updateMenu(id: number, payload: { name: string; key: string; is_active?: boolean }): Promise<MenuRow> {
    const { data } = await api.put(`${base}/menus/${id}`, payload);
    return data.data as MenuRow;
}
export async function deleteMenu(id: number): Promise<void> {
    await api.delete(`${base}/menus/${id}`);
}
export async function getMenu(id: number): Promise<MenuDetail> {
    const { data } = await api.get(`${base}/menus/${id}`);
    return data.data as MenuDetail;
}
export async function saveMenuTree(id: number, items: MenuTreeItem[]): Promise<MenuTreeItem[]> {
    const { data } = await api.put(`${base}/menus/${id}/tree`, { items });
    return data.data as MenuTreeItem[];
}

/* ---------------------------------------------------------------- redirects */

export interface RedirectRow {
    id: number;
    from_path: string;
    to_path: string;
    status_code: number;
    hits: number;
    is_active: boolean;
}
export async function listRedirects(params: { search?: string } = {}): Promise<RedirectRow[]> {
    const { data } = await api.get(`${base}/redirects`, { params: { per_page: 200, ...params } });
    return data.data as RedirectRow[];
}
export async function createRedirect(payload: { from_path: string; to_path: string; status_code: number; is_active?: boolean }): Promise<RedirectRow> {
    const { data } = await api.post(`${base}/redirects`, payload);
    return data.data as RedirectRow;
}
export async function updateRedirect(id: number, payload: { from_path: string; to_path: string; status_code: number; is_active?: boolean }): Promise<RedirectRow> {
    const { data } = await api.put(`${base}/redirects/${id}`, payload);
    return data.data as RedirectRow;
}
export async function deleteRedirect(id: number): Promise<void> {
    await api.delete(`${base}/redirects/${id}`);
}

/* ------------------------------------------------------------------ notices */

export interface MetaTerm {
    id: number;
    name: string;
    taxonomy: string | null;
}

export interface NoticeRow {
    id: number;
    title: string;
    slug: string;
    status: ContentStatus;
    is_important: boolean;
    notice_date: string | null;
    published_at: string | null;
    categories: string[];
    attachment: AssetPayload | null;
    deleted_at: string | null;
}
export interface NoticeDetail {
    id: number;
    title: string;
    slug: string;
    body: string | null;
    notice_date: string | null;
    is_important: boolean;
    status: ContentStatus;
    published_at: string | null;
    attachment_asset_id: number | null;
    attachment: AssetPayload | null;
    terms: number[];
}
export interface ContentMeta {
    statuses: { value: string; label: string }[];
    terms: MetaTerm[];
}
export interface NoticePayload {
    title: string;
    slug?: string | null;
    body?: string | null;
    notice_date: string;
    is_important?: boolean;
    status: ContentStatus;
    published_at?: string | null;
    attachment_asset_id?: number | null;
    terms?: number[];
}

export async function listNotices(params: { search?: string; status?: string; sort?: string; direction?: SortDir } = {}): Promise<NoticeRow[]> {
    const { data } = await api.get(`${base}/notices`, { params: { per_page: 200, ...params } });
    return data.data as NoticeRow[];
}
export async function getNoticeMeta(): Promise<ContentMeta> {
    const { data } = await api.get(`${base}/notices/meta`);
    return data.data as ContentMeta;
}
export async function getNotice(id: number): Promise<NoticeDetail> {
    const { data } = await api.get(`${base}/notices/${id}`);
    return data.data as NoticeDetail;
}
export async function createNotice(payload: NoticePayload): Promise<NoticeDetail> {
    const { data } = await api.post(`${base}/notices`, payload);
    return data.data as NoticeDetail;
}
export async function updateNotice(id: number, payload: NoticePayload): Promise<NoticeDetail> {
    const { data } = await api.put(`${base}/notices/${id}`, payload);
    return data.data as NoticeDetail;
}
export async function deleteNotice(id: number): Promise<void> {
    await api.delete(`${base}/notices/${id}`);
}

/* ------------------------------------------------------------------- events */

export interface EventRow {
    id: number;
    title: string;
    slug: string;
    status: ContentStatus;
    starts_at: string | null;
    ends_at: string | null;
    location: string | null;
    categories: string[];
    featured_asset: AssetPayload | null;
    deleted_at: string | null;
}
export interface EventDetail {
    id: number;
    title: string;
    slug: string;
    body: string | null;
    starts_at: string | null;
    ends_at: string | null;
    location: string | null;
    status: ContentStatus;
    published_at: string | null;
    featured_asset_id: number | null;
    featured_asset: AssetPayload | null;
    terms: number[];
}
export interface EventPayload {
    title: string;
    slug?: string | null;
    body?: string | null;
    starts_at: string;
    ends_at?: string | null;
    location?: string | null;
    featured_asset_id?: number | null;
    status: ContentStatus;
    published_at?: string | null;
    terms?: number[];
}

export async function listEvents(params: { search?: string; status?: string; sort?: string; direction?: SortDir } = {}): Promise<EventRow[]> {
    const { data } = await api.get(`${base}/events`, { params: { per_page: 200, ...params } });
    return data.data as EventRow[];
}
export async function getEventMeta(): Promise<ContentMeta> {
    const { data } = await api.get(`${base}/events/meta`);
    return data.data as ContentMeta;
}
export async function getEvent(id: number): Promise<EventDetail> {
    const { data } = await api.get(`${base}/events/${id}`);
    return data.data as EventDetail;
}
export async function createEvent(payload: EventPayload): Promise<EventDetail> {
    const { data } = await api.post(`${base}/events`, payload);
    return data.data as EventDetail;
}
export async function updateEvent(id: number, payload: EventPayload): Promise<EventDetail> {
    const { data } = await api.put(`${base}/events/${id}`, payload);
    return data.data as EventDetail;
}
export async function deleteEvent(id: number): Promise<void> {
    await api.delete(`${base}/events/${id}`);
}

/* ---------------------------------------------------------------- ui tokens */

export const inputCls =
    'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';
export const labelCls = 'block text-[13px] font-medium text-fg mb-1.5';
