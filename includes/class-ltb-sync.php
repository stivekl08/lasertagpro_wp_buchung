<?php
/**
 * Synchronisierung zwischen Reservierungen und DAV-Kalender
 */

if (!defined('ABSPATH')) {
	exit;
}

class LTB_Sync {

	/**
	 * Reservierungen mit Kalender synchronisieren
	 *
	 * @param array $options Sync-Optionen
	 * @return array Ergebnis mit Statistiken
	 */
	public static function sync_reservations_to_calendar($options = array()) {
		$defaults = array(
			'date_from' => '',
			'date_to' => '',
			'status' => '', // Leer = alle außer cancelled
			'create_missing' => true,
			'update_existing' => false,
		);
		$options = wp_parse_args($options, $defaults);
		
		$result = array(
			'created' => 0,
			'updated' => 0,
			'errors' => 0,
			'skipped' => 0,
			'messages' => array()
		);
		
		// Reservierungen abrufen
		$args = array(
			'date_from' => $options['date_from'],
			'date_to' => $options['date_to'],
			'status' => $options['status'],
		);
		
		// Standard: Alle außer stornierte
		if (empty($args['status'])) {
			$reservations = LTB_Booking::get_reservations($args);
			// Manuell stornierte herausfiltern
			$reservations = array_filter($reservations, function($res) {
				return $res->status !== 'cancelled';
			});
		} else {
			$reservations = LTB_Booking::get_reservations($args);
		}
		
		if (empty($reservations)) {
			$result['messages'][] = __('Keine Reservierungen zum Synchronisieren gefunden.', 'lasertagpro-buchung');
			return $result;
		}
		
		$dav_client = new LTB_DAV_Client();
		
		foreach ($reservations as $reservation) {
			// Prüfen ob Event bereits existiert
			$event_exists = self::check_event_exists($dav_client, $reservation);
			
			if ($event_exists && !$options['update_existing']) {
				$result['skipped']++;
				continue;
			}
			
			// Event erstellen oder aktualisieren
			$summary = 'BELEGT - ' . $reservation->name . ' (' . $reservation->person_count . ' Personen)';
			
			// Status in Summary einbauen
			if ($reservation->status === 'pending') {
				$summary = '[AUSSTEHEND] ' . $summary;
			} elseif ($reservation->status === 'confirmed') {
				$summary = '[BESTÄTIGT] ' . $summary;
			}
			
			$dav_result = $dav_client->create_event(
				$reservation->booking_date,
				$reservation->start_time,
				$reservation->booking_duration,
				$summary
			);
			
			if (is_wp_error($dav_result)) {
				$result['errors']++;
				$result['messages'][] = sprintf(
					__('Reservierung #%d (%s): %s', 'lasertagpro-buchung'),
					$reservation->id,
					$reservation->booking_date . ' ' . $reservation->start_time,
					$dav_result->get_error_message()
				);
			} else {
				if ($event_exists) {
					$result['updated']++;
				} else {
					$result['created']++;
				}
			}
		}
		
		return $result;
	}

	/**
	 * Prüfen ob Event für Reservierung existiert
	 *
	 * @param LTB_DAV_Client $dav_client DAV-Client
	 * @param object $reservation Reservierung
	 * @return bool Existiert
	 */
	private static function check_event_exists($dav_client, $reservation) {
		// Hole alle Events für das Datum
		$available_slots = $dav_client->get_available_slots(
			$reservation->booking_date,
			$reservation->booking_date
		);
		
		// Prüfe ob ein BELEGT-Event für diesen Zeitraum existiert
		// Dazu müssen wir die Events direkt abrufen (nicht nur verfügbare Slots)
		$events = self::get_events_for_date($dav_client, $reservation->booking_date);
		
		$reservation_start = new DateTime($reservation->booking_date . ' ' . $reservation->start_time);
		$reservation_end = new DateTime($reservation->booking_date . ' ' . $reservation->end_time);
		
		foreach ($events as $event) {
			if (empty($event['start']) || empty($event['end'])) {
				continue;
			}
			
			$event_start = new DateTime($event['start']);
			$event_end = new DateTime($event['end']);
			
			// Prüfe Überschneidung
			if ($event_start < $reservation_end && $event_end > $reservation_start) {
				// Prüfe ob Event "BELEGT" ist
				$summary_upper = isset($event['summary']) ? strtoupper($event['summary']) : '';
				if (strpos($summary_upper, 'BELEGT') !== false || 
					strpos($summary_upper, 'BOOKED') !== false ||
					strpos($summary_upper, 'RESERVIERT') !== false) {
					return true;
				}
			}
		}
		
		return false;
	}

