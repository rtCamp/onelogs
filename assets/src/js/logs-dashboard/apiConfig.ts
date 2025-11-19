import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export const setupApiFetch = () => {
	apiFetch.setFetchHandler( ( { url, path, data, method, includeHeaders } ) => {
		if ( ! url && ! path ) {
			throw new Error( __( 'Either url or path must be provided.', 'onelogs' ) );
		}

		const fetchOptions: RequestInit = {
			method: method || 'GET',
			headers: {
				'Content-Type': 'application/json',
			},
			body: data ? JSON.stringify( data ) : null,
		};

		return fetch( url ?? path, fetchOptions ).then( ( response ) => {
			if ( includeHeaders ) {
				return Promise.all( [ response.json(), response.headers ] ).then( ( [ body, headers ] ) => {
					return { body, headers };
				} );
			}
			return response.json();
		} );
	} );
};
