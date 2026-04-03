<?php
/**
 * Notice Banner — License Engine
 *
 * Validates against the HostingSpell license API and caches the result
 * in mod_noticebanner_license to avoid repeated remote calls.
 *
 * Remote endpoint: https://manage.hostingspell.com/api/validate.php
 */

if (!defined('WHMCS')) {
    die('Access Denied');
}

// ─── Constants ────────────────────────────────────────────────────────────────

define('NB_LICENSE_PRODUCT',     'noticebanner-whmcs');
define('NB_LICENSE_API_URL',     'https://manage.hostingspell.com/api/validate.php');
define('NB_LICENSE_CACHE_HOURS', 24);  // re-check every 24 h
define('NB_LICENSE_GRACE_HOURS', 72);  // keep Pro for 72 h if API unreachable

// ─── Cache table ──────────────────────────────────────────────────────────────

if (!function_exists('noticebanner_license_ensure_table')) {
function noticebanner_license_ensure_table(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $schema = \WHMCS\Database\Capsule::schema();
        if (!$schema->hasTable('mod_noticebanner_license')) {
            $schema->create('mod_noticebanner_license', function ($t) {
                $t->increments('id');
                $t->string('cache_key', 64)->unique();
                $t->string('status', 30)->default('unknown');
                $t->string('plan', 20)->default('free');
                $t->string('issued_to', 200)->default('');
                $t->timestamp('license_expires_at')->nullable();
                $t->timestamp('last_ok_at')->nullable();
                $t->timestamp('next_check_after')->nullable();
                $t->text('last_error')->nullable();
                $t->timestamps();
            });
        }
    } catch (\Exception $e) {}
}
}

// ─── Settings helpers ─────────────────────────────────────────────────────────

if (!function_exists('noticebanner_license_get_key')) {
function noticebanner_license_get_key(): string {
    try {
        $val = \WHMCS\Database\Capsule::table('tbladdonmodules')
            ->where('module', 'noticebanner')
            ->where('setting', 'license_key')
            ->value('value');
        return trim((string)($val ?? ''));
    } catch (\Exception $e) { return ''; }
}
}

if (!function_exists('noticebanner_license_get_setting')) {
function noticebanner_license_get_setting(string $setting, $default = '') {
    try {
        $val = \WHMCS\Database\Capsule::table('tbladdonmodules')
            ->where('module', 'noticebanner')
            ->where('setting', $setting)
            ->value('value');
        return $val ?? $default;
    } catch (\Exception $e) { return $default; }
}
}

// ─── Domain normalisation ─────────────────────────────────────────────────────

if (!function_exists('noticebanner_license_domain')) {
function noticebanner_license_domain(): string {
    try {
        $sysUrl = \WHMCS\Database\Capsule::table('tblconfiguration')
            ->where('setting', 'SystemURL')
            ->value('value');
        $host = strtolower(parse_url((string)($sysUrl ?? ''), PHP_URL_HOST) ?? '');
        return preg_replace('/^www\./', '', $host) ?: strtolower(preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'unknown'));
    } catch (\Exception $e) {
        return strtolower(preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'unknown'));
    }
}
}

// ─── Cache read / write ───────────────────────────────────────────────────────

if (!function_exists('noticebanner_license_read_cache')) {
function noticebanner_license_read_cache(): ?object {
    noticebanner_license_ensure_table();
    try {
        return \WHMCS\Database\Capsule::table('mod_noticebanner_license')
            ->where('cache_key', 'state')->first();
    } catch (\Exception $e) { return null; }
}
}

if (!function_exists('noticebanner_license_write_cache')) {
function noticebanner_license_write_cache(array $data): void {
    noticebanner_license_ensure_table();
    try {
        \WHMCS\Database\Capsule::table('mod_noticebanner_license')->updateOrInsert(
            ['cache_key' => 'state'],
            array_merge($data, ['updated_at' => date('Y-m-d H:i:s')])
        );
    } catch (\Exception $e) {}
}
}

// ─── Remote call ──────────────────────────────────────────────────────────────

if (!function_exists('noticebanner_license_remote_validate')) {
function noticebanner_license_remote_validate(string $licenseKey, string $domain): ?array {
    if ($licenseKey === '') return null;

    $body = json_encode([
        'license_key' => $licenseKey,
        'product'     => NB_LICENSE_PRODUCT,
        'domain'      => $domain,
        'version'     => '3.1.0',
    ]);

    try {
        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\nUser-Agent: NoticeBanner-WHMCS/3.1.0\r\n",
            'content'       => $body,
            'timeout'       => 8,
            'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents(NB_LICENSE_API_URL, false, $ctx);
    } catch (\Exception $e) { return null; }

    if (!$raw) return null;

    $resp = json_decode($raw, true);
    if (!is_array($resp)) return null;

    return $resp;
}
}

// ─── Refresh logic ────────────────────────────────────────────────────────────

