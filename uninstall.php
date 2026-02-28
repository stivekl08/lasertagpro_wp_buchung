<?php
/**
 * Plugin-Deinstallation: Tabellen und Optionen entfernen
 *
 * Wird ausgeführt wenn der Admin das Plugin unter "Plugins → Löschen" entfernt.
 */

// Direkten Aufruf verhindern
if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

global $wpdb;

// Tabellen löschen
$tables = array(
	$wpdb->prefix . 'ltb_reservations',
	$wpdb->prefix . 'ltb_game_modes',
	$wpdb->prefix . 'ltb_promo_codes',
);

foreach ($tables as $table) {
	$wpdb->query("DROP TABLE IF EXISTS `$table`");
}

// Alle Plugin-Optionen löschen
$options = array(
	'ltb_dav_url',
	'ltb_dav_username',
	'ltb_dav_password',
	'ltb_start_hour',
	'ltb_end_hour',
	'ltb_min_players',
	'ltb_max_players',
	'ltb_inquiry_threshold',
	'ltb_gotify_enabled',
	'ltb_gotify_url',
	'ltb_gotify_token',
	'ltb_telegram_enabled',
	'ltb_telegram_bot_token',
	'ltb_telegram_chat_id',
	'ltb_price_1h',
	'ltb_price_2h',
	'ltb_price_3h',
	'ltb_email_from',
	'ltb_email_from_name',
);

foreach ($options as $option) {
	delete_option($option);
}

// Transients und Cache leeren
wp_cache_flush();
