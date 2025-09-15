=== LLM Visibility Monitor ===
Contributors: openstream
Tags: llm, ai, monitoring, openrouter, dashboard
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.13.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Monitor LLM responses on a schedule and store/export results with OpenRouter integration.

== Description ==

LLM Visibility Monitor is a comprehensive WordPress plugin that allows you to monitor Large Language Model (LLM) responses on a scheduled basis. It integrates with OpenRouter to send prompts to various AI models and stores the results for analysis and export.

**Key Features:**

* **OpenRouter Integration**: Connect to 300+ AI models through OpenRouter's unified API
* **Scheduled Monitoring**: Set up daily or weekly cron jobs to automatically send prompts
* **Prompt Management**: Create, edit, and delete prompts with multi-model selection
* **Results Dashboard**: View all LLM responses in a sortable, searchable table
* **CSV Export**: Export results for external analysis
* **Email Reports**: Receive email notifications with formatted results
* **Role-Based Access Control**: Assign "LLM Manager Free" or "LLM Manager Pro" roles with configurable usage limits
* **User-Specific Data**: Secure isolation between user prompts, results, and exports
* **Multi-Model Selection**: Select multiple AI models from 300+ available models for each prompt to compare responses
* **Personalized Email Reports**: Users receive emails at their own address with only their data
* **Comprehensive Logging**: Detailed logging for debugging and monitoring
* **German Localization**: Full support for German (de_DE, de_CH)

**Use Cases:**

* Monitor AI model performance over time
* Track response quality and consistency
* Generate regular reports for stakeholders
* Test different prompts across 300+ AI models simultaneously
* Maintain audit trails of AI interactions

**Role-Based Access:**

* **Administrators**: Full access to all features including settings
* **LLM Manager Pro**: 10 prompts max, 6 models per prompt, 300 runs per month
* **LLM Manager Free**: 3 prompts max, 3 models per prompt, 30 runs per month
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

Yes! Each prompt can be configured to use multiple AI models simultaneously from 300+ available models, or it can fall back to your global default model. This allows you to compare responses across different models for the same prompt.

= How often can I run the monitoring? =

The plugin supports daily and weekly scheduling. You can also manually trigger runs using the "Run Now" button.

= Can I export the results? =

Yes, the plugin provides CSV export functionality for all stored results.

= Is there role-based access control? =

Yes! You can assign users the "LLM Manager Free" or "LLM Manager Pro" roles, which give them access to manage prompts and view results with different usage limits, but not access to plugin settings.

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

== Development ==

=== PHP Syntax Checking with DDEV ===

When developing with DDEV, you can check PHP syntax using the following commands:

# Check PHP version
ddev php -v

# Check syntax of main plugin file
ddev php -l /var/www/html/wp-content/plugins/llm-visibility-monitor/llm-visibility-monitor.php

# Check syntax of key plugin files
ddev php -l /var/www/html/wp-content/plugins/llm-visibility-monitor/includes/class-llmvm-cron.php
ddev php -l /var/www/html/wp-content/plugins/llm-visibility-monitor/includes/class-llmvm-progress-tracker.php
ddev php -l /var/www/html/wp-content/plugins/llm-visibility-monitor/includes/class-llmvm-admin.php
ddev php -l /var/www/html/wp-content/plugins/llm-visibility-monitor/includes/class-llmvm-database.php

**Note**: When using `ddev php` from the host system, use the full container paths (`/var/www/html/...`). If you're inside the DDEV container, you can use relative paths.

=== Container Paths ===

* **Plugin Directory**: `/var/www/html/wp-content/plugins/llm-visibility-monitor/`
* **Main Plugin File**: `/var/www/html/wp-content/plugins/llm-visibility-monitor/llm-visibility-monitor.php`
* **Includes Directory**: `/var/www/html/wp-content/plugins/llm-visibility-monitor/includes/`

== Changelog ==

= 0.13.0 - 2025-01-12 =
* **UI Improvements**: Enhanced Admin Interface
  * Added collapsible "Add New Prompt" form that starts collapsed for cleaner interface
  * Implemented click-to-expand functionality with smooth CSS transitions
  * Improved textarea alignment between "Add New Prompt" and "Your Prompts" sections
  * Ensured consistent width (589px) and left margin alignment across all textareas
  * Added proper internal padding to textareas for better text spacing
  * Fixed "Prompt" header padding in "Your Prompts" section for visual consistency
  * Enhanced overall user experience with progressive disclosure design

