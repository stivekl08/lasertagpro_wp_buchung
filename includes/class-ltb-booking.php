<?php
/**
 * Buchungs-Verwaltung
 */

if (!defined('ABSPATH')) {
	exit;
}

class LTB_Booking {

	/**
	 * Reservierung erstellen
	 *
	 * @param array $data Buchungsdaten
	 * @return int|WP_Error Reservierungs-ID oder Fehler
	 */
	public static function create_reservation($data) {
		// Output-Buffer sicherstellen (falls nicht bereits aktiv)
		$buffer_started = ob_get_level() > 0;
		if (!$buffer_started) {
			ob_start();
		}
		
		global $wpdb;
		
		$table = $wpdb->prefix . 'ltb_reservations';
		
		// Validierung
		$errors = self::validate_booking_data($data);
		if (!empty($errors)) {
			return new WP_Error('validation_error', implode(', ', $errors));
		}
		
		// Verfügbarkeit prüfen (NUR DAV-Kalender)
		// Die Datenbank wird nur für die Speicherung verwendet, nicht für die Verfügbarkeitsprüfung
		$is_available = self::check_availability($data['booking_date'], $data['start_time'], $data['booking_duration']);
		if (!$is_available) {
			return new WP_Error('not_available', __('Der gewählte Termin ist nicht mehr verfügbar.', 'lasertagpro-buchung'));
		}
		
		// Endzeit berechnen
		$start_datetime = $data['booking_date'] . ' ' . $data['start_time'];
		$start_obj = new DateTime($start_datetime);
		$end_obj = clone $start_obj;
		$end_obj->modify('+' . $data['booking_duration'] . ' hours');
		$end_time = $end_obj->format('H:i:s');
		
		// Confirmation Token generieren
		$token = bin2hex(random_bytes(32));
		
		// Preis berechnen (mit Dauer)
		$duration = isset($data['booking_duration']) ? absint($data['booking_duration']) : 1;
		$pricing = LTB_Pricing::calculate_slot_price(
			$data['booking_date'],
			$data['game_mode'],
			$data['person_count'],
			$duration
		);
		
		// Daten vorbereiten
		$insert_data = array(
			'booking_date' => sanitize_text_field($data['booking_date']),
			'booking_duration' => absint($data['booking_duration']),
			'start_time' => sanitize_text_field($data['start_time']),
			'end_time' => $end_time,
			'name' => sanitize_text_field($data['name']),
			'email' => sanitize_email($data['email']),
			'phone' => sanitize_text_field($data['phone']),
			'message' => sanitize_textarea_field($data['message']),
			'person_count' => absint($data['person_count']),
			'game_mode' => sanitize_text_field($data['game_mode']),
			'price_per_person' => $pricing['price_per_person'],
			'total_price' => $pricing['total_price'],
			'status' => 'pending',
			'confirmation_token' => $token,
		);
		
		// Volumenrabatt berechnen (wenn mehrere Buchungen im Warenkorb)
		$cart = LTB_Cart::get_cart();
		$game_count = count($cart) + 1; // +1 für aktuelle Buchung
		$volume_discount = LTB_Pricing::calculate_volume_discount($game_count);
		
		if ($volume_discount['discount_percent'] > 0) {
			$discount_data = LTB_Pricing::apply_discount($pricing['total_price'], $volume_discount['discount_percent']);
			$insert_data['discount_percent'] = $volume_discount['discount_percent'];
			$insert_data['discount_amount'] = $discount_data['discount_amount'];
			$insert_data['total_price'] = $discount_data['final_price'];
		}
		
		// Promo-Code verarbeiten, falls vorhanden
		if (!empty($data['promo_code'])) {
			$price_after_volume = $insert_data['total_price'];
			$promo_result = LTB_Pricing::validate_promo_code($data['promo_code'], $price_after_volume);
			if (!is_wp_error($promo_result)) {
				$insert_data['promo_code'] = $promo_result['code'];
				$insert_data['discount_amount'] = ($insert_data['discount_amount'] ?? 0) + $promo_result['discount_amount'];
				$insert_data['total_price'] = $promo_result['final_price'];
				LTB_Pricing::increment_promo_usage($promo_result['promo_id']);
			}
		}
		
		$result = $wpdb->insert($table, $insert_data);
		
		if ($result === false) {
			return new WP_Error('db_error', __('Fehler beim Speichern der Reservierung.', 'lasertagpro-buchung'));
		}
		
		$reservation_id = $wpdb->insert_id;
		
		// Event im DAV-Kalender erstellen
		$dav_client = new LTB_DAV_Client();
		$summary = 'BELEGT - ' . sanitize_text_field($data['name']) . ' (' . absint($data['person_count']) . ' Personen)';
		$dav_result = $dav_client->create_event(
			$data['booking_date'],
			$data['start_time'],
			$data['booking_duration'],
			$summary
		);
		
		if (is_wp_error($dav_result)) {
			// Fehler beim Erstellen des Kalender-Events loggen, aber Buchung trotzdem speichern
			error_log('LTB Booking: Fehler beim Erstellen des DAV-Events: ' . $dav_result->get_error_message());
		}
		
		// Reservierungsanfrage-E-Mail senden (nicht Bestätigung!)
		// E-Mail-Versand verzögern, damit er nicht die AJAX-Response blockiert
		// Verwende wp_schedule_single_event für asynchronen Versand
		if (!wp_next_scheduled('ltb_send_booking_request_email', array($reservation_id))) {
			wp_schedule_single_event(time() + 1, 'ltb_send_booking_request_email', array($reservation_id));
		}
		
		// Output-Buffer leeren (falls wir ihn gestartet haben)
		if (!$buffer_started) {
			ob_end_clean();
		}
		
		return $reservation_id;
	}

