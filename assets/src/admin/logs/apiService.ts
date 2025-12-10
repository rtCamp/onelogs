import type { APIResponse, fetchReturn, FilterOptions, OneLogsDataType } from './types';
import apiFetch from '@wordpress/api-fetch';
import { NONCE, API_KEY } from '../../js/constants';

declare global {
	interface Window {
		OneLogsData?: OneLogsDataType;
	}
}

type FetchOptions = {
	filters?: {
		site_url?: string | undefined;
	};
	queryParams?: URLSearchParams;
	returnKey?: string;
	headers?: Record<string, string>;
};

export const fetchLogs = async (
	filters: FilterOptions,
) => {
	const formattedFilters = {
		...filters,
		date_from: filters.date_from ? `${ filters.date_from } 00:00:00` : undefined,
		date_to: filters.date_to ? `${ filters.date_to } 23:59:59` : undefined,
	};

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

		const apiResponse = await oneLogsFetch<APIResponse>( 'logs', { queryParams } );

		return {
			logs: apiResponse?.data || [],
			total: apiResponse?.meta.total || 0,
			pages: apiResponse?.meta.total_pages || 1,
			errors: apiResponse?.meta.errors,
		};
	}
	const queryParams = new URLSearchParams();
	Object.entries( formattedFilters ).forEach( ( [ key, value ] ) => {
		if ( value !== undefined && value !== null ) {
			queryParams.append( key, String( value ) );
		}
	} );

	const apiResponse = await oneLogsFetch<APIResponse>( 'logs', { queryParams } );

	return {
		logs: apiResponse.data,
		total: apiResponse.meta.total,
		pages: apiResponse.meta.total_pages,
		errors: null,
	};
};

export const fetchContexts = async ( filters: FilterOptions ): Promise<string[]> => oneLogsFetch<string[]>( 'logs/contexts', {
	filters,
	returnKey: 'data',
} );

export const fetchActions = async ( filters: FilterOptions ): Promise<string[]> => oneLogsFetch<string[]>( 'logs/actions', {
	filters,
	returnKey: 'data',
} );

export const fetchConnectors = async ( filters: FilterOptions ): Promise<string[]> => oneLogsFetch( 'logs/connectors', { filters } );

export const fetchUsers = async ( filters: FilterOptions ): Promise<APIResponse> => oneLogsFetch( 'logs/users', { filters } );

export const fetchSharedSites = async () => oneLogsFetch( 'shared-sites', { returnKey: 'shared_sites' } );

export const fetchSiteType = async () => oneLogsFetch( 'site-type', { returnKey: 'site_type' } );

const oneLogsFetch = async <T extends fetchReturn>(
	endpoint: string,
	options: FetchOptions = {},
): Promise<T> => {
	const { filters, queryParams, returnKey } = options;

	let query = '';

	if ( queryParams ) {
		query = `?${ queryParams.toString() }`;
	} else if ( filters?.site_url && filters.site_url !== 'governing-site' ) {
		query = `?site_url=${ filters.site_url }`;
	}

	apiFetch.use( apiFetch.createNonceMiddleware( NONCE ) );

	apiFetch.use( ( fetchOptions, next ) => {
		fetchOptions.headers = {
			...( fetchOptions.headers || {} ),
			'X-OneLogs-Token': API_KEY,
		};
		return next( fetchOptions );
	} );

	const apiResponse = await apiFetch( {
		path: `/onelogs/v1/${ endpoint }${ query }`,
		method: 'GET',
	} );

	const result = apiResponse as APIResponse;

	return ( returnKey ? ( result[ returnKey ] ?? [] ) : result ) as T;
};
