import { createRoot } from 'react-dom/client';
import OnboardingScreen from './page';

// Render to the target element.
const target = document.getElementById( 'onelogs-site-selection-modal' );
if ( target ) {
	const root = createRoot( target );
	root.render( <OnboardingScreen /> );
}