= 0.12.0 - 2025-01-12 =
* **New Feature**: Enhanced Response Time Logging
  * Added detailed response time tracking for all OpenRouter API requests
  * Response times logged in milliseconds with model, prompt length, and body size information
  * Enhanced error logging with response time context for better debugging
  * Progress tracking now displays response times in completion messages
  * Improved performance monitoring for production environments
* **New Feature**: Queue System for Asynchronous Processing
  * Implemented WordPress-based queue system to prevent timeout issues
  * Queue system is now always enabled for all LLM requests (simplified architecture)
  * Queue status display showing pending, processing, completed, and failed jobs
  * Automatic cleanup of old completed jobs (7+ days old)
  * Fallback to synchronous processing if queue system fails
* **New Feature**: Queue Management Interface
  * New "LLM Queue Status" page under Tools for monitoring queue operations
  * Real-time queue status with auto-refresh every 30 seconds
  * User-specific queue filtering (limited users see only their jobs, admins see all)
  * Queue job details including model, status, creation time, and attempt counts
  * Admin controls for clearing all queue jobs
  * Visual status indicators with color-coded badges and cards
  * Performance metrics display including response time, execution time, and queue overhead
  * Detailed timing breakdown showing queue wait time vs processing time
* **New Feature**: Configurable Concurrency Control
  * Admin setting to control maximum concurrent jobs (1-5, default: 1 for shared hosting)
  * Prevents server overload on shared hosting environments
  * Automatic job queuing when concurrency limit is reached
  * Enhanced queue processing with proper job prioritization
* **Enhancement**: Email Reporting System Overhaul
  * Completely refactored email reporting to use database storage instead of transients
  * Fixed email reporting for both single runs and "run all prompts now" operations
  * Implemented batch run ID system to group all results from multi-prompt runs
  * Email reports now correctly include all results from the current run only
  * Enhanced email reliability with proper result collection and cleanup
* **Enhancement**: Logging System Improvements
  * Moved logging to WordPress root directory for easier access
  * Implemented dual log file system: master log (all logs) and current run log (run-specific)
  * Added log rotation for master log file (5MB limit)
  * Enhanced log filtering for current run logs with relevant keywords
  * Added .gitignore to prevent committing log files
* **Bug Fixes**: Critical Issues Resolved
  * Fixed undefined variable `$job_update_time` PHP warning
  * Fixed email reporting for batch runs to include all results instead of just the last prompt
  * Fixed run ID generation to use consistent batch run IDs across all jobs
  * Fixed email firing logic to use correct run_id (batch_run_id vs prompt_id)
  * Fixed duplicate job processing race conditions with atomic updates
  * Fixed popup display issues by removing progress popup for queue-based runs
  * Fixed negative overhead calculations in queue status display

= 0.11.0 - 2025-09-10 =
* **New Feature**: Per-Prompt Cron Frequency Settings
  * Added cron frequency dropdown (daily/weekly/monthly) to "Add New Prompt" form
  * Each prompt can now have its own individual cron schedule
  * Updated "Your Prompts" section to show current cron frequency and allow changes
  * Removed global cron frequency setting from Settings page
  * Individual cron jobs are automatically scheduled for each prompt based on their frequency
  * Enhanced cron scheduler to handle per-prompt frequencies with proper WordPress cron integration
  * Database migration automatically adds cron_frequency field to existing prompts (defaults to daily)
  * Improved user experience with granular control over prompt execution timing
* **Enhancement**: Improved Cron Management
  * Automatic scheduling/unscheduling of cron jobs when prompts are added, edited, or deleted
  * Better cron job isolation with unique hooks per prompt
  * Enhanced logging for cron job operations
  * Proper cleanup of cron jobs when prompts are removed
* **Technical Improvement**: Database Schema Update
  * Updated database version to 1.5.0
  * Added migration for existing prompts to include cron_frequency field
  * Backward compatibility maintained for existing installations
