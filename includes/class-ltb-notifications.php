<?php
/**
 * Benachrichtigungen (Gotify & Telegram)
 */

if (!defined('ABSPATH')) {
	exit;
}

class LTB_Notifications {

	/**
	 * Benachrichtigung an Gotify senden
	 *
	 * @param string $title Titel
	 * @param string $message Nachricht
	 * @param int $priority Priorität (0-10, Standard: 5)
	 * @return bool|WP_Error Erfolg oder Fehler
	 */
	public static function send_gotify($title, $message, $priority = 5) {
		$gotify_url = get_option('ltb_gotify_url', '');
		$gotify_token = get_option('ltb_gotify_token', '');
		
		if (empty($gotify_url) || empty($gotify_token)) {
			return new WP_Error('gotify_config', __('Gotify-Konfiguration fehlt.', 'lasertagpro-buchung'));
		}
		
		// URL normalisieren (ohne trailing slash)
		$gotify_url = rtrim($gotify_url, '/');
		
		$url = $gotify_url . '/message?token=' . urlencode($gotify_token);
		
		$body = array(
			'title' => $title,
			'message' => $message,
			'priority' => absint($priority),
		);
		
		$args = array(
			'method' => 'POST',
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body' => wp_json_encode($body),
			'timeout' => 10,
		);
		
		$response = wp_remote_request($url, $args);
		
		if (is_wp_error($response)) {
			error_log('LTB Gotify Error: ' . $response->get_error_message());
			return $response;
		}
		
		$code = wp_remote_retrieve_response_code($response);
		
		if ($code >= 200 && $code < 300) {
			return true;
		} else {
			$body = wp_remote_retrieve_body($response);
			error_log('LTB Gotify Error: HTTP ' . $code . ' - ' . $body);
			return new WP_Error('gotify_error', __('Fehler beim Senden der Gotify-Benachrichtigung. HTTP ' . $code, 'lasertagpro-buchung'));
		}
	}

	/**
	 * Benachrichtigung an Telegram senden
	 *
	 * @param string $message Nachricht
	 * @param string $parse_mode Parse-Modus (HTML, Markdown, MarkdownV2)
	 * @return bool|WP_Error Erfolg oder Fehler
	 */
	public static function send_telegram($message, $parse_mode = 'HTML') {
		$telegram_bot_token = get_option('ltb_telegram_bot_token', '');
		$telegram_chat_id = get_option('ltb_telegram_chat_id', '');
		
		if (empty($telegram_bot_token) || empty($telegram_chat_id)) {
			return new WP_Error('telegram_config', __('Telegram-Konfiguration fehlt.', 'lasertagpro-buchung'));
		}
		
		$url = 'https://api.telegram.org/bot' . urlencode($telegram_bot_token) . '/sendMessage';
		
		$body = array(
			'chat_id' => $telegram_chat_id,
			'text' => $message,
			'parse_mode' => $parse_mode,
			'disable_web_page_preview' => true,
		);
		
		$args = array(
			'method' => 'POST',
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body' => wp_json_encode($body),
			'timeout' => 10,
		);
		
		$response = wp_remote_request($url, $args);
		
		if (is_wp_error($response)) {
			error_log('LTB Telegram Error: ' . $response->get_error_message());
			return $response;
		}
		
		$code = wp_remote_retrieve_response_code($response);
		
		if ($code >= 200 && $code < 300) {
			$response_body = json_decode(wp_remote_retrieve_body($response), true);
			if (isset($response_body['ok']) && $response_body['ok'] === true) {
				return true;
			} else {
				$error_msg = isset($response_body['description']) ? $response_body['description'] : __('Unbekannter Fehler', 'lasertagpro-buchung');
				error_log('LTB Telegram Error: ' . $error_msg);
				return new WP_Error('telegram_error', __('Fehler beim Senden der Telegram-Benachrichtigung: ', 'lasertagpro-buchung') . $error_msg);
			}
		} else {
			$body = wp_remote_retrieve_body($response);
			error_log('LTB Telegram Error: HTTP ' . $code . ' - ' . $body);
			return new WP_Error('telegram_error', __('Fehler beim Senden der Telegram-Benachrichtigung. HTTP ' . $code, 'lasertagpro-buchung'));
		}
	}

