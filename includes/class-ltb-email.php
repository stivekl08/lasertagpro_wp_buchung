<?php
/**
 * E-Mail-Verwaltung
 */

if (!defined('ABSPATH')) {
	exit;
}

class LTB_Email {

	/**
	 * Reservierungsanfrage-E-Mail senden (bei Buchung)
	 *
	 * @param int $reservation_id Reservierungs-ID
	 * @return bool Erfolg
	 */
	public static function send_booking_request($reservation_id) {
		$reservation = LTB_Booking::get_reservation($reservation_id);
		
		if (!$reservation) {
			return false;
		}
		
		$to = $reservation->email;
		$subject = __('Ihre Reservierungsanfrage bei LaserTagPro', 'lasertagpro-buchung');
		
		$message = self::get_booking_request_email_template($reservation);
		
		$from_email = get_option('ltb_email_from', get_option('admin_email'));
		$from_name = get_option('ltb_email_from_name', get_bloginfo('name'));
		
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $from_name . ' <' . $from_email . '>',
		);
		
		// Output puffern, damit SMTP-Debug-Output nicht in AJAX-Response gelangt
		// Alle existierenden Buffer-Ebenen sammeln
		$existing_buffers = array();
		$buffer_level = ob_get_level();
		for ($i = 0; $i < $buffer_level; $i++) {
			$existing_buffers[] = ob_get_clean();
		}
		
		// Neuen Buffer starten
		ob_start();
		
		$result = wp_mail($to, $subject, $message, $headers);
		
		// Alle Outputs sammeln und löschen
		$output = ob_get_clean();
		
		// Buffer wiederherstellen
		foreach (array_reverse($existing_buffers) as $buffer_content) {
			ob_start();
			if (!empty($buffer_content)) {
				echo $buffer_content;
			}
		}
		
		// Nur loggen, nicht ausgeben
		if (!empty($output)) {
			error_log('LTB Email Output: ' . $output);
		}
		
