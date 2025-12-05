import { FilterOptions, UserOption } from './types';
import apiFetch from "@wordpress/api-fetch";

const API_NAMESPACE = window.OneLogsData.restUrl + '/onelogs/v1';
const NONCE = window.OneLogsData.restNonce;
const API_KEY = window.OneLogsData.apiKey;

type FetchOptions = {
	filters?: Record<string, any>;
	queryParams?: URLSearchParams;
	returnKey?: string;
	headers?: Record<string, string>;
};

export const fetchLogs = async (
	filters: FilterOptions,
	showSharedSitesLogs: boolean,
	sharedSites: any[],
) => {
	const formattedFilters = {
		...filters,
		date_from: filters.date_from ? `${ filters.date_from } 00:00:00` : undefined,
		date_to: filters.date_to ? `${ filters.date_to } 23:59:59` : undefined,
	};

	let response: any;

	if ( filters.site_url === 'governing-site' ) {
		formattedFilters.current_site_logs = true;
		formattedFilters.site_url = undefined;
		formattedFilters.include_shared_sites = false;
	} else {
		formattedFilters.current_site_logs = false;
		formattedFilters.include_shared_sites = true;
	}

	if ( ! formattedFilters.current_site_logs ) {
		const queryParams = new URLSearchParams();
		formattedFilters.include_shared_sites = true;

		Object.entries( formattedFilters ).forEach( ( [ key, value ] ) => {
			if ( value !== undefined && value !== null && key !== 'site_id' ) {
				queryParams.append( key, String( value ) );
			}
		} );

		const apiResponse = await oneLogsFetch( 'logs', { queryParams } );

		return {
			logs: apiResponse.data || [],
			total: apiResponse.meta.total || 0,
			pages: apiResponse.meta.total_pages || 1,
			errors: apiResponse.meta.errors,
		};
	}
	const queryParams = new URLSearchParams();
	Object.entries( formattedFilters ).forEach( ( [ key, value ] ) => {
		if ( value !== undefined && value !== null ) {
			queryParams.append( key, String( value ) );
		}
	} );

	const apiResponse = await oneLogsFetch( 'logs', { queryParams } );

	return {
		logs: apiResponse.data,
		total: apiResponse.meta.total,
		pages: apiResponse.meta.total_pages,
		errors: null,
	};
};

export const fetchContexts = async ( filters ): Promise<string[]> => oneLogsFetch<string[]>( 'logs/contexts', {
	filters,
	returnKey: 'data',
} );

export const fetchActions = async ( filters ): Promise<string[]> => oneLogsFetch<string[]>( 'logs/actions', {
	filters,
	returnKey: 'data',
} );

export const fetchConnectors = async ( filters ): Promise<string[]> => oneLogsFetch( 'logs/connectors', { filters } );

export const fetchUsers = async ( filters ): Promise<UserOption[]> => oneLogsFetch( 'logs/users', {
	filters,
	returnKey: 'data',
} );

export const fetchSharedSites = async (): Promise<any[]> => oneLogsFetch<any[]>( 'shared-sites', { returnKey: 'shared_sites' } );

export const fetchSiteType = async () => oneLogsFetch<any[]>( 'site-type', { returnKey: 'site_type' } );

const oneLogsFetch = async <T = any>(
	endpoint: string,
	options: FetchOptions = {},
): Promise<T> => {
	const { filters, queryParams, returnKey, headers = {} } = options;

	let query = '';

	if ( queryParams ) {
		query = `?${ queryParams.toString() }`;
	} else if ( filters?.site_url && filters.site_url !== 'governing-site' ) {
		query = `?site_url=${ filters.site_url }`;
	}

	apiFetch.use( apiFetch.createNonceMiddleware( NONCE ) );

	apiFetch.use( ( options, next ) => {
		options.headers = {
			...( options.headers || {} ),
			'X-OneLogs-Token': API_KEY,
		};
		return next( options );
	});

	const apiResponse = await apiFetch( {
		path: `/onelogs/v1/${ endpoint }${ query }`,
		method: 'GET',
	});

	const result = apiResponse;

	return returnKey ? result?.[ returnKey ] ?? [] : result;
};