* **UI/UX Enhancement**: Optimized Admin Interface
  * Reduced "Add New Prompt" form width to 45% for better desktop layout
  * Added WordPress admin styling with background, border, and shadow for better visual separation
  * Converted form structure to table layout for improved organization
  * Reduced model selection font size to 11px to prevent line breaks in model names
  * Updated model dropdown border color from red to standard WordPress admin color (#c3c4c7)
  * Enhanced form usability with proper spacing and visual hierarchy
* **Email Layout Optimization**: Improved Desktop Email Reports
  * Removed separate "Date & Model" column from email report tables
  * Integrated date and model information into the "Prompt" column as badges
  * Increased "Answer" column width from 55% to 65% for better content display
  * Enhanced mobile responsiveness with adjusted column widths (Answer: 50% → 60%)
  * Improved email readability with better content organization
* **Bug Fixes**: Critical Issues Resolved
  * Fixed "Run All Prompts Now" bug where only the last prompt was processed
  * Corrected run count discrepancies in dashboard and email reports
  * Fixed missing prompt text extraction in cron execution loop
  * Resolved incomplete dashboard and email reports for multi-prompt runs
* **Performance & Reliability**: Execution Time Management
  * Added execution time limit of 720 seconds (12 minutes) for all run operations
  * Implemented large run warnings for operations with more than 10 models
  * Added user-friendly warning dialogs to prevent server timeout issues
  * Enhanced error handling for production server environments
  * Improved user guidance for large batch operations
* **Logging Optimization**: Reduced Log Spam
  * Fixed repetitive logging of "Plugin initialized" and "Cron already scheduled" messages
  * Implemented rate limiting to log initialization messages at most once per minute
  * Moved cron checking logic to prevent excessive logging on every request
  * Cleaner log files with only relevant operational information
* **Internationalization**: German Translation Updates
  * Added comprehensive German translations for all new UI elements
  * Translated prompt management interface including cron frequency options
  * Updated all German locale files (de_DE, de_CH, de_DE_formal, de_CH_informal)
  * Fixed Swiss German informal translations to use proper "Du" forms instead of "Sie"
  * Maintained consistency with existing translation patterns

= 0.10.0 - 2025-09-09 =
* **New Feature**: Web Search Integration with OpenRouter
  * Added web search checkbox to "Add New Prompt" form
  * Added "Web Search" column to prompts table showing web search status
  * Automatic appending of :online to model names when web search is enabled
  * Follows OpenRouter web search documentation for model-agnostic grounding
  * Users can enable/disable web search for individual prompts
  * Web search status visible in both admin and user views
  * Backward compatibility maintained for existing prompts
  * Enhanced logging to track web search usage and model modifications
* **New Feature**: Loading Overlay for Run Operations
  * Added loading overlay that prevents user interactions during prompt execution
  * Prevents accidental interruption of running prompts
  * Shows progress messages for "Run All Prompts Now" and individual "Run Now" operations
  * Blocks all user interactions (clicks, keyboard shortcuts, context menu) during execution
  * Enhanced user experience with clear visual feedback
* **New Feature**: Markdown Link Support in Email Reports
  * Added support for markdown link syntax [text](URL) in email reports
  * Automatic conversion to HTML <a> tags with proper styling
  * Secure URL handling with esc_url() and esc_html() for XSS protection
  * Email-compatible inline CSS styling for links
  * Enhanced content formatting for better email readability
* **New Feature**: Login Page Customization
  * Added "Login Page Customization" section in plugin settings
  * Replaces WordPress logo with site name "LLM Visibility Monitor" on login page
  * Custom text area for adding personalized content below the site name
  * Supports HTML formatting including links and bold text
  * Editable through Settings → LLM Visibility Monitor → Login Page Customization
  * Professional styling with proper spacing and visual hierarchy
* **Enhancement**: Upgrade Link for Usage Limits
  * Added upgrade link in usage summary when monthly run limit is reached
  * Direct link to subscription page for easy plan upgrades
  * Encourages users to upgrade when they hit their limits
  * Improved user experience with clear upgrade path
* **Bug Fix**: Fixed duplicate menu items in WordPress admin
  * Resolved issue where "LLM Visibility Monitor" appeared twice in Settings sidebar
  * Optimized admin class instantiation to prevent duplicate menu registration
  * Cleaner admin interface with single menu entry
* **Bug Fix**: Fixed duplicate settings sections
  * Removed duplicate "Login Page Customization" section on settings page
  * Proper form integration for all settings sections
  * Cleaner settings interface with no duplicate content
* **New Feature**: Real-time Progress Tracking with Progress Bar
  * Enhanced loading overlay with realistic progress bar instead of simulated progress
  * Real-time progress updates via AJAX polling during prompt execution
  * Server-side progress tracking using WordPress transients for reliable state management
  * Progress messages show current step and detailed status (e.g., "Starting model: gpt-4o-mini")
  * Accurate progress percentage based on actual completion status
  * Progress bar reflects real execution time instead of estimated completion
  * Enhanced user experience with accurate feedback during long-running operations
* **Enhancement**: Web Search Progress Messages
  * Progress messages now display `:online` suffix when web search is enabled
  * Clear indication of which models are using web search capabilities
  * Examples: "Starting model: gpt-4o-mini:online" and "Completed model: gpt-4o-mini:online"
  * Better visibility into web search usage during prompt execution
* **Bug Fix**: Fixed missing dates in email reports
  * Resolved issue where email reports showed only green lines instead of timestamps
  * Added missing `created_at` field to progress tracking result arrays
  * Email reports now display proper timestamps for all results
  * Fixed in both `run_with_progress()` and `run_single_prompt_with_progress()` methods
  * Enhanced result data structure with complete timestamp information
* **Enhancement**: Customized admin bar for LLM Manager roles
  * Cleaned up WordPress admin bar for LLM Manager Free and Pro users
  * Removed comments/notifications icon and "New" button from admin bar
  * Hidden updates notification and search box for cleaner interface
  * Preserved WordPress logo, site name, and user profile menu
  * Creates focused admin experience tailored for LLM management tasks

= 0.9.0 - 2025-09-08 =
* **New Feature**: Model limit enforcement for free plan users
  * Free plan users are now limited to 3 models per prompt with client-side validation
  * Added visual counter showing "X / Y models selected" with color coding
  * Alert notification when model limit is reached
  * Enhanced user experience with better validation and feedback
* **Bug Fix**: Resolved WordPress object caching issues in settings
  * Fixed systematic value reduction in settings (e.g., 50→44→46, 40→33, 30→27)
  * Added proper cache clearing to ensure fresh values are saved and displayed
  * Improved settings form reliability and data integrity
  * Enhanced error handling and validation
* **Bug Fix**: Fixed email reporter data passing issues
  * Resolved issue where email reports were not being sent due to WordPress action hook parameter limitations
  * Implemented global variable approach to reliably pass current run results to email reporter
  * Email reports now correctly send only results from the current execution (not latest 10 from database)
  * Fixed cross-user data leakage in email reports
  * Enhanced email report reliability and data accuracy
* **Enhancement**: Optimized mobile email report layout and width utilization
  * Increased prompt column width from 25% to 30% for better readability
  * Increased answer column width from 50% to 55% for more content space
  * Reduced meta column width from 25% to 20% to optimize space usage
  * Enhanced mobile responsive design with better column width distribution
  * Improved stacked card layout for screens ≤600px with full width utilization
  * Removed unnecessary labels ("Meta:", "Prompt:", "Answer:") in mobile view for cleaner presentation
  * Increased answer content max-height from 200px to 300px for better readability
  * Better padding and spacing for improved mobile user experience
  * Fine-tuned mobile card width to 90% to ensure full border visibility
  * Aligned left border with other email text for consistent layout
* **New Feature**: Markdown table support in email reports
  * Added comprehensive markdown table to HTML conversion functionality
  * Support for standard markdown table syntax with | separators
  * Automatic detection and skipping of separator rows (|----|)
  * Mobile-responsive HTML tables with horizontal scrolling
  * First row automatically becomes table header with distinct styling
  * Professional styling with borders, shadows, and proper spacing
  * Consistent design integration with existing email report theme

= 0.8.0 - 2025-09-05 =
* **New Feature**: User-specific timezone preferences
  * Added timezone setting in user profile page (/wp-admin/profile.php)
  * All users can set their preferred timezone for date display
  * Dashboard and email reports now show dates in user's local timezone
  * Fallback to site default timezone if user hasn't set preference
  * Support for all PHP timezone identifiers (e.g., Europe/Zurich, America/New_York)
* **Enhancement**: Improved email report rendering and mobile responsiveness
  * Enhanced email report design for better desktop and mobile mail client compatibility
  * Optimized column widths and spacing in email table
  * Improved mobile responsiveness with better breakpoints and horizontal scrolling
  * Fixed vertical alignment issues in email table cells
  * Enhanced markdown to HTML conversion for better content formatting
* **Enhancement**: Better markdown rendering in email reports
  * Improved support for H1, H2, H3, H4 headings including alternative underline formats
  * Enhanced list parsing for various unordered (-, *, +) and ordered (1., 1), 1:) list formats
  * Fixed numbered list display with proper sequential numbering (1, 2, 3, 4 instead of 1, 1, 1, 1)
  * Manual numbering implementation to ensure consistent display across email clients
  * Better handling of markdown content from different LLM providers

