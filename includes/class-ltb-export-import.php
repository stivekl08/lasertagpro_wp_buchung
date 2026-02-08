<?php
/**
 * Export/Import-Funktionalität für Reservierungen
 */

if (!defined('ABSPATH')) {
	exit;
}

class LTB_Export_Import {

	/**
	 * Reservierungen als CSV exportieren
	 *
	 * @param array $args Filter-Argumente (optional)
	 * @return void
	 */
	public static function export_reservations_csv($args = array()) {
		$reservations = LTB_Booking::get_reservations($args);
		
		$filename = 'reservierungen_' . date('Y-m-d_His') . '.csv';
		
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=' . $filename);
		header('Pragma: no-cache');
		header('Expires: 0');
		
		$output = fopen('php://output', 'w');
		
		// BOM für UTF-8 (Excel-Kompatibilität)
		fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
		
		// Header-Zeile
		$headers = array(
			'ID',
			'Datum',
			'Startzeit',
			'Endzeit',
			'Dauer (Stunden)',
			'Name',
			'E-Mail',
			'Telefon',
			'Nachricht',
			'Anzahl Personen',
			'Spielmodus',
			'Preis pro Person',
			'Gesamtpreis',
			'Rabatt Betrag',
			'Rabatt Prozent',
			'Promo-Code',
			'Status',
			'Bestätigungs-Token',
			'Erstellt am',
			'Aktualisiert am'
		);
		fputcsv($output, $headers, ';');
		
		// Daten-Zeilen
		foreach ($reservations as $reservation) {
			$row = array(
				$reservation->id,
				$reservation->booking_date,
				$reservation->start_time,
				$reservation->end_time,
				$reservation->booking_duration,
				$reservation->name,
				$reservation->email,
				$reservation->phone ? $reservation->phone : '',
				$reservation->message ? $reservation->message : '',
				$reservation->person_count,
				$reservation->game_mode,
				$reservation->price_per_person ? number_format($reservation->price_per_person, 2, ',', '.') : '',
				$reservation->total_price ? number_format($reservation->total_price, 2, ',', '.') : '',
				$reservation->discount_amount ? number_format($reservation->discount_amount, 2, ',', '.') : '0,00',
				$reservation->discount_percent ? number_format($reservation->discount_percent, 2, ',', '.') : '0,00',
				$reservation->promo_code ? $reservation->promo_code : '',
				$reservation->status,
				$reservation->confirmation_token ? $reservation->confirmation_token : '',
				$reservation->created_at,
				$reservation->updated_at
			);
			fputcsv($output, $row, ';');
		}
		
		fclose($output);
		exit;
	}

	/**
	 * Reservierungen als JSON exportieren
	 *
	 * @param array $args Filter-Argumente (optional)
	 * @return void
	 */
	public static function export_reservations_json($args = array()) {
		$reservations = LTB_Booking::get_reservations($args);
		
		$filename = 'reservierungen_' . date('Y-m-d_His') . '.json';
		
		header('Content-Type: application/json; charset=utf-8');
		header('Content-Disposition: attachment; filename=' . $filename);
		header('Pragma: no-cache');
		header('Expires: 0');
		
		$data = array();
		foreach ($reservations as $reservation) {
			$data[] = array(
				'id' => (int) $reservation->id,
				'booking_date' => $reservation->booking_date,
				'start_time' => $reservation->start_time,
				'end_time' => $reservation->end_time,
				'booking_duration' => (int) $reservation->booking_duration,
				'name' => $reservation->name,
				'email' => $reservation->email,
				'phone' => $reservation->phone ? $reservation->phone : '',
				'message' => $reservation->message ? $reservation->message : '',
				'person_count' => (int) $reservation->person_count,
				'game_mode' => $reservation->game_mode,
				'price_per_person' => $reservation->price_per_person ? (float) $reservation->price_per_person : null,
				'total_price' => $reservation->total_price ? (float) $reservation->total_price : null,
				'discount_amount' => $reservation->discount_amount ? (float) $reservation->discount_amount : 0.0,
				'discount_percent' => $reservation->discount_percent ? (float) $reservation->discount_percent : 0.0,
				'promo_code' => $reservation->promo_code ? $reservation->promo_code : '',
				'status' => $reservation->status,
				'confirmation_token' => $reservation->confirmation_token ? $reservation->confirmation_token : '',
				'created_at' => $reservation->created_at,
				'updated_at' => $reservation->updated_at
			);
		}
		
		echo wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
		exit;
	}

