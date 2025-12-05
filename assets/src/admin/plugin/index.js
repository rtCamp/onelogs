/**
 * WordPress dependencies
 */
import { useState, useEffect, createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Card, CardHeader, CardBody, Notice, Button, SelectControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { API_NAMESPACE, NONCE, API_KEY, SETTINGS_LINK } from '../../js/constants';

/**
 * SiteTypeSelector component for selecting site type.
 *
 * @param {Object}   props             - Component properties.
 * @param {string}   props.value       - Current selected site type.
 * @param {Function} props.setSiteType - Function to update the selected site type.
 * @return {JSX.Element} Rendered component.
 */
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

/**
 * Site type selection component for OneLogs setup.
 *
 * @return {JSX.Element} Rendered component.
 */
const OneLogsSiteTypeSelection = () => {
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
				window.location.href = SETTINGS_LINK;
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
		<>
			<Card>
				<>
					{ notice?.message?.length > 0 &&
					<Notice
						status={ notice?.type ?? 'success' }
						isDismissible={ true }
						onRemove={ () => setNotice( null ) }
					>
						{ notice?.message }
					</Notice>
					}
				</>
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
		</>
	);
};

// Render to Gutenberg admin page with ID: onelogs-site-selection-modal
const target = document.getElementById( 'onelogs-site-selection-modal' );
if ( target ) {
	const root = createRoot( target );
	root.render( <OneLogsSiteTypeSelection /> );
}
