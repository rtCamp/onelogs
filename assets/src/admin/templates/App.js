/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import TemplateModal from './components/TemplateModal';

/**
 * Registers the Template Library plugin.
 *
 * @return {void}
 */
registerPlugin( 'onelogs-template-library', {
	render: () => {
		if ( typeof createRoot !== 'function' ) {
			return null;
		}

		const className = 'onelogs-template-library';
		const modalID = 'onelogs-template-library-modal';

		if ( document.getElementById( modalID ) ) {
			return null;
		}

		const modalWrap = document.createElement( 'div' );
		const modal = Object.assign( modalWrap, { id: modalID, className } );
		document.body?.appendChild( modal );
		createRoot( modal ).render( <TemplateModal /> );

		// make document.title to Template Library.
		document.title = __( 'Template Library', 'onelogs' );
	},
} );
