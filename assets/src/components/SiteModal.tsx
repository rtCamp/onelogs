import { useState, useMemo } from 'react';
import {
	Modal,
	TextControl,
	TextareaControl,
	Button,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { isValidUrl } from '../js/utils';

interface BrandSite {
	name: string;
	url: string;
	api_key: string;
}

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
	originalData,
}: {
	formData: BrandSite;
	setFormData: ( data: BrandSite ) => void;
	onSubmit: () => Promise< Response >;
	onClose: () => void;
	editing: boolean;
	originalData?: BrandSite | undefined;
} ) => {
	const [ errors, setErrors ] = useState< ErrorsType >( {
		name: '',
		url: '',
		api_key: '',
		message: '',
	} );
	const [ showNotice, setShowNotice ] = useState( false );
	const [ isProcessing, setIsProcessing ] = useState( false );

	const handleSubmit = async (): Promise< void > => {
		// Validate inputs
		let siteUrlError = '';
		if ( ! formData.url.trim() ) {
			siteUrlError = __( 'Site URL is required.', 'onelogs' );
		} else if ( ! isValidUrl( formData.url ) ) {
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
			api_key: ! formData.api_key.trim()
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

		// Start processing
		setIsProcessing( true );
		setShowNotice( false );

		try {
			// Perform health-check
			const healthCheck = await fetch(
				`${ formData.url }/wp-json/onelogs/v1/health-check`,
				{
					method: 'GET',
					headers: {
						'Content-Type': 'application/json',
						'X-OneLogs-Token': formData.api_key,
					},
				}
			);

			const healthCheckData = await healthCheck.json();
			if ( ! healthCheckData.success ) {
				setErrors( {
					...newErrors,
					message: __(
						'Health check failed. Please ensure the site is accessible and the api key is correct.',
						'onelogs'
					),
				} );
				setShowNotice( true );
				setIsProcessing( false );
				return;
			}

			setShowNotice( false );
			const submitResponse: Response & {
				data?: { status?: number };
				message?: string;
			} = await onSubmit();

			if ( ! submitResponse?.ok ) {
				const errorData = await submitResponse?.json();
				setErrors( {
					...newErrors,
					message:
						errorData.message ||
						__(
							'An error occurred while saving the site. Please try again.',
							'onelogs'
						),
				} );
				setShowNotice( true );
			}
			if ( submitResponse?.data?.status === 400 ) {
				setErrors( {
					...newErrors,
					message:
						submitResponse?.message ||
						__(
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
			setIsProcessing( false );
			return;
		} finally {
			setIsProcessing( false );
		}
	};

	// Check if form data has changed from original data (only for editing mode)
	const hasChanges = useMemo( () => {
		if ( ! editing ) {
			return true;
		} // Always allow submission for new sites

		return (
			formData.name !== originalData?.name ||
			formData.url !== originalData?.url ||
			formData.api_key !== originalData?.api_key
		);
	}, [ editing, formData, originalData ] );

	// Button should be disabled if:
	// 1. Currently processing, OR
	// 2. Required fields are empty, OR
	// 3. In editing mode and no changes have been made
	const isButtonDisabled =
		isProcessing ||
		! formData.name ||
		! formData.url ||
		! formData.api_key ||
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
				label={ __( 'API Key*', 'onelogs' ) }
				value={ formData.api_key }
				onChange={ ( value ) =>
					setFormData( { ...formData, api_key: value } )
				}
				help={ __(
					'This is the API key that will be used to authenticate the site for OneLogs.',
					'onelogs'
				) }
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
