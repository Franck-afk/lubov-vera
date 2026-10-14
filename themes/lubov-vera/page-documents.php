<?php
/**
 * Template Name: Документы
 *
 * @package lubov-vera
 */

get_header();

$documents  = lv_get_setting( 'documents', array() );
$page_title = get_the_title();
?>

<main class="main">
	<div class="container">
		<!-- Хлебные крошки -->
		<nav class="breadcrumbs" aria-label="Хлебные крошки">
			<ul class="breadcrumbs__list">
				<li class="breadcrumbs__item">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="breadcrumbs__link">Главная</a>
				</li>
				<li class="breadcrumbs__item">
					<span class="breadcrumbs__current"><?php echo esc_html( $page_title ); ?></span>
				</li>
			</ul>
		</nav>

		<div class="documents-page">
			<h1 class="page-title"><?php echo esc_html( $page_title ); ?></h1>

			<?php if ( ! empty( $documents ) ) : ?>
			<div class="documents-wrapper">
				<!-- Список документов (слева) -->
				<div class="documents-list">
					<h2 class="documents-list__title">Список документов</h2>
					<?php
					foreach ( $documents as $index => $doc ) :
						if ( ! is_array( $doc ) ) {
							continue;
						}
						$file_url = $doc['file'] ?? '';
						$lv_title = $doc['title'] ?? '';
						if ( empty( $lv_title ) ) {
							$lv_title = 'Без названия';
						}
						if ( empty( $file_url ) ) {
							continue;
						}
						?>
					<div class="document-item <?php echo 0 === $index ? 'active' : ''; ?>"
						data-doc-index="<?php echo esc_attr( $index ); ?>"
						data-url="<?php echo esc_url( $file_url ); ?>"
						data-title="<?php echo esc_attr( $lv_title ); ?>">
						<div class="document-item__icon">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
								<polyline points="14 2 14 8 20 8"></polyline>
								<line x1="16" y1="13" x2="8" y2="13"></line>
								<line x1="16" y1="17" x2="8" y2="17"></line>
							</svg>
						</div>
						<div class="document-item__content">
							<span class="document-item__title"><?php echo esc_html( $lv_title ); ?></span>
						</div>
					</div>
					<?php endforeach; ?>
				</div>

				<!-- Просмотрщик (справа) -->
				<div class="documents-viewer">
					<div class="documents-viewer__container" id="doc-viewer-container">
						<div class="documents-viewer__placeholder">
							<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
								<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
								<polyline points="14 2 14 8 20 8"></polyline>
							</svg>
							<p>Выберите документ из списка</p>
						</div>
					</div>
				</div>
			</div>
			<?php else : ?>
			<div class="documents-empty">
				<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
					<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
					<polyline points="14 2 14 8 20 8"></polyline>
				</svg>
				<h3>Документы не добавлены</h3>
				<p>В настоящее время нет доступных документов для просмотра.</p>
			</div>
			<?php endif; ?>
		</div>
	</div>
</main>

	<script src="<?php echo esc_url( get_template_directory_uri() . '/assets/js/pdfjs/pdf.min.js' ); ?>" nonce="<?php echo esc_attr( $GLOBALS['lv_csp_nonce'] ); ?>"></script><?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Template-specific PDF.js; must exist before the inline viewer below. ?>
	<script nonce="<?php echo esc_attr( $GLOBALS['lv_csp_nonce'] ); ?>">
	pdfjsLib.GlobalWorkerOptions.workerSrc = '<?php echo esc_url( get_template_directory_uri() . '/assets/js/pdfjs/pdf.worker.min.js' ); ?>';

