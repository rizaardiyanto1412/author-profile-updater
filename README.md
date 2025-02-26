# Author Profile Updater

A WordPress plugin that helps you automatically map guest authors to WordPress user accounts.

## Description

Author Profile Updater is designed to work with PublishPress Authors to map guest authors to WordPress users based on matching email addresses. This is particularly useful when you have a large number of authors (e.g., 29k users) that need to be updated.

## Features

- Dedicated admin page for bulk updating author profiles
- Progress bar and statistics to track the update process
- Batch processing to handle large numbers of authors
- Log of update activities
- Ability to stop and resume the update process
- Bulk update all authors to match WordPress users
- Update specific authors by email, username, or display name
- Force update authors that are already mapped to different users
- Detailed debug information for troubleshooting

## Installation

1. Upload the `author-profile-updater` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Tools > Author Profile Updater to access the admin page

## Usage

### Specific User Update

1. Navigate to the Author Profile Updater admin page
2. Enter an email address, username, or display name in the "Email or Username" field
3. Select the match type (Email, Username, or Display Name)
4. Check "Force Update" if you want to update authors that are already mapped to different users
5. Click "Update Specific User"

### Bulk Update

1. Navigate to the Author Profile Updater admin page
2. Optionally check "Force Update" if you want to update authors that are already mapped to users
3. Click "Start Bulk Update"

## Matching Process

The plugin uses the following strategies to match authors to WordPress users:

1. **Email Matching**: Checks if the author's email matches a WordPress user's email
2. **Username Matching**: Checks if the author's display name matches a WordPress user's username
3. **Display Name Matching**: Checks if the author's display name matches a WordPress user's display name

## Force Update Option

The "Force Update" option allows you to update authors that are already mapped to different users. This is useful when:

- An author was incorrectly mapped to the wrong user
- You want to consolidate multiple authors into a single user
- You're migrating from one user system to another

Without the force update option enabled, the plugin will skip authors that are already mapped to a different user to prevent accidental changes.

## Debug Information

The plugin provides detailed debug information to help you troubleshoot issues:

- Which authors were matched and why
- Which authors were skipped and why
- Email sources that were checked
- Total counts of updated, skipped, and failed authors

## Requirements

- WordPress 5.0 or higher
- PublishPress Authors plugin

## Notes

- The plugin maps guest authors to users based on matching email addresses
- Only unmapped authors will be processed
- Authors without an email address will be skipped
- The update process is irreversible, so it's recommended to backup your database before starting

## License

This plugin is licensed under the GPL v2 or later.

## Credits

This plugin was created to extend the functionality of the PublishPress Authors plugin.

## Support

For support or feature requests, please create an issue on the GitHub repository.
