=== LLM Visibility Monitor ===
Contributors: openstream
Tags: llm, monitoring, openrouter, ai, automation
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Monitor LLM responses on a schedule and store/export results.

== Description ==

LLM Visibility Monitor is a WordPress plugin that allows you to monitor and track responses from Large Language Models (LLMs) on a scheduled basis. It integrates with OpenRouter to send prompts to various AI models and stores the results for analysis and export.

= Key Features =

* **Scheduled Monitoring**: Automatically send prompts to LLMs on a daily or weekly schedule
* **OpenRouter Integration**: Support for hundreds of AI models through OpenRouter API
* **Result Storage**: Store all responses in a custom database table
* **Export Functionality**: Export results as CSV for further analysis
* **Admin Dashboard**: View latest results and manage prompts
* **Secure API Key Storage**: Encrypted storage of your OpenRouter API key
* **Email Reports**: Automatic HTML email reports sent to admin after each run
* **German Translations**: Full localization support for German locales

= Use Cases =

* Monitor AI model performance over time
* Track responses to specific prompts
* Analyze AI behavior patterns
* Export data for reporting and analysis
* Test different AI models and compare results

== External Services ==

This plugin connects to the OpenRouter API to send prompts to various AI models and retrieve responses. This service is required for the core functionality of monitoring LLM responses.

**What data is sent and when:**
- Your configured prompts are sent to OpenRouter each time the cron job runs (daily/weekly) or when you click "Run Now"
- The selected model identifier (e.g., openai/gpt-4o-mini) is sent with each request
- Your WordPress site URL is sent as the HTTP referer for API tracking

**Service provider:** OpenRouter (https://openrouter.ai)
- Terms of Service: https://openrouter.ai/terms
- Privacy Policy: https://openrouter.ai/privacy

**Note:** The plugin also includes a stub model (openrouter/stub-model-v1) for testing that does not make external API calls.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/llm-visibility-monitor` directory, or install through WordPress admin
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → LLM Visibility Monitor to configure your OpenRouter API key and prompts

== Frequently Asked Questions ==

= What is OpenRouter? =

OpenRouter is a service that provides access to hundreds of AI models through a single API. You can use models from OpenAI, Anthropic, Google, and many other providers.

= Do I need an OpenRouter account? =

Yes, you'll need to sign up at [openrouter.ai](https://openrouter.ai) and get an API key to use this plugin.

= Can I test without an API key? =

Yes! The plugin includes a stub model (`openrouter/stub-model-v1`) that works without an API key for testing purposes.

= How often can I run the monitoring? =

You can configure the plugin to run daily or weekly, or use the "Run Now" button to execute immediately.

= Is my API key stored securely? =

Yes, the API key is encrypted using WordPress's built-in encryption functions and stored securely in the database.

== Screenshots ==

1. Settings page with API key configuration and prompt management
2. Dashboard showing latest LLM responses
3. CSV export functionality
4. Model selection dropdown with search capability

== Changelog ==

= 0.4.0 (2025-09-02) =
* **New Feature**: Per-prompt model selection - each prompt can now use a different AI model
* **Enhanced Flexibility**: Choose specific models for different types of prompts
* **Improved UI**: Model selection dropdowns in prompt management interface
* **Backward Compatibility**: Existing prompts automatically use the default model
* **Performance**: Individual model validation and API key checking per prompt

= 0.3.0 =
* **Fixed cron scheduling issues**: Cron jobs now run at proper intervals (9:00 AM daily/weekly) instead of every minute
* **Improved logging**: Eliminated duplicate log entries and excessive logging with deduplication logic
* **Added OpenRouter models caching**: 1-hour cache to reduce API calls and improve settings page performance
* **Enhanced bulk operations**: Fixed bulk delete functionality and resolved UI conflicts
* **Improved dashboard UI**: Better spacing, mobile responsiveness, and user experience
* **WordPress coding standards compliance**: Updated file operations, nonce handling, and translation loading
* **Performance optimizations**: Reduced unnecessary API calls and improved overall efficiency

= 0.2.0 (2025-08-27) =
* Enhanced model selection with dropdown interface
* Improved dashboard layout with action buttons
* Better API key handling and error recovery
* Email reporting functionality with HTML reports
* Comprehensive German translations
* WordPress coding standards compliance
* PHP 8.1 compatibility fixes

= 0.1.0 (2025-08-27) =
* Initial release
* Basic OpenRouter integration
* Scheduled monitoring functionality
* Result storage and export
* Admin interface

== Upgrade Notice ==

= 0.4.0 (2025-09-02) =
This version introduces per-prompt model selection, allowing you to choose different AI models for different types of prompts. Existing prompts will automatically use your default model, and you can customize the model for each prompt individually.

= 0.3.0 =
This version fixes critical cron scheduling issues and significantly improves performance. Cron jobs now run at proper intervals instead of every minute, and logging has been optimized to eliminate duplicate entries. The settings page is now much faster due to OpenRouter models caching.

= 0.2.0 =
This version includes significant improvements to the model selection interface and better error handling. The dashboard has been redesigned for improved usability.
