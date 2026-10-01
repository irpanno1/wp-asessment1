<?php
/**
 * Country list access.
 *
 * Wraps the shipped `inc/data/countries.php` table so PHP and the admin select
 * read one source. Memoized because the identity shortcode and the localizer
 * both ask for it on the same request.
 *
 * @package SureCookie\Inc\Modules\PrivacyPolicy
 * @since 1.5.0
 */

namespace SureCookie\Inc\Modules\PrivacyPolicy;

defined( 'ABSPATH' ) || exit;

/**
 * Countries
 *
 * @since 1.5.0
 */
final class Countries {
	/**
	 * Memoized code => name map.
	 *
	 * @var array<string, string>|null
	 */
	private static ?array $countries = null;

	/**
	 * All known countries, keyed by ISO 3166-1 alpha-2 code.
	 *
	 * @since 1.5.0
	 * @return array<string, string>
	 */
	public static function all(): array {
		if ( self::$countries !== null ) {
			return self::$countries;
		}

		$file = SURECOOKIE_DIR . 'inc/data/countries.php';

		if ( ! file_exists( $file ) ) {
			self::$countries = [];
			return self::$countries;
		}

		$countries = require $file;

		self::$countries = is_array( $countries ) ? $countries : [];

		return self::$countries;
	}

	/**
	 * English name for a country code, or an empty string when unknown.
	 *
	 * @since 1.5.0
	 * @param string $code ISO 3166-1 alpha-2 code.
	 * @return string
	 */
	public static function name( string $code ): string {
		return self::all()[ strtoupper( $code ) ] ?? '';
	}

	/**
	 * The list shaped for a force-ui select.
	 *
	 * @since 1.5.0
	 * @return array<int, array{value: string, label: string}>
	 */
	public static function options(): array {
		$options = [];

		foreach ( self::all() as $code => $name ) {
			$options[] = [
				'value' => $code,
				'label' => $name,
			];
		}

		return $options;
	}
}
