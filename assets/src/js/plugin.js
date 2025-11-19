import { createRoot, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Card, CardBody, CardHeader, Notice, SelectControl } from '@wordpress/components';

const API_NAMESPACE = OneLogsSettings.restUrl + '/onelogs/v1';
const NONCE = OneLogsSettings.restNonce;
const API_KEY = OneLogsSettings.apiKey;

const SiteTypeSelector = ( { value, setSiteType } ) => (
	<SelectControl
		label={ __( 'Site Type', 'onelogs' ) }
		value={ value }
		help={ __( 'Choose your site\'s primary purpose. This setting cannot be changed later and affects available features and configurations.', 'onelogs' ) }
		onChange={ ( v ) => {
			setSiteType( v );
		} }
		options={ [
			{ label: __( 'Select…', 'onelogs' ), value: '' },
			{ label: __( 'Brand Site', 'onelogs' ), value: 'brand-site' },
			{ label: __( 'Governing Site', 'onelogs' ), value: 'governing-site' },
		] }
	/>
);

const OneLogsSettingsPage = () => {
	const [ siteType, setSiteType ] = useState( '' );
	const [ notice, setNotice ] = useState( null );
	const [ isSaving, setIsSaving ] = useState( false );

	useEffect( () => {
		const token = ( NONCE );

		const fetchData = async () => {
			try {
				const [ siteTypeRes ] = await Promise.all( [
					fetch( `${ API_NAMESPACE }/site-type`, {
						headers: {
							'Content-Type': 'application/json',
							'X-WP-NONCE': token,
							'X-OneLogs-Token': API_KEY,
						},
					} ),
				] );

				const siteTypeData = await siteTypeRes.json();

				if ( siteTypeData?.site_type ) {
					setSiteType( siteTypeData.site_type );
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

	const handleSiteTypeChange = async ( value ) => {
		setSiteType( value );
		const token = ( NONCE );
		setIsSaving( true );

		try {
			const response = await fetch( `${ API_NAMESPACE }/site-type`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-NONCE': token,
					'X-OneLogs-Token': API_KEY,
				},
				body: JSON.stringify( { site_type: value } ),
			} );

			if ( ! response.ok ) {
				setNotice( {
					type: 'error',
					message: __( 'Error setting site type.', 'onelogs' ),
				} );
				return;
			}

			const data = await response.json();
			if ( data?.site_type ) {
				setSiteType( data.site_type );

				// redirect user to setup page.
				window.location.href = OneLogsSettings.setupUrl;
			}
		} catch {
			setNotice( {
				type: 'error',
				message: __( 'Error setting site type.', 'onelogs' ),
			} );
		} finally {
			setIsSaving( false );
		}
	};

	return (
		<Card>
			{ notice?.message?.length > 0 &&
				<Notice
					status={ notice?.type ?? 'success' }
					isDismissible={ true }
					onRemove={ () => setNotice( null ) }
				>
					{ notice?.message }
				</Notice>
			}
			<CardHeader>
				<h2>{ __( 'OneLogs', 'onelogs' ) }</h2>
			</CardHeader>
			<CardBody>
				<SiteTypeSelector value={ siteType } setSiteType={ setSiteType } />
				<Button
					variant="primary"
					onClick={ () => handleSiteTypeChange( siteType ) }
					disabled={ isSaving || siteType.trim().length === 0 }
					style={ { marginTop: '1.5rem' } }
					className={ isSaving ? 'is-busy' : '' }
				>
					{ __( 'Select Current Site Type', 'onelogs' ) }
				</Button>
			</CardBody>
		</Card>
	);
};

// Accessibility utilities for modal
const useModalAccessibility = () => {
	useEffect( () => {
		const modal = document.getElementById( 'onelogs-site-selection-modal' );
		const mainContent = document.getElementById( 'wpwrap' );

		if ( modal && mainContent ) {
			// Set modal attributes for screen readers
			modal.setAttribute( 'role', 'dialog' );
			modal.setAttribute( 'aria-modal', 'true' );
			modal.setAttribute( 'aria-label', 'OneLogs Site Setup' );

			// Hide main content from assistive technology
			mainContent.setAttribute( 'aria-hidden', 'true' );

			// Focus management
			const firstFocusable = modal.querySelector( 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])' );
			if ( firstFocusable ) {
				setTimeout( () => firstFocusable.focus(), 100 );
			}

			// Handle escape key
			const handleEscape = ( e ) => {
				if ( e.key === 'Escape' ) {
					document.body.classList.remove( 'onelogs-site-selection-modal' );
				}
			};

			document.addEventListener( 'keydown', handleEscape );

			return () => {
				document.removeEventListener( 'keydown', handleEscape );

				// Cleanup when modal closes
				if ( mainContent ) {
					mainContent.removeAttribute( 'aria-hidden' );
				}
				if ( modal ) {
					modal.removeAttribute( 'role' );
					modal.removeAttribute( 'aria-modal' );
					modal.removeAttribute( 'aria-label' );
				}
			};
		}
	}, [] );
};

// Initialize modal accessibility when component mounts
const AccessibleOneLogsSettingsPage = () => {
	useModalAccessibility();
	return <OneLogsSettingsPage />;
};

// Render to Gutenberg admin page with ID: onelogs-site-selection-modal
const target = document.getElementById( 'onelogs-site-selection-modal' );
if ( target ) {
	const root = createRoot( target );
	root.render( <AccessibleOneLogsSettingsPage /> );
}
