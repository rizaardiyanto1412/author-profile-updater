<?php
/**
 * Admin page view for Author Profile Updater
 *
 * @package Author_Profile_Updater
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap apu-admin-page">
    <h1><?php echo esc_html__('Author Profile Updater', 'author-profile-updater'); ?></h1>
    
    <div class="apu-description">
        <p><?php echo esc_html__('This tool will update author profiles to match with author emails. It will map guest authors to users with matching email addresses.', 'author-profile-updater'); ?></p>
        <p><?php echo esc_html__('If no matching email is found, the tool will try to match by author name as a fallback.', 'author-profile-updater'); ?></p>
        <p><?php echo esc_html__('This is useful when you have a large number of authors that need to be mapped to users.', 'author-profile-updater'); ?></p>
    </div>
    
    <div class="apu-card">
        <div class="apu-card-header">
            <h2><?php echo esc_html__('Update Author Profiles', 'author-profile-updater'); ?></h2>
        </div>
        
        <div class="apu-card-body">
            <div class="apu-specific-user-section">
                <h3><?php echo esc_html__('Update Specific User', 'author-profile-updater'); ?></h3>
                <p><?php echo esc_html__('Enter a specific email address or username to update only authors that match.', 'author-profile-updater'); ?></p>
                
                <div class="apu-specific-user-form">
                    <div class="apu-form-row">
                        <label for="apu-specific-user"><?php echo esc_html__('Email or Username:', 'author-profile-updater'); ?></label>
                        <input type="text" id="apu-specific-user" class="regular-text" placeholder="<?php echo esc_attr__('e.g., user@example.com or username', 'author-profile-updater'); ?>">
                    </div>
                    <div class="apu-form-row">
                        <label for="apu-match-type"><?php echo esc_html__('Match Type:', 'author-profile-updater'); ?></label>
                        <select id="apu-match-type">
                            <option value="email"><?php echo esc_html__('Email', 'author-profile-updater'); ?></option>
                            <option value="username"><?php echo esc_html__('Username', 'author-profile-updater'); ?></option>
                            <option value="display_name"><?php echo esc_html__('Display Name', 'author-profile-updater'); ?></option>
                        </select>
                    </div>
                    <div class="apu-form-row">
                        <label for="apu-force-update"><?php echo esc_html__('Force Update:', 'author-profile-updater'); ?></label>
                        <input type="checkbox" id="apu-force-update">
                        <span class="apu-checkbox-description"><?php echo esc_html__('Update authors even if already mapped to a different user', 'author-profile-updater'); ?></span>
                    </div>
                    <div class="apu-form-row">
                        <button type="button" class="button button-secondary" id="apu-update-specific-button"><?php echo esc_html__('Update Specific User', 'author-profile-updater'); ?></button>
                    </div>
                </div>
                
                <div class="apu-specific-result" id="apu-specific-result"></div>
            </div>
            
            <hr class="apu-divider">
            
            <div class="apu-sync-author-data-section">
                <h3><?php echo esc_html__('Sync Author and User Fields', 'author-profile-updater'); ?></h3>
                <p><?php echo esc_html__('Update author fields with data from their mapped WordPress users. This will sync fields like name, email, URL, and description.', 'author-profile-updater'); ?></p>
                
                <div class="apu-sync-author-form">
                    <div class="apu-form-row">
                        <label for="apu-sync-specific-user"><?php echo esc_html__('User Email or Username:', 'author-profile-updater'); ?></label>
                        <input type="text" id="apu-sync-specific-user" class="regular-text" placeholder="<?php echo esc_attr__('e.g., user@example.com or username', 'author-profile-updater'); ?>">
                    </div>
                    <div class="apu-form-row">
                        <label for="apu-sync-match-type"><?php echo esc_html__('Match Type:', 'author-profile-updater'); ?></label>
                        <select id="apu-sync-match-type">
                            <option value="email"><?php echo esc_html__('Email', 'author-profile-updater'); ?></option>
                            <option value="username"><?php echo esc_html__('Username', 'author-profile-updater'); ?></option>
                            <option value="display_name"><?php echo esc_html__('Display Name', 'author-profile-updater'); ?></option>
                        </select>
                    </div>
                    <div class="apu-form-row">
                        <label for="apu-sync-force-update"><?php echo esc_html__('Force Update:', 'author-profile-updater'); ?></label>
                        <input type="checkbox" id="apu-sync-force-update">
                        <span class="apu-checkbox-description"><?php echo esc_html__('Update authors even if already mapped to a different user', 'author-profile-updater'); ?></span>
                    </div>
                    <div class="apu-form-row">
                        <button type="button" class="button button-secondary" id="apu-sync-specific-button"><?php echo esc_html__('Sync Author Data', 'author-profile-updater'); ?></button>
                    </div>
                </div>
                
                <div class="apu-sync-result" id="apu-sync-result"></div>
            </div>
            
            <hr class="apu-divider">
            
            <h3><?php echo esc_html__('Bulk Update All Authors', 'author-profile-updater'); ?></h3>
            
            <div class="apu-stats">
                <div class="apu-stat-item">
                    <span class="apu-stat-label"><?php echo esc_html__('Total Authors:', 'author-profile-updater'); ?></span>
                    <span class="apu-stat-value" id="apu-total-authors">-</span>
                </div>
                <div class="apu-stat-item">
                    <span class="apu-stat-label"><?php echo esc_html__('Updated:', 'author-profile-updater'); ?></span>
                    <span class="apu-stat-value" id="apu-updated-authors">0</span>
                </div>
                <div class="apu-stat-item">
                    <span class="apu-stat-label"><?php echo esc_html__('Remaining:', 'author-profile-updater'); ?></span>
                    <span class="apu-stat-value" id="apu-remaining-authors">-</span>
                </div>
            </div>
            
            <div class="apu-form-row">
                <label for="apu-bulk-force-update"><?php echo esc_html__('Force Update:', 'author-profile-updater'); ?></label>
                <input type="checkbox" id="apu-bulk-force-update">
                <span class="apu-checkbox-description"><?php echo esc_html__('Update authors even if already mapped to a user', 'author-profile-updater'); ?></span>
            </div>
            
            <div class="apu-form-row">
                <label for="apu-bulk-update-type"><?php echo esc_html__('Update Type:', 'author-profile-updater'); ?></label>
                <select id="apu-bulk-update-type">
                    <option value="map_authors"><?php echo esc_html__('Map authors to users by email', 'author-profile-updater'); ?></option>
                    <option value="sync_fields"><?php echo esc_html__('Sync author and user fields (for already mapped authors)', 'author-profile-updater'); ?></option>
                </select>
            </div>
            
            <div class="apu-progress-container">
                <div class="apu-progress-bar" id="apu-progress-bar">
                    <div class="apu-progress" id="apu-progress" style="width: 0%;"></div>
                </div>
                <div class="apu-progress-text" id="apu-progress-text">0%</div>
            </div>
            
            <div class="apu-message" id="apu-message"></div>
            
            <div class="apu-actions">
                <button type="button" class="button button-primary" id="apu-start-button"><?php echo esc_html__('Start Bulk Update', 'author-profile-updater'); ?></button>
                <button type="button" class="button" id="apu-stop-button" disabled><?php echo esc_html__('Stop', 'author-profile-updater'); ?></button>
            </div>
        </div>
        
        <div class="apu-card-footer">
            <p class="description"><?php echo esc_html__('Note: This process may take some time depending on the number of authors. You can stop the process at any time and resume it later.', 'author-profile-updater'); ?></p>
        </div>
    </div>
    
    <div class="apu-log-container">
        <h3><?php echo esc_html__('Update Log', 'author-profile-updater'); ?></h3>
        <div class="apu-log" id="apu-log"></div>
    </div>
</div>
