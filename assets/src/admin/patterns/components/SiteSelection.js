/**
 * WordPress dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { Spinner, Notice, Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import useSitesManagement from '../../../hooks/useSitesManagement';
import { getInitials } from '../../../js/utils';
import { API_NAMESPACE, NONCE } from '../../../js/constants';
import { renderIcon } from '../../../components/Dashicons';

/**
 * Component to render the brand site selection with enhanced UX.
 *
 * @param {Object}   props                   - Component properties.
 * @param {Function} props.setIsSiteSelected - Function to set the site selection state.
 * @param {Array}    props.selectedPatterns  - Array of selected pattern names.
 * @param {Array}    props.basePatterns      - Array of base patterns for the current site.
 * @param {Object}   props.sitePatterns      - Object mapping site IDs to their patterns.
 *
 * @return {JSX.Element} JSX Element
 */
const SiteSelection = ( {
	setIsSiteSelected,
	selectedPatterns = [],
	basePatterns = [],
	sitePatterns = {},
} ) => {
	// common state for site info and health check results
	const {
		sitesHealthCheckResult,
		isLoading: isSitesLoading,
	} = useSitesManagement( { NONCE, API_NAMESPACE } );

	/**
	 * Get the current value of the brand_site meta field.
	 */
	const { BrandSite } = useSelect( ( select ) => {
		const meta = select( 'core/editor' ).getEditedPostAttribute( 'meta' );
		return {
			BrandSite: meta?.brand_site || [],
		};
	} );

	/**
	 * Dispatch the action to update the brand_site meta field.
	 */
	const { editPost } = useDispatch( 'core/editor' );

	const [ siteOptions, setSiteOptions ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	const onBrandSiteChange = ( siteId ) => {
		const newBrandSite = BrandSite.includes( siteId )
			? BrandSite.filter( ( site ) => site !== siteId )
			: [ ...BrandSite, siteId ];
		setIsSiteSelected( newBrandSite.length > 0 );
		editPost( { meta: { brand_site: newBrandSite } } );
	};

	const selectAllSites = () => {
		// Get IDs of sites that don't already have all patterns (not disabled)
		const selectableSiteIds = siteOptions
			.filter( ( site ) => {
				// skip if site is not reachable
				if ( ! isSiteReachable( site.id ) ) {
					return false;
				}

				// Skip if site has all patterns already
				if ( selectedPatterns.length > 0 && sitePatterns[ site.id ] ) {
					const sitePatternsArray = sitePatterns[ site.id ] || [];
					const presentPatterns = selectedPatterns.filter( ( patternName ) =>
						sitePatternsArray.some(
							( pattern ) => pattern.name === patternName.replace( /\//g, '' ),
						),
					);

					// If all patterns are present, exclude this site
					return ! (
						presentPatterns.length === selectedPatterns.length &&
						selectedPatterns.length > 0
					);
				}
				return true;
			} )
			.map( ( site ) => site.id );

		editPost( { meta: { brand_site: selectableSiteIds } } );
	};

	const deselectAllSites = () => {
		editPost( { meta: { brand_site: [] } } );
	};

	const retryFetch = () => {
		setIsLoading( true );
		setError( null );
		fetchSites();
	};

	const fetchSites = async () => {
		try {
			const response = await apiFetch( {
				path: `/onelogs/v1/configured-sites`,
			} );

			const data = response;
			setSiteOptions( data );
			setError( null );
		} catch ( fetchError ) {
			setError( {
				message: __(
					'Failed to load brand sites. Please check your connection and try again.',
					'onelogs',
				),
				details: fetchError.message,
			} );
		} finally {
			setIsLoading( false );
		}
	};

	useEffect( () => {
		fetchSites();
		// Reset error state
		setError( null );
		// Reset loading state
		setIsLoading( true );
		// Clear brand site selection on mount
		editPost( { meta: { brand_site: [] } } );
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	const totalCount = siteOptions.length;

	// helper function to check if site is reachable.
	const isSiteReachable = ( siteId ) => {
		return sitesHealthCheckResult?.[ siteId ] && sitesHealthCheckResult[ siteId ]?.success;
	};

	// Calculate the number of sites that don't have all patterns already
	const selectableSites = siteOptions.filter( ( site ) => {
		// skip if site is not reachable
		if ( ! isSiteReachable( site.id ) ) {
			return false;
		}

		if ( selectedPatterns.length > 0 && sitePatterns[ site.id ] ) {
			const sitePatternsArray = sitePatterns[ site.id ] || [];
			const presentPatterns = selectedPatterns.filter( ( patternName ) =>
				sitePatternsArray.some(
					( pattern ) => pattern.name === patternName.replace( /\//g, '' ),
				),
			);

			// If all patterns are present, site is not selectable
			return ! (
				presentPatterns.length === selectedPatterns.length &&
				selectedPatterns.length > 0
			);
		}
		return true;
	} );

	const selectableSiteCount = selectableSites.length;
	const selectedSelectableSiteCount = BrandSite.filter( ( siteId ) =>
		selectableSites.some( ( site ) => site.id === siteId ),
	).length;

	const selectedCount = BrandSite.length;

	if ( isLoading || isSitesLoading || sitesHealthCheckResult === undefined ) {
		return (
			<div className="onelogs-site-loading">
				<div className="onelogs-loading-content">
					<Spinner />
					<p>{ __( 'Loading brand sites…', 'onelogs' ) }</p>
				</div>
			</div>
		);
	}

	if ( error ) {
		return (
			<div className="onelogs-site-error">
				<Notice status="error" isDismissible={ false }>
					<p>{ error.message }</p>
					{ error.details && (
						<details className="onelogs-error-details">
							<summary>{ __( 'Technical details', 'onelogs' ) }</summary>
							<p>{ error.details }</p>
						</details>
					) }
				</Notice>
				<Button
					variant="secondary"
					onClick={ retryFetch }
					className="onelogs-retry-button"
				>
					{ __( 'Retry', 'onelogs' ) }
				</Button>
			</div>
		);
	}

	if ( siteOptions.length === 0 ) {
		return (
			<div className="onelogs-no-sites">
				<Notice status="warning" isDismissible={ false }>
					<p>{ __( 'No brand sites configured.', 'onelogs' ) }</p>
					<p>
						{ __(
							'Please configure brand sites first to apply patterns.',
							'onelogs',
						) }
					</p>
				</Notice>
			</div>
		);
	}

	return (
		<div className="onelogs-brand-site-selection">
			<div className="onelogs-selection-header">
				<div className="onelogs-selection-summary">
					<h4>{ __( 'Select Brand Sites', 'onelogs' ) }</h4>
					<span className="onelogs-selection-count">
						{ selectedCount > 0
							? sprintf(
								/* translators: %1$d: Number of selected sites, %2$d: Total number of sites. */
								__( '%1$d of %2$d selected', 'onelogs' ),
								selectedCount,
								selectableSiteCount,
							)
							: sprintf(
								/* translators: %1$d: Number of available sites, %2$d: Total number of sites. */
								__( '%1$d of %2$d sites available', 'onelogs' ),
								selectableSiteCount,
								totalCount,
							) }
					</span>
				</div>

				{ totalCount > 1 && (
					<div className="onelogs-bulk-actions">
						<Button
							variant="link"
							onClick={ selectAllSites }
							disabled={
								selectedSelectableSiteCount === selectableSiteCount ||
								selectableSiteCount === 0
							}
							className="onelogs-bulk-action"
						>
							{ __( 'Select All', 'onelogs' ) }
						</Button>
						<span className="onelogs-bulk-separator">|</span>
						<Button
							variant="link"
							onClick={ deselectAllSites }
							disabled={ selectedCount === 0 }
							className="onelogs-bulk-action"
						>
							{ __( 'Deselect All', 'onelogs' ) }
						</Button>
					</div>
				) }
			</div>

			{ /* Message explaining disabled sites if there are any */ }
			{ selectedPatterns.length > 0 && (
				<div className="onelogs-selection-hint">
					<p>
						<span className="dashicons dashicons-info"></span>
						{ totalCount !== selectableSiteCount
							? sprintf(
								/* translators: %1$d: number of non-selectable sites, %2$d: total sites */
								__(
									'%1$d of %2$d sites already have all selected patterns and are disabled.',
									'onelogs',
								),
								totalCount - selectableSiteCount,
								totalCount,
							)
							: __(
								'Sites that already have all selected patterns are disabled.',
								'onelogs',
							) }
					</p>
				</div>
			) }

			<div className="onelogs-sites-list onelogs-sites-grid">
				{ siteOptions.map( ( { id, name, url, logo } ) => {
					const isSelected = BrandSite?.includes( id );

					// Check if all selected patterns are already present on this site
					let hasAllPatterns = false;
					let isDisabled = false;

					if ( selectedPatterns.length > 0 && sitePatterns[ id ] ) {
						const sitePatternsArray = sitePatterns[ id ] || [];
						const presentPatterns = selectedPatterns.filter( ( patternName ) =>
							sitePatternsArray.some(
								( pattern ) => pattern.name === patternName.replace( /\//g, '' ),
							),
						);

						// If all selected patterns are already present, disable the site
						hasAllPatterns =
							presentPatterns.length === selectedPatterns.length &&
							selectedPatterns.length > 0;
						isDisabled = ( hasAllPatterns && ! isSelected ) || ! isSiteReachable( id );
					}

					return (
						<div
							key={ id }
							className={ `onelogs-site-item ${ isSelected ? 'onelogs-site-selected' : '' } ${ isDisabled ? 'onelogs-site-disabled' : '' }` }
							onClick={ () => ! isDisabled && onBrandSiteChange( id ) }
							onKeyDown={ ( e ) => {
								if ( ! isDisabled && ( e.code === 'Enter' || e.code === 'Space' ) ) {
									onBrandSiteChange( id );
								}
							} }
							tabIndex={ isDisabled ? -1 : 0 }
							role="checkbox"
							aria-checked={ isSelected }
							aria-disabled={ isDisabled }
						>
							<div className="onelogs-site-inner">
								{ isSelected && (
									<div className="onelogs-site-selected-indicator">
										{ renderIcon( { sitesHealthCheckResult, id } ) }
									</div>
								) }
								{ isDisabled && ! isSelected && (
									<div
										className="onelogs-site-disabled-indicator"
										title={ __(
											'This site already has all selected patterns',
											'onelogs',
										) }
									>
										{ renderIcon( { sitesHealthCheckResult, id } ) }
									</div>
								) }
								<div className="onelogs-site-logo">
									{ logo ? (
										<img src={ logo } alt={ name } />
									) : (
										<div className="onelogs-site-initials">
											{ name ? getInitials( name ) : '?' }
										</div>
									) }
								</div>
								<span className="onelogs-site-name">{ name }</span>
								{ url && <span className="onelogs-site-url">{ url }</span> }

								{ /* Pattern sync status */ }
								{ selectedPatterns.length > 0 && sitePatterns[ id ] && (
									<div className="onelogs-pattern-status">
										{ ( () => {
											const sitePatternsArray = sitePatterns[ id ] || [];
											// Remove forward slash from selected patterns names before comparison
											const presentPatterns = selectedPatterns.filter(
												( patternName ) =>
													sitePatternsArray.some(
														( pattern ) =>
															pattern.name === patternName.replace( /\//g, '' ),
													),
											);
											const presentCount = presentPatterns.length;
											const allPatternsPresent =
												presentCount === selectedPatterns.length &&
												selectedPatterns.length > 0;

											if ( presentCount === 0 ) {
												return (
													<span className="onelogs-onelogs-info">
														{ __( 'All patterns will be synced', 'onelogs' ) }
													</span>
												);
											}

											if ( allPatternsPresent ) {
												return (
													<span className="onelogs-onelogs-info onelogs-all-patterns-present">
														{ __(
															'All selected patterns are already present',
															'onelogs',
														) }
													</span>
												);
											}

											const toSyncPatterns = selectedPatterns.filter(
												( patternName ) =>
													! sitePatternsArray.some(
														( pattern ) =>
															pattern.name === patternName.replace( /\//g, '' ),
													),
											);

											const toSyncPatternsTitles = toSyncPatterns.map(
												( patternName ) =>
													basePatterns.find(
														( pattern ) => pattern.name === patternName,
													)?.title,
											);

											// Limit toSyncPatternsTitles to 5 items for display
											if ( toSyncPatternsTitles.length > 5 ) {
												toSyncPatternsTitles.length = 5;
												toSyncPatternsTitles.push( '…' );
											}

											return (
												<>
													<span
														className="onelogs-onelogs-info"
													>
														{ presentCount } { __( 'of', 'onelogs' ) }{ ' ' }
														{ selectedPatterns.length }{ ' ' }
														{ __(
															'selected patterns are already present',
															'onelogs',
														) }
													</span>
												</>
											);
										} )() }
									</div>
								) }
							</div>
						</div>
					);
				} ) }
			</div>
		</div>
	);
};

export default SiteSelection;
