/**
 * External dependencies
 */
import { useState } from 'react';

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardHeader,
	CardBody,
	Notice,
	Button,
	SelectControl,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import type { SiteType } from '@/types/global';

const BRAND_SITE = 'brand-site' as const;
const GOVERNING_SITE = 'governing-site' as const;

const SiteTypeSelector = ( {
	value,
	setSiteType,
}: {
	value: SiteType;
	setSiteType: ( v: SiteType ) => void;
} ) => (
	<SelectControl
		label={ __( 'Site Type', 'onelogs' ) }
		value={ value }
		help={ __(
			"Choose your site's primary purpose. This setting cannot be changed later and affects available features and configurations.",
			'onelogs'
		) }
		onChange={ ( v: SiteType ) => {
			setSiteType( v );
		} }
		__nextHasNoMarginBottom
		__next40pxDefaultSize
		options={ [
			{ label: __( 'Select…', 'onelogs' ), value: '' },
			{ label: __( 'Brand Site', 'onelogs' ), value: BRAND_SITE },
			{
				label: __( 'Governing site', 'onelogs' ),
				value: GOVERNING_SITE,
			},
		] }
	/>
);

const SiteTypeSettings = () => {
	const [ siteType, setSiteType ] = useState< SiteType >( '' );
	const [ error, setError ] = useState( '' );
	const [ isSaving, setIsSaving ] = useState( false );

	const handleSiteTypeChange = async ( value: SiteType ) => {
		setIsSaving( true );

		try {
			const settings = await apiFetch< {
				onelogs_site_type?: SiteType;
			} >( {
				path: '/wp/v2/settings',
				method: 'POST',
				data: { onelogs_site_type: value },
			} );

			if ( ! settings?.onelogs_site_type ) {
				throw new Error( 'No site type in response' );
			}

			// Reload so the settings screen and menu match the new site type.
			window.location.reload();
		} catch {
			setError( __( 'Error setting site type.', 'onelogs' ) );
		} finally {
			setIsSaving( false );
		}
	};

	return (
		<Card>
			{ !! error && (
				<Notice
					status="error"
					isDismissible
					onRemove={ () => setError( '' ) }
				>
					{ error }
				</Notice>
			) }

			<CardHeader>
				<h2>{ __( 'OneLogs', 'onelogs' ) }</h2>
			</CardHeader>

			<CardBody>
				<SiteTypeSelector
					value={ siteType }
					setSiteType={ setSiteType }
				/>
				<p>
					<strong>{ __( 'Governing site', 'onelogs' ) }</strong>:{ ' ' }
					{ __(
						'connects the brand sites and shows their activity logs.',
						'onelogs'
					) }
					<br />
					<strong>{ __( 'Brand site', 'onelogs' ) }</strong>:{ ' ' }
					{ __(
						'connects to a governing site, which can view its activity logs.',
						'onelogs'
					) }
				</p>
				<Button
					variant="primary"
					onClick={ () => handleSiteTypeChange( siteType ) }
					disabled={ isSaving || ! siteType }
					isBusy={ isSaving }
					style={ { marginTop: '1.5rem' } }
				>
					{ __( 'Select Current Site Type', 'onelogs' ) }
				</Button>
			</CardBody>
		</Card>
	);
};

export default SiteTypeSettings;
