/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import type {
	APIResponse,
	fetchReturn,
	FilterOptions,
	SharedSite,
} from './types';
import { REST_NAMESPACE, SHARED_SITES } from '../../js/constants';

type FetchOptions = {
	filters?: {
		site_url?: string | undefined;
	};
	queryParams?: URLSearchParams;
	returnKey?: string;
};

export const fetchLogs = async ( filters: FilterOptions ) => {
	const formattedFilters = {
		...filters,
		date_from: filters.date_from
			? `${ filters.date_from } 00:00:00`
			: undefined,
		date_to: filters.date_to ? `${ filters.date_to } 23:59:59` : undefined,
	};

	const isBrandSite =
		!! filters.site_url && filters.site_url !== 'governing-site';

	// The governing site fetches a selected brand site's logs for the browser.
	formattedFilters.current_site_logs = ! isBrandSite;
	formattedFilters.include_shared_sites = isBrandSite;
	if ( ! isBrandSite ) {
		formattedFilters.site_url = undefined;
	}

	const queryParams = new URLSearchParams();
	Object.entries( formattedFilters ).forEach( ( [ key, value ] ) => {
		if (
			value !== undefined &&
			value !== null &&
			! ( isBrandSite && key === 'site_id' )
		) {
			queryParams.append( key, String( value ) );
		}
	} );

	const apiResponse = await oneLogsFetch< APIResponse >( 'logs', {
		queryParams,
	} );

	return {
		// A missing list is no logs, never a crash.
		logs: Array.isArray( apiResponse?.data ) ? apiResponse.data : [],
		total: apiResponse?.meta?.total || 0,
		pages: apiResponse?.meta?.total_pages || 1,
		errors: isBrandSite ? apiResponse?.meta?.errors : null,
	};
};

export const fetchContexts = async (
	filters: FilterOptions
): Promise< string[] > =>
	oneLogsFetch< string[] >( 'logs/contexts', {
		filters,
		returnKey: 'data',
	} );

export const fetchActions = async (
	filters: FilterOptions
): Promise< string[] > =>
	oneLogsFetch< string[] >( 'logs/actions', {
		filters,
		returnKey: 'data',
	} );

export const fetchConnectors = async (
	filters: FilterOptions
): Promise< string[] > => oneLogsFetch( 'logs/connectors', { filters } );

export const fetchUsers = async (
	filters: FilterOptions
): Promise< APIResponse > => oneLogsFetch( 'logs/users', { filters } );

/**
 * Gets the governing site's brand sites, which the page already has.
 */
export const fetchSharedSites = async (): Promise< SharedSite[] > =>
	SHARED_SITES;

/**
 * Requests a OneLogs route on this site.
 *
 * WordPress sets up apiFetch with the REST root and nonce, so requests only need the path.
 * Rejects with the REST error, like `{ code, message }`, when the route fails.
 *
 * @param endpoint The route, relative to the OneLogs namespace.
 * @param options  The query, and the key of the response to return.
 */
const oneLogsFetch = async < T extends fetchReturn >(
	endpoint: string,
	options: FetchOptions = {}
): Promise< T > => {
	const { filters, queryParams, returnKey } = options;

	let query = '';

	if ( queryParams ) {
		query = `?${ queryParams.toString() }`;
	} else if ( filters?.site_url && filters.site_url !== 'governing-site' ) {
		query = `?${ new URLSearchParams( {
			site_url: filters.site_url,
		} ).toString() }`;
	}

	const result = await apiFetch< APIResponse >( {
		path: `/${ REST_NAMESPACE }/${ endpoint }${ query }`,
		method: 'GET',
	} );

	return ( returnKey ? result?.[ returnKey ] ?? [] : result ) as T;
};