	/**
	 * Benachrichtigung bei neuer Reservierung senden
	 *
	 * @param object $reservation Reservierung
	 * @return void
	 */
	public static function notify_new_reservation($reservation) {
		$enabled_gotify = get_option('ltb_gotify_enabled', false);
		$enabled_telegram = get_option('ltb_telegram_enabled', false);
		
		if (!$enabled_gotify && !$enabled_telegram) {
			return;
		}
		
		$date_formatted = date_i18n(get_option('date_format'), strtotime($reservation->booking_date));
		$date_only = substr($reservation->booking_date, 0, 10);
		$start_time_obj = new DateTime($date_only . ' ' . $reservation->start_time);
		$end_time_obj = new DateTime($date_only . ' ' . $reservation->end_time);
		$time_formatted = $start_time_obj->format('H:i');
		$end_time_formatted = $end_time_obj->format('H:i');
		
		$admin_url = admin_url('admin.php?page=ltb-reservations');
		
		// Gotify-Benachrichtigung
		if ($enabled_gotify) {
			$gotify_title = __('🔔 Neue Reservierungsanfrage', 'lasertagpro-buchung');
			$gotify_message = sprintf(
				__("Name: %s\nE-Mail: %s\nTelefon: %s\n\nDatum: %s\nZeit: %s - %s Uhr\nPersonen: %d\nSpielmodus: %s\nPreis: €%s\n\nStatus: %s", 'lasertagpro-buchung'),
				$reservation->name,
				$reservation->email,
				!empty($reservation->phone) ? $reservation->phone : __('Nicht angegeben', 'lasertagpro-buchung'),
				$date_formatted,
				$time_formatted,
				$end_time_formatted,
				$reservation->person_count,
				$reservation->game_mode,
				number_format($reservation->total_price, 2, ',', '.'),
				$reservation->status === 'pending' ? __('Ausstehend', 'lasertagpro-buchung') : ($reservation->status === 'confirmed' ? __('Bestätigt', 'lasertagpro-buchung') : __('Storniert', 'lasertagpro-buchung'))
			);
			
			if (!empty($reservation->message)) {
				$gotify_message .= "\n\n" . __('Nachricht:', 'lasertagpro-buchung') . "\n" . $reservation->message;
			}
			
			self::send_gotify($gotify_title, $gotify_message, 7);
		}
		
		// Telegram-Benachrichtigung
		if ($enabled_telegram) {
			$telegram_message = "<b>🔔 " . esc_html__('Neue Reservierungsanfrage', 'lasertagpro-buchung') . "</b>\n\n";
			$telegram_message .= "<b>" . esc_html__('Name:', 'lasertagpro-buchung') . "</b> " . esc_html($reservation->name) . "\n";
			$telegram_message .= "<b>" . esc_html__('E-Mail:', 'lasertagpro-buchung') . "</b> " . esc_html($reservation->email) . "\n";
			$telegram_message .= "<b>" . esc_html__('Telefon:', 'lasertagpro-buchung') . "</b> " . (!empty($reservation->phone) ? esc_html($reservation->phone) : esc_html__('Nicht angegeben', 'lasertagpro-buchung')) . "\n\n";
			$telegram_message .= "<b>" . esc_html__('Datum:', 'lasertagpro-buchung') . "</b> " . esc_html($date_formatted) . "\n";
			$telegram_message .= "<b>" . esc_html__('Zeit:', 'lasertagpro-buchung') . "</b> " . esc_html($time_formatted) . " - " . esc_html($end_time_formatted) . " " . esc_html__('Uhr', 'lasertagpro-buchung') . "\n";
			$telegram_message .= "<b>" . esc_html__('Personen:', 'lasertagpro-buchung') . "</b> " . esc_html($reservation->person_count) . "\n";
			$telegram_message .= "<b>" . esc_html__('Spielmodus:', 'lasertagpro-buchung') . "</b> " . esc_html($reservation->game_mode) . "\n";
			$telegram_message .= "<b>" . esc_html__('Preis:', 'lasertagpro-buchung') . "</b> €" . number_format($reservation->total_price, 2, ',', '.') . "\n";
			$telegram_message .= "<b>" . esc_html__('Status:', 'lasertagpro-buchung') . "</b> " . ($reservation->status === 'pending' ? esc_html__('Ausstehend', 'lasertagpro-buchung') : ($reservation->status === 'confirmed' ? esc_html__('Bestätigt', 'lasertagpro-buchung') : esc_html__('Storniert', 'lasertagpro-buchung'))) . "\n";
			
			if (!empty($reservation->message)) {
				$telegram_message .= "\n<b>" . esc_html__('Nachricht:', 'lasertagpro-buchung') . "</b>\n" . esc_html($reservation->message) . "\n";
			}
			
			$telegram_message .= "\n<a href=\"" . esc_url($admin_url) . "\">" . esc_html__('Im Admin-Bereich ansehen', 'lasertagpro-buchung') . "</a>";
			
			self::send_telegram($telegram_message);
		}
	}