	/**
	 * Reservierungen aus CSV importieren
	 *
	 * @param string $file_path Pfad zur CSV-Datei
	 * @param array $options Import-Optionen
	 * @return array Ergebnis mit Statistiken
	 */
	public static function import_reservations_csv($file_path, $options = array()) {
		$defaults = array(
			'skip_duplicates' => true,
			'update_existing' => false,
			'validate_data' => true,
		);
		$options = wp_parse_args($options, $defaults);
		
		$result = array(
			'success' => 0,
			'errors' => 0,
			'skipped' => 0,
			'updated' => 0,
			'messages' => array()
		);
		
		if (!file_exists($file_path)) {
			$result['errors']++;
			$result['messages'][] = __('Datei nicht gefunden.', 'lasertagpro-buchung');
			return $result;
		}
		
		$handle = fopen($file_path, 'r');
		if ($handle === false) {
			$result['errors']++;
			$result['messages'][] = __('Fehler beim Öffnen der Datei.', 'lasertagpro-buchung');
			return $result;
		}
		
		// BOM entfernen falls vorhanden
		$bom = fread($handle, 3);
		if ($bom !== chr(0xEF).chr(0xBB).chr(0xBF)) {
			rewind($handle);
		}
		
		// Header-Zeile lesen
		$headers = fgetcsv($handle, 0, ';');
		if ($headers === false) {
			fclose($handle);
			$result['errors']++;
			$result['messages'][] = __('Ungültiges CSV-Format.', 'lasertagpro-buchung');
			return $result;
		}
		
		// Header normalisieren (Leerzeichen entfernen)
		$headers = array_map('trim', $headers);
		
		// Mapping für Spalten
		$column_map = array(
			'ID' => 'id',
			'Datum' => 'booking_date',
			'Startzeit' => 'start_time',
			'Endzeit' => 'end_time',
			'Dauer (Stunden)' => 'booking_duration',
			'Name' => 'name',
			'E-Mail' => 'email',
			'Telefon' => 'phone',
			'Nachricht' => 'message',
			'Anzahl Personen' => 'person_count',
			'Spielmodus' => 'game_mode',
			'Preis pro Person' => 'price_per_person',
			'Gesamtpreis' => 'total_price',
			'Rabatt Betrag' => 'discount_amount',
			'Rabatt Prozent' => 'discount_percent',
			'Promo-Code' => 'promo_code',
			'Status' => 'status',
			'Bestätigungs-Token' => 'confirmation_token'
		);
		
		$line_number = 1;
		while (($row = fgetcsv($handle, 0, ';')) !== false) {
			$line_number++;
			
			if (count($row) !== count($headers)) {
				$result['errors']++;
				$result['messages'][] = sprintf(__('Zeile %d: Ungültige Anzahl von Spalten.', 'lasertagpro-buchung'), $line_number);
				continue;
			}
			
			$data = array();
			foreach ($headers as $index => $header) {
				if (isset($column_map[$header])) {
					$value = trim($row[$index]);
					// Entferne Anführungszeichen am Anfang und Ende
					if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
						(substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
						$value = substr($value, 1, -1);
					}
					$data[$column_map[$header]] = $value;
				}
			}
			
			// Validierung
			if ($options['validate_data']) {
				$validation_errors = self::validate_import_data($data);
				if (!empty($validation_errors)) {
					$result['errors']++;
					$result['messages'][] = sprintf(__('Zeile %d: %s', 'lasertagpro-buchung'), $line_number, implode(', ', $validation_errors));
					continue;
				}
			}
			
			// Daten konvertieren
			$data = self::convert_import_data($data);
			
			// Duplikat-Prüfung
			if ($options['skip_duplicates'] && !empty($data['id'])) {
				global $wpdb;
				$table = $wpdb->prefix . 'ltb_reservations';
				$existing = $wpdb->get_var($wpdb->prepare(
					"SELECT COUNT(*) FROM $table WHERE id = %d",
					$data['id']
				));
				
				if ($existing > 0) {
					if ($options['update_existing']) {
						$update_result = self::update_reservation($data);
						if ($update_result) {
							$result['updated']++;
						} else {
							$result['errors']++;
							$result['messages'][] = sprintf(__('Zeile %d: Fehler beim Aktualisieren.', 'lasertagpro-buchung'), $line_number);
						}
					} else {
						$result['skipped']++;
					}
					continue;
				}
			}
			
			// Reservierung erstellen
			$insert_result = self::create_reservation_from_import($data);
			if (is_wp_error($insert_result)) {
				$result['errors']++;
				$result['messages'][] = sprintf(__('Zeile %d: %s', 'lasertagpro-buchung'), $line_number, $insert_result->get_error_message());
			} else {
				$result['success']++;
			}
		}
		
		fclose($handle);
		return $result;
	}

