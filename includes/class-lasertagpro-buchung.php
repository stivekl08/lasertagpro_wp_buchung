<?php
/**
 * Hauptklasse des Plugins
 */

if (!defined('ABSPATH')) {
	exit;
}

class LaserTagPro_Buchung {

	/**
	 * Plugin-Version
	 */
	const VERSION = LTB_VERSION;

	/**
	 * Instanz der Klasse
	 */
	private static $instance = null;

	/**
	 * Admin-Instanz
	 */
	private $admin;

	/**
	 * Public-Instanz
	 */
	private $public;

	/**
	 * DAV-Client-Instanz
	 */
	private $dav_client;

	/**
	 * Singleton-Instanz
	 */
	public static function get_instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Konstruktor
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Dependencies laden
	 */
	private function load_dependencies() {
		require_once LTB_PLUGIN_DIR . 'includes/class-ltb-dav-client.php';
		require_once LTB_PLUGIN_DIR . 'includes/class-ltb-database.php';
		require_once LTB_PLUGIN_DIR . 'includes/class-ltb-pricing.php';
		require_once LTB_PLUGIN_DIR . 'includes/class-ltb-cart.php';
		require_once LTB_PLUGIN_DIR . 'includes/class-ltb-booking.php';
		require_once LTB_PLUGIN_DIR . 'includes/class-ltb-email.php';
		require_once LTB_PLUGIN_DIR . 'includes/class-ltb-export-import.php';
		require_once LTB_PLUGIN_DIR . 'includes/class-ltb-sync.php';
		require_once LTB_PLUGIN_DIR . 'includes/class-ltb-notifications.php';

		// wp_mail-Fehler ins Error-Log schreiben (zeigt WARUM E-Mails scheitern)
		add_action('wp_mail_failed', array('LTB_Email', 'log_mail_error'), 10, 1);

		if (is_admin()) {
			require_once LTB_PLUGIN_DIR . 'admin/class-ltb-admin.php';
			$this->admin = new LTB_Admin();
		}
		
		require_once LTB_PLUGIN_DIR . 'public/class-ltb-public.php';
		$this->public = new LTB_Public();
		
		$this->dav_client = new LTB_DAV_Client();
	}

	/**
	 * Hooks initialisieren
	 */
	private function init_hooks() {
		add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
		add_action('init', array($this, 'start_session'));
		$this->load_textdomain();
	}
	
	/**
	 * Session starten
	 */
	public function start_session() {
		if (!session_id() && !headers_sent()) {
			session_start();
		}
	}

	/**
	 * Textdomain laden
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'lasertagpro-buchung',
			false,
			dirname(LTB_PLUGIN_BASENAME) . '/languages'
		);
	}

	/**
	 * Public Assets laden
	 */
	public function enqueue_public_assets() {
		wp_enqueue_style(
			'ltb-public-style',
			LTB_PLUGIN_URL . 'assets/css/public.css',
			array(),
			time() // Cache-Busting: Immer neue Version laden
		);
		
		wp_enqueue_script(
			'ltb-public-script',
			LTB_PLUGIN_URL . 'assets/js/public.js',
			array(),
			time(), // Cache-Busting: Immer neue Version laden
			true
		);
		
		$max_players = get_option('ltb_max_players', 0);
		$max_players = ($max_players === '' || $max_players === null) ? 0 : absint($max_players);

		$inquiry_threshold = get_option('ltb_inquiry_threshold', 0);
		$inquiry_threshold = ($inquiry_threshold === '' || $inquiry_threshold === null) ? 0 : absint($inquiry_threshold);

		// Ersten aktiven Spielmodus aus der DB lesen (statt 'LaserTag' hardcoden)
		global $wpdb;
		$default_game_mode = $wpdb->get_var(
			"SELECT name FROM {$wpdb->prefix}ltb_game_modes WHERE active = 1 ORDER BY sort_order ASC, name ASC LIMIT 1"
		);
		if (empty($default_game_mode)) {
			$default_game_mode = 'LaserTag';
		}

		wp_localize_script('ltb-public-script', 'ltbData', array(
			'ajaxUrl'          => admin_url('admin-ajax.php'),
			'nonce'            => wp_create_nonce('ltb_nonce'),
			'minPlayers'       => absint(get_option('ltb_min_players', 1)),
			'maxPlayers'       => $max_players,
			'inquiryThreshold' => $inquiry_threshold,
			'defaultGameMode'  => $default_game_mode,
			'prices'           => array(
				1 => (float) get_option('ltb_price_1h', 25.00),
				2 => (float) get_option('ltb_price_2h', 35.00),
				3 => (float) get_option('ltb_price_3h', 45.00),
			),
			'strings'          => array(
				'loading'         => __('Lädt...', 'lasertagpro-buchung'),
				'error'           => __('Ein Fehler ist aufgetreten.', 'lasertagpro-buchung'),
				'success'         => __('Buchung erfolgreich!', 'lasertagpro-buchung'),
				'inquiryRequired' => __('Bei dieser Spieleranzahl benötigen wir weitere Details. Bitte füllen Sie das Nachrichtenfeld aus.', 'lasertagpro-buchung'),
			)
		));
	}

	/**
	 * Admin Assets laden
	 */
	public function enqueue_admin_assets() {
		wp_enqueue_style(
			'ltb-admin-style',
			LTB_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			self::VERSION
		);
		
		wp_enqueue_script(
			'ltb-admin-script',
			LTB_PLUGIN_URL . 'assets/js/admin.js',
			array('jquery'),
			self::VERSION,
			true
		);
	}


	/**
	 * Plugin ausführen
	 */
	public function run() {
		// Plugin läuft
	}

	/**
	 * DAV-Client-Instanz abrufen
	 */
	public function get_dav_client() {
		return $this->dav_client;
	}
}

