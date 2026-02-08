<?php
/**
 * Frontend-Bereich
 */

if (!defined('ABSPATH')) {
	exit;
}

class LTB_Public {

	/**
	 * Konstruktor
	 */
	public function __construct() {
		add_shortcode('lasertagpro_kalender', array($this, 'render_calendar_shortcode'));
		add_action('widgets_init', array($this, 'register_widget'));
		add_action('init', array($this, 'add_rewrite_rules'));
		add_filter('query_vars', array($this, 'add_query_vars'));
		add_action('template_redirect', array($this, 'handle_cancellation'));
		add_action('wp_ajax_ltb_get_available_slots', array($this, 'ajax_get_available_slots'));
		add_action('wp_ajax_nopriv_ltb_get_available_slots', array($this, 'ajax_get_available_slots'));
		add_action('wp_ajax_ltb_get_slot_pricing', array($this, 'ajax_get_slot_pricing'));
		add_action('wp_ajax_nopriv_ltb_get_slot_pricing', array($this, 'ajax_get_slot_pricing'));
		add_action('wp_ajax_ltb_add_to_cart', array($this, 'ajax_add_to_cart'));
		add_action('wp_ajax_nopriv_ltb_add_to_cart', array($this, 'ajax_add_to_cart'));
		add_action('wp_ajax_ltb_remove_from_cart', array($this, 'ajax_remove_from_cart'));
		add_action('wp_ajax_nopriv_ltb_remove_from_cart', array($this, 'ajax_remove_from_cart'));
		add_action('wp_ajax_ltb_get_cart', array($this, 'ajax_get_cart'));
		add_action('wp_ajax_nopriv_ltb_get_cart', array($this, 'ajax_get_cart'));
		add_action('wp_ajax_ltb_create_booking', array($this, 'ajax_create_booking'));
		add_action('wp_ajax_nopriv_ltb_create_booking', array($this, 'ajax_create_booking'));
	}

	/**
	 * Shortcode rendern
	 */
	public function render_calendar_shortcode($atts) {
		$atts = shortcode_atts(array(
			'month' => '',
			'year' => '',
		), $atts);
		
		ob_start();
		include LTB_PLUGIN_DIR . 'public/views/calendar.php';
		return ob_get_clean();
	}

	/**
	 * Widget registrieren
	 */
	public function register_widget() {
		require_once LTB_PLUGIN_DIR . 'public/class-ltb-widget.php';
		register_widget('LTB_Widget');
	}

	/**
	 * Rewrite Rules hinzufügen
	 */
	public function add_rewrite_rules() {
		add_rewrite_rule('^lasertagpro-buchung/?$', 'index.php?ltb_page=booking', 'top');
	}

	/**
	 * Query Vars hinzufügen
	 */
	public function add_query_vars($vars) {
		$vars[] = 'ltb_page';
		return $vars;
	}

	/**
	 * Stornierung verarbeiten
	 */
	public function handle_cancellation() {
		if (isset($_GET['ltb_cancel']) && !empty($_GET['ltb_cancel'])) {
			$token = sanitize_text_field($_GET['ltb_cancel']);
			$result = LTB_Booking::cancel_reservation($token);
			
			if (is_wp_error($result)) {
				wp_die($result->get_error_message());
			}
			
			wp_die(
				__('Ihre Reservierung wurde erfolgreich storniert. Sie erhalten eine Bestätigung per E-Mail.', 'lasertagpro-buchung'),
				__('Reservierung storniert', 'lasertagpro-buchung'),
				array('response' => 200)
			);
		}
	}

	/**
	 * AJAX: Verfügbare Slots abrufen
	 */
	public function ajax_get_available_slots() {
		try {
			check_ajax_referer('ltb_nonce', 'nonce');
			
			$month = isset($_POST['month']) ? absint($_POST['month']) : date('n');
			$year = isset($_POST['year']) ? absint($_POST['year']) : date('Y');
			
			$start_date = date('Y-m-d', mktime(0, 0, 0, $month, 1, $year));
			$end_date = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
			
			$dav_client = new LTB_DAV_Client();
			$slots = $dav_client->get_available_slots($start_date, $end_date);
			
			// NUR DAV-Kalender verwenden, keine Datenbank-Filterung
			// Die Datenbank wird nur für die Speicherung verwendet
			wp_send_json_success($slots);
		} catch (Exception $e) {
			error_log('LTB AJAX Error: ' . $e->getMessage());
			wp_send_json_error(array(
				'message' => __('Fehler beim Laden der Zeiten.', 'lasertagpro-buchung'),
				'error' => $e->getMessage()
			));
		}
	}

	/**
	 * AJAX: Preis für Slot abrufen
	 */
	public function ajax_get_slot_pricing() {
		check_ajax_referer('ltb_nonce', 'nonce');
		
		$date = sanitize_text_field($_POST['date']);
		$game_mode = sanitize_text_field($_POST['game_mode']);
		$person_count = absint($_POST['person_count']);
		$duration = isset($_POST['duration']) ? absint($_POST['duration']) : 1;
		
		$pricing = LTB_Pricing::calculate_slot_price($date, $game_mode, $person_count, $duration);
		
		wp_send_json_success($pricing);
	}

