
# PHP-WebHook

A modern PHP-based webhook endpoint and log viewer with a beautiful dashboard.

## Features

- **Webhook Endpoint**: Accepts and logs incoming HTTP requests (GET, POST, PUT, DELETE, PATCH, etc.).
- **Comprehensive Logging**: Captures headers, cookies, query params, form data, files, JSON, XML, raw body, and more.
- **Daily Log Files**: Stores requests in daily JSON files under `src/log/`.
- **Admin Dashboard**: Secure login to view, search, filter, and delete logs via a responsive Tailwind CSS/Alpine.js dashboard.
- **AJAX Actions**: Delete log files or individual requests without page reloads.
- **Statistics**: View request method and content type stats per log file.
- **Beautiful UI**: Neon-glass styled dashboard, mobile-friendly, with custom scrollbars and icons.
- **Assets**: Includes `banner.png`, `logo.png`, and `panel.png` in the `asset/` folder.

## Usage

1. **Deploy** the contents of `src/` to your PHP server.
2. **Send webhooks** to `index.php` (root of `src/`). All requests are logged.
3. **View logs**: Go to `/src/index.php?view_logs` and log in with:
	 - Username: `admin`
	 - Password: `xyz`
4. **Dashboard**: Browse, search, filter, and delete logs. View request details, payloads, headers, and cookies.

## File Structure

```
asset/
	banner.png
	logo.png
	panel.png
src/
	index.php
	.htaccess
	log/
		YYYY-MM-DD.json
test/
	simple_test.py
	test.py
LICENSE
README.md
```

## Security

- Simple session-based login for admin dashboard.
- Webhook endpoint is open for receiving requests.

## Credits

Designed & Developed by [AhmadYousuf](https://0xAhmadYousufcom)