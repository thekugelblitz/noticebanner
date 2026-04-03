<?php
/**
 * Optional license API URL override (fixes Cloudflare blocking server-to-server POSTs).
 *
 * INSTRUCTIONS:
 * 1. Copy this file to: noticebanner-license-url.php (same folder as noticebanner.php)
 * 2. Uncomment and set the URL below.
 *
 * Use a hostname that does NOT go through Cloudflare’s orange-cloud proxy (DNS only / grey cloud),
 * or a path you have excluded from Bot Fight / JS challenge in Cloudflare.
 *
 * Example: lic-origin.yourdomain.com → A record to your server IP, proxy OFF in Cloudflare.
 *
 * HostingSpell default API host (grey cloud): whmcsapi.hostingspell.com — already set in license.php;
 * only use this override file if you host the API elsewhere.
 */

// define('NB_LICENSE_API_URL', 'https://whmcsapi.hostingspell.com/api/validate.php');
