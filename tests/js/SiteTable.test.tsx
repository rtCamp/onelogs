/**
 * External dependencies
 */
import { fireEvent, render, screen, within } from '@testing-library/react';

/**
 * Internal dependencies
 */
import SiteTable from '@/components/SiteTable';
import type { BrandSite } from '@/admin/settings/page';

const sites: BrandSite[] = [
	{
		id: 'one',
		name: 'Brand One',
		url: 'https://brand-one.example.com/',
	},
];

const renderTable = ( props: Partial< Parameters< typeof SiteTable >[ 0 ] > ) =>
	render(
		<SiteTable
			sites={ [] }
			onEdit={ jest.fn() }
			onDelete={ jest.fn() }
			setFormData={ jest.fn() }
			setShowModal={ jest.fn() }
			{ ...props }
		/>
	);

describe( 'SiteTable', () => {
	it( 'renders an empty state when there are no brand sites', () => {
		renderTable( {} );

		expect(
			screen.getByText( 'No Brand Sites found.' )
		).toBeInTheDocument();
	} );

	it( 'opens the add modal when the add button is clicked', () => {
		const setShowModal = jest.fn();

		renderTable( { setShowModal } );

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Add Brand Site' } )
		);

		expect( setShowModal ).toHaveBeenCalledWith( true );
	} );

	it( 'masks the api key, which never reaches the browser (QA TC-047)', () => {
		renderTable( {
			// Even if a key were passed, it isn't shown.
			sites: [ { ...sites[ 0 ]!, api_key: '1234567890abcdef' } ],
		} );

		expect( screen.getByText( '••••••••' ) ).toBeInTheDocument();
		expect( screen.queryByText( /1234567890/ ) ).not.toBeInTheDocument();
	} );

	it( 'passes the selected site to edit handlers with an empty key field', () => {
		const onEdit = jest.fn();
		const setFormData = jest.fn();
		const setShowModal = jest.fn();

		renderTable( { sites, onEdit, setFormData, setShowModal } );

		fireEvent.click( screen.getByRole( 'button', { name: 'Edit' } ) );

		// The empty key keeps the stored one when saved.
		expect( setFormData ).toHaveBeenCalledWith( {
			...sites[ 0 ],
			api_key: '',
		} );
		expect( onEdit ).toHaveBeenCalledWith( 0 );
		expect( setShowModal ).toHaveBeenCalledWith( true );
	} );

	it( 'confirms deletion before calling onDelete', () => {
		const onDelete = jest.fn();

		renderTable( { sites, onDelete } );

		fireEvent.click( screen.getByRole( 'button', { name: 'Delete' } ) );

		const dialog = screen.getByRole( 'dialog', {
			name: 'Delete Brand Site',
		} );
		expect(
			within( dialog ).getByText(
				'Are you sure you want to delete this Brand Site? This action cannot be undone.'
			)
		).toBeInTheDocument();

		fireEvent.click(
			within( dialog ).getByRole( 'button', { name: 'Delete' } )
		);

		expect( onDelete ).toHaveBeenCalledWith( 0 );
	} );

	it( 'lets the user cancel deletion', () => {
		const onDelete = jest.fn();

		renderTable( { sites, onDelete } );

		fireEvent.click( screen.getByRole( 'button', { name: 'Delete' } ) );
		fireEvent.click( screen.getByRole( 'button', { name: 'Cancel' } ) );

		expect( onDelete ).not.toHaveBeenCalled();
		expect(
			screen.queryByRole( 'dialog', { name: 'Delete Brand Site' } )
		).not.toBeInTheDocument();
	} );
} );