	/**
	 * Alle Events für ein Datum abrufen
	 *
	 * @param LTB_DAV_Client $dav_client DAV-Client
	 * @param string $date Datum (Y-m-d)
	 * @return array Events
	 */
	private static function get_events_for_date($dav_client, $date) {
		if (empty($dav_client)) {
			return array();
		}
		
		// Verwende Reflection um auf private Methode zuzugreifen
		// Oder: Erweitere DAV-Client um public Methode
		// Für jetzt: Verwende get_available_slots und parse die Antwort
		
		// Alternative: Direkter CalDAV-Request
		$dav_url = get_option('ltb_dav_url', '');
		$username = get_option('ltb_dav_username', '');
		$password = get_option('ltb_dav_password', '');
		
		if (empty($dav_url) || empty($username) || empty($password)) {
			return array();
		}
		
		$url = rtrim($dav_url, '/') . '/';
		
		$body = '<?xml version="1.0" encoding="UTF-8"?>
<C:calendar-query xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
	<D:prop>
		<D:getetag/>
		<C:calendar-data/>
	</D:prop>
	<C:filter>
		<C:comp-filter name="VCALENDAR">
			<C:comp-filter name="VEVENT">
				<C:time-range start="' . self::format_caldav_date($date . 'T00:00:00') . '" end="' . self::format_caldav_date($date . 'T23:59:59') . '"/>
			</C:comp-filter>
		</C:comp-filter>
	</C:filter>
</C:calendar-query>';

		$args = array(
			'method' => 'REPORT',
			'headers' => array(
				'Content-Type' => 'application/xml; charset=utf-8',
				'Depth' => '1',
				'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
			),
			'body' => $body,
			'timeout' => 30,
		);

		$response = wp_remote_request($url, $args);

		if (is_wp_error($response)) {
			return array();
		}

		$body = wp_remote_retrieve_body($response);
		$code = wp_remote_retrieve_response_code($response);

		if ($code !== 207) {
			return array();
		}

		return self::parse_calendar_response($body);
	}

	/**
	 * Kalender-Antwort parsen
	 *
	 * @param string $xml_body XML-Antwort
	 * @return array Events
	 */
	private static function parse_calendar_response($xml_body) {
		$events = array();
		
		libxml_use_internal_errors(true);
		$xml = simplexml_load_string($xml_body);
		
		if ($xml === false) {
			return $events;
		}

		$xml->registerXPathNamespace('d', 'DAV:');
		$xml->registerXPathNamespace('c', 'urn:ietf:params:xml:ns:caldav');

		$calendar_data = $xml->xpath('//c:calendar-data');
		
		foreach ($calendar_data as $data) {
			$ical = (string) $data;
			$parsed_events = self::parse_ical($ical);
			$events = array_merge($events, $parsed_events);
		}

		return $events;
	}

	/**
	 * iCal-String parsen
	 *
	 * @param string $ical iCal-String
	 * @return array Events
	 */
	private static function parse_ical($ical) {
		$events = array();
		
		preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $ical, $matches);
		