	/**
	 * Reservierungen aus JSON importieren
	 *
	 * @param string $file_path Pfad zur JSON-Datei
	 * @param array $options Import-Optionen
	 * @return array Ergebnis mit Statistiken
	 */
	public static function import_reservations_json($file_path, $options = array()) {
		$defaults = array(
			'skip_duplicates' => true,
			'update_existing' => false,
			'validate_data' => true,
		);
		$options = wp_parse_args($options, $defaults);
		
		$result = array(
			'success' => 0,
			'errors' => 0,
			'skipped' => 0,
			'updated' => 0,
			'messages' => array()
		);
		
		if (!file_exists($file_path)) {
			$result['errors']++;
			$result['messages'][] = __('Datei nicht gefunden.', 'lasertagpro-buchung');
			return $result;
		}
		
		$json_content = file_get_contents($file_path);
		if ($json_content === false) {
			$result['errors']++;
			$result['messages'][] = __('Fehler beim Lesen der Datei.', 'lasertagpro-buchung');
			return $result;
		}
		
		$data = json_decode($json_content, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			$result['errors']++;
			$result['messages'][] = __('Ungültiges JSON-Format: ', 'lasertagpro-buchung') . json_last_error_msg();
			return $result;
		}
		
		if (!is_array($data)) {
			$result['errors']++;
			$result['messages'][] = __('JSON-Daten müssen ein Array sein.', 'lasertagpro-buchung');
			return $result;
		}
		
		foreach ($data as $index => $item) {
			$line_number = $index + 1;
			
			if (!is_array($item)) {
				$result['errors']++;
				$result['messages'][] = sprintf(__('Zeile %d: Ungültiges Datenformat.', 'lasertagpro-buchung'), $line_number);
				continue;
			}
			
			// Validierung
			if ($options['validate_data']) {
				$validation_errors = self::validate_import_data($item);
				if (!empty($validation_errors)) {
					$result['errors']++;
					$result['messages'][] = sprintf(__('Zeile %d: %s', 'lasertagpro-buchung'), $line_number, implode(', ', $validation_errors));
					continue;
				}
			}
			
			// Daten konvertieren
			$data_item = self::convert_import_data($item);
			
			// Duplikat-Prüfung
			if ($options['skip_duplicates'] && !empty($data_item['id'])) {
				global $wpdb;
				$table = $wpdb->prefix . 'ltb_reservations';
				$existing = $wpdb->get_var($wpdb->prepare(
					"SELECT COUNT(*) FROM $table WHERE id = %d",
					$data_item['id']
				));
				
				if ($existing > 0) {
					if ($options['update_existing']) {
						$update_result = self::update_reservation($data_item);
						if ($update_result) {
							$result['updated']++;
						} else {
							$result['errors']++;
							$result['messages'][] = sprintf(__('Zeile %d: Fehler beim Aktualisieren.', 'lasertagpro-buchung'), $line_number);
						}
					} else {
						$result['skipped']++;
					}
					continue;
				}
			}
			
			// Reservierung erstellen
			$insert_result = self::create_reservation_from_import($data_item);
			if (is_wp_error($insert_result)) {
				$result['errors']++;
				$result['messages'][] = sprintf(__('Zeile %d: %s', 'lasertagpro-buchung'), $line_number, $insert_result->get_error_message());
			} else {
				$result['success']++;
			}
		}
		
		return $result;
	}

