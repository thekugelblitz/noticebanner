<?php
/**
 * Notice Banner — License Engine
 *
 * License key is stored in mod_noticebanner_license (not in tbladdonmodules).
 * free_max_notices comes from the API response per-key (not editable by customer).
 *
 * Remote endpoint: https://whmcsapi.hostingspell.com/api/validate.php (DNS-only / grey cloud)
 */

if (!defined('WHMCS')) {
    die('Access Denied');
}

// Optional: copy noticebanner-license-url.example.php → noticebanner-license-url.php
// and set a URL that bypasses Cloudflare (e.g. DNS-only subdomain pointing at origin).
$__nbLicUrlFile = __DIR__ . '/noticebanner-license-url.php';
if (is_file($__nbLicUrlFile)) {
    require_once $__nbLicUrlFile;
}

// ─── Constants ────────────────────────────────────────────────────────────────

define('NB_LICENSE_PRODUCT',     'noticebanner-whmcs');
if (!defined('NB_LICENSE_API_URL')) {
    define('NB_LICENSE_API_URL', 'https://whmcsapi.hostingspell.com/api/validate.php');
}
// Optional in noticebanner-license-url.php: define('NB_LICENSE_SSL_VERIFY_PEER', false);
if (!defined('NB_LICENSE_SSL_VERIFY_PEER')) {
    define('NB_LICENSE_SSL_VERIFY_PEER', true);
}
define('NB_LICENSE_CACHE_HOURS', 24);  // re-check every 24 h
define('NB_LICENSE_GRACE_HOURS', 72);  // keep Pro for 72 h if API unreachable
define('NB_LICENSE_FREE_CAP',    3);   // default free cap if API hasn't responded yet

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
                $t->string('license_key_stored', 100)->default(''); // key entered by admin
                $t->string('status', 30)->default('unknown');
                $t->string('plan', 20)->default('free');
                $t->string('issued_to', 200)->default('');
                $t->integer('free_max_notices')->default(NB_LICENSE_FREE_CAP); // set by API
                $t->timestamp('license_expires_at')->nullable();
                $t->timestamp('last_ok_at')->nullable();
                $t->timestamp('next_check_after')->nullable();
                $t->text('last_error')->nullable();
                $t->text('last_http_debug')->nullable();
                $t->timestamps();
            });
        } else {
            // Add columns if upgrading from older schema
            if (!$schema->hasColumn('mod_noticebanner_license', 'license_key_stored')) {
                $schema->table('mod_noticebanner_license', function ($t) {
                    $t->string('license_key_stored', 100)->default('')->after('cache_key');
                });
            }
            if (!$schema->hasColumn('mod_noticebanner_license', 'free_max_notices')) {
                $schema->table('mod_noticebanner_license', function ($t) {
                    $t->integer('free_max_notices')->default(NB_LICENSE_FREE_CAP)->after('issued_to');
                });
            }
            if (!$schema->hasColumn('mod_noticebanner_license', 'last_http_debug')) {
                $schema->table('mod_noticebanner_license', function ($t) {
                    $t->text('last_http_debug')->nullable()->after('last_error');
                });
            }
        }
    } catch (\Exception $e) {}
}
}

// ─── Key storage (in our own table, not tbladdonmodules) ──────────────────────

if (!function_exists('noticebanner_license_get_key')) {
function noticebanner_license_get_key(): string {
    noticebanner_license_ensure_table();
    try {
        $row = \WHMCS\Database\Capsule::table('mod_noticebanner_license')
            ->where('cache_key', 'state')->first(['license_key_stored']);
        return trim((string)($row->license_key_stored ?? ''));
    } catch (\Exception $e) { return ''; }
}
}

if (!function_exists('noticebanner_license_save_key')) {
function noticebanner_license_save_key(string $key): void {
    noticebanner_license_ensure_table();
    try {
        \WHMCS\Database\Capsule::table('mod_noticebanner_license')->updateOrInsert(
            ['cache_key' => 'state'],
            ['license_key_stored' => trim($key), 'updated_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s')]
        );
    } catch (\Exception $e) {}
}
}

