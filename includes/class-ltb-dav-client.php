<?php
/**
 * DAV/CalDAV Client für Terminabfrage
 */

if (!defined('ABSPATH')) {
	exit;
}

class LTB_DAV_Client {

	/**
	 * DAV-Server URL
	 */
	private $dav_url;

	/**
	 * Benutzername
	 */
	private $username;

	/**
	 * Passwort
	 */
	private $password;

	/**
	 * Konstruktor
	 */
	public function __construct() {
		$this->dav_url = get_option('ltb_dav_url', '');
		$this->username = get_option('ltb_dav_username', '');
		$this->password = get_option('ltb_dav_password', '');
	}

	/**
	 * Verfügbare Termine für einen Zeitraum abrufen
	 *
	 * @param string $start_date Startdatum (Y-m-d)
	 * @param string $end_date Enddatum (Y-m-d)
	 * @return array Array von verfügbaren Terminen
	 */
	public function get_available_slots($start_date, $end_date) {
		if (empty($this->dav_url) || empty($this->username) || empty($this->password)) {
			return array();
		}

		// CalDAV REPORT Request für Termine
		$url = rtrim($this->dav_url, '/') . '/';
		
		$body = '<?xml version="1.0" encoding="UTF-8"?>
<C:calendar-query xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
	<D:prop>
		<D:getetag/>
		<C:calendar-data/>
	</D:prop>
	<C:filter>
		<C:comp-filter name="VCALENDAR">
			<C:comp-filter name="VEVENT">
				<C:time-range start="' . $this->format_caldav_date($start_date . 'T00:00:00') . '" end="' . $this->format_caldav_date($end_date . 'T23:59:59') . '"/>
			</C:comp-filter>
		</C:comp-filter>
	</C:filter>
</C:calendar-query>';

		$args = array(
			'method' => 'REPORT',
			'headers' => array(
				'Content-Type' => 'application/xml; charset=utf-8',
				'Depth' => '1',
			),
			'body' => $body,
			'timeout' => 30,
		);

		$response = wp_remote_request($url, $this->add_auth($args));

		if (is_wp_error($response)) {
			error_log('LTB DAV Error: ' . $response->get_error_message());
			return array();
		}

		$body = wp_remote_retrieve_body($response);
		$code = wp_remote_retrieve_response_code($response);

		if ($code !== 207) {
			error_log('LTB DAV Error: HTTP ' . $code);
			return array();
		}

		return $this->parse_calendar_response($body, $start_date, $end_date);
	}

	/**
	 * Kalender-Antwort parsen
	 *
	 * @param string $xml_body XML-Antwort
	 * @param string $start_date Startdatum
	 * @param string $end_date Enddatum
	 * @return array Array von verfügbaren Zeitslots
	 */
	private function parse_calendar_response($xml_body, $start_date, $end_date) {
		$slots = array();
		
		// XML parsen
		libxml_use_internal_errors(true);
		$xml = simplexml_load_string($xml_body);
		
		if ($xml === false) {
			error_log('LTB DAV Error: Could not parse XML');
			return $slots;
		}

		// Namespace registrieren
		$xml->registerXPathNamespace('d', 'DAV:');
		$xml->registerXPathNamespace('c', 'urn:ietf:params:xml:ns:caldav');

		// calendar-data Elemente finden
		$calendar_data = $xml->xpath('//c:calendar-data');
		
		foreach ($calendar_data as $data) {
			$ical = (string) $data;
			
			// iCal parsen
			$events = $this->parse_ical($ical);
			$slots = array_merge($slots, $events);
		}

		// Verfügbare Slots aus Events extrahieren (Events mit "FREI" oder ohne Status = verfügbar)
		$available_slots = array();
		$booked_events = array();
		
		foreach ($slots as $event) {
			if (empty($event['start']) || empty($event['end'])) {
				continue;
			}
			
			// Wenn Event "FREI" ist oder kein Status = verfügbar
			$is_available = true;
			if (isset($event['summary'])) {
				$summary_upper = strtoupper($event['summary']);
				// Wenn "BUSY", "BELEGT" oder ähnliches im Summary steht = belegt
				if (strpos($summary_upper, 'BUSY') !== false || 
					strpos($summary_upper, 'BELEGT') !== false ||
					strpos($summary_upper, 'RESERVIERT') !== false ||
					strpos($summary_upper, 'BOOKED') !== false) {
					$is_available = false;
					$booked_events[] = $event;
				}
				// Wenn "FREI" im Summary steht = verfügbar
				if (strpos($summary_upper, 'FREI') !== false || 
					strpos($summary_upper, 'FREE') !== false ||
					strpos($summary_upper, 'AVAILABLE') !== false) {
					$is_available = true;
				}
			}
			
			// Status prüfen
			if (isset($event['status'])) {
				$status_upper = strtoupper($event['status']);
				if ($status_upper === 'CONFIRMED' || $status_upper === 'BUSY') {
					$is_available = false;
					$booked_events[] = $event;
				}
			}
			
			if ($is_available) {
				$start_dt = new DateTime($event['start']);
				$end_dt = new DateTime($event['end']);
				$date_str = $start_dt->format('Y-m-d');
				$hour = (int) $start_dt->format('G');
				
				// Slot hinzufügen
				$available_slots[] = array(
					'date' => $date_str,
					'start' => $event['start'],
					'end' => $event['end'],
					'hour' => $hour,
				);
			}
		}
		
		// Falls keine Events gefunden wurden, Standard-Slots generieren (alle Stunden als verfügbar)
		if (empty($available_slots) && empty($booked_events)) {
			$available_slots = $this->generate_available_slots($start_date, $end_date, array());
		} elseif (empty($available_slots) && !empty($booked_events)) {
			// Wenn nur belegte Events gefunden wurden, generiere alle Slots und filtere belegte heraus
			$available_slots = $this->generate_available_slots($start_date, $end_date, $booked_events);
		}
		
		// Debug-Logging
		error_log('LTB DAV: Gefundene verfügbare Slots: ' . count($available_slots));
		
		return $available_slots;
	}

