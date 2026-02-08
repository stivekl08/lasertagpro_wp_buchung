<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html__('Reservierungen', 'lasertagpro-buchung'); ?>
		<a href="<?php echo esc_url(add_query_arg(array('action' => 'new'), admin_url('admin.php?page=ltb-reservations'))); ?>" class="page-title-action"><?php echo esc_html__('Neue Reservierung', 'lasertagpro-buchung'); ?></a>
		<button type="button" class="page-title-action" id="ltb-export-btn"><?php echo esc_html__('Exportieren', 'lasertagpro-buchung'); ?></button>
		<button type="button" class="page-title-action" id="ltb-import-btn"><?php echo esc_html__('Importieren', 'lasertagpro-buchung'); ?></button>
		<button type="button" class="page-title-action" id="ltb-sync-btn"><?php echo esc_html__('Mit Kalender synchronisieren', 'lasertagpro-buchung'); ?></button>
	</h1>
	
	<?php if (isset($_GET['confirmed'])): ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html__('Reservierung wurde bestätigt. E-Mail-Bestätigung gesendet.', 'lasertagpro-buchung'); ?></p>
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
	
	<?php if (isset($_GET['imported'])): ?>
		<div class="notice notice-<?php echo absint($_GET['errors']) > 0 ? 'warning' : 'success'; ?> is-dismissible">
			<p>
				<strong><?php echo esc_html__('Import abgeschlossen:', 'lasertagpro-buchung'); ?></strong><br>
				<?php echo esc_html__('Erfolgreich:', 'lasertagpro-buchung'); ?> <?php echo absint($_GET['success']); ?> | 
				<?php echo esc_html__('Fehler:', 'lasertagpro-buchung'); ?> <?php echo absint($_GET['errors']); ?> | 
				<?php echo esc_html__('Übersprungen:', 'lasertagpro-buchung'); ?> <?php echo absint($_GET['skipped']); ?>
				<?php if (isset($_GET['updated']) && absint($_GET['updated']) > 0): ?>
					| <?php echo esc_html__('Aktualisiert:', 'lasertagpro-buchung'); ?> <?php echo absint($_GET['updated']); ?>
				<?php endif; ?>
				<?php if (isset($_GET['messages']) && !empty($_GET['messages'])): ?>
					<br><small><?php echo esc_html(urldecode($_GET['messages'])); ?></small>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>
	
	<?php if (isset($_GET['synced'])): ?>
		<div class="notice notice-<?php echo absint($_GET['errors']) > 0 ? 'warning' : 'success'; ?> is-dismissible">
			<p>
				<strong><?php echo esc_html__('Synchronisierung abgeschlossen:', 'lasertagpro-buchung'); ?></strong><br>
				<?php if (isset($_GET['direction']) && $_GET['direction'] === 'to_calendar'): ?>
					<?php echo esc_html__('Richtung: Reservierungen → Kalender', 'lasertagpro-buchung'); ?><br>
					<?php echo esc_html__('Erstellt:', 'lasertagpro-buchung'); ?> <?php echo absint($_GET['created']); ?> | 
					<?php echo esc_html__('Aktualisiert:', 'lasertagpro-buchung'); ?> <?php echo absint($_GET['updated']); ?> | 
					<?php echo esc_html__('Übersprungen:', 'lasertagpro-buchung'); ?> <?php echo absint($_GET['skipped']); ?> | 
					<?php echo esc_html__('Fehler:', 'lasertagpro-buchung'); ?> <?php echo absint($_GET['errors']); ?>
				<?php else: ?>
					<?php echo esc_html__('Richtung: Kalender → Reservierungen', 'lasertagpro-buchung'); ?><br>
					<?php echo esc_html__('Gefunden:', 'lasertagpro-buchung'); ?> <?php echo absint($_GET['found']); ?> | 
					<?php echo esc_html__('Erstellt:', 'lasertagpro-buchung'); ?> <?php echo absint($_GET['created']); ?> | 
					<?php echo esc_html__('Fehler:', 'lasertagpro-buchung'); ?> <?php echo absint($_GET['errors']); ?>
				<?php endif; ?>
				<?php if (isset($_GET['messages']) && !empty($_GET['messages'])): ?>
					<br><small><?php echo esc_html(urldecode($_GET['messages'])); ?></small>
				<?php endif; ?>
			</p>
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