// ─── Settings helper (only for non-sensitive settings still in tbladdonmodules) ─

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
        return preg_replace('/^www\./', '', $host)
            ?: strtolower(preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'unknown'));
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
            array_merge($data, [
                'updated_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ])
        );
    } catch (\Exception $e) {}
}
}

// ─── HTTP POST (cURL preferred — many hosts disable allow_url_fopen for URLs) ─

if (!function_exists('noticebanner_license_is_cloudflare_challenge')) {
function noticebanner_license_is_cloudflare_challenge(string $raw, int $httpCode): bool {
    if ($raw === '' || $httpCode < 400) {
        return false;
    }
    $r = strtolower($raw);
    return (stripos($r, 'just a moment') !== false)
        || (stripos($r, 'cf-browser-verification') !== false)
        || (stripos($r, 'cdn-cgi/challenge') !== false)
        || (stripos($r, 'checking your browser') !== false);
}
}

if (!function_exists('noticebanner_license_curl_post_json')) {
/** @return array{raw:string,http_code:int,errno:int,cerror:string,user_agent:string} */
function noticebanner_license_curl_post_json(string $url, string $body, string $userAgent): array {
    $headers = [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($body),
        'User-Agent: ' . $userAgent,
        'Accept: application/json, text/plain, */*',
    ];
    if (stripos($userAgent, 'Mozilla') !== false) {
        $headers[] = 'Accept-Language: en-US,en;q=0.9';
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
    ]);
    $raw   = curl_exec($ch);
    $errno = curl_errno($ch);
    $cerr  = curl_error($ch);
    $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'raw'         => ($raw === false) ? '' : (string)$raw,
        'http_code'   => $code,
        'errno'       => $errno,
        'cerror'      => $cerr,
        'user_agent'  => $userAgent,
    ];
}
}

if (!function_exists('noticebanner_license_http_post_json')) {
/**
 * POST JSON to URL. Returns diagnostic array; sets decoded payload if JSON is valid.
 */
function noticebanner_license_http_post_json(string $url, array $payload): array {
    $body = json_encode($payload);
    $out  = [
        'url'                 => $url,
        'php_allow_url_fopen' => filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN),
        'curl_available'      => function_exists('curl_init'),
        'transport'           => '',
        'http_code'           => 0,
        'raw'                 => '',
        'raw_preview'         => '',
        'error'               => '',
        'json_ok'             => false,
        'decoded'             => null,
        'cloudflare_hint'     => '',
        'retry_note'          => '',
    ];

    if (function_exists('curl_init')) {
        $out['transport'] = 'curl';
        $uaModule = 'NoticeBanner-WHMCS/3.1.0';
        $uaBrowser = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

        $r = noticebanner_license_curl_post_json($url, $body, $uaModule);
        $out['http_code'] = $r['http_code'];
        if ($r['errno']) {
            $out['error'] = 'cURL error ' . $r['errno'] . ': ' . $r['cerror'];
            $out['raw']   = '';
        } else {
            $out['raw'] = $r['raw'];
        }

        // Cloudflare "Just a moment…" interstitial — retry once with browser-like UA (helps some configs; JS challenges still need CF bypass).
        if ($out['raw'] !== '' && noticebanner_license_is_cloudflare_challenge($out['raw'], $out['http_code'])) {
            $out['cloudflare_hint'] = 'Cloudflare bot/challenge page detected (not valid JSON). '
                . 'Allow this path in Cloudflare or use a DNS-only API hostname — see noticebanner-license-url.example.php and CLOUDFLARE.txt on the license server.';
            $r2 = noticebanner_license_curl_post_json($url, $body, $uaBrowser);
            if (!$r2['errno'] && $r2['raw'] !== '') {
                $dec2 = json_decode($r2['raw'], true);
                if (is_array($dec2)) {
                    $out['raw']         = $r2['raw'];
                    $out['http_code']   = $r2['http_code'];
                    $out['retry_note']  = 'Retried with browser User-Agent after Cloudflare-style response.';
                    $out['json_ok']     = true;
                    $out['decoded']     = $dec2;
                    $out['error']       = '';
                    $out['cloudflare_hint'] = '';
                } else {
                    $out['retry_note'] = 'Retried with browser User-Agent; still not JSON (configure Cloudflare bypass — see CLOUDFLARE.txt).';
                    $out['raw']        = $r2['raw'];
                    $out['http_code']  = $r2['http_code'];
                }
            }
        }
    } elseif (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
        $out['transport'] = 'file_get_contents';
        try {
            $ctx = stream_context_create(['http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\nUser-Agent: NoticeBanner-WHMCS/3.1.0\r\n",
                'content'       => $body,
                'timeout'       => 15,
                'ignore_errors' => true,
            ]]);
            $raw = @file_get_contents($url, false, $ctx);
            if ($raw === false) {
                $out['error'] = 'file_get_contents failed (check SSL, DNS, and firewall outbound to port 443).';
            } else {
                $out['raw'] = (string)$raw;
            }
            if (isset($http_response_header) && !empty($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
                $out['http_code'] = (int)$m[1];
            }
        } catch (\Throwable $e) {
            $out['error'] = $e->getMessage();
        }
    } else {
        $out['error'] = 'Outbound HTTP blocked: cURL extension not loaded and allow_url_fopen is Off. Enable one of them on this server.';
    }

    $preview = $out['raw'];
    if (strlen($preview) > 1200) {
        $preview = substr($preview, 0, 1200) . "\n… (truncated)";
    }
    $out['raw_preview'] = $preview;

    if ($out['raw'] !== '' && !$out['json_ok']) {
        $dec = json_decode($out['raw'], true);
        if (is_array($dec)) {
            $out['json_ok'] = true;
            $out['decoded'] = $dec;
            $out['error']   = '';
        } else {
            $out['error'] = ($out['error'] ?: '') . ($out['error'] ? ' ' : '') . 'Response is not valid JSON.';
        }
    }

    return $out;
}
}

