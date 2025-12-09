/**
 * Helper function to extract initials from a name.
 *
 * @param {string} name - The name to extract initials from.
 * @return {string} The extracted initials (up to 2 characters).
 */
const getInitials = ( name ) => {
	// Handle empty or invalid names
	if ( ! name || typeof name !== 'string' ) {
		return '?';
	}

	// Trim the name and convert to proper case
	const trimmedName = name.trim();
	if ( ! trimmedName ) {
		return '?';
	}

	// Split the name by spaces and other separators
	const parts = trimmedName
		.split( /[\s-_,.]+/ )
		.filter( ( part ) => part.length > 0 );

	// For single word names
	if ( parts.length === 1 ) {
		// If name is a single character, return that character
		if ( parts[ 0 ].length === 1 ) {
			return parts[ 0 ].toUpperCase();
		}
		// Otherwise return first two characters
		return parts[ 0 ].substring( 0, 2 ).toUpperCase();
	}

	// For multi-word names, take first letter of first two parts
	return (
		parts[ 0 ].charAt( 0 ) + ( parts[ 1 ] ? parts[ 1 ].charAt( 0 ) : '' )
	).toUpperCase();
};

/**
 * Helper function to validate if a string is a well-formed URL.
 *
 * @param {string} str - The string to validate as a URL.
 *
 * @return {boolean} True if the string is a valid URL, false otherwise.
 */
const isURL = ( str ) => {
	const pattern = new RegExp(
		'^https?:\\/\\/' +
		'(?:[a-z\\d](?:[a-z\\d-]*[a-z\\d])?\\.)?' +
		'[a-z\\d](?:[a-z\\d-]*[a-z\\d])?\\.' +
		'[a-z]{2,}' +
		'(?::\\d+)?' +
		'(?:\\/[^\\s]*)?' +
		'$', 'i',
	);
	return pattern.test( str );
};

/**
 * Validates if a given string is a valid URL.
 *
 * @param {string} url - The URL string to validate.
 *
 * @return {boolean} True if the URL is valid, false otherwise.
 */
const isValidUrl = ( url ) => {
	try {
		const parsedUrl = new URL( url );
		return isURL( parsedUrl.href );
	} catch ( e ) {
		return false;
	}
};

/**
 * Debounce function to limit the rate at which a function can fire.
 *
 * @param {Function} func      The function to debounce
 * @param {number}   wait      The number of milliseconds to wait
 * @param {boolean}  immediate If true, trigger the function on the leading edge, instead of the trailing.
 *
 * @return {(function(): void)|*} The debounced function
 */
function debounce( func, wait, immediate ) {
	let timeout;
	return function() {
		const context = this,
			args = arguments;
		clearTimeout( timeout );
		if ( immediate && ! timeout ) {
			func.apply( context, args );
		}
		timeout = setTimeout( function() {
			timeout = null;
			if ( ! immediate ) {
				func.apply( context, args );
			}
		}, wait );
	};
}

/**
 * Formats a date string into a more readable format.
 *
 * @param {string} dateString The date string to format.
 *
 * @return {string} The formatted date string.
 */
export const formatDate = ( dateString ) => {
	return new Date( dateString ).toLocaleString( 'en-US', {
		month: 'short',
		day: 'numeric',
		year: 'numeric',
		hour: 'numeric',
		minute: '2-digit',
		hour12: true,
	} );
};

export {
	getInitials,
	isURL,
	isValidUrl,
	debounce,
};