<!-- Export Modal -->
<div id="ltb-export-modal" class="ltb-modal" style="display: none;">
	<div class="ltb-modal-content">
		<span class="ltb-modal-close">&times;</span>
		<h2><?php echo esc_html__('Reservierungen exportieren', 'lasertagpro-buchung'); ?></h2>
		<form method="get" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<?php wp_nonce_field('ltb_export_reservations'); ?>
			<input type="hidden" name="action" value="ltb_export_reservations">
			
			<table class="form-table">
				<tr>
					<th><label for="export_format"><?php echo esc_html__('Format', 'lasertagpro-buchung'); ?></label></th>
					<td>
						<select id="export_format" name="format">
							<option value="csv">CSV</option>
							<option value="json">JSON</option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="export_status"><?php echo esc_html__('Status', 'lasertagpro-buchung'); ?></label></th>
					<td>
						<select id="export_status" name="status">
							<option value=""><?php echo esc_html__('Alle', 'lasertagpro-buchung'); ?></option>
							<option value="pending" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'pending'); ?>><?php echo esc_html__('Ausstehend', 'lasertagpro-buchung'); ?></option>
							<option value="confirmed" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'confirmed'); ?>><?php echo esc_html__('Bestätigt', 'lasertagpro-buchung'); ?></option>
							<option value="cancelled" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'cancelled'); ?>><?php echo esc_html__('Storniert', 'lasertagpro-buchung'); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="export_date_from"><?php echo esc_html__('Von Datum', 'lasertagpro-buchung'); ?></label></th>
					<td>
						<input type="date" id="export_date_from" name="date_from" value="<?php echo esc_attr(isset($_GET['date_from']) ? $_GET['date_from'] : ''); ?>">
					</td>
				</tr>
				<tr>
					<th><label for="export_date_to"><?php echo esc_html__('Bis Datum', 'lasertagpro-buchung'); ?></label></th>
					<td>
						<input type="date" id="export_date_to" name="date_to" value="<?php echo esc_attr(isset($_GET['date_to']) ? $_GET['date_to'] : ''); ?>">
					</td>
				</tr>
			</table>
			
			<p class="submit">
				<input type="submit" class="button button-primary" value="<?php echo esc_attr__('Exportieren', 'lasertagpro-buchung'); ?>">
				<button type="button" class="button ltb-modal-cancel"><?php echo esc_html__('Abbrechen', 'lasertagpro-buchung'); ?></button>
			</p>
		</form>
	</div>
</div>

<!-- Import Modal -->
<div id="ltb-import-modal" class="ltb-modal" style="display: none;">
	<div class="ltb-modal-content">
		<span class="ltb-modal-close">&times;</span>
		<h2><?php echo esc_html__('Reservierungen importieren', 'lasertagpro-buchung'); ?></h2>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
			<?php wp_nonce_field('ltb_import_reservations'); ?>
			<input type="hidden" name="action" value="ltb_import_reservations">
			
			<table class="form-table">
				<tr>
					<th><label for="import_file"><?php echo esc_html__('Datei', 'lasertagpro-buchung'); ?> <span class="required">*</span></label></th>
					<td>
						<input type="file" id="import_file" name="import_file" accept=".csv,.json" required>
						<p class="description"><?php echo esc_html__('CSV oder JSON-Datei auswählen. Maximale Dateigröße: ', 'lasertagpro-buchung'); echo esc_html(size_format(wp_max_upload_size())); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="skip_duplicates"><?php echo esc_html__('Optionen', 'lasertagpro-buchung'); ?></label></th>
					<td>
						<label>
							<input type="checkbox" id="skip_duplicates" name="skip_duplicates" value="1" checked>
							<?php echo esc_html__('Duplikate überspringen', 'lasertagpro-buchung'); ?>
						</label><br>
						<label>
							<input type="checkbox" id="update_existing" name="update_existing" value="1">
							<?php echo esc_html__('Bestehende Reservierungen aktualisieren', 'lasertagpro-buchung'); ?>
						</label><br>
						<label>
							<input type="checkbox" id="validate_data" name="validate_data" value="1" checked>
							<?php echo esc_html__('Daten validieren', 'lasertagpro-buchung'); ?>
						</label>
					</td>
				</tr>
			</table>
			
			<p class="submit">
				<input type="submit" class="button button-primary" value="<?php echo esc_attr__('Importieren', 'lasertagpro-buchung'); ?>">
				<button type="button" class="button ltb-modal-cancel"><?php echo esc_html__('Abbrechen', 'lasertagpro-buchung'); ?></button>
			</p>
		</form>
	</div>
