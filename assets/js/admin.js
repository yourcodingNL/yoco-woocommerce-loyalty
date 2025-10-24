/**
 * WooCommerce Loyalty Plugin - Admin JavaScript
 */

(function($) {
    'use strict';
    
    // Document ready
    $(document).ready(function() {
        WCLoyaltyAdmin.init();
    });
    
    /**
     * Admin JavaScript Object
     */
    var WCLoyaltyAdmin = {
        
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
            $(document).on('click', '.wc-loyalty-check-update', this.checkForUpdatesManual);
            
            // Clear cache button
            $(document).on('click', '.wc-loyalty-clear-cache', this.clearUpdateCache);
            
            // Tab switching
            $(document).on('click', '.wc-loyalty-tab', this.switchTab);
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
            if (!$('.wc-loyalty-admin-content').length) {
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
            
            $button.text('Controleren...').prop('disabled', true);
            
            WCLoyaltyAdmin.performUpdateCheck(function(hasUpdate) {
                $button.text(originalText).prop('disabled', false);
                
                if (hasUpdate) {
                    WCLoyaltyAdmin.showMessage('Er is een nieuwe versie beschikbaar!', 'warning');
                } else {
                    WCLoyaltyAdmin.showMessage('Je gebruikt de nieuwste versie.', 'success');
                }
            });
        },
        
        /**
         * Perform update check
         */
        performUpdateCheck: function(callback) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wc_loyalty_check_update',
                    nonce: WCLoyaltyAdmin.getNonce()
                },
                success: function(response) {
                    if (response.success && response.data.has_update) {
                        WCLoyaltyAdmin.showUpdateNotice(response.data);
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
            var notice = $('<div class="wc-loyalty-update-notice">' +
                '<p><strong>Nieuwe versie beschikbaar:</strong> ' + updateData.new_version + 
                ' (huidige versie: ' + updateData.current_version + ')</p>' +
                '<p><a href="' + updateData.update_url + '" class="wc-loyalty-button">Nu bijwerken</a></p>' +
                '</div>');
            
            $('.wc-loyalty-admin-content').prepend(notice);
        },
        
        /**
         * Clear update cache
         */
        clearUpdateCache: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            $button.text('Wissen...').prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wc_loyalty_clear_cache',
                    nonce: WCLoyaltyAdmin.getNonce()
                },
                success: function(response) {
                    $button.text(originalText).prop('disabled', false);
                    
                    if (response.success) {
                        WCLoyaltyAdmin.showMessage('Cache succesvol gewist.', 'success');
                    } else {
                        WCLoyaltyAdmin.showMessage('Cache wissen mislukt.', 'error');
                    }
                },
                error: function() {
                    $button.text(originalText).prop('disabled', false);
                    WCLoyaltyAdmin.showMessage('Cache wissen mislukt.', 'error');
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
            $('.wc-loyalty-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Show/hide content
            $('.wc-loyalty-tab-content').hide();
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
            
            var $message = $('<div class="wc-loyalty-message ' + type + '">' + message + '</div>');
            
            $('.wc-loyalty-admin-content').prepend($message);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        /**
         * Get AJAX nonce
         */
        getNonce: function() {
            // For now, return empty string - in production you'd add this via wp_localize_script
            return '';
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
            $element.addClass('wc-loyalty-loading');
        },
        
        /**
         * Utility: Hide loading state
         */
        hideLoading: function($element) {
            $element.removeClass('wc-loyalty-loading');
        }
    };
    
    // Make WCLoyaltyAdmin globally accessible
    window.WCLoyaltyAdmin = WCLoyaltyAdmin;
    
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
        window.showWCLoyaltyMessage = function(message, type) {
            var messageDiv = document.createElement('div');
            messageDiv.className = 'wc-loyalty-message ' + (type || 'info');
            messageDiv.textContent = message;
            
            var content = document.querySelector('.wc-loyalty-admin-content');
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
