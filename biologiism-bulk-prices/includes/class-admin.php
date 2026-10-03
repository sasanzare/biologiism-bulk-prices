<?php
/**
 * Capability- and nonce-gated WooCommerce admin workflow.
 *
 * @package BiologiismBulkPrices
 */

declare(strict_types=1);

namespace Biologiism\BulkPrices;

defined( 'ABSPATH' ) || exit;

final class Admin {
	private const PAGE_SLUG = 'biologiism-bulk-prices';

	/**
	 * Register admin hooks after plugins load.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( ! function_exists( 'wc_get_products' ) ) {
			add_action( 'admin_notices', array( self::class, 'dependency_notice' ) );
			return;
		}

		add_action( 'admin_menu', array( self::class, 'register_page' ) );
		add_action( 'admin_post_bbpm_preview', array( self::class, 'handle_preview' ) );
		add_action( 'admin_post_bbpm_apply', array( self::class, 'handle_apply' ) );
		add_action( 'admin_post_bbpm_restore', array( self::class, 'handle_restore' ) );
	}

	/**
	 * Show an admin warning if WooCommerce is unavailable.
	 *
	 * @return void
	 */
	public static function dependency_notice(): void {
		if ( ! self::can_manage() ) {
			return;
		}
		echo '<div class="notice notice-error"><p>';
		echo esc_html( 'افزونه مدیریت گروهی قیمت‌های بیولوژیسم برای اجرا به ووکامرس فعال نیاز دارد.' );
		echo '</p></div>';
	}

	/**
	 * Add the WooCommerce submenu.
	 *
	 * @return void
	 */
	public static function register_page(): void {
		add_submenu_page(
			'woocommerce',
			'مدیریت گروهی قیمت‌ها',
			'مدیریت گروهی قیمت‌ها',
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( self::class, 'render_page' )
		);
	}

	/**
	 * Render the admin page.
	 *
	 * @return void
	 */
	public static function render_page(): void {
		self::require_capability();

		echo '<div class="wrap" dir="rtl">';
		echo '<h1>مدیریت گروهی قیمت‌های بیولوژیسم</h1>';
		echo '<p>پیش‌نمایش‌ها فقط محصولات منتشرشدهٔ ساده و متغیر را بررسی می‌کنند. هیچ قیمت عادی تغییر نمی‌کند.</p>';

		self::render_result_notice();
		self::render_operation_forms();

		$preview = self::load_preview_from_query();
		if ( is_array( $preview ) ) {
			self::render_preview( $preview );
		}

		self::render_backup_history();
		echo '</div>';
	}

	/**
	 * Build and store a user-bound read-only preview.
	 *
	 * @return void
	 */
	public static function handle_preview(): void {
		self::require_capability();
		check_admin_referer( 'bbpm_preview' );

		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		$percentage = null;

		if ( PriceManager::MODE_PERCENT === $mode ) {
			$raw_percentage = isset( $_POST['percentage'] ) ? sanitize_text_field( wp_unslash( $_POST['percentage'] ) ) : '';
			$raw_percentage = VariationClassifier::normalize( $raw_percentage );
			$raw_percentage = str_replace( array( '٫', ',' ), '.', $raw_percentage );
			if ( ! is_numeric( $raw_percentage ) ) {
				self::redirect_with_notice( 'درصد واردشده معتبر نیست.' );
			}

			$percentage = (float) $raw_percentage;
			if ( $percentage < 0.01 || $percentage > 99.99 || round( $percentage, 2 ) !== $percentage ) {
				self::redirect_with_notice( 'درصد تخفیف باید بین ۰٫۰۱ تا ۹۹٫۹۹ و حداکثر با دو رقم اعشار باشد.' );
			}
		}

		$preview = PriceManager::build_preview( $mode, $percentage );
		if ( is_wp_error( $preview ) ) {
			self::redirect_with_notice( $preview->get_error_message() );
		}

		$token = bin2hex( random_bytes( 16 ) );
		$preview['user_id'] = get_current_user_id();
		$preview_key = self::preview_key( $token );
		set_transient( $preview_key, $preview, DAY_IN_SECONDS );

		if ( get_transient( $preview_key ) !== $preview ) {
			self::redirect_with_notice( 'پیش‌نمایش ذخیره و بررسی نشد؛ قیمت‌ها تغییر نکردند.' );
		}

		self::redirect_to_page( array( 'preview' => $token ) );
	}