= 0.7.0 - 2025-09-04 =
* **New Feature**: Dual-tier user role system
  * Added "LLM Manager Pro" role with higher usage limits
  * Renamed existing role to "LLM Manager Free" with basic limits
  * Automatic migration of existing users from old role to new Free role
  * Role management interface in settings for upgrading/downgrading users
* **New Feature**: Configurable usage limits
  * Free plan: 3 prompts max, 3 models per prompt, 30 runs per month
  * Pro plan: 10 prompts max, 6 models per prompt, 300 runs per month
  * All limits configurable via Settings → LLM Visibility Monitor
  * Real-time usage tracking and enforcement
* **New Feature**: Usage monitoring and display
  * Usage summary display on prompts page showing current vs. limits
  * Color-coded warnings when approaching or exceeding limits
  * Monthly run tracking with automatic reset
  * Prompt and model count tracking per user
* **New Feature**: Enhanced run confirmation system
  * JavaScript popups for "Run All Prompts Now" and individual "Run Now" buttons
  * Credit usage calculation and display before execution
  * Different confirmation messages for admin vs. regular users
  * Prevention of runs that would exceed monthly limits
* **Enhancement**: Improved German localization
  * Complete translation coverage for all new features
  * Proper formality handling (informal "Du" vs. formal "Sie")
  * Fixed untranslated strings in usage summary
  * Updated model selection placeholder text
