<?php
/**
 * Template part: Таймер обратного отсчёта до эфира.
 *
 * Показывается, только если задан заголовок и корректные дата/время эфира,
 * и эфир ещё не закончился. JS досчитывает таймер и по окончании скрывает блок.
 *
 * @package lubov-vera
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$timer_data = function_exists( 'lv_get_timer_data' ) ? lv_get_timer_data() : array(
	'title' => '',
	'date'  => '',
	'time'  => '',
);

$timer_title = isset( $timer_data['title'] ) ? trim( (string) $timer_data['title'] ) : '';
if ( '' === $timer_title ) {
	return;
}

$timer_date = isset( $timer_data['date'] ) ? trim( (string) $timer_data['date'] ) : '';
$timer_time = isset( $timer_data['time'] ) ? trim( (string) $timer_data['time'] ) : '';
if ( '' === $timer_time ) {
	$timer_time = '19:00';
}

$starts       = DateTimeImmutable::createFromFormat( 'd.m.Y H:i', $timer_date . ' ' . $timer_time, wp_timezone() );
$parse_errors = DateTimeImmutable::getLastErrors();

if ( false === $starts || ( is_array( $parse_errors ) && ( $parse_errors['warning_count'] > 0 || $parse_errors['error_count'] > 0 ) ) ) {
	return;
}

// Сколько минут после начала эфира показывать «В ЭФИРЕ».
$air_minutes = (int) apply_filters( 'lv_broadcast_air_minutes', 120 );
$ends        = $starts->modify( '+' . max( 0, $air_minutes ) . ' minutes' );
$now         = new DateTimeImmutable( 'now', wp_timezone() );

if ( $now >= $ends ) {
	return;
}

$is_live     = $now >= $starts;
$badge_class = $is_live ? 'countdown-badge is-live' : 'countdown-badge';

if ( ! function_exists( 'lv_format_countdown_diff' ) ) {
	/**
	 * Разбивает интервал в секундах на дд чч:мм:сс / чч:мм:сс / мм:сс.
	 *
	 * @param int $seconds Сколько секунд осталось.
	 * @return string
	 */
	function lv_format_countdown_diff( $seconds ) {
		$seconds = max( 0, (int) $seconds );
		$days    = intdiv( $seconds, DAY_IN_SECONDS );
		$hours   = intdiv( $seconds % DAY_IN_SECONDS, HOUR_IN_SECONDS );
		$minutes = intdiv( $seconds % HOUR_IN_SECONDS, MINUTE_IN_SECONDS );
		$secs    = $seconds % MINUTE_IN_SECONDS;

		if ( $days > 0 ) {
			return sprintf( '%dд %02d:%02d:%02d', $days, $hours, $minutes, $secs );
		}
		if ( $hours > 0 ) {
			return sprintf( '%02d:%02d:%02d', $hours, $minutes, $secs );
		}
		return sprintf( '%02d:%02d', $minutes, $secs );
	}
}
?>
<section class="countdown-section">
	<div class="container">
		<div class="countdown-banner">
			<div class="<?php echo esc_attr( $badge_class ); ?>">
				<span class="countdown-label"><?php esc_html_e( 'До эфира', 'lubov-vera' ); ?></span>
				<span class="countdown-timer" id="countdownTimer"
					data-start="<?php echo esc_attr( $starts->format( 'c' ) ); ?>"
					data-end="<?php echo esc_attr( $ends->format( 'c' ) ); ?>"><?php echo esc_html( $is_live ? __( 'В ЭФИРЕ', 'lubov-vera' ) : lv_format_countdown_diff( $starts->getTimestamp() - $now->getTimestamp() ) ); ?></span>
			</div>
			<div class="countdown-content">
				<span class="countdown-title"><?php echo esc_html( $timer_title ); ?></span>
				<span class="countdown-date"><?php echo esc_html( date_i18n( 'j F Y', $starts->getTimestamp() ) . ', ' . $starts->format( 'H:i' ) ); ?></span>
			</div>
			<a href="#schedule" class="countdown-link">
				<?php esc_html_e( 'Расписание трансляций', 'lubov-vera' ); ?>
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<path d="M5 12h14M12 5l7 7-7 7"/>
				</svg>
			</a>
		</div>
	</div>
</section>
