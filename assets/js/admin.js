/**
 * YoCo Loyalty Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Check for updates button
        $('.yoco-loyalty-check-update').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const originalText = $button.text();
            
            $button.text('Checking...').prop('disabled', true);
            
            $.ajax({
                url: yocoLoyalty.ajaxurl,
                type: 'POST',
                data: {
                    action: 'yoco_loyalty_check_update',
                    nonce: yocoLoyalty.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data.message);
                    } else {
                        showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    showNotice('error', 'Er is een fout opgetreden bij het controleren op updates.');
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        });
        
        // Clear cache button
        $('.yoco-loyalty-clear-cache').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const originalText = $button.text();
            
            $button.text('Clearing...').prop('disabled', true);
            
            $.ajax({
                url: yocoLoyalty.ajaxurl,
                type: 'POST',
                data: {
                    action: 'yoco_loyalty_clear_cache',
                    nonce: yocoLoyalty.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data.message);
                    } else {
                        showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    showNotice('error', 'Er is een fout opgetreden bij het wissen van de cache.');
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        });
        
        // Auto-refresh stats every 30 seconds on dashboard
        if ($('.yoco-loyalty-stats-grid').length) {
            setInterval(function() {
                refreshStats();
            }, 30000);
        }
        
        // Points adjustment form validation
        $('#adjust_points').on('submit', function(e) {
            const userId = $('#user_id').val();
            const points = $('#points').val();
            
            if (!userId || userId <= 0) {
                e.preventDefault();
                showNotice('error', 'Voer een geldig gebruiker ID in.');
                return false;
            }
            
            if (!points || points <= 0) {
                e.preventDefault();
                showNotice('error', 'Voer een geldig aantal punten in.');
                return false;
            }
            
            // Confirm action
            const action = $('#action').val();
            const actionText = action === 'add' ? 'toevoegen aan' : 'aftrekken van';
            
            if (!confirm(`Weet je zeker dat je ${points} punten wilt ${actionText} gebruiker ${userId}?`)) {
                e.preventDefault();
                return false;
            }
        });
        
        // Tooltips for help text
        $('[data-tooltip]').each(function() {
            $(this).attr('title', $(this).data('tooltip'));
        });
        
        // Collapsible sections
        $('.yoco-loyalty-collapsible-header').on('click', function() {
            const $content = $(this).next('.yoco-loyalty-collapsible-content');
            $content.slideToggle(300);
            $(this).find('.dashicons').toggleClass('dashicons-arrow-down dashicons-arrow-up');
        });
        
    });
    
    /**
     * Show admin notice
     */
    function showNotice(type, message) {
        const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        
        // Remove existing notices
        $('.notice.yoco-loyalty-notice').remove();
        
        // Add new notice
        $notice.addClass('yoco-loyalty-notice').insertAfter('.wrap h1');
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        // Make dismissible
        $notice.on('click', '.notice-dismiss', function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        });
    }
    
    /**
     * Refresh dashboard stats
     */
    function refreshStats() {
        $.ajax({
            url: yocoLoyalty.ajaxurl,
            type: 'POST',
            data: {
                action: 'yoco_loyalty_get_stats',
                nonce: yocoLoyalty.nonce
            },
            success: function(response) {
                if (response.success && response.data.stats) {
                    updateStatsDisplay(response.data.stats);
                }
            },
            error: function() {
                console.log('Failed to refresh stats');
            }
        });
    }
    
    /**
     * Update stats display
     */
    function updateStatsDisplay(stats) {
        $('.yoco-loyalty-stat-card').each(function() {
            const $card = $(this);
            const statType = $card.data('stat-type');
            
            if (stats[statType] !== undefined) {
                $card.find('.stat-number').text(formatNumber(stats[statType]));
            }
        });
    }
    
    /**
     * Format number with commas
     */
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    
    /**
     * Confirm dialog helper
     */
    window.yocoLoyaltyConfirm = function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    };
    
})(jQuery);