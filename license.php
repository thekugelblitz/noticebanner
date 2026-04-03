<?php
/**
 * Notice Banner — License Engine
 *
 * Handles remote validation against the HostingSpell license API,
 * local caching in mod_noticebanner_license, and the is_pro() gate.
 *
 * Remote endpoint: https://hostingspell.com/api/noticebanner-license/v1/validate
 * Response is RSA-SHA256 signed; the public key is embedded below.
 */

if (!defined('WHMCS')) {
    die('Access Denied');
}

// ─── License constants ────────────────────────────────────────────────────────

define('NB_LICENSE_PRODUCT',      'noticebanner-whmcs');
define('NB_LICENSE_API_URL',      'https://hostingspell.com/api/noticebanner-license/v1/validate');
define('NB_LICENSE_CACHE_HOURS',  24);   // re-validate every 24 h by default
define('NB_LICENSE_GRACE_HOURS',  72);   // keep Pro for 72 h if API is unreachable

/**
 * RSA-2048 public key (PEM) used to verify signed responses from the API.
 * The matching private key lives only on hostingspell.com.
 *
 * REPLACE the placeholder below with your real public key before distributing.
 * Generate a key pair:
 *   openssl genrsa -out nb_private.pem 2048
 *   openssl rsa -in nb_private.pem -pubout -out nb_public.pem
 */
define('NB_LICENSE_PUBLIC_KEY', <<<'PUBKEY'
-----BEGIN PUBLIC KEY-----
REPLACE_WITH_YOUR_REAL_RSA_PUBLIC_KEY_HERE
-----END PUBLIC KEY-----
PUBKEY
);

// ─── Cache table bootstrap ────────────────────────────────────────────────────

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
                $t->string('cache_key', 64)->unique();   // e.g. 'state'
                $t->string('status', 20)->default('unknown'); // valid|invalid|expired|error|unknown
                $t->string('plan', 20)->default('free');      // pro|free
                $t->string('issued_to', 200)->default('');
                $t->timestamp('license_expires_at')->nullable();
                $t->timestamp('last_ok_at')->nullable();
                $t->timestamp('next_check_after')->nullable();
                $t->text('last_error')->nullable();
                $t->string('response_hash', 64)->default(''); // sha256 of last raw response
                $t->timestamps();
            });
        }
    } catch (\Exception $e) {}
}
}

// ─── Config helpers ───────────────────────────────────────────────────────────

if (!function_exists('noticebanner_license_get_key')) {
function noticebanner_license_get_key(): string {
    try {
        $val = \WHMCS\Database\Capsule::table('tbladdonmodules')
            ->where('module', 'noticebanner')
            ->where('setting', 'license_key')
            ->value('value');
        return trim((string)($val ?? ''));
    } catch (\Exception $e) {
        return '';
    }
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
    } catch (\Exception $e) {
        return $default;
    }
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
        // Strip leading www.
        $host = preg_replace('/^www\./', '', $host);
        return $host ?: ($_SERVER['HTTP_HOST'] ?? 'unknown');
    } catch (\Exception $e) {
        return strtolower(preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'unknown'));
    }
}
}

// ─── Signature verification ───────────────────────────────────────────────────

if (!function_exists('noticebanner_license_verify_signature')) {
/**
 * Verify that $signature (base64) over $data matches the embedded public key.
 * Returns true only if openssl_verify succeeds with result 1.
 */
function noticebanner_license_verify_signature(string $data, string $signatureB64): bool {
    if (!function_exists('openssl_verify')) return false;
    $pubKey = NB_LICENSE_PUBLIC_KEY;
    if (strpos($pubKey, 'REPLACE_WITH') !== false) {
        // Public key not yet configured — treat as unverifiable; block Pro
        return false;
    }
    $sig = base64_decode($signatureB64, true);
    if ($sig === false) return false;
    $result = openssl_verify($data, $sig, $pubKey, OPENSSL_ALGO_SHA256);
    return $result === 1;
}
}

// ─── Remote validation ────────────────────────────────────────────────────────

if (!function_exists('noticebanner_license_remote_validate')) {
/**
 * Call the HostingSpell API. Returns parsed payload array on success, null on failure.
 * Expected response JSON: { "payload": "<base64>", "signature": "<base64>" }
 * Payload JSON: { "valid": bool, "plan": "pro"|"free", "expires_at": "ISO8601|null",
 *                 "issued_to": "string", "message": "string" }
 */
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
    } catch (\Exception $e) {
        return null;
    }

    if ($raw === false || $raw === '') return null;

    $resp = json_decode($raw, true);
    if (!is_array($resp) || empty($resp['payload']) || empty($resp['signature'])) return null;

    // Verify signature over the raw payload string
    if (!noticebanner_license_verify_signature($resp['payload'], $resp['signature'])) return null;

    $payload = json_decode(base64_decode($resp['payload'], true), true);
    if (!is_array($payload)) return null;

    // Attach hash of raw response for audit
    $payload['_response_hash'] = hash('sha256', $raw);

    return $payload;
}
}

// ─── Cache read / write ───────────────────────────────────────────────────────

