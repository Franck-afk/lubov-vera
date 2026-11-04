<?php
/**
 * Template part: Календарь служений
 *
 * @package lubov-vera
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<section class="calendar-section">
	<div class="container">
		<div class="calendar-header">
			<div class="nav-section">
				<div class="title"><?php esc_html_e( 'Календарь служений церкви', 'lubov-vera' ); ?></div>
				<div class="nav-buttons">
					<button class="nav-button" id="prevMonth" aria-label="<?php esc_attr_e( 'Предыдущий месяц', 'lubov-vera' ); ?>">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<path d="M15 18l-6-6 6-6"/>
						</svg>
					</button>
					<span class="date-range" id="dateRange"></span>
					<button class="nav-button" id="nextMonth" aria-label="<?php esc_attr_e( 'Следующий месяц', 'lubov-vera' ); ?>">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<path d="M9 18l6-6-6-6"/>
						</svg>
					</button>
				</div>
			</div>
		</div>
		<div class="calendar-container">
			<div class="calendar-grid" id="calendarGrid">
				<div class="day-header-cell">ВС</div>
				<div class="day-header-cell">ПН</div>
				<div class="day-header-cell">ВТ</div>
				<div class="day-header-cell">СР</div>
				<div class="day-header-cell">ЧТ</div>
				<div class="day-header-cell">ПТ</div>
				<div class="day-header-cell">СБ</div>
			</div>

			<div class="events-section">
				<h2><?php esc_html_e( 'Предстоящие события', 'lubov-vera' ); ?></h2>
				<div class="events-grid" id="eventsGrid"></div>
			</div>
		</div>
	</div>
</section>
