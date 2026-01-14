<?php
/**
 * Widget für Kalender
 */

if (!defined('ABSPATH')) {
	exit;
}

class LTB_Widget extends WP_Widget {

	/**
	 * Konstruktor
	 */
	public function __construct() {
		parent::__construct(
			'ltb_calendar_widget',
			__('LaserTagPro Kalender', 'lasertagpro-buchung'),
			array('description' => __('Zeigt den Buchungskalender an', 'lasertagpro-buchung'))
		);
	}

	/**
	 * Widget ausgeben
	 */
	public function widget($args, $instance) {
		echo $args['before_widget'];
		
		if (!empty($instance['title'])) {
			echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
		}
		
		echo do_shortcode('[lasertagpro_kalender]');
		
		echo $args['after_widget'];
	}

	/**
	 * Widget-Formular
	 */
	public function form($instance) {
		$title = !empty($instance['title']) ? $instance['title'] : __('Buchungskalender', 'lasertagpro-buchung');
		?>
		<p>
			<label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php echo esc_html__('Titel:', 'lasertagpro-buchung'); ?></label>
			<input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
				   name="<?php echo esc_attr($this->get_field_name('title')); ?>" 
				   type="text" value="<?php echo esc_attr($title); ?>">
		</p>
		<?php
	}

	/**
	 * Widget speichern
	 */
	public function update($new_instance, $old_instance) {
		$instance = array();
		$instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
		return $instance;
	}
}




