<?php
/**
 * Preisberechnung
 */

if (!defined('ABSPATH')) {
	exit;
}

class LTB_Pricing {

	/**
	 * Staffelpreise pro Person (pauschal, nicht pro Stunde)
	 */
	private static $package_prices = array(
		1 => 25.00,  // 60 Minuten: €25 pro Person
		2 => 35.00,  // 120 Minuten: €35 pro Person
		3 => 45.00,  // 180 Minuten: €45 pro Person
	);

	/**
	 * Preis für einen Slot berechnen
	 *
	 * @param string $date Datum (Y-m-d)
	 * @param string $game_mode Spielmodus-Name
	 * @param int $person_count Anzahl Personen
	 * @param int $duration Dauer in Stunden (Standard: 1)
	 * @return array Preisinformationen
	 */
	public static function calculate_slot_price($date, $game_mode, $person_count, $duration = 1) {
		// Staffelpreis pro Person basierend auf Dauer
		$price_per_person = isset(self::$package_prices[$duration]) 
			? self::$package_prices[$duration] 
			: self::$package_prices[1];
		
		// Gesamtpreis = Preis pro Person × Anzahl Personen
		$total_price = $price_per_person * $person_count;
		
		return array(
			'price_per_person' => $price_per_person,
			'price_per_person_per_hour' => $price_per_person,
			'total_price' => $total_price,
			'base_price' => $price_per_person,
			'extra_price' => 0,
			'duration' => $duration,
			'is_weekend' => false,
		);
	}

	/**
	 * Rabatt für mehrere Spiele berechnen
	 *
	 * @param int $game_count Anzahl der Spiele
	 * @return array Rabattinformationen
	 */
	public static function calculate_volume_discount($game_count) {
		$discount_percent = 0;
		
		if ($game_count >= 3) {
			$discount_percent = 70;
		} elseif ($game_count >= 2) {
			$discount_percent = 40;
		}
		
		return array(
			'discount_percent' => $discount_percent,
			'game_count' => $game_count,
		);
	}

	/**
	 * Preis mit Rabatt berechnen
	 *
	 * @param float $total_price Gesamtpreis
	 * @param int $discount_percent Rabatt in Prozent
	 * @return array Preis mit Rabatt
	 */
	public static function apply_discount($total_price, $discount_percent) {
		$discount_amount = ($total_price * $discount_percent) / 100;
		$final_price = $total_price - $discount_amount;
		
		return array(
			'original_price' => $total_price,
			'discount_percent' => $discount_percent,
			'discount_amount' => $discount_amount,
			'final_price' => $final_price,
		);
	}

	/**
	 * Promo-Code validieren und Rabatt berechnen
	 *
	 * @param string $code Promo-Code
	 * @param float $total_price Gesamtpreis
	 * @return array|WP_Error Promo-Code-Informationen oder Fehler
	 */
	public static function validate_promo_code($code, $total_price) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'ltb_promo_codes';
		
		$promo = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM $table WHERE code = %s AND active = 1",
			strtoupper($code)
		));
		
		if (!$promo) {
			return new WP_Error('invalid_code', __('Ungültiger Promo-Code.', 'lasertagpro-buchung'));
		}
		
		// Gültigkeitsprüfung
		$today = date('Y-m-d');
		if ($promo->valid_from && $promo->valid_from > $today) {
			return new WP_Error('not_valid_yet', __('Promo-Code ist noch nicht gültig.', 'lasertagpro-buchung'));
		}
		if ($promo->valid_until && $promo->valid_until < $today) {
			return new WP_Error('expired', __('Promo-Code ist abgelaufen.', 'lasertagpro-buchung'));
		}
		
		// Nutzungslimit prüfen
		if ($promo->usage_limit && $promo->usage_count >= $promo->usage_limit) {
			return new WP_Error('limit_reached', __('Promo-Code wurde bereits zu oft verwendet.', 'lasertagpro-buchung'));
		}
		
		// Rabatt berechnen
		$discount_amount = 0;
		if ($promo->discount_type === 'percent') {
			$discount_amount = ($total_price * $promo->discount_value) / 100;
		} else {
			$discount_amount = $promo->discount_value;
		}
		
		$final_price = max(0, $total_price - $discount_amount);
		
		return array(
			'code' => $promo->code,
			'discount_type' => $promo->discount_type,
			'discount_value' => $promo->discount_value,
			'discount_amount' => $discount_amount,
			'final_price' => $final_price,
			'promo_id' => $promo->id,
		);
	}

	/**
	 * Promo-Code-Nutzung erhöhen
	 *
	 * @param int $promo_id Promo-Code-ID
	 */
	public static function increment_promo_usage($promo_id) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'ltb_promo_codes';
		
		$wpdb->query($wpdb->prepare(
			"UPDATE $table SET usage_count = usage_count + 1 WHERE id = %d",
			$promo_id
		));
	}
}



