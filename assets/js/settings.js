jQuery(document).ready(function($) {
    'use strict';
    
    // Tab switching
    $('.ae-nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var tabUrl = $(this).attr('href');
        var tabKey = $(this).data('tab');
        
        // Update active tab
        $('.ae-nav-tab').removeClass('active');
        $(this).addClass('active');
        
        // Update form tab input
        $('input[name="tab"]').val(tabKey);
        
        // Load tab content via AJAX or redirect
        if (history.pushState) {
            history.pushState(null, null, tabUrl);
        } else {
            window.location.href = tabUrl;
            return;
        }
        
        // For now, redirect to load new content
        // In a more advanced version, you could load content via AJAX
        window.location.href = tabUrl;
    });
    
    // Form submission with AJAX (optional enhancement)
    $('#ae-settings-form').on('submit', function(e) {
        var $form = $(this);
        var $saveButton = $('.ae-save-button');
        var $saveText = $('.ae-save-text');
        var $saveSpinner = $('.ae-save-spinner');
        
        // Show saving state
        $saveText.hide();
        $saveSpinner.show();
        $saveButton.prop('disabled', true);
        
        // Let form submit normally for now
        // You could implement AJAX submission here if desired
        
        // Reset button state after a short delay if still on page
        setTimeout(function() {
            $saveSpinner.hide();
            $saveText.show();
            $saveButton.prop('disabled', false);
        }, 3000);
    });
    
    // reCAPTCHA toggle functionality
    $('input[name="ae_settings[enable_recaptcha]"]').on('change', function() {
        var $container = $('.ae-settings-section').has('.ae-recaptcha-field');
        if ($(this).is(':checked')) {
            $container.addClass('ae-recaptcha-enabled');
        } else {
            $container.removeClass('ae-recaptcha-enabled');
        }
    }).trigger('change');
    
    // Auto-save functionality (optional)
    var autoSaveTimeout;
    $('.ae-setting-field input, .ae-setting-field textarea, .ae-setting-field select').on('input change', function() {
        clearTimeout(autoSaveTimeout);
        
        // Show unsaved changes indicator
        $('.ae-save-text').text('Save Changes');
        
        // Auto-save after 2 seconds of inactivity
        autoSaveTimeout = setTimeout(function() {
            // You could implement auto-save here
            console.log('Auto-save would trigger here');
        }, 2000);
    });
    
    // Settings validation
    $('#ae-settings-form').on('submit', function(e) {
        var isValid = true;
        var errors = [];
        
        // Validate email fields
        $('input[type="email"]').each(function() {
            var email = $(this).val();
            if (email && !isValidEmail(email)) {
                isValid = false;
                errors.push('Please enter a valid email address for ' + $(this).closest('.ae-setting-row').find('.ae-setting-label').text());
                $(this).addClass('error');
            } else {
                $(this).removeClass('error');
            }
        });
        
        // Validate required fields
        $('input[required], textarea[required], select[required]').each(function() {
            if (!$(this).val().trim()) {
                isValid = false;
                errors.push($(this).closest('.ae-setting-row').find('.ae-setting-label').text() + ' is required');
                $(this).addClass('error');
            } else {
                $(this).removeClass('error');
            }
        });
        
        // Validate reCAPTCHA settings
        if ($('input[name="ae_settings[enable_recaptcha]"]').is(':checked')) {
            var siteKey = $('input[name="ae_settings[recaptcha_site_key]"]').val();
            var secretKey = $('input[name="ae_settings[recaptcha_secret_key]"]').val();
            
            if (!siteKey.trim()) {
                isValid = false;
                errors.push('reCAPTCHA Site Key is required when reCAPTCHA is enabled');
                $('input[name="ae_settings[recaptcha_site_key]"]').addClass('error');
            }
            
            if (!secretKey.trim()) {
                isValid = false;
                errors.push('reCAPTCHA Secret Key is required when reCAPTCHA is enabled');
                $('input[name="ae_settings[recaptcha_secret_key]"]').addClass('error');
            }
        }
        
        if (!isValid) {
            e.preventDefault();
            alert('Please fix the following errors:\n\n' + errors.join('\n'));
            return false;
        }
    });
    
    // Helper function to validate email
    function isValidEmail(email) {
        var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailPattern.test(email);
    }
    
    // Add error styling
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .ae-setting-field input.error,
            .ae-setting-field textarea.error,
            .ae-setting-field select.error {
                border-color: #d63638;
                box-shadow: 0 0 0 1px #d63638;
            }
            
            .ae-setting-field input.error:focus,
            .ae-setting-field textarea.error:focus,
            .ae-setting-field select.error:focus {
                border-color: #d63638;
                box-shadow: 0 0 0 1px #d63638;
            }
        `)
        .appendTo('head');
    
    // Template placeholders help
    $('.ae-setting-field').has('textarea[name*="email_templates"]').each(function() {
        var $field = $(this);
        var $textarea = $field.find('textarea');
        var $description = $field.find('.description');
        
        if ($description.length) {
            $description.find('code').on('click', function() {
                var placeholder = $(this).text();
                var textarea = $textarea[0];
                var start = textarea.selectionStart;
                var end = textarea.selectionEnd;
                var text = $textarea.val();
                
                // Insert placeholder at cursor position
                var newText = text.substring(0, start) + placeholder + text.substring(end);
                $textarea.val(newText);
                
                // Move cursor after inserted text
                textarea.setSelectionRange(start + placeholder.length, start + placeholder.length);
                $textarea.focus();
            });
            
            // Make placeholders clickable
            $description.find('code').css({
                'cursor': 'pointer',
                'padding': '2px 4px',
                'background': '#f1f1f1',
                'border-radius': '2px',
                'border': '1px solid #ddd'
            }).attr('title', 'Click to insert');
        }
    });
    
    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl/Cmd + S to save
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            $('#ae-settings-form').submit();
        }
        
        // Ctrl/Cmd + 1-4 to switch tabs
        if ((e.ctrlKey || e.metaKey) && e.key >= '1' && e.key <= '4') {
            e.preventDefault();
            var tabIndex = parseInt(e.key) - 1;
            $('.ae-nav-tab').eq(tabIndex).click();
        }
    });
    
    // Success message fade out
    $('.notice-success').delay(3000).fadeOut();
    
    // Initialize tooltips if needed
    if (typeof $.fn.tooltip !== 'undefined') {
        $('[title]').tooltip();
    }
    
    // Focus first input on page load
    $('.ae-setting-field input:first').focus();
    
    console.log('Arbe Events Settings initialized');
});