	/**
	 * Import-Daten validieren
	 *
	 * @param array $data Daten
	 * @return array Array von Fehlermeldungen
	 */
	private static function validate_import_data($data) {
		$errors = array();
		
		if (empty($data['booking_date'])) {
			$errors[] = __('Datum fehlt.', 'lasertagpro-buchung');
		} else {
			// Datum normalisieren für Validierung (Zeitanteil entfernen)
			$date_to_validate = $data['booking_date'];
			if (strpos($date_to_validate, ' ') !== false) {
				$date_to_validate = substr($date_to_validate, 0, strpos($date_to_validate, ' '));
			}
			
			if (!self::is_valid_date($date_to_validate)) {
				$errors[] = __('Ungültiges Datum.', 'lasertagpro-buchung');
			}
		}
		
		if (empty($data['start_time'])) {
			$errors[] = __('Startzeit fehlt.', 'lasertagpro-buchung');
		} elseif (!self::is_valid_time($data['start_time'])) {
			$errors[] = __('Ungültige Startzeit.', 'lasertagpro-buchung');
		}
		
		if (empty($data['booking_duration']) || $data['booking_duration'] < 1 || $data['booking_duration'] > 3) {
			$errors[] = __('Dauer muss zwischen 1 und 3 Stunden liegen.', 'lasertagpro-buchung');
		}
		
		if (empty($data['name'])) {
			$errors[] = __('Name fehlt.', 'lasertagpro-buchung');
		}
		
		if (empty($data['email']) || !is_email($data['email'])) {
			$errors[] = __('Ungültige E-Mail-Adresse.', 'lasertagpro-buchung');
		}
		
		if (empty($data['person_count']) || $data['person_count'] < 1) {
			$errors[] = __('Anzahl Personen fehlt oder ist ungültig.', 'lasertagpro-buchung');
		}
		
		if (empty($data['game_mode'])) {
			$errors[] = __('Spielmodus fehlt.', 'lasertagpro-buchung');
		}
		
		if (!empty($data['status']) && !in_array($data['status'], array('pending', 'confirmed', 'cancelled'))) {
			$errors[] = __('Ungültiger Status.', 'lasertagpro-buchung');
		}
		
		return $errors;
	}

