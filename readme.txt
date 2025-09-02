=== LLM Visibility Monitor ===
Contributors: openstream
Tags: llm, ai, monitoring, openrouter, cron, dashboard
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Monitor LLM responses on a schedule and store/export results with OpenRouter integration.

== Description ==

LLM Visibility Monitor is a comprehensive WordPress plugin that allows you to monitor Large Language Model (LLM) responses on a scheduled basis. It integrates with OpenRouter to send prompts to various AI models and stores the results for analysis and export.

**Key Features:**

* **OpenRouter Integration**: Connect to multiple AI models through OpenRouter's unified API
* **Scheduled Monitoring**: Set up daily or weekly cron jobs to automatically send prompts
* **Prompt Management**: Create, edit, and delete prompts with individual model selection
* **Results Dashboard**: View all LLM responses in a sortable, searchable table
* **CSV Export**: Export results for external analysis
* **Email Reports**: Receive email notifications with formatted results
* **Role-Based Access Control**: Assign "LLM Manager" role for limited admin access
* **User-Specific Data**: Secure isolation between user prompts, results, and exports
* **Personalized Email Reports**: Users receive emails at their own address with only their data
* **Comprehensive Logging**: Detailed logging for debugging and monitoring
* **German Localization**: Full support for German (de_DE, de_CH)

**Use Cases:**

* Monitor AI model performance over time
* Track response quality and consistency
* Generate regular reports for stakeholders
* Test different prompts and models
* Maintain audit trails of AI interactions

**Role-Based Access:**

* **Administrators**: Full access to all features including settings
* **LLM Managers**: Can manage prompts, view dashboard, and view results (no settings access)
* **Other Roles**: No LLM access

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/llm-visibility-monitor` directory, or install the plugin through the WordPress admin interface.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to 'Settings > LLM Visibility Monitor' to configure your OpenRouter API key and other settings.
4. Add prompts and configure your monitoring schedule.

== Frequently Asked Questions ==

= What is OpenRouter? =

OpenRouter is a unified API that provides access to multiple AI models from different providers (OpenAI, Anthropic, Google, etc.) through a single interface.

= Do I need an OpenRouter account? =

Yes, you'll need to create an account at [openrouter.ai](https://openrouter.ai) and obtain an API key to use this plugin.

= Can I use different models for different prompts? =

Yes! Each prompt can be configured to use a specific AI model, or it can fall back to your global default model.

= How often can I run the monitoring? =

The plugin supports daily and weekly scheduling. You can also manually trigger runs using the "Run Now" button.

= Can I export the results? =

Yes, the plugin provides CSV export functionality for all stored results.

= Is there role-based access control? =

Yes! You can assign users the "LLM Manager" role, which gives them access to manage prompts and view results, but not access to plugin settings.

= Can users see each other's data? =

No! Each user can only see and manage their own prompts and results. Administrators can see all data for oversight purposes, but regular users are completely isolated.

= How do email reports work? =

Administrators receive emails at the WordPress admin email with all results from all users. Regular users receive emails at their own email address with only their own results.

= Is my data secure from other users? =

Yes! The plugin implements strict user isolation. Each user's prompts, results, and exports are completely separated from other users' data.

== Screenshots ==

1. Plugin settings page with OpenRouter configuration
2. Prompt management interface
3. Results dashboard with sorting and bulk operations
4. Individual result detail view

== Changelog ==

= 0.5.0 =
* **New Feature**: Implemented role-based access control
  * Added "LLM Manager" role with limited admin access
  * LLM Managers can manage prompts, view dashboard, and view results
  * LLM Managers cannot access plugin settings
  * Administrators retain full access to all features
  * Other user roles have no LLM access
* **New Feature**: User-specific prompt management
  * Users can only see and manage their own prompts
  * Admins can see all prompts but only edit/delete their own
  * Secure isolation between user data
* **New Feature**: User-specific results filtering
  * Dashboard shows only user's own results (unless admin)
  * CSV export respects user permissions
  * Proper user ID assignment in cron jobs
* **New Feature**: Personalized email reporting
  * Users receive emails at their own email address
  * Admins receive emails at admin email with all results
  * User ownership information in admin reports
  * Smart filtering based on user role
* **Enhancement**: Improved security and data isolation
  * Fixed CSV export user filtering
  * Enhanced cron job user context
  * Better user permission enforcement

= 0.4.0 =
* **New Feature**: Per-prompt model selection
  * Users can now specify a different OpenRouter model for each individual prompt
  * Falls back to global default model if no specific model is selected
  * Prevents duplicate prompts with the same text and model combination
  * Added admin notices for successful operations and warnings

= 0.3.0 =
* **Enhancement**: Improved dashboard table functionality
  * Added column sorting (click column headers to sort)
  * Implemented bulk delete functionality for results
  * Added hover actions for better user experience
  * Fixed cron job scheduling issues
  * Improved logging and removed debug backtraces
  * Enhanced email reports with markdown to HTML conversion
  * Fixed vertical spacing between buttons and table

= 0.2.0 =
* **New Feature**: Email reporting system
  * Configurable email notifications for cron job results
  * HTML-formatted reports with prompt, model, and answer details
  * Customizable email settings in admin panel
* **Enhancement**: Improved dashboard layout
  * Better mobile responsiveness with adjusted column widths
  * Action links moved to prompt column with hover display
  * "Details" and "Delete" buttons for each result entry
* **Enhancement**: Enhanced OpenRouter model selection
  * Searchable dropdown for model selection
  * Graceful fallback for API errors
  * Better error handling and user feedback

= 0.1.0 =
* **Initial Release**: Core LLM monitoring functionality
  * OpenRouter API integration with secure API key storage
  * Prompt management (CRUD operations)
  * Scheduled cron jobs (daily/weekly)
  * Results storage and dashboard
  * CSV export functionality
  * Comprehensive logging system
  * WordPress admin interface
  * German localization (de_DE, de_CH)

== Upgrade Notice ==

= 0.5.0 =
This version introduces comprehensive role-based access control and user data isolation. A new "LLM Manager" role will be created automatically, allowing you to grant limited admin access to other users. Users will now only see their own prompts and results, with administrators maintaining oversight of all data. Email reports are now personalized - users receive emails at their own address with only their data, while admins receive comprehensive reports at the admin email.

= 0.4.0 =
This version adds per-prompt model selection. Existing prompts will automatically use your global default model, but you can now assign specific models to individual prompts.

= 0.3.0 =
This version fixes cron job scheduling issues and improves the dashboard functionality with sorting and bulk operations.

= 0.2.0 =
This version adds email reporting and improves the dashboard layout with better mobile support.

== License ==

This plugin is licensed under the GPL v2 or later.
