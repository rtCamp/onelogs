/**
 * External dependencies
 */
import { useMemo, useState } from 'react';

/**
 * WordPress dependencies
 */
import {
	Button,
	Modal,
	Notice,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { BrandSite } from '@/admin/settings/page';
import { isValidUrl, normalizeUrl } from '@/js/utils';

interface ErrorsType {
	name: string;
	url: string;
	api_key: string;
	message: string;
}

const SiteModal = ( {
	formData,
	setFormData,
	onSubmit,
	onClose,
	editing,
	sites,
	originalData,
}: {
	formData: BrandSite;
	setFormData: ( data: BrandSite ) => void;
	// Resolves true on success, or false or the server's error message on failure.
	onSubmit: () => Promise< boolean | string >;
	onClose: () => void;
	editing: boolean;
	sites: BrandSite[];
	originalData: BrandSite | undefined;
} ) => {
	const [ errors, setErrors ] = useState< ErrorsType >( {
		name: '',
		url: '',
		api_key: '',
		message: '',
	} );
	const [ showNotice, setShowNotice ] = useState( false );
	const [ isProcessing, setIsProcessing ] = useState( false );

	// A blank API key means the user hasn't typed one yet. When editing, it keeps the stored key.
	const typedApiKey = ( formData.api_key ?? '' ).trim();
	const needsApiKey =
		! editing ||
		normalizeUrl( formData.url ) !==
			normalizeUrl( originalData?.url ?? '' );

	// Check if form data has changed from original data (only for editing mode)
	const hasChanges = useMemo( () => {
		if ( ! editing ) {
			return true;
		} // Always allow submission for new sites

		return (
			formData.name !== originalData?.name ||
			formData.url !== originalData?.url ||
			typedApiKey !== ''
		);
	}, [ editing, formData, originalData, typedApiKey ] );

	const handleSubmit = async (): Promise< void > => {
		// Validate inputs
		let siteUrlError = '';
		if ( ! formData.url.trim() ) {
			siteUrlError = __( 'Site URL is required.', 'onelogs' );
		} else if ( ! isValidUrl( formData.url.trim() ) ) {
			siteUrlError = __(
				'Enter a valid URL (must start with http or https).',
				'onelogs'
			);
		}

		const newErrors: ErrorsType = {
			name: ! formData.name.trim()
				? __( 'Site Name is required.', 'onelogs' )
				: '',
			url: siteUrlError,
			api_key:
				needsApiKey && ! typedApiKey
					? __( 'API Key is required.', 'onelogs' )
					: '',
			message: '',
		};

		// Make sure site name is under 20 characters
		if ( formData.name.length > 20 ) {
			newErrors.name = __(
				'Site Name must be under 20 characters.',
				'onelogs'
			);
		}

		setErrors( newErrors );
		const hasErrors = Object.values( newErrors ).some( ( err ) => err );

		if ( hasErrors ) {
			setShowNotice( true );
			return;
		}

		// Different spellings of the same URL, like `HTTPS://Brand.test` and `https://brand.test/`, are the same site.
		const formUrl = normalizeUrl( formData.url );
		const isAlreadyExists = sites.some(
			( site ) =>
				normalizeUrl( site.url ) === formUrl &&
				// Allow keeping the original url in editing mode.
				! (
					editing &&
					normalizeUrl( originalData?.url ?? '' ) === formUrl
				)
		);

		if ( isAlreadyExists ) {
			setErrors( {
				...newErrors,
				message: __( 'Brand Site already exists.', 'onelogs' ),
			} );
			setShowNotice( true );
			return;
		}

		// Start processing
		setIsProcessing( true );
		setShowNotice( false );

		try {
			const submitResponse = await onSubmit();

			if ( submitResponse !== true ) {
				setErrors( {
					...newErrors,
					message:
						typeof submitResponse === 'string' && submitResponse
							? submitResponse
							: __(
									'An error occurred while saving the site. Please try again.',
									'onelogs'
							  ),
				} );
				setShowNotice( true );
			}
		} catch {
			setErrors( {
				...newErrors,
				message: __(
					'An unexpected error occurred. Please try again.',
					'onelogs'
				),
			} );
			setShowNotice( true );
		} finally {
			setIsProcessing( false );
		}
	};

	// Button should be disabled if:
	// 1. Currently processing, OR
	// 2. Required fields are empty, OR
	// 3. In editing mode and no changes have been made
	const isButtonDisabled =
		isProcessing ||
		! formData.name ||
		! formData.url ||
		( needsApiKey && ! typedApiKey ) ||
		( editing && ! hasChanges );

	return (
		<Modal
			title={
				editing
					? __( 'Edit Brand Site', 'onelogs' )
					: __( 'Add Brand Site', 'onelogs' )
			}
			onRequestClose={ onClose }
			size="medium"
			shouldCloseOnClickOutside
		>
			{ showNotice && (
				<Notice
					status="error"
					isDismissible
					onRemove={ () => setShowNotice( false ) }
				>
					{ errors.message ||
						errors.name ||
						errors.url ||
						errors.api_key }
				</Notice>
			) }

			<TextControl
				label={ __( 'Site Name*', 'onelogs' ) }
				value={ formData.name }
				onChange={ ( value ) =>
					setFormData( { ...formData, name: value } )
				}
				help={ __(
					'This is the name of the site that will be registered.',
					'onelogs'
				) }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
			<TextControl
				label={ __( 'Site URL*', 'onelogs' ) }
				value={ formData.url }
				onChange={ ( value ) =>
					setFormData( { ...formData, url: value } )
				}
				help={ __(
					'It must start with http or https and end with /, like: https://rtcamp.com/',
					'onelogs'
				) }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
			<TextareaControl
				label={
					needsApiKey
						? __( 'API Key*', 'onelogs' )
						: __( 'API Key', 'onelogs' )
				}
				value={ formData.api_key ?? '' }
				onChange={ ( value ) =>
					setFormData( { ...formData, api_key: value } )
				}
				help={
					needsApiKey
						? __(
								'This is the API key that will be used to authenticate the site for OneLogs.',
								'onelogs'
						  )
						: __(
								'Leave blank to keep the current key.',
								'onelogs'
						  )
				}
				__nextHasNoMarginBottom
			/>

			<Button
				variant="primary"
				onClick={ handleSubmit }
				className={ isProcessing ? 'is-busy' : '' }
				disabled={ isButtonDisabled }
				style={ { marginTop: '12px' } }
			>
				{ editing
					? __( 'Update Site', 'onelogs' )
					: __( 'Add Site', 'onelogs' ) }
			</Button>
		</Modal>
	);
};

export default SiteModal;