</div>

<!-- Sync Modal -->
<div id="ltb-sync-modal" class="ltb-modal" style="display: none;">
	<div class="ltb-modal-content">
		<span class="ltb-modal-close">&times;</span>
		<h2><?php echo esc_html__('Mit Kalender synchronisieren', 'lasertagpro-buchung'); ?></h2>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<?php wp_nonce_field('ltb_sync_reservations'); ?>
			<input type="hidden" name="action" value="ltb_sync_reservations">
			
			<table class="form-table">
				<tr>
					<th><label for="sync_direction"><?php echo esc_html__('Synchronisierungsrichtung', 'lasertagpro-buchung'); ?></label></th>
					<td>
						<select id="sync_direction" name="sync_direction">
							<option value="to_calendar"><?php echo esc_html__('Reservierungen → Kalender', 'lasertagpro-buchung'); ?></option>
							<option value="from_calendar"><?php echo esc_html__('Kalender → Reservierungen', 'lasertagpro-buchung'); ?></option>
						</select>
						<p class="description">
							<?php echo esc_html__('Reservierungen → Kalender: Erstellt/aktualisiert Events im Kalender für alle Reservierungen.', 'lasertagpro-buchung'); ?><br>
							<?php echo esc_html__('Kalender → Reservierungen: Findet Events im Kalender, die keine Reservierung haben.', 'lasertagpro-buchung'); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><label for="sync_date_from"><?php echo esc_html__('Von Datum', 'lasertagpro-buchung'); ?></label></th>
					<td>
						<input type="date" id="sync_date_from" name="date_from" value="<?php echo esc_attr(isset($_GET['date_from']) ? $_GET['date_from'] : ''); ?>">
						<p class="description"><?php echo esc_html__('Leer lassen für alle Daten', 'lasertagpro-buchung'); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="sync_date_to"><?php echo esc_html__('Bis Datum', 'lasertagpro-buchung'); ?></label></th>
					<td>
						<input type="date" id="sync_date_to" name="date_to" value="<?php echo esc_attr(isset($_GET['date_to']) ? $_GET['date_to'] : ''); ?>">
						<p class="description"><?php echo esc_html__('Leer lassen für alle Daten', 'lasertagpro-buchung'); ?></p>
					</td>
				</tr>
				<tr id="sync-status-row" style="display: none;">
					<th><label for="sync_status"><?php echo esc_html__('Status', 'lasertagpro-buchung'); ?></label></th>
					<td>
						<select id="sync_status" name="status">
							<option value=""><?php echo esc_html__('Alle (außer storniert)', 'lasertagpro-buchung'); ?></option>
							<option value="pending" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'pending'); ?>><?php echo esc_html__('Ausstehend', 'lasertagpro-buchung'); ?></option>
							<option value="confirmed" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'confirmed'); ?>><?php echo esc_html__('Bestätigt', 'lasertagpro-buchung'); ?></option>
						</select>
					</td>
				</tr>
				<tr id="sync-options-row">
					<th><label><?php echo esc_html__('Optionen', 'lasertagpro-buchung'); ?></label></th>
					<td>
						<label id="update-existing-label">
							<input type="checkbox" id="update_existing" name="update_existing" value="1">
							<?php echo esc_html__('Bestehende Events aktualisieren', 'lasertagpro-buchung'); ?>
						</label><br>
						<label id="create-missing-label" style="display: none;">
							<input type="checkbox" id="create_missing" name="create_missing" value="1">
							<?php echo esc_html__('Fehlende Reservierungen erstellen', 'lasertagpro-buchung'); ?>
						</label>
					</td>
				</tr>
			</table>
			
			<p class="submit">
				<input type="submit" class="button button-primary" value="<?php echo esc_attr__('Synchronisieren', 'lasertagpro-buchung'); ?>">
				<button type="button" class="button ltb-modal-cancel"><?php echo esc_html__('Abbrechen', 'lasertagpro-buchung'); ?></button>
			</p>
		</form>
	</div>
