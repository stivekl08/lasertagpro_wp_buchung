<?php
if (!defined('ABSPATH')) {
	exit;
}

if (isset($_GET['settings-updated'])) {
	echo '<div class="notice notice-success"><p>' . esc_html__('Einstellungen gespeichert!', 'lasertagpro-buchung') . '</p></div>';
}
?>
<div class="wrap">
	<h1><?php echo esc_html__('Einstellungen', 'lasertagpro-buchung'); ?></h1>
	
	<form method="post" action="options.php">
		<?php settings_fields('ltb_settings'); ?>
		
		<h2><?php echo esc_html__('DAV-Kalender-Einstellungen', 'lasertagpro-buchung'); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="ltb_dav_url"><?php echo esc_html__('DAV-URL', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="url" id="ltb_dav_url" name="ltb_dav_url" value="<?php echo esc_attr(get_option('ltb_dav_url')); ?>" class="regular-text" required>
					<p class="description"><?php echo esc_html__('Die URL zu Ihrem CalDAV-Server (z.B. https://example.com/caldav/)', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="ltb_dav_username"><?php echo esc_html__('Benutzername', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="text" id="ltb_dav_username" name="ltb_dav_username" value="<?php echo esc_attr(get_option('ltb_dav_username')); ?>" class="regular-text" required>
				</td>
			</tr>
			<tr>
				<th><label for="ltb_dav_password"><?php echo esc_html__('Passwort', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="password" id="ltb_dav_password" name="ltb_dav_password" value="<?php echo esc_attr(get_option('ltb_dav_password')); ?>" class="regular-text" required>
				</td>
			</tr>
		</table>
		
		<h2><?php echo esc_html__('Benachrichtigungen', 'lasertagpro-buchung'); ?></h2>
		<table class="form-table">
			<tr>
				<th><label><?php echo esc_html__('Gotify', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<label>
						<input type="checkbox" id="ltb_gotify_enabled" name="ltb_gotify_enabled" value="1" <?php checked(get_option('ltb_gotify_enabled', false)); ?>>
						<?php echo esc_html__('Gotify-Benachrichtigungen aktivieren', 'lasertagpro-buchung'); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th><label for="ltb_gotify_url"><?php echo esc_html__('Gotify-URL', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="url" id="ltb_gotify_url" name="ltb_gotify_url" value="<?php echo esc_attr(get_option('ltb_gotify_url')); ?>" class="regular-text" placeholder="https://gotify.example.com">
					<p class="description"><?php echo esc_html__('Die URL zu Ihrem Gotify-Server (ohne trailing slash)', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="ltb_gotify_token"><?php echo esc_html__('Gotify-Token', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="text" id="ltb_gotify_token" name="ltb_gotify_token" value="<?php echo esc_attr(get_option('ltb_gotify_token')); ?>" class="regular-text">
					<p class="description"><?php echo esc_html__('Das Application Token von Gotify', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label><?php echo esc_html__('Telegram', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<label>
						<input type="checkbox" id="ltb_telegram_enabled" name="ltb_telegram_enabled" value="1" <?php checked(get_option('ltb_telegram_enabled', false)); ?>>
						<?php echo esc_html__('Telegram-Benachrichtigungen aktivieren', 'lasertagpro-buchung'); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th><label for="ltb_telegram_bot_token"><?php echo esc_html__('Telegram Bot Token', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="text" id="ltb_telegram_bot_token" name="ltb_telegram_bot_token" value="<?php echo esc_attr(get_option('ltb_telegram_bot_token')); ?>" class="regular-text">
					<p class="description"><?php echo esc_html__('Das Bot Token von @BotFather', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="ltb_telegram_chat_id"><?php echo esc_html__('Telegram Chat ID', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="text" id="ltb_telegram_chat_id" name="ltb_telegram_chat_id" value="<?php echo esc_attr(get_option('ltb_telegram_chat_id')); ?>" class="regular-text">
					<p class="description"><?php echo esc_html__('Die Chat-ID, an die Benachrichtigungen gesendet werden sollen (z.B. -1001234567890 oder 123456789)', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
		</table>
		
		<h2><?php echo esc_html__('Buchungszeiten', 'lasertagpro-buchung'); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="ltb_start_hour"><?php echo esc_html__('Startstunde', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="number" id="ltb_start_hour" name="ltb_start_hour" value="<?php echo esc_attr(get_option('ltb_start_hour', 10)); ?>" min="0" max="23" required>
					<p class="description"><?php echo esc_html__('Erste verfügbare Stunde (0-23)', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="ltb_end_hour"><?php echo esc_html__('Endstunde', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="number" id="ltb_end_hour" name="ltb_end_hour" value="<?php echo esc_attr(get_option('ltb_end_hour', 23)); ?>" min="0" max="23" required>
					<p class="description"><?php echo esc_html__('Letzte verfügbare Stunde (0-23)', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
		</table>
		
		<h2><?php echo esc_html__('Dunkelheit-Einstellungen', 'lasertagpro-buchung'); ?></h2>
		<table class="form-table">
			<tr>
				<th><label><?php echo esc_html__('Dunkelheitsperiode', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<p class="description"><?php echo esc_html__('Zeitraum, in dem es zu dunkel zum Spielen ist (z.B. Winter).', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="ltb_darkness_start_month"><?php echo esc_html__('Von Monat', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<select id="ltb_darkness_start_month" name="ltb_darkness_start_month">
						<option value="0"><?php echo esc_html__('Deaktiviert', 'lasertagpro-buchung'); ?></option>
						<?php
						$months = array(1 => __('Januar', 'lasertagpro-buchung'), 2 => __('Februar', 'lasertagpro-buchung'), 3 => __('März', 'lasertagpro-buchung'), 4 => __('April', 'lasertagpro-buchung'), 5 => __('Mai', 'lasertagpro-buchung'), 6 => __('Juni', 'lasertagpro-buchung'), 7 => __('Juli', 'lasertagpro-buchung'), 8 => __('August', 'lasertagpro-buchung'), 9 => __('September', 'lasertagpro-buchung'), 10 => __('Oktober', 'lasertagpro-buchung'), 11 => __('November', 'lasertagpro-buchung'), 12 => __('Dezember', 'lasertagpro-buchung'));
						$start_month = absint(get_option('ltb_darkness_start_month', 10));
						foreach ($months as $num => $name) {
							echo '<option value="' . $num . '"' . selected($start_month, $num, false) . '>' . esc_html($name) . '</option>';
						}
						?>
					</select>
					<input type="number" id="ltb_darkness_start_day" name="ltb_darkness_start_day" value="<?php echo esc_attr(get_option('ltb_darkness_start_day', 1)); ?>" min="1" max="31" style="width: 80px; margin-left: 10px;">
					<p class="description"><?php echo esc_html__('Startdatum der Dunkelheitsperiode (z.B. Oktober, 1 = 1. Oktober)', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="ltb_darkness_end_month"><?php echo esc_html__('Bis Monat', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<select id="ltb_darkness_end_month" name="ltb_darkness_end_month">
						<option value="0"><?php echo esc_html__('Deaktiviert', 'lasertagpro-buchung'); ?></option>
						<?php
						$end_month = absint(get_option('ltb_darkness_end_month', 3));
						foreach ($months as $num => $name) {
							echo '<option value="' . $num . '"' . selected($end_month, $num, false) . '>' . esc_html($name) . '</option>';
						}
						?>
					</select>
					<input type="number" id="ltb_darkness_end_day" name="ltb_darkness_end_day" value="<?php echo esc_attr(get_option('ltb_darkness_end_day', 31)); ?>" min="1" max="31" style="width: 80px; margin-left: 10px;">
					<p class="description"><?php echo esc_html__('Enddatum der Dunkelheitsperiode (z.B. März, 31 = 31. März)', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="ltb_darkness_hour"><?php echo esc_html__('Ab dieser Stunde zu dunkel', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="number" id="ltb_darkness_hour" name="ltb_darkness_hour" value="<?php echo esc_attr(get_option('ltb_darkness_hour', 18)); ?>" min="0" max="23">
					<p class="description"><?php echo esc_html__('Ab dieser Stunde ist es während der Dunkelheitsperiode zu dunkel zum Spielen (z.B. 18 = ab 18:00 Uhr)', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
		</table>
		
		<h2><?php echo esc_html__('Spieleranzahl', 'lasertagpro-buchung'); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="ltb_min_players"><?php echo esc_html__('Mindestanzahl Spieler', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="number" id="ltb_min_players" name="ltb_min_players" value="<?php echo esc_attr(get_option('ltb_min_players', 1)); ?>" min="1" required>
					<p class="description"><?php echo esc_html__('Minimale Anzahl der Spieler pro Buchung', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="ltb_max_players"><?php echo esc_html__('Maximalanzahl Spieler', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="number" id="ltb_max_players" name="ltb_max_players" value="<?php echo esc_attr(get_option('ltb_max_players', 0)); ?>" min="0">
					<p class="description"><?php echo esc_html__('Maximale Anzahl der Spieler pro Buchung (0 = unbegrenzt, leer = unbegrenzt)', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="ltb_inquiry_threshold"><?php echo esc_html__('Anfrage-Schwelle', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="number" id="ltb_inquiry_threshold" name="ltb_inquiry_threshold" value="<?php echo esc_attr(get_option('ltb_inquiry_threshold', 0)); ?>" min="0">
					<p class="description"><?php echo esc_html__('Ab dieser Spieleranzahl ist eine Anfrage mit Details erforderlich (0 = deaktiviert, leer = deaktiviert)', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
		</table>
		
		<h2><?php echo esc_html__('Paketpreise', 'lasertagpro-buchung'); ?></h2>
		<p class="description"><?php echo esc_html__('Preise pro Person nach Buchungsdauer. Änderungen gelten sofort für neue Buchungen.', 'lasertagpro-buchung'); ?></p>
		<table class="form-table">
			<tr>
				<th><label for="ltb_price_1h"><?php echo esc_html__('60 Minuten (€ pro Person)', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="number" id="ltb_price_1h" name="ltb_price_1h" value="<?php echo esc_attr(get_option('ltb_price_1h', '25.00')); ?>" min="0" step="0.01" class="small-text" required>
					<p class="description"><?php echo esc_html__('Standardwert: €25,00', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="ltb_price_2h"><?php echo esc_html__('120 Minuten (€ pro Person)', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="number" id="ltb_price_2h" name="ltb_price_2h" value="<?php echo esc_attr(get_option('ltb_price_2h', '35.00')); ?>" min="0" step="0.01" class="small-text" required>
					<p class="description"><?php echo esc_html__('Standardwert: €35,00', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="ltb_price_3h"><?php echo esc_html__('180 Minuten (€ pro Person)', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="number" id="ltb_price_3h" name="ltb_price_3h" value="<?php echo esc_attr(get_option('ltb_price_3h', '45.00')); ?>" min="0" step="0.01" class="small-text" required>
					<p class="description"><?php echo esc_html__('Standardwert: €45,00', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php echo esc_html__('E-Mail-Einstellungen', 'lasertagpro-buchung'); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="ltb_email_from"><?php echo esc_html__('Absender-E-Mail', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="email" id="ltb_email_from" name="ltb_email_from" value="<?php echo esc_attr(get_option('ltb_email_from', get_option('admin_email'))); ?>" class="regular-text">
					<p class="description"><?php echo esc_html__('Absenderadresse für alle Buchungs-E-Mails.', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="ltb_email_from_name"><?php echo esc_html__('Absender-Name', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="text" id="ltb_email_from_name" name="ltb_email_from_name" value="<?php echo esc_attr(get_option('ltb_email_from_name', get_bloginfo('name'))); ?>" class="regular-text">
				</td>
			</tr>
		</table>

		<h2><?php echo esc_html__('SMTP-Konfiguration', 'lasertagpro-buchung'); ?></h2>
		<p class="description"><?php echo esc_html__('Falls E-Mails nicht ankommen, hier SMTP aktivieren. Empfohlen für alle Produktivumgebungen.', 'lasertagpro-buchung'); ?></p>
		<table class="form-table">
			<tr>
				<th><label for="ltb_smtp_enabled"><?php echo esc_html__('SMTP aktivieren', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<label>
						<input type="checkbox" id="ltb_smtp_enabled" name="ltb_smtp_enabled" value="1" <?php checked(get_option('ltb_smtp_enabled', 0), 1); ?>>
						<?php echo esc_html__('SMTP zum Versenden von E-Mails verwenden', 'lasertagpro-buchung'); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th><label for="ltb_smtp_host"><?php echo esc_html__('SMTP-Host', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="text" id="ltb_smtp_host" name="ltb_smtp_host" value="<?php echo esc_attr(get_option('ltb_smtp_host', '')); ?>" class="regular-text" placeholder="smtp.gmail.com">
					<p class="description"><?php echo esc_html__('z.B. smtp.gmail.com, smtp.ionos.de, mail.ihr-server.de', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="ltb_smtp_port"><?php echo esc_html__('SMTP-Port', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="number" id="ltb_smtp_port" name="ltb_smtp_port" value="<?php echo esc_attr(get_option('ltb_smtp_port', 587)); ?>" class="small-text" min="1" max="65535">
					<p class="description"><?php echo esc_html__('587 für TLS/STARTTLS (empfohlen), 465 für SSL, 25 ohne Verschlüsselung', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="ltb_smtp_encryption"><?php echo esc_html__('Verschlüsselung', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<select id="ltb_smtp_encryption" name="ltb_smtp_encryption">
						<option value="tls" <?php selected(get_option('ltb_smtp_encryption', 'tls'), 'tls'); ?>>TLS / STARTTLS (Port 587, empfohlen)</option>
						<option value="ssl" <?php selected(get_option('ltb_smtp_encryption', 'tls'), 'ssl'); ?>>SSL (Port 465)</option>
						<option value="" <?php selected(get_option('ltb_smtp_encryption', 'tls'), ''); ?>>Keine Verschlüsselung (Port 25)</option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="ltb_smtp_username"><?php echo esc_html__('SMTP-Benutzername', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="text" id="ltb_smtp_username" name="ltb_smtp_username" value="<?php echo esc_attr(get_option('ltb_smtp_username', '')); ?>" class="regular-text" placeholder="info@ihre-domain.de" autocomplete="off">
				</td>
			</tr>
			<tr>
				<th><label for="ltb_smtp_password"><?php echo esc_html__('SMTP-Passwort', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="password" id="ltb_smtp_password" name="ltb_smtp_password" value="<?php echo esc_attr(get_option('ltb_smtp_password', '')); ?>" class="regular-text" autocomplete="new-password">
					<p class="description"><?php echo esc_html__('Bei Gmail: App-Passwort verwenden (nicht das Google-Konto-Passwort).', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>
</div>

