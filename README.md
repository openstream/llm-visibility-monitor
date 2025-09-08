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
  - File: `wp-content/uploads/llm-visibility-monitor/llmvm.log`
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

## Changelog

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