		return $result;
	}

	/**
	 * Buchungsbestätigung senden (nach Admin-Bestätigung)
	 *
	 * @param int $reservation_id Reservierungs-ID
	 * @return bool Erfolg
	 */
	public static function send_booking_confirmation($reservation_id) {
		$reservation = LTB_Booking::get_reservation($reservation_id);
		
		if (!$reservation) {
			return false;
		}
		
		$to = $reservation->email;
		$subject = __('Ihre Reservierung wurde bestätigt - LaserTagPro', 'lasertagpro-buchung');
		
		$message = self::get_booking_email_template($reservation);
		
		$from_email = get_option('ltb_email_from', get_option('admin_email'));
		$from_name = get_option('ltb_email_from_name', get_bloginfo('name'));
		
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $from_name . ' <' . $from_email . '>',
		);
		
		// Output puffern, damit SMTP-Debug-Output nicht in AJAX-Response gelangt
		// Alle existierenden Buffer-Ebenen sammeln
		$existing_buffers = array();
		$buffer_level = ob_get_level();
		for ($i = 0; $i < $buffer_level; $i++) {
			$existing_buffers[] = ob_get_clean();
		}
		
		// Neuen Buffer starten
		ob_start();
		
		$result = wp_mail($to, $subject, $message, $headers);
		
		// Alle Outputs sammeln und löschen
		$output = ob_get_clean();
		
		// Buffer wiederherstellen
		foreach (array_reverse($existing_buffers) as $buffer_content) {
			ob_start();
			if (!empty($buffer_content)) {
				echo $buffer_content;
			}
		}
		
		// Nur loggen, nicht ausgeben
		if (!empty($output)) {
			error_log('LTB Email Output: ' . $output);
		}
		
		return $result;
	}

	/**
	 * Stornierungsbestätigung senden
	 *
	 * @param int $reservation_id Reservierungs-ID
	 * @return bool Erfolg
	 */
	public static function send_cancellation_confirmation($reservation_id) {
		$reservation = LTB_Booking::get_reservation($reservation_id);
		
		if (!$reservation) {
			return false;
		}
		
		$to = $reservation->email;
		$subject = __('Ihre Reservierung wurde storniert', 'lasertagpro-buchung');
		
		$message = self::get_cancellation_email_template($reservation);
		
		$from_email = get_option('ltb_email_from', get_option('admin_email'));
		$from_name = get_option('ltb_email_from_name', get_bloginfo('name'));
		
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $from_name . ' <' . $from_email . '>',
		);
		
		// Output puffern, damit SMTP-Debug-Output nicht in AJAX-Response gelangt
		// Alle existierenden Buffer-Ebenen sammeln
		$existing_buffers = array();
		$buffer_level = ob_get_level();
		for ($i = 0; $i < $buffer_level; $i++) {
			$existing_buffers[] = ob_get_clean();
		}
		
		// Neuen Buffer starten
		ob_start();
		
		$result = wp_mail($to, $subject, $message, $headers);
		
		// Alle Outputs sammeln und löschen
		$output = ob_get_clean();
		
		// Buffer wiederherstellen
		foreach (array_reverse($existing_buffers) as $buffer_content) {
			ob_start();
			if (!empty($buffer_content)) {
				echo $buffer_content;
			}
		}
		
		// Nur loggen, nicht ausgeben
		if (!empty($output)) {
			error_log('LTB Email Output: ' . $output);
		}
		
		return $result;
	}

	/**
	 * Reservierungsanfrage-E-Mail-Template (bei Buchung)
	 *
	 * @param object $reservation Reservierung
	 * @return string E-Mail-HTML
	 */
	private static function get_booking_request_email_template($reservation) {
		$date_formatted = date_i18n(get_option('date_format'), strtotime($reservation->booking_date));
		// Deutsches 24-Stunden-Format verwenden
		// Extrahiere nur das Datum (falls booking_date bereits eine Zeit enthält)
		$date_only = substr($reservation->booking_date, 0, 10);
		$start_time_obj = new DateTime($date_only . ' ' . $reservation->start_time);
		$end_time_obj = new DateTime($date_only . ' ' . $reservation->end_time);
		$time_formatted = $start_time_obj->format('H:i');
		$end_time_formatted = $end_time_obj->format('H:i');
		
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<style>
				body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
				.container { max-width: 600px; margin: 0 auto; padding: 20px; }
				.header { background-color: #2196F3; color: white; padding: 20px; text-align: center; }
				.content { padding: 20px; background-color: #f9f9f9; }
				.details { background-color: white; padding: 15px; margin: 15px 0; border-left: 4px solid #2196F3; }
				.info-box { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
			</style>
		</head>
		<body>
			<div class="container">
				<div class="header">
					<h1><?php echo esc_html__('Reservierungsanfrage erhalten', 'lasertagpro-buchung'); ?></h1>
				</div>
				<div class="content">
					<p><?php echo esc_html__('Hallo', 'lasertagpro-buchung'); ?> <?php echo esc_html($reservation->name); ?>,</p>
					<p><?php echo esc_html__('vielen Dank für Ihre Reservierungsanfrage!', 'lasertagpro-buchung'); ?></p>
					
					<div class="info-box">
						<p><strong><?php echo esc_html__('Wichtig:', 'lasertagpro-buchung'); ?></strong> <?php echo esc_html__('Ihre Reservierung ist noch nicht bestätigt. Sie erhalten eine separate Bestätigungs-E-Mail, sobald wir Ihre Anfrage bearbeitet haben.', 'lasertagpro-buchung'); ?></p>
					</div>
					
					<div class="details">
						<h2><?php echo esc_html__('Ihre Reservierungsanfrage:', 'lasertagpro-buchung'); ?></h2>
						<p><strong><?php echo esc_html__('Datum:', 'lasertagpro-buchung'); ?></strong> <?php echo esc_html($date_formatted); ?></p>
						<p><strong><?php echo esc_html__('Uhrzeit:', 'lasertagpro-buchung'); ?></strong> <?php echo esc_html($time_formatted); ?> - <?php echo esc_html($end_time_formatted); ?> <?php echo esc_html__('Uhr', 'lasertagpro-buchung'); ?></p>
						<p><strong><?php echo esc_html__('Dauer:', 'lasertagpro-buchung'); ?></strong> <?php echo esc_html($reservation->booking_duration); ?> <?php echo esc_html__('Stunden', 'lasertagpro-buchung'); ?></p>
						<p><strong><?php echo esc_html__('Personen:', 'lasertagpro-buchung'); ?></strong> <?php echo esc_html($reservation->person_count); ?></p>
						<p><strong><?php echo esc_html__('Spielmodus:', 'lasertagpro-buchung'); ?></strong> <?php echo esc_html($reservation->game_mode); ?></p>
						<?php if (!empty($reservation->message)): ?>
							<p><strong><?php echo esc_html__('Nachricht:', 'lasertagpro-buchung'); ?></strong><br><?php echo nl2br(esc_html($reservation->message)); ?></p>
						<?php endif; ?>
					</div>
					
					<p><?php echo esc_html__('Wir werden Ihre Anfrage schnellstmöglich bearbeiten und Sie per E-Mail informieren.', 'lasertagpro-buchung'); ?></p>
					
					<p><?php echo esc_html__('Mit freundlichen Grüßen,', 'lasertagpro-buchung'); ?><br><?php echo esc_html(get_bloginfo('name')); ?></p>
				</div>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Buchungsbestätigungs-E-Mail-Template (nach Admin-Bestätigung)
	 *
	 * @param object $reservation Reservierung
	 * @return string E-Mail-HTML
	 */
	private static function get_booking_email_template($reservation) {
		$cancel_url = add_query_arg(
			array(
				'ltb_cancel' => $reservation->confirmation_token,
			),
			home_url()
		);
		
		$date_formatted = date_i18n(get_option('date_format'), strtotime($reservation->booking_date));
		// Deutsches 24-Stunden-Format verwenden
		// Extrahiere nur das Datum (falls booking_date bereits eine Zeit enthält)
		$date_only = substr($reservation->booking_date, 0, 10);
		$start_time_obj = new DateTime($date_only . ' ' . $reservation->start_time);
		$end_time_obj = new DateTime($date_only . ' ' . $reservation->end_time);
		$time_formatted = $start_time_obj->format('H:i');
		$end_time_formatted = $end_time_obj->format('H:i');
		
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<style>
				body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
				.container { max-width: 600px; margin: 0 auto; padding: 20px; }
				.header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
				.content { padding: 20px; background-color: #f9f9f9; }
				.details { background-color: white; padding: 15px; margin: 15px 0; border-left: 4px solid #4CAF50; }
				.button { display: inline-block; padding: 12px 24px; background-color: #f44336; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px; }
			</style>
		</head>
		<body>
			<div class="container">
				<div class="header">
					<h1><?php echo esc_html__('Reservierung bestätigt', 'lasertagpro-buchung'); ?></h1>
				</div>
				<div class="content">
					<p><?php echo esc_html__('Hallo', 'lasertagpro-buchung'); ?> <?php echo esc_html($reservation->name); ?>,</p>
					<p><?php echo esc_html__('Ihre Reservierung wurde bestätigt!', 'lasertagpro-buchung'); ?></p>
					
					<div class="details">
						<h2><?php echo esc_html__('Ihre Reservierungsdetails:', 'lasertagpro-buchung'); ?></h2>
						<p><strong><?php echo esc_html__('Datum:', 'lasertagpro-buchung'); ?></strong> <?php echo esc_html($date_formatted); ?></p>
						<p><strong><?php echo esc_html__('Uhrzeit:', 'lasertagpro-buchung'); ?></strong> <?php echo esc_html($time_formatted); ?> - <?php echo esc_html($end_time_formatted); ?> <?php echo esc_html__('Uhr', 'lasertagpro-buchung'); ?></p>
						<p><strong><?php echo esc_html__('Dauer:', 'lasertagpro-buchung'); ?></strong> <?php echo esc_html($reservation->booking_duration); ?> <?php echo esc_html__('Stunden', 'lasertagpro-buchung'); ?></p>
						<p><strong><?php echo esc_html__('Personen:', 'lasertagpro-buchung'); ?></strong> <?php echo esc_html($reservation->person_count); ?></p>
						<p><strong><?php echo esc_html__('Spielmodus:', 'lasertagpro-buchung'); ?></strong> <?php echo esc_html($reservation->game_mode); ?></p>
						<?php if (!empty($reservation->message)): ?>
							<p><strong><?php echo esc_html__('Nachricht:', 'lasertagpro-buchung'); ?></strong><br><?php echo nl2br(esc_html($reservation->message)); ?></p>
						<?php endif; ?>
					</div>
					
					<p><?php echo esc_html__('Falls Sie Ihre Reservierung stornieren möchten, klicken Sie bitte auf den folgenden Link:', 'lasertagpro-buchung'); ?></p>
					<p><a href="<?php echo esc_url($cancel_url); ?>" class="button"><?php echo esc_html__('Reservierung stornieren', 'lasertagpro-buchung'); ?></a></p>
					
					<p><?php echo esc_html__('Wir freuen uns auf Ihren Besuch!', 'lasertagpro-buchung'); ?></p>
				</div>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Stornierungs-E-Mail-Template
	 *
	 * @param object $reservation Reservierung
	 * @return string E-Mail-HTML
	 */
	private static function get_cancellation_email_template($reservation) {
		$date_formatted = date_i18n(get_option('date_format'), strtotime($reservation->booking_date));
		// Deutsches 24-Stunden-Format verwenden
		// Extrahiere nur das Datum (falls booking_date bereits eine Zeit enthält)
		$date_only = substr($reservation->booking_date, 0, 10);
		$start_time_obj = new DateTime($date_only . ' ' . $reservation->start_time);
		$time_formatted = $start_time_obj->format('H:i');
		
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<style>
				body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
				.container { max-width: 600px; margin: 0 auto; padding: 20px; }
				.header { background-color: #f44336; color: white; padding: 20px; text-align: center; }
				.content { padding: 20px; background-color: #f9f9f9; }
			</style>
		</head>
		<body>
			<div class="container">
				<div class="header">
					<h1><?php echo esc_html__('Reservierung storniert', 'lasertagpro-buchung'); ?></h1>
				</div>
				<div class="content">
					<p><?php echo esc_html__('Hallo', 'lasertagpro-buchung'); ?> <?php echo esc_html($reservation->name); ?>,</p>
					<p><?php echo esc_html__('Ihre Reservierung für den', 'lasertagpro-buchung'); ?> <?php echo esc_html($date_formatted); ?> <?php echo esc_html__('um', 'lasertagpro-buchung'); ?> <?php echo esc_html($time_formatted); ?> <?php echo esc_html__('Uhr', 'lasertagpro-buchung'); ?> <?php echo esc_html__('wurde erfolgreich storniert.', 'lasertagpro-buchung'); ?></p>
					<p><?php echo esc_html__('Wir hoffen, Sie in Zukunft wieder bei uns begrüßen zu dürfen!', 'lasertagpro-buchung'); ?></p>
				</div>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}
}