if (!function_exists('noticebanner_license_refresh')) {
function noticebanner_license_refresh(bool $force = false): void {
    noticebanner_license_ensure_table();

    $licenseKey = noticebanner_license_get_key();

    if ($licenseKey === '') {
        noticebanner_license_write_cache([
            'status'             => 'no_key',
            'plan'               => 'free',
            'issued_to'          => '',
            'license_expires_at' => null,
            'last_ok_at'         => null,
            'next_check_after'   => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'last_error'         => null,
        ]);
        return;
    }

    if (!$force) {
        $cache = noticebanner_license_read_cache();
        if ($cache && $cache->next_check_after && strtotime($cache->next_check_after) > time()) {
            return; // Still fresh
        }
    }

    $domain  = noticebanner_license_domain();
    $payload = noticebanner_license_remote_validate($licenseKey, $domain);
    $intervalHours = max(1, (int)noticebanner_license_get_setting('license_check_interval_hours', NB_LICENSE_CACHE_HOURS));
    $now = date('Y-m-d H:i:s');

    if ($payload === null) {
        // Network failure — keep last known good within grace window
        $cache      = noticebanner_license_read_cache();
        $graceUntil = ($cache && $cache->last_ok_at)
            ? date('Y-m-d H:i:s', strtotime($cache->last_ok_at) + NB_LICENSE_GRACE_HOURS * 3600)
            : $now;
        $statusNow = ($cache && $cache->status === 'valid' && $graceUntil > $now) ? 'valid' : 'error';

        noticebanner_license_write_cache([
            'status'             => $statusNow,
            'plan'               => ($statusNow === 'valid' && $cache) ? $cache->plan : 'free',
            'issued_to'          => $cache ? $cache->issued_to : '',
            'license_expires_at' => $cache ? $cache->license_expires_at : null,
            'last_ok_at'         => $cache ? $cache->last_ok_at : null,
            'next_check_after'   => date('Y-m-d H:i:s', strtotime('+30 minutes')),
            'last_error'         => 'Could not reach license server at ' . $now,
        ]);
        $masked = substr($licenseKey, 0, 8) . '****';
        noticebanner_log(null, 'license_check_failed', "Key: $masked, Domain: $domain");
        return;
    }

    $isValid   = !empty($payload['valid']);
    $plan      = ($isValid && ($payload['plan'] ?? '') === 'pro') ? 'pro' : 'free';
    $expiresAt = !empty($payload['expires_at']) ? date('Y-m-d H:i:s', strtotime($payload['expires_at'])) : null;
    $issuedTo  = $payload['issued_to'] ?? '';
    $status    = $isValid ? 'valid' : 'invalid';
    $errMsg    = $isValid ? null : ($payload['message'] ?? 'Invalid');

    noticebanner_license_write_cache([
        'status'             => $status,
        'plan'               => $plan,
        'issued_to'          => $issuedTo,
        'license_expires_at' => $expiresAt,
        'last_ok_at'         => $isValid ? $now : null,
        'next_check_after'   => date('Y-m-d H:i:s', strtotime("+{$intervalHours} hours")),
        'last_error'         => $errMsg,
    ]);

    $masked = substr($licenseKey, 0, 8) . '****';
    noticebanner_log(null, 'license_checked', "Status: $status, Plan: $plan, Key: $masked, Domain: $domain");
}
}

// ─── Public gate ──────────────────────────────────────────────────────────────

if (!function_exists('noticebanner_license_is_pro')) {
function noticebanner_license_is_pro(): bool {
    static $memo = null;
    if ($memo !== null) return $memo;

    noticebanner_license_ensure_table();
    noticebanner_license_refresh(false);

    $cache = noticebanner_license_read_cache();
    if (!$cache || $cache->status !== 'valid' || $cache->plan !== 'pro') {
        return $memo = false;
    }
    if ($cache->license_expires_at && strtotime($cache->license_expires_at) < time()) {
        return $memo = false;
    }
    return $memo = true;
}
}

if (!function_exists('noticebanner_license_status')) {
function noticebanner_license_status(): array {
    noticebanner_license_ensure_table();
    $cache = noticebanner_license_read_cache();
    return $cache ? (array)$cache : [
        'status' => 'unknown', 'plan' => 'free', 'issued_to' => '',
        'license_expires_at' => null, 'last_ok_at' => null,
        'next_check_after' => null, 'last_error' => null,
    ];
}
}

// ─── Free-tier cap ────────────────────────────────────────────────────────────

if (!function_exists('noticebanner_free_notice_cap')) {
function noticebanner_free_notice_cap(): int {
    return max(1, (int)noticebanner_license_get_setting('free_max_notices', 3));
}
}

if (!function_exists('noticebanner_free_cap_reached')) {
function noticebanner_free_cap_reached(): bool {
    if (noticebanner_license_is_pro()) return false;
    try {
        $count = \WHMCS\Database\Capsule::table('mod_noticebanner')
            ->where('is_template', 0)->count();
        return $count >= noticebanner_free_notice_cap();
    } catch (\Exception $e) { return false; }
}
}

// ─── Daily cron ───────────────────────────────────────────────────────────────

add_hook('DailyCronJob', 1, function () {
    if (function_exists('noticebanner_license_refresh')) {
        noticebanner_license_refresh(false);
    }
});
