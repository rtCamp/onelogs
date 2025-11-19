<?php
/**
 * Class Constants -- this is to define plugin constants.
 *
 * @package OneLogs
 */

namespace OneLogs\Modules\Plugin_Configs;

/**
 * Class Constants
 */
class Constants {
	/**
	 * Child site api key.
	 *
	 * @var string
	 */
	public const ONELOGS_API_KEY = 'onelogs_child_site_api_key';

	/**
	 * Shared sites.
	 *
	 * @var string
	 */
	public const ONELOGS_SHARED_SITES = 'onelogs_shared_sites';

	/**
	 * Site type.
	 *
	 * @var string
	 */
	public const ONELOGS_SITE_TYPE = 'onelogs_site_type';

	/**
	 * Site type transient.
	 *
	 * @var string
	 */
	public const ONELOGS_SITE_TYPE_TRANSIENT = 'onelogs_site_type_transient';

	/**
	 * Governing site request origin url.
	 *
	 * @var string
	 */
	public const ONELOGS_GOVERNING_SITE_URL = 'onelogs_governing_site_url';
}
