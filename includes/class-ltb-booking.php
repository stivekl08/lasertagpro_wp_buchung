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
	public static function create_reservation($data, $require_email = true) {
		// Output-Buffer sicherstellen (falls nicht bereits aktiv)
		$buffer_started = ob_get_level() > 0;
		if (!$buffer_started) {
			ob_start();
		}

		global $wpdb;

		$table = $wpdb->prefix . 'ltb_reservations';

		// Validierung
		$errors = self::validate_booking_data($data, $require_email);
		if (!empty($errors)) {
			return new WP_Error('validation_error', implode(', ', $errors));
		}

		// Vorab-Verfügbarkeitsprüfung (DAV + DB)
		$is_available = self::check_availability($data['booking_date'], $data['start_time'], $data['booking_duration']);
		if (!$is_available) {
			return new WP_Error('not_available', __('Der gewählte Termin ist nicht mehr verfügbar.', 'lasertagpro-buchung'));
		}

		// Advisory Lock gegen Race Conditions bei gleichzeitigen Buchungen
		$lock_key = 'ltb_' . md5($data['booking_date'] . '_' . $data['start_time']);
		$lock_acquired = $wpdb->get_var($wpdb->prepare("SELECT GET_LOCK(%s, 10)", $lock_key));

		if ($lock_acquired !== '1') {
			return new WP_Error('lock_failed', __('Der Termin wird gerade gebucht. Bitte versuchen Sie es in einem Moment erneut.', 'lasertagpro-buchung'));
		}

		// DB-Verfügbarkeitsprüfung innerhalb des Locks (verhindert Race Condition)
		if (!self::check_availability_db_only($data['booking_date'], $data['start_time'], $data['booking_duration'])) {
			$wpdb->query($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_key));
			return new WP_Error('not_available', __('Der Termin wurde soeben von jemand anderem gebucht. Bitte wählen Sie einen anderen Termin.', 'lasertagpro-buchung'));
		}

		// Endzeit berechnen
		$start_datetime = $data['booking_date'] . ' ' . $data['start_time'];
		$start_obj = new DateTime($start_datetime);
		$end_obj = clone $start_obj;
		$end_obj->modify('+' . $data['booking_duration'] . ' hours');
		$end_time = $end_obj->format('H:i:s');

		// Confirmation Token generieren
		$token = bin2hex(random_bytes(32));

		// Preis serverseitig berechnen (niemals vom Frontend übernehmen)
		$duration = isset($data['booking_duration']) ? absint($data['booking_duration']) : 1;
		$pricing = LTB_Pricing::calculate_slot_price(
			$data['booking_date'],
			$data['game_mode'],
			$data['person_count'],
			$duration
		);

		// Daten vorbereiten
		$insert_data = array(
			'booking_date'      => sanitize_text_field($data['booking_date']),
			'booking_duration'  => absint($data['booking_duration']),
			'start_time'        => sanitize_text_field($data['start_time']),
			'end_time'          => $end_time,
			'name'              => sanitize_text_field($data['name']),
			'email'             => sanitize_email($data['email']),
			'phone'             => sanitize_text_field($data['phone']),
			'message'           => sanitize_textarea_field($data['message']),
			'person_count'      => absint($data['person_count']),
			'game_mode'         => sanitize_text_field($data['game_mode']),
			'price_per_person'  => $pricing['price_per_person'],
			'total_price'       => $pricing['total_price'],
			'status'            => 'pending',
			'confirmation_token' => $token,
		);

		$result = $wpdb->insert($table, $insert_data);

		// Lock sofort nach dem Insert freigeben
		$wpdb->query($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_key));

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
			$error_msg = $dav_result->get_error_message();
			error_log('LTB Booking: Fehler beim Erstellen des DAV-Events für Reservierung #' . $reservation_id . ': ' . $error_msg);
			self::notify_admin_dav_sync_failed($reservation_id, $error_msg);
		}

		// Reservierungsanfrage-E-Mail asynchron via WP-Cron senden
		wp_schedule_single_event(time(), 'ltb_send_booking_email', array($reservation_id));

		return $reservation_id;
	}

	/**
	 * Buchungsdaten validieren
	 *
	 * @param array $data Buchungsdaten
	 * @return array Array von Fehlermeldungen
	 */
	private static function validate_booking_data($data, $require_email = true) {
		$errors = array();

		if (empty($data['booking_date'])) {
			$errors[] = __('Bitte wählen Sie ein Datum.', 'lasertagpro-buchung');
		} else {
			// Gesperrte Tage prüfen
			$blocked_dates = get_option('ltb_blocked_dates', array());
			if (in_array($data['booking_date'], $blocked_dates)) {
				$errors[] = __('Dieser Tag ist gesperrt und kann nicht gebucht werden.', 'lasertagpro-buchung');
			}
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

		if ($require_email && (empty($data['email']) || !is_email($data['email']))) {
			$errors[] = __('Bitte geben Sie eine gültige E-Mail-Adresse ein.', 'lasertagpro-buchung');
		} elseif (!empty($data['email']) && !is_email($data['email'])) {
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

		return (int) $count === 0;
	}

	/**
	 * Verfügbarkeit prüfen (DB + DAV-Kalender)
	 *
	 * Prüft alle benötigten aufeinanderfolgenden Stunden sowohl in der Datenbank
	 * als auch im DAV-Kalender.
	 *
	 * @param string $date Datum (Y-m-d)
	 * @param string $start_time Startzeit (H:i:s)
	 * @param int $duration Dauer in Stunden
	 * @return bool Verfügbar
	 */
	public static function check_availability($date, $start_time, $duration) {
		// DB ist die einzige zuverlässige Quelle für Doppelbuchungs-Prüfung.
		// Die DAV-Verfügbarkeit wurde bereits beim Anzeigen der Slots geprüft;
		// nach der Buchung wird ein BELEGT-Event in DAV erstellt.
		return self::check_availability_db_only($date, $start_time, $duration);
	}

	/**
	 * Admin per E-Mail über fehlgeschlagene DAV-Synchronisation benachrichtigen
	 *
	 * @param int $reservation_id Reservierungs-ID
	 * @param string $error_message Fehlermeldung
	 */
	private static function notify_admin_dav_sync_failed($reservation_id, $error_message) {
		$admin_email = get_option('admin_email');
		$from_email  = get_option('ltb_email_from', $admin_email);
		$from_name   = get_option('ltb_email_from_name', get_bloginfo('name'));

		$subject = sprintf('[LaserTagPro] DAV-Sync fehlgeschlagen – Reservierung #%d', $reservation_id);
		$body = sprintf(
			"Achtung: Der Kalender-Eintrag für Reservierung #%d konnte nicht im DAV-Kalender erstellt werden.\n\n" .
			"Fehlermeldung: %s\n\n" .
			"Die Reservierung wurde in der Datenbank gespeichert, ist aber möglicherweise nicht im Kalender eingetragen.\n" .
			"Bitte prüfen Sie die DAV-Konfiguration und legen Sie den Eintrag ggf. manuell an.",
			$reservation_id,
			$error_message
		);

		$headers = array('From: ' . $from_name . ' <' . $from_email . '>');
		wp_mail($admin_email, $subject, $body, $headers);
	}

	/**
	 * Reservierung stornieren (per Token)
	 *
	 * @param string $token Bestätigungstoken
	 * @return bool|WP_Error Erfolg oder Fehler
	 */
	public static function cancel_reservation($token) {
		global $wpdb;

		// Rate Limiting: max. 10 Versuche pro IP in 15 Minuten
		$ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : 'unknown';
		$rate_key = 'ltb_cancel_attempts_' . md5($ip);
		$attempts = (int) get_transient($rate_key);

		if ($attempts >= 10) {
			return new WP_Error('rate_limit', __('Zu viele Stornierungsversuche. Bitte versuchen Sie es in 15 Minuten erneut.', 'lasertagpro-buchung'));
		}

		set_transient($rate_key, $attempts + 1, 15 * MINUTE_IN_SECONDS);

		$table = $wpdb->prefix . 'ltb_reservations';

		$reservation = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM $table WHERE confirmation_token = %s AND status != 'cancelled'",
			$token
		));

		if (!$reservation) {
			return new WP_Error('not_found', __('Reservierung nicht gefunden.', 'lasertagpro-buchung'));
		}

		// Token-Ablauf prüfen: Stornierung nur innerhalb von 30 Tagen nach Buchungserstellung
		$created_at = strtotime($reservation->created_at);
		if ($created_at && (time() - $created_at) > (30 * DAY_IN_SECONDS)) {
			return new WP_Error('token_expired', __('Der Stornierungslink ist abgelaufen. Bitte kontaktieren Sie uns direkt.', 'lasertagpro-buchung'));
		}

		// Erfolgreichen Versuch: Rate-Limit-Zähler zurücksetzen
		delete_transient($rate_key);

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

		// DAV-Kalender bereinigen: BELEGT-Event löschen + FREI-Slots wiederherstellen
		$dav_client = new LTB_DAV_Client();
		$dav_client->cancel_reservation_in_calendar(
			$reservation->booking_date,
			$reservation->start_time,
			$reservation->booking_duration
		);

		// ltb_blocked_dates bereinigen: falls für diesen Tag keine aktiven Reservierungen mehr existieren
		self::maybe_unblock_date($reservation->booking_date);

		// E-Mail senden
		LTB_Email::send_cancellation_confirmation($id);

		return true;
	}

	/**
	 * Datum aus blocked_dates entfernen, falls keine aktiven Reservierungen mehr
	 *
	 * @param string $booking_date Datum (Y-m-d oder Y-m-d H:i:s)
	 */
	private static function maybe_unblock_date($booking_date) {
		global $wpdb;

		$date = substr($booking_date, 0, 10);
		$table = $wpdb->prefix . 'ltb_reservations';

		$active = $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM $table WHERE booking_date LIKE %s AND status != 'cancelled'",
			$date . '%'
		));

		if ($active == 0) {
			$blocked_dates = get_option('ltb_blocked_dates', array());
			$new = array_values(array_diff($blocked_dates, array($date)));
			if (count($new) !== count($blocked_dates)) {
				update_option('ltb_blocked_dates', $new);
				error_log('LTB: Datum ' . $date . ' aus blocked_dates entfernt (keine aktiven Reservierungen mehr)');
			}
		}
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
	 * Anzahl der Reservierungen zählen (für Paginierung)
	 *
	 * @param array $args Filter-Argumente (status, date_from, date_to)
	 * @return int Anzahl
	 */
	public static function count_reservations($args = array()) {
		global $wpdb;

		$table = $wpdb->prefix . 'ltb_reservations';

		$defaults = array(
			'status'    => '',
			'date_from' => '',
			'date_to'   => '',
		);
		$args = wp_parse_args($args, $defaults);

		$where        = array('1=1');
		$where_values = array();

		if (!empty($args['status'])) {
			$where[]        = 'status = %s';
			$where_values[] = $args['status'];
		}
		if (!empty($args['date_from'])) {
			$where[]        = 'booking_date >= %s';
			$where_values[] = $args['date_from'];
		}
		if (!empty($args['date_to'])) {
			$where[]        = 'booking_date <= %s';
			$where_values[] = $args['date_to'];
		}

		$where_clause = implode(' AND ', $where);
		if (!empty($where_values)) {
			$where_clause = $wpdb->prepare($where_clause, $where_values);
		}

		return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where_clause");
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

