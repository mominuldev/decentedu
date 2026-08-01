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
    _key?: number;
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
export async function getPage(idOrSlug: number | string): Promise<PageDetail> {
    const { data } = await api.get(`${base}/pages/${idOrSlug}`);
    return data.data as PageDetail;
}
export async function createPage(payload: PagePayload): Promise<PageDetail> {
    const { data } = await api.post(`${base}/pages`, payload);
    return data.data as PageDetail;
}
export async function updatePage(idOrSlug: number | string, payload: PagePayload): Promise<PageDetail> {
    const { data } = await api.put(`${base}/pages/${idOrSlug}`, payload);
    return data.data as PageDetail;
}
export async function deletePage(idOrSlug: number | string): Promise<void> {
    await api.delete(`${base}/pages/${idOrSlug}`);
}
export async function restorePage(idOrSlug: number | string): Promise<void> {
    await api.post(`${base}/pages/${idOrSlug}/restore`);
}
export async function forceDeletePage(idOrSlug: number | string): Promise<void> {
    await api.delete(`${base}/pages/${idOrSlug}/force`);
}
export async function duplicatePage(idOrSlug: number | string): Promise<PageDetail> {
    const { data } = await api.post(`${base}/pages/${idOrSlug}/duplicate`);
    return data.data as PageDetail;
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
export async function getPost(idOrSlug: number | string): Promise<PostDetail> {
    const { data } = await api.get(`${base}/posts/${idOrSlug}`);
    return data.data as PostDetail;
}
export async function createPost(payload: PostPayload): Promise<PostDetail> {
    const { data } = await api.post(`${base}/posts`, payload);
    return data.data as PostDetail;
}
export async function updatePost(idOrSlug: number | string, payload: PostPayload): Promise<PostDetail> {
    const { data } = await api.put(`${base}/posts/${idOrSlug}`, payload);
    return data.data as PostDetail;
}
export async function deletePost(idOrSlug: number | string): Promise<void> {
    await api.delete(`${base}/posts/${idOrSlug}`);
}
export async function restorePost(idOrSlug: number | string): Promise<void> {
    await api.post(`${base}/posts/${idOrSlug}/restore`);
}
export async function forceDeletePost(idOrSlug: number | string): Promise<void> {
    await api.delete(`${base}/posts/${idOrSlug}/force`);
}
export async function duplicatePost(idOrSlug: number | string): Promise<PostDetail> {
    const { data } = await api.post(`${base}/posts/${idOrSlug}/duplicate`);
    return data.data as PostDetail;
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
export async function getNotice(idOrSlug: number | string): Promise<NoticeDetail> {
    const { data } = await api.get(`${base}/notices/${idOrSlug}`);
    return data.data as NoticeDetail;
}
export async function createNotice(payload: NoticePayload): Promise<NoticeDetail> {
    const { data } = await api.post(`${base}/notices`, payload);
    return data.data as NoticeDetail;
}
export async function updateNotice(idOrSlug: number | string, payload: NoticePayload): Promise<NoticeDetail> {
    const { data } = await api.put(`${base}/notices/${idOrSlug}`, payload);
    return data.data as NoticeDetail;
}
export async function deleteNotice(idOrSlug: number | string): Promise<void> {
    await api.delete(`${base}/notices/${idOrSlug}`);
}
export async function restoreNotice(idOrSlug: number | string): Promise<void> {
    await api.post(`${base}/notices/${idOrSlug}/restore`);
}
export async function forceDeleteNotice(idOrSlug: number | string): Promise<void> {
    await api.delete(`${base}/notices/${idOrSlug}/force`);
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
export async function getEvent(idOrSlug: number | string): Promise<EventDetail> {
    const { data } = await api.get(`${base}/events/${idOrSlug}`);
    return data.data as EventDetail;
}
export async function createEvent(payload: EventPayload): Promise<EventDetail> {
    const { data } = await api.post(`${base}/events`, payload);
    return data.data as EventDetail;
}
export async function updateEvent(idOrSlug: number | string, payload: EventPayload): Promise<EventDetail> {
    const { data } = await api.put(`${base}/events/${idOrSlug}`, payload);
    return data.data as EventDetail;
}
export async function deleteEvent(idOrSlug: number | string): Promise<void> {
    await api.delete(`${base}/events/${idOrSlug}`);
}
export async function restoreEvent(idOrSlug: number | string): Promise<void> {
    await api.post(`${base}/events/${idOrSlug}/restore`);
}
export async function forceDeleteEvent(idOrSlug: number | string): Promise<void> {
    await api.delete(`${base}/events/${idOrSlug}/force`);
}

/* ------------------------------------------------------------------ galleries */

export interface GalleryRow {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    cover_asset_id: number | null;
    cover_asset: AssetPayload | null;
    image_ids: number[];
    images: AssetPayload[];
    status: ContentStatus;
    published_at: string | null;
    created_at: string;
    deleted_at: string | null;
}

export interface GalleryPayload {
    title: string;
    slug?: string | null;
    description?: string | null;
    cover_asset_id?: number | null;
    images?: number[];
    status: ContentStatus;
    published_at?: string | null;
}

export async function listGalleries(params: { search?: string; status?: string; sort?: string; direction?: SortDir } = {}): Promise<GalleryRow[]> {
    const { data } = await api.get(`${base}/galleries`, { params: { per_page: 200, ...params } });
    return data.data as GalleryRow[];
}
export async function getGallery(idOrSlug: number | string): Promise<GalleryRow> {
    const { data } = await api.get(`${base}/galleries/${idOrSlug}`);
    return data.data as GalleryRow;
}
export async function createGallery(payload: GalleryPayload): Promise<GalleryRow> {
    const { data } = await api.post(`${base}/galleries`, payload);
    return data.data as GalleryRow;
}
export async function updateGallery(idOrSlug: number | string, payload: GalleryPayload): Promise<GalleryRow> {
    const { data } = await api.put(`${base}/galleries/${idOrSlug}`, payload);
    return data.data as GalleryRow;
}
export async function deleteGallery(idOrSlug: number | string): Promise<void> {
    await api.delete(`${base}/galleries/${idOrSlug}`);
}
export async function restoreGallery(idOrSlug: number | string): Promise<void> {
    await api.post(`${base}/galleries/${idOrSlug}/restore`);
}
export async function forceDeleteGallery(idOrSlug: number | string): Promise<void> {
    await api.delete(`${base}/galleries/${idOrSlug}/force`);
}
export async function duplicateGallery(idOrSlug: number | string): Promise<GalleryRow> {
    const { data } = await api.post(`${base}/galleries/${idOrSlug}/duplicate`);
    return data.data as GalleryRow;
}

/* -------------------------------------------------------------- site settings */

/** One footer link column: an authored heading over an existing menu's items. */
export interface FooterMenuColumn {
    title: string;
    menu_id: number | null;
}

export interface SiteSettingDetail {
    id: number;
    site_title: string;
    site_tagline: string | null;
    site_description: string | null;
    header_logo_asset_id: number | null;
    header_logo: AssetPayload | null;
    footer_logo_asset_id: number | null;
    footer_logo: AssetPayload | null;
    favicon_asset_id: number | null;
    favicon: AssetPayload | null;
    eiin: string | null;
    color_scheme: string | null;
    /** Per-token hex overrides on top of the chosen (or default) preset. */
    brand_colors: Record<string, string> | null;
    header_topbar_cta_label: string | null;
    header_topbar_cta_url: string | null;
    header_cta_label: string | null;
    header_cta_url: string | null;
    contact_email: string | null;
    contact_phone: string | null;
    contact_address: string | null;
    footer_description: string | null;
    footer_menus: FooterMenuColumn[];
    /** Sanitized HTML from the WYSIWYG — the bottom-bar copyright line. */
    footer_copyright: string | null;
    footer_bottom_menu_id: number | null;
    facebook_url: string | null;
    twitter_url: string | null;
    linkedin_url: string | null;
    youtube_url: string | null;
    instagram_url: string | null;
    google_analytics_code: string | null;
    google_tag_manager_code: string | null;
    meta_keywords: string | null;
    additional_settings: Record<string, unknown> | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface SiteSettingPayload {
    site_title: string;
    site_tagline?: string | null;
    site_description?: string | null;
    header_logo_asset_id?: number | null;
    footer_logo_asset_id?: number | null;
    favicon_asset_id?: number | null;
    eiin?: string | null;
    color_scheme?: string | null;
    brand_colors?: Record<string, string> | null;
    header_topbar_cta_label?: string | null;
    header_topbar_cta_url?: string | null;
    header_cta_label?: string | null;
    header_cta_url?: string | null;
    contact_email?: string | null;
    contact_phone?: string | null;
    contact_address?: string | null;
    footer_description?: string | null;
    footer_menus?: FooterMenuColumn[];
    footer_copyright?: string | null;
    footer_bottom_menu_id?: number | null;
    facebook_url?: string | null;
    twitter_url?: string | null;
    linkedin_url?: string | null;
    youtube_url?: string | null;
    instagram_url?: string | null;
    google_analytics_code?: string | null;
    google_tag_manager_code?: string | null;
    meta_keywords?: string | null;
    additional_settings?: Record<string, unknown> | null;
}

/** One curated color-scheme preset: display copy plus its 15-token color map. */
export interface ColorScheme {
    label: string;
    description: string;
    colors: Record<string, string>;
}

export async function listColorSchemes(): Promise<Record<string, ColorScheme>> {
    const { data } = await api.get(`${base}/site-settings/color-schemes`);
    return data.data as Record<string, ColorScheme>;
}

export async function getSiteSettings(): Promise<SiteSettingDetail> {
    const { data } = await api.get(`${base}/site-settings`);
    return data.data as SiteSettingDetail;
}

export async function updateSiteSettings(payload: SiteSettingPayload): Promise<SiteSettingDetail> {
    const { data } = await api.put(`${base}/site-settings`, payload);
    return data.data as SiteSettingDetail;
}

export async function resetSiteSettings(): Promise<SiteSettingDetail> {
    const { data } = await api.post(`${base}/site-settings/reset`);
    return data.data as SiteSettingDetail;
}

export async function exportSiteSettings(): Promise<void> {
    const response = await api.get(`${base}/site-settings/export`, {
        responseType: 'blob',
    });
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `site-settings-${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
}

export async function importSiteSettings(file: File): Promise<SiteSettingDetail> {
    const formData = new FormData();
    formData.append('file', file);

    const { data } = await api.post(`${base}/site-settings/import`, formData, {
        headers: {
            'Content-Type': 'multipart/form-data',
        },
    });
    return data.data as SiteSettingDetail;
}

/* ---------------------------------------------------------------- ui tokens */

export const inputCls =
    'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';
export const labelCls = 'block text-[13px] font-medium text-fg mb-1.5';
