<?php
/**
 * Warenkorb-Verwaltung
 */

if (!defined('ABSPATH')) {
	exit;
}

class LTB_Cart {

	/**
	 * Warenkorb abrufen
	 *
	 * @return array Warenkorb-Inhalt
	 */
	public static function get_cart() {
		if (!session_id() && !headers_sent()) {
			session_start();
		}
		
		return isset($_SESSION['ltb_cart']) ? $_SESSION['ltb_cart'] : array();
	}

	/**
	 * Artikel zum Warenkorb hinzufügen
	 *
	 * @param array $item Buchungsartikel
	 * @return bool Erfolg
	 */
	public static function add_to_cart($item) {
		if (!session_id() && !headers_sent()) {
			session_start();
		}
		
		if (!isset($_SESSION['ltb_cart'])) {
			$_SESSION['ltb_cart'] = array();
		}
		
		// Validierung
		if (empty($item['booking_date']) || empty($item['start_time']) || empty($item['game_mode'])) {
			return false;
		}
		
		// Eindeutige ID für Artikel generieren
		$item_id = md5($item['booking_date'] . $item['start_time'] . $item['game_mode']);
		
		// Preis berechnen (mit Dauer)
		$duration = isset($item['booking_duration']) ? absint($item['booking_duration']) : 1;
		$pricing = LTB_Pricing::calculate_slot_price(
			$item['booking_date'],
			$item['game_mode'],
			$item['person_count'],
			$duration
		);
		
		$item['item_id'] = $item_id;
		$item['price_per_person'] = $pricing['price_per_person'];
		$item['total_price'] = $pricing['total_price'];
		$item['base_price'] = $pricing['base_price'];
		$item['extra_price'] = $pricing['extra_price'];
		
		$_SESSION['ltb_cart'][$item_id] = $item;
		
		return true;
	}

	/**
	 * Artikel aus Warenkorb entfernen
	 *
	 * @param string $item_id Artikel-ID
	 * @return bool Erfolg
	 */
	public static function remove_from_cart($item_id) {
		if (!session_id() && !headers_sent()) {
			session_start();
		}
		
		if (isset($_SESSION['ltb_cart'][$item_id])) {
			unset($_SESSION['ltb_cart'][$item_id]);
			return true;
		}
		
		return false;
	}

	/**
	 * Warenkorb leeren
	 */
	public static function clear_cart() {
		if (!session_id() && !headers_sent()) {
			session_start();
		}
		
		unset($_SESSION['ltb_cart']);
	}

	/**
	 * Warenkorb-Gesamtsumme berechnen
	 *
	 * @param string $promo_code Optional: Promo-Code
	 * @return array Gesamtsumme mit Rabatten
	 */
	public static function calculate_total($promo_code = '') {
		$cart = self::get_cart();
		
		if (empty($cart)) {
			return array(
				'subtotal' => 0,
				'volume_discount' => 0,
				'volume_discount_percent' => 0,
				'promo_discount' => 0,
				'total' => 0,
			);
		}
		
		// Zwischensumme
		$subtotal = 0;
		foreach ($cart as $item) {
			$subtotal += $item['total_price'];
		}
		
		// Volumenrabatt DEAKTIVIERT - keine automatischen Rabatte
		$game_count = count($cart);
		$volume_discount_info = array('discount_percent' => 0);
		$volume_discount_data = array('discount_amount' => 0, 'final_price' => $subtotal);
		
		$price_after_volume = $subtotal;
		
		// Promo-Code-Rabatt
		$promo_discount = 0;
		if (!empty($promo_code)) {
			$promo_result = LTB_Pricing::validate_promo_code($promo_code, $price_after_volume);
			if (!is_wp_error($promo_result)) {
				$promo_discount = $promo_result['discount_amount'];
			}
		}
		
		$total = $price_after_volume - $promo_discount;
		
		return array(
			'subtotal' => $subtotal,
			'volume_discount' => 0, // DEAKTIVIERT - keine automatischen Rabatte
			'volume_discount_percent' => 0, // DEAKTIVIERT
			'promo_discount' => $promo_discount,
			'promo_code' => $promo_code,
			'total' => max(0, $total),
			'game_count' => $game_count,
		);
	}

	/**
	 * Verfügbarkeit für alle Artikel im Warenkorb prüfen
	 *
	 * @return array Verfügbarkeitsstatus
	 */
	public static function check_availability() {
		$cart = self::get_cart();
		$availability = array();
		
		foreach ($cart as $item_id => $item) {
			$is_available = LTB_Booking::check_availability(
				$item['booking_date'],
				$item['start_time'],
				$item['booking_duration']
			);
			
			$availability[$item_id] = $is_available;
		}
		
		return $availability;
	}
}

