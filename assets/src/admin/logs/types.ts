export interface UserOption {
	id: number;
	display_name: string;
	user_login?: string;
	user_email?: string;
	gravatar_url?: string;
}

export interface LogEntry {
	ID: number;
	site_id: number;
	blog_id: number;
	current_site_name: string;
	user_id: number;
	user_role: string;
	object_id: number | null;
	connector: string;
	context: string;
	action: string;
	summary: string;
	created: string;
	ip: string;
	meta: Record<string, []>;
	user_display_name?: string;
	site_name?: string;
	site_url?: string;
	is_remote?: boolean;
	user?: {
		display_name?: string;
		avatar_url?: string;
	};
	object_data?: {
		title?: string;
		edit_link_text?: string;
		edit_link?: string;
	};
}

export interface FilterOptions extends Record<string, unknown> {
	page: number;
	per_page: number;
	orderby?: string;
	order?: 'asc' | 'desc';
	site_id?: number;
	blog_id?: number;
	user_id?: number;
	connector?: string;
	context?: string;
	action?: string;
	search?: string;
	date_from?: string;
	date_to?: string;
	site_url?: string | undefined;
	exclude_current_site?: boolean;
	current_site_logs?: boolean;
	include_shared_sites?: boolean;
}

export type SortableField = 'ID' | 'summary' | 'connector' | 'context' | 'action' | 'user_id' | 'ip' | 'created' | 'site_name';

export interface SortState {
	field: SortableField | null | string;
	direction: 'asc' | 'desc' | null;
}

export type APIResponse = {
	[key: string]: unknown;
	data: object[] | LogEntry[] | UserOption[];
	meta: {
		total: number;
		total_pages: number;
		errors?: string | [];
	};
};

export type StringArrayResponse = string[];

export type fetchReturn = APIResponse | StringArrayResponse | string | SharedSite[];

export interface SharedSite {
	api_key: string;
	id: string;
	name: string;
	url: string;
}

export interface OneLogsDataType {
	restUrl: string;
	restNonce: string;
	apiKey: string;
}
