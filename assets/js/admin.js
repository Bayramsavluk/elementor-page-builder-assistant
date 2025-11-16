/**
 * Admin JavaScript
 */
(function($) {
	'use strict';

	let templates = [];
let masterTemplateSelect = null;

// (moved to top-level)

	/**
	 * Generate slug from title - WordPress'in sanitize_title() davranışını taklit eder
	 * Türkçe karakterleri doğru şekilde çevirir
	 */
	function generateSlug(title) {
		if (!title) return '';
		
		// Türkçe karakterleri İngilizce karşılıklarına çevir
		const turkishChars = {
			'ı': 'i', 'İ': 'i', 'I': 'i',
			'ş': 's', 'Ş': 's',
			'ğ': 'g', 'Ğ': 'g',
			'ü': 'u', 'Ü': 'u',
			'ö': 'o', 'Ö': 'o',
			'ç': 'c', 'Ç': 'c'
		};
		
		let slug = title;
		
		// Türkçe karakterleri değiştir
		for (let char in turkishChars) {
			slug = slug.replace(new RegExp(char, 'g'), turkishChars[char]);
		}
		
		// WordPress'in sanitize_title() davranışı:
		// 1. Küçük harfe çevir
		slug = slug.toLowerCase();
		
		// 2. Özel karakterleri temizle - sadece alfanumerik, boşluk ve tire bırak
		slug = slug.replace(/[^\w\s-]/g, '');
		
		// 3. Boşlukları tireye çevir
		slug = slug.replace(/\s+/g, '-');
		
		// 4. Birden fazla tireyi tek tireye indir
		slug = slug.replace(/-+/g, '-');
		
		// 5. Başta ve sonda tire varsa kaldır
		slug = slug.replace(/^-+|-+$/g, '');
		
		return slug;
	}

	/**
	 * Initialize
	 */
	$(document).ready(function() {
		loadTemplates();
		initPagesPage();
		initPagesEditPage();
		initHeaderFooterPage();
		initModal();
		initTranslationToggle();
	});

	/**
	 * Load templates from server
	 */
	function loadTemplates() {
		$.ajax({
			url: ebiAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ebi_get_templates',
				nonce: ebiAdmin.nonce,
				template_type: 'all'
			},
			success: function(response) {
				if (response.success && response.data.templates) {
					templates = response.data.templates;
					// Build master template select for independent cloning per row
                    masterTemplateSelect = $('<select class="ebi-template-select ebi-field-template" required><option value="">Select template...</option></select>');
                    const list = Array.isArray(templates) ? templates.filter(function(t){
                        return t && t.source === 'elementor_library' && (
                            t.type === 'single-page' || t.type === 'section' || t.type === 'section-header' || t.type === 'section-footer' || t.type === 'section-other'
                        );
                    }) : [];
                    list.forEach(function(t){
                        masterTemplateSelect.append($('<option></option>').val(t.full_id).text(t.kit_name + ' - ' + t.name));
                    });
				}
			},
			error: function() {
				console.error('Failed to load templates');
			}
		});
	}

	/**
	 * Initialize pages page
	 */
	function initPagesPage() {
		if ($('#ebi-pages-list').length === 0 && $('.ebi-add-new-page').length === 0) {
			return;
		}

		// Open add page modal
		$(document).on('click', '.ebi-add-new-page', function(e) {
			e.preventDefault();
			openModal('#ebi-add-page-modal');
			resetMultiPageForm('#ebi-add-page-form', '#ebi-page-rows');
			addPageRow('#ebi-page-rows');
		});

		// Add new row button
		$(document).on('click', '.ebi-add-page-row', function(e) {
			e.preventDefault();
			addPageRow('#ebi-page-rows');
		});

		// Auto-generate slug when title changes
		$(document).on('input', '#ebi-page-title', function() {
			const title = $(this).val();
			if (title) {
				// Eğer slug alanı varsa ve boşsa otomatik doldur
				const $slugField = $('#ebi-page-slug');
				if ($slugField.length && !$slugField.val()) {
					$slugField.val(generateSlug(title));
				}
			}
		});

		// Submit add page form (batch)
		$(document).on('submit', '#ebi-add-page-form', function(e) {
			e.preventDefault();
			submitBatchAdd('#ebi-page-rows', '#ebi-page-messages');
		});
	}

	/**
	 * Initialize pages edit page (WordPress Pages list)
	 */
	function initPagesEditPage() {
		// Check if we're on the pages edit screen
		const isPagesEditPage = $('body.post-type-page.edit-php').length > 0 || $('#ebi-add-page-modal-edit').length > 0;
		
		if (!isPagesEditPage) {
			return;
		}

		// Override the default "Add New" button to open modal instead
		$(document).on('click', '.post-type-page .page-title-action', function(e) {
			if (!$(this).hasClass('ebi-add-new-page')) {
				e.preventDefault();
				e.stopPropagation();
				resetMultiPageForm('#ebi-add-page-form-edit', '#ebi-page-rows-edit');
				openModal('#ebi-add-page-modal-edit');
				addPageRow('#ebi-page-rows-edit');
				return false;
			}
		});

		// Handle template select change
		$(document).on('change', '#ebi-page-template-select-edit', function() {
			const template = $(this).val();
			// Template selected, form ready to submit
		});

		// Add new row (edit list screen)
		$(document).on('click', '.ebi-add-page-row-edit', function(e) {
			e.preventDefault();
			addPageRow('#ebi-page-rows-edit');
		});

		// Submit add page form (batch)
		$(document).on('submit', '#ebi-add-page-form-edit', function(e) {
			e.preventDefault();
			submitBatchAdd('#ebi-page-rows-edit', '#ebi-page-messages-edit');
		});
	}

	/**
	 * Populate page template select for edit page modal
	 */
// no-op (legacy replaced by per-row population)

	/**
	 * Submit add page form for edit page
	 */
// removed in favor of batch submission

	/**
	 * Reset page form for edit page
	 */
	function resetMultiPageForm(formSelector, rowsContainer) {
		const $form = $(formSelector);
		if ($form.length) {
			$form[0].reset();
		}
		$(rowsContainer).empty();
		const messages = formSelector.indexOf('edit') > -1 ? '#ebi-page-messages-edit' : '#ebi-page-messages';
		$(messages).empty();
	}

function addPageRow(containerSelector) {
    const $container = $(containerSelector);
    const index = $container.find('.ebi-row, .ebi-page-row').length;
    const rowId = 'ebi-row-' + index;

    const $row = $('<div class="ebi-row" id="' + rowId + '"></div>');
    const $head = $('<div class="ebi-row-head"></div>');
    $head.append('<span class="ebi-row-badge">Page</span>');
    const $headRight = $('<div class="ebi-head-right"></div>');
    // Translation controls in header
    const $switchWrap = $('<label class="ebi-switch" title="Enable translation?"></label>');
    const $switch = $('<input type="checkbox" class="ebi-field-tr-enable" />');
    const $track = $('<span class="ebi-switch-track"><span class="ebi-switch-thumb"></span></span>');
    $switchWrap.append($switch).append($track);
    const $trHeadWrap = $('<div class="ebi-tr-head-options" style="display:none;"></div>');
    const $trApiSmall = $('<select class="ebi-field-tr-api"><option value="">API</option></select>');
    const $trLangSmall = $('<select class="ebi-field-tr-lang">\
            <option value="en">English</option>\
            <option value="tr">Türkçe</option>\
            <option value="de">Deutsch</option>\
            <option value="fr">Français</option>\
            <option value="es">Español</option>\
            <option value="it">Italiano</option>\
            <option value="pt">Português</option>\
            <option value="ru">Русский</option>\
            <option value="ar">العربية</option>\
            <option value="zh">中文</option>\
            <option value="ja">日本語</option>\
        </select>');
    $trHeadWrap.append($trApiSmall).append($trLangSmall);
    populateApisIntoSelect($trApiSmall);
    $headRight.append('<span class="ebi-head-label">Translation</span>').append($switchWrap).append($trHeadWrap);
    const $actions = $('<div class="ebi-row-actions"></div>');
    $actions.append('<button type="button" class="button link-delete ebi-remove-row" title="Remove">&times;</button>');
    $head.append($headRight).append($actions);

    const $grid = $('<div class="ebi-grid"></div>');

    const $title = $('<div class="ebi-field"></div>')
        .append('<label>Page Title <span class="required">*</span></label>')
        .append('<input type="text" class="regular-text ebi-field-title" required />');

    const $tpl = $('<div class="ebi-field"></div>')
        .append('<label>Template (Template Kit) <span class="required">*</span></label>');
    let $tplSelect;
    if (masterTemplateSelect && masterTemplateSelect.length) {
        $tplSelect = masterTemplateSelect.clone();
    } else {
        $tplSelect = $('<select class="ebi-template-select ebi-field-template" required><option value="">Select template...</option></select>');
        populateTemplatesIntoSelect($tplSelect, 'page');
    }
    $tpl.append($tplSelect);

    const $parent = $('<div class="ebi-field"></div>')
        .append('<label>Parent Page</label>');
    let $parentSelect = $('#ebi-proto-parent').clone();
    if (!$parentSelect.length) { $parentSelect = $('#ebi-proto-parent-edit').clone(); }
    if ($parentSelect.length) {
        $parentSelect.removeAttr('id').removeAttr('name').addClass('ebi-field-parent');
    } else {
        $parentSelect = $('<select class="ebi-field-parent"><option value="0">None</option></select>');
    }
    $parent.append($parentSelect);

    const $ptype = $('<div class="ebi-field"></div>')
        .append('<label>Page Template</label>');
    let $ptypeSelect = $('#ebi-proto-template-type').clone();
    if (!$ptypeSelect.length) { $ptypeSelect = $('#ebi-proto-template-type-edit').clone(); }
    if ($ptypeSelect.length) {
        $ptypeSelect.removeAttr('id').removeAttr('name').addClass('ebi-field-template-type');
    } else {
        $ptypeSelect = $('<select class="ebi-field-template-type">\
                <option value="default">Default Template</option>\
                <option value="elementor_canvas">Elementor Canvas</option>\
                <option value="elementor_header_footer">Elementor Full Width</option>\
            </select>');
    }
    $ptype.append($ptypeSelect);

    $grid.append($title, $tpl, $parent, $ptype);
    $row.append($head).append($grid);
    $container.append($row);
}

	$(document).on('change', '.ebi-field-tr-enable', function() {
    const $wrapOld = $(this).closest('.ebi-page-row').find('.ebi-tr-options, .ebi-tr-head-options');
    const $wrapNew = $(this).closest('.ebi-row').find('.ebi-tr-head-options, .ebi-tr-options');
    const $wrap = $wrapNew.length ? $wrapNew : $wrapOld;
    if ($(this).is(':checked')) {
        $wrap.addClass('is-open').css('display','flex');
    } else {
        $wrap.removeClass('is-open').hide();
    }
	});

	$(document).on('click', '.ebi-remove-row', function() {
    const $r = $(this).closest('.ebi-row');
    if ($r.length) { $r.remove(); return; }
    $(this).closest('.ebi-page-row').remove();
	});

	function populateTemplatesIntoSelect($select, type) {
		$select.find('option:not(:first)').remove();
		let list;
    if (type === 'page') {
            list = Array.isArray(templates) ? templates.filter(function(t){
                return t && t.source === 'elementor_library' && (
                    t.type === 'single-page' || t.type === 'section' || t.type === 'section-header' || t.type === 'section-footer' || t.type === 'section-other'
                );
            }) : [];
		} else {
			list = filterTemplates(type);
		}
		if (!list.length) {
			$select.append($('<option></option>').val('').text('No page template found'));
			return;
		}
		list.forEach(function(t) {
			$select.append($('<option></option>').val(t.full_id).text(t.kit_name + ' - ' + t.name));
		});
	}

	function populateApisIntoSelect($select) {
		// Try to get enabled APIs from modal data attribute
		const $modal = $('#ebi-add-page-modal');
		let enabledApis = {};
		
		if ($modal.length && $modal.attr('data-enabled-apis')) {
			try {
				enabledApis = JSON.parse($modal.attr('data-enabled-apis'));
			} catch(e) {
				console.error('Failed to parse enabled APIs:', e);
			}
		}
		
		$select.find('option:not(:first)').remove();
		
		// If we have enabled APIs from PHP, use them
		if (Object.keys(enabledApis).length > 0) {
			for (const apiId in enabledApis) {
				$select.append($('<option></option>').val(apiId).text(enabledApis[apiId]));
			}
		} else {
			// Fallback to showing common APIs if no data available
			const apis = [
				{ id: 'libretranslate', name: 'LibreTranslate' },
				{ id: 'mymemory', name: 'MyMemory Translation' },
				{ id: 'deepl', name: 'DeepL API' },
				{ id: 'microsoft', name: 'Microsoft Translator' },
				{ id: 'yandex', name: 'Yandex Translate' },
				{ id: 'argostranslate', name: 'Argos Translate' }
			];
			apis.forEach(a => $select.append($('<option></option>').val(a.id).text(a.name)));
		}
	}

	function submitBatchAdd(containerSelector, messagesSelector) {
	const $container = $(containerSelector);
    const $rows = $container.find('.ebi-row, .ebi-page-row');
	if ($rows.length === 0) {
		showMessage(messagesSelector, 'Add at least one row.', 'error');
		return;
	}
		const pages = [];
		let hasError = false;
		$rows.each(function() {
			const $r = $(this);
			const title = $r.find('.ebi-field-title').val();
			const template = $r.find('.ebi-field-template').val();
			if (!title || !template) { hasError = true; return; }
			const parent = $r.find('.ebi-field-parent').val() || '0';
			const templateType = $r.find('.ebi-field-template-type').val() || 'default';
        const trEnabled = $r.find('.ebi-field-tr-enable').is(':checked');
        // read from header options first
        const trApi = ($r.find('.ebi-row-head .ebi-field-tr-api').val() || $r.find('.ebi-field-tr-api').val()) || '';
        const trLang = ($r.find('.ebi-row-head .ebi-field-tr-lang').val() || $r.find('.ebi-field-tr-lang').val()) || 'en';
			const item = {
				title: title,
				template: template,
				parent: parent,
				template_type: templateType
			};
			if (trEnabled) {
				item.translation = { enabled: true, api_id: trApi, target_lang: trLang };
			}
			pages.push(item);
		});
		if (hasError) {
			showMessage(messagesSelector, 'Please fill in all required fields.', 'error');
			return;
		}

	const $submitBtn = $(messagesSelector).closest('.ebi-modal-body').find('button[type="submit"]');
	$submitBtn.prop('disabled', true);
	
	// Check if any page has translation enabled
	const hasTranslation = pages.some(p => p.translation && p.translation.enabled);
	if (hasTranslation) {
		$submitBtn.text('Translating...');
		showMessage(messagesSelector, 'Translation in progress, please wait...', 'info');
	} else {
		$submitBtn.text('Importing...');
		showMessage(messagesSelector, 'Import started, please wait...', 'info');
	}

	$.ajax({
		url: ebiAdmin.ajaxUrl,
		type: 'POST',
		data: { action: 'ebi_import_pages', nonce: ebiAdmin.nonce, pages: pages },
		success: function(response) {
			if (response.success) {
				const msg = hasTranslation ? 'All pages processed and translated successfully!' : 'All pages imported successfully!';
				showMessage(messagesSelector, msg, 'success');
				setTimeout(function(){ closeModal(); location.reload(); }, 1500);
			} else {
				showMessage(messagesSelector, (response.data && response.data.message) || ebiAdmin.strings.error, 'error');
				$submitBtn.prop('disabled', false).text('Add All');
			}
		},
		error: function() {
			showMessage(messagesSelector, ebiAdmin.strings.error, 'error');
			$submitBtn.prop('disabled', false).text('Add All');
		}
	});
	}

	/**
	 * Initialize header/footer page
	 */
	function initHeaderFooterPage() {
		// Check if we're on the edit-elementor-hf page
		const isHFPage = $('body.post-type-elementor-hf').length > 0 || $('#ebi-add-hf-modal').length > 0;
		
		if (!isHFPage) {
			return;
		}

		// Override the default "Add New" button to open modal instead
		$(document).on('click', '.post-type-elementor-hf .page-title-action', function(e) {
			// Check if it's the default Add New button (not already our custom button)
			if (!$(this).hasClass('ebi-add-new-hf')) {
				e.preventDefault();
				e.stopPropagation();
				openModal('#ebi-add-hf-modal');
				resetHFForm();
				return false;
			}
		});

		// Also handle our custom button if it exists
		$(document).on('click', '.ebi-add-new-hf', function(e) {
			e.preventDefault();
			openModal('#ebi-add-hf-modal');
			resetHFForm();
		});

		// Handle type change
		$(document).on('change', '#ebi-hf-type', function() {
			const type = $(this).val();
			if (type) {
				$('.ebi-template-select-wrapper').slideDown();
				populateHFTemplateSelect(type);
			} else {
				$('.ebi-template-select-wrapper').slideUp();
			}
		});

		// Submit add HF form
		$(document).on('submit', '#ebi-add-hf-form', function(e) {
			e.preventDefault();
			submitAddHFForm();
		});
	}

	/**
	 * Initialize translation toggle for all modals
	 */
	function initTranslationToggle() {
		// Toggle translation options when checkbox is clicked
		$(document).on('change', '#ebi-page-enable-translation, #ebi-hf-enable-translation', function() {
			const $options = $(this).closest('.ebi-form-group').find('[id$="-translation-options"]');
			if ($(this).is(':checked')) {
				$options.slideDown(200);
			} else {
				$options.slideUp(200);
			}
		});
	}

	/**
	 * Initialize modal
	 */
	function initModal() {
		// Close modal on close button click
		$(document).on('click', '.ebi-modal-close, .ebi-modal-cancel', function() {
			closeModal();
		});

		// Close modal on outside click
		$(document).on('click', '.ebi-modal', function(e) {
			if ($(e.target).hasClass('ebi-modal')) {
				closeModal();
			}
		});
	}

	/**
	 * Open modal
	 */
	function openModal(modalId) {
		$(modalId).fadeIn(200);
		$('body').addClass('ebi-modal-open');
	}

	/**
	 * Close modal
	 */
	function closeModal() {
		$('.ebi-modal').fadeOut(200);
		$('body').removeClass('ebi-modal-open');
	}

	/**
	 * Populate page template select
	 */
	function populatePageTemplateSelect() {
		const $select = $('#ebi-page-template-select');
		$select.find('option:not(:first)').remove();

		const pageTemplates = filterTemplates('page');
		pageTemplates.forEach(function(template) {
			const option = $('<option></option>')
				.val(template.full_id)
				.text(template.kit_name + ' - ' + template.name);
			$select.append(option);
		});
	}

	/**
	 * Populate HF template select
	 */
	function populateHFTemplateSelect(type) {
		const $select = $('#ebi-hf-template');
		$select.find('option:not(:first)').remove();

		// Map our type values to template type values
		const typeMap = {
			'header': 'section-header',
			'footer': 'section-footer',
			'before_footer': 'section-footer', // Before footer uses footer templates
			'404': 'single-404'
		};

		const templateType = typeMap[type] || type;
		const filteredTemplates = filterTemplates(templateType === 'section-footer' && type === 'before_footer' ? 'footer' : type);

			if (filteredTemplates.length === 0) {
				const option = $('<option></option>')
					.val('')
					.text('No template found for this type');
				$select.append(option);
			} else {
		filteredTemplates.forEach(function(template) {
			const option = $('<option></option>')
				.val(template.full_id)
				.text(template.kit_name + ' - ' + template.name);
			$select.append(option);
		});
		}
	}

	/**
	 * Filter templates by type
	 */
	function filterTemplates(filterType) {
		if (filterType === 'all' || !filterType) {
			return templates;
		}

// Only Elementor Library templates; include pages and sections
function getLibraryPageAndSections() {
    if (!Array.isArray(templates)) return [];
    return templates.filter(function(t){
        if (!t) return false;
        if (t.source !== 'elementor_library') return false;
        return (
            t.type === 'single-page' ||
            t.type === 'section' || // safety
            t.type === 'section-header' ||
            t.type === 'section-footer'
        );
    });
		}

		const typeMap = {
			'page': 'single-page',
			'header': 'section-header',
			'footer': 'section-footer',
			'before_footer': 'section-footer', // Before footer also uses footer templates
			'404': 'single-404'
		};

		const targetType = typeMap[filterType] || filterType;
		return templates.filter(function(template) {
			return template.type === targetType;
		});
	}

	/**
	 * Submit add page form
	 */
	function submitAddPageForm() {
		const $form = $('#ebi-add-page-form');
		const title = $('#ebi-page-title').val();
		const template = $('#ebi-page-template-select').val();
		
		// Auto-generate slug from title using WordPress-like slug generation
		// WordPress'in sanitize_title() fonksiyonunun JavaScript versiyonu
		let slug = $('#ebi-page-slug').val();
		if (!slug && title) {
			slug = generateSlug(title);
		}

		if (!title || !template) {
			showMessage('#ebi-page-messages', 'Please fill in all fields.', 'error');
			return;
		}

		const $submitBtn = $form.find('button[type="submit"]');
		$submitBtn.prop('disabled', true);

		const enableTranslation = $('#ebi-page-enable-translation').is(':checked');
		const translationData = {};
		
		if (enableTranslation) {
			translationData.enable_translation = true;
			translationData.translation_api = $('#ebi-page-translation-api').val() || '';
			translationData.target_lang = $('#ebi-page-translation-target-lang').val() || 'en';
			showMessage('#ebi-page-messages', 'Çeviri yapılıyor, lütfen bekleyin...', 'info');
			$submitBtn.text('Çeviri yapılıyor...');
		} else {
			showMessage('#ebi-page-messages', 'Şablon aktarılıyor, lütfen bekleyin...', 'info');
			$submitBtn.text('Aktarılıyor...');
		}

		$.ajax({
			url: ebiAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ebi_import_single_page',
				nonce: ebiAdmin.nonce,
				page_title: title,
				page_template: template,
				page_slug: slug || '',
				page_parent: $('#ebi-page-parent').val() || '0',
				page_template_type: $('#ebi-page-template-type').val() || 'default',
				page_excerpt: $('#ebi-page-excerpt').val() || '',
				page_comments: $('#ebi-page-comments').is(':checked') ? '1' : '',
				page_author: $('#ebi-page-author').val() || '',
				page_status: $('#ebi-page-status').val() || 'publish',
				translation: translationData
			},
			success: function(response) {
				if (response.success) {
					const successMsg = enableTranslation 
						? 'Çeviri tamamlandı ve sayfa başarıyla oluşturuldu! <a href="' + response.data.result.edit_url + '" target="_blank">Düzenle</a>'
						: 'Sayfa başarıyla oluşturuldu! <a href="' + response.data.result.edit_url + '" target="_blank">Düzenle</a>';
					showMessage('#ebi-page-messages', successMsg, 'success');
					setTimeout(function() {
						closeModal();
						location.reload();
					}, 2000);
				} else {
					showMessage('#ebi-page-messages', response.data.message || ebiAdmin.strings.error, 'error');
					$submitBtn.prop('disabled', false).text('Ekle');
				}
			},
			error: function() {
				showMessage('#ebi-page-messages', ebiAdmin.strings.error, 'error');
				$submitBtn.prop('disabled', false).text('Ekle');
			}
		});
	}

	/**
	 * Submit add HF form
	 */
	function submitAddHFForm() {
		const $form = $('#ebi-add-hf-form');
		const title = $('#ebi-hf-title').val();
		const type = $('#ebi-hf-type').val();
		const template = $('#ebi-hf-template').val();

		if (!title || !type || !template) {
			showMessage('#ebi-hf-messages', 'Please fill in all fields.', 'error');
			return;
		}

	const $submitBtn = $form.find('button[type="submit"]');
	const originalText = $submitBtn.text();
	$submitBtn.prop('disabled', true);

	const enableTranslation = $('#ebi-hf-enable-translation').is(':checked');
	const translationData = {};
	
	if (enableTranslation) {
		translationData.enable_translation = true;
		translationData.translation_api = $('#ebi-hf-translation-api').val() || '';
		translationData.target_lang = $('#ebi-hf-translation-target-lang').val() || 'en';
		showMessage('#ebi-hf-messages', 'Translation in progress, please wait...', 'info');
		$submitBtn.text('Translating...');
	} else {
		showMessage('#ebi-hf-messages', 'Template importing, please wait...', 'info');
		$submitBtn.text('Importing...');
	}

		// Get target rules if header-footer-elementor exists
		let targetLocations = {
			rule: ['basic-global']
		};
		let targetExclusion = {};
		let targetUsers = [];

		// Try to get target rules from form if they exist
		// For now, we'll use default values since we're not showing target rules in modal
		const formData = {
			action: 'ebi_import_header_footer',
			nonce: ebiAdmin.nonce,
			hf_title: title,
			hf_type: type,
			hf_template: template,
			hf_enable_canvas: $('#ebi-hf-enable-canvas').is(':checked') ? '1' : '',
			translation: translationData
		};

		// Add target rules data
		if ($('.ast-target_rule-input').length > 0) {
			// Target rules are present, collect them
			const locationInput = $('[name="bsf-target-rules-location"]').val();
			const exclusionInput = $('[name="bsf-target-rules-exclusion"]').val();
			
			if (locationInput) {
				try {
					targetLocations = JSON.parse(locationInput);
				} catch(e) {
					// Use default
				}
			}
			
			if (exclusionInput) {
				try {
					targetExclusion = JSON.parse(exclusionInput);
				} catch(e) {
					// Use default
				}
			}

			if ($('[name="bsf-target-rules-users[]"]').length > 0) {
				targetUsers = $('[name="bsf-target-rules-users[]"]:checked').map(function() {
					return $(this).val();
				}).get();
			}

			formData['bsf-target-rules-location'] = JSON.stringify(targetLocations);
			formData['bsf-target-rules-exclusion'] = JSON.stringify(targetExclusion);
			if (targetUsers.length > 0) {
				targetUsers.forEach(function(user, index) {
				formData['bsf-target-rules-users[' + index + ']'] = user;
				});
			}
		}

		$.ajax({
			url: ebiAdmin.ajaxUrl,
			type: 'POST',
			data: formData,
			success: function(response) {
				if (response.success) {
					const successMsg = enableTranslation 
						? 'Translation completed and Header/Footer created successfully! <a href="' + response.data.result.edit_url + '" target="_blank">Edit</a>'
						: 'Header/Footer created successfully! <a href="' + response.data.result.edit_url + '" target="_blank">Edit</a>';
					showMessage('#ebi-hf-messages', successMsg, 'success');
					setTimeout(function() {
						closeModal();
						location.reload();
					}, 2000);
				} else {
					showMessage('#ebi-hf-messages', response.data.message || ebiAdmin.strings.error, 'error');
					$submitBtn.prop('disabled', false).text(originalText);
				}
			},
			error: function() {
				showMessage('#ebi-hf-messages', ebiAdmin.strings.error, 'error');
				$submitBtn.prop('disabled', false).text(originalText);
			}
		});
	}

	/**
	 * Reset HF form
	 */
	function resetHFForm() {
		$('#ebi-add-hf-form')[0].reset();
		$('.ebi-template-select-wrapper').hide();
		$('#ebi-hf-template').find('option:not(:first)').remove();
		$('#ebi-hf-messages').empty();
	}

	/**
	 * Initialize Target Rules JavaScript
	 * header-footer-elementor JavaScript'leri otomatik çalışacak,
	 * sadece modal içindeki alanlar için ekstra kontrol yapıyoruz
	 */
	function initializeTargetRules() {
		// header-footer-elementor JavaScript'leri zaten document.ready içinde çalışıyor
		// Modal içindeki yeni eklenen alanlar için bir trigger gönderelim
		jQuery(document).trigger('ebi-target-rules-init');
	}

	/**
	 * Show message
	 */
	function showMessage(container, message, type) {
		const $container = $(container);
		const $message = $('<div></div>')
			.addClass('ebi-message')
			.addClass(type)
			.html(message);

		$container.empty().append($message);

		setTimeout(function() {
			$message.fadeOut(function() {
				$(this).remove();
			});
		}, 5000);
	}

})(jQuery);
