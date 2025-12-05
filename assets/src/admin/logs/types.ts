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
	user_id: number;
	user_role: string;
	object_id: number | null;
	connector: string;
	context: string;
	action: string;
	summary: string;
	created: string;
	ip: string;
	meta: Record<string, any>;
	user_display_name?: string;
	site_name?: string;
	site_url?: string;
	is_remote?: boolean;
}

export interface FilterOptions {
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
	site_url?: string;
	exclude_current_site?: boolean;
}

export interface LocalFilterOptions {
	search?: string;
}

export type SortableField = 'ID' | 'summary' | 'connector' | 'context' | 'action' | 'user_id' | 'ip' | 'created' | 'site_name';

export interface SortState {
	field: SortableField | null;
	direction: 'asc' | 'desc' | null;
}
