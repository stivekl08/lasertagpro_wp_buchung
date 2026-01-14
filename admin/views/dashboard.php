<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html__('LaserTagPro Buchung - Dashboard', 'lasertagpro-buchung'); ?></h1>
	
	<div class="ltb-dashboard-stats">
		<div class="ltb-stat-box">
			<h3><?php echo esc_html__('Anstehende Reservierungen', 'lasertagpro-buchung'); ?></h3>
			<p class="ltb-stat-number"><?php echo esc_html($upcoming); ?></p>
		</div>
		
		<div class="ltb-stat-box">
			<h3><?php echo esc_html__('Ausstehend', 'lasertagpro-buchung'); ?></h3>
			<p class="ltb-stat-number"><?php echo esc_html($pending); ?></p>
		</div>
		
		<div class="ltb-stat-box">
			<h3><?php echo esc_html__('Bestätigt', 'lasertagpro-buchung'); ?></h3>
			<p class="ltb-stat-number"><?php echo esc_html($confirmed); ?></p>
		</div>
		
		<div class="ltb-stat-box">
			<h3><?php echo esc_html__('Storniert', 'lasertagpro-buchung'); ?></h3>
			<p class="ltb-stat-number"><?php echo esc_html($cancelled); ?></p>
		</div>
	</div>
	
	<div class="ltb-dashboard-actions">
		<a href="<?php echo esc_url(admin_url('admin.php?page=ltb-reservations')); ?>" class="button button-primary">
			<?php echo esc_html__('Alle Reservierungen anzeigen', 'lasertagpro-buchung'); ?>
		</a>
		<a href="<?php echo esc_url(admin_url('admin.php?page=ltb-settings')); ?>" class="button">
			<?php echo esc_html__('Einstellungen', 'lasertagpro-buchung'); ?>
		</a>
	</div>
</div>