	/**
	 * Apply one stored preview.
	 *
	 * @return void
	 */
	public static function handle_apply(): void {
		self::require_capability();
		check_admin_referer( 'bbpm_apply' );

		$token = self::posted_token( 'preview_token' );
		if ( '' === $token ) {
			self::redirect_with_notice( 'پیش‌نمایش معتبر نیست یا منقضی شده است.' );
		}

		$preview = get_transient( self::preview_key( $token ) );
		if ( ! is_array( $preview ) || (int) ( $preview['user_id'] ?? 0 ) !== get_current_user_id() ) {
			self::redirect_with_notice( 'پیش‌نمایش معتبر نیست یا منقضی شده است.' );
		}

		$result = PriceManager::apply_preview( $preview );
		delete_transient( self::preview_key( $token ) );
		$result_token = self::store_result( $result );
		self::redirect_to_page( array( 'result' => $result_token ) );
	}

	/**
	 * Restore one saved snapshot after a separate acknowledgement checkbox.
	 *
	 * @return void
	 */
	public static function handle_restore(): void {
		self::require_capability();
		check_admin_referer( 'bbpm_restore' );

		if ( ! isset( $_POST['confirm_restore'] ) || '1' !== sanitize_text_field( wp_unslash( $_POST['confirm_restore'] ) ) ) {
			self::redirect_with_notice( 'برای بازگردانی، تیک تأیید را فعال کنید.' );
		}

		$backup_id = self::posted_token( 'backup_id' );
		$backup = PriceManager::get_backup( $backup_id );
		if ( is_wp_error( $backup ) ) {
			self::redirect_with_notice( $backup->get_error_message() );
		}

		$result = PriceManager::restore_backup( $backup );
		$result_token = self::store_result( $result );
		self::redirect_to_page( array( 'result' => $result_token ) );
	}

