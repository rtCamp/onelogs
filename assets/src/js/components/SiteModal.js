import { useMemo, useState } from '@wordpress/element';
import { Button, Modal, Notice, TextareaControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { isValidUrl } from '../utils';

const SiteModal = ( { formData, setFormData, onSubmit, onClose, editing, originalData = {} } ) => {
	const [ errors, setErrors ] = useState( {
		siteName: '',
		siteUrl: '',
		apiKey: '',
		message: '',
	} );
	const [ showNotice, setShowNotice ] = useState( false );
	const [ isProcessing, setIsProcessing ] = useState( false );

	// Check if form data has changed from original data (only for editing mode)
	const hasChanges = useMemo( () => {
		if ( ! editing ) {
			return true;
		} // Always allow submission for new sites

		return (
			formData.siteName !== originalData.siteName ||
			formData.siteUrl !== originalData.siteUrl ||
			formData.apiKey !== originalData.apiKey
		);
	}, [ editing, formData, originalData ] );

	const handleSubmit = async () => {
		// Validate inputs
		let siteUrlError = '';
		if ( ! formData.siteUrl.trim() ) {
			siteUrlError = __( 'Site URL is required.', 'onelogs' );
		} else if ( ! isValidUrl( formData.siteUrl ) ) {
			siteUrlError = __( 'Enter a valid URL (must start with http or https).', 'onelogs' );
		}

		const newErrors = {
			siteName: ! formData.siteName.trim() ? __( 'Site Name is required.', 'onelogs' ) : '',
			siteUrl: siteUrlError,
			apiKey: ! formData.apiKey.trim() ? __( 'API Key is required.', 'onelogs' ) : '',
			message: '',
		};

		// make sure site name is under 20 characters
		if ( formData.siteName.length > 20 ) {
			newErrors.siteName = __( 'Site Name must be under 20 characters.', 'onelogs' );
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
				`${ formData.siteUrl }/wp-json/onelogs/v1/health-check`,
				{
					method: 'GET',
					headers: {
						'Content-Type': 'application/json',
						'X-OneLogs-Token': formData.apiKey,
					},
				},
			);

			const healthCheckData = await healthCheck.json();
			if ( ! healthCheckData.success ) {
				setErrors( {
					...newErrors,
					message: __( 'Health check failed, please verify API key and make sure there\'s no governing site connected.', 'onelogs' ),
				} );
				setShowNotice( true );
				setIsProcessing( false );
				return;
			}

			setShowNotice( false );
			const submitResponse = await onSubmit();

			if ( ! submitResponse.ok ) {
				const errorData = await submitResponse.json();
				setErrors( {
					...newErrors,
					message: errorData.message || __( 'An error occurred while saving the site. Please try again.', 'onelogs' ),
				} );
				setShowNotice( true );
			}
			if ( submitResponse?.data?.status === 400 ) {
				setErrors( {
					...newErrors,
					message: submitResponse?.message || __( 'An error occurred while saving the site. Please try again.', 'onelogs' ),
				} );
				setShowNotice( true );
			}
		} catch ( error ) {
			setErrors( {
				...newErrors,
				message: __( 'An unexpected error occurred. Please try again.', 'onelogs' ),
			} );
			setShowNotice( true );
			setIsProcessing( false );
			return;
		}

		setIsProcessing( false );
	};

	// Button should be disabled if:
	// 1. Currently processing, OR
	// 2. Required fields are empty, OR
	// 3. In editing mode and no changes have been made
	const isButtonDisabled = isProcessing ||
		! formData.siteName ||
		! formData.siteUrl ||
		! formData.apiKey ||
		( editing && ! hasChanges );

	return (
		<Modal
			className="onelogs-site-modal"
			title={ editing ? __( 'Edit Brand Site', 'onelogs' ) : __( 'Add Brand Site', 'onelogs' ) }
			onRequestClose={ onClose }
			size="medium"
			shouldCloseOnClickOutside={ true }
		>
			{ showNotice && (
				<Notice
					status="error"
					isDismissible={ true }
					onRemove={ () => setShowNotice( false ) }
				>
					{ errors.message || errors.siteName || errors.siteUrl || errors.apiKey }
				</Notice>
			) }

			<TextControl
				label={ __( 'Site Name*', 'onelogs' ) }
				value={ formData.siteName }
				onChange={ ( value ) => setFormData( { ...formData, siteName: value } ) }
				error={ errors.siteName }
				help={ __( 'This is the name of the site that will be registered.', 'onelogs' ) }
			/>
			<TextControl
				label={ __( 'Site URL*', 'onelogs' ) }
				value={ formData.siteUrl }
				onChange={ ( value ) => setFormData( { ...formData, siteUrl: value } ) }
				error={ errors.siteUrl }
				help={ __( 'It must start with http or https and end with /, like: https://onelogs.com/', 'onelogs' ) }
			/>
			<TextareaControl
				label={ __( 'API Key*', 'onelogs' ) }
				value={ formData.apiKey }
				onChange={ ( value ) => setFormData( { ...formData, apiKey: value } ) }
				error={ errors.apiKey }
				help={ __( 'This is the api key that will be used to authenticate the site for OneLogs.', 'onelogs' ) }
			/>

			<Button
				variant="primary"
				onClick={ handleSubmit }
				className={ isProcessing ? 'is-busy' : '' }
				disabled={ isButtonDisabled }
				style={ { marginTop: '12px' } }
			>
				{ (
					editing ? __( 'Update Site', 'onelogs' ) : __( 'Add Site', 'onelogs' )
				) }
			</Button>
		</Modal>
	);
};

export default SiteModal;
