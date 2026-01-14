<?php
/**
 * Preisberechnung
 */

if (!defined('ABSPATH')) {
	exit;
}

class LTB_Pricing {

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
		global $wpdb;
		
		$table = $wpdb->prefix . 'ltb_game_modes';
		$mode = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM $table WHERE name = %s AND active = 1",
			$game_mode
		));
		
		if (!$mode) {
			return array(
				'price_per_person' => 0,
				'total_price' => 0,
				'base_price' => 0,
				'extra_price' => 0,
			);
		}
		
		// Wochentag bestimmen (1=Montag, 7=Sonntag)
		$day_of_week = date('N', strtotime($date));
		$is_weekend = ($day_of_week >= 5); // Freitag, Samstag, Sonntag
		
		// Basispreis pro Person pro Stunde
		$base_price_per_hour = $is_weekend && $mode->price_weekend ? $mode->price_weekend : $mode->price;
		
		// Zusatzkosten für privates Spiel (einmalig, nicht pro Stunde)
		$extra_price = 0;
		if ($mode->is_private) {
			if ($is_weekend && $mode->private_game_extra_fr_so) {
				$extra_price = $mode->private_game_extra_fr_so;
			} elseif (!$is_weekend && $mode->private_game_extra_mo_do) {
				$extra_price = $mode->private_game_extra_mo_do;
			}
		}
		
		// Preis pro Person (pro Stunde)
		$price_per_person_per_hour = $base_price_per_hour;
		
		// Gesamtpreis = (Preis pro Person × Anzahl Personen × Dauer in Stunden) + Extra
		$total_price = ($base_price_per_hour * $person_count * $duration) + $extra_price;
		
		// Preis pro Person (gesamt, nicht pro Stunde)
		$price_per_person = $base_price_per_hour * $duration;
		
		return array(
			'price_per_person' => $price_per_person,
			'price_per_person_per_hour' => $price_per_person_per_hour,
			'total_price' => $total_price,
			'base_price' => $base_price_per_hour,
			'extra_price' => $extra_price,
			'duration' => $duration,
			'is_weekend' => $is_weekend,
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



