<?php
/**
 * Template part: Расписание трансляций
 *
 * @package lubov-vera
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$schedule_title = function_exists( 'lv_get_setting' ) ? lv_get_setting( 'schedule_title', 'Расписание трансляций' ) : '';
$schedule_list  = function_exists( 'lv_get_setting' ) ? lv_get_setting( 'schedule', array() ) : array();

if ( empty( $schedule_list ) || ! is_array( $schedule_list ) ) {
	return;
}
?>

<section id="schedule" class="schedule-section">
	<div class="container">
		<div class="section-header">
			<h2 class="section-title"><?php echo esc_html( $schedule_title ); ?></h2>
		</div>
		<div class="schedule-grid">
			<?php
			foreach ( $schedule_list as $item ) :
				if ( empty( $item['day'] ) || empty( $item['time'] ) ) {
					continue;
				}
				?>
			<div class="schedule-card">
				<div class="schedule-day"><?php echo esc_html( $item['day'] ); ?></div>
				<div class="schedule-time"><?php echo esc_html( $item['time'] ); ?></div>
				<div class="schedule-text"><?php echo esc_html( $item['text'] ); ?></div>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
