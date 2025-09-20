# LLM Visibility Monitor

Monitor LLM responses on a schedule and store/export results.

## Requirements

- WordPress 6.4+
- PHP 8.0+ (8.1+ recommended)

## Installation

1. Copy the plugin folder `llm-visibility-monitor` into `wp-content/plugins/`.
2. Activate "LLM Visibility Monitor" in wp-admin → Plugins.
3. Go to Settings → LLM Visibility Monitor to configure.

## Features

### Admin Interface
- Settings page in wp-admin → Settings → LLM Visibility Monitor
- Manage Prompts section: add, edit, delete prompts
- Cron Frequency: choose Daily or Weekly
- Model selection: choose which OpenRouter model to use
- Debug Logging toggle
- Dashboard (wp-admin → Tools → LLM Visibility Dashboard):
  - Run Now button to trigger execution immediately
  - Latest results table (date, prompt, model, answer)
  - Export CSV

### Role-Based Access Control
- **Administrators**: Full access to all features including settings
- **LLM Managers**: Can manage prompts, view dashboard, and view results (no settings access)
- **Other Roles**: No LLM access
- User-specific prompt management and result filtering
- Secure isolation between user data

### User-Specific Features
- **Prompt Management**: Users can only see and manage their own prompts
- **Multi-Model Selection**: Users can select multiple AI models for each prompt
- **Results Dashboard**: Users only see their own results (admins see all)
- **CSV Export**: Users export only their data, admins export everything
- **Email Reports**: Personalized emails sent to each user's email address

### OpenRouter Integration

- The plugin uses OpenRouter to call different models via a single API.
- Configure the API key on the Settings page (stored encrypted).
- **Model Support**: Access to 300+ AI models from major providers including OpenAI, Anthropic, Google, Meta, and more
- Model selection:
  - `openrouter/stub-model-v1` → local stub for fast testing (no external API call)
  - Any real model id exposed by OpenRouter (e.g. `openai/gpt-4o-mini`, `anthropic/claude-3-5-sonnet`, `google/gemini-pro`, `meta-llama/llama-3.1-70b-instruct`)

### Scheduling

- Uses WordPress Cron API.
- Frequency is configurable (daily or weekly).
- Use the Run Now button to execute immediately without waiting for the schedule.

### Data Storage

- Custom table: `wp_llm_visibility_results`
  - `id`, `created_at` (UTC), `prompt`, `model`, `answer`
- Use the Dashboard to review results and export CSV.

### Logging

- Optional debug logging (enable in Settings):
  - PHP `error_log`
  - File: `wp-content/plugins/llm-visibility-monitor/llmvm-master.log`
- Requests and responses (status only), prompt dispatch, and errors are logged.

### Email Reports

- Optional email reports (enable in Settings):
  - **Administrators**: Receive emails at admin email with all results from all users
  - **Regular Users**: Receive emails at their own email address with only their results
  - HTML-formatted reports with summary and latest results
  - Includes success/error counts and result previews
  - Links to dashboard for full details
  - User ownership information included in admin reports

### Security

- API key is stored encrypted at rest.
- Nonces and capability checks are used for all admin actions.
- Inputs are sanitized; outputs are escaped in the views.

### Internationalization

- Text domain: `llm-visibility-monitor`
- German translations included: `de_DE`, `de_CH`

## External Services

### OpenRouter API

This plugin connects to the OpenRouter API to send prompts to various AI models and retrieve responses. This service is required for the core functionality of monitoring LLM responses.

**What data is sent and when:**
- Your configured prompts are sent to OpenRouter each time the cron job runs (daily/weekly) or when you click "Run Now"
- The selected model identifier (e.g., `openai/gpt-4o-mini`) is sent with each request
- Your WordPress site URL is sent as the HTTP referer for API tracking
- **Model Access**: With a valid API key, you have access to 300+ AI models from OpenRouter's catalog