if (!function_exists('noticebanner_license_debug_format')) {
function noticebanner_license_debug_format(array $dbg): string {
    $lines = [
        'URL:              ' . ($dbg['url'] ?? ''),
        'Transport:        ' . ($dbg['transport'] ?: '(none)'),
        'cURL available:   ' . (($dbg['curl_available'] ?? false) ? 'yes' : 'no'),
        'allow_url_fopen:  ' . (($dbg['php_allow_url_fopen'] ?? false) ? 'On' : 'Off'),
        'HTTP status:      ' . (string)($dbg['http_code'] ?? 0),
        'Error:            ' . ($dbg['error'] ?: '(none)'),
        'JSON parsed:      ' . (($dbg['json_ok'] ?? false) ? 'yes' : 'no'),
    ];
    if (!empty($dbg['retry_note'])) {
        $lines[] = 'Retry:            ' . $dbg['retry_note'];
    }
    if (!empty($dbg['cloudflare_hint'])) {
        $lines[] = '>>> ' . $dbg['cloudflare_hint'];
    }
    $lines[] = '--- Response body (preview) ---';
    $lines[] = $dbg['raw_preview'] ?? '';
    return implode("\n", $lines);
}
}

// ─── Remote call ──────────────────────────────────────────────────────────────

if (!function_exists('noticebanner_license_remote_validate')) {
function noticebanner_license_remote_validate(string $licenseKey, string $domain): ?array {
    if ($licenseKey === '') return null;

    $http = noticebanner_license_http_post_json(NB_LICENSE_API_URL, [
        'license_key' => $licenseKey,
        'product'     => NB_LICENSE_PRODUCT,
        'domain'      => $domain,
        'version'     => '3.1.0',
    ]);

    $GLOBALS['noticebanner_last_license_http'] = $http;

    if (!empty($http['decoded']) && is_array($http['decoded'])) {
        return $http['decoded'];
    }
    return null;
}
}