	/**
	 * Buchungsdaten validieren
	 *
	 * @param array $data Buchungsdaten
	 * @return array Array von Fehlermeldungen
	 */
	private static function validate_booking_data($data) {
		$errors = array();
		
		if (empty($data['booking_date'])) {
			$errors[] = __('Bitte wählen Sie ein Datum.', 'lasertagpro-buchung');
		}
		
		if (empty($data['start_time'])) {
			$errors[] = __('Bitte wählen Sie eine Uhrzeit.', 'lasertagpro-buchung');
		}
		
		if (empty($data['booking_duration']) || $data['booking_duration'] < 1 || $data['booking_duration'] > 3) {
			$errors[] = __('Die Dauer muss zwischen 1 und 3 Stunden liegen.', 'lasertagpro-buchung');
		}
		
		if (empty($data['name'])) {
			$errors[] = __('Bitte geben Sie Ihren Namen ein.', 'lasertagpro-buchung');
		}
		
		if (empty($data['email']) || !is_email($data['email'])) {
			$errors[] = __('Bitte geben Sie eine gültige E-Mail-Adresse ein.', 'lasertagpro-buchung');
		}
		
		if (empty($data['person_count']) || $data['person_count'] < 1) {
			$errors[] = __('Bitte geben Sie die Anzahl der Personen an.', 'lasertagpro-buchung');
		}
		
		if (empty($data['game_mode'])) {
			$errors[] = __('Bitte wählen Sie einen Spielmodus.', 'lasertagpro-buchung');
		}
		
		return $errors;
	}

	/**
	 * Verfügbarkeit prüfen (nur Datenbank - für schnelle Prüfung)
	 *
	 * @param string $date Datum (Y-m-d)
	 * @param string $start_time Startzeit (H:i:s)
	 * @param int $duration Dauer in Stunden
	 * @return bool Verfügbar
	 */
	public static function check_availability_db_only($date, $start_time, $duration) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'ltb_reservations';
		
		// Start- und Endzeit berechnen
		$start_datetime = $date . ' ' . $start_time;
		$start_obj = new DateTime($start_datetime);
		$end_obj = clone $start_obj;
		$end_obj->modify('+' . $duration . ' hours');
		$end_time = $end_obj->format('H:i:s');
		
		// Prüfen ob bereits Reservierungen existieren (Überschneidungen)
		$query = $wpdb->prepare(
			"SELECT COUNT(*) FROM $table 
			WHERE booking_date = %s 
			AND status != 'cancelled'
			AND (
				(start_time < %s AND end_time > %s) OR
				(start_time < %s AND end_time > %s) OR
				(start_time >= %s AND start_time < %s)
			)",
			$date,
			$end_time,
			$start_time,
			$start_time,
			$end_time,
			$start_time,
			$end_time
		);
		
		$count = $wpdb->get_var($query);
		
