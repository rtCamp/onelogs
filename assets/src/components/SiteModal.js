/**
 * WordPress dependencies
 */
import { useState, useMemo } from 'react';
import {
	Modal,
	TextControl,
	TextareaControl,
	Button,
	Notice,
	BaseControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { isValidUrl } from '../js/utils';

/**
 * Delete Confirmation Modal component.
 *
 * @param {Object}   props           - Component properties.
 * @param {Function} props.onConfirm - Function to call on confirm.
 * @param {Function} props.onCancel  - Function to call on cancel.
 * @return {JSX.Element} Rendered component.
 */
const DeleteConfirmationModal = ( { onConfirm, onCancel } ) => (
	<Modal
		title={ __( 'Remove Site Logo', 'onelogs' ) }
		onRequestClose={ onCancel }
		isDismissible={ false }
		className="onelogs-delete-confirmation-modal"
	>
		<p>{ __( 'Are you sure you want to remove this logo? This action cannot be undone.', 'onelogs' ) }</p>
		<div style={ { display: 'flex', justifyContent: 'flex-end', marginTop: '20px', gap: '16px' } }>
			<Button
				variant="secondary"
				onClick={ onCancel }
			>
				{ __( 'Cancel', 'onelogs' ) }
			</Button>
			<Button
				variant="primary"
				isDestructive
				onClick={ onConfirm }
			>
				{ __( 'Remove', 'onelogs' ) }
			</Button>
		</div>
	</Modal>
);

/**
 * Site Modal component for adding/editing a site.
 *
 * @param {Object}   props              - Component properties.
 * @param {Object}   props.formData     - Current form data.
 * @param {Function} props.setFormData  - Function to update form data.
 * @param {Function} props.onSubmit     - Function to call on form submission.
 * @param {Function} props.onClose      - Function to call on modal close.
 * @param {boolean}  props.editing      - Whether the modal is in editing mode.
 * @param {Object}   props.originalData - Original data for comparison when editing.
 * @return {JSX.Element} Rendered component.
 */
const SiteModal = ( { formData, setFormData, onSubmit, onClose, editing, originalData = {} } ) => {
	const [ errors, setErrors ] = useState( {
		name: '',
		url: '',
		api_key: '',
		message: '',
	} );
	const [ showNotice, setShowNotice ] = useState( false );
	const [ isProcessing, setIsProcessing ] = useState( false );
	const [ showDeleteConfirm, setShowDeleteConfirm ] = useState( false );

	const handleSubmit = async () => {
		// Validate inputs
		let siteUrlError = '';
		if ( ! formData.url.trim() ) {
			siteUrlError = __( 'Site URL is required.', 'onelogs' );
		} else if ( ! isValidUrl( formData.url ) ) {
			siteUrlError = __( 'Enter a valid URL (must start with http or https).', 'onelogs' );
		}

		const newErrors = {
			name: ! formData.name.trim() ? __( 'Site Name is required.', 'onelogs' ) : '',
			url: siteUrlError,
			api_key: ! formData.api_key.trim() ? __( 'API Key is required.', 'onelogs' ) : '',
			message: '',
		};

		// Make sure site name is under 20 characters
		if ( formData.name.length > 20 ) {
			newErrors.name = __( 'Site Name must be under 20 characters.', 'onelogs' );
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
				},
			);

			const healthCheckData = await healthCheck.json();
			if ( ! healthCheckData.success ) {
				setErrors( {
					...newErrors,
					message: __( 'Health check failed. Please ensure the site is accessible and the api key is correct.', 'onelogs' ),
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

	const handleLogoSelect = () => {
		// Create a media frame for single image selection
		const mediaFrame = wp.media( {
			title: __( 'Select Site Logo', 'onelogs' ),
			button: {
				text: __( 'Select Image', 'onelogs' ),
			},
			multiple: false, // Restrict to single image selection
			library: {
				type: [ 'image' ], // Only allow images
			},
		} );

		// When an image is selected, update the formData with the image data
		mediaFrame.on( 'select', () => {
			const attachment = mediaFrame.state().get( 'selection' ).first().toJSON();
			setFormData( {
				...formData,
				logo: attachment.url,
				logo_id: attachment.id, // Store the attachment ID for future reference
			} );
		} );

		// If logo_id is already set, pre-select that image in the media library
		if ( formData.logo_id ) {
			mediaFrame.on( 'open', function() {
				const selection = mediaFrame.state().get( 'selection' );
				const attachment = wp.media.attachment( formData.logo_id );

				// Fetch attachment details
				attachment.fetch();

				// Add to selection
				if ( selection && attachment ) {
					selection.add( [ attachment ] );
				}
			} );
		}

		// Open the media modal
		mediaFrame.open();
	};

	const handleLogoRemove = ( e ) => {
		e.preventDefault();
		e.stopPropagation();
		setShowDeleteConfirm( true );
	};

	const confirmLogoRemove = ( e ) => {
		e.preventDefault();
		e.stopPropagation();
		setFormData( {
			...formData,
			logo: '',
			logo_id: null,
		} );
		setShowDeleteConfirm( false );
	};

	const cancelLogoRemove = ( e ) => {
		e.preventDefault();
		e.stopPropagation();
		setShowDeleteConfirm( false );
	};

	const handleMainModalClose = () => {
		if ( ! showDeleteConfirm ) {
			onClose();
		}
	};

	const hasChanges = useMemo( () => {
		if ( ! editing ) {
			return true;
		} // Always allow submission for new sites

		return (
			formData?.name !== originalData?.name ||
			formData?.url !== originalData?.url ||
			formData?.api_key !== originalData?.api_key ||
			formData?.logo !== originalData?.logo
		);
	}, [ editing, formData, originalData ] );

	// Button should be disabled if:
	// 1. Currently processing, OR
	// 2. Required fields are empty, OR
	// 3. In editing mode and no changes have been made
	const isButtonDisabled = isProcessing ||
		! formData.name ||
		! formData.url ||
		! formData.api_key ||
		( editing && ! hasChanges );

	return (
		<>
			{ ! showDeleteConfirm && (
				<Modal
					title={ editing ? __( 'Edit Brand Site', 'onelogs' ) : __( 'Add Brand Site', 'onelogs' ) }
					onRequestClose={ handleMainModalClose }
					size="medium"
				>
					{ showNotice && (
						<Notice
							status="error"
							isDismissible={ true }
							onRemove={ () => setShowNotice( false ) }
						>
							{ errors.message || errors.name || errors.url || errors.api_key }
						</Notice>
					) }

					<TextControl
						label={ __( 'Site Name*', 'onelogs' ) }
						value={ formData.name }
						onChange={ ( value ) => setFormData( { ...formData, name: value } ) }
						error={ errors.name }
						help={ __( 'This is the name of the site that will be registered.', 'onelogs' ) }
					/>
					<TextControl
						label={ __( 'Site URL*', 'onelogs' ) }
						value={ formData.url }
						onChange={ ( value ) => setFormData( { ...formData, url: value } ) }
						error={ errors.url }
						help={ __( 'It must start with http or https and end with /, like: https://onelogs.com/', 'onelogs' ) }
					/>

					{ /* Logo Media Selection */ }
					<BaseControl
						id="site-logo"
						label={ __( 'Site Logo', 'onelogs' ) }
						help={ __( 'Select a logo for this brand site.', 'onelogs' ) }
					>
						<div style={ { marginTop: '8px' } }>
							{ formData.logo && (
								<div style={ {
									marginBottom: '12px',
									padding: '12px',
									border: '1px solid #ddd',
									borderRadius: '4px',
									backgroundColor: '#f9f9f9',
								} }>
									<img
										src={ formData.logo }
										alt={ __( 'Site Logo', 'onelogs' ) }
										style={ {
											maxWidth: '150px',
											maxHeight: '100px',
											display: 'block',
											marginBottom: '8px',
										} }
									/>
									<div style={ { display: 'flex', gap: '8px' } }>
										<Button
											variant="secondary"
											onClick={ handleLogoSelect }
											size="small"
										>
											{ __( 'Replace Logo', 'onelogs' ) }
										</Button>
										<Button
											variant="secondary"
											onClick={ handleLogoRemove }
											size="small"
											isDestructive={ true }
										>
											{ __( 'Remove Logo', 'onelogs' ) }
										</Button>
									</div>
								</div>
							) }

							{ ! formData.logo && (
								<Button
									variant="secondary"
									onClick={ handleLogoSelect }
								>
									{ __( 'Select Logo', 'onelogs' ) }
								</Button>
							) }
						</div>
					</BaseControl>

					<TextareaControl
						label={ __( 'API Key*', 'onelogs' ) }
						value={ formData.api_key }
						onChange={ ( value ) => setFormData( { ...formData, api_key: value } ) }
						error={ errors.api_key }
						help={ __( 'This is the api key that will be used to authenticate the site for onelogs.', 'onelogs' ) }
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
			) }

			{ showDeleteConfirm && (
				<DeleteConfirmationModal
					onConfirm={ confirmLogoRemove }
					onCancel={ cancelLogoRemove }
				/>
			) }
		</>
	);
};

export default SiteModal;
