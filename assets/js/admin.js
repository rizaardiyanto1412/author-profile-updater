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
        
        // Bulk update elements
        APU.$bulkForceUpdate = $('#apu-bulk-force-update');

        // Bind events
        APU.$startButton.on('click', APU.startUpdate);
        APU.$stopButton.on('click', APU.stopUpdate);
        APU.$updateSpecificButton.on('click', APU.updateSpecificUser);

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
        
        // Update UI
        APU.$startButton.prop('disabled', true);
        APU.$stopButton.prop('disabled', false);
        APU.$updatedAuthors.text('0');
        APU.$progressBar.css('width', '0%');
        APU.$progressText.text('0%');
        APU.showMessage('', '');
        
        APU.log('Starting update process...');
        
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
                force_update: forceUpdate ? 'true' : 'false'
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

    // Initialize when document is ready
    $(document).ready(APU.init);

})(jQuery);
