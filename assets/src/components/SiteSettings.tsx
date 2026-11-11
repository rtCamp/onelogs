/**
 * External dependencies
 */
import { useEffect, useState, useCallback } from 'react';

/**
 * WordPress dependencies
 */
import {
	TextareaControl,
	Button,
	Card,
	Notice,
	Spinner,
	CardHeader,
	CardBody,
	TextControl,
	Modal,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import type { NoticeType } from '@/admin/settings/page';
import { API_KEY, REST_NAMESPACE } from '@/js/constants';

const SiteSettings = () => {
	const [ apiKey, setApiKey ] = useState( API_KEY );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ notice, setNotice ] = useState< NoticeType | null >( null );
	const [ governingSite, setGoverningSite ] = useState( '' );
	const [ showDisconnectionModal, setShowDisconnectionModal ] =
		useState( false );

	const regenerateApiKey = useCallback( async () => {
		try {
			const data = await apiFetch< { secret_key?: string } >( {
				path: `/${ REST_NAMESPACE }/secret-key`,
				method: 'POST',
			} );
			if ( data?.secret_key ) {
				setApiKey( data.secret_key );
				setNotice( {
					type: 'warning',
					message: __(
						'API key regenerated successfully. Please update your old key with this newly generated key to make sure plugin works properly.',
						'onelogs'
					),
				} );
			} else {
				setNotice( {
					type: 'error',
					message: __(
						'Failed to regenerate API key. Please try again later.',
						'onelogs'
					),
				} );
			}
		} catch {
			setNotice( {
				type: 'error',
				message: __(
					'Error regenerating API key. Please try again later.',
					'onelogs'
				),
			} );
		}
	}, [] );

	const fetchCurrentGoverningSite = useCallback( async () => {
		try {
			const data = await apiFetch< {
				onelogs_parent_site_url?: string;
			} >( { path: '/wp/v2/settings' } );
			setGoverningSite( data?.onelogs_parent_site_url || '' );
		} catch {
			setNotice( {
				type: 'error',
				message: __(
					'Failed to fetch governing site. Please try again later.',
					'onelogs'
				),
			} );
		}
	}, [] );

	const deleteGoverningSiteConnection = useCallback( async () => {
		try {
			const data = await apiFetch< {
				governing_site_notified: boolean;
				message: string;
			} >( {
				path: `/${ REST_NAMESPACE }/governing-site`,
				method: 'DELETE',
			} );
			setGoverningSite( '' );

			// Disconnected locally, but the governing site may still list this site.
			if ( data?.governing_site_notified === false ) {
				setNotice( { type: 'warning', message: data.message } );
				return;
			}

			setNotice( {
				type: 'success',
				message: __(
					'Governing site disconnected successfully.',
					'onelogs'
				),
			} );
		} catch {
			setNotice( {
				type: 'error',
				message: __(
					'Failed to disconnect governing site. Please try again later.',
					'onelogs'
				),
			} );
		} finally {
			setShowDisconnectionModal( false );
		}
	}, [] );

	const handleDisconnectGoverningSite = useCallback( async () => {
		setShowDisconnectionModal( true );
	}, [] );

	// Load once on mount.
	useEffect( () => {
		fetchCurrentGoverningSite().finally( () => setIsLoading( false ) );
	}, [ fetchCurrentGoverningSite ] );

	if ( isLoading ) {
		return <Spinner />;
	}

	return (
		<>
			{ notice && (
				<Notice
					status={ notice.type }
					isDismissible
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			<Card
				className="brand-site-settings"
				style={ { marginTop: '30px' } }
			>
				<CardHeader>
					<h2>{ __( 'API Key', 'onelogs' ) }</h2>
					<div>
						{ /* Copy to clipboard button */ }
						<Button
							variant="primary"
							onClick={ () => {
								(
									navigator.clipboard?.writeText( apiKey ) ??
									Promise.reject( '' )
								)
									.then( () => {
										setNotice( {
											type: 'success',
											message: __(
												'API key copied to clipboard.',
												'onelogs'
											),
										} );
									} )
									.catch( ( error ) => {
										setNotice( {
											type: 'error',
											message:
												__(
													'Failed to copy api key. Please try again.',
													'onelogs'
												) +
												' ' +
												error,
										} );
									} );
							} }
						>
							{ __( 'Copy API Key', 'onelogs' ) }
						</Button>
						{ /* Regenerate key button */ }
						<Button
							variant="secondary"
							onClick={ regenerateApiKey }
							style={ { marginLeft: '10px' } }
						>
							{ __( 'Regenerate API Key', 'onelogs' ) }
						</Button>
					</div>
				</CardHeader>
				<CardBody>
					<div>
						<TextareaControl
							label={ __( 'API Key', 'onelogs' ) }
							hideLabelFromVision
							value={ apiKey }
							// Not disabled, so the key can still be selected and copied by hand.
							readOnly
							help={ __(
								'This key is used for secure communication with the Governing site.',
								'onelogs'
							) }
							__nextHasNoMarginBottom
							onChange={ () => {} } // to avoid ts warning
						/>
					</div>
				</CardBody>
			</Card>
			<Card
				className="governing-site-connection"
				style={ { marginTop: '30px' } }
			>
				<CardHeader>
					<h2>{ __( 'Governing Site Connection', 'onelogs' ) }</h2>
					<Button
						variant="secondary"
						isDestructive
						onClick={ handleDisconnectGoverningSite }
						disabled={
							governingSite.trim().length === 0 || isLoading
						}
					>
						{ __( 'Disconnect Governing Site', 'onelogs' ) }
					</Button>
				</CardHeader>
				<CardBody>
					<TextControl
						label={ __( 'Governing Site URL', 'onelogs' ) }
						value={ governingSite }
						disabled
						help={ __(
							'This is the URL of the Governing site this Brand site is connected to.',
							'onelogs'
						) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						onChange={ () => {} } // to avoid ts warning
					/>
				</CardBody>
			</Card>

			{ showDisconnectionModal && (
				<Modal
					title={ __( 'Disconnect Governing Site', 'onelogs' ) }
					onRequestClose={ () => setShowDisconnectionModal( false ) }
					shouldCloseOnClickOutside
				>
					<p>
						{ __(
							'Are you sure you want to disconnect from the governing site? This action cannot be undone.',
							'onelogs'
						) }
					</p>
					<div
						style={ {
							display: 'flex',
							justifyContent: 'flex-end',
							marginTop: '20px',
							gap: '16px',
						} }
					>
						<Button
							variant="secondary"
							onClick={ () => setShowDisconnectionModal( false ) }
						>
							{ __( 'Cancel', 'onelogs' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={ deleteGoverningSiteConnection }
						>
							{ __( 'Disconnect', 'onelogs' ) }
						</Button>
					</div>
				</Modal>
			) }
		</>
	);
};

export default SiteSettings;
