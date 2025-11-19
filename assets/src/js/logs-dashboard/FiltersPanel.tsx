import React, { useEffect, useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Button, SelectControl, TextControl } from '@wordpress/components';
import { debounce } from 'lodash';
import { FilterOptions, UserOption } from './types';
import { fetchSiteType } from './apiService';

interface FiltersPanelProps {
	localSearch: string;
	setLocalSearch: ( value: string ) => void;
	filters: FilterOptions;
	handleFilterChange: ( key: keyof FilterOptions, value: any ) => void;
	connectors: string[];
	contexts: string[];
	users: UserOption[];
	sharedSites: any[];
	showSharedSitesLogs: boolean;
	setShowSharedSitesLogs: ( value: boolean ) => void;
	showAdvancedFilters: boolean;
	setShowAdvancedFilters: ( value: boolean ) => void;
	fetchLogsData: () => void;
	resetFilters: () => void;
	exportData: () => void;
	exportLoading: boolean;
}

export const FiltersPanel: React.FC<FiltersPanelProps> = ( {
	localSearch,
	setLocalSearch,
	filters,
	handleFilterChange,
	connectors,
	contexts,
	actions,
	users,
	sharedSites,
	showSharedSitesLogs,
	setShowSharedSitesLogs,
	showAdvancedFilters,
	setShowAdvancedFilters,
	fetchLogsData,
	resetFilters,
	exportData,
	exportLoading,
} ) => {
	const debouncedSearchRef = useRef<( ( searchValue: string | undefined ) => void ) | null>( null );

	const [ siteType, setSiteType ] = useState( false );
	const today = new Date().toISOString().split( 'T' )[ 0 ];

	const loadSiteType = async () => {
		try {
			const data = await fetchSiteType();
			setSiteType( data );
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.log( err );
		}
	};

	useEffect( () => {
		loadSiteType();
		const debouncedFn = debounce( ( searchValue: string | undefined ) => {
			handleFilterChange( 'search', searchValue );
		}, 500 );

		debouncedSearchRef.current = debouncedFn;

		return () => {
			if ( debouncedFn && typeof debouncedFn.cancel === 'function' ) {
				debouncedFn.cancel();
			}
		};
	}, [ handleFilterChange ] );

	return (
		<div>
			<div className="onelogs-advanced-filters-grid">
				{ siteType && siteType === 'governing-site' && (
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Site', 'onelogs' ) }
						value={ filters.site_url || '' }
						onChange={ ( value ) => handleFilterChange( 'site_url', value || undefined ) }
						options={ [
							{ label: __( 'Governing Site', 'onelogs' ), value: 'governing-site' },
							...sharedSites.map( ( site ) => ( {
								label: site.siteName || site.siteUrl,
								value: site.siteUrl,
							} ) ),
						] }
					/> ) }

				<div style={ { flex: 1 } }>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Context', 'onelogs' ) }
						value={ filters.context || '' }
						onChange={ ( value ) => handleFilterChange( 'context', value || undefined ) }
						options={ [
							{ label: __( 'All Contexts', 'onelogs' ), value: '' },
							...contexts.map( ( context ) => ( {
								label: context.charAt( 0 ).toUpperCase() + context.slice( 1 ),
								value: context,
							} ) ),
						] }
					/>
				</div>

				<div>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						style={ { flex: 1 } }
						label={ __( 'Action', 'onelogs' ) }
						value={ filters.action || '' }
						onChange={ ( value ) => handleFilterChange( 'action', value || undefined ) }
						options={ [
							{ label: __( 'All Actions', 'onelogs' ), value: '' },
							...actions.map( ( action ) => ( {
								label: action.charAt( 0 ).toUpperCase() + action.slice( 1 ),
								value: action,
							} ) ),
						] }
					/>
				</div>

				<div>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'User', 'onelogs' ) }
						value={ filters.user_id || '' }
						onChange={ ( value ) => handleFilterChange( 'user_id', value ? parseInt( value, 10 ) : undefined ) }
						options={ [
							{ label: __( 'All Users', 'onelogs' ), value: '' },
							...users.map( ( user ) => ( {
								label: user.display_name,
								value: user.id.toString(),
							} ) ),
						] }
					/>
				</div>

				<div>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Date From', 'onelogs' ) }
						type="date"
						value={ filters.date_from || '' }
						onChange={ ( value ) => handleFilterChange( 'date_from', value || undefined ) }
						max={ today }
					/>
				</div>

				<div>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Date To', 'onelogs' ) }
						type="date"
						value={ filters.date_to || '' }
						onChange={ ( value ) => handleFilterChange( 'date_to', value || undefined ) }
						min={ filters.date_from || undefined }
						max={ today }
					/>
				</div>
				<div style={ {
					gridColumn: 'auto / -1',
					width: '100%',
					flex: 1,
					display: 'flex',
					justifyContent: 'flex-end',
					alignItems: 'flex-end',
				} }>
					<Button
						variant="secondary"
						onClick={ () => resetFilters() }
					>
						{ __( 'Clear Filters', 'onepress-logs' ) }
					</Button>
				</div>
			</div>

			<div className={ 'onelogs-filters-panel-footer' }>
				<div style={ { width: '25%' } }>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Search', 'onelogs' ) }
						value={ localSearch }
						onChange={ ( value ) => {
							setLocalSearch( value || '' );
							if ( debouncedSearchRef.current ) {
								debouncedSearchRef.current( value || undefined );
							}
						} }
						placeholder={ __( 'Search by summary', 'onelogs' ) }
					/>
				</div>

				<div style={ { display: 'flex', alignItems: 'flex-end', marginBottom: '8px' } }>
					<Button
						variant="primary"
						onClick={ () => fetchLogsData() }
					>
						<span className="dashicons dashicons-update" style={ { marginRight: '4px' } }></span>
						{ __( 'Refresh', 'onepress-logs' ) }
					</Button>
					<Button
						isBusy={ exportLoading }
						disabled={ exportLoading }
						variant="secondary"
						onClick={ () => exportData() }
						style={ { marginLeft: '8px' } }
					>
						{ __( 'Export CSV', 'onepress-logs' ) }
					</Button>
				</div>
			</div>

		</div>
	);
};
