<?php
if (!defined('ABSPATH')) {
	exit;
}

$current_month = !empty($atts['month']) ? absint($atts['month']) : date('n');
$current_year = !empty($atts['year']) ? absint($atts['year']) : date('Y');

// Mindestanzahl Spieler aus Einstellungen
$min_players = absint(get_option('ltb_min_players', 1));

global $wpdb;
$table = $wpdb->prefix . 'ltb_game_modes';
$game_modes = $wpdb->get_results("SELECT * FROM $table WHERE active = 1 ORDER BY sort_order ASC, name ASC");
?>
<div class="ltb-booking-container">
	<div class="ltb-booking-main">
		<!-- Schritt 1: Spieleranzahl -->
		<div class="ltb-step ltb-step-1 ltb-step-active" data-step="1">
			<h2 class="ltb-step-title">1. <?php echo esc_html__('Wähle die Anzahl der Spieler', 'lasertagpro-buchung'); ?></h2>
			<div class="ltb-player-selector">
				<button type="button" class="ltb-btn-minus" aria-label="<?php echo esc_attr__('Weniger Spieler', 'lasertagpro-buchung'); ?>">−</button>
				<span class="ltb-player-count" id="ltb-player-count"><?php echo esc_html($min_players); ?></span>
				<button type="button" class="ltb-btn-plus" aria-label="<?php echo esc_attr__('Mehr Spieler', 'lasertagpro-buchung'); ?>">+</button>
			</div>
			<button type="button" class="ltb-btn-primary ltb-next-step" data-next="2"><?php echo esc_html__('Weiter', 'lasertagpro-buchung'); ?></button>
		</div>

		<!-- Schritt 2: Buchungsdauer -->
		<div class="ltb-step ltb-step-2" data-step="2" style="display: none;">
			<h2 class="ltb-step-title">2. <?php echo esc_html__('Wähle dein Paket', 'lasertagpro-buchung'); ?></h2>
			<div class="ltb-duration-packages">
				<div class="ltb-package-card" data-duration="1">
					<span class="ltb-package-label"><?php echo esc_html__('ACTION STARTER', 'lasertagpro-buchung'); ?></span>
					<span class="ltb-package-duration">60 <?php echo esc_html__('Minuten', 'lasertagpro-buchung'); ?></span>
					<span class="ltb-package-price">€25,-</span>
					<span class="ltb-package-per-person"><?php echo esc_html__('pro Person', 'lasertagpro-buchung'); ?></span>
				</div>
				<div class="ltb-package-card ltb-bestseller" data-duration="2">
					<span class="ltb-package-badge"><?php echo esc_html__('Best Seller', 'lasertagpro-buchung'); ?></span>
					<span class="ltb-package-label"><?php echo esc_html__('PREMIUM MISSION', 'lasertagpro-buchung'); ?></span>
					<span class="ltb-package-duration">120 <?php echo esc_html__('Minuten', 'lasertagpro-buchung'); ?></span>
					<span class="ltb-package-price">€35,-</span>
					<span class="ltb-package-per-person"><?php echo esc_html__('pro Person', 'lasertagpro-buchung'); ?></span>
				</div>
				<div class="ltb-package-card" data-duration="3">
					<span class="ltb-package-label"><?php echo esc_html__('ELITE OPERATION', 'lasertagpro-buchung'); ?></span>
					<span class="ltb-package-duration">180 <?php echo esc_html__('Minuten', 'lasertagpro-buchung'); ?></span>
					<span class="ltb-package-price">€45,-</span>
					<span class="ltb-package-per-person"><?php echo esc_html__('pro Person', 'lasertagpro-buchung'); ?></span>
				</div>
			</div>
			<ul class="ltb-package-features">
				<li>✓ <?php echo esc_html__('Ab 6 Personen buchbar', 'lasertagpro-buchung'); ?></li>
				<li>✓ <?php echo esc_html__('Profi-Ausrüstung inkl.', 'lasertagpro-buchung'); ?></li>
				<li>✓ <?php echo esc_html__('Mineralwasser inkl.', 'lasertagpro-buchung'); ?></li>
				<li>✓ <?php echo esc_html__('Gratis Fotoservice', 'lasertagpro-buchung'); ?></li>
			</ul>
			<button type="button" class="ltb-btn-secondary ltb-prev-step" data-prev="1"><?php echo esc_html__('Zurück', 'lasertagpro-buchung'); ?></button>
			<button type="button" class="ltb-btn-primary ltb-next-step" data-next="3" style="display: none;"><?php echo esc_html__('Weiter', 'lasertagpro-buchung'); ?></button>
		</div>

		<!-- Schritt 3: Datum -->
		<div class="ltb-step ltb-step-3" data-step="3" style="display: none;">
			<h2 class="ltb-step-title">3. <?php echo esc_html__('Wähle ein Datum', 'lasertagpro-buchung'); ?></h2>
			<div class="ltb-date-navigation">
				<button type="button" class="ltb-nav-btn ltb-prev-date" aria-label="<?php echo esc_attr__('Vorheriger Tag', 'lasertagpro-buchung'); ?>">‹</button>
				<div class="ltb-current-date ltb-date-picker-trigger">
					<span class="ltb-date-display"></span>
					<span class="ltb-calendar-icon">📅</span>
					<input type="date" class="ltb-date-input" aria-label="<?php echo esc_attr__('Datum wählen', 'lasertagpro-buchung'); ?>">
				</div>
				<button type="button" class="ltb-nav-btn ltb-next-date" aria-label="<?php echo esc_attr__('Nächster Tag', 'lasertagpro-buchung'); ?>">›</button>
			</div>
			<p class="ltb-date-hint"><?php echo esc_html__('Klicke auf das Datum für Kalender-Auswahl', 'lasertagpro-buchung'); ?></p>
			<div class="ltb-step-actions">
				<button type="button" class="ltb-btn-secondary ltb-prev-step" data-prev="2"><?php echo esc_html__('Zurück', 'lasertagpro-buchung'); ?></button>
				<button type="button" class="ltb-btn-primary ltb-next-step" data-next="4"><?php echo esc_html__('Weiter', 'lasertagpro-buchung'); ?></button>
			</div>
		</div>

		<!-- Schritt 4: Zeit-Slots -->
		<div class="ltb-step ltb-step-4" data-step="4" style="display: none;">
			<h2 class="ltb-step-title">4. <?php echo esc_html__('Wähle eine Uhrzeit', 'lasertagpro-buchung'); ?></h2>
			<div class="ltb-time-slots-container">
				<div class="ltb-calendar-loading">
					<p><?php echo esc_html__('Lädt verfügbare Zeiten...', 'lasertagpro-buchung'); ?></p>
				</div>
				<div class="ltb-time-slots-grid"></div>
			</div>
			<div class="ltb-step-actions">
				<button type="button" class="ltb-btn-secondary ltb-prev-step" data-prev="3"><?php echo esc_html__('Zurück', 'lasertagpro-buchung'); ?></button>
				<button type="button" class="ltb-btn-secondary ltb-select-another-date" style="display: none;"><?php echo esc_html__('Anderes Datum wählen', 'lasertagpro-buchung'); ?></button>
			</div>
		</div>

		<!-- Rabatt-Banner ENTFERNT -->
	</div>

	<!-- Warenkorb-Sidebar -->
	<div class="ltb-cart-sidebar">
		<h3 class="ltb-cart-title"><?php echo esc_html__('Warenkorb', 'lasertagpro-buchung'); ?></h3>
		<div class="ltb-cart-content">
			<div class="ltb-cart-items"></div>
			<div class="ltb-cart-summary">
				<div class="ltb-cart-line">
					<span><?php echo esc_html__('Spiele', 'lasertagpro-buchung'); ?>:</span>
					<span class="ltb-cart-subtotal">€0.00</span>
				</div>
				<div class="ltb-cart-line ltb-volume-discount" style="display: none;">
					<span><?php echo esc_html__('Rabatt', 'lasertagpro-buchung'); ?>:</span>
					<span class="ltb-discount-amount">-€0.00</span>
				</div>
				<div class="ltb-cart-line ltb-promo-discount" style="display: none;">
					<span><?php echo esc_html__('Promo-Code', 'lasertagpro-buchung'); ?>:</span>
					<span class="ltb-promo-amount">-€0.00</span>
				</div>
				<div class="ltb-cart-total">
					<span><?php echo esc_html__('Gesamt', 'lasertagpro-buchung'); ?>:</span>
					<span class="ltb-total-amount">€0.00</span>
				</div>
				<div class="ltb-per-person">
					<span class="ltb-per-person-amount">€0.00</span> <?php echo esc_html__('pro Spieler', 'lasertagpro-buchung'); ?>
				</div>
			</div>
			<div class="ltb-promo-section">
				<input type="text" class="ltb-promo-input" placeholder="<?php echo esc_attr__('Promo-Code hinzufügen', 'lasertagpro-buchung'); ?>" id="ltb-promo-code">
				<button type="button" class="ltb-btn-promo"><?php echo esc_html__('Anwenden', 'lasertagpro-buchung'); ?></button>
			</div>
			<div class="ltb-gift-card-section">
				<input type="text" class="ltb-gift-card-input" placeholder="<?php echo esc_attr__('Geschenkkarte hinzufügen', 'lasertagpro-buchung'); ?>">
			</div>
			<button type="button" class="ltb-btn-checkout" disabled><?php echo esc_html__('Bestellung abschließen', 'lasertagpro-buchung'); ?></button>
		</div>
	</div>

	<!-- Checkout-Formular (Modal) -->
	<div class="ltb-checkout-modal" style="display: none;">
		<div class="ltb-modal-content">
			<button type="button" class="ltb-modal-close" aria-label="<?php echo esc_attr__('Schließen', 'lasertagpro-buchung'); ?>">×</button>
			<h2><?php echo esc_html__('Bestellung abschließen', 'lasertagpro-buchung'); ?></h2>
			<form id="ltb-checkout-form" class="ltb-checkout-form">
				<div class="ltb-form-group">
					<label for="ltb-checkout-name"><?php echo esc_html__('Name', 'lasertagpro-buchung'); ?> <span class="required">*</span></label>
					<input type="text" id="ltb-checkout-name" name="name" required>
				</div>
				<div class="ltb-form-group">
					<label for="ltb-checkout-email"><?php echo esc_html__('E-Mail', 'lasertagpro-buchung'); ?> <span class="required">*</span></label>
					<input type="email" id="ltb-checkout-email" name="email" required>
				</div>
				<div class="ltb-form-group">
					<label for="ltb-checkout-phone"><?php echo esc_html__('Telefon/WhatsApp', 'lasertagpro-buchung'); ?> <span class="recommended"><?php echo esc_html__('(empfohlen)', 'lasertagpro-buchung'); ?></span></label>
					<input type="tel" id="ltb-checkout-phone" name="phone" placeholder="+43 660 1234567">
					<p class="ltb-whatsapp-hint">
						<span class="ltb-whatsapp-icon">📱</span>
						<?php echo esc_html__('Die Bestätigung inkl. Anfahrtsplan wird per WhatsApp versendet!', 'lasertagpro-buchung'); ?>
					</p>
				</div>
				<div class="ltb-form-group">
					<label for="ltb-checkout-message"><?php echo esc_html__('Nachricht', 'lasertagpro-buchung'); ?></label>
					<textarea id="ltb-checkout-message" name="message" rows="4"></textarea>
				</div>
				<div class="ltb-form-actions">
					<button type="button" class="ltb-btn-secondary ltb-modal-cancel"><?php echo esc_html__('Abbrechen', 'lasertagpro-buchung'); ?></button>
					<button type="submit" class="ltb-btn-primary"><?php echo esc_html__('Buchen', 'lasertagpro-buchung'); ?></button>
				</div>
			</form>
		</div>
	</div>

	<div class="ltb-message" role="alert" aria-live="polite"></div>
</div>
