<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html__('Reservierungen', 'lasertagpro-buchung'); ?>
		<a href="<?php echo esc_url(add_query_arg(array('action' => 'new'), admin_url('admin.php?page=ltb-reservations'))); ?>" class="page-title-action"><?php echo esc_html__('Neue Reservierung', 'lasertagpro-buchung'); ?></a>
	</h1>
	
	<?php if (isset($_GET['confirmed'])): ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html__('Reservierung wurde bestätigt.', 'lasertagpro-buchung'); ?></p>
		</div>
	<?php endif; ?>
	
	<?php if (isset($_GET['cancelled'])): ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html__('Reservierung wurde storniert.', 'lasertagpro-buchung'); ?></p>
		</div>
	<?php endif; ?>
	
	<?php if (isset($_GET['deleted'])): ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html__('Reservierung wurde gelöscht.', 'lasertagpro-buchung'); ?></p>
		</div>
	<?php endif; ?>
	
	<?php if (isset($_GET['created'])): ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html__('Reservierung wurde erstellt.', 'lasertagpro-buchung'); ?></p>
		</div>
	<?php endif; ?>
	
	<?php if (isset($_GET['error'])): ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html(urldecode($_GET['error'])); ?></p>
		</div>
	<?php endif; ?>
	
	<?php if (isset($_GET['action']) && $_GET['action'] === 'new'): ?>
		<div class="ltb-new-reservation">
			<h2><?php echo esc_html__('Neue Reservierung', 'lasertagpro-buchung'); ?></h2>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
				<?php wp_nonce_field('ltb_create_reservation'); ?>
				<input type="hidden" name="action" value="ltb_create_reservation">
				
				<table class="form-table">
					<tr>
						<th><label for="booking_date"><?php echo esc_html__('Datum', 'lasertagpro-buchung'); ?> <span class="required">*</span></label></th>
						<td><input type="date" id="booking_date" name="booking_date" required></td>
					</tr>
					<tr>
						<th><label for="start_time"><?php echo esc_html__('Startzeit', 'lasertagpro-buchung'); ?> <span class="required">*</span></label></th>
						<td><input type="time" id="start_time" name="start_time" required></td>
					</tr>
					<tr>
						<th><label for="booking_duration"><?php echo esc_html__('Dauer (Stunden)', 'lasertagpro-buchung'); ?> <span class="required">*</span></label></th>
						<td><input type="number" id="booking_duration" name="booking_duration" min="1" max="3" value="1" required></td>
					</tr>
					<tr>
						<th><label for="name"><?php echo esc_html__('Name', 'lasertagpro-buchung'); ?> <span class="required">*</span></label></th>
						<td><input type="text" id="name" name="name" required></td>
					</tr>
					<tr>
						<th><label for="email"><?php echo esc_html__('E-Mail', 'lasertagpro-buchung'); ?> <span class="required">*</span></label></th>
						<td><input type="email" id="email" name="email" required></td>
					</tr>
					<tr>
						<th><label for="phone"><?php echo esc_html__('Telefon', 'lasertagpro-buchung'); ?></label></th>
						<td><input type="tel" id="phone" name="phone"></td>
					</tr>
					<tr>
						<th><label for="person_count"><?php echo esc_html__('Anzahl Personen', 'lasertagpro-buchung'); ?> <span class="required">*</span></label></th>
						<td><input type="number" id="person_count" name="person_count" min="1" required></td>
					</tr>
					<tr>
						<th><label for="game_mode"><?php echo esc_html__('Spielmodus', 'lasertagpro-buchung'); ?> <span class="required">*</span></label></th>
						<td>
							<select id="game_mode" name="game_mode" required>
								<?php
								global $wpdb;
								$table = $wpdb->prefix . 'ltb_game_modes';
								$game_modes = $wpdb->get_results("SELECT * FROM $table WHERE active = 1 ORDER BY name ASC");
								foreach ($game_modes as $mode) {
									echo '<option value="' . esc_attr($mode->name) . '">' . esc_html($mode->name) . '</option>';
								}
								?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="message"><?php echo esc_html__('Nachricht', 'lasertagpro-buchung'); ?></label></th>
						<td><textarea id="message" name="message" rows="4"></textarea></td>
					</tr>
				</table>
				
				<p class="submit">
					<input type="submit" class="button button-primary" value="<?php echo esc_attr__('Reservierung erstellen', 'lasertagpro-buchung'); ?>">
					<a href="<?php echo esc_url(admin_url('admin.php?page=ltb-reservations')); ?>" class="button"><?php echo esc_html__('Abbrechen', 'lasertagpro-buchung'); ?></a>
				</p>
			</form>
		</div>
	<?php else: ?>
	
	<form method="get" action="">
		<input type="hidden" name="page" value="ltb-reservations">
		
		<div class="ltb-filter-box">
			<label>
				<?php echo esc_html__('Status:', 'lasertagpro-buchung'); ?>
				<select name="status">
					<option value=""><?php echo esc_html__('Alle', 'lasertagpro-buchung'); ?></option>
					<option value="pending" <?php selected($status, 'pending'); ?>><?php echo esc_html__('Ausstehend', 'lasertagpro-buchung'); ?></option>
					<option value="confirmed" <?php selected($status, 'confirmed'); ?>><?php echo esc_html__('Bestätigt', 'lasertagpro-buchung'); ?></option>
					<option value="cancelled" <?php selected($status, 'cancelled'); ?>><?php echo esc_html__('Storniert', 'lasertagpro-buchung'); ?></option>
				</select>
			</label>
			
			<label>
				<?php echo esc_html__('Von:', 'lasertagpro-buchung'); ?>
				<input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>">
			</label>
			
			<label>
				<?php echo esc_html__('Bis:', 'lasertagpro-buchung'); ?>
				<input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>">
			</label>
			
			<input type="submit" class="button" value="<?php echo esc_attr__('Filtern', 'lasertagpro-buchung'); ?>">
		</div>
	</form>
	
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php echo esc_html__('ID', 'lasertagpro-buchung'); ?></th>
				<th><?php echo esc_html__('Datum', 'lasertagpro-buchung'); ?></th>
				<th><?php echo esc_html__('Zeit', 'lasertagpro-buchung'); ?></th>
				<th><?php echo esc_html__('Name', 'lasertagpro-buchung'); ?></th>
				<th><?php echo esc_html__('E-Mail', 'lasertagpro-buchung'); ?></th>
				<th><?php echo esc_html__('Personen', 'lasertagpro-buchung'); ?></th>
				<th><?php echo esc_html__('Spielmodus', 'lasertagpro-buchung'); ?></th>
				<th><?php echo esc_html__('Status', 'lasertagpro-buchung'); ?></th>
				<th><?php echo esc_html__('Aktionen', 'lasertagpro-buchung'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if (empty($reservations)): ?>
				<tr>
					<td colspan="9"><?php echo esc_html__('Keine Reservierungen gefunden.', 'lasertagpro-buchung'); ?></td>
				</tr>
			<?php else: ?>
				<?php foreach ($reservations as $reservation): ?>
					<tr>
						<td><?php echo esc_html($reservation->id); ?></td>
						<td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($reservation->booking_date))); ?></td>
						<td><?php echo esc_html(date_i18n(get_option('time_format'), strtotime($reservation->start_time))); ?> - <?php echo esc_html(date_i18n(get_option('time_format'), strtotime($reservation->end_time))); ?></td>
						<td><?php echo esc_html($reservation->name); ?></td>
						<td><?php echo esc_html($reservation->email); ?></td>
						<td><?php echo esc_html($reservation->person_count); ?></td>
						<td><?php echo esc_html($reservation->game_mode); ?></td>
						<td>
							<span class="ltb-status ltb-status-<?php echo esc_attr($reservation->status); ?>">
								<?php
								switch ($reservation->status) {
									case 'pending':
										echo esc_html__('Ausstehend', 'lasertagpro-buchung');
										break;
									case 'confirmed':
										echo esc_html__('Bestätigt', 'lasertagpro-buchung');
										break;
									case 'cancelled':
										echo esc_html__('Storniert', 'lasertagpro-buchung');
										break;
								}
								?>
							</span>
						</td>
						<td>
							<?php if ($reservation->status === 'pending'): ?>
								<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ltb_confirm_reservation&id=' . $reservation->id), 'ltb_confirm_reservation')); ?>" class="button button-small"><?php echo esc_html__('Bestätigen', 'lasertagpro-buchung'); ?></a>
							<?php endif; ?>
							
							<?php if ($reservation->status !== 'cancelled'): ?>
								<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ltb_cancel_reservation&id=' . $reservation->id), 'ltb_cancel_reservation')); ?>" class="button button-small" onclick="return confirm('<?php echo esc_js(__('Möchten Sie diese Reservierung wirklich stornieren?', 'lasertagpro-buchung')); ?>');"><?php echo esc_html__('Stornieren', 'lasertagpro-buchung'); ?></a>
							<?php endif; ?>
							
							<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ltb_delete_reservation&id=' . $reservation->id), 'ltb_delete_reservation')); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js(__('Möchten Sie diese Reservierung wirklich löschen?', 'lasertagpro-buchung')); ?>');"><?php echo esc_html__('Löschen', 'lasertagpro-buchung'); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	<?php endif; ?>
</div>