if (!function_exists('noticebanner_license_read_cache')) {
function noticebanner_license_read_cache(): ?object {
    noticebanner_license_ensure_table();
    try {
        return \WHMCS\Database\Capsule::table('mod_noticebanner_license')
            ->where('cache_key', 'state')
            ->first();
    } catch (\Exception $e) {
        return null;
    }
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

// ─── Main refresh logic ───────────────────────────────────────────────────────

if (!function_exists('noticebanner_license_refresh')) {
/**
 * Perform a remote check if the cache is stale or $force is true.
 * Updates the cache table with the result.
 */
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
            'response_hash'      => '',
        ]);
        return;
    }

    if (!$force) {
        $cache = noticebanner_license_read_cache();
        if ($cache && $cache->next_check_after && strtotime($cache->next_check_after) > time()) {
            return; // Cache still fresh
        }
    }

    $domain  = noticebanner_license_domain();
    $payload = noticebanner_license_remote_validate($licenseKey, $domain);

    $intervalHours = max(1, (int)noticebanner_license_get_setting('license_check_interval_hours', NB_LICENSE_CACHE_HOURS));
    $now           = date('Y-m-d H:i:s');

    if ($payload === null) {
        // Network / signature failure — keep last known good within grace window
        $cache = noticebanner_license_read_cache();
        $graceUntil = $cache && $cache->last_ok_at
            ? date('Y-m-d H:i:s', strtotime($cache->last_ok_at) + NB_LICENSE_GRACE_HOURS * 3600)
            : $now;
        $statusNow = ($cache && $cache->status === 'valid' && $graceUntil > $now) ? 'valid' : 'error';

        noticebanner_license_write_cache([
            'status'             => $statusNow,
            'plan'               => ($statusNow === 'valid' && $cache) ? $cache->plan : 'free',
            'issued_to'          => $cache ? $cache->issued_to : '',
            'license_expires_at' => $cache ? $cache->license_expires_at : null,
            'last_ok_at'         => $cache ? $cache->last_ok_at : null,
            'next_check_after'   => date('Y-m-d H:i:s', strtotime('+30 minutes')), // retry sooner
            'last_error'         => 'Remote validation failed or signature mismatch at ' . $now,
            'response_hash'      => '',
        ]);

        // Log masked key only
        $maskedKey = substr($licenseKey, 0, 8) . '****';
        noticebanner_log(null, 'license_check_failed', "Key: $maskedKey, Domain: $domain");
        return;
    }

    $isValid    = !empty($payload['valid']);
    $plan       = ($isValid && ($payload['plan'] ?? '') === 'pro') ? 'pro' : 'free';
    $expiresAt  = !empty($payload['expires_at']) ? date('Y-m-d H:i:s', strtotime($payload['expires_at'])) : null;
    $issuedTo   = $payload['issued_to'] ?? '';
    $status     = $isValid ? 'valid' : 'invalid';

    // Check domain binding (server also checks, but belt-and-suspenders)
    if ($isValid && !empty($payload['domain']) && strtolower($payload['domain']) !== $domain) {
        $status = 'domain_mismatch';
        $plan   = 'free';
    }

    noticebanner_license_write_cache([
        'status'             => $status,
        'plan'               => $plan,
        'issued_to'          => $issuedTo,
        'license_expires_at' => $expiresAt,
        'last_ok_at'         => $status === 'valid' ? $now : null,
        'next_check_after'   => date('Y-m-d H:i:s', strtotime("+{$intervalHours} hours")),
        'last_error'         => $isValid ? null : ($payload['message'] ?? 'License invalid'),
        'response_hash'      => $payload['_response_hash'] ?? '',
    ]);

    $maskedKey = substr($licenseKey, 0, 8) . '****';
    noticebanner_log(null, 'license_checked', "Status: $status, Plan: $plan, Key: $maskedKey, Domain: $domain");
}
}

// ─── Public gate ─────────────────────────────────────────────────────────────

if (!function_exists('noticebanner_license_is_pro')) {
/**
 * Returns true if the current cached license state grants Pro features.
 * Triggers a background refresh if the cache is stale (non-blocking on failure).
 */
function noticebanner_license_is_pro(): bool {
    static $memo = null;
    if ($memo !== null) return $memo;

    noticebanner_license_ensure_table();

    // Trigger refresh if stale (silently — never blocks page render)
    noticebanner_license_refresh(false);

    $cache = noticebanner_license_read_cache();
    if (!$cache) {
        $memo = false;
        return false;
    }

    if ($cache->status !== 'valid' || $cache->plan !== 'pro') {
        $memo = false;
        return false;
    }

    // Hard expiry check
    if ($cache->license_expires_at && strtotime($cache->license_expires_at) < time()) {
        $memo = false;
        return false;
    }

    $memo = true;
    return true;
}
}

if (!function_exists('noticebanner_license_status')) {
/**
 * Returns the full cache row as an array for display in the admin UI.
 */
function noticebanner_license_status(): array {
    noticebanner_license_ensure_table();
    $cache = noticebanner_license_read_cache();
    if (!$cache) {
        return [
            'status'             => 'unknown',
            'plan'               => 'free',
            'issued_to'          => '',
            'license_expires_at' => null,
            'last_ok_at'         => null,
            'next_check_after'   => null,
            'last_error'         => null,
        ];
    }
    return (array)$cache;
}
}

// ─── Free-tier notice cap ─────────────────────────────────────────────────────

if (!function_exists('noticebanner_free_notice_cap')) {
function noticebanner_free_notice_cap(): int {
    $cap = (int)noticebanner_license_get_setting('free_max_notices', 3);
    return max(1, $cap);
}
}

if (!function_exists('noticebanner_free_cap_reached')) {
/**
 * Returns true when the free-tier notice limit has been reached and the
 * installation is not on a Pro license.
 */
function noticebanner_free_cap_reached(): bool {
    if (noticebanner_license_is_pro()) return false;
    try {
        $count = \WHMCS\Database\Capsule::table('mod_noticebanner')
            ->where('is_template', 0)
            ->count();
        return $count >= noticebanner_free_notice_cap();
    } catch (\Exception $e) {
        return false;
    }
}
}

// ─── Daily cron refresh ───────────────────────────────────────────────────────

add_hook('DailyCronJob', 1, function () {
    if (function_exists('noticebanner_license_refresh')) {
        noticebanner_license_refresh(false);
    }
});
