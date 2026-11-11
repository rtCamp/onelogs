/**
 * External dependencies
 */
import { useState, useRef, useCallback } from 'react';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Notice, Snackbar } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import SiteTable from '@/components/SiteTable';
import SiteModal from '@/components/SiteModal';
import SiteSettings from '@/components/SiteSettings';
import { REST_NAMESPACE, SHARED_SITES, SITE_TYPE } from '@/js/constants';
import { getErrorMessage } from '@/js/utils';

export interface NoticeType {
	// Set by the settings page, so each notice gets its own Snackbar.
	id?: number;
	type: 'success' | 'error' | 'warning' | 'info';
	message: string;
}

export interface BrandSite {
	id?: string;
	name: string;
	url: string;
	// Only set while the admin types a key. The stored key never reaches the browser.
	api_key?: string;
}

export const defaultBrandSite: BrandSite = {
	name: '',
	url: '',
	api_key: '',
};

export type EditingIndex = number | null;

const SHARED_SITES_ENDPOINT = `/${ REST_NAMESPACE }/shared-sites`;

interface SharedSitesResponse {
	shared_sites?: BrandSite[];
}

interface DeleteSiteResponse extends SharedSitesResponse {
	governing_site_notified?: boolean;
	message?: string;
}

/**
 * Reloads the page, so the menu shows or hides the Logs page with the brand sites.
 */
const reloadPage = () => window.location.reload();

const SettingsPage = () => {
	const [ showModal, setShowModal ] = useState( false );
	const [ editingIndex, setEditingIndex ] = useState< EditingIndex >( null );
	const [ sites, setSites ] = useState< BrandSite[] >( SHARED_SITES );
	const [ formData, setFormData ] = useState< BrandSite >( defaultBrandSite );
	const [ notice, setNoticeState ] = useState< NoticeType | null >( null );
	const noticeId = useRef( 0 );

	// A new id remounts the Snackbar, which restarts its timer for the new notice.
	const setNotice = useCallback( ( next: NoticeType | null ) => {
		noticeId.current += 1;
		setNoticeState( next ? { ...next, id: noticeId.current } : null );
	}, [] );

	// Kept until dismissed, since each one asks the admin to finish disconnecting a brand site.
	const [ disconnectNotices, setDisconnectNotices ] = useState<
		NoticeType[]
	>( [] );

	const closeModal = () => {
		setShowModal( false );
		setEditingIndex( null );
		setFormData( defaultBrandSite );
	};

	const handleFormSubmit = async (): Promise< boolean | string > => {
		const updated: BrandSite[] =
			editingIndex !== null
				? sites.map( ( item, i ) =>
						i === editingIndex ? formData : item
				  )
				: [ ...sites, formData ];

		try {
			// The server health-checks new or changed sites before saving them.
			const data = await apiFetch< SharedSitesResponse >( {
				path: SHARED_SITES_ENDPOINT,
				method: 'POST',
				data: { sites_data: updated },
			} );

			if ( ! data?.shared_sites ) {
				return false;
			}

			if ( sites.length === 0 ) {
				reloadPage();
			}

			setSites( data.shared_sites );
			closeModal();
			setNotice( {
				type: 'success',
				message: __( 'Brand Site saved successfully.', 'onelogs' ),
			} );
			return true;
		} catch ( error ) {
			// The modal stays open with the server's message, so the admin can fix the input and retry.
			return getErrorMessage( error, '' ) || false;
		}
	};

	const handleDelete = async ( index: number | null ): Promise< void > => {
		const removedSite = index !== null ? sites[ index ] : undefined;

		if ( ! removedSite ) {
			return;
		}

		let response: DeleteSiteResponse;

		try {
			// The server also tells the brand site to disconnect, with the key it stored.
			response = await apiFetch< DeleteSiteResponse >( {
				path: `${ SHARED_SITES_ENDPOINT }/${ encodeURIComponent(
					removedSite.id ?? ''
				) }`,
				method: 'DELETE',
			} );
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message: getErrorMessage(
					error,
					__(
						'Failed to delete Brand site. Please try again.',
						'onelogs'
					)
				),
			} );
			return;
		}

		const remaining =
			response?.shared_sites ??
			sites.filter( ( site ) => site.id !== removedSite.id );

		setSites( remaining );

		if ( response?.governing_site_notified === false && response.message ) {
			const disconnectNotice: NoticeType = {
				type: 'warning',
				message: response.message,
			};

			setDisconnectNotices( ( notices ) => [
				...notices.filter(
					( item ) => item.message !== disconnectNotice.message
				),
				disconnectNotice,
			] );
		} else {
			setNotice( {
				type: 'success',
				message:
					response?.message ||
					__( 'Brand Site deleted successfully.', 'onelogs' ),
			} );
		}

		// Keep the page while a disconnect notice needs reading.
		if (
			remaining.length === 0 &&
			response?.governing_site_notified !== false
		) {
			reloadPage();
		}
	};

	return (
		<>
			{ !! notice && notice.message.length > 0 && (
				<Snackbar
					key={ notice.id }
					explicitDismiss={ false }
					onRemove={ () => setNotice( null ) }
					className={
						notice.type === 'error'
							? 'onelogs-error-notice'
							: 'onelogs-success-notice'
					}
				>
					{ notice.message }
				</Snackbar>
			) }

			{ disconnectNotices.map( ( disconnectNotice ) => (
				<Notice
					key={ disconnectNotice.message }
					status={ disconnectNotice.type }
					isDismissible
					onRemove={ () =>
						setDisconnectNotices( ( notices ) =>
							notices.filter(
								( item ) => item !== disconnectNotice
							)
						)
					}
				>
					{ disconnectNotice.message }
				</Notice>
			) ) }

			{ SITE_TYPE === 'brand-site' && <SiteSettings /> }

			{ SITE_TYPE === 'governing-site' && (
				<SiteTable
					sites={ sites }
					onEdit={ setEditingIndex }
					onDelete={ handleDelete }
					setFormData={ setFormData }
					setShowModal={ setShowModal }
				/>
			) }

			{ showModal && (
				<SiteModal
					formData={ formData }
					setFormData={ setFormData }
					onSubmit={ handleFormSubmit }
					onClose={ closeModal }
					editing={ editingIndex !== null }
					sites={ sites }
					originalData={
						editingIndex !== null
							? sites[ editingIndex ]
							: undefined
					}
				/>
			) }
		</>
	);
};

export default SettingsPage;
