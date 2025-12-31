<?php
/**
 * Plugin Name:       Cabin Analytics Dashboard Widget
 * Description:       WordPress-native dashboard widget for Cabin Analytics (Summary + larger sparkline OR Cabin-style stacked Views/Visitors chart).
 * Version:           1.0.0
 * Requires at least: 6.9
 * Requires PHP:      8.3
 * Author:            Stephen Walker
 * License:           GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

final class WP_Cabin_Dashboard_Widget {
	const OPT_API_KEY        = 'wp_cabin_api_key';
	const OPT_MODE           = 'wp_cabin_widget_mode';        // 'sparkline' | 'chart'
	const OPT_DEFAULT_RANGE  = 'wp_cabin_default_range';      // 'today' | '7d' | '30d'
	const NONCE_ACTION       = 'wp_cabin_widget';

	public static function init() : void {
		add_action( 'admin_menu', [ __CLASS__, 'add_settings_page' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'wp_dashboard_setup', [ __CLASS__, 'register_dashboard_widget' ] );
		add_action( 'admin_head', [ __CLASS__, 'admin_css' ] );
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
				'default'           => '7d',
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
		$value = is_string( $value ) ? sanitize_key( $value ) : '7d';
		return in_array( $value, [ 'today', '7d', '30d' ], true ) ? $value : '7d';
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
		$val = (string) get_option( self::OPT_DEFAULT_RANGE, '7d' );
		?>
		<fieldset>
			<label>
				<input type="radio" name="<?php echo esc_attr( self::OPT_DEFAULT_RANGE ); ?>" value="today" <?php checked( $val, 'today' ); ?> />
				Today
			</label><br />
			<label>
				<input type="radio" name="<?php echo esc_attr( self::OPT_DEFAULT_RANGE ); ?>" value="7d" <?php checked( $val, '7d' ); ?> />
				7d
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

		$default_range = (string) get_option( self::OPT_DEFAULT_RANGE, '7d' );
		$range = isset( $_GET['cabin_range'] ) ? sanitize_key( (string) $_GET['cabin_range'] ) : $default_range;
		if ( ! in_array( $range, [ 'today', '7d', '30d' ], true ) ) {
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
		$bounce_rate     = isset( $summary['bounce_rate'] ) ? (float) $summary['bounce_rate'] : null;

		$uv_pct = null;
		if ( ! is_null( $unique_visitors ) && ! is_null( $page_views ) && $page_views > 0 ) {
			$uv_pct = ( $unique_visitors / $page_views ) * 100;
		}

		if ( 'chart' === $mode ) {
			$chart_svg = self::views_visitors_stacked_svg( $daily_data );
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
						</div>
					</div>

					<div class="wp-cabin-chart" aria-hidden="true">
						<?php echo $chart_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
							echo esc_html(
								is_null( $bounce_rate )
									? '—'
									: number_format_i18n( $bounce_rate * 100, 0 ) . '%'
							);
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
						echo esc_html(
							is_null( $bounce_rate )
								? '—'
								: number_format_i18n( $bounce_rate * 100, 0 ) . '%'
						);
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
			'today' => 'Today',
			'7d'    => '7d',
			'30d'   => '30d',
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

		if ( 'today' === $range ) {
			return [ $today, $today ];
		}

		$days = ( '30d' === $range ) ? 30 : 7;
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
	 * Cabin-style stacked bars:
	 * - Dark base = unique visitors
	 * - Light cap = page views minus unique visitors
	 * Includes <title> per day for hover labels (zero JS) and y-axis labels.
	 */
	private static function views_visitors_stacked_svg( array $daily_data ) : string {
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
			return '<div class="notice inline notice-warning"><p>Not enough data to render chart.</p></div>';
		}

		$raw_max = 0.0;
		foreach ( $points as $p ) {
			if ( $p['views'] > $raw_max ) $raw_max = $p['views'];
		}
		$max = self::nice_max( (float) $raw_max );
		if ( $max <= 0 ) $max = 1.0;

		// Geometry
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

		// Bars (stacked)
		$svg .= '<g class="bars">';
		for ( $i = 0; $i < $n; $i++ ) {
			$p = $points[ $i ];
			$x = $padL + ( $i * ( $barW + $gap ) );

			$uniqH = ( $p['uniq'] / $max ) * $innerH;
			$capH  = ( $p['cap']  / $max ) * $innerH;

			$yUniq = $padT + ( $innerH - $uniqH );
			$yCap  = $yUniq - $capH;

			$ts_sec = (int) floor( $p['ts'] / 1000 );
			$label  = wp_date( 'M j, Y', $ts_sec );
			$title  = sprintf(
				'%s — Views: %s, Visitors: %s',
				$label,
				number_format_i18n( (int) $p['views'] ),
				number_format_i18n( (int) $p['uniq'] )
			);

			$svg .= '<g class="day">';
			$svg .= '<title>' . esc_html( $title ) . '</title>';

			// Visitors base
			$svg .= '<rect class="bar bar--visitors" x="' . esc_attr( $x ) . '" y="' . esc_attr( $yUniq ) . '" width="' . esc_attr( $barW ) . '" height="' . esc_attr( $uniqH ) . '" rx="2" />';

			// Views cap
			if ( $capH > 0 ) {
				$svg .= '<rect class="bar bar--views-cap" x="' . esc_attr( $x ) . '" y="' . esc_attr( $yCap ) . '" width="' . esc_attr( $barW ) . '" height="' . esc_attr( $capH ) . '" rx="2" />';
			}

			// X label
			if ( 0 === ( $i % $labelEvery ) ) {
				$short = wp_date( 'M j', $ts_sec );
				$svg .= '<text class="xlab" x="' . esc_attr( $x + ( $barW / 2 ) ) . '" y="' . esc_attr( $padT + $innerH + 32 ) . '" text-anchor="middle">' . esc_html( $short ) . '</text>';
			}

			$svg .= '</g>';
		}
		$svg .= '</g>';

		$svg .= '</svg>';

		return $svg;
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
			.wp-cabin-spark__chart{
				width: 100%;
			}
			.wp-cabin-sparkline{
				width: 100%;
				height: auto;
				display: block;
			}
			.wp-cabin-spark__desc{
				margin: 8px 0 0;
			}

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
			.wp-cabin-legend__swatch.is-visitors{
				background:#1d2327;
			}

			.wp-cabin-chart{
				padding: 8px 12px 12px;
				min-height: 340px;
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
			.wp-cabin-vv-chart .day:hover .bar{
				opacity: 0.92;
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
			}
		</style>
		<?php
	}
}

WP_Cabin_Dashboard_Widget::init();