/**
 * Validates if a given string is a valid URL.
 *
 * @param {string} url - The URL string to validate.
 *
 * @return {boolean} True if the URL is valid, false otherwise.
 */
export const isValidUrl = ( url: string ): boolean => {
	try {
		new URL( url );
		return true;
	} catch {
		return false;
	}
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
