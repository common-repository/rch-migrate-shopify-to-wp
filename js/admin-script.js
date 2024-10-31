'use strict';
jQuery(document).ready(function ($) {
    $('.vi-ui.accordion').rc_accordion('refresh');
    /*import product options*/
    $('.rch-save-products-options').on('click', function (e) {
        let button = $(this);
        button.addClass('loading');
        let saving_overlay = $('.rch-import-products-options-saving-overlay');
        saving_overlay.removeClass('rch-hidden');
        _rch_nonce = $('#_rch_nonce').val();
        rch_domain = $('#rch-rch_domain').val();
        product_status = $('#rch-product_status').val();
        product_categories = $('#rch-product_categories').val();
        download_images = $('#rch-download_images').prop('checked') ? 1 : 0;
        products_per_request = $('#rch-products_per_request').val();
        product_import_sequence = $('#rch-product_import_sequence').val();
        $.ajax({
            url: rch_params_admin.url,
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'rch_save_settings_product_options',
                rch_domain: rch_domain,
                _rch_nonce: _rch_nonce,
                download_images: download_images,
                product_status: product_status,
                product_categories: product_categories ? product_categories : [],
                products_per_request: products_per_request,
                product_import_sequence: product_import_sequence,
            },
            success: function (response) {
                total_products = parseInt(response.total_products);
                total_pages = response.total_pages;
                current_import_id = response.current_import_id;
                current_import_product = parseInt(response.current_import_product);
                current_import_page = response.current_import_page;
                button.removeClass('loading');
                saving_overlay.addClass('rch-hidden');
                rch_product_options_close();
            },
            error: function (err) {
                button.removeClass('loading');
                saving_overlay.addClass('rch-hidden');
                rch_product_options_close();
            }
        })
    });
    $('.rch-import-products-options-close').on('click', function (e) {
        rch_product_options_close();
        rch_product_options_cancel();
    });
    $('.rch-import-products-options-overlay').on('click', function (e) {
        $('.rch-import-products-options-close').click();
    });
    $('.rch-import-products-options-shortcut').on('click', function (e) {
        if (!$('.rch-accordion').find('.content').eq(0).hasClass('active')) {
            e.preventDefault();
            rch_product_options_show();
            $('.rch-import-products-options-main').append($('.rch-import-products-options-content'));
        } else if (!$('#rch-import-products-options-anchor').hasClass('active')) {
            $('#rch-import-products-options-anchor').rc_accordion('open')
        }
    });

    function rch_product_options_cancel() {
        $('#rch-product_status').val(product_status);
        $('#rch-download_images').prop('checked', (download_images == 1));
        $('#rch-products_per_request').val(products_per_request);
        $('#rch-product_import_sequence').val(product_import_sequence);
        if (product_categories) {
            $('#rch-product_categories').val(product_categories).trigger('change');
        } else {
            $('#rch-product_categories').val(null).trigger('change');
        }
    }

    $('.search-category').select2({
        closeOnSelect: false,
        placeholder: 'Please fill in your category title',
        ajax: {
            url: 'admin-ajax.php?action=rch_search_cate',
            dataType: 'json',
            type: 'GET',
            quietMillis: 50,
            delay: 250,
            data: function (params) {
                return {
                    keyword: params.term
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        escapeMarkup: function (markup) {
            return markup;
        }, // let our custom formatter work
        minimumInputLength: 2
    });
    $('.vi-ui.checkbox').checkbox();
    $('.vi-ui.dropdown').dropdown();

    $('.rch-import-element-enable-bulk').on('change', function () {
        $('.rch-import-element-enable').prop('checked', $(this).prop('checked'));
    });
    $('#rch-rch_domain').on('change', function () {
        let rch_domain = $(this).val();
        rch_domain = rch_domain.replace(/https:\/\//g, '');
        rch_domain = rch_domain.replace(/\//g, '');

        $(this).val(rch_domain);
    });
    let selected_elements = [];
    let progress_bars = {};

    function get_selected_elements() {
        selected_elements = [];
        progress_bars = [];
        $('.rch-import-element-enable').map(function () {
            if ($(this).prop('checked')) {
                let element_name = $(this).data()['element_name'];
                selected_elements.push(element_name);
                progress_bars[element_name] = $('#rch-' + element_name.replace('_', '-') + '-progress');
            }
        });
        console.log(progress_bars);
    }

    function rch_import_element() {
        if (selected_elements.length) {
            let element = selected_elements.shift();
            progress_bars[element].progress('set label', 'Importing...');
            progress_bars[element].progress('set active');
            switch (element) {
                case 'products':
                    rch_import_products();
                    break;
                case 'product_categories':
                    rch_import_product_categories();
                    break;
            }
        } else {
            rch_unlock_buttons();
            import_active = false;
            $('.rch-sync').removeClass('loading');
            setTimeout(function () {
                alert('Import completed.');
            }, 400);
        }
    }

    let request_timeout = $('#rch-request_timeout').val(),
        products_per_request = $('#rch-products_per_request').val(),
        product_import_sequence = $('#rch-product_import_sequence').val();

    let total_products = parseInt(rch_params_admin.total_products),
        total_pages = rch_params_admin.total_pages,
        current_import_id = rch_params_admin.current_import_id,
        current_import_product = parseInt(rch_params_admin.current_import_product),
        current_import_page = rch_params_admin.current_import_page,
        product_percent_old = 0,

        imported_elements = rch_params_admin.imported_elements,
        elements_titles = rch_params_admin.elements_titles,
        _rch_nonce = $('#_rch_nonce').val(),
        rch_domain = $('#rch-rch_domain').val(),
        rch_api_key = $('#rch-rch_api_key').val(),
        rch_api_secret = $('#rch-rch_api_secret').val(),
        download_images = $('#rch-download_images').prop('checked') ? 1 : 0,
        product_status = $('#rch-product_status').val(),
        product_categories = $('#rch-product_categories').val();

    let save_active = false,
        import_complete = false,
        error_log = '',
        import_active = false;
    let warning;
    let warning_empty_store = rch_params_admin.warning_empty_store,
        warning_empty_rch_api_key = rch_params_admin.warning_empty_rch_api_key,
        warning_empty_rch_api_secret = rch_params_admin.warning_empty_rch_api_secret;

    function rch_validate_data() {
        warning = '';
        let validate = true;
        if (!$('#rch-rch_domain').val()) {
            validate = false;
            warning += warning_empty_store;
        }
        if (!$('#rch-rch_api_key').val()) {
            validate = false;
            warning += warning_empty_rch_api_key;
        }
        if (!$('#rch-rch_api_secret').val()) {
            validate = false;
            warning += warning_empty_rch_api_secret;
        }
        return validate;
    }

    $('.rch-delete-history').on('click', function () {
        if (!confirm('You are about to delete import history of selected elements. Continue?')) {
            return false;
        }
    })
    $('.rch-save').on('click', function () {
        if (!rch_validate_data()) {
            alert(warning);
            return;
        }
        if (import_active || save_active) {
            return;
        }
        save_active = true;
        product_status = $('#rch-product_status').val();
        product_categories = $('#rch-product_categories').val();
        _rch_nonce = $('#_rch_nonce').val();
        rch_domain = $('#rch-rch_domain').val();
        rch_api_key = $('#rch-rch_api_key').val();
        rch_api_secret = $('#rch-rch_api_secret').val();
        download_images = $('#rch-download_images').prop('checked') ? 1 : 0;
        request_timeout = $('#rch-request_timeout').val();
        products_per_request = $('#rch-products_per_request').val();
        product_import_sequence = $('#rch-product_import_sequence').val();
        let button = $(this);
        button.addClass('loading');
        $.ajax({
            url: rch_params_admin.url,
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'rch_save_settings',
                _rch_nonce: _rch_nonce,
                step: 'save',
                rch_domain: rch_domain,
                rch_api_key: rch_api_key,
                rch_api_secret: rch_api_secret,
                download_images: download_images,
                product_status: product_status,
                product_categories: product_categories ? product_categories : [],
                request_timeout: request_timeout,
                products_per_request: products_per_request,
                product_import_sequence: product_import_sequence,
            },
            success: function (response) {
                total_products = parseInt(response.total_products);
                total_pages = response.total_pages;
                current_import_id = response.current_import_id;
                current_import_product = parseInt(response.current_import_product);
                current_import_page = response.current_import_page;
                imported_elements = response.imported_elements;
                save_active = false;
                button.removeClass('loading');
                if (response.api_error) {
                    alert(response.api_error);
                    $('.rch-import-container').hide();
                    $('.rch-error-warning').show();
                } else if (response.validate) {
                    $('.rch-import-element-enable').map(function () {
                        let element = $(this).data()['element_name'];

                        if (imported_elements[element] == 1) {
                            $(this).prop('checked', false);
                            $('.rch-import-' + element.replace(/_/g, '-') + '-check-icon').addClass('green').removeClass('grey');
                        } else {
                            $(this).prop('checked', true);
                            $('.rch-import-' + element.replace(/_/g, '-') + '-check-icon').addClass('grey').removeClass('green');
                        }
                    });
                    $('.rch-import-container').show();
                    $('.rch-error-warning').hide();
                    $('.rch-accordion>.title').removeClass('active');
                    $('.rch-accordion>.content').removeClass('active');
                }
            },
            error: function (err) {
                save_active = false;
                button.removeClass('loading');
                console.log(err)
            }
        })
    });
    $('.rch-sync').on('click', function () {
        if (!rch_validate_data()) {
            alert(warning);
            return;
        }
        get_selected_elements();
        if (selected_elements.length == 0) {
            alert('Please select which data you want to import.');
            return;
        } else {
            let imported = [];
            for (let i in selected_elements) {
                let element = selected_elements[i];
                if (imported_elements[element] == 1) {
                    imported.push(elements_titles[element]);
                }
            }
            if (imported.length > 0) {
                if (!confirm('You already imported ' + imported.join(', ') + '. Do you want to continue?')) {
                    return;
                }
            }
        }
        let button = $(this);
        if (import_active || save_active) {
            return;
        }
        $('.rch-import-progress').css({'visibility': 'hidden'});
        for (let ele in progress_bars) {
            progress_bars[ele].css({'visibility': 'visible'});
            progress_bars[ele].progress('set label', 'Waiting...');
        }
        import_active = true;
        button.addClass('loading');
        rch_lock_buttons();
        rch_jump_to_import();
        rch_import_element();
    });

    function rch_import_products() {
        $.ajax({
            url: rch_params_admin.url,
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'rch_MIGRATE_SHOPIFY_TO_WORDPRESS',
                _rch_nonce: _rch_nonce,
                step: 'products',
                total_products: total_products,
                total_pages: total_pages,
                current_import_id: current_import_id,
                current_import_page: current_import_page,
                current_import_product: current_import_product,
                error_log: error_log,
            },
            success: function (response) {
                if (response.status === 'retry') {
                    total_products = parseInt(response.total_products);
                    total_pages = parseInt(response.total_pages);
                    current_import_id = response.current_import_id;
                    current_import_page = parseInt(response.current_import_page);
                    current_import_product = parseInt(response.current_import_product);
                    rch_import_products();
                }else{
                    error_log = '';
                    progress_bars['products'].progress('set label', response.message.toString());

                    if (response.status === 'error') {
                        rch_import_products();
                    } else {
                        current_import_id = response.current_import_id;
                        current_import_page = parseInt(response.current_import_page);
                        current_import_product = parseInt(response.current_import_product);
                        let imported_products = parseInt(response.imported_products);
                        let percent = Math.ceil(imported_products * 100 / total_products);
                        if (percent > 100) {
                            percent = 100;
                        }
                        progress_bars['products'].progress('set percent', percent);
                        let logs = response.logs;

                        if (logs) {
                            $('.rch-logs').append(response.logs).scrollTop($('.rch-logs')[0].scrollHeight);
                        }
                        if (response.status === 'successful') {
                            if (current_import_page <= total_pages) {
                                rch_import_products();
                            } else {
                                import_complete = true;

                                progress_bars['products'].progress('complete');
                                rch_import_element();
                            }
                        } else {
                            import_complete = true;

                            progress_bars['products'].progress('complete');
                            rch_import_element();
                        }
                    }
                }
            },
            error: function (err) {
                error_log = 'error ' + err.status + ' : ' + err.statusText;
                console.log(err);
                // progress_bars['products'].progress('set error');
                if (!import_complete) {
                    selected_elements.unshift('products');
                }
                setTimeout(function () {
                    rch_import_element();
                }, 3000)
            }
        })
    }

    let categories_current_page = 0;
    let total_categories = 0;

    function rch_import_product_categories() {
        $.ajax({
            url: rch_params_admin.url,
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'rch_MIGRATE_SHOPIFY_TO_WORDPRESS',
                _rch_nonce: _rch_nonce,
                step: 'product_categories',
                categories_current_page: categories_current_page,
                total_categories: total_categories,
            },
            success: function (response) {
                if (response.status === 'retry') {
                    categories_current_page = parseInt(response.categories_current_page);
                    total_categories = parseInt(response.total_categories);
                    rch_import_product_categories();
                } else if (response.status === 'success') {
                    categories_current_page = parseInt(response.categories_current_page);
                    total_categories = parseInt(response.total_categories);
                    let percent = categories_current_page * 100 / total_categories;
                    progress_bars['product_categories'].progress('set percent', percent);
                    rch_import_product_categories();
                } else if (response.status === 'error') {
                    progress_bars['product_categories'].progress('set label', response.message.toString());
                    progress_bars['product_categories'].progress('set error');
                    setTimeout(function () {
                        rch_import_product_categories();
                    }, 2000)
                } else {
                    categories_current_page = parseInt(response.categories_current_page);
                    total_categories = parseInt(response.total_categories);
                    progress_bars['product_categories'].progress('set label', response.message.toString());
                    progress_bars['product_categories'].progress('complete');
                    rch_import_element();
                }
            },
            error: function (err) {
                console.log(err);
                progress_bars['product_categories'].progress('set error');
                setTimeout(function () {
                    rch_import_element();
                }, 2000)
            },
        });

    }

    function rch_lock_buttons() {
        $('.rch-import-element-enable').prop('readonly', true);
    }

    function rch_unlock_buttons() {
        $('.rch-import-element-enable').prop('readonly', false);
    }

    function rch_jump_to_import() {
        $('html').prop('scrollTop', $('.rch-import-container').prop('offsetTop'))
    }
    function rch_product_options_close() {
        rch_product_options_hide();
        $('#rch-import-products-options').append($('.rch-import-products-options-content'));
    }

    function rch_product_options_hide() {
        $('.rch-import-products-options-modal').addClass('rch-hidden');
        rch_enable_scroll();
    }

    function rch_product_options_show() {
        $('.rch-import-products-options-modal').removeClass('rch-hidden');
        rch_disable_scroll();
    }
    function rch_enable_scroll() {
        let html = $('html');
        let scrollTop = parseInt(html.css('top'));
        html.removeClass('rch-noscroll');
        $('html,body').scrollTop(-scrollTop);
    }

    function rch_disable_scroll() {
        let html = $('html');
        if ($(document).height() > $(window).height()) {
            let scrollTop = (html.scrollTop()) ? html.scrollTop() : $('body').scrollTop(); // Works for Chrome, Firefox, IE...
            html.addClass('rch-noscroll').css('top', -scrollTop);
        }
    }
});
