/**
 * WordPress dependencies
 */
import { useState, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { __ } from '@wordpress/i18n';
import { Snackbar } from '@wordpress/components';

/**
 * Internal dependencies
 */
import SiteTable from '../../components/SiteTable';
import SiteModal from '../../components/SiteModal';
import SiteSettings from '../../components/SiteSettings';
import { API_NAMESPACE, NONCE } from '../../js/constants';

/**
 * Settings page component for OneLogs plugin.
 *
 * @return {JSX.Element} Rendered component.
 */
const OneLogsSettingsPage = () => {
	const [ siteType, setSiteType ] = useState( '' );
	const [ showModal, setShowModal ] = useState( false );
	const [ editingIndex, setEditingIndex ] = useState( null );
	const [ sites, setSites ] = useState( [] );
	const [ formData, setFormData ] = useState( { name: '', url: '', api_key: '' } );
	const [ notice, setNotice ] = useState( {
		type: 'success',
		message: '',
	} );

	useEffect( () => {
		const fetchData = async () => {
			try {
				const [ siteTypeRes, sitesRes ] = await Promise.all( [
					fetch( `${ API_NAMESPACE }/site-type`, {
						headers: { 'Content-Type': 'application/json', 'X-WP-NONCE': NONCE },
					} ),
					fetch( `${ API_NAMESPACE }/shared-sites`, {
						headers: { 'Content-Type': 'application/json', 'X-WP-NONCE': NONCE },
					} ),
				] );

				const siteTypeData = await siteTypeRes.json();
				const sitesData = await sitesRes.json();

				if ( siteTypeData?.site_type ) {
					setSiteType( siteTypeData?.site_type );
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

	const handleFormSubmit = async () => {
		const updated = editingIndex !== null
			? sites.map( ( item, i ) => ( i === editingIndex ? formData : item ) )
			: [ ...sites, formData ];

		try {
			const response = await fetch( `${ API_NAMESPACE }/shared-sites`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-NONCE': NONCE,
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

		setFormData( { name: '', url: '', api_key: '' } );
		setShowModal( false );
		setEditingIndex( null );
	};

	const handleDelete = async ( index ) => {
		const updated = sites.filter( ( _, i ) => i !== index );

		try {
			const response = await fetch( `${ API_NAMESPACE }/shared-sites`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-NONCE': NONCE,
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
				<Snackbar
					status={ notice?.type ?? 'success' }
					isDismissible={ true }
					onRemove={ () => setNotice( null ) }
					className={ notice?.type === 'error' ? 'onelogs-error-notice' : 'onelogs-success-notice' }
				>
					{ notice?.message }
				</Snackbar>
			}

			{
				siteType === 'brand-site' && (
					<SiteSettings />
				)
			}

			{ siteType === 'governing-site' && (
				<SiteTable
					sites={ sites }
					onEdit={ setEditingIndex }
					onDelete={ handleDelete }
					setFormData={ setFormData }
					setShowModal={ setShowModal }
					setSites={ setSites }
					setNotice={ setNotice }
				/>
			) }

			{ showModal && (
				<SiteModal
					formData={ formData }
					setFormData={ setFormData }
					onSubmit={ handleFormSubmit }
					onClose={ () => {
						setShowModal( false );
						setEditingIndex( null );
						setFormData( { name: '', url: '', api_key: '' } );
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
