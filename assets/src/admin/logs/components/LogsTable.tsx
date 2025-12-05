import React from 'react';
import { __ } from '@wordpress/i18n';
import { LogEntry, SortableField, SortState, UserOption } from '../types';
import { formatDate } from '../utils';

interface LogsTableProps {
	logs: LogEntry[];
	users: UserOption[];
	currentSort: SortState;
	handleSort: ( field: SortableField ) => void;
}

export const LogsTable: React.FC<LogsTableProps> = ( {
	logs,
	users,
	currentSort,
	handleSort,
} ) => {
	const getUserDisplayName = ( userId: number ): { name: string; gravatar?: string } => {
		const user = users.find( ( u ) => u.id === userId );
		return {
			name: user ? user.display_name : `User ${ userId }`,
			gravatar: user?.gravatar_url,
		};
	};

	const getSortIndicator = ( field: SortableField ) => {
		if ( currentSort.field !== field ) {
			return null;
		}
		return currentSort.direction === 'asc' ? '↑' : '↓';
	};

	return (
		<div className="onelogs-logs-table-container">
			{ logs?.length > 0 ? (
				<table className="wp-list-table widefat fixed striped onelogs-logs-table">
					<thead>
						<tr>
							<th
								onClick={ () => handleSort( 'created' ) }
								className={ ` ${ currentSort.field === 'created' ? `sorted-${ currentSort.direction }` : '' }` }
							>
								{ __( 'Date', 'onelogs' ) }
							</th>
							<th
								onClick={ () => handleSort( 'site_name' ) }
								className={ ` ${ currentSort.field === 'site_name' ? `sorted-${ currentSort.direction }` : '' }` }
							>
								{ __( 'Site', 'onelogs' ) }
							</th>
							<th
								onClick={ () => handleSort( 'summary' ) }
								className={ ` ${ currentSort.field === 'summary' ? `sorted-${ currentSort.direction }` : '' }` }
							>
								{ __( 'Summary', 'onelogs' ) }
							</th>
							<th
								onClick={ () => handleSort( 'context' ) }
								className={ `onelogs-context-column  ${ currentSort.field === 'context' ? `sorted-${ currentSort.direction }` : '' }` }
							>
								{ __( 'Context', 'onelogs' ) }
							</th>
							<th
								onClick={ () => handleSort( 'action' ) }
								className={ `onelogs-action-column ${ currentSort.field === 'action' ? `sorted-${ currentSort.direction }` : '' }` }
							>
								{ __( 'Action', 'onelogs' ) }
							</th>
							<th
								onClick={ () => handleSort( 'subject' ) }
								className={ `onelogs-subject-column ${ currentSort.field === 'subject' ? `sorted-${ currentSort.direction }` : '' }` }
							>
								{ __( 'Subject', 'onelogs' ) }
							</th>
							<th
								onClick={ () => handleSort( 'user_id' ) }
								className={ `onelogs-column-user ${ currentSort.field === 'user_id' ? `sorted-${ currentSort.direction }` : '' }` }
							>
								{ __( 'User', 'onelogs' ) }
							</th>
							<th
								onClick={ () => handleSort( 'ip' ) }
								className={ `onelogs-ip-column ${ currentSort.field === 'ip' ? `sorted-${ currentSort.direction }` : '' }` }
							>
								{ __( 'IP', 'onelogs' ) }
							</th>
						</tr>
					</thead>
					<tbody>
						{ logs.length > 0 ? (
							logs.map( ( log ) => (
								<tr key={ log.ID } className="onelogs-log-row">
									<td className="onelogs-date-column">{ formatDate( log.created ) }</td>
									<td className="onelogs-site-column">
										{ log.is_remote ? (
											<a
												href={ log.site_url }
												target="_blank"
												rel="noopener noreferrer"
												className="onelogs-site-link"
												title={ __( 'Visit site', 'onelogs' ) }
											>
												<span className="onelogs-site-name">{ log.site_name }</span>
											</a>
										) : (
											<span
												className="onelogs-site-local">{ __( 'Governing Site', 'onelogs' ) }</span>
										) }
									</td>
									<td className="onelogs-summary-column">
										{ log.summary }
										{ log.object_data && (
											<div>
												{ log.object_data.edit_link && (
													<a href={ log.object_data.edit_link } target="_blank"
														rel="noreferrer">{ log.object_data.edit_link_text }</a> ) }
											</div>
										) }
									</td>
									<td className="onelogs-context-column">{ log.context }</td>
									<td className="onelogs-action-column" data-action={ log.action }>{ log.action }</td>
									<td className="onelogs-subject-column">{ log?.object_data?.title || 'N/A' }</td>
									<td className="onelogs-user-column">
										{ ( () => {
											const user = log.user;
											return (
												<div className="onelogs-user-cell">

													<img
														src={ user?.avatar_url ?? 'https://www.gravatar.com/avatar/?d=mp' }
														alt={ user?.display_name }
														className="onelogs-user-gravatar"
													/>

													<div>
														<div style={ {
															fontWeight: '600',
															color: '#1e293b',
														} }>{ user?.display_name ?? 'Guest User' }</div>
														<div style={ {
															fontSize: '12px',
															color: '#64748b',
														} }>{ log.user_role ? log.user_role : 'None' }</div>
													</div>
													{ /*<span className="onelogs-user-name">{ user?.display_name }</span>*/ }
												</div>
											);
										} )() }
									</td>
									<td className="onelogs-ip-column">{ log.ip }</td>
								</tr>
							) )
						) : (
							<tr>
								<td colSpan={ 8 } className="onelogs-no-results">
									{ __( 'No logs found matching the current filters.', 'onelogs' ) }
								</td>
							</tr>
						) }
					</tbody>
				</table>
			) : (
				<p>{ __( 'No logs found matching the current filters.', 'onelogs' ) }</p>
			) }
		</div>
	);
};
