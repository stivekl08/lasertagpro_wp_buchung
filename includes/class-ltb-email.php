<?php
/**
 * E-Mail-Verwaltung
 */

if (!defined('ABSPATH')) {
	exit;
}

class LTB_Email {

	/**
	 * wp_mail mit ob_start/ob_get_clean Wrapper (fängt SMTP-Debug-Output ab)
	 *
	 * @param string   $to      Empfänger
	 * @param string   $subject Betreff
	 * @param string   $message HTML-Body
	 * @param array    $headers E-Mail-Header
	 * @param callable $after   Optionaler Callback nach wp_mail (z.B. für Notifications)
	 * @return bool
	 */
	private static function send_mail_buffered($to, $subject, $message, $headers, $after = null) {
		ob_start();
		$result = wp_mail($to, $subject, $message, $headers);
		if (!$result) {
			error_log('LTB wp_mail FEHLGESCHLAGEN: ' . $to);
		}
		if ($after) {
			$after();
		}
		$output = ob_get_clean();
		if (!empty($output)) {
			error_log('LTB Email Output: ' . $output);
		}
		return $result;
	}

	/**
	 * wp_mail_failed-Fehler ins Error-Log schreiben
	 */
	public static function log_mail_error($wp_error) {
		$message = $wp_error->get_error_message();
		$data    = $wp_error->get_error_data();
		$to      = isset($data['to']) ? (is_array($data['to']) ? implode(', ', $data['to']) : $data['to']) : 'unbekannt';
		$subject = isset($data['subject']) ? $data['subject'] : 'unbekannt';
		error_log('LTB wp_mail_failed: to=' . $to . ' | subject=' . $subject . ' | error=' . $message);
	}

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
		
		$result = self::send_mail_buffered($to, $subject, $message, $headers, function() use ($reservation) {
			// *** ADMIN-BENACHRICHTIGUNG SENDEN ***
			self::send_admin_notification($reservation);
			// *** GOTIFY & TELEGRAM BENACHRICHTIGUNGEN SENDEN ***
			LTB_Notifications::notify_new_reservation($reservation);
		});

