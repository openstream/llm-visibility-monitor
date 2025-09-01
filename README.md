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

### OpenRouter Integration

- The plugin uses OpenRouter to call different models via a single API.
- Configure the API key on the Settings page (stored encrypted).
- Model selection:
  - `openrouter/stub-model-v1` → local stub for fast testing (no external API call)
  - Any real model id exposed by OpenRouter (e.g. `openai/gpt-4o-mini`, `openai/gpt-4.1`, or `openai/gpt-5` when available)

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
  - Automatically sent to WordPress admin email after each cron run
  - HTML-formatted reports with summary and latest results
  - Includes success/error counts and result previews
  - Links to dashboard for full details

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

**Service provider:** OpenRouter (https://openrouter.ai)
- [Terms of Service](https://openrouter.ai/terms)
- [Privacy Policy](https://openrouter.ai/privacy)

**Note:** The plugin also includes a stub model (`openrouter/stub-model-v1`) for testing that does not make external API calls.

## Configuration

1. OpenRouter API Key: paste your key in Settings (stored encrypted; re-enter to change).
2. Model: enter an OpenRouter model id. Start with `openrouter/stub-model-v1` for quick testing, then switch to a real model (e.g. `openai/gpt-4o-mini`, `openai/gpt-5` when available).
3. Cron Frequency: choose how often results should be collected.
4. Debug Logging: enable when troubleshooting; review `wp-content/uploads/llm-visibility-monitor/llmvm.log`.
5. Email Reports: enable to receive automatic reports after each cron run.

## Testing

1. Add one or more prompts in Settings.
2. Click Run Now on the Dashboard or the Settings page.
3. Review results in Tools → LLM Visibility Dashboard and/or export CSV.
4. If logging is enabled, check `wp-content/uploads/llm-visibility-monitor/llmvm.log`.

## Changelog

### 0.3.0 (2025-09-01)
- **Fixed cron scheduling issues**: Cron jobs now run at proper intervals (9:00 AM daily/weekly) instead of every minute
- **Improved logging**: Eliminated duplicate log entries and excessive logging with deduplication logic
- **Added OpenRouter models caching**: 1-hour cache to reduce API calls and improve settings page performance
- **Enhanced bulk operations**: Fixed bulk delete functionality and resolved UI conflicts
- **Improved dashboard UI**: Better spacing, mobile responsiveness, and user experience
- **WordPress coding standards compliance**: Updated file operations, nonce handling, and translation loading
- **Performance optimizations**: Reduced unnecessary API calls and improved overall efficiency

### 0.2.0 (2025-08-27)
- Added email reports feature with HTML formatting
- Implemented dashboard with sorting and bulk operations
- Added German translations (de_DE, de_CH)
- Enhanced security with proper nonce verification
- Improved error handling and user feedback

### 0.1.0 (2025-08-27)
- Initial release with core LLM monitoring functionality
- OpenRouter API integration
- WordPress cron scheduling
- CSV export capabilities
- Basic admin interface

## License

- Plugin license: GPL-2.0-or-later (see plugin header)
- GPT license: see the `LICENSE` file added to the repository for applicable GPT terms