	/**
	 * AJAX: Zum Warenkorb hinzufügen
	 */
	public function ajax_add_to_cart() {
		check_ajax_referer('ltb_nonce', 'nonce');
		
		// Session sicherstellen
		if (!session_id() && !headers_sent()) {
			session_start();
		}
		
		$item = array(
			'booking_date' => sanitize_text_field($_POST['booking_date']),
			'start_time' => sanitize_text_field($_POST['start_time']),
			'booking_duration' => absint($_POST['booking_duration']),
			'game_mode' => sanitize_text_field($_POST['game_mode']),
			'person_count' => absint($_POST['person_count']),
		);
		
		$result = LTB_Cart::add_to_cart($item);
		
		if (!$result) {
			wp_send_json_error(array('message' => __('Fehler beim Hinzufügen zum Warenkorb.', 'lasertagpro-buchung')));
		}
		
		$cart = LTB_Cart::get_cart();
		$cart_total = LTB_Cart::calculate_total();
		
		// Cart als Array konvertieren (für JavaScript)
		$cart_array = array_values($cart);
		
		wp_send_json_success(array(
			'message' => __('Zum Warenkorb hinzugefügt.', 'lasertagpro-buchung'),
			'cart' => $cart_array,
			'total' => $cart_total,
		));
	}

	/**
	 * AJAX: Aus Warenkorb entfernen
	 */
	public function ajax_remove_from_cart() {
		check_ajax_referer('ltb_nonce', 'nonce');
		
		// Session sicherstellen
		if (!session_id() && !headers_sent()) {
			session_start();
		}
		
		$item_id = sanitize_text_field($_POST['item_id']);
		
		$result = LTB_Cart::remove_from_cart($item_id);
		
		if (!$result) {
			wp_send_json_error(array('message' => __('Artikel nicht gefunden.', 'lasertagpro-buchung')));
		}
		
		$cart = LTB_Cart::get_cart();
		$cart_total = LTB_Cart::calculate_total();
		
		// Cart als Array konvertieren (für JavaScript)
		$cart_array = array_values($cart);
		
		wp_send_json_success(array(
			'message' => __('Aus Warenkorb entfernt.', 'lasertagpro-buchung'),
			'cart' => $cart_array,
			'total' => $cart_total,
		));
	}

	/**
	 * AJAX: Warenkorb abrufen
	 */
	public function ajax_get_cart() {
		check_ajax_referer('ltb_nonce', 'nonce');
		
		// Session sicherstellen
		if (!session_id() && !headers_sent()) {
			session_start();
		}
		
		$cart = LTB_Cart::get_cart();
		$cart_total = LTB_Cart::calculate_total();
		
		// Cart als Array konvertieren (für JavaScript)
		$cart_array = array_values($cart);
		
		wp_send_json_success(array(
			'cart' => $cart_array,
			'total' => $cart_total,
		));
	}

	/**
	 * AJAX: Buchung erstellen (aus Warenkorb)
	 */
	public function ajax_create_booking() {
		// Output-Buffer SOFORT starten, bevor irgendetwas ausgegeben wird
		ob_start();
		
		check_ajax_referer('ltb_nonce', 'nonce');
		
		// Session sicherstellen
		if (!session_id() && !headers_sent()) {
			session_start();
		}
		
		$cart = LTB_Cart::get_cart();
		
		if (empty($cart)) {
			ob_end_clean(); // Buffer leeren
			wp_send_json_error(array('message' => __('Warenkorb ist leer.', 'lasertagpro-buchung')));
		}
		
		// Validierung
		if (empty($_POST['name']) || empty($_POST['email'])) {
			ob_end_clean(); // Buffer leeren
			wp_send_json_error(array('message' => __('Bitte füllen Sie alle Pflichtfelder aus.', 'lasertagpro-buchung')));
		}
		
		$name = sanitize_text_field($_POST['name']);
		$email = sanitize_email($_POST['email']);
		$phone = sanitize_text_field($_POST['phone']);
		$message = sanitize_textarea_field($_POST['message']);
		
		// E-Mail-Validierung
		if (!is_email($email)) {
			ob_end_clean(); // Buffer leeren
			wp_send_json_error(array('message' => __('Bitte geben Sie eine gültige E-Mail-Adresse ein.', 'lasertagpro-buchung')));
		}
		
		// Cart als Array konvertieren (falls assoziatives Array)
		$cart = array_values($cart);
		
		$errors = array();
		$success_count = 0;
		
		// Alle Artikel im Warenkorb buchen
		foreach ($cart as $item) {
			$data = array(
				'booking_date' => $item['booking_date'],
				'start_time' => $item['start_time'],
				'booking_duration' => $item['booking_duration'],
				'name' => $name,
				'email' => $email,
				'phone' => $phone,
				'message' => $message,
				'person_count' => $item['person_count'],
				'game_mode' => $item['game_mode'],
			);
			
			$result = LTB_Booking::create_reservation($data);
			
			if (is_wp_error($result)) {
				$errors[] = $result->get_error_message();
			} else {
				$success_count++;
			}
		}
		
		// Alle Outputs sammeln und löschen
		$output = ob_get_clean();
		if (!empty($output)) {
			error_log('LTB AJAX Output (vor JSON-Response): ' . $output);
		}
		
		if ($success_count > 0) {
			LTB_Cart::clear_cart();
			
			wp_send_json_success(array(
				'message' => sprintf(__('%d Reservierungsanfrage(n) erfolgreich übermittelt! Sie erhalten eine E-Mail mit den Details. Eine Bestätigung erhalten Sie, sobald wir Ihre Anfrage bearbeitet haben.', 'lasertagpro-buchung'), $success_count),
				'errors' => $errors,
			));
		} else {
			// Alle Fehlermeldungen zusammenfassen
			$error_message = !empty($errors) ? implode(' ', array_unique($errors)) : __('Fehler bei der Buchung.', 'lasertagpro-buchung');
			
			wp_send_json_error(array('message' => $error_message));
		}
	}
}