		return $result;
	}
	
	/**
	 * Admin-Benachrichtigung bei neuer Reservierung
	 *
	 * @param object $reservation Reservierung
	 * @return bool Erfolg
	 */
	public static function send_admin_notification($reservation) {
		$admin_email = get_option('admin_email');
		$subject = __('🔔 Neue Reservierungsanfrage - LaserTagPro', 'lasertagpro-buchung');
		
		$message = self::get_admin_notification_template($reservation);
		
		$from_email = get_option('ltb_email_from', $admin_email);
		$from_name = get_option('ltb_email_from_name', get_bloginfo('name'));
		
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $from_name . ' <' . $from_email . '>',
			'Reply-To: ' . $reservation->name . ' <' . $reservation->email . '>',
		);
		
		return wp_mail($admin_email, $subject, $message, $headers);
	}
	
	/**
	 * Admin-Benachrichtigungs-Template
	 *
	 * @param object $reservation Reservierung
	 * @return string E-Mail-HTML
	 */
	private static function get_admin_notification_template($reservation) {
		$date_formatted = date_i18n(get_option('date_format'), strtotime($reservation->booking_date));
		$date_only = substr($reservation->booking_date, 0, 10);
		$start_time_obj = new DateTime($date_only . ' ' . $reservation->start_time);
		$end_time_obj = new DateTime($date_only . ' ' . $reservation->end_time);
		$time_formatted = $start_time_obj->format('H:i');
		$end_time_formatted = $end_time_obj->format('H:i');
		
		$admin_url = admin_url('admin.php?page=lasertagpro-buchung');
		
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<style>
				body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
				.container { max-width: 600px; margin: 0 auto; padding: 20px; }
				.header { background-color: #FF6600; color: white; padding: 20px; text-align: center; }
				.content { padding: 20px; background-color: #f9f9f9; }
				.details { background-color: white; padding: 15px; margin: 15px 0; border-left: 4px solid #FF6600; }
				.contact-box { background-color: #e3f2fd; border-left: 4px solid #2196F3; padding: 15px; margin: 15px 0; }
				.message-box { background-color: #fff3e0; border-left: 4px solid #FF9800; padding: 15px; margin: 15px 0; }
				.button { display: inline-block; padding: 12px 24px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px; }
			</style>
		</head>
		<body>
			<div class="container">
				<div class="header">
					<h1>🔔 <?php echo esc_html__('Neue Reservierungsanfrage', 'lasertagpro-buchung'); ?></h1>
				</div>
				<div class="content">
					<p><strong><?php echo esc_html__('Eine neue Reservierungsanfrage ist eingegangen!', 'lasertagpro-buchung'); ?></strong></p>
					
					<div class="contact-box">
						<h2>📞 <?php echo esc_html__('Kontaktdaten', 'lasertagpro-buchung'); ?></h2>
						<p><strong><?php echo esc_html__('Name:', 'lasertagpro-buchung'); ?></strong> <?php echo esc_html($reservation->name); ?></p>
						<p><strong><?php echo esc_html__('E-Mail:', 'lasertagpro-buchung'); ?></strong> <a href="mailto:<?php echo esc_attr($reservation->email); ?>"><?php echo esc_html($reservation->email); ?></a></p>
						<p><strong><?php echo esc_html__('Telefon:', 'lasertagpro-buchung'); ?></strong> <?php echo !empty($reservation->phone) ? esc_html($reservation->phone) : '<em>' . esc_html__('Nicht angegeben', 'lasertagpro-buchung') . '</em>'; ?></p>
					</div>
					
					<?php if (!empty($reservation->message)): ?>
					<div class="message-box">
						<h2>💬 <?php echo esc_html__('Nachricht vom Kunden', 'lasertagpro-buchung'); ?></h2>
						<p><?php echo nl2br(esc_html($reservation->message)); ?></p>
					</div>
					<?php endif; ?>
					
					<div class="details">
						<h2>📅 <?php echo esc_html__('Buchungsdetails', 'lasertagpro-buchung'); ?></h2>
						<p><strong><?php echo esc_html__('Datum:', 'lasertagpro-buchung'); ?></strong> <?php echo esc_html($date_formatted); ?></p>
						<p><strong><?php echo esc_html__('Uhrzeit:', 'lasertagpro-buchung'); ?></strong> <?php echo esc_html($time_formatted); ?> - <?php echo esc_html($end_time_formatted); ?> <?php echo esc_html__('Uhr', 'lasertagpro-buchung'); ?></p>
						<p><strong><?php echo esc_html__('Dauer:', 'lasertagpro-buchung'); ?></strong> <?php echo esc_html($reservation->booking_duration); ?> <?php echo esc_html__('Stunden', 'lasertagpro-buchung'); ?></p>
						<p><strong><?php echo esc_html__('Personen:', 'lasertagpro-buchung'); ?></strong> <?php echo esc_html($reservation->person_count); ?></p>
						<p><strong><?php echo esc_html__('Spielmodus:', 'lasertagpro-buchung'); ?></strong> <?php echo esc_html($reservation->game_mode); ?></p>
						<p><strong><?php echo esc_html__('Gesamtpreis:', 'lasertagpro-buchung'); ?></strong> €<?php echo number_format($reservation->total_price, 2, ',', '.'); ?></p>
					</div>
					
					<p style="text-align: center;">
						<a href="<?php echo esc_url($admin_url); ?>" class="button"><?php echo esc_html__('Reservierung im Admin-Bereich ansehen', 'lasertagpro-buchung'); ?></a>
					</p>
				</div>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
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
		
		$result = self::send_mail_buffered($to, $subject, $message, $headers, function() use ($reservation) {
			// *** GOTIFY & TELEGRAM BENACHRICHTIGUNGEN SENDEN ***
			LTB_Notifications::notify_confirmed_reservation($reservation);
		});

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
		
		return self::send_mail_buffered($to, $subject, $message, $headers, function() use ($reservation) {
			// *** GOTIFY & TELEGRAM BENACHRICHTIGUNGEN SENDEN ***
			LTB_Notifications::notify_cancelled_reservation($reservation);
		});
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
						<p><strong><?php echo esc_html__('Wichtig:', 'lasertagpro-buchung'); ?></strong> <?php echo esc_html__('Ihre Reservierung ist noch nicht bestätigt.', 'lasertagpro-buchung'); ?></p>
						<p>📧 <?php echo esc_html__('Sie erhalten eine Bestätigungs-E-Mail, sobald wir Ihre Anfrage bearbeitet haben.', 'lasertagpro-buchung'); ?></p>
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

		$maps_url = get_option('ltb_confirmation_maps_url', '');
		$confirmation_text = get_option('ltb_confirmation_text', '');

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
				.button-maps { display: inline-block; padding: 12px 24px; background-color: #1a73e8; color: white; text-decoration: none; border-radius: 4px; margin-top: 10px; }
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

					<?php if (!empty($maps_url)): ?>
					<p><a href="<?php echo esc_url($maps_url); ?>" class="button-maps">📍 <?php echo esc_html__('Anfahrt mit Google Maps', 'lasertagpro-buchung'); ?></a></p>
					<?php endif; ?>

					<?php if (!empty($confirmation_text)): ?>
					<p><?php echo nl2br(esc_html($confirmation_text)); ?></p>
					<?php endif; ?>

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

