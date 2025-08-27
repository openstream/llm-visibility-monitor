# LLM Visibility Monitor

## Purpose
This WordPress plugin performs regular automated queries to different LLMs to monitor the visibility of specific content or brands. Administrators can manage their own queries and receive scheduled reports by email.

## Features

### Admin Interface
- Settings page in the WordPress admin area.  
- Manage custom prompts: add, edit, and delete queries.  
- Configure query frequency (daily, weekly) and select target LLMs.  

### API Integration
- Uses **OpenRouter** to connect with multiple LLMs via a single interface.  
- Easily extendable to support additional models in the future.  

### Results Management
- Stores responses and applies a scoring system within the admin dashboard.  
- Sends email reports to administrators based on configured intervals.  

### Technical Requirements
- Compatible with WordPress 6.4 or higher.  
- Requires **PHP 8.0+** (recommended: 8.1 or newer).  
- Uses the WordPress Cron API for scheduled tasks.  
- Secure handling of API keys (encrypted storage).  

### Extensibility
- Hooks and filters for future extensions.  
- Documentation for customizing prompts and scoring logic.  

