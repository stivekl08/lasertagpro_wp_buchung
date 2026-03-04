<?php
/**
 * Admin-Bereich
 */

if (!defined('ABSPATH')) {
	exit;
}

class LTB_Admin {

	/**
	 * Konstruktor
	 */
	public function __construct() {
		add_action('admin_menu', array($this, 'add_admin_menu'));
		add_action('admin_init', array($this, 'register_settings'));
		add_action('admin_init', array($this, 'add_email_settings_to_general'));
		add_action('admin_post_ltb_confirm_reservation', array($this, 'confirm_reservation'));
		add_action('admin_post_ltb_cancel_reservation', array($this, 'cancel_reservation'));
		add_action('admin_post_ltb_delete_reservation', array($this, 'delete_reservation'));
		add_action('admin_post_ltb_create_reservation', array($this, 'create_reservation'));
		add_action('admin_post_ltb_export_reservations', array($this, 'export_reservations'));
		add_action('admin_post_ltb_import_reservations', array($this, 'import_reservations'));
		add_action('admin_post_ltb_sync_reservations', array($this, 'sync_reservations'));
		add_action('admin_post_ltb_block_date', array($this, 'block_date'));
		add_action('admin_post_ltb_unblock_date', array($this, 'unblock_date'));
		add_action('admin_post_ltb_reserve_full_day', array($this, 'reserve_full_day'));
	}

	/**
	 * Admin-Menü hinzufügen
	 */
	public function add_admin_menu() {
		add_menu_page(
			__('LaserTagPro Buchung', 'lasertagpro-buchung'),
			__('LaserTagPro', 'lasertagpro-buchung'),
			'manage_options',
			'lasertagpro-buchung',
			array($this, 'render_dashboard'),
			'dashicons-calendar-alt',
			30
		);
		
		add_submenu_page(
			'lasertagpro-buchung',
			__('Reservierungen', 'lasertagpro-buchung'),
			__('Reservierungen', 'lasertagpro-buchung'),
			'manage_options',
			'ltb-reservations',
			array($this, 'render_reservations')
		);
		
		add_submenu_page(
			'lasertagpro-buchung',
			__('Einstellungen', 'lasertagpro-buchung'),
			__('Einstellungen', 'lasertagpro-buchung'),
			'manage_options',
			'ltb-settings',
			array($this, 'render_settings')
		);
	}

	/**
	 * Einstellungen registrieren
	 */
	public function register_settings() {
		register_setting('ltb_settings', 'ltb_dav_url');
		register_setting('ltb_settings', 'ltb_dav_username');
		register_setting('ltb_settings', 'ltb_dav_password');
		register_setting('ltb_settings', 'ltb_start_hour');
		register_setting('ltb_settings', 'ltb_end_hour');
		
		register_setting('ltb_settings', 'ltb_min_players');
		register_setting('ltb_settings', 'ltb_max_players', array(
			'type' => 'integer',
			'sanitize_callback' => function($value) {
				$value = absint($value);
				return $value === 0 ? 0 : $value; // 0 = unbegrenzt
			},
		));
		register_setting('ltb_settings', 'ltb_inquiry_threshold', array(
			'type' => 'integer',
			'sanitize_callback' => function($value) {
				$value = absint($value);
				return $value === 0 ? 0 : $value; // 0 = deaktiviert
			},
		));
		
		register_setting('ltb_settings', 'ltb_gotify_enabled');
		register_setting('ltb_settings', 'ltb_gotify_url');
		register_setting('ltb_settings', 'ltb_gotify_token');
		register_setting('ltb_settings', 'ltb_telegram_enabled');
		register_setting('ltb_settings', 'ltb_telegram_bot_token');
		register_setting('ltb_settings', 'ltb_telegram_chat_id');

		$price_sanitize = function($value) {
			$value = floatval(str_replace(',', '.', $value));
			return max(0, round($value, 2));
		};
		register_setting('ltb_settings', 'ltb_price_1h', array('sanitize_callback' => $price_sanitize));
		register_setting('ltb_settings', 'ltb_price_2h', array('sanitize_callback' => $price_sanitize));
		register_setting('ltb_settings', 'ltb_price_3h', array('sanitize_callback' => $price_sanitize));
	}

