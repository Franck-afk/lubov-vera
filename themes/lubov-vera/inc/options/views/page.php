<?php
/**
 * Каркас страницы настроек.
 *
 * Структура:
 *   .wrap.lv-options
 *     ├── h1 + .lv-options__cols (pseudo-кнопки + подсказка)
 *     └── .lv-options__body
 *           ├── .lv-options__main
 *           │     └── .lv-options__layout
 *           │           ├── .lv-options__tablist (вертикальные вкладки)
 *           │           └── .lv-options__content (форма + кастомные панели)
 *           └── .lv-options__sidebar (виджеты справа)
 *     └── .lv-options__toolbar (внизу страницы)
 *
 * Ожидает переменные от LV_Theme_Settings::render_page():
 *   $tabs, $saved, $active_tab, $updated, $filled_count, $imported, $export_json.
 *
 * @package LV_Theme
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap lv-options js-lv-options">

	<h1><?php esc_html_e( 'Настройки сайта', 'lubov-vera' ); ?></h1>

	<?php require get_template_directory() . '/inc/options/views/partial-header.php'; ?>

	<h3><?php esc_html_e( 'Настройки', 'lubov-vera' ); ?></h3>

	<div class="lv-options__cols">
		<div class="pseudo-button js-lv-save-top" role="button" tabindex="0"><?php esc_html_e( 'Сохранить', 'lubov-vera' ); ?></div>
		<div class="pseudo-button pseudo-button__green js-lv-expand"><?php esc_html_e( 'Развернуть описания', 'lubov-vera' ); ?></div>
		<div class="pseudo-button pseudo-button__gray js-lv-collapse"><?php esc_html_e( 'Свернуть описания', 'lubov-vera' ); ?></div>

		<p><?php esc_html_e( 'Настройки читаются из опции lv_site_settings — ту же, что использует фронт. Изменения применяются сразу после сохранения.', 'lubov-vera' ); ?></p>
		<p><strong><?php esc_html_e( 'Не забудьте сохранить настройки', 'lubov-vera' ); ?></strong></p>

		<div class="lv-options__body">
			<div class="lv-options__main">
				<div class="lv-options__layout">

					<div class="lv-options__tablist">
						<h2 class="lv-options__tabs js-lv-options__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Разделы настроек', 'lubov-vera' ); ?>">
							<?php foreach ( (array) $tabs as $lv_key => $lv_section ) : ?>
								<?php
								$lv_key       = (string) $lv_key;
								$lv_is_active = ( $lv_key === $active_tab );
								?>
								<a
									class="lv-options__tab<?php echo $lv_is_active ? ' is-active' : ''; ?>"
									id="lv-tab-<?php echo esc_attr( $lv_key ); ?>"
									href="#lv-<?php echo esc_attr( $lv_key ); ?>"
									data-panel="<?php echo esc_attr( $lv_key ); ?>"
									role="tab"
									aria-selected="<?php echo $lv_is_active ? 'true' : 'false'; ?>"
									aria-controls="lv-tab-in-<?php echo esc_attr( $lv_key ); ?>"
								><?php echo esc_html( $lv_section['label'] ); ?></a>
							<?php endforeach; ?>
						</h2>
					</div>

					<div class="lv-options__content">
						<form id="lv-options-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php // action и nonce; активный раздел переживает сохранение. ?>
							<input type="hidden" name="action" value="lv_options_save" />
							<?php wp_nonce_field( 'lv_options', '_lv_nonce' ); ?>
							<input type="hidden" name="lv_active_tab" id="lv_active_tab" value="<?php echo esc_attr( $active_tab ); ?>" />

							<?php foreach ( (array) $tabs as $lv_key => $lv_section ) : ?>
								<?php
								/*
								 * Секции с custom (Экспорт/Импорт) — не обычные поля: их
								 * панель рендерится отдельным циклом ниже, потому
								 * что вложенные <form> в HTML запрещены.
								 */
								if ( ! empty( $lv_section['custom'] ) ) {
									continue;
								}

								$lv_fields    = ! empty( $lv_section['fields'] ) ? (array) $lv_section['fields'] : array();
								$lv_deferred  = ! empty( $lv_section['deferred'] );
								$lv_is_active = ( (string) $lv_key === $active_tab );
								?>
								<div
									class="lv-options__tab-panel<?php echo $lv_is_active ? ' active' : ''; ?>"
									id="lv-tab-in-<?php echo esc_attr( $lv_key ); ?>"
									data-panel="<?php echo esc_attr( $lv_key ); ?>"
									role="tabpanel"
									aria-labelledby="lv-tab-<?php echo esc_attr( $lv_key ); ?>"
									<?php echo $lv_is_active ? '' : 'hidden'; ?>
								>
									<div class="lv-field-header"><?php echo esc_html( $lv_section['label'] ); ?></div>

									<?php if ( ! empty( $lv_section['desc'] ) ) : ?>
										<p><?php echo esc_html( $lv_section['desc'] ); ?></p>
									<?php endif; ?>

									<?php if ( $lv_deferred ) : ?>
										<p class="lv-placeholder">Раздел запланирован, поля появятся позже.</p>
									<?php elseif ( empty( $lv_fields ) ) : ?>
										<p class="lv-placeholder">В этом разделе пока нет полей.</p>
									<?php else : ?>
										<?php
										/*
										 * ВАЖНО: форма обязана отдавать ВСЕ объявленные ключи при каждом
										 * сохранении. sanitize() отбрасывает то, чего нет в POST, а merge()
										 * лишь сохраняет старое значение — записать новое в такой ключ
										 * будет уже невозможно. Поэтому рисуем все поля раздела, а не
										 * только заполненные.
										 */
										$lv_partials = array(
											'textarea'    => 'textarea.php',
											'page_select' => 'page-select.php',
											'media'       => 'media.php',
											'repeater'    => 'repeater.php',
											'toggle'      => 'toggle.php',
										);
										?>
										<div class="lv-fields">
											<?php foreach ( $lv_fields as $field_key => $field_def ) : ?>
												<?php
												// Имя переменной — именно $field: партиалы ждут контракт ($field, $saved).
												$field        = wp_parse_args(
													is_array( $field_def ) ? $field_def : array(),
													array(
														'label' => (string) $field_key,
														'desc'  => '',
													)
												);
												$field['key'] = (string) $field_key;

												$lv_type = isset( $field['type'] ) ? (string) $field['type'] : 'text';
												$partial = isset( $lv_partials[ $lv_type ] ) ? $lv_partials[ $lv_type ] : 'text.php';
												$partial = 'fields/' . $partial;

												if ( ! file_exists( get_template_directory() . '/inc/options/views/' . $partial ) ) {
													$partial = 'fields/text.php';
												}

												require get_template_directory() . '/inc/options/views/' . $partial;
												?>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>

						</form>

						<?php
						/*
						 * Панели, живущие вне главной формы (у них свои <form> — вложенные
						 * формы в HTML запрещены). Переключаются теми же табами, что и
						 * обычные панели: settings.js ищет панель по data-panel.
						 */
						foreach ( (array) $tabs as $lv_key => $lv_section ) :
							if ( empty( $lv_section['custom'] ) ) {
								continue;
							}

							$lv_custom = (string) $lv_section['custom'];

							if ( ! file_exists( get_template_directory() . '/inc/options/views/partial-' . $lv_custom . '.php' ) ) {
								continue;
							}

							require get_template_directory() . '/inc/options/views/partial-' . $lv_custom . '.php';
						endforeach;
						?>

					</div><!-- .lv-options__content -->

				</div><!-- .lv-options__layout -->

			</div><!-- .lv-options__main -->

			<div class="lv-options__sidebar">
				<?php require get_template_directory() . '/inc/options/views/partial-sidebar.php'; ?>
			</div>

		</div><!-- .lv-options__body -->

	</div><!-- .lv-options__cols -->

	<?php
	/*
	 * Панель сохранения — в самом низу страницы, ПОСЛЕ всех панелей.
	 * Она общая для всех вкладок, а у Экспорта/Импорта своя форма и свой
	 * <form>, поэтому тулбар лежит вне главной формы: кнопка «Сохранить»
	 * привязана к ней атрибутом form="lv-options-form".
	 */
	?>
	<div class="lv-options__toolbar js-lv-options__toolbar">
		<?php require get_template_directory() . '/inc/options/views/partial-toolbar.php'; ?>
	</div>

	<?php // Диалог подтверждения сброса. Вне формы, чтобы его кнопки не отправляли её. ?>
	<div class="lv-modal" data-lv-modal hidden>
		<div class="lv-modal__backdrop" data-lv-modal-close></div>

		<div class="lv-modal__box" role="dialog" aria-modal="true"
			aria-labelledby="lv-modal-title" aria-describedby="lv-modal-text">

			<h2 class="lv-modal__title" id="lv-modal-title">
				<?php esc_html_e( 'Сбросить все настройки?', 'lubov-vera' ); ?>
			</h2>

			<div class="lv-modal__text" id="lv-modal-text">
				<?php if ( ! empty( $filled_count ) ) : ?>
					<p>
						<?php
						printf(
							/* translators: %d: количество заполненных полей. */
							esc_html__( 'Заполнено полей: %d. Их значения вернутся к пустым или к значениям по умолчанию — отменить это будет нельзя.', 'lubov-vera' ),
							(int) $filled_count
						);
						?>
					</p>
				<?php else : ?>
					<p><?php esc_html_e( 'Заполненных полей нет, но сброс всё равно очистит остальные разделы.', 'lubov-vera' ); ?></p>
				<?php endif; ?>

				<p class="lv-modal__note">
					<?php esc_html_e( 'Шрифты сайта и настройки cookie-баннера не затрагиваются.', 'lubov-vera' ); ?>
				</p>
			</div>

			<div class="lv-modal__actions">
				<button type="button" class="button button-large" data-lv-modal-close>
					<?php esc_html_e( 'Отмена', 'lubov-vera' ); ?>
				</button>

				<button type="button" class="button button-primary button-large lv-modal__confirm" data-lv-modal-confirm>
					<span class="lv-spinner" hidden></span>
					<span class="lv-modal__confirm-label"><?php esc_html_e( 'Сбросить всё', 'lubov-vera' ); ?></span>
				</button>
			</div>
		</div>
	</div>
</div>