	/**
	 * iCal-String parsen
	 *
	 * @param string $ical iCal-String
	 * @return array Array von Events
	 */
	private function parse_ical($ical) {
		$events = array();
		
		// Einfacher iCal-Parser für VEVENT
		preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $ical, $matches);
		
		foreach ($matches[1] as $event_data) {
			$event = array();
			
			// DTSTART
			if (preg_match('/DTSTART[^:]*:([0-9TZ]+)/', $event_data, $dtstart)) {
				$event['start'] = $this->parse_ical_datetime($dtstart[1]);
			}
			
			// DTEND
			if (preg_match('/DTEND[^:]*:([0-9TZ]+)/', $event_data, $dtend)) {
				$event['end'] = $this->parse_ical_datetime($dtend[1]);
			}
			
			// SUMMARY
			if (preg_match('/SUMMARY:(.*)/', $event_data, $summary)) {
				$event['summary'] = trim($summary[1]);
			}
			
			// STATUS (FREI = verfügbar, BUSY = belegt)
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
	private function parse_ical_datetime($datetime) {
		// Format: YYYYMMDDTHHMMSS oder YYYYMMDDTHHMMSSZ
		$datetime = str_replace(array('T', 'Z'), array(' ', ''), $datetime);
		
		if (strlen($datetime) === 15) {
			// YYYYMMDD HHMMSS
			$date = substr($datetime, 0, 8);
			$time = substr($datetime, 9, 6);
			$formatted = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2) . ' ' .
				substr($time, 0, 2) . ':' . substr($time, 2, 2) . ':' . substr($time, 4, 2);
			return $formatted;
		}
		
		return '';
	}

	/**
	 * Verfügbare Slots generieren (Fallback wenn keine Events im Kalender)
	 *
	 * @param string $start_date Startdatum
	 * @param string $end_date Enddatum
	 * @param array $booked_events Gebuchte Events (werden als belegt markiert)
	 * @return array Verfügbare Slots
	 */
	private function generate_available_slots($start_date, $end_date, $booked_events) {
		$available = array();
		
		$start = new DateTime($start_date);
		$end = new DateTime($end_date);
		$end->modify('+1 day');
		
		$current = clone $start;
		
		// Alle möglichen 1-Stunden-Slots generieren (z.B. 10:00-23:00)
		$start_hour = (int) get_option('ltb_start_hour', 10);
		$end_hour = (int) get_option('ltb_end_hour', 23);
		
		while ($current < $end) {
			$date_str = $current->format('Y-m-d');
			
			for ($hour = $start_hour; $hour < $end_hour; $hour++) {
				$slot_start = $date_str . ' ' . str_pad($hour, 2, '0', STR_PAD_LEFT) . ':00:00';
				$slot_end = $date_str . ' ' . str_pad($hour + 1, 2, '0', STR_PAD_LEFT) . ':00:00';
				
				// Prüfen ob Slot belegt ist
				$is_booked = false;
				foreach ($booked_events as $event) {
					if (empty($event['start']) || empty($event['end'])) {
						continue;
					}
					$event_start = new DateTime($event['start']);
					$event_end = new DateTime($event['end']);
					$slot_start_dt = new DateTime($slot_start);
					$slot_end_dt = new DateTime($slot_end);
					
					// Überschneidung prüfen
					if ($slot_start_dt < $event_end && $slot_end_dt > $event_start) {
						$is_booked = true;
						break;
					}
				}
				
				if (!$is_booked) {
					$available[] = array(
						'date' => $date_str,
						'start' => $slot_start,
						'end' => $slot_end,
						'hour' => $hour,
					);
				}
			}
			
			$current->modify('+1 day');
		}
		
		return $available;
	}

	/**
	 * Datum für CalDAV formatieren
	 *
	 * @param string $datetime MySQL-Datetime
	 * @return string CalDAV-Datetime
	 */
	private function format_caldav_date($datetime) {
		$dt = new DateTime($datetime);
		return $dt->format('Ymd\THis\Z');
	}

