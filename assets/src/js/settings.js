import { createRoot, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Snackbar } from '@wordpress/components';
import SiteTable from './components/SiteTable';
import SiteModal from './components/SiteModal';
import SiteSettings from './components/SiteSettings';

const API_NAMESPACE = OneLogsSettings.restUrl + '/onelogs/v1';
const NONCE = OneLogsSettings.restNonce;
const API_KEY = OneLogsSettings.apiKey;

const OneLogsSettingsPage = () => {
	const [ siteType, setSiteType ] = useState( '' );
	const [ showModal, setShowModal ] = useState( false );
	const [ editingIndex, setEditingIndex ] = useState( null );
	const [ sites, setSites ] = useState( [] );
	const [ formData, setFormData ] = useState( { siteName: '', siteUrl: '', apiKey: '' } );
	const [ notice, setNotice ] = useState( {
		type: 'success',
		message: '',
	} );

	useEffect( () => {
		const token = (
			NONCE
		);

		const fetchData = async () => {
			try {
				const [ siteTypeRes, sitesRes ] = await Promise.all( [
					fetch( `${ API_NAMESPACE }/site-type`, {
						headers: {
							'Content-Type': 'application/json',
							'X-WP-NONCE': token,
							'X-OneLogs-Token': API_KEY,
						},
					} ),
					fetch( `${ API_NAMESPACE }/shared-sites`, {
						headers: {
							'Content-Type': 'application/json',
							'X-WP-NONCE': token,
							'X-OneLogs-Token': API_KEY,
						},
					} ),
				] );

				const siteTypeData = await siteTypeRes.json();
				const sitesData = await sitesRes.json();

				if ( siteTypeData?.site_type ) {
					setSiteType( siteTypeData.site_type );
				}
				if ( Array.isArray( sitesData?.shared_sites ) ) {
					setSites( sitesData?.shared_sites );
				}
			} catch {
				setNotice( {
					type: 'error',
					message: __( 'Error fetching site type or Brand sites.', 'onelogs' ),
				} );
			}
		};

		fetchData();
	}, [] );

	useEffect( () => {
		if ( siteType === 'governing-site' && sites.length > 0 ) {
			document.body.classList.remove( 'onelogs-missing-brand-sites' );
		}
	}, [ sites, siteType ] );

	useEffect( () => {
		// Add a link element to head /wp-admin/load-styles.php?c=0&dir=ltr&load%5Bchunk_0%5D=wp-components&ver=6.8.3
		const link = document.createElement( 'link' );
		link.rel = 'stylesheet';
		link.href = '/wp-admin/load-styles.php?c=0&dir=ltr&load%5Bchunk_0%5D=wp-components&ver=6.8.3';
		document.head.appendChild( link );

		return () => {
			document.head.removeChild( link );
		};
	}, [] );

	const handleFormSubmit = async () => {
		const updated = editingIndex !== null
			? sites.map( ( item, i ) => (
				i === editingIndex ? formData : item
			) )
			: [ ...sites, formData ];

		const token = (
			NONCE
		);
		try {
			const response = await fetch( `${ API_NAMESPACE }/shared-sites`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-NONCE': token,
					'X-OneLogs-Token': API_KEY,
				},
				body: JSON.stringify( { sites_data: updated } ),
			} );
			if ( ! response.ok ) {
				console.error( 'Error saving Brand site:', response.statusText ); // eslint-disable-line no-console
				return response;
			}

			if ( sites.length === 0 ) {
				window.location.reload();
			}

			setSites( updated );
			setNotice( {
				type: 'success',
				message: __( 'Brand Site saved successfully.', 'onelogs' ),
			} );
		} catch {
			setNotice( {
				type: 'error',
				message: __( 'Error saving Brand site. Please try again later.', 'onelogs' ),
			} );
		}

		setFormData( { siteName: '', siteUrl: '', apiKey: '' } );
		setShowModal( false );
		setEditingIndex( null );
	};

	const handleDelete = async ( index ) => {
		const updated = sites.filter( ( _, i ) => i !== index );
		const token = (
			NONCE
		);

		try {
			const response = await fetch( `${ API_NAMESPACE }/shared-sites`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-NONCE': token,
					'X-OneLogs-Token': API_KEY,
				},
				body: JSON.stringify( { sites_data: updated } ),
			} );
			if ( ! response.ok ) {
				setNotice( {
					type: 'error',
					message: __( 'Failed to delete Brand site. Please try again.', 'onelogs' ),
				} );
				return;
			}
			setNotice( {
				type: 'success',
				message: __( 'Brand Site deleted successfully.', 'onelogs' ),
			} );
			setSites( updated );
			if ( updated.length === 0 ) {
				window.location.reload();
			} else {
				document.body.classList.remove( 'onelogs-missing-brand-sites' );
			}
		} catch {
			setNotice( {
				type: 'error',
				message: __( 'Error deleting Brand site. Please try again later.', 'onelogs' ),
			} );
		}
	};

	return (
		<>
			{ notice?.message?.length > 0 &&
				<Snackbar status={ notice?.type ?? 'success' } isDismissible={ true } onRemove={ () => setNotice( null ) }
					className={ notice?.type === 'error' ? 'onelogs-error-notice' : 'onelogs-success-notice' }>
					{ notice?.message }
				</Snackbar>
			}

			{ siteType === 'brand-site' && (
				<SiteSettings />
			) }

			{ siteType === 'governing-site' && (
				<SiteTable sites={ sites } onEdit={ setEditingIndex } onDelete={ handleDelete }
					setFormData={ setFormData } setShowModal={ setShowModal } />
			) }

			{ showModal && (
				<SiteModal
					formData={ formData }
					setFormData={ setFormData }
					onSubmit={ handleFormSubmit }
					onClose={ () => {
						setShowModal( false );
						setEditingIndex( null );
						setFormData( { siteName: '', siteUrl: '', apiKey: '' } );
					} }
					editing={ editingIndex !== null }
					originalData={ sites[ editingIndex ] }
				/>
			) }
		</>
	);
};

// Render to Gutenberg admin page with ID: onelogs-settings-page
const target = document.getElementById( 'onelogs-settings-page' );
if ( target ) {
	const root = createRoot( target );
	root.render( <OneLogsSettingsPage /> );
}

