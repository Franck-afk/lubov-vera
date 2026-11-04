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

		// Виджет «Открытый раздел» в сайдбаре.
		var $section = $( '[data-lv-active-section]' );
		if ( $section.length ) {
			var $tab = $( TAB_CLASS + '[data-panel="' + key + '"]' );
			if ( $tab.length ) {
				$section.text( $tab.text() );
			}
		}
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

		// Новая строка может нести контролы с инициализацией (датапикер) —
		// signalling остальным модулям, что строка готова.
		$( document ).trigger( 'lv:rep:added', [ $row, $field ] );

		$row.find( 'input, select' ).first().trigger( 'focus' );
	} );

		$( document ).on( 'click', '[data-lv-rep-remove]', function () {
			var $row   = $( this ).closest( '.lv-rep__row' );
			var $field = $row.closest( '[data-lv-repeater]' );

			$row.remove();
			reindex( $field );
		} );

		// Копирование строки: тот же клон, что при добавлении, но со
		// значениями исходной. Вставляем сразу за источником.
		$( document ).on( 'click', '[data-lv-rep-copy]', function () {
			var $row   = $( this ).closest( '.lv-rep__row' );
			var $field = $row.closest( '[data-lv-repeater]' );

			// clone() без аргумента не тащит jQuery-данные и обработчики —
			// контролы клона должны пройти инициализацию заново.
			var $copy = $row.clone();

			// jQuery UI вешает класс hasDatepicker и кнопку-триггер прямо в
			// разметку; в клоне их надо снять, иначе .not('.hasDatepicker')
			// пропустит поле и датапикер не заработает.
			$copy.find( '.hasDatepicker' ).removeClass( 'hasDatepicker' );
			$copy.find( '.ui-datepicker-trigger' ).remove();

			$row.after( $copy );
			reindex( $field );

			// Сигнал датапикеру: в строке есть поля, требующие инициализации.
			$( document ).trigger( 'lv:rep:added', [ $copy, $field ] );

			$copy.find( 'input, select' ).first().trigger( 'focus' );
		} );

		// Enter внутри строки добавляет следующую — быстрее, чем тянуться мышью.
		$( document ).on( 'keydown', '[data-lv-repeater] input', function ( e ) {
			if ( 13 !== e.which ) {
				return;
			}

			e.preventDefault();
			$( this ).closest( '[data-lv-repeater]' ).find( '[data-lv-rep-add]' ).trigger( 'click' );
		} );

		// Драг-н-дроп строк: порядок меняет только ручка [data-lv-rep-drag],
		// чтобы не мешать вводу в полях. Перетаскивается сам элемент строки,
		// поэтому датапикеры/медиа остаются привязанными к своим инпутам.
		if ( $.fn.sortable ) {
			$( '[data-lv-rep-rows]' ).sortable( {
				items: '> .lv-rep__row',
				handle: '[data-lv-rep-drag]',
				cursor: 'grabbing',
				axis: 'y',
				tolerance: 'pointer',
				placeholder: 'lv-rep__placeholder',
				update: function () {
					// После перестановки пересобираем сплошные индексы —
					// PHP ожидает lv_options[key][i][sub] без дыр.
					reindex( $( this ).closest( '[data-lv-repeater]' ) );
				}
			} );
		}
	} );

	/* -------------------------------------------------------- Медиатека */

	$( function () {
		if ( ! $( '[data-lv-media]' ).length || ! window.wp || ! window.wp.media ) {
			return;
		}

		var frames = { image: null, file: null }; // Фрейм на режим: у файлов свой набор mime.
		var $current = null; // Поле, из которого медиатеку открыли последним.

		function paint( $box ) {
			var url = $.trim( $box.find( '[data-lv-media-url]' ).val() || '' );
			var $pv  = $box.find( '.lv-media__preview' );
			var isFile = $box.is( '[data-lv-file]' );

			$box.find( '[data-lv-media-remove]' ).prop( 'hidden', '' === url );

			if ( '' === url ) {
				$pv.addClass( 'is-empty' ).html(
					'<span class="lv-media__placeholder">' + ( isFile ? 'Нет файла' : 'Нет картинки' ) + '</span>'
				);
				return;
			}

			// У файлов вместо превью-картинки показываем имя документа.
			if ( isFile ) {
				var name = ( url.split( '#' )[ 0 ].split( '?' )[ 0 ].split( '/' ).pop() || '' );

				try {
					name = decodeURIComponent( name );
				} catch ( err ) { // Некорректный %-энкодинг — показываем как есть.
					name = url;
				}

				$pv.removeClass( 'is-empty' ).html(
					$( '<span>' ).attr( 'class', 'lv-media__file' ).attr( 'title', name ).text( name )
				);
				return;
			}

			// src через img: значение приходит из формы, выводим его
			// как есть — экранирование делает атрибут src.
			$pv.removeClass( 'is-empty' ).html( $( '<img>' ).attr( 'src', url ).attr( 'alt', '' ) );
		}

		$( document ).on( 'click', '[data-lv-media-select]', function () {
			// Медиа-полей на экране несколько (в т.ч. в строках повторителя),
			// поэтому целевое поле запоминаем на момент открытия, а не
			// на момент создания фрейма.
			$current = $( this ).closest( '[data-lv-media]' );

			// Файлы (документы повторителя) открывают отдельный фрейм без
			// ограничения «только картинки».
			var mode = $current && $current.is( '[data-lv-file]' ) ? 'file' : 'image';
			var frame = frames[ mode ];

			if ( ! frame ) {
				frame = window.wp.media( {
					title: 'file' === mode ? 'Выберите документ' : 'Выберите картинку',
					button: { text: 'Вставить' },
					library: 'file' === mode
						? { type: [ 'application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ] }
						: { type: 'image' },
					multiple: false
				} );

				frame.on( 'select', function () {
					var a = frame.state().get( 'selection' ).first().toJSON();

					if ( $current ) {
						$current.find( '[data-lv-media-url]' ).val( a.url ).trigger( 'change' );
					}
				} );

				frames[ mode ] = frame;
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

	/* --------------------------------------------------- Датапикер */

	$( function () {
		if ( ! $.fn.datepicker ) {
			return;
		}

		// Экран настроек русский по содержанию, поэтому календарь тоже
		// русский — независимо от языка интерфейса WordPress.
		var MONTHS = [
			'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
			'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'
		];
		var MONTHS_SHORT = [
			'Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн',
			'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'
		];
		var DAYS_MIN = [ 'Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб' ];

		function init( $scope ) {
			// jQuery UI помечает инициализированные поля классом hasDatepicker
			// (верблюжий регистр) — иначе повторная инициализация клона.
			$scope.find( '.lv-datepicker' ).not( '.hasDatepicker' ).each( function () {
				$( this ).datepicker( {
					dateFormat: 'dd.mm.yy',
					monthNames: MONTHS,
					monthNamesShort: MONTHS_SHORT,
					dayNamesMin: DAYS_MIN,
					firstDay: 1,
					changeMonth: true,
					changeYear: true,
					yearRange: 'c-2:c+10',
					showOn: 'button',
					buttonText: 'Выбрать дату',
					buttonTextOnly: true,
					prevText: 'Предыдущий месяц',
					nextText: 'Следующий месяц',
					currentText: 'Сегодня',
					closeText: 'Закрыть',
					showAnim: 'fadeIn'
				} );
			} );
		}

		init( $( document ) );

		// Строки повторителя приходят клонированием из <template> —
		// их датапикеры инициализируем после добавления.
		$( document ).on( 'lv:rep:added', function ( e, $row ) {
			init( $row );
		} );
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
		// Кнопка «Сохранить» живёт в тулбаре вне формы (панель внизу страницы),
		// поэтому ищем её и статус глобально, а не внутри $form.
		var $buttons = $( '[data-lv-save]' );
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

			$( '[data-lv-spinner]' ).prop( 'hidden', ! on );
			$( '[data-lv-save-label]' ).text( on ? cfg.strings.saving : cfg.strings.save );
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
		$form.on( 'change input', function () {
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

	/* ------------------------------------------------- Экспорт / Импорт */

	$( function () {
		// Копирование JSON-выгрузки в буфер обмена.
		$( document ).on( 'click', '[data-lv-export-copy]', function () {
			var $ta = $( '[data-lv-export-text]' ).first();

			if ( ! $ta.length ) {
				return;
			}

			var $st = $( '[data-lv-export-status]' );

			function done( ok ) {
				$st
					.text( ok ? 'Скопировано.' : 'Не удалось скопировать.' )
					.toggleClass( 'is-success', ok )
					.toggleClass( 'is-error', ! ok );

				window.setTimeout( function () {
					$st.text( '' ).removeClass( 'is-success is-error' );
				}, 2500 );
			}

			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( $ta.val() ).then(
					function () { done( true ); },
					function () { done( false ); }
				);
				return;
			}

			// Fallback для старых браузеров/контекста без https.
			$ta[ 0 ].select();
			done( document.execCommand( 'copy' ) );
		} );

		var $importForm = $( '#lv-import-form' );

		if ( ! $importForm.length ) {
			return;
		}

		var $file   = $importForm.find( '[data-lv-import-file]' );
		var $text   = $importForm.find( '[data-lv-import-text]' );
		var $status = $importForm.find( '[data-lv-import-status]' );
		var $btn    = $importForm.find( '[data-lv-import-submit]' );
		var inFlight = false;

		// Файл → textarea: настоящий разбор делает сервер, здесь только чтение.
		$file.on( 'change', function () {
			var file = this.files && this.files[ 0 ];

			if ( ! file ) {
				return;
			}

			var reader = new FileReader();

			reader.onload = function () {
				$text.val( String( reader.result || '' ) );
				$status
					.text( 'Файл загружен. Нажмите «Импортировать».' )
					.removeClass( 'is-success is-error' );
				$text.trigger( 'focus' );
			};

			reader.readAsText( file, 'utf-8' );

			// Сброс значения: повторный выбор того же файла снова сработает.
			this.value = '';
		} );

		// Штатные строки статуса.
		function sayImport( text, state ) {
			$status
				.text( text )
				.toggleClass( 'is-saving', 'saving' === state )
				.toggleClass( 'is-success', 'success' === state )
				.toggleClass( 'is-error', 'error' === state );
		}

		// Без данных от PHP форма уходит обычным POST и импортируется
		// классическим обработчиком (редирект + notice).
		var cfg = window.LVSettings;

		if ( ! cfg || ! cfg.ajaxUrl ) {
			return;
		}

		function importBusy( on ) {
			$btn.prop( 'disabled', on ).toggleClass( 'is-busy', on );
			$btn.find( '.lv-spinner' ).prop( 'hidden', ! on );
		}

		$importForm.on( 'submit', function ( e ) {
			if ( inFlight ) {
				e.preventDefault();
				return;
			}

			e.preventDefault();

			if ( '' === $.trim( $text.val() ) ) {
				sayImport( 'Вставьте JSON или загрузите файл.', 'error' );
				return;
			}

			inFlight = true;
			importBusy( true );
			sayImport( cfg.strings.importing || 'Импортируем…', 'saving' );

			$.post( cfg.ajaxUrl, {
				action: 'lv_options_import',
				_lv_nonce: cfg.nonce,
				lv_import_json: $text.val()
			} )
				.done( function ( res ) {
					if ( res && res.success ) {
						var msg = ( res.data && res.data.message ) || cfg.strings.imported || 'Настройки импортированы.';
						sayImport( msg, 'success' );
					} else {
						var m = res && res.data && res.data.message ? res.data.message : cfg.strings.import_failed;
						sayImport( m, 'error' );
					}
				} )
				.fail( function ( xhr ) {
					sayImport( 403 === xhr.status ? cfg.strings.expired : cfg.strings.import_failed, 'error' );
				} )
				.always( function () {
					inFlight = false;
					importBusy( false );
				} );
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