	/**
	 * Event im Kalender erstellen oder aktualisieren
	 *
	 * @param string $date Datum (Y-m-d)
	 * @param string $start_time Startzeit (H:i:s)
	 * @param int $duration Dauer in Stunden
	 * @param string $summary Titel/Summary des Events
	 * @return bool|WP_Error Erfolg oder Fehler
	 */
	public function create_event($date, $start_time, $duration, $summary = 'BELEGT') {
		if (empty($this->dav_url) || empty($this->username) || empty($this->password)) {
			return new WP_Error('dav_config', __('DAV-Konfiguration fehlt.', 'lasertagpro-buchung'));
		}

		// Start- und Endzeit berechnen
		$start_datetime = $date . ' ' . $start_time;
		$start_obj = new DateTime($start_datetime);
		$end_obj = clone $start_obj;
		$end_obj->modify('+' . $duration . ' hours');
		
		$start_hour = (int) $start_obj->format('G');
		
		// ROBUSTE LÖSUNG: Hole ALLE Events für den gesamten Tag und finde alle FREI-Events, die überlappen
		error_log('LTB DAV: Suche nach FREI-Events für ' . $date . ' ab Stunde ' . $start_hour . ' (Dauer: ' . $duration . ' Stunden)');
		
		// Hole ALLE Events für den Tag (nicht nur für einzelne Stunden)
		$all_free_events = $this->find_all_free_events_for_date($date, $start_hour, $duration);
		
		error_log('LTB DAV: Gefundene FREI-Events: ' . count($all_free_events));
		
		// Wenn FREI-Events gefunden wurden, aktualisiere das erste und lösche die restlichen
		if (!empty($all_free_events)) {
			$existing_free_event = $all_free_events[0]; // Nimm das erste Event
			error_log('LTB DAV: ' . count($all_free_events) . ' FREI-Event(s) gefunden. Aktualisiere erstes Event: ' . $existing_free_event['url']);
		
			if ($existing_free_event && !empty($existing_free_event['url'])) {
				// Bestehenden FREI-Event aktualisieren
				error_log('LTB DAV: FREI-Event gefunden! URL: ' . $existing_free_event['url'] . ', UID: ' . (isset($existing_free_event['uid']) ? $existing_free_event['uid'] : 'nicht gefunden'));
			
			// UID aus bestehendem Event übernehmen (wichtig für Update!)
			$uid = isset($existing_free_event['uid']) && !empty($existing_free_event['uid']) ? $existing_free_event['uid'] : 'ltb-' . time() . '-' . rand(1000, 9999) . '@lasertagpro.at';
			$dtstart = $start_obj->format('Ymd\THis');
			$dtend = $end_obj->format('Ymd\THis');
			$dtstamp = date('Ymd\THis\Z');
			
			$ical = "BEGIN:VCALENDAR\r\n";
			$ical .= "VERSION:2.0\r\n";
			$ical .= "PRODID:-//LaserTagPro//Booking System//EN\r\n";
			$ical .= "BEGIN:VEVENT\r\n";
			$ical .= "UID:" . $uid . "\r\n";
			$ical .= "DTSTAMP:" . $dtstamp . "\r\n";
			$ical .= "DTSTART:" . $dtstart . "\r\n";
			$ical .= "DTEND:" . $dtend . "\r\n";
			$ical .= "SUMMARY:" . $summary . "\r\n";
			$ical .= "STATUS:CONFIRMED\r\n";
			$ical .= "END:VEVENT\r\n";
			$ical .= "END:VCALENDAR\r\n";
			
			error_log('LTB DAV: Sende PUT-Request an: ' . $existing_free_event['url']);
			error_log('LTB DAV: iCal-Body (erste 200 Zeichen): ' . substr($ical, 0, 200));
			
			// PUT Request zum Aktualisieren des bestehenden Events
			$args = array(
				'method' => 'PUT',
				'headers' => array(
					'Content-Type' => 'text/calendar; charset=utf-8',
				),
				'body' => $ical,
				'timeout' => 30,
			);
			
			$response = wp_remote_request($existing_free_event['url'], $this->add_auth($args));
			
			if (is_wp_error($response)) {
				error_log('LTB DAV Update Error: ' . $response->get_error_message());
				// Fallback: Versuche zu löschen und neu zu erstellen
				error_log('LTB DAV: Fallback - lösche FREI-Event und erstelle neues');
				$this->delete_free_slot($date, $start_hour);
			} else {
				$code = wp_remote_retrieve_response_code($response);
				error_log('LTB DAV: PUT-Response Code: ' . $code);
				
				if ($code >= 200 && $code < 300) {
					error_log('LTB DAV: Event erfolgreich aktualisiert: ' . $existing_free_event['url']);
					
					// WICHTIG: Lösche ALLE anderen gefundenen FREI-Events SOFORT
					$updated_url = $existing_free_event['url'];
					$deleted_count = 0;
					foreach ($all_free_events as $free_event) {
						if ($free_event['url'] !== $updated_url) {
							error_log('LTB DAV: Lösche zusätzliches FREI-Event: ' . $free_event['url']);
							if ($this->delete_event_by_url($free_event['url'])) {
								$deleted_count++;
							}
						}
					}
					error_log('LTB DAV: ' . $deleted_count . ' zusätzliche FREI-Event(s) gelöscht');
					
					// Zusätzlich: Lösche alle weiteren FREI-Events für alle betroffenen Stunden (falls welche übersehen wurden)
					for ($i = 0; $i < $duration; $i++) {
						$check_hour = $start_hour + $i;
						error_log('LTB DAV: Lösche alle weiteren FREI-Events für Stunde ' . $check_hour . ' (außer aktualisiertem)');
						$deleted_in_hour = $this->delete_all_free_slots_for_hour($date, $check_hour, $updated_url);
						if ($deleted_in_hour > 0) {
							error_log('LTB DAV: ' . $deleted_in_hour . ' weitere FREI-Event(s) für Stunde ' . $check_hour . ' gelöscht');
						}
					}
					
					return true;
				} else {
					$response_body = wp_remote_retrieve_body($response);
					error_log('LTB DAV Update Error: HTTP ' . $code . ', Body: ' . substr($response_body, 0, 500));
					// Fallback: Versuche zu löschen und neu zu erstellen
					error_log('LTB DAV: Fallback - lösche FREI-Event und erstelle neues');
					$this->delete_free_slot($date, $start_hour);
				}
			}
			} else {
				error_log('LTB DAV: FREI-Event gefunden, aber URL ist leer');
			}
		} else {
			error_log('LTB DAV: Kein FREI-Event gefunden für alle betroffenen Stunden');
		}
		
		// Kein FREI-Event gefunden oder Update fehlgeschlagen - lösche alle betroffenen FREI-Events und erstelle neues
		error_log('LTB DAV: Lösche alle betroffenen FREI-Events und erstelle neues BELEGT-Event');
		
		// Alle betroffenen FREI-Events löschen (für jede Stunde)
		for ($i = 0; $i < $duration; $i++) {
			$check_hour = $start_hour + $i;
			error_log('LTB DAV: Lösche FREI-Event für Stunde ' . $check_hour);
			$this->delete_free_slot($date, $check_hour);
		}
		
		// iCal-Event erstellen
		$uid = 'ltb-' . time() . '-' . rand(1000, 9999) . '@lasertagpro.at';
		$dtstart = $start_obj->format('Ymd\THis');
		$dtend = $end_obj->format('Ymd\THis');
		$dtstamp = date('Ymd\THis\Z');
		
		$ical = "BEGIN:VCALENDAR\r\n";
		$ical .= "VERSION:2.0\r\n";
		$ical .= "PRODID:-//LaserTagPro//Booking System//EN\r\n";
		$ical .= "BEGIN:VEVENT\r\n";
		$ical .= "UID:" . $uid . "\r\n";
		$ical .= "DTSTAMP:" . $dtstamp . "\r\n";
		$ical .= "DTSTART:" . $dtstart . "\r\n";
		$ical .= "DTEND:" . $dtend . "\r\n";
		$ical .= "SUMMARY:" . $summary . "\r\n";
		$ical .= "STATUS:CONFIRMED\r\n";
		$ical .= "END:VEVENT\r\n";
		$ical .= "END:VCALENDAR\r\n";
		
		// Event-URL generieren (basierend auf Datum und Startzeit)
		$event_filename = 'ltb-' . $date . '-' . str_replace(':', '', substr($start_time, 0, 5)) . '.ics';
		$event_url = rtrim($this->dav_url, '/') . '/' . $event_filename;
		
		// PUT Request zum Erstellen des Events
		$args = array(
			'method' => 'PUT',
			'headers' => array(
				'Content-Type' => 'text/calendar; charset=utf-8',
			),
			'body' => $ical,
			'timeout' => 30,
		);
		
		$response = wp_remote_request($event_url, $this->add_auth($args));
		
		if (is_wp_error($response)) {
			error_log('LTB DAV Create Error: ' . $response->get_error_message());
			return new WP_Error('dav_error', __('Fehler beim Erstellen des Kalender-Events.', 'lasertagpro-buchung'));
		}
		
		$code = wp_remote_retrieve_response_code($response);
		
		if ($code >= 200 && $code < 300) {
			error_log('LTB DAV: Event erfolgreich erstellt: ' . $event_url);
			return true;
		} else {
			error_log('LTB DAV Create Error: HTTP ' . $code);
			return new WP_Error('dav_error', __('Fehler beim Erstellen des Kalender-Events. HTTP ' . $code, 'lasertagpro-buchung'));
		}
	}

