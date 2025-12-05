import React from 'react';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

interface PaginationProps {
	currentPage: number;
	totalPages: number;
	onPageChange: ( page: number ) => void;
}

export const Pagination: React.FC<PaginationProps> = ( {
	currentPage,
	totalPages,
	onPageChange,
	totalLogs,
} ) => {
	return (
		<div style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '16px' } }>
			<div>
				{ totalLogs > 0 && (
					<div className="onelogs-total-count">
						{ __( 'Total logs:', 'onelogs' ) } <strong>{ totalLogs.toLocaleString() }</strong>
					</div>
				) }
			</div>
			<div className="onelogs-pagination-controls onelogs-pagination-bottom">
				<Button
					variant="secondary"
					disabled={ currentPage <= 1 }
					onClick={ () => onPageChange( currentPage - 1 ) }
				>
					{ __( 'Previous', 'onelogs' ) }
				</Button>

				<Button
					variant="secondary"
					disabled={ currentPage >= totalPages }
					onClick={ () => onPageChange( currentPage + 1 ) }
				>
					{ __( 'Next', 'onelogs' ) }
				</Button>
			</div>

			<div><span>
				{ __( 'Page', 'onelogs' ) } { currentPage } { __( 'of', 'onelogs' ) } { totalPages }
			</span></div>
		</div>
	);
};
