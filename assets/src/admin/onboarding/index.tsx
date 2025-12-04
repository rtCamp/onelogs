import { createRoot } from 'react-dom/client';
import OnboardingScreen, { type SiteType } from './page';

interface OneLogsSettings {
	nonce: string;
	site_type: SiteType | '';
	setup_url: string;
}

declare global {
	interface Window {
		OneLogsSettings: OneLogsSettings;
	}
}

// Render to the target element.
const target = document.getElementById( 'onelogs-site-selection-modal' );
if ( target ) {
	const root = createRoot( target );
	root.render( <OnboardingScreen /> );
}
