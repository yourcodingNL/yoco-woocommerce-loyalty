/**
 * YoCo WooCommerce Loyalty - Admin JavaScript
 */

(function($) {
    'use strict';
    
    // Document ready
    $(document).ready(function() {
        YoCoLoyaltyAdmin.init();
    });
    
    /**
     * Admin JavaScript Object
     */
    var YoCoLoyaltyAdmin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.checkForUpdates();
            this.initTooltips();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Settings form handler
            $('form[action="options.php"]').on('submit', this.handleSettingsSubmit);
            
            // Update check button
            $(document).on('click', '.yoco-loyalty-check-update', this.checkForUpdatesManual);
            
            // Clear cache button
            $(document).on('click', '.yoco-loyalty-clear-cache', this.clearUpdateCache);
            
            // Tab switching
            $(document).on('click', '.yoco-loyalty-tab', this.switchTab);
        },
        
        /**
         * Handle settings form submission
         */
        handleSettingsSubmit: function(e) {
            var $form = $(this);
            var $submitButton = $form.find('input[type="submit"]');
            
            // Show loading state
            $submitButton.prop('disabled', true).val('Opslaan...');
            
            // Form will submit normally, but we show loading state
            setTimeout(function() {
                $submitButton.prop('disabled', false).val('Wijzigingen opslaan');
            }, 2000);
        },
        
        /**
         * Check for updates automatically
         */
        checkForUpdates: function() {
            // Only check on plugin pages
            if (!$('.yoco-loyalty-admin-content').length) {
                return;
            }
            
            // Check every 30 minutes
            setInterval(this.performUpdateCheck, 30 * 60 * 1000);
        },
        
        /**
         * Manual update check
         */
        checkForUpdatesManual: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            $button.text(yocoLoyaltyAjax.strings.checking).prop('disabled', true);
            
            YoCoLoyaltyAdmin.performUpdateCheck(function(hasUpdate) {
                $button.text(originalText).prop('disabled', false);
                
                if (hasUpdate) {
                    YoCoLoyaltyAdmin.showMessage(yocoLoyaltyAjax.strings.update_available, 'warning');
                } else {
                    YoCoLoyaltyAdmin.showMessage(yocoLoyaltyAjax.strings.up_to_date, 'success');
                }
            });
        },
        
        /**
         * Perform update check
         */
        performUpdateCheck: function(callback) {
            $.ajax({
                url: yocoLoyaltyAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'yoco_loyalty_check_update',
                    nonce: yocoLoyaltyAjax.nonce
                },
                success: function(response) {
                    if (response.success && response.data.has_update) {
                        YoCoLoyaltyAdmin.showUpdateNotice(response.data);
                        if (callback) callback(true);
                    } else {
                        if (callback) callback(false);
                    }
                },
                error: function() {
                    console.log('Update check failed');
                    if (callback) callback(false);
                }
            });
        },
        
        /**
         * Show update notice
         */
        showUpdateNotice: function(updateData) {
            var notice = $('<div class="yoco-loyalty-update-notice">' +
                '<p><strong>Nieuwe versie beschikbaar:</strong> ' + updateData.new_version + 
                ' (huidige versie: ' + updateData.current_version + ')</p>' +
                '<p><a href="' + updateData.update_url + '" class="yoco-loyalty-button">Nu bijwerken</a></p>' +
                '</div>');
            
            $('.yoco-loyalty-admin-content').prepend(notice);
        },
        
        /**
         * Clear update cache
         */
        clearUpdateCache: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            $button.text(yocoLoyaltyAjax.strings.clearing).prop('disabled', true);
            
            $.ajax({
                url: yocoLoyaltyAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'yoco_loyalty_clear_cache',
                    nonce: yocoLoyaltyAjax.nonce
                },
                success: function(response) {
                    $button.text(originalText).prop('disabled', false);
                    
                    if (response.success) {
                        YoCoLoyaltyAdmin.showMessage(yocoLoyaltyAjax.strings.cache_cleared, 'success');
                    } else {
                        YoCoLoyaltyAdmin.showMessage(yocoLoyaltyAjax.strings.error_occurred, 'error');
                    }
                },
                error: function() {
                    $button.text(originalText).prop('disabled', false);
                    YoCoLoyaltyAdmin.showMessage(yocoLoyaltyAjax.strings.error_occurred, 'error');
                }
            });
        },
        
        /**
         * Switch tabs
         */
        switchTab: function(e) {
            e.preventDefault();
            
            var $tab = $(this);
            var target = $tab.data('target');
            
            // Update tab states
            $('.yoco-loyalty-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Show/hide content
            $('.yoco-loyalty-tab-content').hide();
            $(target).show();
        },
        
        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            if ($.fn.tooltip) {
                $('[data-tooltip]').tooltip({
                    content: function() {
                        return $(this).data('tooltip');
                    }
                });
            }
        },
        
        /**
         * Show message to user
         */
        showMessage: function(message, type) {
            type = type || 'info';
            
            var $message = $('<div class="yoco-loyalty-message ' + type + '">' + message + '</div>');
            
            $('.yoco-loyalty-admin-content').prepend($message);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        /**
         * Utility: Format file size
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },
        
        /**
         * Utility: Validate email
         */
        validateEmail: function(email) {
            var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },
        
        /**
         * Utility: Show loading state
         */
        showLoading: function($element) {
            $element.addClass('yoco-loyalty-loading');
        },
        
        /**
         * Utility: Hide loading state
         */
        hideLoading: function($element) {
            $element.removeClass('yoco-loyalty-loading');
        }
    };
    
    // Make YoCoLoyaltyAdmin globally accessible
    window.YoCoLoyaltyAdmin = YoCoLoyaltyAdmin;
    
})(jQuery);

/**
 * Vanilla JavaScript fallback for basic functionality
 * (in case jQuery is not available)
 */
if (typeof jQuery === 'undefined') {
    document.addEventListener('DOMContentLoaded', function() {
        
        // Basic form validation
        var forms = document.querySelectorAll('form[action="options.php"]');
        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                var submitButton = form.querySelector('input[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.value = 'Opslaan...';
                    
                    setTimeout(function() {
                        submitButton.disabled = false;
                        submitButton.value = 'Wijzigingen opslaan';
                    }, 2000);
                }
            });
        });
        
        // Basic message display
        window.showYoCoLoyaltyMessage = function(message, type) {
            var messageDiv = document.createElement('div');
            messageDiv.className = 'yoco-loyalty-message ' + (type || 'info');
            messageDiv.textContent = message;
            
            var content = document.querySelector('.yoco-loyalty-admin-content');
            if (content) {
                content.insertBefore(messageDiv, content.firstChild);
                
                setTimeout(function() {
                    messageDiv.style.opacity = '0';
                    setTimeout(function() {
                        messageDiv.remove();
                    }, 300);
                }, 5000);
            }
        };
    });
}