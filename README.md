# LLM Visibility Monitor

Monitor LLM responses on a schedule and store/export results.

## Requirements

- WordPress 6.4+
- PHP 8.0+ (8.1+ recommended)

## Installation

1. Copy the plugin folder `llm-visibility-monitor` into `wp-content/plugins/`.
2. Activate “LLM Visibility Monitor” in wp-admin → Plugins.
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
  - File: `wp-content/uploads/llmvm-logs/llmvm.log`
- Requests and responses (status only), prompt dispatch, and errors are logged.

### Security

- API key is stored encrypted at rest.
- Nonces and capability checks are used for all admin actions.
- Inputs are sanitized; outputs are escaped in the views.

### Internationalization

- Text domain: `llm-visibility-monitor`
- German translations included: `de_DE`, `de_CH`

## Configuration

1. OpenRouter API Key: paste your key in Settings (stored encrypted; re-enter to change).
2. Model: enter an OpenRouter model id. Start with `openrouter/stub-model-v1` for quick testing, then switch to a real model (e.g. `openai/gpt-4o-mini`, `openai/gpt-5` when available).
3. Cron Frequency: choose how often results should be collected.
4. Debug Logging: enable when troubleshooting; review `wp-content/uploads/llmvm-logs/llmvm.log`.

## Testing

1. Add one or more prompts in Settings.
2. Click Run Now on the Dashboard or the Settings page.
3. Review results in Tools → LLM Visibility Dashboard and/or export CSV.
4. If logging is enabled, check `wp-content/uploads/llmvm-logs/llmvm.log`.

## Roadmap

- Real-time notifications and webhooks
- Per-prompt model overrides
- Pagination and filters for results

## License

- Plugin license: GPL-2.0-or-later (see plugin header)
- GPT license: see the `LICENSE` file added to the repository for applicable GPT terms