	/**
	 * Import-Daten konvertieren
	 *
	 * @param array $data Daten
	 * @return array Konvertierte Daten
	 */
	private static function convert_import_data($data) {
		$converted = array();
		
		if (isset($data['id'])) {
			$converted['id'] = absint($data['id']);
		}
		
		// Datum normalisieren (kann Y-m-d oder Y-m-d H:i:s sein)
		$booking_date = sanitize_text_field($data['booking_date']);
		// Wenn Datum+Zeit, nur Datumsteil extrahieren
		if (strpos($booking_date, ' ') !== false) {
			$booking_date = substr($booking_date, 0, strpos($booking_date, ' '));
		}
		$converted['booking_date'] = $booking_date;
		
		// Startzeit normalisieren (auf H:i:s Format)
		$start_time = sanitize_text_field($data['start_time']);
		$time_parts = explode(':', $start_time);
		if (count($time_parts) === 2) {
			$start_time = $time_parts[0] . ':' . $time_parts[1] . ':00';
		}
		$converted['start_time'] = $start_time;
		
		if (isset($data['end_time']) && !empty($data['end_time'])) {
			// Endzeit normalisieren (auf H:i:s Format)
			$end_time = sanitize_text_field($data['end_time']);
			$time_parts = explode(':', $end_time);
			if (count($time_parts) === 2) {
				$end_time = $time_parts[0] . ':' . $time_parts[1] . ':00';
			}
			$converted['end_time'] = $end_time;
		} else {
			// Endzeit berechnen falls nicht vorhanden
			$start_datetime = $converted['booking_date'] . ' ' . $converted['start_time'];
			$start_obj = new DateTime($start_datetime);
			$end_obj = clone $start_obj;
			$end_obj->modify('+' . absint($data['booking_duration']) . ' hours');
			$converted['end_time'] = $end_obj->format('H:i:s');
		}
		
		$converted['booking_duration'] = absint($data['booking_duration']);
		$converted['name'] = sanitize_text_field($data['name']);
		$converted['email'] = sanitize_email($data['email']);
		$converted['phone'] = isset($data['phone']) ? sanitize_text_field($data['phone']) : '';
		$converted['message'] = isset($data['message']) ? sanitize_textarea_field($data['message']) : '';
		$converted['person_count'] = absint($data['person_count']);
		$converted['game_mode'] = sanitize_text_field($data['game_mode']);
		
		if (isset($data['price_per_person'])) {
			$converted['price_per_person'] = self::parse_decimal($data['price_per_person']);
		}
		
		if (isset($data['total_price'])) {
			$converted['total_price'] = self::parse_decimal($data['total_price']);
		}
		
		if (isset($data['discount_amount'])) {
			$converted['discount_amount'] = self::parse_decimal($data['discount_amount']);
		} else {
			$converted['discount_amount'] = 0.00;
		}
		
		if (isset($data['discount_percent'])) {
			$converted['discount_percent'] = self::parse_decimal($data['discount_percent']);
		} else {
			$converted['discount_percent'] = 0.00;
		}
		
		$converted['promo_code'] = isset($data['promo_code']) ? sanitize_text_field($data['promo_code']) : '';
		$converted['status'] = isset($data['status']) ? sanitize_text_field($data['status']) : 'pending';
		
		if (isset($data['confirmation_token'])) {
			$converted['confirmation_token'] = sanitize_text_field($data['confirmation_token']);
		} else {
			$converted['confirmation_token'] = bin2hex(random_bytes(32));
		}
		
		return $converted;
	}

	/**
	 * Dezimalzahl parsen (unterstützt verschiedene Formate)
	 *
	 * @param mixed $value Wert
	 * @return float Dezimalzahl
	 */
	private static function parse_decimal($value) {
		if (is_numeric($value)) {
			return (float) $value;
		}
		
		// Komma als Dezimaltrennzeichen
		$value = str_replace(',', '.', $value);
		$value = preg_replace('/[^0-9.]/', '', $value);
		
		return (float) $value;
	}

	/**
	 * Datum validieren
	 *
	 * @param string $date Datum (kann Y-m-d oder Y-m-d H:i:s sein)
	 * @return bool Gültig
	 */
	private static function is_valid_date($date) {
		// Entferne Zeitanteil falls vorhanden
		if (strpos($date, ' ') !== false) {
			$date = substr($date, 0, strpos($date, ' '));
		}
		
		// Prüfe auf Y-m-d Format
		$d = DateTime::createFromFormat('Y-m-d', $date);
		if ($d && $d->format('Y-m-d') === $date) {
			return true;
		}
		
		// Alternative: Prüfe auf andere gängige Formate
		$d = DateTime::createFromFormat('d.m.Y', $date);
		if ($d && $d->format('d.m.Y') === $date) {
			return true;
		}
		
		return false;
	}

