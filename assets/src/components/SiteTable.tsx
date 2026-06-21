import { useState } from 'react';
import {
	Button,
	Card,
	CardHeader,
	CardBody,
	Modal,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

interface BrandSite {
	name: string;
	url: string;
	api_key: string;
	is_editable?: boolean;
}

const SiteTable = ( {
	sites,
	onEdit,
	onDelete,
	setFormData,
	setShowModal,
}: {
	sites: BrandSite[];
	onEdit: ( index: number ) => void;
	onDelete: ( index: number | null ) => void;
	setFormData: ( data: BrandSite ) => void;
	setShowModal: ( show: boolean ) => void;
} ) => {
	const [ showDeleteModal, setShowDeleteModal ] = useState( false );
	const [ deleteIndex, setDeleteIndex ] = useState< number | null >( null );

	const handleDeleteClick = ( index: number ) => {
		setDeleteIndex( index );
		setShowDeleteModal( true );
	};

	const handleDeleteConfirm = () => {
		onDelete( deleteIndex );
		setShowDeleteModal( false );
		setDeleteIndex( null );
	};

	const handleDeleteCancel = () => {
		setShowDeleteModal( false );
		setDeleteIndex( null );
	};

	return (
		<Card style={ { marginTop: '30px' } }>
			<CardHeader>
				<h3>{ __( 'Brand Sites', 'onelogs' ) }</h3>
				<Button
					style={ { width: 'fit-content' } }
					variant="primary"
					onClick={ () => setShowModal( true ) }
				>
					{ __( 'Add Brand Site', 'onelogs' ) }
				</Button>
			</CardHeader>
			<CardBody>
				<table className="wp-list-table widefat fixed striped ">
					<thead>
						<tr>
							<th>{ __( 'Site Name', 'onelogs' ) }</th>
							<th>{ __( 'Site URL', 'onelogs' ) }</th>
							<th>{ __( 'API Key', 'onelogs' ) }</th>
							<th>{ __( 'Actions', 'onelogs' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ sites.length === 0 && (
							<tr>
								<td
									colSpan={ 4 }
									style={ { textAlign: 'center' } }
								>
									{ __( 'No Brand Sites found.', 'onelogs' ) }
								</td>
							</tr>
						) }
						{ sites?.map( ( site, index ) => (
							<tr key={ index }>
								<td>{ site?.name }</td>
								<td>{ site?.url }</td>
								<td>
									<code>
										{ site?.api_key?.substring( 0, 10 ) }...
									</code>
								</td>
								<td>
									<Button
										variant="secondary"
										onClick={ () => {
											setFormData( site );
											onEdit( index );
											setShowModal( true );
										} }
										disabled={ site?.is_editable === false }
										style={ { marginRight: '8px' } }
									>
										{ __( 'Edit', 'onelogs' ) }
									</Button>
									<Button
										variant="secondary"
										isDestructive
										onClick={ () =>
											handleDeleteClick( index )
										}
									>
										{ __( 'Delete', 'onelogs' ) }
									</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</CardBody>
			{ showDeleteModal && (
				<DeleteConfirmationModal
					onConfirm={ handleDeleteConfirm }
					onCancel={ handleDeleteCancel }
				/>
			) }
		</Card>
	);
};

const DeleteConfirmationModal = ( {
	onConfirm,
	onCancel,
}: {
	onConfirm: () => void;
	onCancel: () => void;
} ) => (
	<Modal
		title={ __( 'Delete Brand Site', 'onelogs' ) }
		onRequestClose={ onCancel }
		isDismissible
	>
		<p>
			{ __(
				'Are you sure you want to delete this Brand Site? This action cannot be undone.',
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
			<Button variant="secondary" onClick={ onCancel }>
				{ __( 'Cancel', 'onelogs' ) }
			</Button>
			<Button variant="primary" isDestructive onClick={ onConfirm }>
				{ __( 'Delete', 'onelogs' ) }
			</Button>
		</div>
	</Modal>
);

export default SiteTable;
