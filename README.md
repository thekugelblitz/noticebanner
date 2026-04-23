# Notice Banner (WHMCS)

**Version:** 3.1.0  
**Author:** Dhruv from HostingSpell

Display **admin and client** notices as **banners** with **markdown-style** content, **polls**, **@mentions**, **assignments**, **scheduling**, **webhooks**, **to-do / promo** workflows, a **dashboard widget**, and more.

## Requirements

- A supported **WHMCS** release and a matching **PHP** version (follow WHMCS’s own requirements).
- **MySQL** or **MariaDB** (the module uses its own `mod_*` tables).
- **Outbound HTTPS (port 443)** from your server to the **license API** (see *Licensing*). Firewalls and hosts must allow this.

## Installation

1. Upload the `noticebanner` folder to:

   `modules/addons/noticebanner/`

2. In **Admin → System Settings → Addon Modules**, open **Notice Banner** and **Activate**.  
   Activation creates/updates the module database tables.

3. The module uses **`storage/`** for uploads (e.g. to-do attachments). It is **created automatically** when needed. Ensure the **web user** can write under the module directory.

4. When **active**, WHMCS loads **`hooks.php`** (client and admin area output) and **`widget.php`** (admin home widget).

| File / folder        | Role |
|---------------------|------|
| `noticebanner.php`  | Addon entry, admin UI, handling |
| `hooks.php`         | Hooks for client/admin banners |
| `widget.php`        | Admin dashboard widget |
| `license.php`      | License validation |
| `templates/admin.tpl` | Admin interface template |
| `storage/`         | Created at runtime (uploads) |

## Using the module

- **Admin:** **Addon Modules → Notice Banner** for full configuration.
- **Admin home:** the **Notice Banner** widget (requires **Addon Modules** permission) for a quick list and actions.
- **Client area / admin area:** Notices are shown via hooks, according to each notice’s rules (audience, schedule, page targeting, etc.).

**Optional (module settings):** **Global Webhook URL** — sends JSON to your URL when a notice is created or updated (e.g. Slack, Discord, custom endpoint).

## Free and Pro

The module includes a **Free** tier and a **Pro** license (extra features, higher limits). For pricing, lifetime updates, and how to get a key, use the in-module **License** tab or the official information page: **<https://2hs.in/nbm>**

Enter your license key under **License & Settings** in the Notice Banner admin screen.

## Licensing (technical)

- The module validates the license over **HTTPS**. If your server **cannot** reach the default API (for example, strict **proxy/Cloudflare** rules), you may need to allow the license path, use a **DNS-only** host for the API, or add an optional file **`noticebanner-license-url.php`** in the same folder as `noticebanner.php` and define **`NB_LICENSE_API_URL`** (and, only if your host requires it, **`NB_LICENSE_SSL_VERIFY_PEER`**).  
  Contact support if you need help with this on locked-down servers.

## Support

Use the support channel provided with your purchase or on the product page (e.g. **<https://2hs.in/nbm>** for ordering and product details).
