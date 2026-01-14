<?php
/**
 * Datenbank-Verwaltung
 */

if (!defined('ABSPATH')) {
	exit;
}

class LTB_Database {

	/**
	 * Tabellen erstellen
	 */
	public static function create_tables() {
		global $wpdb;
		
		$charset_collate = $wpdb->get_charset_collate();
		
		// Tabelle für Reservierungen
		$table_reservations = $wpdb->prefix . 'ltb_reservations';
		
		$sql_reservations = "CREATE TABLE $table_reservations (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_date datetime NOT NULL,
			booking_duration int(11) NOT NULL DEFAULT 1 COMMENT 'Dauer in Stunden',
			start_time time NOT NULL,
			end_time time NOT NULL,
			name varchar(255) NOT NULL,
			email varchar(255) NOT NULL,
			phone varchar(50) DEFAULT NULL,
			message text DEFAULT NULL,
			person_count int(11) NOT NULL DEFAULT 1,
			game_mode varchar(100) NOT NULL,
			price_per_person decimal(10,2) DEFAULT NULL,
			total_price decimal(10,2) DEFAULT NULL,
			discount_amount decimal(10,2) DEFAULT 0.00,
			discount_percent decimal(5,2) DEFAULT 0.00,
			promo_code varchar(50) DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, confirmed, cancelled',
			confirmation_token varchar(64) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY booking_date (booking_date),
			KEY status (status),
			KEY email (email)
		) $charset_collate;";
		
		// Tabelle für Spielmodi/Packages
		$table_game_modes = $wpdb->prefix . 'ltb_game_modes';
		
		$sql_game_modes = "CREATE TABLE $table_game_modes (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text DEFAULT NULL,
			duration decimal(5,2) NOT NULL DEFAULT 1.00 COMMENT 'Dauer in Stunden (z.B. 1.00 = 60 Min, 2.00 = 120 Min)',
			price decimal(10,2) DEFAULT NULL,
			price_weekend decimal(10,2) DEFAULT NULL COMMENT 'Preis für Wochenende (FR-SO)',
			private_game_extra_mo_do decimal(10,2) DEFAULT NULL COMMENT 'Zusatzkosten für privates Spiel MO-DO',
			private_game_extra_fr_so decimal(10,2) DEFAULT NULL COMMENT 'Zusatzkosten für privates Spiel FR-SO',
			is_private tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Ist privates Spiel',
			min_players int(11) DEFAULT 6 COMMENT 'Minimale Spieleranzahl',
			max_players int(11) DEFAULT NULL COMMENT 'Maximale Spieleranzahl',
			is_bestseller tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Best Seller Markierung',
			active tinyint(1) NOT NULL DEFAULT 1,
			sort_order int(11) NOT NULL DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY active (active)
		) $charset_collate;";
		
		// Tabelle für Promo-Codes
		$table_promo_codes = $wpdb->prefix . 'ltb_promo_codes';
		
		$sql_promo_codes = "CREATE TABLE $table_promo_codes (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			code varchar(50) NOT NULL,
			discount_type varchar(20) NOT NULL DEFAULT 'percent' COMMENT 'percent, fixed',
			discount_value decimal(10,2) NOT NULL,
			usage_limit int(11) DEFAULT NULL,
			usage_count int(11) NOT NULL DEFAULT 0,
			valid_from date DEFAULT NULL,
			valid_until date DEFAULT NULL,
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY code (code),
			KEY active (active)
		) $charset_collate;";
		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql_reservations);
		dbDelta($sql_game_modes);
		dbDelta($sql_promo_codes);
		
		// Fehlende Spalten hinzufügen (für Updates)
		$columns = $wpdb->get_col("DESC $table_game_modes");
		if (!in_array('min_players', $columns)) {
			$wpdb->query("ALTER TABLE $table_game_modes ADD COLUMN min_players int(11) DEFAULT 6 COMMENT 'Minimale Spieleranzahl' AFTER max_players");
		}
		if (!in_array('is_bestseller', $columns)) {
			$wpdb->query("ALTER TABLE $table_game_modes ADD COLUMN is_bestseller tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Best Seller Markierung' AFTER max_players");
		}
		// Spalte auf decimal ändern falls nötig (für bestehende Installationen)
		$duration_info = $wpdb->get_row("SHOW COLUMNS FROM $table_game_modes WHERE Field = 'duration'");
		if ($duration_info && $duration_info->Type !== 'decimal(5,2)') {
			$wpdb->query("ALTER TABLE $table_game_modes MODIFY COLUMN duration decimal(5,2) NOT NULL DEFAULT 1.00 COMMENT 'Dauer in Stunden'");
		}
		
		// Standard-Spielmodi einfügen, falls Tabelle leer ist
		$existing_modes = $wpdb->get_var("SELECT COUNT(*) FROM $table_game_modes");
		if ($existing_modes == 0) {
			$default_modes = array(
				array(
					'name' => 'Offenes Spiel',
					'description' => 'Bei einem offenen Spiel spielen mehrere Teams zusammen',
					'duration' => 1,
					'price' => 16.90,
					'price_weekend' => 16.90,
					'is_private' => 0,
					'max_players' => 24,
				),
				array(
					'name' => 'Privates Spiel',
					'description' => 'Die ganze Arena gehört nur Dir und Deinen Freunden.',
					'duration' => 1,
					'price' => 16.90,
					'price_weekend' => 16.90,
					'private_game_extra_mo_do' => 29.00,
					'private_game_extra_fr_so' => 59.00,
					'is_private' => 1,
					'max_players' => 24,
				),
			);
			
			foreach ($default_modes as $mode) {
				$wpdb->insert(
					$table_game_modes,
					$mode,
					array('%s', '%s', '%d', '%f', '%f', '%f', '%f', '%d', '%d')
				);
			}
		}
	}
}