	/**
	 * Alle FREI-Events für einen Zeitraum finden
	 *
	 * @param string $date Datum (Y-m-d)
	 * @param int $start_hour Startstunde (0-23)
	 * @param int $duration Dauer in Stunden
	 * @return array Array von Event-Infos (url, uid)
	 */
	private function find_all_free_events_for_date($date, $start_hour, $duration) {
		if (empty($this->dav_url) || empty($this->username) || empty($this->password)) {
			return array();
		}

		// Hole ALLE Events für den gesamten Tag
		$url = rtrim($this->dav_url, '/') . '/';
		
		$start_time_str = str_pad($start_hour, 2, '0', STR_PAD_LEFT) . ':00:00';
		$end_hour = $start_hour + $duration;
		$end_time_str = str_pad($end_hour, 2, '0', STR_PAD_LEFT) . ':00:00';
		
		$body = '<?xml version="1.0" encoding="UTF-8"?>
<C:calendar-query xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
	<D:prop>
		<D:getetag/>
		<D:getcontenttype/>
		<D:href/>
		<C:calendar-data/>
	</D:prop>
	<C:filter>
		<C:comp-filter name="VCALENDAR">
			<C:comp-filter name="VEVENT">
				<C:time-range start="' . $this->format_caldav_date($date . 'T00:00:00') . '" end="' . $this->format_caldav_date($date . 'T23:59:59') . '"/>
			</C:comp-filter>
		</C:comp-filter>
	</C:filter>
</C:calendar-query>';

		$args = array(
			'method' => 'REPORT',
			'headers' => array(
				'Content-Type' => 'application/xml; charset=utf-8',
				'Depth' => '1',
			),
			'body' => $body,
			'timeout' => 30,
		);

		$response = wp_remote_request($url, $this->add_auth($args));
		
		if (is_wp_error($response)) {
			error_log('LTB DAV Find All Free Events Error: ' . $response->get_error_message());
			return array();
		}

		$body = wp_remote_retrieve_body($response);
		$code = wp_remote_retrieve_response_code($response);

		if ($code !== 207) {
			error_log('LTB DAV Find All Free Events Error: HTTP ' . $code);
			return array();
		}

		// XML parsen
		libxml_use_internal_errors(true);
		$xml = simplexml_load_string($body);
		
		if ($xml === false) {
			error_log('LTB DAV: Could not parse XML for find_all_free_events_for_date');
			return array();
		}

		$xml->registerXPathNamespace('d', 'DAV:');
		$xml->registerXPathNamespace('c', 'urn:ietf:params:xml:ns:caldav');

		$responses = $xml->xpath('//d:response');
		$free_events = array();
		
		error_log('LTB DAV find_all_free_events_for_date: Gefundene Responses: ' . count($responses));
		
		// Berechne den Buchungszeitraum
		$booking_start = new DateTime($date . ' ' . $start_time_str);
		$booking_end = new DateTime($date . ' ' . $end_time_str);
		
		foreach ($responses as $response) {
			$href_elements = $response->xpath('.//d:href');
			$href = !empty($href_elements) ? (string) $href_elements[0] : '';
			
			$calendar_data = $response->xpath('.//c:calendar-data');
			
			if (empty($calendar_data)) {
				continue;
			}
			
			$ical = (string) $calendar_data[0];
			
			// Prüfen ob "FREI" im Summary steht
			if (stripos($ical, 'SUMMARY:') !== false) {
				if (preg_match('/SUMMARY:([^\r\n]+)/', $ical, $summary_matches)) {
					$summary = trim($summary_matches[1]);
					$summary_upper = strtoupper($summary);
					
					// Wenn "FREI" im Summary steht, prüfe ob es mit dem Buchungszeitraum überlappt
					if (strpos($summary_upper, 'FREI') !== false || strpos($summary_upper, 'FREE') !== false || strpos($summary_upper, 'AVAILABLE') !== false) {
						// Event-Zeiten extrahieren
						$event_start = null;
						$event_end = null;
						
						if (preg_match('/DTSTART[^:]*:([0-9TZ]+)/', $ical, $dtstart)) {
							$event_start = new DateTime($this->parse_ical_datetime($dtstart[1]));
						}
						if (preg_match('/DTEND[^:]*:([0-9TZ]+)/', $ical, $dtend)) {
							$event_end = new DateTime($this->parse_ical_datetime($dtend[1]));
						}
						
						// Prüfe ob Event mit Buchungszeitraum überlappt
						if ($event_start && $event_end) {
							if ($event_start < $booking_end && $event_end > $booking_start) {
								// Event-URL aus href extrahieren
								$event_url = '';
								
								if (!empty($href)) {
									if (strpos($href, 'http') === 0) {
										$event_url = $href;
									} else {
										$href_clean = ltrim($href, '/');
										$dav_url_clean = rtrim($this->dav_url, '/');
										$event_url = $dav_url_clean . '/' . $href_clean;
									}
								}
								
								// UID extrahieren
								$uid = '';
								if (preg_match('/UID:([^\r\n]+)/', $ical, $uid_matches)) {
									$uid = trim($uid_matches[1]);
								}
								
								if (!empty($event_url)) {
									error_log('LTB DAV: FREI-Event gefunden, das überlappt: ' . $event_url . ' (Zeit: ' . $event_start->format('H:i') . '-' . $event_end->format('H:i') . ')');
									$free_events[] = array(
										'url' => $event_url,
										'uid' => $uid,
									);
								}
							}
						}
					}
				}
			}
		}
		
		return $free_events;
	}

