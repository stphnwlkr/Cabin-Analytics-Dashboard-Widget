<?php
/**
 * Plugin Name:       Cabin Analytics Dashboard Widget
 * Description:       WordPress-native dashboard widget for Cabin Analytics (Summary + larger sparkline OR Cabin-style stacked Views/Visitors chart).
 * Version:           1.1.0
 * Requires at least: 6.9
 * Requires PHP:      8.3
 * Author:            Stephen Walker
 * License:           GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

final class WP_Cabin_Dashboard_Widget {
	const OPT_API_KEY        = 'wp_cabin_api_key';
	const OPT_MODE           = 'wp_cabin_widget_mode';        // 'sparkline' | 'chart'
	const OPT_DEFAULT_RANGE  = 'wp_cabin_default_range';      // '7d' | '14d' | '30d'
	const NONCE_ACTION       = 'wp_cabin_widget';

	public static function init() : void {
		add_action( 'admin_menu', [ __CLASS__, 'add_settings_page' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );

		add_action( 'wp_dashboard_setup', [ __CLASS__, 'register_dashboard_widget' ] );

		add_action( 'admin_head', [ __CLASS__, 'admin_css' ] );
		add_action( 'admin_footer', [ __CLASS__, 'admin_js' ] );
	}

	/* -------------------------
	 * Settings
	 * ------------------------- */

	public static function add_settings_page() : void {
		add_options_page(
			'Cabin Analytics',
			'Cabin Analytics',
			'manage_options',
			'wp-cabin-analytics',
			[ __CLASS__, 'render_settings_page' ]
		);
	}

	public static function register_settings() : void {
		register_setting(
			'wp_cabin_settings',
			self::OPT_API_KEY,
			[
				'type'              => 'string',
				'sanitize_callback' => [ __CLASS__, 'sanitize_api_key' ],
				'default'           => '',
			]
		);

		register_setting(
			'wp_cabin_settings',
			self::OPT_MODE,
			[
				'type'              => 'string',
				'sanitize_callback' => [ __CLASS__, 'sanitize_mode' ],
				'default'           => 'sparkline',
			]
		);

		register_setting(
			'wp_cabin_settings',
			self::OPT_DEFAULT_RANGE,
			[
				'type'              => 'string',
				'sanitize_callback' => [ __CLASS__, 'sanitize_range' ],
				'default'           => '14d',
			]
		);

		add_settings_section(
			'wp_cabin_main',
			'Settings',
			function () {
				echo '<p>Enter your Cabin API key. The widget automatically uses this site’s current domain.</p>';
			},
			'wp-cabin-analytics'
		);

		add_settings_field(
			self::OPT_API_KEY,
			'Cabin API Key',
			[ __CLASS__, 'render_api_key_field' ],
			'wp-cabin-analytics',
			'wp_cabin_main'
		);

		add_settings_field(
			self::OPT_MODE,
			'Widget Display',
			[ __CLASS__, 'render_mode_field' ],
			'wp-cabin-analytics',
			'wp_cabin_main'
		);

		add_settings_field(
			self::OPT_DEFAULT_RANGE,
			'Default date range',
			[ __CLASS__, 'render_default_range_field' ],
			'wp-cabin-analytics',
			'wp_cabin_main'
		);
	}

	public static function sanitize_api_key( $value ) : string {
		$value = is_string( $value ) ? trim( $value ) : '';
		return preg_replace( '/\s+/', '', $value );
	}

	public static function sanitize_mode( $value ) : string {
		$value = is_string( $value ) ? sanitize_key( $value ) : 'sparkline';
		return in_array( $value, [ 'sparkline', 'chart' ], true ) ? $value : 'sparkline';
	}

	public static function sanitize_range( $value ) : string {
		$value = is_string( $value ) ? sanitize_key( $value ) : '14d';
		return in_array( $value, [ '7d', '14d', '30d' ], true ) ? $value : '14d';
	}

	public static function render_api_key_field() : void {
		$val = get_option( self::OPT_API_KEY, '' );
		echo '<input type="password" class="regular-text" name="' . esc_attr( self::OPT_API_KEY ) . '" value="' . esc_attr( $val ) . '" autocomplete="off" />';
		echo '<p class="description">Cabin API key is required.</p>';
	}

	public static function render_mode_field() : void {
		$mode = get_option( self::OPT_MODE, 'sparkline' );
		?>
		<fieldset>
			<label>
				<input type="radio" name="<?php echo esc_attr( self::OPT_MODE ); ?>" value="sparkline" <?php checked( $mode, 'sparkline' ); ?> />
				Summary + Sparkline (minimal)
			</label><br />
			<label>
				<input type="radio" name="<?php echo esc_attr( self::OPT_MODE ); ?>" value="chart" <?php checked( $mode, 'chart' ); ?> />
				Summary + Views/Visitors Chart (Cabin-style)
			</label>
			<p class="description">You can switch modes anytime—no data changes.</p>
		</fieldset>
		<?php
	}

	public static function render_default_range_field() : void {
		$val = (string) get_option( self::OPT_DEFAULT_RANGE, '14d' );
		?>
		<fieldset>
			<label>
				<input type="radio" name="<?php echo esc_attr( self::OPT_DEFAULT_RANGE ); ?>" value="7d" <?php checked( $val, '7d' ); ?> />
				7d
			</label><br />
			<label>
				<input type="radio" name="<?php echo esc_attr( self::OPT_DEFAULT_RANGE ); ?>" value="14d" <?php checked( $val, '14d' ); ?> />
				14d
			</label><br />
			<label>
				<input type="radio" name="<?php echo esc_attr( self::OPT_DEFAULT_RANGE ); ?>" value="30d" <?php checked( $val, '30d' ); ?> />
				30d
			</label>
			<p class="description">Used when no <code>cabin_range</code> is specified in the dashboard URL.</p>
		</fieldset>
		<?php
	}

	public static function render_settings_page() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.' ) );
		}

		$domain = self::get_site_domain();
		?>
		<div class="wrap">
			<h1>Cabin Analytics</h1>

			<div class="notice notice-info inline">
				<p><strong>Domain:</strong> <?php echo esc_html( $domain ?: '—' ); ?></p>
			</div>

			<form method="post" action="options.php">
				<?php
					settings_fields( 'wp_cabin_settings' );
					do_settings_sections( 'wp-cabin-analytics' );
					submit_button( 'Save Settings' );
				?>
			</form>
		</div>
		<?php
	}

	/* -------------------------
	 * Dashboard Widget
	 * ------------------------- */

	public static function register_dashboard_widget() : void {
		wp_add_dashboard_widget(
			'wp_cabin_dashboard_widget',
			'Cabin Analytics',
			[ __CLASS__, 'render_dashboard_widget' ]
		);
	}

	public static function render_dashboard_widget() : void {
		$api_key = (string) get_option( self::OPT_API_KEY, '' );
		$mode    = (string) get_option( self::OPT_MODE, 'sparkline' );
		$domain  = self::get_site_domain();

		if ( empty( $domain ) ) {
			echo '<p>Could not determine this site’s domain.</p>';
			return;
		}

		if ( empty( $api_key ) ) {
			echo '<p>Set your Cabin API key in <a href="' . esc_url( admin_url( 'options-general.php?page=wp-cabin-analytics' ) ) . '">Settings → Cabin Analytics</a>.</p>';
			return;
		}

		$default_range = (string) get_option( self::OPT_DEFAULT_RANGE, '14d' );
		$range = isset( $_GET['cabin_range'] ) ? sanitize_key( (string) $_GET['cabin_range'] ) : $default_range;
		if ( ! in_array( $range, [ '7d', '14d', '30d' ], true ) ) {
			$range = $default_range;
		}

		$force_refresh = isset( $_GET['cabin_refresh'] ) && '1' === (string) $_GET['cabin_refresh'];
		if ( $force_refresh && check_admin_referer( self::NONCE_ACTION ) ) {
			self::delete_cache( $domain, $range, $mode );
		}

		echo self::render_widget_header( $range, $domain, $mode );

		$data = self::get_analytics( $api_key, $domain, $range, $mode );
		if ( is_wp_error( $data ) ) {
			echo '<p><strong>Error:</strong> ' . esc_html( $data->get_error_message() ) . '</p>';
			echo self::render_widget_footer( $domain );
			return;
		}

		$summary    = isset( $data['summary'] ) && is_array( $data['summary'] ) ? $data['summary'] : [];
		$daily_data = isset( $data['daily_data'] ) && is_array( $data['daily_data'] ) ? $data['daily_data'] : [];

		$page_views      = isset( $summary['page_views'] ) ? (int) $summary['page_views'] : null;
		$unique_visitors = isset( $summary['unique_visitors'] ) ? (int) $summary['unique_visitors'] : null;
		$bounce_rate_pct = self::cabin_bounce_rate_percent( $summary );

		$uv_pct = null;
		if ( ! is_null( $unique_visitors ) && ! is_null( $page_views ) && $page_views > 0 ) {
			$uv_pct = ( $unique_visitors / $page_views ) * 100;
		}

		if ( 'chart' === $mode ) {
			$chart = self::views_visitors_stacked_render( $daily_data ); // svg + overlay + popover
			?>
			<div class="wp-cabin-chart-only">
				<div class="wp-cabin-chart-wrap">
					<div class="wp-cabin-chart-head" aria-hidden="true">
						<div class="wp-cabin-legend">
							<span class="wp-cabin-legend__item">
								<span class="wp-cabin-legend__swatch is-views" aria-hidden="true"></span>
								Views
							</span>
							<span class="wp-cabin-legend__item">
								<span class="wp-cabin-legend__swatch is-visitors" aria-hidden="true"></span>
								Visitors
							</span>

							<span class="wp-cabin-help" title="<?php echo esc_attr__( 'Click a bar to see values.', 'wp-cabin' ); ?>">
								<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
								<span class="screen-reader-text"><?php echo esc_html__( 'Click a bar to see values.', 'wp-cabin' ); ?></span>
							</span>
						</div>
					</div>

					<div class="wp-cabin-chart" aria-hidden="true">
						<div class="wp-cabin-chart-shell">
							<?php echo $chart['svg']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo $chart['overlay']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo $chart['popover']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					</div>
				</div>

				<div class="wp-cabin-metrics-row">
					<div class="wp-cabin-metric-card">
						<div class="wp-cabin-metric-card__label">Page hits</div>
						<div class="wp-cabin-metric-card__value"><?php echo esc_html( is_null( $page_views ) ? '—' : number_format_i18n( $page_views ) ); ?></div>
					</div>

					<div class="wp-cabin-metric-card">
						<div class="wp-cabin-metric-card__label">
							Unique visitors
							<?php if ( ! is_null( $uv_pct ) ) : ?>
								<span class="wp-cabin-metric-card__hint">(<?php echo esc_html( number_format_i18n( $uv_pct, 0 ) ); ?>%)</span>
							<?php endif; ?>
						</div>
						<div class="wp-cabin-metric-card__value"><?php echo esc_html( is_null( $unique_visitors ) ? '—' : number_format_i18n( $unique_visitors ) ); ?></div>
					</div>

					<div class="wp-cabin-metric-card">
						<div class="wp-cabin-metric-card__label">Bounce rate</div>
						<div class="wp-cabin-metric-card__value"><?php
							echo esc_html( is_null( $bounce_rate_pct ) ? '—' : number_format_i18n( $bounce_rate_pct ) . '%' );
						?></div>
					</div>
				</div>
			</div>
			<?php
		} else {
			// Larger sparkline to better match Cabin feel.
			$spark_points = self::daily_points_from_timestamp_series( $daily_data, 'page_views' );
			$spark_svg    = self::sparkline_svg( $spark_points, 520, 120 );
			?>
			<div class="wp-cabin-grid">
				<div class="wp-cabin-metric">
					<div class="wp-cabin-metric__label">Page hits</div>
					<div class="wp-cabin-metric__value"><?php echo esc_html( is_null( $page_views ) ? '—' : number_format_i18n( $page_views ) ); ?></div>
				</div>

				<div class="wp-cabin-metric">
					<div class="wp-cabin-metric__label">
						Unique visitors
						<?php if ( ! is_null( $uv_pct ) ) : ?>
							<span class="wp-cabin-metric__hint">(<?php echo esc_html( number_format_i18n( $uv_pct, 0 ) ); ?>%)</span>
						<?php endif; ?>
					</div>
					<div class="wp-cabin-metric__value"><?php echo esc_html( is_null( $unique_visitors ) ? '—' : number_format_i18n( $unique_visitors ) ); ?></div>
				</div>

				<div class="wp-cabin-metric">
					<div class="wp-cabin-metric__label">Bounce rate</div>
					<div class="wp-cabin-metric__value"><?php
						echo esc_html( is_null( $bounce_rate_pct ) ? '—' : number_format_i18n( $bounce_rate_pct ) . '%' );
					?></div>
				</div>

				<div class="wp-cabin-spark">
					<div class="wp-cabin-spark__label">Trend</div>
					<div class="wp-cabin-spark__chart" aria-hidden="true">
						<?php echo $spark_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<p class="description wp-cabin-spark__desc">Daily page hits over the selected range.</p>
				</div>
			</div>
			<?php
		}

		echo self::render_widget_footer( $domain );
	}

	private static function render_widget_header( string $range, string $domain, string $mode ) : string {
		$base = remove_query_arg( [ 'cabin_refresh', '_wpnonce' ] );

		$tabs = [
			'7d'   => '7d',
			'14d'  => '14d',
			'30d'  => '30d',
		];

		$out  = '<div class="wp-cabin-header">';
		$out .= '<div class="wp-cabin-header__meta"><span class="dashicons dashicons-chart-area" aria-hidden="true"></span> <span class="wp-cabin-domain">' . esc_html( $domain ) . '</span></div>';
		$out .= '<nav class="wp-cabin-tabs" aria-label="Cabin date range">';

		foreach ( $tabs as $key => $label ) {
			$url = add_query_arg( 'cabin_range', $key, $base );
			$cls = 'wp-cabin-tab' . ( $range === $key ? ' is-active' : '' );
			$out .= '<a class="' . esc_attr( $cls ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}

		$out .= '</nav>';

		$refresh_url = wp_nonce_url(
			add_query_arg(
				[
					'cabin_range'   => $range,
					'cabin_refresh' => '1',
				],
				$base
			),
			self::NONCE_ACTION
		);

		$out .= '<div class="wp-cabin-actions">';
		$out .= '<span class="wp-cabin-mode">' . esc_html( 'chart' === $mode ? 'Chart' : 'Sparkline' ) . '</span>';
		$out .= ' <a class="wp-cabin-refresh" href="' . esc_url( $refresh_url ) . '">Refresh</a>';
		$out .= '</div>';

		$out .= '</div>';

		return $out;
	}

	private static function render_widget_footer( string $domain ) : string {
		$settings_link = admin_url( 'options-general.php?page=wp-cabin-analytics' );
		$cabin_link    = 'https://withcabin.com/dashboard/' . rawurlencode( $domain );

		return
			'<div class="wp-cabin-footer">' .
				'<a href="' . esc_url( $settings_link ) . '">Settings</a>' .
				'<a href="' . esc_url( $cabin_link ) . '" target="_blank" rel="noopener noreferrer">View Dashboard</a>' .
			'</div>';
	}

	/* -------------------------
	 * Cabin API
	 * ------------------------- */

	private static function get_analytics( string $api_key, string $domain, string $range, string $mode ) {
		$cache_key = self::cache_key( $domain, $range, $mode );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		[ $date_from, $date_to ] = self::range_dates( $range );

		$url = add_query_arg(
			[
				'domain'      => $domain,
				'date_from'   => $date_from,
				'date_to'     => $date_to,
				'scope'       => 'core',
				'limit_lists' => 10,
			],
			'https://api.withcabin.com/v1/analytics'
		);

		$res = wp_remote_get(
			$url,
			[
				'timeout' => 12,
				'headers' => [
					'x-api-key' => $api_key,
					'accept'    => 'application/json',
				],
			]
		);

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = (string) wp_remote_retrieve_body( $res );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'cabin_http', 'Cabin API request failed (HTTP ' . $code . ').' );
		}

		$json = json_decode( $body, true );
		if ( ! is_array( $json ) ) {
			return new WP_Error( 'cabin_json', 'Could not parse Cabin response as JSON.' );
		}

		set_transient( $cache_key, $json, 10 * MINUTE_IN_SECONDS );
		return $json;
	}

	private static function range_dates( string $range ) : array {
		$today = gmdate( 'Y-m-d' );

		$days = 14;
		if ( '7d' === $range ) {
			$days = 7;
		} elseif ( '30d' === $range ) {
			$days = 30;
		}

		$from_ts   = time() - ( ( $days - 1 ) * DAY_IN_SECONDS );
		$date_from = gmdate( 'Y-m-d', $from_ts );

		return [ $date_from, $today ];
	}

	private static function cache_key( string $domain, string $range, string $mode ) : string {
		return 'wp_cabin_' . md5( $domain . '|' . $range . '|' . $mode );
	}

	private static function delete_cache( string $domain, string $range, string $mode ) : void {
		delete_transient( self::cache_key( $domain, $range, $mode ) );
	}

	private static function get_site_domain() : string {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === trim( $host ) ) {
			return '';
		}
		$host = preg_replace( '/:\d+$/', '', $host );
		return strtolower( $host );
	}

	/* -------------------------
	 * Sparkline helpers
	 * ------------------------- */

	private static function daily_points_from_timestamp_series( array $daily_data, string $key ) : array {
		$points = [];
		foreach ( $daily_data as $row ) {
			if ( ! is_array( $row ) ) continue;
			if ( ! isset( $row[ $key ] ) ) continue;

			$val = $row[ $key ];
			if ( is_numeric( $val ) ) {
				$points[] = (float) $val;
			}
		}
		return $points;
	}

	private static function sparkline_svg( array $values, int $w = 160, int $h = 38 ) : string {
		$pad = 6;

		if ( count( $values ) < 2 ) {
			return '<svg class="wp-cabin-sparkline" viewBox="0 0 ' . (int) $w . ' ' . (int) $h . '" role="img" aria-label="Trend sparkline"><path d="M ' . (int) $pad . ' ' . (int) ( $h - $pad ) . ' L ' . (int) ( $w - $pad ) . ' ' . (int) ( $h - $pad ) . '" fill="none" stroke="currentColor" stroke-width="3" opacity="0.35" /></svg>';
		}

		$min = min( $values );
		$max = max( $values );
		$range = ( $max - $min );
		if ( 0.0 === $range ) {
			$range = 1.0;
		}

		$count = count( $values );
		$step = ( $w - ( 2 * $pad ) ) / ( $count - 1 );

		$points = [];
		for ( $i = 0; $i < $count; $i++ ) {
			$x = $pad + ( $i * $step );
			$norm = ( $values[ $i ] - $min ) / $range;
			$y = ( $h - $pad ) - ( $norm * ( $h - ( 2 * $pad ) ) );
			$points[] = sprintf( '%.2f,%.2f', $x, $y );
		}

		$polyline = implode( ' ', $points );
		$area = $polyline . ' ' . sprintf( '%.2f,%.2f', $pad + ( ( $count - 1 ) * $step ), ( $h - $pad ) ) . ' ' . sprintf( '%.2f,%.2f', $pad, ( $h - $pad ) );

		return
			'<svg class="wp-cabin-sparkline" viewBox="0 0 ' . (int) $w . ' ' . (int) $h . '" role="img" aria-label="Trend sparkline">' .
				'<polygon points="' . esc_attr( $area ) . '" fill="currentColor" opacity="0.10"></polygon>' .
				'<polyline points="' . esc_attr( $polyline ) . '" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></polyline>' .
			'</svg>';
	}

	/* -------------------------
	 * Chart helpers
	 * ------------------------- */

	private static function nice_max( float $max ) : float {
		if ( $max <= 0 ) return 1;

		$exp  = floor( log10( $max ) );
		$base = pow( 10, $exp );
		$f    = $max / $base;

		if ( $f <= 1 ) return 1 * $base;
		if ( $f <= 2 ) return 2 * $base;
		if ( $f <= 5 ) return 5 * $base;
		return 10 * $base;
	}

	private static function format_compact_number( float $n ) : string {
		if ( $n >= 1000000 ) return number_format_i18n( $n / 1000000, 1 ) . 'M';
		if ( $n >= 1000 ) return number_format_i18n( $n / 1000, 1 ) . 'K';
		return number_format_i18n( $n, 0 );
	}

	/**
	 * Cabin bounce rate (matches Cabin UI behavior you confirmed):
	 * bounce_rate = bounces / unique_visitors
	 */
	private static function cabin_bounce_rate_percent( array $summary ) : ?int {
		$uv = isset( $summary['unique_visitors'] ) && is_numeric( $summary['unique_visitors'] ) ? (float) $summary['unique_visitors'] : null;
		$b  = isset( $summary['bounces'] ) && is_numeric( $summary['bounces'] ) ? (float) $summary['bounces'] : null;

		if ( is_null( $uv ) || $uv <= 0 || is_null( $b ) || $b < 0 ) {
			// Fallback to API field if present (assume 0–1 fraction).
			if ( isset( $summary['bounce_rate'] ) && is_numeric( $summary['bounce_rate'] ) ) {
				$r = (float) $summary['bounce_rate'];
				if ( $r > 1 && $r <= 100 ) return (int) round( $r ); // if ever returned as percent
				if ( $r >= 0 && $r <= 1 ) return (int) round( $r * 100 );
			}
			return null;
		}

		$rate = min( 1.0, max( 0.0, $b / $uv ) );
		return (int) round( $rate * 100 );
	}

	/**
	 * Returns chart SVG plus an invisible HTML overlay and a single Popover balloon.
	 * - Baseline hover remains via SVG <title>.
	 * - Enhanced (modern): click a bar to open an anchored popover.
	 */
	private static function views_visitors_stacked_render( array $daily_data ) : array {
		$points = [];

		foreach ( $daily_data as $row ) {
			if ( ! is_array( $row ) ) continue;

			$ts    = isset( $row['timestamp'] ) && is_numeric( $row['timestamp'] ) ? (int) $row['timestamp'] : 0;
			$views = isset( $row['page_views'] ) && is_numeric( $row['page_views'] ) ? (float) $row['page_views'] : null;
			$uniq  = isset( $row['unique_visitors'] ) && is_numeric( $row['unique_visitors'] ) ? (float) $row['unique_visitors'] : null;

			if ( $ts <= 0 || is_null( $views ) || is_null( $uniq ) ) continue;

			$views = max( 0.0, $views );
			$uniq  = max( 0.0, min( $uniq, $views ) );
			$cap   = max( 0.0, $views - $uniq );

			$points[] = [
				'ts'    => $ts,
				'views' => $views,
				'uniq'  => $uniq,
				'cap'   => $cap,
			];
		}

		usort( $points, function( $a, $b ) {
			return $a['ts'] <=> $b['ts'];
		});

		if ( count( $points ) < 2 ) {
			return [
				'svg'     => '<div class="notice inline notice-warning"><p>Not enough data to render chart.</p></div>',
				'overlay' => '',
				'popover' => '',
			];
		}

		$raw_max = 0.0;
		foreach ( $points as $p ) {
			if ( $p['views'] > $raw_max ) $raw_max = $p['views'];
		}
		$max = self::nice_max( (float) $raw_max );
		if ( $max <= 0 ) $max = 1.0;

		// Geometry (must match overlay percent math)
		$w = 860;
		$h = 380;

		$padL = 56;
		$padB = 52;
		$padT = 18;
		$padR = 16;

		$innerW = $w - $padL - $padR;
		$innerH = $h - $padT - $padB;

		$n = count( $points );
		$gap = 10;
		$barW = max( 10, ( $innerW - ( ( $n - 1 ) * $gap ) ) / $n );

		$gridLines = 4;
		$labelEvery = ( $n > 14 ) ? 3 : ( ( $n > 10 ) ? 2 : 1 );

		$svg  = '<svg class="wp-cabin-vv-chart" viewBox="0 0 ' . (int) $w . ' ' . (int) $h . '" role="img" aria-label="Views and visitors per day">';

		// Grid + y labels
		$svg .= '<g class="grid">';
		for ( $i = 0; $i <= $gridLines; $i++ ) {
			$y = $padT + ( $innerH * ( $i / $gridLines ) );
			$svg .= '<line x1="' . (int) $padL . '" y1="' . (int) $y . '" x2="' . (int) ( $w - $padR ) . '" y2="' . (int) $y . '" />';

			$val = $max * ( 1 - ( $i / $gridLines ) );
			$svg .= '<text class="ylab" x="' . (int) ( $padL - 10 ) . '" y="' . (int) ( $y + 4 ) . '" text-anchor="end">' . esc_html( self::format_compact_number( $val ) ) . '</text>';
		}
		$svg .= '</g>';

		$overlay = '<div class="wp-cabin-overlay" aria-hidden="true">';
		$bars    = '<g class="bars">';

		for ( $i = 0; $i < $n; $i++ ) {
			$p = $points[ $i ];
			$x = $padL + ( $i * ( $barW + $gap ) );

			$uniqH = ( $p['uniq'] / $max ) * $innerH;
			$capH  = ( $p['cap']  / $max ) * $innerH;

			$yUniq = $padT + ( $innerH - $uniqH );
			$yCap  = $yUniq - $capH;

			$stackTop = $yCap;
			$stackH   = $uniqH + $capH;

			$ts_sec = (int) floor( $p['ts'] / 1000 );
			$label  = wp_date( 'D, j M Y', $ts_sec ); // matches your screenshot style
			$title  = sprintf(
				'%s — Views: %s, Visitors: %s',
				$label,
				number_format_i18n( (int) $p['views'] ),
				number_format_i18n( (int) $p['uniq'] )
			);

			$bars .= '<g class="day">';
			$bars .= '<title>' . esc_html( $title ) . '</title>';

			// Visitors base
			$bars .= '<rect class="bar bar--visitors" x="' . esc_attr( $x ) . '" y="' . esc_attr( $yUniq ) . '" width="' . esc_attr( $barW ) . '" height="' . esc_attr( $uniqH ) . '" rx="2" />';

			// Views cap
			if ( $capH > 0 ) {
				$bars .= '<rect class="bar bar--views-cap" x="' . esc_attr( $x ) . '" y="' . esc_attr( $yCap ) . '" width="' . esc_attr( $barW ) . '" height="' . esc_attr( $capH ) . '" rx="2" />';
			}

			// X label
			if ( 0 === ( $i % $labelEvery ) ) {
				$short = wp_date( 'M j', $ts_sec );
				$bars .= '<text class="xlab" x="' . esc_attr( $x + ( $barW / 2 ) ) . '" y="' . esc_attr( $padT + $innerH + 32 ) . '" text-anchor="middle">' . esc_html( $short ) . '</text>';
			}

			$bars .= '</g>';

			// Overlay button (for popover anchoring)
			// Convert SVG coords to percentages (overlay uses absolute positioning in same box)
			$leftPct = ( $x / $w ) * 100;
			$topPct  = ( $stackTop / $h ) * 100;
			$wPct    = ( $barW / $w ) * 100;
			$hPct    = ( $stackH / $h ) * 100;

			// Ensure a minimum clickable height without changing chart geometry.
			// If the bar is tiny, give the hit target a little extra height (still centered).
			if ( $hPct < 2.0 ) {
				$extra = ( 2.0 - $hPct );
				$topPct = max( 0.0, $topPct - ( $extra / 2.0 ) );
				$hPct = 2.0;
			}

			$anchor = '--wp-cabin-a-' . $i;

			$overlay .= sprintf(
				'<button type="button" class="wp-cabin-hit" style="left:%.4f%%; top:%.4f%%; width:%.4f%%; height:%.4f%%; anchor-name:%s;" data-wp-cabin-anchor="%s" data-label="%s" data-uniq="%s" data-views="%s" aria-label="%s"></button>',
				$leftPct,
				$topPct,
				$wPct,
				$hPct,
				esc_attr( $anchor ),
				esc_attr( $anchor ),
				esc_attr( $label ),
				esc_attr( number_format_i18n( (int) $p['uniq'] ) ),
				esc_attr( number_format_i18n( (int) $p['views'] ) ),
				esc_attr( $title )
			);
		}

		$bars .= '</g>';
		$overlay .= '</div>';

		$svg .= $bars;
		$svg .= '</svg>';

		$popover =
			'<div class="wp-cabin-pop" popover id="wp-cabin-popover" aria-hidden="true">' .
				'<div class="wp-cabin-balloon">' .
					'<div class="wp-cabin-balloon__title" data-wp-cabin-pop-title></div>' .
					'<div class="wp-cabin-balloon__row"><span class="sw sw--dark" aria-hidden="true"></span><span data-wp-cabin-pop-uniq></span></div>' .
					'<div class="wp-cabin-balloon__row"><span class="sw sw--light" aria-hidden="true"></span><span data-wp-cabin-pop-views></span></div>' .
				'</div>' .
			'</div>';

		return [
			'svg'     => $svg,
			'overlay' => $overlay,
			'popover' => $popover,
		];
	}

	/* -------------------------
	 * Admin CSS
	 * ------------------------- */

	public static function admin_css() : void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'dashboard' !== $screen->id ) {
			return;
		}
		?>
		<style>
			#wp_cabin_dashboard_widget .inside { padding-top: 8px; }

			.wp-cabin-header{
				display:flex;
				align-items:center;
				justify-content:space-between;
				gap:12px;
				margin: 0 0 10px;
			}
			.wp-cabin-header__meta{
				display:flex;
				align-items:center;
				gap:6px;
				color:#50575e;
				white-space:nowrap;
			}
			.wp-cabin-domain{ font-weight: 600; }
			.wp-cabin-tabs{
				display:flex;
				gap:4px;
				flex-wrap:wrap;
				margin: 0;
			}
			.wp-cabin-tab{
				display:inline-block;
				padding: 4px 10px;
				border: 1px solid #dcdcde;
				border-radius: 6px;
				text-decoration:none;
				color:#1d2327;
				background:#fff;
			}
			.wp-cabin-tab:hover{ background:#f6f7f7; }
			.wp-cabin-tab.is-active{
				border-color:#2271b1;
				box-shadow: 0 0 0 1px #2271b1;
				color:#2271b1;
				background:#f0f6fc;
			}
			.wp-cabin-actions{
				display:flex;
				align-items:center;
				gap:10px;
				white-space:nowrap;
			}
			.wp-cabin-mode{
				color:#646970;
				font-size:12px;
			}
			.wp-cabin-actions .wp-cabin-refresh{ text-decoration:none; }

			/* Footer: Settings left, View Dashboard right */
			.wp-cabin-footer{
				margin: 10px 0 0;
				padding-top: 8px;
				border-top: 1px solid #f0f0f1;
				display: flex;
				justify-content: space-between;
				align-items: center;
				font-size: 13px;
			}

			/* Minimal sparkline mode layout */
			.wp-cabin-grid{
				display:grid;
				grid-template-columns: repeat(3, minmax(0, 1fr));
				gap: 12px;
				margin-top: 8px;
			}
			.wp-cabin-metric{
				border: 1px solid #dcdcde;
				border-radius: 6px;
				padding: 10px 12px;
				background: #fff;
			}
			.wp-cabin-metric__label{
				color:#50575e;
				font-size: 12px;
				margin-bottom: 6px;
				letter-spacing: .02em;
				text-transform: uppercase;
			}
			.wp-cabin-metric__hint{
				margin-left: 6px;
				color:#646970;
				font-weight:600;
				text-transform:none;
				letter-spacing:0;
			}
			.wp-cabin-metric__value{
				font-size: 22px;
				line-height: 1.1;
				font-weight: 700;
			}

			/* Larger sparkline panel with label above and description below */
			.wp-cabin-spark{
				grid-column: 1 / -1;
				border: 1px solid #dcdcde;
				border-radius: 6px;
				padding: 10px 12px;
				background: #fff;
			}
			.wp-cabin-spark__label{
				color:#50575e;
				font-size: 12px;
				letter-spacing: .02em;
				text-transform: uppercase;
				margin: 0 0 8px;
			}
			.wp-cabin-spark__chart{ width: 100%; }
			.wp-cabin-sparkline{
				width: 100%;
				height: auto;
				display: block;
			}
			.wp-cabin-spark__desc{ margin: 8px 0 0; }

			/* Chart-first layout (chart full width; metrics below) */
			.wp-cabin-chart-only{
				display: grid;
				gap: 12px;
				margin-top: 10px;
			}
			.wp-cabin-chart-wrap{
				border: 1px solid #dcdcde;
				border-radius: 6px;
				background: #fff;
				overflow: hidden;
			}
			.wp-cabin-chart-head{
				display:flex;
				justify-content:flex-end;
				align-items:center;
				padding: 10px 12px 6px;
				border-bottom: 1px solid #f0f0f1;
			}
			.wp-cabin-legend{
				display:flex;
				gap: 12px;
				align-items:center;
				color:#50575e;
				font-size: 12px;
			}
			.wp-cabin-legend__item{
				display:flex;
				align-items:center;
				gap: 6px;
				white-space: nowrap;
			}
			.wp-cabin-legend__swatch{
				width: 10px;
				height: 10px;
				border-radius: 2px;
				display:inline-block;
			}
			.wp-cabin-legend__swatch.is-views{
				background:#e9ecef;
				border: 1px solid #dcdcde;
			}
			.wp-cabin-legend__swatch.is-visitors{ background:#1d2327; }

			/* Option 2: tiny info icon in chart header */
			.wp-cabin-help{
				display:inline-flex;
				align-items:center;
				gap:6px;
				color:#646970;
				margin-left: 6px;
			}
			.wp-cabin-help .dashicons{
				font-size: 16px;
				width: 16px;
				height: 16px;
				line-height: 16px;
			}

			.wp-cabin-chart{
				padding: 8px 12px 12px;
				min-height: 340px;
			}
			.wp-cabin-chart-shell{
				position: relative;
				width: 100%;
				height: 100%;
			}
			.wp-cabin-vv-chart{
				width: 100%;
				height: 100%;
				display: block;
			}
			.wp-cabin-vv-chart .grid line{
				stroke: #e5e5e5;
				stroke-width: 1;
			}
			.wp-cabin-vv-chart .bar--views-cap{ fill: #e9ecef; }
			.wp-cabin-vv-chart .bar--visitors{ fill: #1d2327; }

			.wp-cabin-vv-chart .xlab,
			.wp-cabin-vv-chart .ylab{
				font-size: 12px;
				fill: #50575e;
			}
			.wp-cabin-vv-chart .day:hover .bar{ opacity: 0.92; }
			.wp-cabin-vv-chart .bar{ cursor: pointer; }

			/* Overlay hit targets (for popover anchoring) */
			.wp-cabin-overlay{
				position: absolute;
				inset: 0;
				pointer-events: none;
				z-index: 5;
			}
			.wp-cabin-hit{
				position: absolute;
				pointer-events: auto;
				background: transparent;
				border: 0;
				padding: 0;
				margin: 0;
				cursor: pointer;
				border-radius: 4px;
			}
			.wp-cabin-hit:focus-visible{
				outline: 2px solid #2271b1;
				outline-offset: 2px;
			}

			/* Popover balloon */
			.wp-cabin-pop[popover]{
				border: 0;
				padding: 0;
				background: transparent;
			}

			.wp-cabin-balloon{
				background: #3b82f6;
				color: #fff;
				border-radius: 10px;
				padding: 14px 16px;
				box-shadow: 0 10px 25px rgba(0,0,0,0.18);
				min-width: 240px;
				max-width: 320px;
			}
			.wp-cabin-balloon__title{
				font-weight: 700;
				font-size: 16px;
				margin: 0 0 10px;
			}
			.wp-cabin-balloon__row{
				display:flex;
				align-items:center;
				gap: 8px;
				font-size: 14px;
				margin: 2px 0;
			}
			.wp-cabin-balloon .sw{
				width: 16px;
				height: 16px;
				border-radius: 2px;
				display:inline-block;
				border: 1px solid rgba(255,255,255,0.7);
				flex: 0 0 auto;
			}
			.wp-cabin-balloon .sw--dark{ background: #0f172a; }
			.wp-cabin-balloon .sw--light{ background: rgba(255,255,255,0.25); }

			/* Modern anchored placement (progressive enhancement) */
			@supports (position-anchor: --a) and (anchor-name: --a) {
				.wp-cabin-pop[popover]{
					position: fixed;
					inset: auto;
					margin: 0;
					z-index: 999999;
					position-anchor: var(--wp-cabin-active-anchor);
					top: anchor(top);
					left: anchor(center);
					translate: -50% -120%;
				}
			}

			.wp-cabin-metrics-row{
				display: grid;
				grid-template-columns: repeat(3, minmax(0, 1fr));
				gap: 12px;
			}
			.wp-cabin-metric-card{
				border: 1px solid #dcdcde;
				border-radius: 6px;
				padding: 10px 12px;
				background: #fff;
			}
			.wp-cabin-metric-card__label{
				color:#50575e;
				font-size: 12px;
				letter-spacing: .02em;
				text-transform: uppercase;
				margin-bottom: 6px;
			}
			.wp-cabin-metric-card__hint{
				text-transform: none;
				letter-spacing: 0;
				font-weight: 600;
				margin-left: 6px;
				color:#646970;
			}
			.wp-cabin-metric-card__value{
				font-size: 34px;
				line-height: 1;
				font-weight: 750;
			}

			@media (max-width: 782px){
				.wp-cabin-grid{ grid-template-columns: 1fr; }
				.wp-cabin-metrics-row{ grid-template-columns: 1fr; }
				.wp-cabin-chart{ min-height: 260px; }
				.wp-cabin-metric-card__value{ font-size: 28px; }
				.wp-cabin-balloon{ min-width: 200px; }
			}
		</style>
		<?php
	}

	/* -------------------------
	 * Admin JS (dashboard only)
	 * ------------------------- */

	public static function admin_js() : void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'dashboard' !== $screen->id ) {
			return;
		}
		?>
		<script>
			(() => {
				const widget = document.getElementById('wp_cabin_dashboard_widget');
				if (!widget) return;

				const pop = widget.querySelector('#wp-cabin-popover');
				if (!pop || typeof pop.showPopover !== 'function') return;

				// Require modern anchor positioning; otherwise fallback remains SVG <title>.
				if (!(window.CSS && CSS.supports && CSS.supports('position-anchor: --a') && CSS.supports('anchor-name: --a'))) {
					return;
				}

				const titleEl = pop.querySelector('[data-wp-cabin-pop-title]');
				const uniqEl  = pop.querySelector('[data-wp-cabin-pop-uniq]');
				const viewsEl = pop.querySelector('[data-wp-cabin-pop-views]');

				function setText(el, text) {
					if (!el) return;
					el.textContent = text;
				}

				widget.querySelectorAll('.wp-cabin-hit').forEach((btn) => {
					btn.addEventListener('click', () => {
						const anchor = btn.getAttribute('data-wp-cabin-anchor') || '';
						if (!anchor) return;

						// Set anchor token for CSS: position-anchor: var(--wp-cabin-active-anchor)
						pop.style.setProperty('--wp-cabin-active-anchor', anchor);

						const label = btn.getAttribute('data-label') || '';
						const uniq  = btn.getAttribute('data-uniq') || '';
						const views = btn.getAttribute('data-views') || '';

						setText(titleEl, label);
						setText(uniqEl, `Unique visitors: ${uniq}`);
						setText(viewsEl, `Total visitors: ${views}`);

						pop.showPopover();
					});

					// Keyboard: Enter/Space activates
					btn.addEventListener('keydown', (e) => {
						if (e.key === 'Enter' || e.key === ' ') {
							e.preventDefault();
							btn.click();
						}
					});
				});

				// Click outside to close (popover already closes on Esc)
				document.addEventListener('mousedown', (e) => {
					if (!pop.matches(':popover-open')) return;
					const inside = pop.contains(e.target);
					if (!inside) {
						pop.hidePopover();
					}
				});
			})();
		</script>
		<?php
	}
}

WP_Cabin_Dashboard_Widget::init();