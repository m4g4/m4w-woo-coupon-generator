(function($) {
    function change_coupon_shortcode(inputId, code, codeId, divider, prefix, suffix) {
        var input = document.getElementById(inputId);
        if (!input)
            return;

        input.value = `${prefix}${code}${divider}${codeId}${suffix}`;
    }

    // Post Title Detection
    var $titleInput = $('#title');
    if ($titleInput.length) {
        var previousTitle = $titleInput.val();

        $titleInput.on('input change', function() {
            var currentTitle = $titleInput.val();
            if (currentTitle !== previousTitle) {
                change_coupon_shortcode('m4w_wcg_mailpoet_shortcode', woo_copoun_generator.mailpoet_shortcode, currentTitle, '', '[', ']');
                change_coupon_shortcode('m4w_wcg_fluentcrm_shortcode', woo_copoun_generator.fluentcrm_smartcode, currentTitle, ':', '{{', '}}');
                previousTitle = currentTitle;
            }
        });

    } else {
        console.warn('Woo Coupon Generator: Post title input not found.');
    }

    // Coupon Panel Functionality
    document.addEventListener("DOMContentLoaded", function() {
        var enablement = document.getElementById(woo_copoun_generator.coupon_enabled_id);
        var prefix = document.getElementById(woo_copoun_generator.coupon_prefix_id);
        var fields = document.getElementById('m4w_wcg_panel_options');
        var removeBtn = document.getElementById('m4w_wcg_remove_child_coupons');
        var notice = document.getElementById('m4w_wcg_remove_notice');
        var postId = woo_copoun_generator.post_id;

        if (!enablement || !prefix || !fields) {
            console.warn('Coupon panel elements not found:', {
                enablement: woo_copoun_generator.coupon_enabled_id,
                prefix: woo_copoun_generator.coupon_prefix_id,
                fields: 'm4w_wcg_panel_options'
            });
            return;
        }

        function toggleOtherFields() {
            var show = enablement.checked;
            fields.style.display = show ? 'block' : 'none';
            if (removeBtn) {
                removeBtn.style.display = show ? 'inline-block' : 'none';
            }
        }

        toggleOtherFields();
        enablement.addEventListener('change', toggleOtherFields);

        function change_example() {
            document.getElementById("m4w_wcg_example").textContent = prefix.value + "123456";
        }
        prefix.addEventListener('input', change_example);
        change_example();

        function attach_copy_click_handler(copyButtonId, inputId) {
            var copyButton = document.getElementById(copyButtonId);
            if (!copyButton)
                return;

            copyButton.addEventListener("click", function() {
                var copyIcon = copyButton.querySelector('.dashicons');
                var copyText = document.getElementById(inputId);
    
                if (!copyIcon || !copyText) {
                    console.warn('Copy elements not found.');
                    return;
                }
            
                copyText.select();
                copyText.setSelectionRange(0, 99999);
            
                function showSuccess() {
                    copyIcon.classList.remove('dashicons-admin-page');
                    copyIcon.classList.add('dashicons-yes');
                    copyButton.classList.add('copied');
                    
                    var notice = document.createElement('span');
                    notice.className = 'copy-notice';
                    notice.textContent = 'Copied!';
                    copyButton.appendChild(notice);
                    
                    setTimeout(() => {
                        copyIcon.classList.remove('dashicons-yes');
                        copyIcon.classList.add('dashicons-admin-page');
                        copyButton.classList.remove('copied');
                        if (notice.parentNode) {
                            notice.parentNode.removeChild(notice);
                        }
                    }, 2000);
                }
            
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(copyText.value).then(function() {
                        showSuccess();
                    }).catch(function(err) {
                        document.execCommand("copy");
                        showSuccess();
                    });
                } else {
                    document.execCommand("copy");
                    showSuccess();
                }
            });
        }

        attach_copy_click_handler('m4w_wcg_mailpoet_copy', 'm4w_wcg_mailpoet_shortcode');
        attach_copy_click_handler('m4w_wcg_fluentcrm_copy', 'm4w_wcg_fluentcrm_shortcode');

        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                if (!confirm('Are you sure you want to remove ALL child coupons generated from this parent coupon? This action cannot be undone.')) {
                    return;
                }

                removeBtn.disabled = true;
                removeBtn.textContent = 'Removing...';
                if (notice) {
                    notice.style.display = 'inline';
                    notice.textContent = '';
                    notice.className = '';
                }

                var data = new FormData();
                data.append('action', 'ar_remove_child_coupons');
                data.append('post_id', postId);
                data.append('nonce', woo_copoun_generator.remove_nonce);

                fetch(ajaxurl, {
                    method: 'POST',
                    body: data
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(result) {
                    removeBtn.disabled = false;
                    removeBtn.textContent = 'Remove All Child Coupons';
                    if (notice) {
                        notice.style.display = 'inline';
                        if (result.success) {
                            var count = (result.data && typeof result.data.count !== 'undefined') ? parseInt(result.data.count, 10) : 0;
                            if (count > 0) {
                                notice.textContent = 'Removed ' + count + (count === 1 ? ' child coupon.' : ' child coupons.');
                                notice.className = 'notice-success';
                            } else {
                                notice.textContent = 'No child coupons found for this coupon.';
                                notice.className = 'notice-info';
                            }
                        } else {
                            notice.textContent = 'Error: ' + ((result.data && (result.data.message || result.data)) || 'Unknown error');
                            notice.className = 'notice-error';
                        }
                    }
                })
                .catch(function(err) {
                    removeBtn.disabled = false;
                    removeBtn.textContent = 'Remove All Child Coupons';
                    if (notice) {
                        notice.style.display = 'inline';
                        notice.textContent = 'Error: ' + err.message;
                        notice.className = 'notice-error';
                    }
                });
            });
        }
    });
})(jQuery);