		return $count === 0;
	}

	/**
	 * Verfügbarkeit prüfen (NUR DAV-Kalender)
	 *
	 * @param string $date Datum (Y-m-d)
	 * @param string $start_time Startzeit (H:i:s)
	 * @param int $duration Dauer in Stunden
	 * @return bool Verfügbar
	 */
	public static function check_availability($date, $start_time, $duration) {
		// NUR DAV-Kalender prüfen (Datenbank wird ignoriert)
		$dav_client = new LTB_DAV_Client();
		$available_slots = $dav_client->get_available_slots($date, $date);
		
		// Benötigte Slots prüfen
		$required_slots = $duration;
		$available_count = 0;
		
		$start_hour = (int) date('G', strtotime($start_time));
		
		for ($i = 0; $i < $duration; $i++) {
			$check_hour = $start_hour + $i;
			
			foreach ($available_slots as $slot) {
				if ($slot['date'] === $date && $slot['hour'] === $check_hour) {
					$available_count++;
					break;
				}
			}
		}
		
		return $available_count >= $required_slots;
	}

	/**
	 * Reservierung stornieren (per Token)
	 *
	 * @param string $token Bestätigungstoken
	 * @return bool|WP_Error Erfolg oder Fehler
	 */
	public static function cancel_reservation($token) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'ltb_reservations';
		
		$reservation = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM $table WHERE confirmation_token = %s AND status != 'cancelled'",
			$token
		));
		
		if (!$reservation) {
			return new WP_Error('not_found', __('Reservierung nicht gefunden.', 'lasertagpro-buchung'));
		}
		
		return self::cancel_reservation_by_id($reservation->id);
	}

	/**
	 * Reservierung stornieren (per ID)
	 *
	 * @param int $id Reservierungs-ID
	 * @return bool|WP_Error Erfolg oder Fehler
	 */
	public static function cancel_reservation_by_id($id) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'ltb_reservations';
		
		$reservation = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM $table WHERE id = %d AND status != 'cancelled'",
			$id
		));
		
		if (!$reservation) {
			return new WP_Error('not_found', __('Reservierung nicht gefunden oder bereits storniert.', 'lasertagpro-buchung'));
		}
		
		$result = $wpdb->update(
			$table,
			array('status' => 'cancelled'),
			array('id' => $id),
			array('%s'),
			array('%d')
		);
		
		if ($result === false) {
			return new WP_Error('db_error', __('Fehler beim Stornieren.', 'lasertagpro-buchung'));
		}
		
		// E-Mail senden
		LTB_Email::send_cancellation_confirmation($id);
		
		return true;
	}

	/**
	 * Reservierung abrufen
	 *
	 * @param int $id Reservierungs-ID
	 * @return object|null Reservierung
	 */
	public static function get_reservation($id) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'ltb_reservations';
		
		return $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM $table WHERE id = %d",
			$id
		));
	}

	/**
	 * Alle Reservierungen abrufen
	 *
	 * @param array $args Filter-Argumente
	 * @return array Reservierungen
	 */
	public static function get_reservations($args = array()) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'ltb_reservations';
		
		$defaults = array(
			'status' => '',
			'date_from' => '',
			'date_to' => '',
			'limit' => -1,
			'offset' => 0,
			'orderby' => 'booking_date',
			'order' => 'ASC',
		);
		
		$args = wp_parse_args($args, $defaults);
		
		$where = array('1=1');
		$where_values = array();
		
		if (!empty($args['status'])) {
			$where[] = 'status = %s';
			$where_values[] = $args['status'];
		}
		
		if (!empty($args['date_from'])) {
			$where[] = 'booking_date >= %s';
			$where_values[] = $args['date_from'];
		}
		
		if (!empty($args['date_to'])) {
			$where[] = 'booking_date <= %s';
			$where_values[] = $args['date_to'];
		}
		
		$where_clause = implode(' AND ', $where);
		
		if (!empty($where_values)) {
			$where_clause = $wpdb->prepare($where_clause, $where_values);
		}
		
		$orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
		if (!$orderby) {
			$orderby = 'booking_date ASC';
		}
		
		$limit = '';
		if ($args['limit'] > 0) {
			$limit = $wpdb->prepare('LIMIT %d OFFSET %d', $args['limit'], $args['offset']);
		}
		
		$query = "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $limit";
		
		return $wpdb->get_results($query);
	}
}

