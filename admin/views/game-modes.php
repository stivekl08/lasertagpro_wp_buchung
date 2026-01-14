<?php
if (!defined('ABSPATH')) {
	exit;
}

if (isset($_GET['saved'])) {
	echo '<div class="notice notice-success"><p>' . esc_html__('Spielmodus gespeichert!', 'lasertagpro-buchung') . '</p></div>';
}

if (isset($_GET['deleted'])) {
	echo '<div class="notice notice-success"><p>' . esc_html__('Spielmodus gelöscht!', 'lasertagpro-buchung') . '</p></div>';
}
?>
<div class="wrap">
	<h1><?php echo esc_html__('Pakete & Preise verwalten', 'lasertagpro-buchung'); ?></h1>
	
	<?php
	$edit_id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
	$edit_mode = null;
	if ($edit_id) {
		global $wpdb;
		$table = $wpdb->prefix . 'ltb_game_modes';
		$edit_mode = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $edit_id));
	}
	?>
	
	<h2><?php echo $edit_mode ? esc_html__('Paket bearbeiten', 'lasertagpro-buchung') : esc_html__('Neues Paket', 'lasertagpro-buchung'); ?></h2>
	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
		<?php wp_nonce_field('ltb_game_mode'); ?>
		<input type="hidden" name="action" value="ltb_save_game_mode">
		<?php if ($edit_mode): ?>
			<input type="hidden" name="id" value="<?php echo esc_attr($edit_mode->id); ?>">
		<?php endif; ?>
		
		<table class="form-table">
			<tr>
				<th><label for="name"><?php echo esc_html__('Paket-Name', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="text" id="name" name="name" class="regular-text" value="<?php echo $edit_mode ? esc_attr($edit_mode->name) : ''; ?>" required>
					<p class="description"><?php echo esc_html__('z.B. "ACTION STARTER", "PREMIUM MISSION"', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="description"><?php echo esc_html__('Beschreibung', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<textarea id="description" name="description" rows="3" class="large-text"><?php echo $edit_mode ? esc_textarea($edit_mode->description) : ''; ?></textarea>
					<p class="description"><?php echo esc_html__('Kurze Beschreibung des Pakets', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="duration"><?php echo esc_html__('Dauer (Minuten)', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="number" id="duration" name="duration" min="15" max="180" step="15" value="<?php echo $edit_mode ? esc_attr($edit_mode->duration * 60) : '60'; ?>" required>
					<p class="description"><?php echo esc_html__('Dauer in Minuten (z.B. 60, 120, 180)', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="price"><?php echo esc_html__('Preis pro Person (€)', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="number" id="price" name="price" step="0.01" min="0" class="regular-text" value="<?php echo $edit_mode ? esc_attr($edit_mode->price) : ''; ?>" required>
					<p class="description"><?php echo esc_html__('Standard-Preis pro Person', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="price_weekend"><?php echo esc_html__('Wochenend-Preis (€)', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="number" id="price_weekend" name="price_weekend" step="0.01" min="0" class="regular-text" value="<?php echo $edit_mode ? esc_attr($edit_mode->price_weekend) : ''; ?>">
					<p class="description"><?php echo esc_html__('Preis für Wochenende (FR-SO). Leer = gleicher Preis wie Standard', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="min_players"><?php echo esc_html__('Minimale Personenanzahl', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="number" id="min_players" name="min_players" min="1" class="small-text" value="<?php echo $edit_mode ? esc_attr(isset($edit_mode->min_players) ? $edit_mode->min_players : 6) : '6'; ?>">
					<p class="description"><?php echo esc_html__('Ab wie vielen Personen buchbar (z.B. 6)', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="max_players"><?php echo esc_html__('Maximale Personenanzahl', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="number" id="max_players" name="max_players" min="1" class="small-text" value="<?php echo $edit_mode ? esc_attr($edit_mode->max_players ?: 24) : '24'; ?>">
					<p class="description"><?php echo esc_html__('Maximale Anzahl an Spielern', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="is_bestseller"><?php echo esc_html__('Best Seller', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="checkbox" id="is_bestseller" name="is_bestseller" value="1" <?php echo $edit_mode && isset($edit_mode->is_bestseller) && $edit_mode->is_bestseller ? 'checked' : ''; ?>>
					<label for="is_bestseller"><?php echo esc_html__('Als "Best Seller" markieren', 'lasertagpro-buchung'); ?></label>
				</td>
			</tr>
			<tr>
				<th><label for="is_private"><?php echo esc_html__('Privates Spiel', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="checkbox" id="is_private" name="is_private" value="1" <?php echo $edit_mode && $edit_mode->is_private ? 'checked' : ''; ?>>
					<label for="is_private"><?php echo esc_html__('Ist privates Spiel (nur für dieses Team)', 'lasertagpro-buchung'); ?></label>
				</td>
			</tr>
			<?php if (isset($edit_mode) && $edit_mode->is_private): ?>
			<tr>
				<th><label for="private_game_extra_mo_do"><?php echo esc_html__('Zusatzkosten MO-DO (€)', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="number" id="private_game_extra_mo_do" name="private_game_extra_mo_do" step="0.01" min="0" class="regular-text" value="<?php echo esc_attr($edit_mode->private_game_extra_mo_do); ?>">
				</td>
			</tr>
			<tr>
				<th><label for="private_game_extra_fr_so"><?php echo esc_html__('Zusatzkosten FR-SO (€)', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="number" id="private_game_extra_fr_so" name="private_game_extra_fr_so" step="0.01" min="0" class="regular-text" value="<?php echo esc_attr($edit_mode->private_game_extra_fr_so); ?>">
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<th><label for="sort_order"><?php echo esc_html__('Reihenfolge', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="number" id="sort_order" name="sort_order" value="<?php echo $edit_mode ? esc_attr($edit_mode->sort_order) : '0'; ?>">
					<p class="description"><?php echo esc_html__('Niedrigere Zahlen werden zuerst angezeigt', 'lasertagpro-buchung'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="active"><?php echo esc_html__('Aktiv', 'lasertagpro-buchung'); ?></label></th>
				<td>
					<input type="checkbox" id="active" name="active" value="1" <?php echo !$edit_mode || $edit_mode->active ? 'checked' : ''; ?>>
					<label for="active"><?php echo esc_html__('Paket ist aktiv und wird angezeigt', 'lasertagpro-buchung'); ?></label>
				</td>
			</tr>
		</table>
		
		<?php submit_button($edit_mode ? __('Änderungen speichern', 'lasertagpro-buchung') : __('Paket hinzufügen', 'lasertagpro-buchung')); ?>
		<?php if ($edit_mode): ?>
			<a href="<?php echo esc_url(admin_url('admin.php?page=ltb-game-modes')); ?>" class="button"><?php echo esc_html__('Abbrechen', 'lasertagpro-buchung'); ?></a>
		<?php endif; ?>
	</form>
	
	<h2><?php echo esc_html__('Vorhandene Pakete', 'lasertagpro-buchung'); ?></h2>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php echo esc_html__('Paket-Name', 'lasertagpro-buchung'); ?></th>
				<th><?php echo esc_html__('Dauer', 'lasertagpro-buchung'); ?></th>
				<th><?php echo esc_html__('Preis', 'lasertagpro-buchung'); ?></th>
				<th><?php echo esc_html__('Personen', 'lasertagpro-buchung'); ?></th>
				<th><?php echo esc_html__('Status', 'lasertagpro-buchung'); ?></th>
				<th><?php echo esc_html__('Aktionen', 'lasertagpro-buchung'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if (empty($game_modes)): ?>
				<tr>
					<td colspan="6"><?php echo esc_html__('Keine Spielmodi gefunden.', 'lasertagpro-buchung'); ?></td>
				</tr>
			<?php else: ?>
					<?php foreach ($game_modes as $mode): ?>
					<tr>
						<td>
							<strong><?php echo esc_html($mode->name); ?></strong>
							<?php if (isset($mode->is_bestseller) && $mode->is_bestseller): ?>
								<br><span style="color: orange; font-weight: bold; font-size: 0.9em;">★ Best Seller</span>
							<?php endif; ?>
							<?php if ($mode->is_private): ?>
								<br><span style="color: #666; font-size: 0.9em;">🔒 Privates Spiel</span>
							<?php endif; ?>
							<?php if ($mode->description): ?>
								<br><small style="color: #666;"><?php echo esc_html($mode->description); ?></small>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html(round($mode->duration * 60)); ?> <?php echo esc_html__('Min.', 'lasertagpro-buchung'); ?></td>
						<td>
							<strong><?php echo $mode->price ? esc_html(number_format($mode->price, 2, ',', '.') . ' €') : '-'; ?></strong>
							<?php if ($mode->price_weekend && $mode->price_weekend != $mode->price): ?>
								<br><small style="color: #666;"><?php echo esc_html__('WE:', 'lasertagpro-buchung'); ?> <?php echo esc_html(number_format($mode->price_weekend, 2, ',', '.') . ' €'); ?></small>
							<?php endif; ?>
						</td>
						<td>
							<?php 
							$min_players = isset($mode->min_players) ? $mode->min_players : 6;
							$max_players = $mode->max_players ?: 24;
							echo esc_html($min_players . '-' . $max_players);
							?>
						</td>
						<td>
							<?php if ($mode->active): ?>
								<span style="color: green;">✓ <?php echo esc_html__('Aktiv', 'lasertagpro-buchung'); ?></span>
							<?php else: ?>
								<span style="color: red;">✗ <?php echo esc_html__('Inaktiv', 'lasertagpro-buchung'); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<a href="<?php echo esc_url(admin_url('admin.php?page=ltb-game-modes&edit=' . $mode->id)); ?>" class="button button-small"><?php echo esc_html__('Bearbeiten', 'lasertagpro-buchung'); ?></a>
							<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ltb_delete_game_mode&id=' . $mode->id), 'ltb_delete_game_mode')); ?>" 
							   onclick="return confirm('<?php echo esc_js(__('Möchten Sie dieses Paket wirklich löschen?', 'lasertagpro-buchung')); ?>');"
							   class="button button-small button-link-delete"><?php echo esc_html__('Löschen', 'lasertagpro-buchung'); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>