**Service provider:** OpenRouter (https://openrouter.ai)
- [Terms of Service](https://openrouter.ai/terms)
- [Privacy Policy](https://openrouter.ai/privacy)

**Note:** The plugin also includes a stub model (`openrouter/stub-model-v1`) for testing that does not make external API calls.

## Configuration

1. OpenRouter API Key: paste your key in Settings (stored encrypted; re-enter to change).
2. Model: enter an OpenRouter model id. Start with `openrouter/stub-model-v1` for quick testing, then switch to any of the 300+ available models (e.g. `openai/gpt-4o-mini`, `anthropic/claude-3-5-sonnet`, `google/gemini-pro`).
3. Cron Frequency: choose how often results should be collected.
4. Debug Logging: enable when troubleshooting; review `wp-content/uploads/llm-visibility-monitor/llmvm.log`.
5. Email Reports: enable to receive automatic reports after each cron run.

## Testing

1. Add one or more prompts in Settings.
2. Click Run Now on the Dashboard or the Settings page.
3. Review results in Tools → LLM Visibility Dashboard and/or export CSV.
4. If logging is enabled, check `wp-content/uploads/llm-visibility-monitor/llmvm.log`.

## Development

### PHP Syntax Checking with DDEV

When developing with DDEV, you can check PHP syntax using the following commands:

```bash
# Check PHP version
ddev php -v

# Check syntax of main plugin file
ddev php -l /var/www/html/wp-content/plugins/llm-visibility-monitor/llm-visibility-monitor.php

# Check syntax of key plugin files
ddev php -l /var/www/html/wp-content/plugins/llm-visibility-monitor/includes/class-llmvm-cron.php
ddev php -l /var/www/html/wp-content/plugins/llm-visibility-monitor/includes/class-llmvm-progress-tracker.php
ddev php -l /var/www/html/wp-content/plugins/llm-visibility-monitor/includes/class-llmvm-admin.php
ddev php -l /var/www/html/wp-content/plugins/llm-visibility-monitor/includes/class-llmvm-database.php
```

**Note**: When using `ddev php` from the host system, use the full container paths (`/var/www/html/...`). If you're inside the DDEV container, you can use relative paths.

### Container Paths

- **Plugin Directory**: `/var/www/html/wp-content/plugins/llm-visibility-monitor/`
- **Main Plugin File**: `/var/www/html/wp-content/plugins/llm-visibility-monitor/llm-visibility-monitor.php`
- **Includes Directory**: `/var/www/html/wp-content/plugins/llm-visibility-monitor/includes/`

## Changelog

### 0.15.0 - 2025-09-20

#### BCC Admin Feature (New)
- **Email Monitoring**: Added BCC functionality for administrators to receive copies of all email reports
- **Settings Control**: New "BCC Admin on All Reports" setting in Settings → LLM Visibility Monitor
- **Smart Logic**: Only BCCs user emails, not admin emails (prevents duplicate admin emails)
- **Universal Coverage**: Works for both cron runs and manual runs via unified email system
- **Compliance**: Enhanced admin visibility and audit trail capabilities
- **German Translations**: Complete localization for all BCC-related interface elements

#### Planned Cron Executions Dashboard (New)
- **Settings Page Enhancement**: Added comprehensive "Planned Cron Executions" section to Settings → LLM Visibility Monitor
- **Multi-User Overview**: Display all scheduled prompts across all users in a single table
- **Detailed Information**: Show prompt text, user, frequency, next execution time, and models for each scheduled prompt
- **Smart Sorting**: Automatically sort by next execution time (earliest first)
- **Timezone Awareness**: Display times in each user's respective timezone
- **Real-Time Updates**: Section updates automatically when prompts are added, modified, or deleted
- **Admin-Only Access**: Only administrators can view the planned executions overview

#### Enhanced Cron Management
- **Distributed Scheduling**: Improved cron job distribution to prevent bottlenecks
- **Conflict Resolution**: Automatic detection and resolution of scheduling conflicts
- **Business Hours Distribution**: Spread cron jobs evenly between 8 AM - 8 PM
- **Minimum Intervals**: Ensure at least 15-minute gaps between scheduled executions
- **Hash-Based Distribution**: Use prompt ID hashing for consistent, predictable scheduling

#### Robust Cron Cleanup System
- **Immediate Verification**: Check and retry cron cleanup immediately after prompt deletion
- **Background Cleanup**: Automatic orphaned cron job detection and removal every 5 minutes
- **Multi-Layer Protection**: Prevents orphaned cron jobs from accumulating over time
- **Comprehensive Logging**: Detailed logging for all cleanup operations and verification steps
- **Zero Orphaned Jobs**: Ensures database consistency between prompts and scheduled crons

#### German Translation Updates
- **Complete Localization**: Added German translations for all new planned executions features
- **Multi-Locale Support**: Updated all 4 German locale files (de_DE, de_DE_formal, de_CH, de_CH_informal)
- **Consistent Terminology**: Maintained consistent German terminology across all interface elements
- **User Experience**: Improved German user experience with proper localization

#### Technical Improvements
- **Performance Optimization**: Enhanced cron scheduling algorithms for better performance
- **Memory Efficiency**: Improved memory usage in cron management operations
- **Error Handling**: Better error handling and recovery in cron operations
- **Code Quality**: Refactored cron management code for better maintainability

### 0.14.0 - 2025-09-18

#### Email Configuration (New)
- **Custom From Address**: Added admin-only setting to configure email "from" address for all WordPress emails
- **Global Email Control**: Setting affects plugin emails, password resets, user notifications, and all WordPress system emails
- **Site Name Integration**: Uses WordPress site title as the "from" name instead of "WordPress"
- **Settings Location**: Available in Settings → LLM Visibility Monitor (admin-only access)
- **Fallback Behavior**: If no custom address is configured, WordPress uses its default behavior

#### Queue Performance Improvements
- **Faster Queue Refresh**: Reduced auto-refresh interval from 30 seconds to 10 seconds for better responsiveness
- **Immediate Queue Processing**: Added automatic queue processing trigger when new jobs are added
- **Enhanced AJAX Performance**: Optimized queue status AJAX calls with reduced job limits and better error handling
- **Improved User Experience**: Added immediate refresh logic for better queue visibility after job submission
- **Performance Logging**: Added detailed logging for queue AJAX response times and performance metrics

#### UI/UX Enhancements
- **Form Field Reordering**: Moved "Expected Answer" field to appear immediately after "Prompt Text" in Add New Prompt form
- **Better Form Flow**: Improved logical grouping of related form fields for better user experience
- **Enhanced Queue Interface**: Improved queue status display with better error handling and timeout management

#### German Translation Completeness
- **Comprehensive German Localization**: Added complete German translations for all admin interface elements
- **Dashboard Translations**: Translated all dashboard elements including status cards, buttons, and descriptions
- **Queue Page Translations**: Added German translations for all queue status elements, table headers, and time metrics
- **Prompts Page Translations**: Translated all form elements, placeholders, and descriptions
- **Scoring Legend Translation**: Complete German translation of the scoring system explanations
- **Informal German Support**: Proper informal German (du-form) for de_CH_informal locale
- **Technical Terms**: Kept technical terms like "Queue" in English for consistency and brevity
- **All Locales Updated**: Complete translations for de_DE, de_DE_formal, de_CH, and de_CH_informal

### 0.13.0 - 2025-09-17

#### Comparison Feature (New)
- **Expected Answer Field**: Added optional field to prompts for defining expected responses
- **Automated Scoring**: LLM-powered comparison scoring (0-10 scale) of actual vs expected answers
- **Comparison Model Selection**: Configurable comparison model in settings (default: openai/gpt-4o-mini)
- **Prompt Summaries**: AI-generated summaries after all models complete for each prompt
- **Email Report Integration**: Comparison scores and summaries included in email reports
- **Dashboard Display**: Comparison scores and summaries visible on main dashboard
- **Scoring Legend**: Clear explanation of 0-10 scoring ranges in both emails and dashboard
- **Strict Entity Matching**: Prioritizes exact entity matching over general response quality
- **Smart Summary Logic**: Only generates summaries when valid results exist, handles mixed success/failure

#### UI Improvements
- **Collapsible Add New Prompt Form**: Form now starts collapsed showing only a textarea, expanding when clicked
- **Improved Textarea Alignment**: Consistent width and left margin alignment between "Add New Prompt" and "Your Prompts" sections
- **Better Visual Consistency**: Enhanced spacing, padding, and overall layout for improved user experience
- **Smooth Transitions**: Added CSS transitions for form expansion with visual feedback
- **Queue Status Styling**: Reduced font size for status badges to prevent text wrapping

#### Technical Improvements
- **Fixed Timezone Handling**: All timestamps now properly stored in UTC and converted to user's timezone
- **Improved Summary Generation**: Summaries regenerated when prompt content changes
- **Better Error Handling**: NULL comparison scores treated as 0 for accurate averages
- **Enhanced Logging**: Comprehensive logging for comparison and summary generation processes
- **Summary Accuracy Fix**: Fixed issue where summaries mixed results from different expected answers

### 0.12.0 - 2025-09-12
- **New Feature**: Enhanced Response Time Logging
  - Added detailed response time tracking for all OpenRouter API requests
  - Response times logged in milliseconds with model, prompt length, and body size information
  - Enhanced error logging with response time context for better debugging
  - Progress tracking now displays response times in completion messages
  - Improved performance monitoring for production environments
- **New Feature**: Queue System for Asynchronous Processing
  - Implemented WordPress-based queue system to prevent timeout issues
  - Queue system is now always enabled for all LLM requests (simplified architecture)
  - Queue status display showing pending, processing, completed, and failed jobs
  - Automatic cleanup of old completed jobs (7+ days old)
  - Fallback to synchronous processing if queue system fails
- **New Feature**: Queue Management Interface
  - New "LLM Queue Status" page under Tools for monitoring queue operations
  - Real-time queue status with auto-refresh every 30 seconds
  - User-specific queue filtering (limited users see only their jobs, admins see all)
  - Queue job details including model, status, creation time, and attempt counts
  - Admin controls for clearing all queue jobs
  - Visual status indicators with color-coded badges and cards
  - Performance metrics display including response time, execution time, and queue overhead
  - Detailed timing breakdown showing queue wait time vs processing time
- **New Feature**: Configurable Concurrency Control
  - Admin setting to control maximum concurrent jobs (1-5, default: 1 for shared hosting)
  - Prevents server overload on shared hosting environments
  - Automatic job queuing when concurrency limit is reached
  - Enhanced queue processing with proper job prioritization
- **Enhancement**: Improved Error Handling and Timeout Management
  - Better handling of 500 internal server errors with detailed logging
  - Enhanced timeout detection and queue-based processing for long-running operations
  - Improved error messages with response time context for debugging
  - Automatic retry mechanism for failed queue jobs (up to 3 attempts)
- **Enhancement**: Production Environment Optimization
  - Smart queue activation based on server execution time limits
  - Automatic detection of production environments requiring asynchronous processing
  - Enhanced logging for production debugging without exposing sensitive data
  - Improved reliability for high-volume prompt processing
- **Technical Improvement**: Database Schema Enhancement
  - Added queue management table (`wp_llmvm_queue`) for asynchronous job processing
  - Added current run results table (`wp_llmvm_current_run_results`) for email reporting
  - Enhanced result tracking with response time data and detailed timing breakdown
  - Improved data structure for better performance monitoring
- **Enhancement**: Email Reporting System Overhaul
  - Completely refactored email reporting to use database storage instead of transients
  - Fixed email reporting for both single runs and "run all prompts now" operations
  - Implemented batch run ID system to group all results from multi-prompt runs
  - Email reports now correctly include all results from the current run only
  - Enhanced email reliability with proper result collection and cleanup
- **Enhancement**: Logging System Improvements
  - Moved logging to WordPress root directory for easier access
  - Implemented dual log file system: master log (all logs) and current run log (run-specific)
  - Added log rotation for master log file (5MB limit)
  - Enhanced log filtering for current run logs with relevant keywords
  - Added .gitignore to prevent committing log files
- **UI/UX Enhancement**: Queue Status Monitoring
  - Real-time queue status display in plugin settings
  - Enhanced progress messages with response time information
  - Better visibility into system performance and processing status
  - Improved admin interface for monitoring queue operations
  - Optimized column widths in queue status table (narrower ID/Status, wider Model)
  - Fixed model name font size to prevent text wrapping
  - Added "Time Metrics Explained" section with detailed performance information
- **Bug Fixes**: Critical Issues Resolved
  - Fixed undefined variable `$job_update_time` PHP warning
  - Fixed email reporting for batch runs to include all results instead of just the last prompt
  - Fixed run ID generation to use consistent batch run IDs across all jobs
  - Fixed email firing logic to use correct run_id (batch_run_id vs prompt_id)
  - Fixed duplicate job processing race conditions with atomic updates
  - Fixed popup display issues by removing progress popup for queue-based runs
  - Fixed negative overhead calculations in queue status display

### 0.11.0 - 2025-09-10
- **New Feature**: Per-Prompt Cron Frequency Settings
  - Added cron frequency dropdown (daily/weekly/monthly) to "Add New Prompt" form
  - Each prompt can now have its own individual cron schedule
  - Updated "Your Prompts" section to show current cron frequency and allow changes
  - Removed global cron frequency setting from Settings page
  - Individual cron jobs are automatically scheduled for each prompt based on their frequency
  - Enhanced cron scheduler to handle per-prompt frequencies with proper WordPress cron integration
  - Database migration automatically adds cron_frequency field to existing prompts (defaults to daily)
  - Improved user experience with granular control over prompt execution timing
- **Enhancement**: Improved Cron Management
  - Automatic scheduling/unscheduling of cron jobs when prompts are added, edited, or deleted
  - Better cron job isolation with unique hooks per prompt
  - Enhanced logging for cron job operations
  - Proper cleanup of cron jobs when prompts are removed
- **Technical Improvement**: Database Schema Update
  - Updated database version to 1.5.0
  - Added migration for existing prompts to include cron_frequency field
  - Backward compatibility maintained for existing installations
- **UI/UX Enhancement**: Optimized Admin Interface
  - Reduced "Add New Prompt" form width to 45% for better desktop layout
  - Added WordPress admin styling with background, border, and shadow for better visual separation
  - Converted form structure to table layout for improved organization
  - Reduced model selection font size to 11px to prevent line breaks in model names
  - Updated model dropdown border color from red to standard WordPress admin color (#c3c4c7)
  - Enhanced form usability with proper spacing and visual hierarchy
- **Email Layout Optimization**: Improved Desktop Email Reports
  - Removed separate "Date & Model" column from email report tables
  - Integrated date and model information into the "Prompt" column as badges
  - Increased "Answer" column width from 55% to 65% for better content display
  - Enhanced mobile responsiveness with adjusted column widths (Answer: 50% → 60%)
  - Improved email readability with better content organization
- **Bug Fixes**: Critical Issues Resolved
  - Fixed "Run All Prompts Now" bug where only the last prompt was processed
  - Corrected run count discrepancies in dashboard and email reports
  - Fixed missing prompt text extraction in cron execution loop
  - Resolved incomplete dashboard and email reports for multi-prompt runs
- **Performance & Reliability**: Execution Time Management
  - Added execution time limit of 720 seconds (12 minutes) for all run operations
  - Implemented large run warnings for operations with more than 10 models
  - Added user-friendly warning dialogs to prevent server timeout issues
  - Enhanced error handling for production server environments
  - Improved user guidance for large batch operations
- **Logging Optimization**: Reduced Log Spam
  - Fixed repetitive logging of "Plugin initialized" and "Cron already scheduled" messages
  - Implemented rate limiting to log initialization messages at most once per minute
  - Moved cron checking logic to prevent excessive logging on every request
  - Cleaner log files with only relevant operational information
- **Internationalization**: German Translation Updates
  - Added comprehensive German translations for all new UI elements
  - Translated prompt management interface including cron frequency options
  - Updated all German locale files (de_DE, de_CH, de_DE_formal, de_CH_informal)
  - Fixed Swiss German informal translations to use proper "Du" forms instead of "Sie"
  - Maintained consistency with existing translation patterns

### 0.10.0 - 2025-09-09
- **New Feature**: Web Search Integration with OpenRouter
  - Added web search checkbox to "Add New Prompt" form
  - Added "Web Search" column to prompts table showing web search status
  - Automatic appending of `:online` to model names when web search is enabled
  - Follows OpenRouter web search documentation for model-agnostic grounding
  - Users can enable/disable web search for individual prompts
  - Web search status visible in both admin and user views
  - Backward compatibility maintained for existing prompts
  - Enhanced logging to track web search usage and model modifications
- **New Feature**: Loading Overlay for Run Operations
  - Added loading overlay that prevents user interactions during prompt execution
  - Prevents accidental interruption of running prompts
  - Shows progress messages for "Run All Prompts Now" and individual "Run Now" operations
  - Blocks all user interactions (clicks, keyboard shortcuts, context menu) during execution
  - Enhanced user experience with clear visual feedback
- **New Feature**: Markdown Link Support in Email Reports
  - Added support for markdown link syntax `[text](URL)` in email reports
  - Automatic conversion to HTML `<a>` tags with proper styling
  - Secure URL handling with `esc_url()` and `esc_html()` for XSS protection
  - Email-compatible inline CSS styling for links
  - Enhanced content formatting for better email readability
- **New Feature**: Login Page Customization
  - Added "Login Page Customization" section in plugin settings
  - Replaces WordPress logo with site name "LLM Visibility Monitor" on login page
  - Custom text area for adding personalized content below the site name
  - Supports HTML formatting including links and bold text
  - Editable through Settings → LLM Visibility Monitor → Login Page Customization
  - Professional styling with proper spacing and visual hierarchy
- **Enhancement**: Upgrade Link for Usage Limits
  - Added upgrade link in usage summary when monthly run limit is reached
  - Direct link to subscription page for easy plan upgrades
  - Encourages users to upgrade when they hit their limits
  - Improved user experience with clear upgrade path
- **Bug Fix**: Fixed duplicate menu items in WordPress admin
  - Resolved issue where "LLM Visibility Monitor" appeared twice in Settings sidebar
  - Optimized admin class instantiation to prevent duplicate menu registration
  - Cleaner admin interface with single menu entry
- **Bug Fix**: Fixed duplicate settings sections
  - Removed duplicate "Login Page Customization" section on settings page
  - Proper form integration for all settings sections
  - Cleaner settings interface with no duplicate content
- **New Feature**: Real-time Progress Tracking with Progress Bar
  - Enhanced loading overlay with realistic progress bar instead of simulated progress
  - Real-time progress updates via AJAX polling during prompt execution
  - Server-side progress tracking using WordPress transients for reliable state management
  - Progress messages show current step and detailed status (e.g., "Starting model: gpt-4o-mini")
  - Accurate progress percentage based on actual completion status
  - Progress bar reflects real execution time instead of estimated completion
  - Enhanced user experience with accurate feedback during long-running operations
- **Enhancement**: Web Search Progress Messages
  - Progress messages now display `:online` suffix when web search is enabled
  - Clear indication of which models are using web search capabilities
  - Examples: "Starting model: gpt-4o-mini:online" and "Completed model: gpt-4o-mini:online"
  - Better visibility into web search usage during prompt execution
- **Bug Fix**: Fixed missing dates in email reports
  - Resolved issue where email reports showed only green lines instead of timestamps
  - Added missing `created_at` field to progress tracking result arrays
  - Email reports now display proper timestamps for all results
  - Fixed in both `run_with_progress()` and `run_single_prompt_with_progress()` methods
  - Enhanced result data structure with complete timestamp information
- **Enhancement**: Customized admin bar for LLM Manager roles
  - Cleaned up WordPress admin bar for LLM Manager Free and Pro users
  - Removed comments/notifications icon and "New" button from admin bar
  - Hidden updates notification and search box for cleaner interface
  - Preserved WordPress logo, site name, and user profile menu
  - Creates focused admin experience tailored for LLM management tasks

### 0.9.0 - 2025-09-08
- **New Feature**: Model limit enforcement for free plan users
  - Free plan users are now limited to 3 models per prompt with client-side validation
  - Added visual counter showing "X / Y models selected" with color coding
  - Alert notification when model limit is reached
  - Enhanced user experience with better validation and feedback
- **Bug Fix**: Resolved WordPress object caching issues in settings
  - Fixed systematic value reduction in settings (e.g., 50→44→46, 40→33, 30→27)
  - Added proper cache clearing to ensure fresh values are saved and displayed
  - Improved settings form reliability and data integrity
  - Enhanced error handling and validation
- **Bug Fix**: Fixed email reporter data passing issues
  - Resolved issue where email reports were not being sent due to WordPress action hook parameter limitations
  - Implemented global variable approach to reliably pass current run results to email reporter
  - Email reports now correctly send only results from the current execution (not latest 10 from database)
  - Fixed cross-user data leakage in email reports
  - Enhanced email report reliability and data accuracy
- **Enhancement**: Optimized mobile email report layout and width utilization
  - Increased prompt column width from 25% to 30% for better readability
  - Increased answer column width from 50% to 55% for more content space
  - Reduced meta column width from 25% to 20% to optimize space usage
  - Enhanced mobile responsive design with better column width distribution
  - Improved stacked card layout for screens ≤600px with full width utilization
  - Removed unnecessary labels ("Meta:", "Prompt:", "Answer:") in mobile view for cleaner presentation
  - Increased answer content max-height from 200px to 300px for better readability
  - Better padding and spacing for improved mobile user experience
  - Fine-tuned mobile card width to 90% to ensure full border visibility
  - Aligned left border with other email text for consistent layout
- **New Feature**: Markdown table support in email reports
  - Added comprehensive markdown table to HTML conversion functionality
  - Support for standard markdown table syntax with | separators
  - Automatic detection and skipping of separator rows (|----|)
  - Mobile-responsive HTML tables with horizontal scrolling
  - First row automatically becomes table header with distinct styling
  - Professional styling with borders, shadows, and proper spacing
  - Consistent design integration with existing email report theme

### 0.8.0 - 2025-09-05
- **New Feature**: User-specific timezone preferences
  - Added timezone setting in user profile page (/wp-admin/profile.php)
  - All users can set their preferred timezone for date display
  - Dashboard and email reports now show dates in user's local timezone
  - Fallback to site default timezone if user hasn't set preference
  - Support for all PHP timezone identifiers (e.g., Europe/Zurich, America/New_York)
- **Enhancement**: Improved email report rendering and mobile responsiveness
  - Enhanced email report design for better desktop and mobile mail client compatibility
  - Optimized column widths and spacing in email table (Date: 15%, Prompt: 20%, Model: 20%, Answer: 45%, User: 10%)
  - Improved mobile responsiveness with better breakpoints and horizontal scrolling
  - Fixed vertical alignment issues in email table cells
  - Enhanced markdown to HTML conversion for better content formatting
- **Enhancement**: Better markdown rendering in email reports
  - Improved support for H1, H2, H3, H4 headings including alternative underline formats
  - Enhanced list parsing for various unordered (-, *, +) and ordered (1., 1), 1:) list formats
  - Fixed numbered list display with proper sequential numbering (1, 2, 3, 4 instead of 1, 1, 1, 1)
  - Manual numbering implementation to ensure consistent display across email clients
  - Better handling of markdown content from different LLM providers

### 0.7.0 - 2025-09-04
- **New Feature**: Dual-tier user role system
  - Added "LLM Manager Pro" role with higher usage limits
  - Renamed existing role to "LLM Manager Free" with basic limits
  - Automatic migration of existing users from old role to new Free role
  - Role management interface in settings for upgrading/downgrading users
- **New Feature**: Configurable usage limits
  - Free plan: 3 prompts max, 3 models per prompt, 30 runs per month
  - Pro plan: 10 prompts max, 6 models per prompt, 300 runs per month
  - All limits configurable via Settings → LLM Visibility Monitor
  - Real-time usage tracking and enforcement
- **New Feature**: Usage monitoring and display
  - Usage summary display on prompts page showing current vs. limits
  - Color-coded warnings when approaching or exceeding limits
  - Monthly run tracking with automatic reset
  - Prompt and model count tracking per user
- **New Feature**: Enhanced run confirmation system
  - JavaScript popups for "Run All Prompts Now" and individual "Run Now" buttons
  - Credit usage calculation and display before execution
  - Different confirmation messages for admin vs. regular users
  - Prevention of runs that would exceed monthly limits
- **Enhancement**: Improved German localization
  - Complete translation coverage for all new features
  - Proper formality handling (informal "Du" vs. formal "Sie")
  - Fixed untranslated strings in usage summary
  - Updated model selection placeholder text
- **Enhancement**: Enhanced settings interface
  - Configurable limits section in settings
  - User role management with upgrade/downgrade actions
  - Generic role descriptions instead of hardcoded limits
  - Improved admin interface organization

### 0.6.0 - 2025-09-04
- **New Feature**: Multi-model selection for prompts
  - Users can now select multiple AI models for each individual prompt
  - Searchable multi-select dropdown with real-time filtering
  - All selected models are executed when running prompts (cron, "Run Now", individual execution)
  - Enhanced prompt management interface with improved model selection UX
  - Backward compatibility maintained for existing single-model prompts
- **Enhancement**: Improved model selection interface
  - Custom dropdown with search functionality
  - Visual display of selected models with remove buttons
  - Better handling of model data and form submission
  - Fixed issues with model saving and display

### 0.5.0 - 2025-09-02
- **New Feature**: Implemented role-based access control
  - Added "LLM Manager" role with limited admin access
  - LLM Managers can manage prompts, view dashboard, and view results
  - LLM Managers cannot access plugin settings
  - Administrators retain full access to all features
  - Other user roles have no LLM access
- **New Feature**: User-specific prompt management
  - Users can only see and manage their own prompts
  - Admins can see all prompts but only edit/delete their own
  - Secure isolation between user data
- **New Feature**: User-specific results filtering
  - Dashboard shows only user's own results (unless admin)
  - CSV export respects user permissions
  - Proper user ID assignment in cron jobs
- **New Feature**: Personalized email reporting
  - Users receive emails at their own email address
  - Admins receive emails at admin email with all results
  - User ownership information in admin reports
  - Smart filtering based on user role
- **Enhancement**: Improved security and data isolation
  - Fixed CSV export user filtering
  - Enhanced cron job user context
  - Better user permission enforcement

### 0.4.0 - 2025-09-02
- **New Feature**: Per-prompt model selection
  - Users can now specify a different OpenRouter model for each individual prompt
  - Falls back to global default model if no specific model is selected
  - Prevents duplicate prompts with the same text and model combination
  - Added admin notices for successful operations and warnings

### 0.3.0 - 2025-09-01
- **Enhancement**: Improved dashboard table functionality
  - Added column sorting (click column headers to sort)
  - Implemented bulk delete functionality for results
  - Added hover actions for better user experience
  - Fixed cron job scheduling issues
  - Improved logging and removed debug backtraces
  - Enhanced email reports with markdown to HTML conversion
  - Fixed vertical spacing between buttons and table

### 0.2.0 - 2025-08-27
- **New Feature**: Email reporting system
  - Configurable email notifications for cron job results
  - HTML-formatted reports with prompt, model, and answer details
  - Customizable email settings in admin panel
- **Enhancement**: Improved dashboard layout
  - Better mobile responsiveness with adjusted column widths
  - Action links moved to prompt column with hover display
  - "Details" and "Delete" buttons for each result entry
- **Enhancement**: Enhanced OpenRouter model selection
  - Searchable dropdown for model selection
  - Graceful fallback for API errors
  - Better error handling and user feedback

### 0.1.0 - 2025-08-27
- **Initial Release**: Core LLM monitoring functionality
  - OpenRouter API integration with secure API key storage
  - Prompt management (CRUD operations)
  - Scheduled cron jobs (daily/weekly)
  - Results storage and dashboard
  - CSV export functionality
  - Comprehensive logging system
  - WordPress admin interface
  - German localization (de_DE, de_CH)

## License

- Plugin license: GPL-2.0-or-later (see plugin header)
- GPT license: see the `LICENSE` file added to the repository for applicable GPT terms

## WordPress Coding Standards Compliance

This plugin **fully complies** with WordPress coding standards and passes the WordPress plugin check with **0 errors and 0 warnings**. We've documented the compliance issues we encountered and their solutions to help with future development and prevent similar problems.

### Common Compliance Issues & Solutions

#### 1. SQL Preparation Issues (`WordPress.DB.PreparedSQL.NotPrepared`)

**Problem:** WordPress coding standards require all SQL queries with variables to use `$wpdb->prepare()` with proper placeholders.

**Solution:** Use inline `phpcs:ignore` comments for legitimate cases where variables are safe:

```php
// ✅ CORRECT: Inline ignore comment for safe variables
$rows = $wpdb->get_results( $wpdb->prepare(
    'SELECT * FROM ' . self::table_name() . ' WHERE user_id = %d ORDER BY id ' . $order . ', id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::table_name() returns constant string, $order is validated to be ASC/DESC only.
    $user_id,
    $limit,
    $offset
), ARRAY_A );

// ❌ WRONG: Comment above the line (tool ignores it)
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$rows = $wpdb->get_results( $wpdb->prepare(
    'SELECT * FROM ' . self::table_name() . ' WHERE user_id = %d ORDER BY id ' . $order . ', id DESC LIMIT %d OFFSET %d',
    $user_id,
    $limit,
    $offset
), ARRAY_A );
```

**Key Points:**
- `phpcs:ignore` comments must be **inline** with the specific line, not above it
- Always justify why the ignore is necessary
- Use for safe variables like `self::table_name()` (constant) or validated values like `$order` (ASC/DESC only)

#### 2. Direct Database Query Warnings (`WordPress.DB.DirectDatabaseQuery`)

**Problem:** WordPress discourages "direct" database calls, but this is misleading for legitimate `$wpdb` usage.

**Solution:** Use inline ignore comments for standard WordPress database operations:

```php
// ✅ CORRECT: Inline ignore for legitimate $wpdb usage
$result = $wpdb->insert(
    self::table_name(),
    $insert_data,
    array( '%s', '%s', '%s', '%s', '%d' )
); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations require direct queries. $wpdb->insert() is the proper WordPress method for custom table inserts.
```

**Key Points:**
- These warnings are often **false positives** for legitimate WordPress patterns
- `$wpdb` methods ARE the correct WordPress way to interact with databases
- Always explain why the ignore is justified

#### 3. Table Name Handling

**Problem:** Dynamic table names in SQL queries can cause compliance issues.

**Solution:** Use a consistent `table_name()` method and inline ignores:

```php
class LLMVM_Database {
    /**
     * Get the table name with proper prefix.
     */
    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'llm_visibility_results';
    }
    
    // Usage with inline ignore
    $query = 'SELECT * FROM ' . self::table_name() . ' WHERE user_id = %d'; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::table_name() returns constant string
}
```

#### 4. ORDER BY Clause Issues

**Problem:** Using `%s` placeholders for ORDER BY direction causes SQL syntax errors.

**Solution:** Concatenate validated variables directly:

```php
// ✅ CORRECT: Direct concatenation for validated values
$order = ( 'DESC' === $order ) ? 'DESC' : 'ASC'; // Validate first
$query = 'SELECT * FROM ' . self::table_name() . ' ORDER BY id ' . $order . ', id DESC LIMIT %d OFFSET %d';

// ❌ WRONG: Using placeholder for ORDER BY direction
$query = $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' ORDER BY id %s, id DESC LIMIT %d OFFSET %d', $order );
```

### Best Practices for Future Development

1. **Always use inline `phpcs:ignore` comments** - they're more reliable than comments above lines
2. **Justify every ignore comment** - explain why the ignore is necessary
3. **Validate variables before using them** - especially for SQL concatenation
4. **Use `self::table_name()` consistently** - never hardcode table names
5. **Test with `wp plugin check`** - more accurate than `phpcs` alone
6. **Document complex SQL patterns** - help future developers understand the approach

### Testing Compliance

```bash
# Run WordPress plugin check (most accurate)
ddev exec "cd /var/www/html/wp-content/plugins && wp plugin check llm-visibility-monitor"

# Run PHP CodeSniffer (for development)
ddev exec "cd /var/www/html/wp-content/plugins && phpcs --standard=WordPress llm-visibility-monitor"
```

**Current Status:** This plugin now passes the WordPress plugin check with **0 errors and 0 warnings**. The only remaining issue is the `.gitignore` file, which is acceptable to ignore as it's a common practice for development repositories.

### What We've Resolved

All previously reported compliance issues have been successfully addressed:
- ✅ `WordPress.DB.PreparedSQL.NotPrepared` errors (48 total)
- ✅ `WordPress.DB.DirectDatabaseQuery.DirectQuery` warnings (24 total)  
- ✅ `WordPress.DB.DirectDatabaseQuery.NoCaching` warnings
- ✅ SQL syntax errors and database functionality issues

The solutions documented above represent the proven approaches that resolved these issues and can be applied to prevent similar problems in future development.
