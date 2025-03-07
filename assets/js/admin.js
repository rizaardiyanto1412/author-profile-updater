/**
 * Admin JavaScript for Author Profile Updater
 */
(function($) {
    'use strict';

    // Variables
    var APU = {
        isRunning: false,
        totalAuthors: 0,
        updatedAuthors: 0,
        remainingAuthors: 0,
        batchSize: 50,
        offset: 0,
        stopRequested: false
    };

    /**
     * Initialize the admin page
     */
    APU.init = function() {
        console.log('APU Init starting');
        // Cache DOM elements
        APU.$startButton = $('#apu-start-button');
        APU.$stopButton = $('#apu-stop-button');
        APU.$totalAuthors = $('#apu-total-authors');
        APU.$updatedAuthors = $('#apu-updated-authors');
        APU.$remainingAuthors = $('#apu-remaining-authors');
        APU.$progressBar = $('#apu-progress');
        APU.$progressText = $('#apu-progress-text');
        APU.$message = $('#apu-message');
        APU.$log = $('#apu-log');
        
        // Specific user elements
        APU.$specificUser = $('#apu-specific-user');
        APU.$matchType = $('#apu-match-type');
        APU.$forceUpdate = $('#apu-force-update');
        APU.$updateSpecificButton = $('#apu-update-specific-button');
        APU.$specificResult = $('#apu-specific-result');
        
        // Sync author data elements
        APU.$syncSpecificUser = $('#apu-sync-specific-user');
        APU.$syncMatchType = $('#apu-sync-match-type');
        APU.$syncForceUpdate = $('#apu-sync-force-update');
        APU.$syncSpecificButton = $('#apu-sync-specific-button');
        console.log('Sync button found:', APU.$syncSpecificButton.length > 0);
        APU.$syncResult = $('#apu-sync-result');
        
        // Bulk update elements
        APU.$bulkForceUpdate = $('#apu-bulk-force-update');
        APU.$bulkUpdateType = $('#apu-bulk-update-type');

        // Bind events
        APU.$startButton.on('click', APU.startUpdate);
        APU.$stopButton.on('click', APU.stopUpdate);
        APU.$updateSpecificButton.on('click', APU.updateSpecificUser);
        
        // Add direct click handler for sync button
        if (APU.$syncSpecificButton.length > 0) {
            console.log('Adding click handler to sync button');
            APU.$syncSpecificButton.on('click', function() {
                console.log('Sync button clicked');
                APU.syncSpecificUser();
            });
        } else {
            console.error('Sync button not found in DOM');
            // Try alternative selector
            var altButton = document.getElementById('apu-sync-specific-button');
            if (altButton) {
                console.log('Found button with direct DOM query');
                $(altButton).on('click', function() {
                    console.log('Alt sync button clicked');
                    APU.syncSpecificUser();
                });
            }
        }

        // Get initial authors count
        APU.getAuthorsCount();
    };

    /**
     * Get authors count
     */
    APU.getAuthorsCount = function() {
        APU.log('Getting authors count...');

        $.ajax({
            url: apuData.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'apu_get_authors_count',
                nonce: apuData.nonce
            },
            success: function(response) {
                if (response.success) {
                    APU.totalAuthors = response.data.count;
                    APU.remainingAuthors = response.data.count;
                    
                    APU.$totalAuthors.text(APU.totalAuthors);
                    APU.$remainingAuthors.text(APU.remainingAuthors);
                    
                    APU.log('Found ' + APU.totalAuthors + ' authors.');
                } else {
                    APU.showError(response.data.message);
                    APU.log('Error: ' + response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                APU.showError('AJAX error: ' + error);
                APU.log('AJAX error: ' + error, 'error');
            }
        });
    };

    /**
     * Update specific user
     */
    APU.updateSpecificUser = function() {
        var specificUser = APU.$specificUser.val().trim();
        var matchType = APU.$matchType.val();
        var forceUpdate = APU.$forceUpdate.is(':checked');
        
        if (!specificUser) {
            APU.showSpecificResult('Please enter an email address or username.', 'error');
            return;
        }
        
        APU.log('Updating specific user: ' + specificUser + ' (Match type: ' + matchType + ', Force update: ' + forceUpdate + ')');
        APU.showSpecificResult('Updating...', '');
        
        $.ajax({
            url: apuData.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'apu_update_specific_user',
                nonce: apuData.nonce,
                specific_user: specificUser,
                match_type: matchType,
                force_update: forceUpdate ? 'true' : 'false'
            },
            success: function(response) {
                if (response.success) {
                    APU.showSpecificResult(response.data.message, 'success');
                    APU.log(response.data.message, 'success');
                } else {
                    APU.showSpecificResult(response.data.message, 'error');
                    APU.log('Error: ' + response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                APU.showSpecificResult('AJAX error: ' + error, 'error');
                APU.log('AJAX error: ' + error, 'error');
            }
        });
    };

    /**
     * Show specific result
     *
     * @param {string} message
     * @param {string} type
     */
    APU.showSpecificResult = function(message, type) {
        APU.$specificResult.removeClass('apu-error apu-success').hide();
        
        if (message) {
            APU.$specificResult.html(message);
            
            if (type === 'error') {
                APU.$specificResult.addClass('apu-error');
            } else if (type === 'success') {
                APU.$specificResult.addClass('apu-success');
            }
            
            APU.$specificResult.show();
        }
    };

    /**
     * Start the update process
     */
    APU.startUpdate = function() {
        // Confirm before starting
        if (!confirm(apuData.confirmMessage)) {
            return;
        }

        // Reset variables
        APU.isRunning = true;
        APU.stopRequested = false;
        APU.updatedAuthors = 0;
        APU.offset = 0;
        APU.updateType = APU.$bulkUpdateType.val();
        
        // Update UI
        APU.$startButton.prop('disabled', true);
        APU.$stopButton.prop('disabled', false);
        APU.$updatedAuthors.text('0');
        APU.$progressBar.css('width', '0%');
        APU.$progressText.text('0%');
        APU.showMessage('', '');
        
        APU.log('Starting update process... (Type: ' + APU.updateType + ')');
        
        // Start the update process
        APU.processNextBatch();
    };

    /**
     * Stop the update process
     */
    APU.stopUpdate = function() {
        APU.stopRequested = true;
        APU.$stopButton.prop('disabled', true);
        APU.showMessage('Stopping the update process...', '');
        APU.log('Stopping update process...');
    };

    /**
     * Process next batch of authors
     */
    APU.processNextBatch = function() {
        if (APU.stopRequested) {
            APU.completeUpdate('Update stopped by user.');
            return;
        }

        APU.log('Processing batch: ' + APU.offset + ' to ' + (APU.offset + APU.batchSize));
        APU.showMessage('Processing authors...', '');

        var forceUpdate = APU.$bulkForceUpdate.is(':checked');
        
        $.ajax({
            url: apuData.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'apu_update_authors',
                nonce: apuData.nonce,
                offset: APU.offset,
                limit: APU.batchSize,
                force_update: forceUpdate ? 'true' : 'false',
                update_type: APU.updateType
            },
            success: function(response) {
                if (response.success) {
                    // Update counters
                    APU.updatedAuthors += response.data.updated;
                    APU.remainingAuthors = response.data.remaining;
                    
                    // Update UI
                    APU.$updatedAuthors.text(APU.updatedAuthors);
                    APU.$remainingAuthors.text(APU.remainingAuthors);
                    
                    // Calculate progress
                    var progress = Math.round((APU.updatedAuthors / APU.totalAuthors) * 100);
                    APU.$progressBar.css('width', progress + '%');
                    APU.$progressText.text(progress + '%');
                    
                    // Log message
                    APU.log(response.data.message);
                    
                    // Check if there are more authors to process
                    if (response.data.remaining > 0 && !APU.stopRequested) {
                        // Increment offset and process next batch
                        APU.offset += APU.batchSize;
                        setTimeout(APU.processNextBatch, 500);
                    } else {
                        // Complete the update
                        APU.completeUpdate(apuData.complete);
                    }
                } else {
                    APU.showError(response.data.message);
                    APU.log('Error: ' + response.data.message, 'error');
                    APU.completeUpdate('Update failed.');
                }
            },
            error: function(xhr, status, error) {
                APU.showError('AJAX error: ' + error);
                APU.log('AJAX error: ' + error, 'error');
                APU.completeUpdate('Update failed due to AJAX error.');
            }
        });
    };

    /**
     * Complete the update process
     *
     * @param {string} message
     */
    APU.completeUpdate = function(message) {
        APU.isRunning = false;
        APU.$startButton.prop('disabled', false);
        APU.$stopButton.prop('disabled', true);
        
        if (APU.stopRequested) {
            APU.showMessage('Update stopped. ' + APU.updatedAuthors + ' authors updated.', '');
            APU.log('Update stopped. ' + APU.updatedAuthors + ' authors updated.');
        } else {
            APU.showMessage(message, 'success');
            APU.log(message, 'success');
        }
    };

    /**
     * Show message
     *
     * @param {string} message
     * @param {string} type
     */
    APU.showMessage = function(message, type) {
        APU.$message.removeClass('apu-error apu-success').hide();
        
        if (message) {
            APU.$message.html(message);
            
            if (type === 'error') {
                APU.$message.addClass('apu-error');
            } else if (type === 'success') {
                APU.$message.addClass('apu-success');
            }
            
            APU.$message.show();
        }
    };

    /**
     * Show error message
     *
     * @param {string} message
     */
    APU.showError = function(message) {
        APU.showMessage(message, 'error');
    };

    /**
     * Add log entry
     *
     * @param {string} message
     * @param {string} type
     */
    APU.log = function(message, type) {
        var now = new Date();
        var time = now.getHours().toString().padStart(2, '0') + ':' + 
                   now.getMinutes().toString().padStart(2, '0') + ':' + 
                   now.getSeconds().toString().padStart(2, '0');
        
        var $entry = $('<div class="apu-log-entry"></div>');
        var $time = $('<span class="apu-log-time"></span>').text('[' + time + ']');
        var $message = $('<span class="apu-log-message"></span>').text(' ' + message);
        
        if (type === 'error') {
            $message.addClass('apu-log-error');
        } else if (type === 'success') {
            $message.addClass('apu-log-success');
        }
        
        $entry.append($time).append($message);
        APU.$log.prepend($entry);
    };

    /**
     * Sync author data for a specific user
     */
    APU.syncSpecificUser = function() {
        console.log('syncSpecificUser function called');
        
        // Check if jQuery elements exist
        if (!APU.$syncSpecificUser || APU.$syncSpecificUser.length === 0) {
            console.error('syncSpecificUser element not found');
            APU.showSyncResult('Error: Form elements not found. Please refresh the page and try again.', 'error');
            return;
        }
        
        var specificUser = APU.$syncSpecificUser.val().trim();
        var matchType = APU.$syncMatchType.val();
        var forceUpdate = APU.$syncForceUpdate.is(':checked');
        
        console.log('Form values:', {
            specificUser: specificUser,
            matchType: matchType,
            forceUpdate: forceUpdate
        });
        
        if (!specificUser) {
            console.log('No specific user entered');
            APU.showSyncResult('Please enter an email address or username.', 'error');
            return;
        }
        
        APU.log('Syncing author data for user: ' + specificUser + ' (Match type: ' + matchType + ', Force update: ' + forceUpdate + ')');
        APU.showSyncResult('Syncing author data...', 'info');
        
        APU.$syncSpecificButton.prop('disabled', true).text('Syncing...');
        
        // Check if AJAX URL and nonce are defined
        if (!apuData || !apuData.ajaxUrl) {
            console.error('apuData or ajaxUrl not defined');
            APU.showSyncResult('Error: AJAX configuration missing.', 'error');
            APU.$syncSpecificButton.prop('disabled', false).text('Sync Author Data');
            return;
        }
        
        console.log('Making AJAX request to:', apuData.ajaxUrl);
        
        $.ajax({
            url: apuData.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'apu_update_specific_user',
                nonce: apuData.nonce,
                specific_user: specificUser,
                match_type: matchType,
                force_update: forceUpdate,
                update_type: 'sync_fields'
            },
            success: function(response) {
                console.log('AJAX success:', response);
                APU.$syncSpecificButton.prop('disabled', false).text('Sync Author Data');
                
                if (response.success) {
                    var message = response.data.message;
                    APU.showSyncResult(message, 'success');
                    APU.log(message);
                    
                    // Show debug info if available
                    if (response.data.debug) {
                        console.log('Debug info:', response.data.debug);
                        var debugInfo = response.data.debug;
                        var debugHtml = '<div class="apu-debug-info">';
                        
                        debugHtml += '<h4>Debug Information</h4>';
                        
                        if (debugInfo.wp_user_found === 'Yes') {
                            debugHtml += '<p>WordPress User: Found (ID: ' + debugInfo.wp_user_id + ', Email: ' + debugInfo.wp_user_email + ')</p>';
                        } else {
                            debugHtml += '<p>WordPress User: Not found</p>';
                        }
                        
                        if (debugInfo.authors_found === 'Yes') {
                            debugHtml += '<p>Authors: Found (' + debugInfo.total_authors + ' total, ' + debugInfo.match_summary.total_matches + ' matches)</p>';
                            
                            if (debugInfo.match_summary.total_matches > 0) {
                                debugHtml += '<ul>';
                                $.each(debugInfo.authors, function(authorId, authorData) {
                                    if (authorData.match_found === 'Yes') {
                                        debugHtml += '<li>Author #' + authorId + ' (' + authorData.term_name + '): ' + authorData.action + ' - ' + authorData.update_result + '</li>';
                                    }
                                });
                                debugHtml += '</ul>';
                            }
                        } else {
                            debugHtml += '<p>Authors: None found</p>';
                        }
                        
                        debugHtml += '</div>';
                        APU.$syncResult.append(debugHtml);
                    }
                } else {
                    console.warn('AJAX response error:', response.data);
                    APU.showSyncResult(response.data.message, 'error');
                    APU.log('Error: ' + response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', {xhr: xhr, status: status, error: error});
                APU.$syncSpecificButton.prop('disabled', false).text('Sync Author Data');
                APU.showSyncResult('AJAX error: ' + error, 'error');
                APU.log('AJAX error: ' + error, 'error');
            }
        });
    };
    
    /**
     * Show sync result
     */
    APU.showSyncResult = function(message, type) {
        var className = 'apu-message-' + (type || 'info');
        APU.$syncResult.html('<div class="' + className + '">' + message + '</div>');
    };

    // Initialize when document is ready
    $(document).ready(function() {
        // Initialize APU
        APU.init();
        
        // Add direct event handler for sync button
        console.log('Adding direct event handler for sync button');
        $('#apu-sync-specific-button').on('click', function(e) {
            console.log('Direct sync button click handler triggered');
            e.preventDefault();
            
            // Get form values directly
            var specificUser = $('#apu-sync-specific-user').val().trim();
            var matchType = $('#apu-sync-match-type').val();
            var forceUpdate = $('#apu-sync-force-update').is(':checked');
            
            console.log('Form values (direct):', {
                specificUser: specificUser,
                matchType: matchType,
                forceUpdate: forceUpdate
            });
            
            if (!specificUser) {
                alert('Please enter an email address or username.');
                return;
            }
            
            // Disable button and show status
            var $button = $(this);
            $button.prop('disabled', true).text('Syncing...');
            
            // Make AJAX request
            $.ajax({
                url: apuData.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'apu_update_specific_user',
                    nonce: apuData.nonce,
                    specific_user: specificUser,
                    match_type: matchType,
                    force_update: forceUpdate,
                    update_type: 'sync_fields'
                },
                success: function(response) {
                    console.log('Direct AJAX success:', response);
                    $button.prop('disabled', false).text('Sync Author Data');
                    
                    if (response.success) {
                        alert('Success: ' + response.data.message);
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Direct AJAX error:', error);
                    $button.prop('disabled', false).text('Sync Author Data');
                    alert('AJAX error: ' + error);
                }
            });
        });
    });

})(jQuery);