</div>

<script>
(function() {
	var syncDirection = document.getElementById('sync_direction');
	var syncStatusRow = document.getElementById('sync-status-row');
	var updateExistingLabel = document.getElementById('update-existing-label');
	var createMissingLabel = document.getElementById('create-missing-label');
	
	if (syncDirection) {
		syncDirection.addEventListener('change', function() {
			if (this.value === 'to_calendar') {
				syncStatusRow.style.display = 'table-row';
				updateExistingLabel.style.display = 'block';
				createMissingLabel.style.display = 'none';
			} else {
				syncStatusRow.style.display = 'none';
				updateExistingLabel.style.display = 'none';
				createMissingLabel.style.display = 'block';
			}
		});
		
		// Initiale Anzeige setzen
		syncDirection.dispatchEvent(new Event('change'));
	}
})();
</script>

<style>
.ltb-modal {
	display: none;
	position: fixed;
	z-index: 100000;
	left: 0;
	top: 0;
	width: 100%;
	height: 100%;
	overflow: auto;
	background-color: rgba(0,0,0,0.4);
}

.ltb-modal-content {
	background-color: #fefefe;
	margin: 5% auto;
	padding: 20px;
	border: 1px solid #888;
	width: 90%;
	max-width: 600px;
	position: relative;
}

.ltb-modal-close {
	color: #aaa;
	float: right;
	font-size: 28px;
	font-weight: bold;
	cursor: pointer;
	position: absolute;
	right: 15px;
	top: 10px;
}

.ltb-modal-close:hover,
.ltb-modal-close:focus {
	color: #000;
	text-decoration: none;
}

.ltb-modal-content h2 {
	margin-top: 0;
	padding-right: 30px;
}
</style>

<script>
(function() {
	var exportBtn = document.getElementById('ltb-export-btn');
	var importBtn = document.getElementById('ltb-import-btn');
	var syncBtn = document.getElementById('ltb-sync-btn');
	var exportModal = document.getElementById('ltb-export-modal');
	var importModal = document.getElementById('ltb-import-modal');
	var syncModal = document.getElementById('ltb-sync-modal');
	var closeButtons = document.querySelectorAll('.ltb-modal-close, .ltb-modal-cancel');
	
	if (exportBtn) {
		exportBtn.addEventListener('click', function() {
			exportModal.style.display = 'block';
		});
	}
	
	if (importBtn) {
		importBtn.addEventListener('click', function() {
			importModal.style.display = 'block';
		});
	}
	
	if (syncBtn) {
		syncBtn.addEventListener('click', function() {
			syncModal.style.display = 'block';
		});
	}
	
	closeButtons.forEach(function(btn) {
		btn.addEventListener('click', function() {
			exportModal.style.display = 'none';
			importModal.style.display = 'none';
			if (syncModal) {
				syncModal.style.display = 'none';
			}
		});
	});
	
	window.addEventListener('click', function(event) {
		if (event.target === exportModal) {
			exportModal.style.display = 'none';
		}
		if (event.target === importModal) {
			importModal.style.display = 'none';
		}
		if (syncModal && event.target === syncModal) {
			syncModal.style.display = 'none';
		}
	});
})();
</script>



