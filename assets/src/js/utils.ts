/**
 * Validates if a given string is a valid http or https URL.
 *
 * @param {string} url - The URL string to validate.
 *
 * @return {boolean} True if the URL is valid, false otherwise.
 */
export const isValidUrl = ( url: string ): boolean => {
	try {
		return [ 'http:', 'https:' ].includes( new URL( url ).protocol );
	} catch {
		return false;
	}
};

/**
 * Normalizes a URL for comparison: lowercases the scheme and host, and ensures a trailing slash.
 *
 * Matches `Utils::normalize_url()` on the server, so `HTTPS://Brand.test` and `https://brand.test/` are the same site.
 *
 * @param {string} url - The URL string to normalize.
 * @return {string} The normalized URL.
 */
export const normalizeUrl = ( url: string ): string => {
	const trimmed = url.trim();

	if ( ! trimmed ) {
		return '';
	}

	const lowered = trimmed.replace(
		/^([a-z][a-z0-9+.-]*:\/\/[^/?#]*)/i,
		( origin ) => origin.toLowerCase()
	);

	return lowered.endsWith( '/' ) ? lowered : `${ lowered }/`;
};

/**
 * Gets a readable message from an error thrown by apiFetch or elsewhere.
 *
 * apiFetch rejects with the REST error object, like `{ code, message }`, which isn't an `Error`.
 *
 * @param {unknown} error    The caught error.
 * @param {string}  fallback The message to use when the error has none.
 * @return {string} The message.
 */
export const getErrorMessage = ( error: unknown, fallback: string ): string => {
	if (
		error &&
		typeof error === 'object' &&
		'message' in error &&
		typeof error.message === 'string' &&
		error.message
	) {
		return error.message;
	}

	return fallback;
};

/**
 * Formats a date string into a more readable format.
 *
 * @param {string} dateString The date string to format.
 *
 * @return {string} The formatted date string.
 */
export const formatDate = ( dateString: string ) => {
	return new Date( dateString ).toLocaleString( 'en-US', {
		month: 'short',
		day: 'numeric',
		year: 'numeric',
		hour: 'numeric',
		minute: '2-digit',
		hour12: true,
	} );
};