	/**
	 * Render operation selection forms.
	 *
	 * @return void
	 */
	private static function render_operation_forms(): void {
		$action_url = admin_url( 'admin-post.php' );

		echo '<div style="max-width:1000px;background:#fff;border:1px solid #c3c4c7;padding:18px;margin:18px 0;">';
		echo '<h2>حذف تخفیف نسخهٔ مادام‌العمر</h2>';
		echo '<p>فقط variationهایی هدف هستند که مقدار ویژگی‌شان با «نسخه مادام العمر» شروع شود. قیمت عادی، گزینهٔ قسطی و رایگان دست‌نخورده می‌ماند؛ زمان‌بندی فروش همان variation هم پاک می‌شود.</p>';
		echo '<form method="post" action="' . esc_url( $action_url ) . '">';
		echo '<input type="hidden" name="action" value="bbpm_preview">';
		echo '<input type="hidden" name="mode" value="' . esc_attr( PriceManager::MODE_REMOVE_LIFETIME ) . '">';
		wp_nonce_field( 'bbpm_preview' );
		submit_button( 'ساخت پیش‌نمایش حذف تخفیف', 'secondary', 'submit', false );
		echo '</form>';
		echo '</div>';

		echo '<div style="max-width:1000px;background:#fff;border:1px solid #c3c4c7;padding:18px;margin:18px 0;">';
		echo '<h2>اعمال تخفیف درصدی روی همهٔ محصولات</h2>';
		echo '<p>درصد از قیمت عادی محاسبه می‌شود و قیمت فروش ویژهٔ فعلیِ محصولات هدف را جایگزین می‌کند. زمان‌بندی فروش قبلی هم پاک می‌شود تا قیمت جدید فوراً اعمال شود. variation رایگان و قیمت‌هایی که پس از گردکردن صفر می‌شوند کنار گذاشته می‌شوند.</p>';
		echo '<form method="post" action="' . esc_url( $action_url ) . '">';
		echo '<input type="hidden" name="action" value="bbpm_preview">';
		echo '<input type="hidden" name="mode" value="' . esc_attr( PriceManager::MODE_PERCENT ) . '">';
		wp_nonce_field( 'bbpm_preview' );
		echo '<label for="bbpm-percentage">درصد تخفیف</label> ';
		echo '<input id="bbpm-percentage" name="percentage" type="number" min="0.01" max="99.99" step="0.01" value="10" required>';
		echo ' <span>محدودهٔ مجاز: ۰٫۰۱ تا ۹۹٫۹۹</span> ';
		submit_button( 'ساخت پیش‌نمایش درصدی', 'secondary', 'submit', false );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render the stored preview and explicit apply action.
	 *
	 * @param array $preview Preview data.
	 * @return void
	 */
	private static function render_preview( array $preview ): void {
		$summary = isset( $preview['summary'] ) && is_array( $preview['summary'] ) ? $preview['summary'] : array();
		$items = isset( $preview['items'] ) && is_array( $preview['items'] ) ? $preview['items'] : array();
		$mode = (string) ( $preview['mode'] ?? '' );
		$token = isset( $_GET['preview'] ) ? self::query_token( wp_unslash( $_GET['preview'] ) ) : '';

		echo '<h2>پیش‌نمایش</h2>';
		if ( PriceManager::MODE_REMOVE_LIFETIME === $mode ) {
			echo '<p><strong>عملیات:</strong> پاک‌کردن قیمت فروش ویژه از variationهای مادام‌العمر.</p>';
		} else {
			echo '<p><strong>عملیات:</strong> تخفیف ' . esc_html( number_format_i18n( (float) ( $preview['percentage'] ?? 0 ), 2 ) ) . '٪ از قیمت عادی.</p>';
		}

		echo '<p>';
		printf(
			'محصولات بررسی‌شده: %1$s | variationهای بررسی‌شده: %2$s | لایسنس‌های مادام‌العمر پیدا‌شده: %3$s | موارد بدون تغییر: %4$s | تغییرهای آماده: %5$s',
			esc_html( number_format_i18n( (int) ( $summary['products_scanned'] ?? 0 ) ) ),
			esc_html( number_format_i18n( (int) ( $summary['variations_scanned'] ?? 0 ) ) ),
			esc_html( number_format_i18n( (int) ( $summary['lifetime_matches'] ?? 0 ) ) ),
			esc_html( number_format_i18n( (int) ( $summary['unchanged'] ?? 0 ) ) ),
			esc_html( number_format_i18n( count( $items ) ) )
		);
		echo '</p>';

		if ( (int) ( $summary['free_skipped'] ?? 0 ) > 0
			|| (int) ( $summary['ambiguous_skipped'] ?? 0 ) > 0
			|| (int) ( $summary['zero_price_skipped'] ?? 0 ) > 0
			|| (int) ( $summary['zero_sale_skipped'] ?? 0 ) > 0
		) {
			echo '<p>';
			printf(
				'کنارگذاشته‌شده: variation رایگان %1$s، مقدار ویژگی مبهم %2$s، قیمت عادی صفر %3$s، قیمت تخفیفِ گرد‌شده به صفر %4$s.',
				esc_html( number_format_i18n( (int) ( $summary['free_skipped'] ?? 0 ) ) ),
				esc_html( number_format_i18n( (int) ( $summary['ambiguous_skipped'] ?? 0 ) ) ),
				esc_html( number_format_i18n( (int) ( $summary['zero_price_skipped'] ?? 0 ) ) ),
				esc_html( number_format_i18n( (int) ( $summary['zero_sale_skipped'] ?? 0 ) ) )
			);
			echo '</p>';
		}

		if ( empty( $items ) ) {
			echo '<p>هیچ تغییری لازم نیست.</p>';
			return;
		}

		echo '<div style="max-width:1200px;overflow:auto;">';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>محصول</th><th>شناسهٔ محصول</th><th>variation / گزینه</th><th>شناسهٔ هدف</th><th>قیمت عادی</th><th>قیمت ویژهٔ فعلی</th><th>قیمت ویژهٔ پیشنهادی</th><th>زمان‌بندی ویژهٔ فعلی</th><th>زمان‌بندی پیشنهادی</th>';
		echo '</tr></thead><tbody>';

		foreach ( $items as $item ) {
			$variation_label = 'variation' === $item['kind'] ? (string) $item['attribute_label'] : 'محصول ساده';
			$new_price_label = PriceManager::MODE_REMOVE_LIFETIME === $mode
				? 'حذف قیمت فروش ویژه'
				: self::display_price( (string) $item['new_sale_price'] );

			echo '<tr>';
			echo '<td>' . esc_html( (string) $item['product_name'] ) . '</td>';
			echo '<td>' . esc_html( (string) $item['product_id'] ) . '</td>';
			echo '<td>' . esc_html( $variation_label ) . '</td>';
			echo '<td>' . esc_html( (string) $item['object_id'] ) . '</td>';
			echo '<td>' . esc_html( self::display_price( (string) $item['regular_price'] ) ) . '</td>';
			echo '<td>' . esc_html( self::display_price( (string) $item['sale_price'] ) ) . '</td>';
			echo '<td>' . esc_html( $new_price_label ) . '</td>';
			echo '<td>' . esc_html( self::display_schedule( $item['sale_from'], $item['sale_to'] ) ) . '</td>';
			echo '<td>' . esc_html( 'پاک‌کردن زمان‌بندی فروش' ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
		echo '<p><strong>توجه:</strong> با اعمال پیش‌نمایش، مقادیر فعلی قیمت فروش ویژه و زمان‌بندی فروش در پشتیبان ذخیره می‌شود. در صورت تغییر هر قیمت از زمان پیش‌نمایش، کل عملیات متوقف می‌شود.</p>';

		if ( '' !== $token ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="bbpm_apply">';
			echo '<input type="hidden" name="preview_token" value="' . esc_attr( $token ) . '">';
			wp_nonce_field( 'bbpm_apply' );
			submit_button( 'اعمال همین پیش‌نمایش', 'primary', 'submit', false );
			echo '</form>';
		}
	}

	/**
	 * Render the restore form for stored snapshots.
	 *
	 * @return void
	 */
	private static function render_backup_history(): void {
		$backups = PriceManager::get_backup_index();
		if ( empty( $backups ) ) {
			return;
		}

		echo '<hr><h2>پشتیبان‌ها و بازگردانی</h2>';
		echo '<p>بازگردانی، قیمت ویژه و تاریخ‌های فروش موارد انتخاب‌شده را به مقادیر ذخیره‌شده برمی‌گرداند. قیمت عادی تغییر نمی‌کند.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="bbpm_restore">';
		wp_nonce_field( 'bbpm_restore' );
		echo '<label for="bbpm-backup">انتخاب پشتیبان</label> ';
		echo '<select id="bbpm-backup" name="backup_id" required>';
		foreach ( $backups as $backup ) {
			if ( empty( $backup['id'] ) || ! preg_match( '/^[a-f0-9]{32}$/', (string) $backup['id'] ) ) {
				continue;
			}
			$label = sprintf(
				'%1$s — %2$s — %3$s مورد — %4$s',
				(string) ( $backup['created_at'] ?? '' ),
				self::operation_label( (string) ( $backup['mode'] ?? '' ) ),
				number_format_i18n( (int) ( $backup['item_count'] ?? 0 ) ),
				self::backup_state_label( (string) ( $backup['status'] ?? '' ) )
			);
			echo '<option value="' . esc_attr( (string) $backup['id'] ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select><br><br>';
		echo '<label><input type="checkbox" name="confirm_restore" value="1" required> بازگردانی، قیمت فروش ویژهٔ موارد این پشتیبان را بازنویسی می‌کند.</label><br><br>';
		submit_button( 'بازگردانی قیمت‌ها از پشتیبان انتخاب‌شده', 'secondary', 'submit', false );
		echo '</form>';
	}

	/**
	 * Render one transient result or notice.
	 *
	 * @return void
	 */
	private static function render_result_notice(): void {
		$token = isset( $_GET['result'] ) ? self::query_token( wp_unslash( $_GET['result'] ) ) : '';
		$notice = isset( $_GET['notice'] ) ? self::query_token( wp_unslash( $_GET['notice'] ) ) : '';

		if ( '' !== $token ) {
			$key = self::result_key( $token );
			$result = get_transient( $key );
			if ( is_array( $result ) ) {
				$class = 'completed' === ( $result['status'] ?? '' ) ? 'notice-success' : 'notice-warning';
				echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . esc_html( (string) ( $result['message'] ?? '' ) ) . '</p></div>';
				delete_transient( $key );
			}
		}

		if ( '' !== $notice ) {
			$key = self::notice_key( $notice );
			$message = get_transient( $key );
			if ( is_string( $message ) && '' !== $message ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
				delete_transient( $key );
			}
		}
	}

	/**
	 * Load a stored preview for this user.
	 *
	 * @return array|null
	 */
	private static function load_preview_from_query(): ?array {
		$token = isset( $_GET['preview'] ) ? self::query_token( wp_unslash( $_GET['preview'] ) ) : '';
		if ( '' === $token ) {
			return null;
		}

		$preview = get_transient( self::preview_key( $token ) );
		if ( ! is_array( $preview ) || (int) ( $preview['user_id'] ?? 0 ) !== get_current_user_id() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html( 'پیش‌نمایش پیدا نشد یا منقضی شده است. دوباره پیش‌نمایش بسازید.' ) . '</p></div>';
			return null;
		}

		return $preview;
	}

	/**
	 * Store a result message for one admin.
	 *
	 * @param array $result Result data.
	 * @return string
	 */
	private static function store_result( array $result ): string {
		$token = bin2hex( random_bytes( 8 ) );
		set_transient( self::result_key( $token ), $result, HOUR_IN_SECONDS );
		return $token;
	}

	/**
	 * Store a one-time error notice.
	 *
	 * @param string $message Notice.
	 * @return void
	 */
	private static function redirect_with_notice( string $message ): void {
		$token = bin2hex( random_bytes( 8 ) );
		set_transient( self::notice_key( $token ), $message, HOUR_IN_SECONDS );
		self::redirect_to_page( array( 'notice' => $token ) );
	}

	/**
	 * Redirect back to the plugin page.
	 *
	 * @param array $args Query arguments.
	 * @return void
	 */
	private static function redirect_to_page( array $args = array() ): void {
		$url = add_query_arg( array_merge( array( 'page' => self::PAGE_SLUG ), $args ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Return the transient key for a preview owned by the current user.
	 *
	 * @param string $token Preview token.
	 * @return string
	 */
	private static function preview_key( string $token ): string {
		return 'bbpm_preview_' . get_current_user_id() . '_' . $token;
	}

	/**
	 * Return the transient key for a result owned by the current user.
	 *
	 * @param string $token Result token.
	 * @return string
	 */
	private static function result_key( string $token ): string {
		return 'bbpm_result_' . get_current_user_id() . '_' . $token;
	}

	/**
	 * Return the transient key for a notice owned by the current user.
	 *
	 * @param string $token Notice token.
	 * @return string
	 */
	private static function notice_key( string $token ): string {
		return 'bbpm_notice_' . get_current_user_id() . '_' . $token;
	}

	/**
	 * Validate a posted or queried opaque token.
	 *
	 * @param string $key Request key.
	 * @return string
	 */
	private static function posted_token( string $key ): string {
		$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		return self::query_token( $value );
	}

	/**
	 * Validate lowercase hexadecimal token input.
	 *
	 * @param mixed $value Token candidate.
	 * @return string
	 */
	private static function query_token( $value ): string {
		$token = is_string( $value ) ? strtolower( $value ) : '';
		return preg_match( '/^[a-f0-9]{16,32}$/', $token ) ? $token : '';
	}

	/**
	 * Format a non-empty WooCommerce price for admin display.
	 *
	 * @param string $price Price string.
	 * @return string
	 */
	private static function display_price( string $price ): string {
		if ( '' === $price ) {
			return '—';
		}
		return wp_strip_all_tags( wc_price( (float) $price ) );
	}

	/**
	 * Format the current sale schedule in the site's timezone.
	 *
	 * @param mixed $from Sale start timestamp.
	 * @param mixed $to Sale end timestamp.
	 * @return string
	 */
	private static function display_schedule( $from, $to ): string {
		$start = is_numeric( $from ) ? wp_date( 'Y-m-d H:i', (int) $from ) : '';
		$end = is_numeric( $to ) ? wp_date( 'Y-m-d H:i', (int) $to ) : '';
		if ( '' === $start && '' === $end ) {
			return 'بدون زمان‌بندی';
		}
		return 'شروع: ' . ( '' !== $start ? $start : 'بدون محدودیت' ) . '؛ پایان: ' . ( '' !== $end ? $end : 'بدون محدودیت' );
	}

	/**
	 * Localized operation label.
	 *
	 * @param string $mode Operation key.
	 * @return string
	 */
	private static function operation_label( string $mode ): string {
		if ( PriceManager::MODE_REMOVE_LIFETIME === $mode ) {
			return 'حذف تخفیف مادام‌العمر';
		}
		if ( PriceManager::MODE_PERCENT === $mode ) {
			return 'تخفیف درصدی';
		}
		if ( 'restore_guard' === $mode ) {
			return 'پشتیبان پیش از بازگردانی';
		}
		return 'عملیات نامشخص';
	}

	/**
	 * Localized snapshot state.
	 *
	 * @param string $state State key.
	 * @return string
	 */
	private static function backup_state_label( string $state ): string {
		$labels = array(
			'prepared'  => 'پشتیبان آماده',
			'applying'  => 'در حال اعمال',
			'completed' => 'کامل',
			'partial'   => 'ناقص؛ بررسی شود',
		);
		return $labels[ $state ] ?? 'نامشخص';
	}

	/**
	 * Check current user's WooCommerce administration capability.
	 *
	 * @return bool
	 */
	private static function can_manage(): bool {
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Block unauthorized page and action requests.
	 *
	 * @return void
	 */
	private static function require_capability(): void {
		if ( ! self::can_manage() ) {
			wp_die( esc_html( 'اجازهٔ مدیریت قیمت‌ها را ندارید.' ), esc_html( 'دسترسی غیرمجاز' ), array( 'response' => 403 ) );
		}
	}
}