		foreach ($matches[1] as $event_data) {
			$event = array();
			
			if (preg_match('/DTSTART[^:]*:([0-9TZ]+)/', $event_data, $dtstart)) {
				$event['start'] = self::parse_ical_datetime($dtstart[1]);
			}
			
			if (preg_match('/DTEND[^:]*:([0-9TZ]+)/', $event_data, $dtend)) {
				$event['end'] = self::parse_ical_datetime($dtend[1]);
			}
			
			if (preg_match('/SUMMARY:(.*)/', $event_data, $summary)) {
				$event['summary'] = trim($summary[1]);
			}
			
			if (preg_match('/STATUS:(.*)/', $event_data, $status)) {
				$event['status'] = trim($status[1]);
			}
			
			if (!empty($event['start']) && !empty($event['end'])) {
				$events[] = $event;
			}
		}
		
		return $events;
	}

	/**
	 * iCal-Datetime parsen
	 *
	 * @param string $datetime iCal-Datetime-String
	 * @return string MySQL-Datetime
	 */
	private static function parse_ical_datetime($datetime) {
		$datetime = str_replace(array('T', 'Z'), array(' ', ''), $datetime);
		
		if (strlen($datetime) === 15) {
			$date = substr($datetime, 0, 8);
			$time = substr($datetime, 9, 6);
			$formatted = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2) . ' ' .
				substr($time, 0, 2) . ':' . substr($time, 2, 2) . ':' . substr($time, 4, 2);
			return $formatted;
		}
		
		return '';
	}

	/**
	 * Datum für CalDAV formatieren
	 *
	 * @param string $datetime MySQL-Datetime
	 * @return string CalDAV-Datetime
	 */
	private static function format_caldav_date($datetime) {
		$dt = new DateTime($datetime);
		return $dt->format('Ymd\THis\Z');
	}

	/**
	 * Kalender-Events mit Reservierungen synchronisieren (umgekehrte Richtung)
	 * Findet Events im Kalender, die nicht in der Datenbank sind
	 *
	 * @param array $options Sync-Optionen
	 * @return array Ergebnis mit Statistiken
	 */
	public static function sync_calendar_to_reservations($options = array()) {
		$defaults = array(
			'date_from' => date('Y-m-d'),
			'date_to' => date('Y-m-d', strtotime('+30 days')),
			'create_missing' => false, // Standard: Nur melden, nicht erstellen
		);
		$options = wp_parse_args($options, $defaults);
		
		$result = array(
			'found' => 0,
			'created' => 0,
			'errors' => 0,
			'messages' => array()
		);
		
		$dav_url = get_option('ltb_dav_url', '');
		$username = get_option('ltb_dav_username', '');
		$password = get_option('ltb_dav_password', '');
		
		if (empty($dav_url) || empty($username) || empty($password)) {
			$result['errors']++;
			$result['messages'][] = __('DAV-Konfiguration fehlt.', 'lasertagpro-buchung');
			return $result;
		}
		
		// Hole alle Events aus dem Kalender
		$events = self::get_events_for_date_range($dav_url, $username, $password, $options['date_from'], $options['date_to']);
		
		// Filtere nur BELEGT-Events
		$booked_events = array();
		foreach ($events as $event) {
			$summary_upper = isset($event['summary']) ? strtoupper($event['summary']) : '';
			if (strpos($summary_upper, 'BELEGT') !== false || 
				strpos($summary_upper, 'BOOKED') !== false ||
				strpos($summary_upper, 'RESERVIERT') !== false) {
				$booked_events[] = $event;
			}
		}
		
		$result['found'] = count($booked_events);
		
		// Prüfe für jedes Event ob Reservierung existiert
		foreach ($booked_events as $event) {
			if (empty($event['start']) || empty($event['end'])) {
				continue;
			}
			
			$event_start = new DateTime($event['start']);
			$event_end = new DateTime($event['end']);
			$event_date = $event_start->format('Y-m-d');
			$event_start_time = $event_start->format('H:i:s');
			
			// Prüfe ob Reservierung existiert
			$existing = LTB_Booking::get_reservations(array(
				'date_from' => $event_date,
				'date_to' => $event_date,
			));
			
			$found = false;
			foreach ($existing as $reservation) {
				$res_start = new DateTime($reservation->booking_date . ' ' . $reservation->start_time);
				$res_end = new DateTime($reservation->booking_date . ' ' . $reservation->end_time);
				
				// Prüfe Überschneidung
				if ($res_start < $event_end && $res_end > $event_start) {
					$found = true;
					break;
				}
			}
			
			if (!$found) {
				$result['messages'][] = sprintf(
					__('Event im Kalender gefunden ohne Reservierung: %s %s (%s)', 'lasertagpro-buchung'),
					$event_date,
					$event_start_time,
					isset($event['summary']) ? $event['summary'] : __('Kein Titel', 'lasertagpro-buchung')
				);
				
				// Optional: Reservierung erstellen
				if ($options['create_missing']) {
					// Versuche Daten aus Summary zu extrahieren
					$summary = isset($event['summary']) ? $event['summary'] : '';
					
					// Name und Personenanzahl aus Summary extrahieren
					$name = __('Unbekannt', 'lasertagpro-buchung');
					$person_count = 1;
					
					if (preg_match('/- (.+?) \((\d+) Personen\)/', $summary, $matches)) {
						$name = $matches[1];
						$person_count = absint($matches[2]);
					}
					
					$duration = $event_start->diff($event_end)->h;
					if ($duration < 1) {
						$duration = 1;
					}
					
					$data = array(
						'booking_date' => $event_date,
						'start_time' => $event_start_time,
						'booking_duration' => $duration,
						'name' => $name,
						'email' => 'sync@lasertagpro.at',
						'phone' => '',
						'message' => __('Automatisch aus Kalender synchronisiert', 'lasertagpro-buchung'),
						'person_count' => $person_count,
						'game_mode' => 'LaserTag', // Standard
						'status' => 'confirmed',
					);
					
					$create_result = LTB_Booking::create_reservation($data);
					
					if (is_wp_error($create_result)) {
						$result['errors']++;
						$result['messages'][] = sprintf(
							__('Fehler beim Erstellen der Reservierung: %s', 'lasertagpro-buchung'),
							$create_result->get_error_message()
						);
					} else {
						$result['created']++;
					}
				}
			}
		}
		
		return $result;
	}

	/**
	 * Events für einen Datumsbereich abrufen
	 *
	 * @param string $dav_url DAV-URL
	 * @param string $username Benutzername
	 * @param string $password Passwort
	 * @param string $date_from Startdatum
	 * @param string $date_to Enddatum
	 * @return array Events
	 */
	private static function get_events_for_date_range($dav_url, $username, $password, $date_from, $date_to) {
		$url = rtrim($dav_url, '/') . '/';
		
		$body = '<?xml version="1.0" encoding="UTF-8"?>
<C:calendar-query xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
	<D:prop>
		<D:getetag/>
		<C:calendar-data/>
	</D:prop>
	<C:filter>
		<C:comp-filter name="VCALENDAR">
			<C:comp-filter name="VEVENT">
				<C:time-range start="' . self::format_caldav_date($date_from . 'T00:00:00') . '" end="' . self::format_caldav_date($date_to . 'T23:59:59') . '"/>
			</C:comp-filter>
		</C:comp-filter>
	</C:filter>
</C:calendar-query>';

		$args = array(
			'method' => 'REPORT',
			'headers' => array(
				'Content-Type' => 'application/xml; charset=utf-8',
				'Depth' => '1',
				'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
			),
			'body' => $body,
			'timeout' => 30,
		);

		$response = wp_remote_request($url, $args);

		if (is_wp_error($response)) {
			return array();
		}

		$body = wp_remote_retrieve_body($response);
		$code = wp_remote_retrieve_response_code($response);

		if ($code !== 207) {
			return array();
		}

		return self::parse_calendar_response($body);
	}
}




