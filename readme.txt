=== LLM Visibility Monitor ===
Contributors: openstream
Tags: llm, monitoring, openrouter, ai, automation
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.2.0
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
* **German Translations**: Full localization support for German locales

= Use Cases =

* Monitor AI model performance over time
* Track responses to specific prompts
* Analyze AI behavior patterns
* Export data for reporting and analysis
* Test different AI models and compare results

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

= 0.2.0 =
* Enhanced model selection with dropdown interface
* Improved dashboard layout with action buttons
* Better API key handling and error recovery
* Comprehensive German translations
* WordPress coding standards compliance
* PHP 8.1 compatibility fixes

= 0.1.0 =
* Initial release
* Basic OpenRouter integration
* Scheduled monitoring functionality
* Result storage and export
* Admin interface

== Upgrade Notice ==

= 0.2.0 =
This version includes significant improvements to the model selection interface and better error handling. The dashboard has been redesigned for improved usability.