	/**
	 * FREI-Event finden
	 *
	 * @param string $date Datum (Y-m-d)
	 * @param int $hour Stunde (0-23)
	 * @return array|false Event-Info (url, uid) oder false
	 */
	private function find_free_event($date, $hour) {
		if (empty($this->dav_url) || empty($this->username) || empty($this->password)) {
			return false;
		}

		// CalDAV REPORT Request für Events an diesem Tag und dieser Stunde
		$url = rtrim($this->dav_url, '/') . '/';
		
		$hour_str = str_pad($hour, 2, '0', STR_PAD_LEFT);
		$next_hour_str = str_pad($hour + 1, 2, '0', STR_PAD_LEFT);
		
		$body = '<?xml version="1.0" encoding="UTF-8"?>
<C:calendar-query xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
	<D:prop>
		<D:getetag/>
		<D:getcontenttype/>
		<D:href/>
		<C:calendar-data/>
	</D:prop>
	<C:filter>
		<C:comp-filter name="VCALENDAR">
			<C:comp-filter name="VEVENT">
				<C:time-range start="' . $this->format_caldav_date($date . 'T' . $hour_str . ':00:00') . '" end="' . $this->format_caldav_date($date . 'T' . $next_hour_str . ':00:00') . '"/>
			</C:comp-filter>
		</C:comp-filter>
	</C:filter>
</C:calendar-query>';

		$args = array(
			'method' => 'REPORT',
			'headers' => array(
				'Content-Type' => 'application/xml; charset=utf-8',
				'Depth' => '1',
			),
			'body' => $body,
			'timeout' => 30,
		);

		$response = wp_remote_request($url, $this->add_auth($args));
		
		if (is_wp_error($response)) {
			error_log('LTB DAV Find Free Event Error: ' . $response->get_error_message());
			return false;
		}

		$body = wp_remote_retrieve_body($response);
		$code = wp_remote_retrieve_response_code($response);

		if ($code !== 207) {
			error_log('LTB DAV Find Free Event Error: HTTP ' . $code);
			return false;
		}

		// XML parsen und Events mit "FREI" im Summary finden
		libxml_use_internal_errors(true);
		$xml = simplexml_load_string($body);
		
		if ($xml === false) {
			error_log('LTB DAV: Could not parse XML for find_free_event');
			return false;
		}

		$xml->registerXPathNamespace('d', 'DAV:');
		$xml->registerXPathNamespace('c', 'urn:ietf:params:xml:ns:caldav');

		// href und calendar-data finden
		$responses = $xml->xpath('//d:response');
		
		error_log('LTB DAV find_free_event: Gefundene Responses: ' . count($responses));
		
		foreach ($responses as $response) {
			$href_elements = $response->xpath('.//d:href');
			$href = !empty($href_elements) ? (string) $href_elements[0] : '';
			
			error_log('LTB DAV find_free_event: Prüfe Response mit href: ' . $href);
			
			$calendar_data = $response->xpath('.//c:calendar-data');
			
			if (empty($calendar_data)) {
				error_log('LTB DAV find_free_event: Keine calendar-data gefunden');
				continue;
			}
			
			$ical = (string) $calendar_data[0];
			
			// Prüfen ob "FREI" im Summary steht
			if (stripos($ical, 'SUMMARY:') !== false) {
				// Summary extrahieren
				if (preg_match('/SUMMARY:([^\r\n]+)/', $ical, $summary_matches)) {
					$summary = trim($summary_matches[1]);
					$summary_upper = strtoupper($summary);
					
					error_log('LTB DAV find_free_event: Event Summary: ' . $summary);
					
					// Wenn "FREI" im Summary steht, Event-Info zurückgeben
					if (strpos($summary_upper, 'FREI') !== false || strpos($summary_upper, 'FREE') !== false || strpos($summary_upper, 'AVAILABLE') !== false) {
						// Event-URL aus href extrahieren
						$event_url = '';
						
						if (!empty($href)) {
							// href kann absolut oder relativ sein
							if (strpos($href, 'http') === 0) {
								$event_url = $href;
							} else {
								// href ist relativ, muss zur DAV-URL hinzugefügt werden
								// Entferne führende Slashes von href, da DAV-URL bereits mit / endet
								$href_clean = ltrim($href, '/');
								$dav_url_clean = rtrim($this->dav_url, '/');
								$event_url = $dav_url_clean . '/' . $href_clean;
							}
						} else {
							error_log('LTB DAV find_free_event: WARNUNG - href ist leer!');
						}
						
						// UID extrahieren
						$uid = '';
						if (preg_match('/UID:([^\r\n]+)/', $ical, $uid_matches)) {
							$uid = trim($uid_matches[1]);
						}
						
						if (!empty($event_url)) {
							error_log('LTB DAV find_free_event: FREI-Event gefunden! URL: ' . $event_url . ', UID: ' . ($uid ? $uid : 'nicht gefunden') . ', Summary: ' . $summary);
							return array(
								'url' => $event_url,
								'uid' => $uid,
							);
						} else {
							error_log('LTB DAV find_free_event: FREI-Event gefunden, aber URL ist leer! href: ' . $href);
						}
					} else {
						error_log('LTB DAV find_free_event: Event ist nicht FREI (Summary: ' . $summary . ')');
					}
				}
			} else {
				error_log('LTB DAV find_free_event: Event hat kein SUMMARY');
			}
		}
		
		error_log('LTB DAV find_free_event: Kein FREI-Event gefunden');
		return false;
	}

