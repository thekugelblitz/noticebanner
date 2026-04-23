# Notice Banner (WHMCS)

**Version:** 3.1.0

Display admin and client notices as banners with markdown, polls, @mentions, assignments, scheduling, webhooks, and more.

**Author:** Dhruv from HostingSpell

## Requirements

- A supported **WHMCS** install with **PHP** matching your server (match WHMCS’s PHP requirements, e.g. 8.1+ where applicable).
- **MySQL/MariaDB** (module creates and uses `mod_*` tables).
- **Outbound HTTPS** to the license validation API (see *Licensing*). Firewalls and hosts must allow that connection.

## Installation

1. Upload the `noticebanner` folder to:

   `modules/addons/noticebanner/`

2. Ensure the directory layout is intact, including:

   | Path | Role |
   |------|------|
   | `noticebanner.php` | Addon entry, admin UI, API |
   | `hooks.php` | Client/admin hooks (load via *Activate* — see below) |
   | `widget.php` | Admin home dashboard widget |
   | `license.php` | License engine |
   | `templates/admin.tpl` | Admin template (must stay plain text if you use IonCube; see `IONCUBE.txt`) |
   | `storage/` | Runtime uploads (e.g. to-do attachments; keep writable by the web user) |

3. In **WHMCS Admin → System Settings → Addon Modules**, find **Notice Banner**, configure if needed, then **Activate** (this creates/updates the module’s database tables).

4. When the module is **active**, WHMCS loads **`hooks.php`** for client and admin area hooks, and the dashboard widget is provided by **`widget.php`**. If banners or hooks do not appear after an upgrade, clear cache, confirm the module is still active, and see WHMCS documentation for your version.

5. (Optional) **Global Webhook URL** in module settings: POSTs JSON when notices are created or updated (Slack, Discord, or custom endpoints).

## Admin usage

- **Addon Modules → Notice Banner** — full management UI.
- **Admin Dashboard** — **Notice Banner** widget (requires **Addon Modules** permission) for a compact overview and quick actions.

## Client area

Notices are injected via hooks into the client area and admin area according to your notice rules (client visibility, pages, schedule, etc.).

## Storage

`storage/` should be writable. It is used for generated/uploaded data (e.g. under `storage/todo_attachments/`). Only `storage/.gitignore` is versioned by default; runtime files are not meant to be committed.

## Licensing

`license.php` performs validation over **HTTPS**. Default endpoint and network notes are documented in **`IONCUBE.txt`** (outbound access, optional URL override, Cloudflare caveats).

To override the license API URL, copy **`noticebanner-license-url.example.php`** to **`noticebanner-license-url.php`** in the same folder as `noticebanner.php` and set `NB_LICENSE_API_URL` (see the example file).

## Distributors: IonCube

If you ship encoded copies, read **`IONCUBE.txt`** for which files to encode, which to leave plain, PHP version matching, and customer loader requirements.

## Support

For HostingSpell product support, use the channels provided with your license or purchase.