	/**
	 * E-Mail-Einstellungen zu WordPress General Settings hinzufügen
	 */
	public function add_email_settings_to_general() {
		// E-Mail-Einstellungen zu General Settings hinzufügen
		add_settings_section(
			'ltb_email_settings',
			__('LaserTagPro Buchung - E-Mail-Einstellungen', 'lasertagpro-buchung'),
			array($this, 'email_settings_section_callback'),
			'general'
		);

		add_settings_field(
			'ltb_email_from',
			__('Absender-E-Mail', 'lasertagpro-buchung'),
			array($this, 'email_from_field_callback'),
			'general',
			'ltb_email_settings'
		);

		add_settings_field(
			'ltb_email_from_name',
			__('Absender-Name', 'lasertagpro-buchung'),
			array($this, 'email_from_name_field_callback'),
			'general',
			'ltb_email_settings'
		);

		register_setting('general', 'ltb_email_from');
		register_setting('general', 'ltb_email_from_name');
	}

	/**
	 * E-Mail-Einstellungen Sektion Callback
	 */
	public function email_settings_section_callback() {
		echo '<p>' . esc_html__('E-Mail-Einstellungen für Buchungsbestätigungen und Stornierungen.', 'lasertagpro-buchung') . '</p>';
	}

	/**
	 * Absender-E-Mail Feld Callback
	 */
	public function email_from_field_callback() {
		$value = get_option('ltb_email_from', get_option('admin_email'));
		echo '<input type="email" name="ltb_email_from" value="' . esc_attr($value) . '" class="regular-text">';
		echo '<p class="description">' . esc_html__('E-Mail-Adresse, die als Absender für Buchungsbestätigungen verwendet wird.', 'lasertagpro-buchung') . '</p>';
	}

	/**
	 * Absender-Name Feld Callback
	 */
	public function email_from_name_field_callback() {
		$value = get_option('ltb_email_from_name', get_bloginfo('name'));
		echo '<input type="text" name="ltb_email_from_name" value="' . esc_attr($value) . '" class="regular-text">';
		echo '<p class="description">' . esc_html__('Name, der als Absender für Buchungsbestätigungen verwendet wird.', 'lasertagpro-buchung') . '</p>';
	}

