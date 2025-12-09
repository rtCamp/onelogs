import { createRoot } from 'react-dom/client';
import OnboardingScreen, { type SiteType } from './page';

interface OneLogsSettings {
	restNonce: string;
	site_type: SiteType | '';
	settingsLink: string;
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
