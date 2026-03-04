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
		return isset($_SESSION['ltb_cart']) ? $_SESSION['ltb_cart'] : array();
	}

	/**
	 * Artikel zum Warenkorb hinzufügen
	 *
	 * @param array $item Buchungsartikel
	 * @return bool Erfolg
	 */
	public static function add_to_cart($item) {
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
		unset($_SESSION['ltb_cart']);
	}

	/**
	 * Warenkorb-Gesamtsumme berechnen
	 *
	 * @return array Gesamtsumme
	 */
	public static function calculate_total() {
		$cart = self::get_cart();

		if (empty($cart)) {
			return array(
				'subtotal'   => 0,
				'total'      => 0,
				'game_count' => 0,
			);
		}

		$subtotal   = 0;
		$game_count = count($cart);
		foreach ($cart as $item) {
			$subtotal += $item['total_price'];
		}

		return array(
			'subtotal'   => $subtotal,
			'total'      => $subtotal,
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