	/**
	 * Zeit validieren
	 *
	 * @param string $time Zeit
	 * @return bool Gültig
	 */
	private static function is_valid_time($time) {
		$parts = explode(':', $time);
		if (count($parts) < 2) {
			return false;
		}
		$hour = (int) $parts[0];
		$minute = (int) $parts[1];
		return $hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59;
	}

	/**
	 * Reservierung aus Import erstellen
	 *
	 * @param array $data Daten
	 * @return int|WP_Error Reservierungs-ID oder Fehler
	 */
	private static function create_reservation_from_import($data) {
		global $wpdb;
		$table = $wpdb->prefix . 'ltb_reservations';
		
		$insert_data = array(
			'booking_date' => $data['booking_date'],
			'booking_duration' => $data['booking_duration'],
			'start_time' => $data['start_time'],
			'end_time' => $data['end_time'],
			'name' => $data['name'],
			'email' => $data['email'],
			'phone' => $data['phone'],
			'message' => $data['message'],
			'person_count' => $data['person_count'],
			'game_mode' => $data['game_mode'],
			'status' => $data['status'],
			'confirmation_token' => $data['confirmation_token'],
		);
		
		if (isset($data['price_per_person'])) {
			$insert_data['price_per_person'] = $data['price_per_person'];
		}
		
		if (isset($data['total_price'])) {
			$insert_data['total_price'] = $data['total_price'];
		}
		
		if (isset($data['discount_amount'])) {
			$insert_data['discount_amount'] = $data['discount_amount'];
		}
		
		if (isset($data['discount_percent'])) {
			$insert_data['discount_percent'] = $data['discount_percent'];
		}
		
		if (!empty($data['promo_code'])) {
			$insert_data['promo_code'] = $data['promo_code'];
		}
		
		$result = $wpdb->insert($table, $insert_data);
		
		if ($result === false) {
			return new WP_Error('db_error', __('Fehler beim Speichern der Reservierung.', 'lasertagpro-buchung'));
		}
		
		return $wpdb->insert_id;
	}

	/**
	 * Reservierung aktualisieren
	 *
	 * @param array $data Daten
	 * @return bool Erfolg
	 */
	private static function update_reservation($data) {
		if (empty($data['id'])) {
			return false;
		}
		
		global $wpdb;
		$table = $wpdb->prefix . 'ltb_reservations';
		
		$update_data = array(
			'booking_date' => $data['booking_date'],
			'booking_duration' => $data['booking_duration'],
			'start_time' => $data['start_time'],
			'end_time' => $data['end_time'],
			'name' => $data['name'],
			'email' => $data['email'],
			'phone' => $data['phone'],
			'message' => $data['message'],
			'person_count' => $data['person_count'],
			'game_mode' => $data['game_mode'],
			'status' => $data['status'],
		);
		
		if (isset($data['price_per_person'])) {
			$update_data['price_per_person'] = $data['price_per_person'];
		}
		
		if (isset($data['total_price'])) {
			$update_data['total_price'] = $data['total_price'];
		}
		
		if (isset($data['discount_amount'])) {
			$update_data['discount_amount'] = $data['discount_amount'];
		}
		
		if (isset($data['discount_percent'])) {
			$update_data['discount_percent'] = $data['discount_percent'];
		}
		
		if (isset($data['promo_code'])) {
			$update_data['promo_code'] = $data['promo_code'];
		}
		
		$result = $wpdb->update(
			$table,
			$update_data,
			array('id' => $data['id']),
			array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s'),
			array('%d')
		);
		
		return $result !== false;
	}
}