	/**
	 * Benachrichtigung bei bestätigter Reservierung senden
	 *
	 * @param object $reservation Reservierung
	 * @return void
	 */
	public static function notify_confirmed_reservation($reservation) {
		$enabled_gotify = get_option('ltb_gotify_enabled', false);
		$enabled_telegram = get_option('ltb_telegram_enabled', false);
		
		if (!$enabled_gotify && !$enabled_telegram) {
			return;
		}
		
		$date_formatted = date_i18n(get_option('date_format'), strtotime($reservation->booking_date));
		$date_only = substr($reservation->booking_date, 0, 10);
		$start_time_obj = new DateTime($date_only . ' ' . $reservation->start_time);
		$end_time_obj = new DateTime($date_only . ' ' . $reservation->end_time);
		$time_formatted = $start_time_obj->format('H:i');
		$end_time_formatted = $end_time_obj->format('H:i');
		
		// Gotify-Benachrichtigung
		if ($enabled_gotify) {
			$gotify_title = __('✅ Reservierung bestätigt', 'lasertagpro-buchung');
			$gotify_message = sprintf(
				__("Reservierung #%d wurde bestätigt.\n\nName: %s\nDatum: %s\nZeit: %s - %s Uhr\nPersonen: %d", 'lasertagpro-buchung'),
				$reservation->id,
				$reservation->name,
				$date_formatted,
				$time_formatted,
				$end_time_formatted,
				$reservation->person_count
			);
			
			self::send_gotify($gotify_title, $gotify_message, 5);
		}
		
		// Telegram-Benachrichtigung
		if ($enabled_telegram) {
			$telegram_message = "<b>✅ " . esc_html__('Reservierung bestätigt', 'lasertagpro-buchung') . "</b>\n\n";
			$telegram_message .= esc_html__('Reservierung #', 'lasertagpro-buchung') . esc_html($reservation->id) . " " . esc_html__('wurde bestätigt.', 'lasertagpro-buchung') . "\n\n";
			$telegram_message .= "<b>" . esc_html__('Name:', 'lasertagpro-buchung') . "</b> " . esc_html($reservation->name) . "\n";
			$telegram_message .= "<b>" . esc_html__('Datum:', 'lasertagpro-buchung') . "</b> " . esc_html($date_formatted) . "\n";
			$telegram_message .= "<b>" . esc_html__('Zeit:', 'lasertagpro-buchung') . "</b> " . esc_html($time_formatted) . " - " . esc_html($end_time_formatted) . " " . esc_html__('Uhr', 'lasertagpro-buchung') . "\n";
			$telegram_message .= "<b>" . esc_html__('Personen:', 'lasertagpro-buchung') . "</b> " . esc_html($reservation->person_count);
			
			self::send_telegram($telegram_message);
		}
	}

	/**
	 * Benachrichtigung bei stornierter Reservierung senden
	 *
	 * @param object $reservation Reservierung
	 * @return void
	 */
	public static function notify_cancelled_reservation($reservation) {
		$enabled_gotify = get_option('ltb_gotify_enabled', false);
		$enabled_telegram = get_option('ltb_telegram_enabled', false);
		
		if (!$enabled_gotify && !$enabled_telegram) {
			return;
		}
		
		$date_formatted = date_i18n(get_option('date_format'), strtotime($reservation->booking_date));
		$date_only = substr($reservation->booking_date, 0, 10);
		$start_time_obj = new DateTime($date_only . ' ' . $reservation->start_time);
		$time_formatted = $start_time_obj->format('H:i');
		
		// Gotify-Benachrichtigung
		if ($enabled_gotify) {
			$gotify_title = __('❌ Reservierung storniert', 'lasertagpro-buchung');
			$gotify_message = sprintf(
				__("Reservierung #%d wurde storniert.\n\nName: %s\nDatum: %s\nZeit: %s Uhr\nPersonen: %d", 'lasertagpro-buchung'),
				$reservation->id,
				$reservation->name,
				$date_formatted,
				$time_formatted,
				$reservation->person_count
			);
			
			self::send_gotify($gotify_title, $gotify_message, 6);
		}
		
		// Telegram-Benachrichtigung
		if ($enabled_telegram) {
			$telegram_message = "<b>❌ " . esc_html__('Reservierung storniert', 'lasertagpro-buchung') . "</b>\n\n";
			$telegram_message .= esc_html__('Reservierung #', 'lasertagpro-buchung') . esc_html($reservation->id) . " " . esc_html__('wurde storniert.', 'lasertagpro-buchung') . "\n\n";
			$telegram_message .= "<b>" . esc_html__('Name:', 'lasertagpro-buchung') . "</b> " . esc_html($reservation->name) . "\n";
			$telegram_message .= "<b>" . esc_html__('Datum:', 'lasertagpro-buchung') . "</b> " . esc_html($date_formatted) . "\n";
			$telegram_message .= "<b>" . esc_html__('Zeit:', 'lasertagpro-buchung') . "</b> " . esc_html($time_formatted) . " " . esc_html__('Uhr', 'lasertagpro-buchung') . "\n";
			$telegram_message .= "<b>" . esc_html__('Personen:', 'lasertagpro-buchung') . "</b> " . esc_html($reservation->person_count);
			
			self::send_telegram($telegram_message);
		}
	}
}