if (!function_exists('noticebanner_license_run_connection_diagnostics')) {
/**
 * Test reachability without requiring a real key (uses a dummy POST).
 */
function noticebanner_license_run_connection_diagnostics(): string {
    $domain = noticebanner_license_domain();
    $http   = noticebanner_license_http_post_json(NB_LICENSE_API_URL, [
        'license_key' => '__diagnostic_ping__',
        'product'     => NB_LICENSE_PRODUCT,
        'domain'      => $domain,
        'version'     => '3.1.0',
    ]);
    $GLOBALS['noticebanner_last_license_http'] = $http;
    $header = "Notice Banner — License server connection test\n"
        . "WHMCS host (from System URL): {$domain}\n"
        . str_repeat('=', 60) . "\n";
    return $header . noticebanner_license_debug_format($http);
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
            'free_max_notices'   => NB_LICENSE_FREE_CAP,
            'license_expires_at' => null,
            'last_ok_at'         => null,
            'next_check_after'   => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'last_error'         => null,
            'last_http_debug'    => null,
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

        $httpDbg = '';
        if (!empty($GLOBALS['noticebanner_last_license_http']) && is_array($GLOBALS['noticebanner_last_license_http'])) {
            $httpDbg = noticebanner_license_debug_format($GLOBALS['noticebanner_last_license_http']);
        }

        noticebanner_license_write_cache([
            'status'             => $statusNow,
            'plan'               => ($statusNow === 'valid' && $cache) ? $cache->plan : 'free',
            'issued_to'          => $cache ? $cache->issued_to : '',
            'free_max_notices'   => $cache ? (int)$cache->free_max_notices : NB_LICENSE_FREE_CAP,
            'license_expires_at' => $cache ? $cache->license_expires_at : null,
            'last_ok_at'         => $cache ? $cache->last_ok_at : null,
            'next_check_after'   => date('Y-m-d H:i:s', strtotime('+30 minutes')),
            'last_error'         => 'Could not reach license server at ' . $now,
            'last_http_debug'    => $httpDbg !== '' ? $httpDbg : null,
        ]);
        $masked = substr($licenseKey, 0, 8) . '****';
        noticebanner_log(null, 'license_check_failed', "Key: $masked, Domain: $domain");
        return;
    }

    $isValid      = !empty($payload['valid']);
    $plan         = ($isValid && ($payload['plan'] ?? '') === 'pro') ? 'pro' : 'free';
    $expiresAt    = !empty($payload['expires_at']) ? date('Y-m-d H:i:s', strtotime($payload['expires_at'])) : null;
    $issuedTo     = $payload['issued_to'] ?? '';
    $freeMaxNotices = isset($payload['free_max_notices']) ? max(1, (int)$payload['free_max_notices']) : NB_LICENSE_FREE_CAP;
    $status       = $isValid ? 'valid' : 'invalid';
    $errMsg       = $isValid ? null : ($payload['message'] ?? 'Invalid');

    noticebanner_license_write_cache([
        'status'             => $status,
        'plan'               => $plan,
        'issued_to'          => $issuedTo,
        'free_max_notices'   => $freeMaxNotices,
        'license_expires_at' => $expiresAt,
        'last_ok_at'         => $isValid ? $now : null,
        'next_check_after'   => date('Y-m-d H:i:s', strtotime("+{$intervalHours} hours")),
        'last_error'         => $errMsg,
        'last_http_debug'    => null,
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
        'free_max_notices' => NB_LICENSE_FREE_CAP,
        'license_expires_at' => null, 'last_ok_at' => null,
        'next_check_after' => null, 'last_error' => null,
        'license_key_stored' => '',
        'last_http_debug' => null,
    ];
}
}

// ─── Free-tier cap (set by API per key, not editable by customer) ─────────────

if (!function_exists('noticebanner_free_notice_cap')) {
function noticebanner_free_notice_cap(): int {
    $cache = noticebanner_license_read_cache();
    return max(1, (int)($cache->free_max_notices ?? NB_LICENSE_FREE_CAP));
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