	/**
	 * Event anhand der URL löschen
	 *
	 * @param string $event_url Event-URL
	 * @return bool Erfolg
	 */
	private function delete_event_by_url($event_url) {
		if (empty($event_url)) {
			return false;
		}
		
		$delete_args = array(
			'method' => 'DELETE',
			'timeout' => 30,
		);
		
		$delete_response = wp_remote_request($event_url, $this->add_auth($delete_args));
		
		if (is_wp_error($delete_response)) {
			error_log('LTB DAV delete_event_by_url Error: ' . $delete_response->get_error_message());
			return false;
		}
		
		$delete_code = wp_remote_retrieve_response_code($delete_response);
		if ($delete_code >= 200 && $delete_code < 300) {
			error_log('LTB DAV: Event erfolgreich gelöscht: ' . $event_url);
			return true;
		} else {
			error_log('LTB DAV delete_event_by_url: HTTP ' . $delete_code . ' für: ' . $event_url);
			return false;
		}
	}

	/**
	 * Alle FREI-Slots für eine Stunde löschen (außer einer bestimmten URL)
	 *
	 * @param string $date Datum (Y-m-d)
	 * @param int $hour Stunde (0-23)
	 * @param string $exclude_url URL, die nicht gelöscht werden soll
	 * @return int Anzahl gelöschter Events
	 */
	private function delete_all_free_slots_for_hour($date, $hour, $exclude_url = '') {
		if (empty($this->dav_url) || empty($this->username) || empty($this->password)) {
			return 0;
		}

		// CalDAV REPORT Request für Events an diesem Tag und dieser Stunde
		$url = rtrim($this->dav_url, '/') . '/';
		
		$hour_str = str_pad($hour, 2, '0', STR_PAD_LEFT);
		$next_hour_str = str_pad($hour + 1, 2, '0', STR_PAD_LEFT);
		
		$body = '<?xml version="1.0" encoding="UTF-8"?>
<C:calendar-query xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
	<D:prop>
		<D:getetag/>
		<D:getcontenttype/>
		<D:href/>
		<C:calendar-data/>
	</D:prop>
	<C:filter>
		<C:comp-filter name="VCALENDAR">
			<C:comp-filter name="VEVENT">
				<C:time-range start="' . $this->format_caldav_date($date . 'T' . $hour_str . ':00:00') . '" end="' . $this->format_caldav_date($date . 'T' . $next_hour_str . ':00:00') . '"/>
			</C:comp-filter>
		</C:comp-filter>
	</C:filter>
</C:calendar-query>';

		$args = array(
			'method' => 'REPORT',
			'headers' => array(
				'Content-Type' => 'application/xml; charset=utf-8',
				'Depth' => '1',
			),
			'body' => $body,
			'timeout' => 30,
		);

		$response = wp_remote_request($url, $this->add_auth($args));
		
		if (is_wp_error($response)) {
			return 0;
		}

		$body = wp_remote_retrieve_body($response);
		$code = wp_remote_retrieve_response_code($response);

		if ($code !== 207) {
			return 0;
		}

		// XML parsen
		libxml_use_internal_errors(true);
		$xml = simplexml_load_string($body);
		
		if ($xml === false) {
			return 0;
		}

		$xml->registerXPathNamespace('d', 'DAV:');
		$xml->registerXPathNamespace('c', 'urn:ietf:params:xml:ns:caldav');

		$responses = $xml->xpath('//d:response');
		$deleted_count = 0;
		
		foreach ($responses as $response) {
			$href_elements = $response->xpath('.//d:href');
			$href = !empty($href_elements) ? (string) $href_elements[0] : '';
			
			$calendar_data = $response->xpath('.//c:calendar-data');
			
			if (empty($calendar_data)) {
				continue;
			}
			
			$ical = (string) $calendar_data[0];
			
			// Prüfen ob "FREI" im Summary steht
			if (stripos($ical, 'SUMMARY:') !== false) {
				if (preg_match('/SUMMARY:([^\r\n]+)/', $ical, $summary_matches)) {
					$summary = trim($summary_matches[1]);
					$summary_upper = strtoupper($summary);
					
					if (strpos($summary_upper, 'FREI') !== false || strpos($summary_upper, 'FREE') !== false || strpos($summary_upper, 'AVAILABLE') !== false) {
						// Event-URL aus href extrahieren
						$event_url = '';
						
						if (!empty($href)) {
							if (strpos($href, 'http') === 0) {
								$event_url = $href;
							} else {
								$href_clean = ltrim($href, '/');
								$dav_url_clean = rtrim($this->dav_url, '/');
								$event_url = $dav_url_clean . '/' . $href_clean;
							}
						}
						
						// Nur löschen, wenn es nicht die ausgeschlossene URL ist
						if (!empty($event_url) && $event_url !== $exclude_url) {
							error_log('LTB DAV: Lösche zusätzliches FREI-Event: ' . $event_url);
							
							$delete_args = array(
								'method' => 'DELETE',
								'timeout' => 30,
							);
							
							$delete_response = wp_remote_request($event_url, $this->add_auth($delete_args));
							
							if (!is_wp_error($delete_response)) {
								$delete_code = wp_remote_retrieve_response_code($delete_response);
								if ($delete_code >= 200 && $delete_code < 300) {
									$deleted_count++;
								}
							}
						}
					}
				}
			}
		}
		
		return $deleted_count;
	}

