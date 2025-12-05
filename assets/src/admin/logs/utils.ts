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