* **Enhancement**: Enhanced settings interface
  * Configurable limits section in settings
  * User role management with upgrade/downgrade actions
  * Generic role descriptions instead of hardcoded limits
  * Improved admin interface organization

= 0.6.0 - 2025-09-04 =
* **New Feature**: Multi-model selection for prompts
  * Users can now select multiple AI models for each individual prompt
  * Searchable multi-select dropdown with real-time filtering
  * All selected models are executed when running prompts (cron, "Run Now", individual execution)
  * Enhanced prompt management interface with improved model selection UX
  * Backward compatibility maintained for existing single-model prompts
* **Enhancement**: Improved model selection interface
  * Custom dropdown with search functionality
  * Visual display of selected models with remove buttons
  * Better handling of model data and form submission
  * Fixed issues with model saving and display

= 0.5.0 - 2025-09-02 =
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

= 0.4.0 - 2025-09-02 =
* **New Feature**: Per-prompt model selection
  * Users can now specify a different OpenRouter model for each individual prompt
  * Falls back to global default model if no specific model is selected
  * Prevents duplicate prompts with the same text and model combination
  * Added admin notices for successful operations and warnings

= 0.3.0 - 2025-09-01 =
* **Enhancement**: Improved dashboard table functionality
  * Added column sorting (click column headers to sort)
  * Implemented bulk delete functionality for results
  * Added hover actions for better user experience
  * Fixed cron job scheduling issues
  * Improved logging and removed debug backtraces
  * Enhanced email reports with markdown to HTML conversion
  * Fixed vertical spacing between buttons and table

= 0.2.0 - 2025-08-27 =
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

= 0.1.0 - 2025-08-27 =
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

= 0.8.0 =
Adds user-specific timezone preferences for personalized date display. All users can now set their timezone in their profile page. Enhanced email report rendering with better mobile support and improved markdown formatting.

= 0.7.0 =
Introduces dual-tier user roles (Free/Pro) with configurable usage limits. Existing users auto-migrated to Free plan. Enhanced run confirmation system with usage tracking improves user experience.

= 0.6.0 =
This version introduces multi-model selection for prompts. Users can now select multiple AI models for each prompt, allowing for comprehensive comparison of responses across different models. The interface has been enhanced with a searchable multi-select dropdown for better user experience.

= 0.5.0 =
This version introduces role-based access control and user data isolation. A new "LLM Manager" role will be created automatically. Users will only see their own data, with admins maintaining oversight. Email reports are now personalized per user.

= 0.4.0 =
This version adds per-prompt model selection. Existing prompts will automatically use your global default model, but you can now assign specific models to individual prompts.

= 0.3.0 =
This version fixes cron job scheduling issues and improves the dashboard functionality with sorting and bulk operations.

= 0.2.0 =
This version adds email reporting and improves the dashboard layout with better mobile support.

== License ==

This plugin is licensed under the GPL v2 or later.