	/**
	 * FREI-Slot löschen
	 *
	 * @param string $date Datum (Y-m-d)
	 * @param int $hour Stunde (0-23)
	 * @return bool Erfolg
	 */
	private function delete_free_slot($date, $hour) {
		if (empty($this->dav_url) || empty($this->username) || empty($this->password)) {
			return false;
		}

		// CalDAV REPORT Request für Events an diesem Tag und dieser Stunde
		$url = rtrim($this->dav_url, '/') . '/';
		
		$hour_str = str_pad($hour, 2, '0', STR_PAD_LEFT);
		$next_hour_str = str_pad($hour + 1, 2, '0', STR_PAD_LEFT);
		
		$body = '<?xml version="1.0" encoding="UTF-8"?>
<C:calendar-query xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
	<D:prop>
		<D:getetag/>
		<D:getcontenttype/>
		<C:calendar-data/>
	</D:prop>
	<C:filter>
		<C:comp-filter name="VCALENDAR">
			<C:comp-filter name="VEVENT">
				<C:time-range start="' . $this->format_caldav_date($date . 'T' . $hour_str . ':00:00') . '" end="' . $this->format_caldav_date($date . 'T' . $next_hour_str . ':00:00') . '"/>
			</C:comp-filter>
		</C:comp-filter>
	</C:filter>
</C:calendar-query>';

		$args = array(
			'method' => 'REPORT',
			'headers' => array(
				'Content-Type' => 'application/xml; charset=utf-8',
				'Depth' => '1',
			),
			'body' => $body,
			'timeout' => 30,
		);

		$response = wp_remote_request($url, $this->add_auth($args));
		
		if (is_wp_error($response)) {
			error_log('LTB DAV Delete Free Slot Error: ' . $response->get_error_message());
			return false;
		}

		$body = wp_remote_retrieve_body($response);
		$code = wp_remote_retrieve_response_code($response);

		if ($code !== 207) {
			error_log('LTB DAV Delete Free Slot Error: HTTP ' . $code);
			return false;
		}

		// XML parsen und Events mit "FREI" im Summary finden
		libxml_use_internal_errors(true);
		$xml = simplexml_load_string($body);
		
		if ($xml === false) {
			error_log('LTB DAV: Could not parse XML for delete_free_slot');
			return false;
		}

		$xml->registerXPathNamespace('d', 'DAV:');
		$xml->registerXPathNamespace('c', 'urn:ietf:params:xml:ns:caldav');

		// href und calendar-data finden
		$responses = $xml->xpath('//d:response');
		
		error_log('LTB DAV: Gefundene Responses: ' . count($responses));
		
		$deleted_count = 0;
		
		foreach ($responses as $response) {
			$href_elements = $response->xpath('.//d:href');
			$href = !empty($href_elements) ? (string) $href_elements[0] : '';
			
			$calendar_data = $response->xpath('.//c:calendar-data');
			
			if (empty($calendar_data)) {
				continue;
			}
			
			$ical = (string) $calendar_data[0];
			
			// Prüfen ob "FREI" im Summary steht
			if (stripos($ical, 'SUMMARY:') !== false) {
				// Summary extrahieren
				if (preg_match('/SUMMARY:([^\r\n]+)/', $ical, $summary_matches)) {
					$summary = trim($summary_matches[1]);
					$summary_upper = strtoupper($summary);
					
					error_log('LTB DAV: Gefundenes Event - Summary: ' . $summary . ', href: ' . $href);
					
					// Wenn "FREI" im Summary steht, Event löschen
					if (strpos($summary_upper, 'FREI') !== false || strpos($summary_upper, 'FREE') !== false || strpos($summary_upper, 'AVAILABLE') !== false) {
						// Event-URL aus href extrahieren
						$event_url = '';
						
						if (!empty($href)) {
							// href kann absolut oder relativ sein
							if (strpos($href, 'http') === 0) {
								$event_url = $href;
							} else {
								// href ist relativ, muss zur DAV-URL hinzugefügt werden
								// href könnte bereits den vollständigen Pfad enthalten
								$event_url = rtrim($this->dav_url, '/') . '/' . ltrim($href, '/');
							}
						} else {
							// Fallback: Event-URL generieren (versuche verschiedene Formate basierend auf Summary)
							// Extrahiere Zeit aus Summary (z.B. "18:00 FREI" -> "18:00")
							$time_from_summary = '';
							if (preg_match('/(\d{1,2}):(\d{2})/', $summary, $time_matches)) {
								$time_from_summary = str_pad($time_matches[1], 2, '0', STR_PAD_LEFT) . $time_matches[2];
							}
							
							$possible_filenames = array();
							if ($time_from_summary) {
								$possible_filenames[] = $time_from_summary . ' FREI.ics';
								$possible_filenames[] = 'FREI ' . $time_from_summary . '.ics';
								$possible_filenames[] = $date . '-' . $time_from_summary . '-FREI.ics';
							}
							$possible_filenames[] = $hour_str . '00 FREI.ics';
							$possible_filenames[] = 'FREI ' . $hour_str . ':00.ics';
							$possible_filenames[] = $date . '-' . $hour_str . '00-FREI.ics';
							$possible_filenames[] = 'ltb-' . $date . '-' . $hour_str . '00.ics';
							
							// Versuche jedes Format
							foreach ($possible_filenames as $filename) {
								$test_url = rtrim($this->dav_url, '/') . '/' . $filename;
								// Prüfen ob Event existiert (HEAD Request)
								$head_args = array(
									'method' => 'HEAD',
									'timeout' => 10,
								);
								$head_response = wp_remote_request($test_url, $this->add_auth($head_args));
								if (!is_wp_error($head_response)) {
									$head_code = wp_remote_retrieve_response_code($head_response);
									if ($head_code >= 200 && $head_code < 300) {
										$event_url = $test_url;
										error_log('LTB DAV: Event-URL gefunden via HEAD: ' . $event_url);
										break;
									}
								}
							}
							
							// Wenn immer noch keine URL, verwende Standard-Format
							if (empty($event_url)) {
								// Versuche direkt mit verschiedenen Formaten zu löschen
								$event_url = rtrim($this->dav_url, '/') . '/' . $hour_str . ':00 FREI.ics';
							}
						}
						
						error_log('LTB DAV: Versuche FREI-Slot zu löschen: ' . $event_url);
						
						// DELETE Request
						$delete_args = array(
							'method' => 'DELETE',
							'timeout' => 30,
						);
						
						$delete_response = wp_remote_request($event_url, $this->add_auth($delete_args));
						
						if (!is_wp_error($delete_response)) {
							$delete_code = wp_remote_retrieve_response_code($delete_response);
							if ($delete_code >= 200 && $delete_code < 300) {
								error_log('LTB DAV: FREI-Slot erfolgreich gelöscht: ' . $event_url . ' (Summary: ' . $summary . ')');
								$deleted_count++;
								continue; // Weiter mit nächstem Event
							} else {
								error_log('LTB DAV: Delete Free Slot HTTP ' . $delete_code . ' für: ' . $event_url);
								// Versuche alternative URL-Formate
								$alt_urls = array();
								if ($time_from_summary) {
									$alt_urls[] = rtrim($this->dav_url, '/') . '/' . $time_from_summary . ' FREI.ics';
									$alt_urls[] = rtrim($this->dav_url, '/') . '/' . 'FREI ' . $time_from_summary . '.ics';
								}
								$alt_urls[] = rtrim($this->dav_url, '/') . '/' . $hour_str . ':00 FREI.ics';
								$alt_urls[] = rtrim($this->dav_url, '/') . '/' . 'FREI ' . $hour_str . ':00.ics';
								$alt_urls[] = rtrim($this->dav_url, '/') . '/' . $date . '-' . $hour_str . '00-FREI.ics';
								
								foreach ($alt_urls as $alt_url) {
									if ($alt_url === $event_url) continue; // Bereits versucht
									
									$alt_response = wp_remote_request($alt_url, $this->add_auth($delete_args));
									if (!is_wp_error($alt_response)) {
										$alt_code = wp_remote_retrieve_response_code($alt_response);
										if ($alt_code >= 200 && $alt_code < 300) {
											error_log('LTB DAV: FREI-Slot gelöscht mit alternativer URL: ' . $alt_url);
											$deleted_count++;
											break;
										}
									}
								}
							}
						} else {
							error_log('LTB DAV: Delete Free Slot Error: ' . $delete_response->get_error_message());
						}
					}
				}
			}
		}
		
		error_log('LTB DAV: Insgesamt ' . $deleted_count . ' FREI-Slot(s) gelöscht für Stunde ' . $hour);
		return $deleted_count > 0;
		
		return false;
	}

	/**
	 * Authentifizierung zu Request-Args hinzufügen
	 *
	 * @param array $args Request-Args
	 * @return array Request-Args mit Auth
	 */
	private function add_auth($args) {
		$args['headers']['Authorization'] = 'Basic ' . base64_encode($this->username . ':' . $this->password);
		return $args;
	}
}

