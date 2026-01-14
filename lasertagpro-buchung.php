<?php
/**
 * Plugin Name: LaserTagPro Buchung
 * Plugin URI: https://wordpress.org/plugins/lasertagpro-buchung/
 * Description: Terminbuchungssystem mit DAV-Kalender-Integration für LaserTagPro
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lasertagpro-buchung
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Verhindere direkten Zugriff
if (!defined('ABSPATH')) {
	exit;
}

// Plugin-Konstanten definieren
define('LTB_VERSION', '1.0.0');
define('LTB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LTB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LTB_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Activation Hook - muss vor dem Laden der Klassen registriert werden
register_activation_hook(__FILE__, 'ltb_activate');
register_deactivation_hook(__FILE__, 'ltb_deactivate');

/**
 * Plugin aktivieren
 */
function ltb_activate() {
	// Datenbank-Klasse laden
	require_once LTB_PLUGIN_DIR . 'includes/class-ltb-database.php';
	LTB_Database::create_tables();
	flush_rewrite_rules();
}

/**
 * Plugin deaktivieren
 */
function ltb_deactivate() {
	flush_rewrite_rules();
}

// Hauptklasse laden
require_once LTB_PLUGIN_DIR . 'includes/class-lasertagpro-buchung.php';

// Plugin initialisieren - nach plugins_loaded
add_action('plugins_loaded', 'ltb_init', 10);

function ltb_init() {
	$plugin = new LaserTagPro_Buchung();
	$plugin->run();
}

                                                                                 