(function() {
	var documents = 
	<?php
	echo wp_json_encode(
		array_values(
			array_filter(
				$documents,
				function ( $doc ) {
					return ! empty( $doc['file'] );
				}
			)
		)
	);
	?>
	;
	var container = document.getElementById('doc-viewer-container');
	var currentPdf = null;
	var currentType = null;

	function renderDoc(url) {
		if (currentPdf && currentType === 'pdf') {
			currentPdf.destroy();
		}
		currentPdf = null;
		currentType = null;
		container.innerHTML = '';

		var isPdf = url.match(/\.pdf($|\?)/i);
		var isImage = url.match(/\.(jpg|jpeg|png|gif|webp)($|\?)/i);

		if (isImage) {
			currentType = 'image';
			container.classList.add('viewer--image');
			container.classList.remove('viewer--pdf', 'viewer--other');
			var img = document.createElement('img');
			img.src = url;
			img.alt = 'Документ';
			img.style.cssText = 'display:block;';
			img.oncontextmenu = function() { return false; };
			img.onerror = function() {
				container.innerHTML = '<div class="documents-viewer__error">Не удалось загрузить изображение</div>';
			};
			container.appendChild(img);
			currentPdf = img;
		} else if (isPdf) {
			currentType = 'pdf';
			container.classList.add('viewer--pdf');
			container.classList.remove('viewer--image', 'viewer--other');
			container.innerHTML = '<div class="documents-viewer__loading">Загрузка...</div>';

			fetch(url).then(function(r) {
				if (!r.ok) throw new Error('HTTP ' + r.status);
				return r.arrayBuffer();
			}).then(function(data) {
				return pdfjsLib.getDocument({ data: data }).promise;
			}).then(function(pdf) {
				currentPdf = pdf;
				currentType = 'pdf';
				container.innerHTML = '';
				var pageNum = 1;

				function renderNextPage() {
					if (pageNum > pdf.numPages) return;
					pdf.getPage(pageNum).then(function(page) {
						var viewport = page.getViewport({ scale: 1.5 });
						var canvas = document.createElement('canvas');
						canvas.className = 'doc-canvas';
						canvas.height = viewport.height;
						canvas.width = viewport.width;
						canvas.oncontextmenu = function() { return false; };
						container.appendChild(canvas);

						page.render({
							canvasContext: canvas.getContext('2d'),
							viewport: viewport
						}).promise.then(function() {
							pageNum++;
							renderNextPage();
						});
					});
				}
				renderNextPage();
			}).catch(function(err) {
				console.error(err);
				container.innerHTML = '<div class="documents-viewer__error">Не удалось загрузить документ</div>';
			});
		} else {
			currentType = 'other';
			container.classList.add('viewer--other');
			container.classList.remove('viewer--image', 'viewer--pdf');
			var iframe = document.createElement('iframe');
			iframe.src = url;
			iframe.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;border:none;display:block;';
			iframe.oncontextmenu = function() { return false; };
			container.appendChild(iframe);
			currentPdf = iframe;
		}
	}

	var firstDoc = documents[0];
	if (firstDoc) {
		renderDoc(firstDoc.file);
	}

	document.querySelectorAll('.document-item').forEach(function(item) {
		item.addEventListener('click', function() {
			var index = this.getAttribute('data-doc-index');
			var doc = documents[index];
			if (!doc) return;
			document.querySelectorAll('.document-item').forEach(function(el) {
				el.classList.remove('active');
			});
			this.classList.add('active');
			renderDoc(doc.file);
		});
	});
})();
</script>

<style>
.doc-canvas {
	display: block;
	width: 100%;
	height: auto;
	margin: 0 auto 1rem;
	box-shadow: 0 2px 8px rgba(0,0,0,0.12);
	border-radius: 4px;
	flex-shrink: 0;
}
.documents-viewer__loading,
.documents-viewer__error,
.documents-viewer__placeholder {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	color: #6b7280;
	text-align: center;
	gap: 1rem;
	box-sizing: border-box;
	padding: 1rem;
}
.documents-viewer__placeholder svg {
	opacity: 0.4;
}

.documents-list::-webkit-scrollbar {
	width: 6px;
}
.documents-list::-webkit-scrollbar-track {
	background: transparent;
}
.documents-list::-webkit-scrollbar-thumb {
	background: #d1d5db;
	border-radius: 3px;
}
.documents-list::-webkit-scrollbar-thumb:hover {
	background: #01a0e2;
}
.documents-list {
	scrollbar-width: thin;
	scrollbar-color: #d1d5db transparent;
}

html.dark .documents-list::-webkit-scrollbar-thumb {
	background: #4b5563;
}
html.dark .documents-list::-webkit-scrollbar-thumb:hover {
	background: #01a0e2;
}
html.dark .documents-list {
	scrollbar-color: #4b5563 transparent;
}
</style>

<?php
get_footer();