	/**
	 * Dashboard rendern
	 */
	public function render_dashboard() {
		global $wpdb;
		
		$reservations_table = $wpdb->prefix . 'ltb_reservations';
		
		$today = date('Y-m-d');
		$upcoming = $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM $reservations_table WHERE booking_date >= %s AND status != 'cancelled'",
			$today
		));
		
		$pending = $wpdb->get_var("SELECT COUNT(*) FROM $reservations_table WHERE status = 'pending'");
		$confirmed = $wpdb->get_var("SELECT COUNT(*) FROM $reservations_table WHERE status = 'confirmed'");
		$cancelled = $wpdb->get_var("SELECT COUNT(*) FROM $reservations_table WHERE status = 'cancelled'");
		
		include LTB_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Reservierungen rendern
	 */
	public function render_reservations() {
		$status    = isset($_GET['status'])    ? sanitize_text_field($_GET['status'])    : '';
		$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
		$date_to   = isset($_GET['date_to'])   ? sanitize_text_field($_GET['date_to'])   : '';
		$paged     = isset($_GET['paged'])     ? max(1, absint($_GET['paged']))           : 1;
		$per_page  = 25;

		$filter_args = array(
			'status'    => $status,
			'date_from' => $date_from,
			'date_to'   => $date_to,
		);

		$total_count = LTB_Booking::count_reservations($filter_args);
		$total_pages = max(1, (int) ceil($total_count / $per_page));
		$paged       = min($paged, $total_pages);

		$reservations = LTB_Booking::get_reservations(array_merge($filter_args, array(
			'orderby' => 'booking_date',
			'order'   => 'ASC',
			'limit'   => $per_page,
			'offset'  => ($paged - 1) * $per_page,
		)));

		include LTB_PLUGIN_DIR . 'admin/views/reservations.php';
	}

	/**
	 * Einstellungen rendern
	 */
	public function render_settings() {
		include LTB_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Reservierung bestätigen
	 */
	public function confirm_reservation() {
		if (!current_user_can('manage_options')) {
			wp_die(__('Sie haben keine Berechtigung für diese Aktion.', 'lasertagpro-buchung'));
		}
		
		check_admin_referer('ltb_confirm_reservation');
		
		$id = absint($_GET['id']);
		
		global $wpdb;
		$table = $wpdb->prefix . 'ltb_reservations';
		
		$wpdb->update(
			$table,
			array('status' => 'confirmed'),
			array('id' => $id),
			array('%s'),
			array('%d')
		);
		
		// Bestätigungs-E-Mail senden
		LTB_Email::send_booking_confirmation($id);
		
		wp_redirect(add_query_arg(array('page' => 'ltb-reservations', 'confirmed' => '1'), admin_url('admin.php')));
		exit;
	}

	/**
	 * Reservierung stornieren
	 */
	public function cancel_reservation() {
		if (!current_user_can('manage_options')) {
			wp_die(__('Sie haben keine Berechtigung für diese Aktion.', 'lasertagpro-buchung'));
		}
		
		check_admin_referer('ltb_cancel_reservation');
		
		$id = absint($_GET['id']);
		
		$result = LTB_Booking::cancel_reservation_by_id($id);
		
		if (is_wp_error($result)) {
			wp_redirect(add_query_arg(array('page' => 'ltb-reservations', 'error' => urlencode($result->get_error_message())), admin_url('admin.php')));
		} else {
			wp_redirect(add_query_arg(array('page' => 'ltb-reservations', 'cancelled' => '1'), admin_url('admin.php')));
		}
		exit;
	}

	/**
	 * Reservierung löschen
	 */
	public function delete_reservation() {
		if (!current_user_can('manage_options')) {
			wp_die(__('Sie haben keine Berechtigung für diese Aktion.', 'lasertagpro-buchung'));
		}
		
		check_admin_referer('ltb_delete_reservation');
		
		$id = absint($_GET['id']);

		global $wpdb;
		$table = $wpdb->prefix . 'ltb_reservations';

		// Reservierung vor dem Löschen abrufen (für Kalender-Cleanup)
		$reservation = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

		$wpdb->delete($table, array('id' => $id), array('%d'));

		// DAV-Kalender bereinigen: BELEGT-Event löschen + FREI-Slots wiederherstellen
		// (nur wenn Reservierung nicht bereits storniert war – stornieren hat das schon erledigt)
		if ($reservation && $reservation->status !== 'cancelled') {
			$dav_client = new LTB_DAV_Client();
			$dav_client->cancel_reservation_in_calendar(
				$reservation->booking_date,
				$reservation->start_time,
				$reservation->booking_duration
			);

			// ltb_blocked_dates bereinigen wenn nötig
			$date = substr($reservation->booking_date, 0, 10);
			$remaining = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE booking_date LIKE %s AND status != 'cancelled'",
				$date . '%'
			));
			if ($remaining == 0) {
				$blocked = get_option('ltb_blocked_dates', array());
				$new = array_values(array_diff($blocked, array($date)));
				if (count($new) !== count($blocked)) {
					update_option('ltb_blocked_dates', $new);
				}
			}
		}

		wp_redirect(add_query_arg(array('page' => 'ltb-reservations', 'deleted' => '1'), admin_url('admin.php')));
		exit;
	}

	/**
	 * Reservierung manuell erstellen
	 */
	public function create_reservation() {
		if (!current_user_can('manage_options')) {
			wp_die(__('Sie haben keine Berechtigung für diese Aktion.', 'lasertagpro-buchung'));
		}
		
		check_admin_referer('ltb_create_reservation');
		
		$data = array(
			'booking_date' => sanitize_text_field($_POST['booking_date']),
			'start_time' => sanitize_text_field($_POST['start_time']) . ':00',
			'booking_duration' => absint($_POST['booking_duration']),
			'name' => sanitize_text_field($_POST['name']),
			'email' => sanitize_email($_POST['email']),
			'phone' => sanitize_text_field($_POST['phone']),
			'message' => sanitize_textarea_field($_POST['message']),
			'person_count' => absint($_POST['person_count']),
			'game_mode' => sanitize_text_field($_POST['game_mode']),
		);
		
		$result = LTB_Booking::create_reservation($data, false);
		
		if (is_wp_error($result)) {
			wp_redirect(add_query_arg(array('page' => 'ltb-reservations', 'error' => urlencode($result->get_error_message())), admin_url('admin.php')));
		} else {
			wp_redirect(add_query_arg(array('page' => 'ltb-reservations', 'created' => '1'), admin_url('admin.php')));
		}
		exit;
	}

	/**
	 * Reservierungen exportieren
	 */
	public function export_reservations() {
		if (!current_user_can('manage_options')) {
			wp_die(__('Sie haben keine Berechtigung für diese Aktion.', 'lasertagpro-buchung'));
		}
		
		check_admin_referer('ltb_export_reservations');
		
		$format = isset($_GET['format']) ? sanitize_text_field($_GET['format']) : 'csv';
		$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
		$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
		$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
		
		$args = array(
			'status' => $status,
			'date_from' => $date_from,
			'date_to' => $date_to,
		);
		
		if ($format === 'json') {
			LTB_Export_Import::export_reservations_json($args);
		} else {
			LTB_Export_Import::export_reservations_csv($args);
		}
	}

	/**
	 * Reservierungen importieren
	 */
	public function import_reservations() {
		if (!current_user_can('manage_options')) {
			wp_die(__('Sie haben keine Berechtigung für diese Aktion.', 'lasertagpro-buchung'));
		}
		
		check_admin_referer('ltb_import_reservations');
		
		if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
			wp_redirect(add_query_arg(array(
				'page' => 'ltb-reservations',
				'error' => urlencode(__('Fehler beim Hochladen der Datei.', 'lasertagpro-buchung'))
			), admin_url('admin.php')));
			exit;
		}
		
		$file = $_FILES['import_file'];
		$file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		
		if (!in_array($file_ext, array('csv', 'json'))) {
			wp_redirect(add_query_arg(array(
				'page' => 'ltb-reservations',
				'error' => urlencode(__('Ungültiges Dateiformat. Nur CSV und JSON werden unterstützt.', 'lasertagpro-buchung'))
			), admin_url('admin.php')));
			exit;
		}
		
		$upload_dir = wp_upload_dir();
		$import_dir = $upload_dir['basedir'] . '/ltb-imports';
		
		if (!file_exists($import_dir)) {
			wp_mkdir_p($import_dir);
		}
		
		$file_path = $import_dir . '/' . sanitize_file_name($file['name']);
		
		if (!move_uploaded_file($file['tmp_name'], $file_path)) {
			wp_redirect(add_query_arg(array(
				'page' => 'ltb-reservations',
				'error' => urlencode(__('Fehler beim Speichern der Datei.', 'lasertagpro-buchung'))
			), admin_url('admin.php')));
			exit;
		}
		
		$options = array(
			'skip_duplicates' => isset($_POST['skip_duplicates']) && $_POST['skip_duplicates'] === '1',
			'update_existing' => isset($_POST['update_existing']) && $_POST['update_existing'] === '1',
			'validate_data' => isset($_POST['validate_data']) && $_POST['validate_data'] === '1',
		);
		
		if ($file_ext === 'json') {
			$result = LTB_Export_Import::import_reservations_json($file_path, $options);
		} else {
			$result = LTB_Export_Import::import_reservations_csv($file_path, $options);
		}
		
		// Datei löschen
		@unlink($file_path);
		
		// Ergebnis als Query-Parameter übergeben
		$query_args = array(
			'page' => 'ltb-reservations',
			'imported' => '1',
			'success' => $result['success'],
			'errors' => $result['errors'],
			'skipped' => $result['skipped'],
			'updated' => $result['updated'],
		);
		
		if (!empty($result['messages'])) {
			$query_args['messages'] = urlencode(implode(' | ', array_slice($result['messages'], 0, 10)));
		}
		
		wp_redirect(add_query_arg($query_args, admin_url('admin.php')));
		exit;
	}

	/**
	 * Ganztägige Reservierung erstellen
	 */
	public function reserve_full_day() {
		if (!current_user_can('manage_options')) {
			wp_die(__('Sie haben keine Berechtigung für diese Aktion.', 'lasertagpro-buchung'));
		}

		check_admin_referer('ltb_reserve_full_day');

		$date = sanitize_text_field($_POST['reserve_date']);
		$name = sanitize_text_field($_POST['reserve_name']);

		if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
			wp_redirect(add_query_arg(array('page' => 'ltb-reservations', 'error' => urlencode(__('Ungültiges Datum.', 'lasertagpro-buchung'))), admin_url('admin.php')));
			exit;
		}

		if (empty($name)) {
			$name = __('Ganztägige Reservierung', 'lasertagpro-buchung');
		}

		$start_hour = absint(get_option('ltb_start_hour', 9));
		$end_hour   = absint(get_option('ltb_end_hour', 21));
		$duration   = max(1, $end_hour - $start_hour);

		$start_time = sprintf('%02d:00:00', $start_hour);
		$end_time   = sprintf('%02d:00:00', $end_hour);

		global $wpdb;
		$table = $wpdb->prefix . 'ltb_reservations';
		$token = bin2hex(random_bytes(32));

		$result = $wpdb->insert($table, array(
			'booking_date'     => $date,
			'booking_duration' => $duration,
			'start_time'       => $start_time,
			'end_time'         => $end_time,
			'name'             => $name,
			'email'            => '',
			'phone'            => '',
			'message'          => '',
			'person_count'     => 1,
			'game_mode'        => __('Ganztägig', 'lasertagpro-buchung'),
			'status'           => 'confirmed',
			'confirmation_token' => $token,
		));

		if ($result === false) {
			wp_redirect(add_query_arg(array('page' => 'ltb-reservations', 'error' => urlencode(__('Fehler beim Speichern der Reservierung.', 'lasertagpro-buchung'))), admin_url('admin.php')));
			exit;
		}

		// DAV-Event für den ganzen Tag erstellen
		$dav_client = new LTB_DAV_Client();
		$summary = 'BELEGT - ' . $name . ' (ganztägig)';
		$dav_client->create_event($date, $start_time, $duration, $summary);

		// Tag auch in blocked_dates eintragen, damit Frontend keine Slots zeigt
		$blocked_dates = get_option('ltb_blocked_dates', array());
		if (!in_array($date, $blocked_dates)) {
			$blocked_dates[] = $date;
			sort($blocked_dates);
			update_option('ltb_blocked_dates', $blocked_dates);
		}

		wp_redirect(add_query_arg(array('page' => 'ltb-reservations', 'reserved_day' => '1'), admin_url('admin.php')));
		exit;
	}

	/**
	 * Tag sperren
	 */
	public function block_date() {
		if (!current_user_can('manage_options')) {
			wp_die(__('Sie haben keine Berechtigung für diese Aktion.', 'lasertagpro-buchung'));
		}

		check_admin_referer('ltb_block_date');

		$date = sanitize_text_field($_POST['block_date']);

		if (!empty($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
			$blocked_dates = get_option('ltb_blocked_dates', array());
			if (!in_array($date, $blocked_dates)) {
				$blocked_dates[] = $date;
				sort($blocked_dates);
				update_option('ltb_blocked_dates', $blocked_dates);
			}
		}

		wp_redirect(add_query_arg(array('page' => 'ltb-reservations', 'blocked_date' => '1'), admin_url('admin.php')));
		exit;
	}

	/**
	 * Tag entsperren
	 */
	public function unblock_date() {
		if (!current_user_can('manage_options')) {
			wp_die(__('Sie haben keine Berechtigung für diese Aktion.', 'lasertagpro-buchung'));
		}

		check_admin_referer('ltb_unblock_date');

		$date = sanitize_text_field($_GET['date']);
		$blocked_dates = get_option('ltb_blocked_dates', array());
		$blocked_dates = array_values(array_diff($blocked_dates, array($date)));
		update_option('ltb_blocked_dates', $blocked_dates);

		wp_redirect(add_query_arg(array('page' => 'ltb-reservations', 'unblocked_date' => '1'), admin_url('admin.php')));
		exit;
	}

	/**
	 * Reservierungen mit Kalender synchronisieren
	 */
	public function sync_reservations() {
		if (!current_user_can('manage_options')) {
			wp_die(__('Sie haben keine Berechtigung für diese Aktion.', 'lasertagpro-buchung'));
		}
		
		check_admin_referer('ltb_sync_reservations');
		
		$sync_direction = isset($_POST['sync_direction']) ? sanitize_text_field($_POST['sync_direction']) : 'to_calendar';
		$date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
		$date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
		$status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
		$create_missing = isset($_POST['create_missing']) && $_POST['create_missing'] === '1';
		$update_existing = isset($_POST['update_existing']) && $_POST['update_existing'] === '1';
		
		$options = array(
			'date_from' => $date_from,
			'date_to' => $date_to,
			'status' => $status,
			'create_missing' => $create_missing,
			'update_existing' => $update_existing,
		);
		
		if ($sync_direction === 'from_calendar') {
			$result = LTB_Sync::sync_calendar_to_reservations($options);
		} else {
			$result = LTB_Sync::sync_reservations_to_calendar($options);
		}
		
		$query_args = array(
			'page' => 'ltb-reservations',
			'synced' => '1',
			'direction' => $sync_direction,
		);
		
		if ($sync_direction === 'to_calendar') {
			$query_args['created'] = $result['created'];
			$query_args['updated'] = $result['updated'];
			$query_args['errors'] = $result['errors'];
			$query_args['skipped'] = $result['skipped'];
		} else {
			$query_args['found'] = $result['found'];
			$query_args['created'] = $result['created'];
			$query_args['errors'] = $result['errors'];
		}
		
		if (!empty($result['messages'])) {
			$query_args['messages'] = urlencode(implode(' | ', array_slice($result['messages'], 0, 10)));
		}
		
		wp_redirect(add_query_arg($query_args, admin_url('admin.php')));
		exit;
	}
}

