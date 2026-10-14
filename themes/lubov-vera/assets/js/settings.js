/**
 * Экран настроек темы.
 *
 * Табы (.lv-options__tab) переключают панели (.lv-options__tab-panel) и hash, без
 * перезагрузки. Сохранение — AJAX (фолбэк обычным POST), сброс — модалка.
 * Сохраняются все data-атрибуты контракта с PHP-партиалами.
 */
( function ( $ ) {
	'use strict';

	var TAB_CLASS   = '.lv-options__tab';
	var PANEL_CLASS = '.lv-options__tab-panel';
	var TAB_ACTIVE  = 'is-active';
	var PANEL_ACTIVE = 'active';

	function showPanel( key ) {
		$( TAB_CLASS ).removeClass( TAB_ACTIVE );
		$( TAB_CLASS + '[data-panel="' + key + '"]' ).addClass( TAB_ACTIVE );

		$( PANEL_CLASS ).removeClass( PANEL_ACTIVE ).prop( 'hidden', true );
		$( PANEL_CLASS + '[data-panel="' + key + '"]' ).addClass( PANEL_ACTIVE ).prop( 'hidden', false );

		// Виджет «Открытый раздел» в правом сайдбаре повторяет выбранный таб.
		$( '[data-lv-active-section]' ).text(
			$( TAB_CLASS + '[data-panel="' + key + '"]' ).text()
		);
	}

	$( document ).on( 'click', TAB_CLASS, function ( e ) {
		e.preventDefault();

		var key = String( $( this ).data( 'panel' ) );

		showPanel( key );

		if ( window.history && window.history.replaceState ) {
			window.history.replaceState( null, '', '#lv-' + key );
		}
	} );

	// Открытый раздел восстанавливается из адресной строки.
	$( function () {
		var key = ( window.location.hash || '' ).replace( '#lv-', '' );

		if ( key && $( PANEL_CLASS + '[data-panel="' + key + '"]' ).length ) {
			showPanel( key );
		}
	} );

	/* -------------------------------- Свернуть/развернуть описания полей */

	$( function () {
		var $page = $( '.lv-options' );

		$( document ).on( 'click', '.js-lv-expand', function () {
			$page.removeClass( 'lv-descs-off' );
		} );

		$( document ).on( 'click', '.js-lv-collapse', function () {
			$page.addClass( 'lv-descs-off' );
		} );
	} );

	/* ---------------------------------------------- Повторяющиеся блоки */

	$( function () {
		if ( ! $( '[data-lv-repeater]' ).length ) {
			return;
		}

		var TOKEN = '__index__';

		// Перенумеровать строки: PHP ожидает сплошные индексы с нуля,
		// иначе после удаления середины массив будет с дырой.
		function reindex( $rep ) {
			$rep.find( '[data-lv-rep-rows] > .lv-rep__row' ).each( function ( i ) {
				var $row = $( this );

				$row.find( '> .lv-rep__col' ).each( function () {
					var $col   = $( this );
					var $input = $col.find( 'input, select' ).first();
					var id     = $input.attr( 'id' ) || '';
					var name   = $input.attr( 'name' ) || '';

					if ( id.indexOf( TOKEN ) !== -1 ) {
						$input.attr( 'id', id.replace( TOKEN, String( i ) ) );
					} else if ( id ) {
						$input.attr( 'id', id.replace( /_(\d+)(_|$)/, '_' + i + '$2' ) );
					}

					// label лежит в той же колонке — просто подставляем id.
					$col.find( 'label' ).attr( 'for', $input.attr( 'id' ) );

					if ( name.indexOf( '[__index__]' ) !== -1 ) {
						$input.attr( 'name', name.replace( '[__index__]', '[' + i + ']' ) );
					} else {
						$input.attr( 'name', name.replace( /\[\d+\]/, '[' + i + ']' ) );
					}
				} );
			} );

			var $empty = $rep.find( '[data-lv-rep-empty]' );
			var has    = $rep.find( '[data-lv-rep-rows] > .lv-rep__row' ).length > 0;

			$empty.prop( 'hidden', has );
		}

		$( document ).on( 'click', '[data-lv-rep-add]', function () {
			var $field = $( this ).closest( '[data-lv-repeater]' );
			var $tpl   = $field.find( '[data-lv-rep-template]' )[ 0 ];
			var $rows  = $field.find( '[data-lv-rep-rows]' );

			if ( ! $tpl ) {
				return;
			}

			// Клонируем шаблон и снимаем disabled — иначе отключённые
			// контролы не отправятся в POST.
			var $row = $( $tpl.content.cloneNode( true ) ).find( '.lv-rep__row' );

			$row.find( '[disabled]' ).removeAttr( 'disabled' );
			$rows.append( $row );

			reindex( $field );
			$row.find( 'input, select' ).first().trigger( 'focus' );
		} );

		$( document ).on( 'click', '[data-lv-rep-remove]', function () {
			var $row   = $( this ).closest( '.lv-rep__row' );
			var $field = $row.closest( '[data-lv-repeater]' );

			$row.remove();
			reindex( $field );
		} );

		// Enter внутри строки добавляет следующую — быстрее, чем тянуться мышью.
		$( document ).on( 'keydown', '[data-lv-repeater] input', function ( e ) {
			if ( 13 !== e.which ) {
				return;
			}

			e.preventDefault();
			$( this ).closest( '[data-lv-repeater]' ).find( '[data-lv-rep-add]' ).trigger( 'click' );
		} );
	} );

	/* -------------------------------------------------------- Медиатека */

	$( function () {
		if ( ! $( '[data-lv-media]' ).length || ! window.wp || ! window.wp.media ) {
			return;
		}

		var frame = null;

		function paint( $box ) {
			var url = $.trim( $box.find( '[data-lv-media-url]' ).val() || '' );
			var $pv  = $box.find( '.lv-media__preview' );

			$box.find( '[data-lv-media-remove]' ).prop( 'hidden', '' === url );

			if ( '' === url ) {
				$pv.addClass( 'is-empty' ).html( '<span class="lv-media__placeholder">Нет картинки</span>' );
				return;
			}

			// src через img: значение приходит из формы, выводим его
			// как есть — экранирование делает атрибут src.
			$pv.removeClass( 'is-empty' ).html( $( '<img>' ).attr( 'src', url ).attr( 'alt', '' ) );
		}

		$( document ).on( 'click', '[data-lv-media-select]', function () {
			var $box = $( this ).closest( '[data-lv-media]' );

			if ( ! frame ) {
				frame = window.wp.media( {
					title: 'Выберите картинку',
					button: { text: 'Вставить' },
					library: { type: 'image' },
					multiple: false
				} );

				frame.on( 'select', function () {
					var a = frame.state().get( 'selection' ).first().toJSON();

					$box.find( '[data-lv-media-url]' ).val( a.url ).trigger( 'change' );
				} );
			}

			frame.open();
		} );

		$( document ).on( 'click', '[data-lv-media-remove]', function () {
			var $box = $( this ).closest( '[data-lv-media]' );

			$box.find( '[data-lv-media-url]' ).val( '' );
			paint( $box );
		} );

		// Ручная правка URL — превью должно поспевать за вводом.
		$( document ).on( 'keyup change', '[data-lv-media-url]', function () {
			var $box = $( this ).closest( '[data-lv-media]' );
			window.clearTimeout( $box.data( 'lvTimer' ) );
			$box.data( 'lvTimer', window.setTimeout( function () { paint( $box ); }, 300 ) );
		} );

		$( '[data-lv-media]' ).each( function () { paint( $( this ) ); } );
	} );

	/* ------------------------------------------------- Сохранение (AJAX) */

	$( function () {
		var cfg = window.LVSettings;

		// Без данных от PHP не перехватываем форму — она уйдёт обычным
		// POST, и всё по-прежнему сохранится, просто с перезагрузкой.
		if ( ! cfg || ! cfg.ajaxUrl ) {
			return;
		}

		var $form = $( '#lv-options-form' );
		var $buttons = $form.find( '[data-lv-save]' );
		var inFlight = false;

		function say( text, state ) {
			$( '[data-lv-status]' )
				.text( text )
				.toggleClass( 'is-saving', 'saving' === state )
				.toggleClass( 'is-success', 'success' === state )
				.toggleClass( 'is-error', 'error' === state );
		}

		// Пока идёт запрос, кнопки заблокированы и показывают прелоадер.
		function setBusy( on ) {
			$buttons
				.prop( 'disabled', on )
				.toggleClass( 'is-busy', on );

			$form.find( '[data-lv-spinner]' ).prop( 'hidden', ! on );
			$form.find( '[data-lv-save-label]' ).text( on ? cfg.strings.saving : cfg.strings.save );
		}

		$form.on( 'submit', function ( e ) {
			if ( inFlight ) {
				e.preventDefault();
				return;
			}

			e.preventDefault();
			inFlight = true;
			setBusy( true );
			say( cfg.strings.saving, 'saving' );

			$.post( cfg.ajaxUrl, $form.serialize() )
				.done( function ( res ) {
					if ( res && res.success ) {
						say( ( res.data && res.data.message ) || cfg.strings.saved, 'success' );
					} else {
						var m = res && res.data && res.data.message ? res.data.message : cfg.strings.failed;
						say( m, 'error' );
					}
				} )
				.fail( function ( xhr ) {
					// 403 — токен протух, обычным текстом это не объяснить.
					say( 403 === xhr.status ? cfg.strings.expired : cfg.strings.failed, 'error' );
				} )
				.always( function () {
					inFlight = false;
					setBusy( false );
				} );
		} );

		// Пока пользователь правит форму, надпись «сохранено» уже неправда.
		$form.on( 'change input', '[data-lv-status]', function () {
			say( '', '' );
		} );

		// Pseudo-кнопка «Сохранить» над формой: она вне формы, поэтому
		// шлёт её программно — обработчик submit выше делает AJAX.
		$( '.js-lv-save-top' ).on( 'click keydown', function ( e ) {
			if ( 'keydown' === e.type && 13 !== e.which && 32 !== e.which ) {
				return;
			}

			e.preventDefault();
			$form.trigger( 'submit' );
		} );

		// Ctrl/Cmd+S — привычное сочетание для сохранения.
		$( document ).on( 'keydown', function ( e ) {
			if ( ! ( ( e.ctrlKey || e.metaKey ) && 's' === String( e.key ).toLowerCase() ) ) {
				return;
			}

			if ( ! $( e.target ).closest( '#lv-options-form' ).length ) {
				return;
			}

			e.preventDefault();
			$buttons.first().trigger( 'click' );
		} );
	} );

	/* ------------------------------------------------- Сброс (с диалогом) */

	$( function () {
		var $modal = $( '[data-lv-modal]' );

		if ( ! $modal.length ) {
			return;
		}

		var $form = $( '#lv-options-form' );
		var $last = null;

		function open() {
			$last = document.activeElement;
			$modal.prop( 'hidden', false );
			$modal.find( '[data-lv-modal-confirm]' ).trigger( 'focus' );
		}

		function close() {
			$modal.prop( 'hidden', true );

			if ( $last && $last.focus ) {
				$last.focus();
			}
		}

		$( document ).on( 'click', '[data-lv-reset]', function () {
			open();
		} );

		$( document ).on( 'click', '[data-lv-modal-close]', function () {
			close();
		} );

		$( document ).on( 'keydown', function ( e ) {
			if ( $modal.prop( 'hidden' ) ) {
				return;
			}

			if ( 27 === e.which ) { // Esc
				close();
			}
		} );

		$( document ).on( 'click', '[data-lv-modal-confirm]', function () {
			var $btn = $( this );

			// Смена значения делается в двух местах: скрытое поле action
			// у формы и query-параметр, по которому WP ищет обработчик.
			var $action = $form.find( 'input[name="action"]' );

			if ( ! $action.length ) {
				return;
			}

			$action.val( 'lv_options_reset' );
			$btn.prop( 'disabled', true ).addClass( 'is-busy' );
			$btn.find( '.lv-spinner' ).prop( 'hidden', false );

			// Нативный submit минует наш обработчик выше — он перехватил бы
			// отправку и сделал AJAX, а здесь нужен обычный POST с редиректом.
			$form[ 0 ].submit();
		} );
	} );
} )( jQuery );