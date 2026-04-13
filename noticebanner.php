<?php
if (!defined('WHMCS')) {
    die('Access Denied');
}

// ─── License engine ──────────────────────────────────────────────────────────
require_once __DIR__ . '/license.php';

// ─── Config ──────────────────────────────────────────────────────────────────

if (!function_exists('noticebanner_config')) {
function noticebanner_config() {
    return [
        'name'        => 'Notice Banner',
        'description' => 'Display admin/client notices as banners with markdown, polls, @mentions, assignments, scheduling and more.',
        'version'     => '3.1.0',
        'author'      => 'Dhruv from HostingSpell',
        'fields'      => [
            'webhook_url' => [
                'FriendlyName' => 'Global Webhook URL',
                'Type'         => 'text',
                'Size'         => '60',
                'Description'  => 'POST JSON payload to this URL whenever a notice is created or updated (Slack/Discord/custom). Leave blank to disable.',
            ],
        ],
    ];
}
}

// ─── Activate / Deactivate ───────────────────────────────────────────────────

if (!function_exists('noticebanner_activate')) {
function noticebanner_activate() {
    noticebanner_ensure_table();
    noticebanner_ensure_columns();
    return ['status' => 'success', 'description' => 'Notice Banner v3.1.0 activated. Database ready.'];
}
}

if (!function_exists('noticebanner_deactivate')) {
function noticebanner_deactivate() {
    return ['status' => 'success', 'description' => 'Module deactivated. All data tables preserved.'];
}
}

// ─── Table bootstrap ─────────────────────────────────────────────────────────

if (!function_exists('noticebanner_ensure_table')) {
function noticebanner_ensure_table() {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $schema = \WHMCS\Database\Capsule::schema();
        if (!$schema->hasTable('mod_noticebanner')) {
            $schema->create('mod_noticebanner', function ($table) {
                $table->increments('id');
                $table->string('notice_title', 255)->default('');
                $table->text('notice_content')->nullable();
                $table->tinyInteger('show_to_clients')->default(0);
                $table->tinyInteger('show_to_admins')->default(1);
                $table->string('display_type', 20)->default('banner');
                $table->integer('show_again_minutes')->default(60);
                $table->tinyInteger('expandable')->default(0);
                $table->string('bg_color', 30)->default('#fffae6');
                $table->string('font_color', 30)->default('#222222');
                $table->tinyInteger('button_enabled')->default(0);
                $table->string('button_text', 100)->default('');
                $table->string('button_link', 500)->default('');
                $table->tinyInteger('button_newtab')->default(0);
                $table->string('button_bg', 30)->default('#2563eb');
                $table->string('button_color', 30)->default('#ffffff');
                $table->tinyInteger('ticket_enabled')->default(0);
                $table->string('ticket_department_id', 20)->default('');
                $table->string('ticket_button_text', 100)->default('');
                $table->tinyInteger('poll_enabled')->default(0);
                $table->string('poll_question', 500)->default('');
                $table->text('poll_options')->nullable();
                $table->text('poll_results')->nullable();
                $table->text('assigned_admins')->nullable();
                $table->text('mentioned_admins')->nullable();
                $table->string('priority', 20)->default('normal');
                $table->datetime('notice_timestamp')->nullable();
                $table->integer('sort_order')->default(0);
                // v3 columns
                $table->datetime('expires_at')->nullable();
                $table->string('tags', 500)->default('');
                $table->text('client_groups')->nullable();
                $table->tinyInteger('is_template')->default(0);
                $table->string('template_name', 100)->default('');
                $table->datetime('publish_at')->nullable();
                $table->string('webhook_url', 500)->default('');
                $table->text('page_slugs')->nullable();
                $table->tinyInteger('is_pinned')->default(0);
                $table->tinyInteger('is_todo_banner')->default(0);
                $table->timestamps();
            });
        }

        // Migrate legacy data.txt if present
        $legacyFile = __DIR__ . '/data.txt';
        if (file_exists($legacyFile)) {
            $legacy = json_decode(file_get_contents($legacyFile), true);
            if (!empty($legacy['notices'])) {
                foreach (array_reverse($legacy['notices']) as $i => $n) {
                    \WHMCS\Database\Capsule::table('mod_noticebanner')->insert([
                        'notice_title'         => $n['notice_title'] ?? '',
                        'notice_content'       => $n['notice_content'] ?? ($n['notice'] ?? ''),
                        'show_to_clients'      => (int)($n['show_to_clients'] ?? 0),
                        'show_to_admins'       => (int)($n['show_to_admins'] ?? 1),
                        'display_type'         => $n['display_type'] ?? 'banner',
                        'show_again_minutes'   => (int)($n['show_again_minutes'] ?? 60),
                        'expandable'           => (int)($n['expandable'] ?? 0),
                        'bg_color'             => $n['bg_color'] ?? '#fffae6',
                        'font_color'           => $n['font_color'] ?? '#222222',
                        'button_enabled'       => (int)($n['button_enabled'] ?? 0),
                        'button_text'          => $n['button_text'] ?? '',
                        'button_link'          => $n['button_link'] ?? '',
                        'button_newtab'        => (int)($n['button_newtab'] ?? 0),
                        'button_bg'            => $n['button_bg'] ?? '#2563eb',
                        'button_color'         => $n['button_color'] ?? '#ffffff',
                        'ticket_enabled'       => (int)($n['ticket_enabled'] ?? 0),
                        'ticket_department_id' => $n['ticket_department_id'] ?? '',
                        'ticket_button_text'   => $n['ticket_button_text'] ?? '',
                        'poll_enabled'         => (int)($n['poll_enabled'] ?? 0),
                        'poll_question'        => $n['poll_question'] ?? '',
                        'poll_options'         => json_encode($n['poll_options'] ?? []),
                        'poll_results'         => json_encode($n['poll_results'] ?? []),
                        'assigned_admins'      => json_encode([]),
                        'mentioned_admins'     => json_encode([]),
                        'priority'             => 'normal',
                        'notice_timestamp'     => !empty($n['timestamp']) ? date('Y-m-d H:i:s', strtotime($n['timestamp'])) : null,
                        'sort_order'           => $i,
                        'created_at'           => $n['created_at'] ?? date('Y-m-d H:i:s'),
                        'updated_at'           => date('Y-m-d H:i:s'),
                    ]);
                }
                rename($legacyFile, $legacyFile . '.migrated');
            }
        }

        noticebanner_ensure_columns();
    } catch (\Exception $e) {}
}
}

// ─── Column migration (idempotent — adds new v3 columns to existing tables) ──

if (!function_exists('noticebanner_ensure_columns')) {
function noticebanner_ensure_columns() {
    static $colChecked = false;
    if ($colChecked) return;
    $colChecked = true;
    try {
        $schema = \WHMCS\Database\Capsule::schema();

        // v3 columns on mod_noticebanner
        $schema->table('mod_noticebanner', function ($table) use ($schema) {
            if (!$schema->hasColumn('mod_noticebanner', 'expires_at'))
                $table->datetime('expires_at')->nullable()->after('sort_order');
            if (!$schema->hasColumn('mod_noticebanner', 'tags'))
                $table->string('tags', 500)->default('')->after('expires_at');
            if (!$schema->hasColumn('mod_noticebanner', 'client_groups'))
                $table->text('client_groups')->nullable()->after('tags');
            if (!$schema->hasColumn('mod_noticebanner', 'is_template'))
                $table->tinyInteger('is_template')->default(0)->after('client_groups');
            if (!$schema->hasColumn('mod_noticebanner', 'template_name'))
                $table->string('template_name', 100)->default('')->after('is_template');
            if (!$schema->hasColumn('mod_noticebanner', 'publish_at'))
                $table->datetime('publish_at')->nullable()->after('template_name');
            if (!$schema->hasColumn('mod_noticebanner', 'webhook_url'))
                $table->string('webhook_url', 500)->default('')->after('publish_at');
            if (!$schema->hasColumn('mod_noticebanner', 'page_slugs'))
                $table->text('page_slugs')->nullable()->after('webhook_url');
            if (!$schema->hasColumn('mod_noticebanner', 'is_pinned'))
                $table->tinyInteger('is_pinned')->default(0)->after('page_slugs');
            if (!$schema->hasColumn('mod_noticebanner', 'is_todo_banner'))
                $table->tinyInteger('is_todo_banner')->default(0)->after('is_pinned');
            // v3.1 — granular targeting
            if (!$schema->hasColumn('mod_noticebanner', 'target_clients'))
                $table->text('target_clients')->nullable()->after('is_todo_banner');
            if (!$schema->hasColumn('mod_noticebanner', 'target_servers'))
                $table->text('target_servers')->nullable()->after('target_clients');
            if (!$schema->hasColumn('mod_noticebanner', 'target_products'))
                $table->text('target_products')->nullable()->after('target_servers');
        });

        // mod_noticebanner_reads
        if (!$schema->hasTable('mod_noticebanner_reads')) {
            $schema->create('mod_noticebanner_reads', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('notice_id');
                $table->string('entity_type', 10)->default('admin'); // admin|client
                $table->unsignedInteger('entity_id');
                $table->timestamp('read_at')->useCurrent();
                $table->unique(['notice_id', 'entity_type', 'entity_id'], 'uniq_nb_read');
            });
        }

        // mod_noticebanner_log
        if (!$schema->hasTable('mod_noticebanner_log')) {
            $schema->create('mod_noticebanner_log', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('notice_id')->nullable();
                $table->unsignedInteger('admin_id')->nullable();
                $table->string('action', 50);
                $table->text('detail')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // mod_noticebanner_poll_votes — individual vote records
        if (!$schema->hasTable('mod_noticebanner_poll_votes')) {
            $schema->create('mod_noticebanner_poll_votes', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('notice_id');
                $table->string('entity_type', 10)->default('client'); // admin|client|predefined
                $table->unsignedInteger('entity_id')->default(0);     // 0 for predefined
                $table->string('entity_label', 200)->default('');     // cached name at vote time
                $table->text('poll_option');
                $table->tinyInteger('is_predefined')->default(0);
                $table->timestamp('voted_at')->useCurrent();
                $table->index(['notice_id'], 'idx_nb_poll_notice');
            });
        }

        if (!$schema->hasTable('mod_noticebanner_todos')) {
            $schema->create('mod_noticebanner_todos', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('notice_id');
                $table->unsignedInteger('parent_todo_id')->nullable();
                $table->string('title', 255);
                $table->text('remarks')->nullable();
                $table->tinyInteger('is_completed')->default(0);
                $table->datetime('due_at')->nullable();
                $table->datetime('completed_at')->nullable();
                $table->unsignedInteger('created_by_admin_id')->nullable();
                $table->unsignedInteger('completed_by_admin_id')->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->index(['notice_id'], 'idx_nb_todo_notice');
                $table->index(['parent_todo_id'], 'idx_nb_todo_parent');
                $table->index(['is_completed'], 'idx_nb_todo_completed');
                $table->index(['due_at'], 'idx_nb_todo_due');
                $table->index(['completed_at'], 'idx_nb_todo_completed_at');
            });
        }

        if (!$schema->hasTable('mod_noticebanner_todo_history')) {
            $schema->create('mod_noticebanner_todo_history', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('todo_id');
                $table->string('action', 40);
                $table->unsignedInteger('admin_id')->nullable();
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['todo_id'], 'idx_nb_todo_hist_todo');
                $table->index(['action'], 'idx_nb_todo_hist_action');
            });
        }
    } catch (\Exception $e) {}
}
}

// ─── Audit log helper ─────────────────────────────────────────────────────────

if (!function_exists('noticebanner_log')) {
function noticebanner_log($noticeId, string $action, string $detail = '') {
    try {
        $adminId = !empty($_SESSION['adminid']) ? (int)$_SESSION['adminid'] : null;
        \WHMCS\Database\Capsule::table('mod_noticebanner_log')->insert([
            'notice_id'  => $noticeId ?: null,
            'admin_id'   => $adminId,
            'action'     => $action,
            'detail'     => $detail ?: null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (\Exception $e) {}
}
}

// ─── Webhook URL safety (blocks SSRF to internal networks) ───────────────────

if (!function_exists('noticebanner_is_safe_webhook_url')) {
function noticebanner_is_safe_webhook_url(string $url): bool {
    $url = trim($url);
    if ($url === '') return false;
    $p = @parse_url($url);
    if (!$p || empty($p['scheme']) || empty($p['host'])) return false;
    $scheme = strtolower((string)$p['scheme']);
    if (!in_array($scheme, ['https', 'http'], true)) return false;
    $host = strtolower((string)$p['host']);
    if ($host === 'localhost' || $host === '0' || preg_match('/(^|\\.)localhost$/', $host)) return false;
    // Numeric IP: reject private / reserved / link-local
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return (bool)filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
    return true;
}
}

// ─── Validate poll vote string against a notice row ──────────────────────────

if (!function_exists('noticebanner_poll_vote_is_valid')) {
function noticebanner_poll_vote_is_valid($row, string $vote): bool {
    if (empty($row) || empty($row->poll_enabled)) return false;
    $vote = trim(html_entity_decode($vote, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($vote === '') return false;
    $opts = array_map(
        fn($o) => trim(html_entity_decode((string)$o, ENT_QUOTES | ENT_HTML5, 'UTF-8')),
        json_decode($row->poll_options ?? '[]', true) ?: []
    );
    return in_array($vote, $opts, true);
}
}

// ─── Webhook helper ───────────────────────────────────────────────────────────

if (!function_exists('noticebanner_fire_webhook')) {
function noticebanner_fire_webhook(array $notice, string $event) {
    // Per-notice URL overrides global config
    $url = trim($notice['webhook_url'] ?? '');
    if (!$url) {
        try {
            $cfg = \WHMCS\Database\Capsule::table('tbladdonmodules')
                ->where('module', 'noticebanner')
                ->where('setting', 'webhook_url')
                ->value('value');
            $url = trim($cfg ?? '');
        } catch (\Exception $e) {}
    }
    if (!$url || !noticebanner_is_safe_webhook_url($url)) return;

    $payload = json_encode([
        'event'           => $event,
        'id'              => $notice['id'] ?? null,
        'title'           => $notice['notice_title'] ?? '',
        'priority'        => $notice['priority'] ?? 'normal',
        'show_to_admins'  => !empty($notice['show_to_admins']),
        'show_to_clients' => !empty($notice['show_to_clients']),
        'tags'            => $notice['tags'] ?? '',
        'expires_at'      => $notice['expires_at'] ?? null,
        'publish_at'      => $notice['publish_at'] ?? null,
        'timestamp'       => date('c'),
    ]);

    try {
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\nContent-Length: " . strlen($payload) . "\r\n",
            'content' => $payload,
            'timeout' => 5,
            'ignore_errors' => true,
        ]]);
        @file_get_contents($url, false, $ctx);
    } catch (\Exception $e) {}
}
}

// ─── DB Helpers ──────────────────────────────────────────────────────────────

if (!function_exists('noticebanner_get_notices')) {
function noticebanner_get_notices(bool $forRendering = false) {
    noticebanner_ensure_table();
    noticebanner_ensure_columns();
    try {
        $now = date('Y-m-d H:i:s');
        $q   = \WHMCS\Database\Capsule::table('mod_noticebanner')
            ->where('is_template', 0);

        if ($forRendering) {
            // Exclude expired notices
            $q->where(function ($q2) use ($now) {
                $q2->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            });
            // Exclude scheduled (not yet published) notices
            $q->where(function ($q2) use ($now) {
                $q2->whereNull('publish_at')->orWhere('publish_at', '<=', $now);
            });
        }

        $rows = $q->orderByRaw('is_pinned DESC')
                  ->orderBy('sort_order', 'asc')
                  ->orderBy('id', 'asc')
                  ->get();

        $notices = [];
        foreach ($rows as $row) {
            $n = (array)$row;
            // Decode HTML entities that may have been stored by older saves
            $rawOpts = json_decode($n['poll_options'] ?? '[]', true) ?: [];
            $n['poll_options']     = array_map('html_entity_decode', $rawOpts);
            // Re-key poll_results to decoded keys so lookups match
            $rawResults = json_decode($n['poll_results'] ?? '{}', true) ?: [];
            $decodedResults = [];
            foreach ($rawResults as $k => $v) {
                $decodedResults[html_entity_decode($k)] = $v;
            }
            $n['poll_results']     = $decodedResults;
            $n['assigned_admins']  = json_decode($n['assigned_admins'] ?? '[]', true) ?: [];
            $n['mentioned_admins'] = json_decode($n['mentioned_admins'] ?? '[]', true) ?: [];
            $n['client_groups']    = json_decode($n['client_groups'] ?? '[]', true) ?: [];
            $n['target_clients']   = json_decode($n['target_clients'] ?? '[]', true) ?: [];
            $n['target_servers']   = json_decode($n['target_servers'] ?? '[]', true) ?: [];
            $n['target_products']  = json_decode($n['target_products'] ?? '[]', true) ?: [];
            $n['page_slugs']       = json_decode($n['page_slugs'] ?? '[]', true) ?: [];
            $notices[] = $n;
        }
        return $notices;
    } catch (\Exception $e) {
        return [];
    }
}
}

if (!function_exists('noticebanner_get_templates')) {
function noticebanner_get_templates() {
    noticebanner_ensure_table();
    noticebanner_ensure_columns();
    try {
        $rows = \WHMCS\Database\Capsule::table('mod_noticebanner')
            ->where('is_template', 1)
            ->orderBy('template_name')
            ->get();
        $out = [];
        foreach ($rows as $row) {
            $n = (array)$row;
            $n['poll_options']     = array_map('html_entity_decode', json_decode($n['poll_options'] ?? '[]', true) ?: []);
            $n['assigned_admins']  = json_decode($n['assigned_admins'] ?? '[]', true) ?: [];
            $n['client_groups']    = json_decode($n['client_groups'] ?? '[]', true) ?: [];
            $n['target_clients']   = json_decode($n['target_clients'] ?? '[]', true) ?: [];
            $n['target_servers']   = json_decode($n['target_servers'] ?? '[]', true) ?: [];
            $n['target_products']  = json_decode($n['target_products'] ?? '[]', true) ?: [];
            $n['page_slugs']       = json_decode($n['page_slugs'] ?? '[]', true) ?: [];
            $out[] = $n;
        }
        return $out;
    } catch (\Exception $e) {
        return [];
    }
}
}

if (!function_exists('noticebanner_get_all_tags')) {
function noticebanner_get_all_tags(): array {
    try {
        $rows = \WHMCS\Database\Capsule::table('mod_noticebanner')
            ->where('is_template', 0)
            ->whereNotNull('tags')
            ->where('tags', '!=', '')
            ->pluck('tags');
        $tags = [];
        foreach ($rows as $r) {
            foreach (array_map('trim', explode(',', $r)) as $t) {
                if ($t !== '') $tags[$t] = true;
            }
        }
        return array_keys($tags);
    } catch (\Exception $e) {
        return [];
    }
}
}

if (!function_exists('noticebanner_get_read_counts')) {
function noticebanner_get_read_counts(int $noticeId): array {
    try {
        $rows = \WHMCS\Database\Capsule::table('mod_noticebanner_reads')
            ->where('notice_id', $noticeId)
            ->selectRaw('entity_type, COUNT(*) as cnt')
            ->groupBy('entity_type')
            ->get();
        $out = ['admins' => 0, 'clients' => 0];
        foreach ($rows as $r) {
            if ($r->entity_type === 'admin')  $out['admins']  = (int)$r->cnt;
            if ($r->entity_type === 'client') $out['clients'] = (int)$r->cnt;
        }
        return $out;
    } catch (\Exception $e) {
        return ['admins' => 0, 'clients' => 0];
    }
}
}

if (!function_exists('noticebanner_get_read_details')) {
function noticebanner_get_read_details(int $noticeId): array {
    try {
        $rows = \WHMCS\Database\Capsule::table('mod_noticebanner_reads')
            ->where('notice_id', $noticeId)
            ->orderBy('read_at', 'desc')
            ->get(['entity_type', 'entity_id', 'read_at'])
            ->toArray();

        $adminIds  = [];
        $clientIds = [];
        foreach ($rows as $r) {
            if ($r->entity_type === 'admin')  $adminIds[]  = (int)$r->entity_id;
            if ($r->entity_type === 'client') $clientIds[] = (int)$r->entity_id;
        }

        // Resolve names
        $adminNames  = [];
        $clientNames = [];
        if (!empty($adminIds)) {
            $aRows = \WHMCS\Database\Capsule::table('tbladmins')
                ->whereIn('id', $adminIds)->get(['id', 'firstname', 'lastname', 'username'])->toArray();
            foreach ($aRows as $a) $adminNames[(int)$a->id] = $a->firstname . ' ' . $a->lastname . ' (@' . $a->username . ')';
        }
        if (!empty($clientIds)) {
            $cRows = \WHMCS\Database\Capsule::table('tblclients')
                ->whereIn('id', $clientIds)->get(['id', 'firstname', 'lastname', 'email'])->toArray();
            foreach ($cRows as $c) $clientNames[(int)$c->id] = $c->firstname . ' ' . $c->lastname . ' (' . $c->email . ')';
        }

        $out = [];
        foreach ($rows as $r) {
            $eid  = (int)$r->entity_id;
            $name = $r->entity_type === 'admin'
                ? ($adminNames[$eid]  ?? 'Admin #' . $eid)
                : ($clientNames[$eid] ?? 'Client #' . $eid);
            $out[] = [
                'entity_type' => $r->entity_type,
                'entity_id'   => $eid,
                'name'        => $name,
                'read_at'     => $r->read_at,
            ];
        }
        return $out;
    } catch (\Exception $e) {
        return [];
    }
}
}

if (!function_exists('noticebanner_get_poll_voters')) {
function noticebanner_get_poll_voters(int $noticeId): array {
    try {
        $rows = \WHMCS\Database\Capsule::table('mod_noticebanner_poll_votes')
            ->where('notice_id', $noticeId)
            ->orderBy('voted_at', 'desc')
            ->get(['id', 'entity_type', 'entity_id', 'entity_label', 'poll_option', 'is_predefined', 'voted_at'])
            ->toArray();

        // Resolve any missing labels live (for rows inserted before caching was added)
        $adminIds  = [];
        $clientIds = [];
        foreach ($rows as $r) {
            if ($r->entity_label !== '') continue;
            if ($r->entity_type === 'admin'  && $r->entity_id) $adminIds[]  = (int)$r->entity_id;
            if ($r->entity_type === 'client' && $r->entity_id) $clientIds[] = (int)$r->entity_id;
        }
        $adminNames  = [];
        $clientNames = [];
        if (!empty($adminIds)) {
            $aRows = \WHMCS\Database\Capsule::table('tbladmins')
                ->whereIn('id', array_unique($adminIds))->get(['id', 'firstname', 'lastname', 'username'])->toArray();
            foreach ($aRows as $a) $adminNames[(int)$a->id] = trim($a->firstname . ' ' . $a->lastname) . ' (@' . $a->username . ')';
        }
        if (!empty($clientIds)) {
            $cRows = \WHMCS\Database\Capsule::table('tblclients')
                ->whereIn('id', array_unique($clientIds))->get(['id', 'firstname', 'lastname', 'email'])->toArray();
            foreach ($cRows as $c) $clientNames[(int)$c->id] = trim($c->firstname . ' ' . $c->lastname) . ' (' . $c->email . ')';
        }

        $out = [];
        foreach ($rows as $r) {
            $eid   = (int)$r->entity_id;
            $label = $r->entity_label;
            if ($label === '') {
                if ($r->entity_type === 'admin')       $label = $adminNames[$eid]  ?? 'Admin #' . $eid;
                elseif ($r->entity_type === 'client')  $label = $clientNames[$eid] ?? 'Client #' . $eid;
                else                                   $label = 'Predefined';
            }
            // For predefined rows entity_id holds the running vote count
            $voteCount = ($r->entity_type === 'predefined') ? max(1, $eid) : 1;
            $out[] = [
                'id'            => (int)$r->id,
                'entity_type'   => $r->entity_type,
                'entity_id'     => $eid,
                'label'         => $label,
                'poll_option'   => $r->poll_option,
                'is_predefined' => (bool)$r->is_predefined,
                'vote_count'    => $voteCount,
                'voted_at'      => $r->voted_at,
            ];
        }
        return $out;
    } catch (\Exception $e) {
        return [];
    }
}
}

if (!function_exists('noticebanner_get_admins')) {
function noticebanner_get_admins() {
    try {
        return \WHMCS\Database\Capsule::table('tbladmins')
            ->where('disabled', 0)
            ->orderBy('firstname')
            ->get(['id', 'firstname', 'lastname', 'username', 'email'])
            ->toArray();
    } catch (\Exception $e) {
        return [];
    }
}
}

if (!function_exists('noticebanner_get_departments')) {
function noticebanner_get_departments() {
    // WHMCS table name varies by version:
    //   tblticketdepartments  — modern WHMCS (v7+)
    //   tblsupportdepts       — older versions
    //   tblsupportdepartments — some forks/older installs
    foreach (['tblticketdepartments', 'tblsupportdepts', 'tblsupportdepartments'] as $tbl) {
        try {
            // Plain fetch first — confirms table exists and has rows
            $rows = \WHMCS\Database\Capsule::table($tbl)
                ->get(['id', 'name'])
                ->toArray();
            if (empty($rows)) continue;
            // Try each known sort-column name; fall back to PHP sort if none exist
            foreach (['sortorder', 'order', 'sort_order'] as $col) {
                try {
                    return \WHMCS\Database\Capsule::table($tbl)
                        ->orderBy($col)
                        ->orderBy('name')
                        ->get(['id', 'name'])
                        ->toArray();
                } catch (\Exception $e) {}
            }
            usort($rows, fn($a, $b) => strcmp($a->name, $b->name));
            return $rows;
        } catch (\Exception $e) {}
    }
    return [];
}
}

if (!function_exists('noticebanner_get_client_groups')) {
function noticebanner_get_client_groups() {
    try {
        return \WHMCS\Database\Capsule::table('tblclientgroups')
            ->orderBy('groupname')
            ->get(['id', 'groupname'])
            ->toArray();
    } catch (\Exception $e) {
        return [];
    }
}
}

if (!function_exists('noticebanner_get_servers')) {
function noticebanner_get_servers() {
    try {
        return \WHMCS\Database\Capsule::table('tblservers')
            ->orderBy('name')
            ->get(['id', 'name', 'hostname', 'type'])
            ->toArray();
    } catch (\Exception $e) {
        return [];
    }
}
}

if (!function_exists('noticebanner_get_products')) {
function noticebanner_get_products() {
    try {
        $rows = \WHMCS\Database\Capsule::table('tblproducts as p')
            ->leftJoin('tblproductgroups as g', 'p.gid', '=', 'g.id')
            ->orderBy('g.name')
            ->orderBy('p.name')
            ->get(['p.id', 'p.name', 'p.type', 'g.name as group_name'])
            ->toArray();
        return $rows;
    } catch (\Exception $e) {
        return [];
    }
}
}

if (!function_exists('noticebanner_search_clients')) {
function noticebanner_search_clients(string $q, int $limit = 20): array {
    if (strlen(trim($q)) < 2) return [];
    try {
        $term = '%' . trim($q) . '%';
        return \WHMCS\Database\Capsule::table('tblclients')
            ->where(function ($query) use ($term) {
                $query->where('firstname', 'like', $term)
                      ->orWhere('lastname', 'like', $term)
                      ->orWhere('email', 'like', $term)
                      ->orWhereRaw("CONCAT(firstname,' ',lastname) LIKE ?", [$term]);
            })
            ->orderBy('firstname')
            ->limit($limit)
            ->get(['id', 'firstname', 'lastname', 'email', 'companyname'])
            ->toArray();
    } catch (\Exception $e) {
        return [];
    }
}
}

if (!function_exists('noticebanner_get_clients_by_ids')) {
function noticebanner_get_clients_by_ids(array $ids): array {
    if (empty($ids)) return [];
    try {
        return \WHMCS\Database\Capsule::table('tblclients')
            ->whereIn('id', $ids)
            ->orderBy('firstname')
            ->get(['id', 'firstname', 'lastname', 'email'])
            ->toArray();
    } catch (\Exception $e) {
        return [];
    }
}
}

// ─── Build save payload from POST ────────────────────────────────────────────

if (!function_exists('noticebanner_build_payload')) {
function noticebanner_build_payload(): array {
    $assignedAdmins = isset($_POST['assigned_admins']) && is_array($_POST['assigned_admins'])
        ? array_values(array_unique(array_map('intval', $_POST['assigned_admins']))) : [];
    $pollOptions = isset($_POST['poll_options']) && is_array($_POST['poll_options'])
        ? array_values(array_filter(array_map(fn($v) => html_entity_decode(trim($v)), $_POST['poll_options']), fn($v) => $v !== '')) : [];
    $clientGroups   = isset($_POST['client_groups']) && is_array($_POST['client_groups'])
        ? array_values(array_unique(array_map('intval', $_POST['client_groups']))) : [];
    $targetClients  = isset($_POST['target_clients']) && is_array($_POST['target_clients'])
        ? array_values(array_unique(array_map('intval', $_POST['target_clients']))) : [];
    $targetServers  = isset($_POST['target_servers']) && is_array($_POST['target_servers'])
        ? array_values(array_unique(array_map('intval', $_POST['target_servers']))) : [];
    $targetProducts = isset($_POST['target_products']) && is_array($_POST['target_products'])
        ? array_values(array_unique(array_map('intval', $_POST['target_products']))) : [];
    $pageSlugs = isset($_POST['page_slugs_raw'])
        ? array_values(array_filter(array_map('trim', explode("\n", $_POST['page_slugs_raw'])), fn($v) => $v !== ''))
        : [];

    $tags = trim($_POST['tags'] ?? '');
    // Normalise: comma-separated, trimmed, lowercase
    if ($tags) {
        $tags = implode(',', array_filter(array_map(fn($t) => strtolower(trim($t)), explode(',', $tags))));
    }

    $isPro = noticebanner_license_is_pro();

    $base = [
        'notice_title'       => trim($_POST['notice_title'] ?? ''),
        'notice_content'     => $_POST['notice_content'] ?? '',
        'show_to_clients'    => isset($_POST['show_to_clients']) ? 1 : 0,
        'show_to_admins'     => isset($_POST['show_to_admins']) ? 1 : 0,
        'display_type'       => $_POST['display_type'] ?? 'banner',
        'show_again_minutes' => (int)($_POST['show_again_minutes'] ?? 60),
        'expandable'         => isset($_POST['expandable']) ? 1 : 0,
        'bg_color'           => $_POST['bg_color'] ?? '#fffae6',
        'font_color'         => $_POST['font_color'] ?? '#222222',
        'priority'           => $_POST['priority'] ?? 'normal',
        'notice_timestamp'   => !empty($_POST['notice_timestamp']) ? date('Y-m-d H:i:s', strtotime($_POST['notice_timestamp'])) : null,
        'updated_at'         => date('Y-m-d H:i:s'),
    ];

    // Pro-only fields — silently zeroed/nulled when not licensed
    $pro = $isPro ? [
        'button_enabled'       => isset($_POST['button_enabled']) ? 1 : 0,
        'button_text'          => $_POST['button_text'] ?? '',
        'button_link'          => $_POST['button_link'] ?? '',
        'button_newtab'        => isset($_POST['button_newtab']) ? 1 : 0,
        'button_bg'            => $_POST['button_bg'] ?? '#2563eb',
        'button_color'         => $_POST['button_color'] ?? '#ffffff',
        'ticket_enabled'       => isset($_POST['ticket_enabled']) ? 1 : 0,
        'ticket_department_id' => $_POST['ticket_department_id'] ?? '',
        'ticket_button_text'   => $_POST['ticket_button_text'] ?? '',
        'poll_enabled'         => isset($_POST['poll_enabled']) ? 1 : 0,
        'poll_question'        => $_POST['poll_question'] ?? '',
        'poll_options'         => json_encode($pollOptions),
        'assigned_admins'      => json_encode($assignedAdmins),
        'mentioned_admins'     => json_encode($assignedAdmins),
        'expires_at'           => !empty($_POST['expires_at']) ? date('Y-m-d H:i:s', strtotime($_POST['expires_at'])) : null,
        'publish_at'           => !empty($_POST['publish_at']) ? date('Y-m-d H:i:s', strtotime($_POST['publish_at'])) : null,
        'tags'                 => $tags,
        'client_groups'        => json_encode($clientGroups),
        'target_clients'       => json_encode($targetClients),
        'target_servers'       => json_encode($targetServers),
        'target_products'      => json_encode($targetProducts),
        'page_slugs'           => json_encode($pageSlugs),
        'webhook_url'          => trim($_POST['notice_webhook_url'] ?? ''),
        'is_pinned'            => isset($_POST['is_pinned']) ? 1 : 0,
    ] : [
        'button_enabled'       => 0,
        'button_text'          => '',
        'button_link'          => '',
        'button_newtab'        => 0,
        'button_bg'            => '#2563eb',
        'button_color'         => '#ffffff',
        'ticket_enabled'       => 0,
        'ticket_department_id' => '',
        'ticket_button_text'   => '',
        'poll_enabled'         => 0,
        'poll_question'        => '',
        'poll_options'         => json_encode([]),
        'assigned_admins'      => json_encode([]),
        'mentioned_admins'     => json_encode([]),
        'expires_at'           => null,
        'publish_at'           => null,
        'tags'                 => '',
        'client_groups'        => json_encode([]),
        'target_clients'       => json_encode([]),
        'target_servers'       => json_encode([]),
        'target_products'      => json_encode([]),
        'page_slugs'           => json_encode([]),
        'webhook_url'          => '',
        'is_pinned'            => 0,
    ];

    return array_merge($base, $pro);
}
}

if (!function_exists('noticebanner_todo_add_history')) {
function noticebanner_todo_add_history(int $todoId, string $action, $oldValue = null, $newValue = null): void {
    try {
        $adminId = !empty($_SESSION['adminid']) ? (int)$_SESSION['adminid'] : null;
        \WHMCS\Database\Capsule::table('mod_noticebanner_todo_history')->insert([
            'todo_id'    => $todoId,
            'action'     => $action,
            'admin_id'   => $adminId,
            'old_value'  => $oldValue === null ? null : json_encode($oldValue),
            'new_value'  => $newValue === null ? null : json_encode($newValue),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (\Exception $e) {}
}
}

if (!function_exists('noticebanner_get_todo_notice_id')) {
function noticebanner_get_todo_notice_id(int $todoId): int {
    try {
        $noticeId = (int)\WHMCS\Database\Capsule::table('mod_noticebanner_todos')->where('id', $todoId)->value('notice_id');
        return $noticeId;
    } catch (\Exception $e) {
        return 0;
    }
}
}

if (!function_exists('noticebanner_delete_todo_recursive')) {
function noticebanner_delete_todo_recursive(int $todoId): void {
    try {
        $children = \WHMCS\Database\Capsule::table('mod_noticebanner_todos')
            ->where('parent_todo_id', $todoId)
            ->pluck('id');
        foreach ($children as $childId) {
            noticebanner_delete_todo_recursive((int)$childId);
        }
        \WHMCS\Database\Capsule::table('mod_noticebanner_todo_history')->where('todo_id', $todoId)->delete();
        \WHMCS\Database\Capsule::table('mod_noticebanner_todos')->where('id', $todoId)->delete();
    } catch (\Exception $e) {}
}
}

if (!function_exists('noticebanner_sync_parent_todo_completion')) {
function noticebanner_sync_parent_todo_completion(int $parentTodoId, int $adminId = 0): void {
    if ($parentTodoId <= 0) return;
    try {
        $parent = \WHMCS\Database\Capsule::table('mod_noticebanner_todos')->where('id', $parentTodoId)->first();
        if (!$parent) return;
        $children = \WHMCS\Database\Capsule::table('mod_noticebanner_todos')
            ->where('parent_todo_id', $parentTodoId)
            ->get(['id', 'is_completed'])
            ->toArray();
        if (empty($children)) return;

        $allDone = true;
        foreach ($children as $child) {
            if (empty($child->is_completed)) {
                $allDone = false;
                break;
            }
        }

        $shouldComplete = $allDone ? 1 : 0;
        $current = (int)$parent->is_completed;
        if ($current === $shouldComplete) return;

        $completedAt = $shouldComplete ? date('Y-m-d H:i:s') : null;
        \WHMCS\Database\Capsule::table('mod_noticebanner_todos')->where('id', $parentTodoId)->update([
            'is_completed' => $shouldComplete,
            'completed_at' => $completedAt,
            'completed_by_admin_id' => $shouldComplete ? ($adminId > 0 ? $adminId : null) : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        noticebanner_todo_add_history($parentTodoId, $shouldComplete ? 'auto_checked_by_subtasks' : 'auto_unchecked_by_subtasks', ['is_completed' => $current], ['is_completed' => $shouldComplete]);
        noticebanner_log((int)$parent->notice_id, $shouldComplete ? 'todo_parent_auto_checked' : 'todo_parent_auto_unchecked', (string)$parent->title);
    } catch (\Exception $e) {}
}
}

if (!function_exists('noticebanner_todo_status_bucket')) {
function noticebanner_todo_status_bucket(array $todo): string {
    if (!empty($todo['is_completed'])) {
        return 'completed';
    }
    if (empty($todo['due_at'])) {
        return 'open';
    }
    $today = date('Y-m-d');
    $due = date('Y-m-d', strtotime((string)$todo['due_at']));
    if ($due < $today) return 'overdue';
    if ($due === $today) return 'due_today';
    return 'upcoming';
}
}

if (!function_exists('noticebanner_get_admin_name_map')) {
function noticebanner_get_admin_name_map(array $ids): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (empty($ids)) return [];
    try {
        $rows = \WHMCS\Database\Capsule::table('tbladmins')
            ->whereIn('id', $ids)
            ->get(['id', 'firstname', 'lastname', 'username'])
            ->toArray();
        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r->id] = trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? '')) . ' (@' . ($r->username ?? '') . ')';
        }
        return $map;
    } catch (\Exception $e) {
        return [];
    }
}
}

if (!function_exists('noticebanner_get_notice_title_map')) {
function noticebanner_get_notice_title_map(array $ids): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (empty($ids)) return [];
    try {
        $rows = \WHMCS\Database\Capsule::table('mod_noticebanner')
            ->whereIn('id', $ids)
            ->get(['id', 'notice_title'])
            ->toArray();
        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r->id] = (string)($r->notice_title ?? ('Notice #' . $r->id));
        }
        return $map;
    } catch (\Exception $e) {
        return [];
    }
}
}

if (!function_exists('noticebanner_get_todos_for_notice')) {
function noticebanner_get_todos_for_notice(int $noticeId): array {
    if ($noticeId <= 0) return [];
    try {
        $rows = \WHMCS\Database\Capsule::table('mod_noticebanner_todos')
            ->where('notice_id', $noticeId)
            ->orderBy('parent_todo_id', 'asc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();
        $todos = [];
        foreach ($rows as $row) {
            $item = (array)$row;
            $item['id'] = (int)$item['id'];
            $item['notice_id'] = (int)$item['notice_id'];
            $item['parent_todo_id'] = $item['parent_todo_id'] ? (int)$item['parent_todo_id'] : null;
            $item['is_completed'] = (int)$item['is_completed'];
            $item['sort_order'] = (int)$item['sort_order'];
            $item['children'] = [];
            $item['status_bucket'] = noticebanner_todo_status_bucket($item);
            $todos[$item['id']] = $item;
        }
        $tree = [];
        foreach ($todos as $id => $todo) {
            $parentId = $todo['parent_todo_id'];
            if ($parentId && isset($todos[$parentId])) {
                $todos[$parentId]['children'][] = $todo;
                continue;
            }
            $tree[] = $todo;
        }
        return $tree;
    } catch (\Exception $e) {
        return [];
    }
}
}

if (!function_exists('noticebanner_get_todos_flat')) {
function noticebanner_get_todos_flat(array $filters = []): array {
    try {
        $q = \WHMCS\Database\Capsule::table('mod_noticebanner_todos')
            ->orderBy('is_completed', 'asc')
            ->orderBy('due_at', 'asc')
            ->orderBy('id', 'desc');

        $noticeId = (int)($filters['notice_id'] ?? 0);
        if ($noticeId > 0) $q->where('notice_id', $noticeId);

        $status = trim((string)($filters['status'] ?? 'all'));
        if ($status === 'completed') {
            $q->where('is_completed', 1);
        } elseif ($status === 'open') {
            $q->where('is_completed', 0);
        } elseif ($status === 'overdue') {
            $q->where('is_completed', 0)->whereNotNull('due_at')->where('due_at', '<', date('Y-m-d 00:00:00'));
        } elseif ($status === 'due_today') {
            $q->where('is_completed', 0)->whereDate('due_at', '=', date('Y-m-d'));
        }

        $dueFrom = trim((string)($filters['due_from'] ?? ''));
        $dueTo = trim((string)($filters['due_to'] ?? ''));
        if ($dueFrom !== '') $q->where('due_at', '>=', date('Y-m-d 00:00:00', strtotime($dueFrom)));
        if ($dueTo !== '') $q->where('due_at', '<=', date('Y-m-d 23:59:59', strtotime($dueTo)));

        $completedFrom = trim((string)($filters['completed_from'] ?? ''));
        $completedTo = trim((string)($filters['completed_to'] ?? ''));
        if ($completedFrom !== '') $q->where('completed_at', '>=', date('Y-m-d 00:00:00', strtotime($completedFrom)));
        if ($completedTo !== '') $q->where('completed_at', '<=', date('Y-m-d 23:59:59', strtotime($completedTo)));

        $rows = $q->get()->toArray();
        $out = [];
        foreach ($rows as $row) {
            $item = (array)$row;
            $item['id'] = (int)$item['id'];
            $item['notice_id'] = (int)$item['notice_id'];
            $item['parent_todo_id'] = $item['parent_todo_id'] ? (int)$item['parent_todo_id'] : null;
            $item['is_completed'] = (int)$item['is_completed'];
            $item['created_by_admin_id'] = $item['created_by_admin_id'] ? (int)$item['created_by_admin_id'] : null;
            $item['completed_by_admin_id'] = $item['completed_by_admin_id'] ? (int)$item['completed_by_admin_id'] : null;
            $item['status_bucket'] = noticebanner_todo_status_bucket($item);
            $out[] = $item;
        }
        return $out;
    } catch (\Exception $e) {
        return [];
    }
}
}

// ─── Admin Output ────────────────────────────────────────────────────────────

if (!function_exists('noticebanner_output')) {
function noticebanner_output($vars) {
    noticebanner_ensure_table();
    noticebanner_ensure_columns();

    $licenseDiagnosticsOutput = '';

    // ── Export poll votes: ?nb_export_votes=<id>&format=csv|json (Pro) ──
    if (!empty($_GET['nb_export_votes'])) {
        if (!noticebanner_license_is_pro()) {
            header('HTTP/1.1 403 Forbidden');
            echo 'Pro license required for export.';
            exit;
        }
        $nid    = (int)$_GET['nb_export_votes'];
        $format = strtolower(trim($_GET['format'] ?? 'csv'));

        // Load the notice for its question/options
        $notice = \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $nid)->first();
        $question = $notice ? ($notice->poll_question ?? 'Poll') : 'Poll';
        $aggResults = $notice ? (json_decode($notice->poll_results ?? '{}', true) ?: []) : [];
        $totalVotes = array_sum($aggResults);

        $voters = noticebanner_get_poll_voters($nid);

        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($question));
        $slug = trim($slug, '-') ?: 'poll';
        $filename = 'poll-votes-' . $nid . '-' . preg_replace('/[^a-z0-9._-]+/i', '', $slug);

        if ($format === 'json') {
            // ── JSON export ──
            $export = [
                'notice_id'    => $nid,
                'question'     => $question,
                'exported_at'  => date('Y-m-d H:i:s'),
                'summary'      => [],
                'votes'        => [],
            ];
            foreach ($aggResults as $opt => $cnt) {
                $pct = $totalVotes > 0 ? round(($cnt / $totalVotes) * 100, 1) : 0;
                $export['summary'][] = [
                    'option'     => $opt,
                    'votes'      => $cnt,
                    'percentage' => $pct,
                ];
            }
            foreach ($voters as $v) {
                $export['votes'][] = [
                    'type'          => $v['entity_type'],
                    'name'          => $v['label'],
                    'option'        => $v['poll_option'],
                    'is_predefined' => $v['is_predefined'],
                    'vote_count'    => $v['vote_count'],
                    'voted_at'      => $v['voted_at'],
                ];
            }
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '.json"');
            echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        // ── CSV export (default) ──
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        // UTF-8 BOM so Excel opens it correctly
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');

        // Section 1: Summary
        fputcsv($out, ['=== SUMMARY ===']);
        fputcsv($out, ['Notice ID', 'Question', 'Total Votes', 'Exported At']);
        fputcsv($out, [$nid, $question, $totalVotes, date('Y-m-d H:i:s')]);
        fputcsv($out, []);
        fputcsv($out, ['Option', 'Votes', 'Percentage']);
        foreach ($aggResults as $opt => $cnt) {
            $pct = $totalVotes > 0 ? round(($cnt / $totalVotes) * 100, 1) : 0;
            fputcsv($out, [$opt, $cnt, $pct . '%']);
        }
        fputcsv($out, []);

        // Section 2: Individual votes
        fputcsv($out, ['=== INDIVIDUAL VOTES ===']);
        fputcsv($out, ['Type', 'Name / Label', 'Option Voted', 'Is Predefined', 'Vote Count', 'Voted At']);
        foreach ($voters as $v) {
            fputcsv($out, [
                ucfirst($v['entity_type']),
                $v['label'],
                $v['poll_option'],
                $v['is_predefined'] ? 'Yes' : 'No',
                $v['vote_count'],
                $v['voted_at'],
            ]);
        }
        fclose($out);
        exit;
    }

    if (!class_exists('NoticeBannerHelper')) {
        require_once __DIR__ . '/hooks.php';
    }

    $notices      = noticebanner_get_notices();
    $departments  = noticebanner_get_departments();
    $admins       = noticebanner_get_admins();
    $clientGroups = noticebanner_get_client_groups();
    $servers      = noticebanner_get_servers();
    $products     = noticebanner_get_products();
    $allTags      = noticebanner_get_all_tags();
    $templates    = noticebanner_get_templates();

    $edit_notice = null;
    $message     = '';

    if (!empty($_GET['edit_id'])) {
        $id = (int)$_GET['edit_id'];
        if ($id > 0) {
            $row = \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $id)->first();
            if ($row) {
                $edit_notice = (array)$row;
                $edit_notice['poll_options']    = array_map('html_entity_decode', json_decode($edit_notice['poll_options'] ?? '[]', true) ?: []);
                $rawRes = json_decode($edit_notice['poll_results'] ?? '{}', true) ?: [];
                $decRes = [];
                foreach ($rawRes as $k => $v) $decRes[html_entity_decode($k)] = $v;
                $edit_notice['poll_results']    = $decRes;
                $edit_notice['assigned_admins'] = json_decode($edit_notice['assigned_admins'] ?? '[]', true) ?: [];
                $edit_notice['client_groups']   = json_decode($edit_notice['client_groups'] ?? '[]', true) ?: [];
                $edit_notice['target_clients']  = json_decode($edit_notice['target_clients'] ?? '[]', true) ?: [];
                $edit_notice['target_servers']  = json_decode($edit_notice['target_servers'] ?? '[]', true) ?: [];
                $edit_notice['target_products'] = json_decode($edit_notice['target_products'] ?? '[]', true) ?: [];
                $edit_notice['page_slugs']      = json_decode($edit_notice['page_slugs'] ?? '[]', true) ?: [];
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // ── Client search (AJAX) ──
        if (isset($_POST['nb_client_search'])) {
            header('Content-Type: application/json');
            $results = noticebanner_search_clients($_POST['nb_client_search'] ?? '');
            $out = [];
            foreach ($results as $c) {
                $out[] = [
                    'id'   => (int)$c->id,
                    'text' => $c->firstname . ' ' . $c->lastname . ' (' . $c->email . ')'
                        . (!empty($c->companyname) ? ' — ' . $c->companyname : ''),
                ];
            }
            echo json_encode($out);
            exit;
        }

        // ── To-Do actions (admin only) ──
        if (isset($_POST['nb_todo_action'])) {
            $action = trim((string)$_POST['nb_todo_action']);
            $isAjax = isset($_POST['nb_todo_ajax']) || !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
            $resp = ['ok' => false, 'message' => 'Invalid to-do request'];

            try {
                $adminId = !empty($_SESSION['adminid']) ? (int)$_SESSION['adminid'] : 0;
                if ($adminId <= 0) {
                    throw new \RuntimeException('Admin login required');
                }

                if ($action === 'add') {
                    $noticeId = (int)($_POST['todo_notice_id'] ?? 0);
                    $parentTodoId = (int)($_POST['todo_parent_todo_id'] ?? 0);
                    $title = trim((string)($_POST['todo_title'] ?? ''));
                    $remarks = trim((string)($_POST['todo_remarks'] ?? ''));
                    $dueAtRaw = trim((string)($_POST['todo_due_at'] ?? ''));
                    if ($noticeId <= 0 || $title === '') {
                        throw new \RuntimeException('Task title and notice are required');
                    }
                    $sortOrder = (int)\WHMCS\Database\Capsule::table('mod_noticebanner_todos')
                        ->where('notice_id', $noticeId)
                        ->where('parent_todo_id', $parentTodoId > 0 ? $parentTodoId : null)
                        ->max('sort_order') + 1;
                    $todoId = (int)\WHMCS\Database\Capsule::table('mod_noticebanner_todos')->insertGetId([
                        'notice_id'            => $noticeId,
                        'parent_todo_id'       => $parentTodoId > 0 ? $parentTodoId : null,
                        'title'                => $title,
                        'remarks'              => $remarks ?: null,
                        'is_completed'         => 0,
                        'due_at'               => $dueAtRaw !== '' ? date('Y-m-d H:i:s', strtotime($dueAtRaw)) : null,
                        'completed_at'         => null,
                        'created_by_admin_id'  => $adminId,
                        'completed_by_admin_id'=> null,
                        'sort_order'           => $sortOrder,
                        'created_at'           => date('Y-m-d H:i:s'),
                        'updated_at'           => date('Y-m-d H:i:s'),
                    ]);
                    noticebanner_todo_add_history($todoId, $parentTodoId > 0 ? 'subtask_created' : 'created', null, ['title' => $title]);
                    noticebanner_log($noticeId, 'todo_created', ($parentTodoId > 0 ? 'Subtask: ' : 'Task: ') . $title);
                    if ($parentTodoId > 0) {
                        noticebanner_sync_parent_todo_completion($parentTodoId, $adminId);
                    }
                    $resp = ['ok' => true, 'message' => 'Task added'];
                } elseif ($action === 'toggle') {
                    $todoId = (int)($_POST['todo_id'] ?? 0);
                    $row = \WHMCS\Database\Capsule::table('mod_noticebanner_todos')->where('id', $todoId)->first();
                    if (!$row) throw new \RuntimeException('Task not found');
                    $newCompleted = $row->is_completed ? 0 : 1;
                    $completedAt = $newCompleted ? date('Y-m-d H:i:s') : null;
                    \WHMCS\Database\Capsule::table('mod_noticebanner_todos')->where('id', $todoId)->update([
                        'is_completed' => $newCompleted,
                        'completed_at' => $completedAt,
                        'completed_by_admin_id' => $newCompleted ? $adminId : null,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    noticebanner_todo_add_history($todoId, $newCompleted ? 'checked' : 'unchecked', ['is_completed' => (int)$row->is_completed], ['is_completed' => $newCompleted]);
                    noticebanner_log((int)$row->notice_id, $newCompleted ? 'todo_checked' : 'todo_unchecked', $row->title);
                    if (!empty($row->parent_todo_id)) {
                        noticebanner_sync_parent_todo_completion((int)$row->parent_todo_id, $adminId);
                    }
                    $resp = ['ok' => true, 'message' => 'Task updated'];
                } elseif ($action === 'update_due') {
                    $todoId = (int)($_POST['todo_id'] ?? 0);
                    $dueAtRaw = trim((string)($_POST['todo_due_at'] ?? ''));
                    $row = \WHMCS\Database\Capsule::table('mod_noticebanner_todos')->where('id', $todoId)->first();
                    if (!$row) throw new \RuntimeException('Task not found');
                    $newDue = $dueAtRaw !== '' ? date('Y-m-d H:i:s', strtotime($dueAtRaw)) : null;
                    \WHMCS\Database\Capsule::table('mod_noticebanner_todos')->where('id', $todoId)->update([
                        'due_at' => $newDue,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    noticebanner_todo_add_history($todoId, 'duedate_changed', ['due_at' => $row->due_at], ['due_at' => $newDue]);
                    noticebanner_log((int)$row->notice_id, 'todo_due_updated', $row->title);
                    $resp = ['ok' => true, 'message' => 'Due date updated'];
                } elseif ($action === 'update_remarks') {
                    $todoId = (int)($_POST['todo_id'] ?? 0);
                    $remarks = trim((string)($_POST['todo_remarks'] ?? ''));
                    $row = \WHMCS\Database\Capsule::table('mod_noticebanner_todos')->where('id', $todoId)->first();
                    if (!$row) throw new \RuntimeException('Task not found');
                    \WHMCS\Database\Capsule::table('mod_noticebanner_todos')->where('id', $todoId)->update([
                        'remarks' => $remarks !== '' ? $remarks : null,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    noticebanner_todo_add_history($todoId, 'remarked', ['remarks' => (string)$row->remarks], ['remarks' => $remarks]);
                    noticebanner_log((int)$row->notice_id, 'todo_remarks_updated', $row->title);
                    $resp = ['ok' => true, 'message' => 'Remarks updated'];
                } elseif ($action === 'delete') {
                    $todoId = (int)($_POST['todo_id'] ?? 0);
                    $row = \WHMCS\Database\Capsule::table('mod_noticebanner_todos')->where('id', $todoId)->first();
                    if (!$row) throw new \RuntimeException('Task not found');
                    $parentTodoId = (int)($row->parent_todo_id ?? 0);
                    noticebanner_todo_add_history($todoId, 'deleted', ['title' => $row->title], null);
                    noticebanner_delete_todo_recursive($todoId);
                    noticebanner_log((int)$row->notice_id, 'todo_deleted', $row->title);
                    if ($parentTodoId > 0) {
                        noticebanner_sync_parent_todo_completion($parentTodoId, $adminId);
                    }
                    $resp = ['ok' => true, 'message' => 'Task deleted'];
                } elseif ($action === 'reorder') {
                    $todoId = (int)($_POST['todo_id'] ?? 0);
                    $direction = ($_POST['todo_direction'] ?? 'up') === 'down' ? 'down' : 'up';
                    $row = \WHMCS\Database\Capsule::table('mod_noticebanner_todos')->where('id', $todoId)->first();
                    if (!$row) throw new \RuntimeException('Task not found');
                    $siblings = \WHMCS\Database\Capsule::table('mod_noticebanner_todos')
                        ->where('notice_id', (int)$row->notice_id)
                        ->where('parent_todo_id', $row->parent_todo_id)
                        ->orderBy('sort_order', 'asc')
                        ->orderBy('id', 'asc')
                        ->get(['id', 'sort_order'])
                        ->toArray();
                    $ids = array_map(fn($s) => (int)$s->id, $siblings);
                    $pos = array_search($todoId, $ids, true);
                    if ($pos !== false) {
                        $swapPos = $direction === 'up' ? $pos - 1 : $pos + 1;
                        if (isset($siblings[$swapPos])) {
                            $currentSort = (int)$siblings[$pos]->sort_order;
                            $targetSort = (int)$siblings[$swapPos]->sort_order;
                            \WHMCS\Database\Capsule::table('mod_noticebanner_todos')->where('id', $todoId)->update(['sort_order' => $targetSort]);
                            \WHMCS\Database\Capsule::table('mod_noticebanner_todos')->where('id', (int)$siblings[$swapPos]->id)->update(['sort_order' => $currentSort]);
                            noticebanner_todo_add_history($todoId, 'reordered', ['sort_order' => $currentSort], ['sort_order' => $targetSort]);
                        }
                    }
                    $resp = ['ok' => true, 'message' => 'Task reordered'];
                }
            } catch (\Throwable $e) {
                $resp = ['ok' => false, 'message' => $e->getMessage()];
            }

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode($resp);
                exit;
            }
            if (!empty($_POST['todo_redirect_notice_id'])) {
                $nid = (int)$_POST['todo_redirect_notice_id'];
                header('Location: addonmodules.php?module=noticebanner&todo_notice_id=' . $nid . '#nb-todo-banners');
                exit;
            }
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }

        // ── Poll vote (legacy non-AJAX fallback — real votes now go via hook) ──
        if (isset($_POST['poll_vote'], $_POST['poll_notice_id']) && empty($_POST['nb_poll_vote'])) {
            $nid  = (int)$_POST['poll_notice_id'];
            $vote = $_POST['poll_vote'];
            try {
                $row = \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $nid)->first();
                if ($row) {
                    $results        = json_decode($row->poll_results ?? '{}', true) ?: [];
                    $results[$vote] = ($results[$vote] ?? 0) + 1;
                    \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $nid)
                        ->update(['poll_results' => json_encode($results), 'updated_at' => date('Y-m-d H:i:s')]);
                    noticebanner_log($nid, 'poll_vote', "Voted: $vote");
                }
            } catch (\Exception $e) {}
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }

        // ── Predefined votes (admin only, Pro) ────────────────────────────────
        // Form sends parallel arrays: predefined_poll_option_hex[] + predefined_poll_add_counts[]
        if (isset($_POST['predefined_poll_vote'], $_POST['predefined_poll_notice_id'])) {
            if (!noticebanner_license_is_pro()) {
                $message = '<div class="nb-alert nb-alert-danger">&#128274; Predefined votes require a Pro license.</div>';
                goto nb_post_end;
            }
            $nid   = (int)$_POST['predefined_poll_notice_id'];
            $label = trim($_POST['predefined_poll_label'] ?? '') ?: 'Predefined Vote';
            $hexes = isset($_POST['predefined_poll_option_hex']) && is_array($_POST['predefined_poll_option_hex'])
                     ? $_POST['predefined_poll_option_hex'] : [];
            $adds  = isset($_POST['predefined_poll_add_counts']) && is_array($_POST['predefined_poll_add_counts'])
                     ? $_POST['predefined_poll_add_counts'] : [];

            if ($nid && !empty($hexes)) {
                try {
                    $row = \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $nid)->first();
                    if ($row) {
                        // Whitelist: option strings for this notice (decoded + trimmed)
                        $allowed = array_map(
                            fn($o) => html_entity_decode(trim((string)$o), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                            json_decode($row->poll_options ?? '[]', true) ?: []
                        );
                        $allowed = array_values(array_filter($allowed, fn($o) => $o !== ''));

                        $results = json_decode($row->poll_results ?? '{}', true) ?: [];
                        $cleanResults = [];
                        foreach ($results as $k => $v) {
                            $cleanResults[html_entity_decode($k, ENT_QUOTES | ENT_HTML5, 'UTF-8')] = (int)$v;
                        }
                        $now     = date('Y-m-d H:i:s');
                        $applied = 0;

                        foreach ($hexes as $i => $hexRaw) {
                            $count = max(0, (int)($adds[$i] ?? 0));
                            if ($count <= 0) continue;

                            $hex = strtolower(preg_replace('/[^0-9a-f]/', '', (string)$hexRaw));
                            if ($hex === '' || (strlen($hex) % 2) !== 0) continue;

                            $opt = @hex2bin($hex);
                            if ($opt === false || $opt === '') continue;
                            $opt = trim(html_entity_decode($opt, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                            // Must match a real poll option (prevents tampered POST)
                            if (!in_array($opt, $allowed, true)) continue;

                            $cleanResults[$opt] = ($cleanResults[$opt] ?? 0) + $count;

                            $existing = \WHMCS\Database\Capsule::table('mod_noticebanner_poll_votes')
                                ->where('notice_id',     $nid)
                                ->where('is_predefined', 1)
                                ->where('entity_label',  $label)
                                ->where('poll_option',   $opt)
                                ->first(['id', 'entity_id']);

                            if ($existing) {
                                \WHMCS\Database\Capsule::table('mod_noticebanner_poll_votes')
                                    ->where('id', $existing->id)
                                    ->update(['entity_id' => (int)$existing->entity_id + $count, 'voted_at' => $now]);
                            } else {
                                \WHMCS\Database\Capsule::table('mod_noticebanner_poll_votes')->insert([
                                    'notice_id'     => $nid,
                                    'entity_type'   => 'predefined',
                                    'entity_id'     => $count,
                                    'entity_label'  => $label,
                                    'poll_option'   => $opt,
                                    'is_predefined' => 1,
                                    'voted_at'      => $now,
                                ]);
                            }
                            noticebanner_log($nid, 'predefined_poll_vote', "Option: $opt, +$count, Label: $label");
                            $applied++;
                        }

                        if ($applied > 0) {
                            \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $nid)
                                ->update(['poll_results' => json_encode($cleanResults), 'updated_at' => $now]);
                        }
                    }
                } catch (\Exception $e) {}
            }
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }

        // ── Delete single vote record (admin only) ──
        if (isset($_POST['delete_poll_vote'], $_POST['delete_poll_vote_id'])) {
            $vid = (int)$_POST['delete_poll_vote_id'];
            $nid = (int)($_POST['delete_poll_notice_id'] ?? 0);
            if ($vid) {
                try {
                    // Get the vote option before deleting so we can decrement the counter
                    $vrow = \WHMCS\Database\Capsule::table('mod_noticebanner_poll_votes')->where('id', $vid)->first();
                    if ($vrow) {
                        \WHMCS\Database\Capsule::table('mod_noticebanner_poll_votes')->where('id', $vid)->delete();
                        // For predefined rows entity_id holds the running count; for real votes it's 1
                        $decrement = ($vrow->entity_type === 'predefined') ? max(1, (int)$vrow->entity_id) : 1;
                        $nrow = \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $vrow->notice_id)->first();
                        if ($nrow) {
                            $results = json_decode($nrow->poll_results ?? '{}', true) ?: [];
                            $opt     = $vrow->poll_option;
                            if (isset($results[$opt])) {
                                $results[$opt] = max(0, $results[$opt] - $decrement);
                            }
                            \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $vrow->notice_id)
                                ->update(['poll_results' => json_encode($results), 'updated_at' => date('Y-m-d H:i:s')]);
                        }
                        noticebanner_log($vrow->notice_id, 'poll_vote_deleted', "Vote #$vid removed (-$decrement)");
                    }
                } catch (\Exception $e) {}
            }
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }

        // ── Reset poll results (admin only) ──
        if (isset($_POST['reset_poll'], $_POST['reset_poll_id'])) {
            $nid = (int)$_POST['reset_poll_id'];
            if ($nid) {
                try {
                    \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $nid)
                        ->update(['poll_results' => json_encode([]), 'updated_at' => date('Y-m-d H:i:s')]);
                    \WHMCS\Database\Capsule::table('mod_noticebanner_poll_votes')->where('notice_id', $nid)->delete();
                    noticebanner_log($nid, 'poll_reset', 'Poll results and vote records cleared');
                } catch (\Exception $e) {}
            }
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }

        // ── Mark read (supports both AJAX fetch and normal POST) ──
        if (isset($_POST['mark_read'], $_POST['mark_read_id'])) {
            $nid  = (int)$_POST['mark_read_id'];
            $type = $_POST['mark_read_type'] ?? 'admin';
            $eid  = (int)($_POST['mark_read_entity'] ?? ($_SESSION['adminid'] ?? 0));
            $ok   = false;
            try {
                \WHMCS\Database\Capsule::table('mod_noticebanner_reads')->updateOrInsert(
                    ['notice_id' => $nid, 'entity_type' => $type, 'entity_id' => $eid],
                    ['read_at' => date('Y-m-d H:i:s')]
                );
                noticebanner_log($nid, 'acknowledged', "Type: $type, Entity: $eid");
                $ok = true;
            } catch (\Exception $e) {}
            // If called via fetch (AJAX), return JSON; otherwise redirect
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
                      (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
                      // fetch() sends no special header by default — detect by absence of full HTML accept
                      (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false);
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => $ok]);
                exit;
            }
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }

        // ── Remove acknowledgement ──
        if (isset($_POST['remove_ack'])) {
            $nid  = (int)($_POST['remove_ack_id'] ?? 0);
            $type = $_POST['remove_ack_type'] ?? 'admin';
            $eid  = (int)($_POST['remove_ack_entity'] ?? 0);
            if ($nid && $eid) {
                try {
                    \WHMCS\Database\Capsule::table('mod_noticebanner_reads')
                        ->where('notice_id', $nid)
                        ->where('entity_type', $type)
                        ->where('entity_id', $eid)
                        ->delete();
                    noticebanner_log($nid, 'ack_removed', "Type: $type, Entity: $eid");
                } catch (\Exception $e) {}
            }
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }

        // ── Add predefined acknowledgement (Pro) ──
        if (isset($_POST['add_predefined_ack'])) {
            if (!noticebanner_license_is_pro()) {
                $message = '<div class="nb-alert nb-alert-danger">&#128274; Predefined acknowledgements require a Pro license.</div>';
                goto nb_post_end;
            }
            $nid  = (int)($_POST['predefined_ack_notice_id'] ?? 0);
            $type = $_POST['predefined_ack_type'] ?? 'admin';
            $eids = isset($_POST['predefined_ack_entities']) && is_array($_POST['predefined_ack_entities'])
                ? array_map('intval', $_POST['predefined_ack_entities']) : [];
            if ($nid && !empty($eids)) {
                foreach ($eids as $eid) {
                    try {
                        \WHMCS\Database\Capsule::table('mod_noticebanner_reads')->updateOrInsert(
                            ['notice_id' => $nid, 'entity_type' => $type, 'entity_id' => $eid],
                            ['read_at' => date('Y-m-d H:i:s')]
                        );
                    } catch (\Exception $e) {}
                }
                noticebanner_log($nid, 'predefined_ack_added', "Type: $type, Count: " . count($eids));
                $message = '<div class="nb-alert nb-alert-success">Added ' . count($eids) . ' acknowledgement(s).</div>';
            }
        }

        // ── Save notice (add or edit) ──
        if (isset($_POST['create_todo_banner'])) {
            $title = trim((string)($_POST['todo_banner_title'] ?? ''));
            $content = trim((string)($_POST['todo_banner_content'] ?? ''));
            if ($title === '') {
                $message = '<div class="nb-alert nb-alert-danger">To-Do banner title is required.</div>';
            } elseif (noticebanner_free_cap_reached()) {
                $cap = noticebanner_free_notice_cap();
                $message = '<div class="nb-alert nb-alert-danger">Free tier limit reached (' . htmlspecialchars((string)$cap) . '). Delete a notice or upgrade to Pro.</div>';
            } else {
                try {
                    \WHMCS\Database\Capsule::table('mod_noticebanner')->increment('sort_order');
                    $newId = \WHMCS\Database\Capsule::table('mod_noticebanner')->insertGetId([
                        'notice_title'         => $title,
                        'notice_content'       => $content,
                        'show_to_clients'      => 0,
                        'show_to_admins'       => 1,
                        'display_type'         => 'banner',
                        'show_again_minutes'   => 60,
                        'expandable'           => 0,
                        'bg_color'             => '#e0f2fe',
                        'font_color'           => '#0f172a',
                        'button_enabled'       => 0,
                        'button_text'          => '',
                        'button_link'          => '',
                        'button_newtab'        => 0,
                        'button_bg'            => '#2563eb',
                        'button_color'         => '#ffffff',
                        'ticket_enabled'       => 0,
                        'ticket_department_id' => '',
                        'ticket_button_text'   => '',
                        'poll_enabled'         => 0,
                        'poll_question'        => '',
                        'poll_options'         => json_encode([]),
                        'poll_results'         => json_encode([]),
                        'assigned_admins'      => json_encode([]),
                        'mentioned_admins'     => json_encode([]),
                        'priority'             => 'normal',
                        'notice_timestamp'     => null,
                        'sort_order'           => 0,
                        'expires_at'           => null,
                        'tags'                 => 'todo',
                        'client_groups'        => json_encode([]),
                        'is_template'          => 0,
                        'template_name'        => '',
                        'publish_at'           => null,
                        'webhook_url'          => '',
                        'page_slugs'           => json_encode([]),
                        'is_pinned'            => 0,
                        'is_todo_banner'       => 1,
                        'target_clients'       => json_encode([]),
                        'target_servers'       => json_encode([]),
                        'target_products'      => json_encode([]),
                        'created_at'           => date('Y-m-d H:i:s'),
                        'updated_at'           => date('Y-m-d H:i:s'),
                    ]);
                    noticebanner_log($newId, 'todo_banner_created', $title);
                    $message = '<div class="nb-alert nb-alert-success">To-Do banner created. You can now add tasks in the To-Do tab.</div>';
                } catch (\Exception $e) {
                    $message = '<div class="nb-alert nb-alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
        if (isset($_POST['save_todo_banner_quick'])) {
            $bannerId = (int)($_POST['todo_banner_edit_id'] ?? 0);
            $title = trim((string)($_POST['todo_banner_edit_title'] ?? ''));
            $content = trim((string)($_POST['todo_banner_edit_content'] ?? ''));
            $visibleAdmins = isset($_POST['todo_banner_edit_visible_admins']) ? 1 : 0;
            if ($bannerId <= 0 || $title === '') {
                $message = '<div class="nb-alert nb-alert-danger">Banner title is required.</div>';
            } else {
                try {
                    \WHMCS\Database\Capsule::table('mod_noticebanner')
                        ->where('id', $bannerId)
                        ->where('is_todo_banner', 1)
                        ->update([
                            'notice_title'   => $title,
                            'notice_content' => $content,
                            'show_to_admins' => $visibleAdmins,
                            'show_to_clients'=> 0,
                            'updated_at'     => date('Y-m-d H:i:s'),
                        ]);
                    noticebanner_log($bannerId, 'todo_banner_updated', $title);
                    $message = '<div class="nb-alert nb-alert-success">To-Do banner updated.</div>';
                } catch (\Exception $e) {
                    $message = '<div class="nb-alert nb-alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
        // ── Save notice (add or edit) ──
        if (isset($_POST['save_notice'])) {
            $payload = noticebanner_build_payload();
            $editId  = (int)($_POST['edit_id'] ?? 0);

            // Free-tier cap: block new notices when limit reached and no Pro license
            if ($editId === 0 && noticebanner_free_cap_reached()) {
                $cap = noticebanner_free_notice_cap();
                $message = '<div class="nb-alert nb-alert-danger">'
                    . '&#128274; Free tier limit reached ('
                    . htmlspecialchars((string)$cap)
                    . ' active notice' . ($cap === 1 ? '' : 's') . '). '
                    . '<a href="https://hostingspell.com" target="_blank" rel="noopener">Upgrade to Pro</a> '
                    . 'or delete an existing notice to continue.</div>';
            } else {
                try {
                    if ($editId > 0) {
                        \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $editId)->update($payload);
                        noticebanner_log($editId, 'updated', $payload['notice_title']);
                        $saved = array_merge(['id' => $editId], $payload);
                        noticebanner_fire_webhook($saved, 'notice.updated');
                        $message = '<div class="nb-alert nb-alert-success">Notice updated successfully.</div>';
                    } else {
                        $payload['poll_results'] = json_encode([]);
                        $payload['sort_order']   = 0;
                        $payload['is_template']  = 0;
                        $payload['template_name'] = '';
                        $payload['created_at']   = date('Y-m-d H:i:s');
                        \WHMCS\Database\Capsule::table('mod_noticebanner')->increment('sort_order');
                        $newId = \WHMCS\Database\Capsule::table('mod_noticebanner')->insertGetId($payload);
                        noticebanner_log($newId, 'created', $payload['notice_title']);
                        $saved = array_merge(['id' => $newId], $payload);
                        noticebanner_fire_webhook($saved, 'notice.created');
                        $message = '<div class="nb-alert nb-alert-success">Notice added successfully.</div>';
                    }
                } catch (\Exception $e) {
                    $message = '<div class="nb-alert nb-alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }

        // ── Save as template (Pro) ──
        elseif (isset($_POST['save_as_template'])) {
            if (!noticebanner_license_is_pro()) {
                $message = '<div class="nb-alert nb-alert-danger">&#128274; Templates require a Pro license.</div>';
                goto nb_post_end;
            }
            $srcId = (int)$_POST['save_as_template'];
            $tplName = trim($_POST['template_name_input'] ?? '');
            $row = \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $srcId)->first();
            if ($row && $tplName !== '') {
                $copy = (array)$row;
                unset($copy['id']);
                $copy['is_template']   = 1;
                $copy['template_name'] = $tplName;
                $copy['show_to_admins']  = 0;
                $copy['show_to_clients'] = 0;
                $copy['created_at']    = date('Y-m-d H:i:s');
                $copy['updated_at']    = date('Y-m-d H:i:s');
                \WHMCS\Database\Capsule::table('mod_noticebanner')->insert($copy);
                noticebanner_log($srcId, 'saved_as_template', $tplName);
                $message = '<div class="nb-alert nb-alert-success">Saved as template: ' . htmlspecialchars($tplName) . '</div>';
            }
        }

        // ── Clone notice (Pro) ──
        elseif (isset($_POST['clone_notice'])) {
            if (!noticebanner_license_is_pro()) {
                $message = '<div class="nb-alert nb-alert-danger">&#128274; Cloning notices requires a Pro license.</div>';
                goto nb_post_end;
            }
            $srcId = (int)$_POST['clone_notice'];
            $row   = \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $srcId)->first();
            if ($row) {
                $copy = (array)$row;
                unset($copy['id']);
                $copy['notice_title']    = 'Copy of ' . $copy['notice_title'];
                $copy['show_to_admins']  = 0;
                $copy['show_to_clients'] = 0;
                $copy['is_template']     = 0;
                $copy['sort_order']      = 0;
                $copy['poll_results']    = json_encode([]);
                $copy['created_at']      = date('Y-m-d H:i:s');
                $copy['updated_at']      = date('Y-m-d H:i:s');
                \WHMCS\Database\Capsule::table('mod_noticebanner')->increment('sort_order');
                $newId = \WHMCS\Database\Capsule::table('mod_noticebanner')->insertGetId($copy);
                noticebanner_log($newId, 'cloned', "Cloned from #$srcId");
                $message = '<div class="nb-alert nb-alert-success">Notice cloned (inactive). Edit it above.</div>';
            }
        }

        // ── Delete ──
        elseif (isset($_POST['delete_notice'])) {
            $id = (int)$_POST['delete_notice'];
            try {
                $row = \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $id)->first();
                \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $id)->delete();
                \WHMCS\Database\Capsule::table('mod_noticebanner_reads')->where('notice_id', $id)->delete();
                noticebanner_log(null, 'deleted', $row->notice_title ?? "ID $id");
                $message = '<div class="nb-alert nb-alert-success">Notice deleted.</div>';
            } catch (\Exception $e) {}
        }

        // ── Toggle visibility ──
        elseif (isset($_POST['toggle_show'])) {
            $id  = (int)$_POST['toggle_show'];
            $row = \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $id)->first();
            if ($row) {
                $enabled = ($row->show_to_admins || $row->show_to_clients) ? 0 : 1;
                \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $id)->update([
                    'show_to_admins'  => $enabled,
                    'show_to_clients' => $enabled,
                    'updated_at'      => date('Y-m-d H:i:s'),
                ]);
                noticebanner_log($id, $enabled ? 'enabled' : 'disabled', $row->notice_title ?? '');
            }
        }

        // ── Reorder ──
        elseif (isset($_POST['move_up']) || isset($_POST['move_down'])) {
            $id        = (int)($_POST['move_up'] ?? $_POST['move_down']);
            $direction = isset($_POST['move_up']) ? 'up' : 'down';
            $allRows   = noticebanner_get_notices();
            $ids       = array_column($allRows, 'id');
            $pos       = array_search($id, $ids);
            if ($pos !== false) {
                $swapPos = $direction === 'up' ? $pos - 1 : $pos + 1;
                if (isset($ids[$swapPos])) {
                    $swapId = $ids[$swapPos];
                    $so1    = $allRows[$pos]['sort_order'];
                    $so2    = $allRows[$swapPos]['sort_order'];
                    \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $id)->update(['sort_order' => $so2]);
                    \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $swapId)->update(['sort_order' => $so1]);
                }
            }
        }

        // ── Load edit ──
        elseif (isset($_POST['edit_load'])) {
            $id  = (int)$_POST['edit_load'];
            $row = \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $id)->first();
            if ($row) {
                $edit_notice = (array)$row;
                $edit_notice['poll_options']    = array_map('html_entity_decode', json_decode($edit_notice['poll_options'] ?? '[]', true) ?: []);
                $rawRes = json_decode($edit_notice['poll_results'] ?? '{}', true) ?: [];
                $decRes = [];
                foreach ($rawRes as $k => $v) $decRes[html_entity_decode($k)] = $v;
                $edit_notice['poll_results']    = $decRes;
                $edit_notice['assigned_admins'] = json_decode($edit_notice['assigned_admins'] ?? '[]', true) ?: [];
                $edit_notice['client_groups']   = json_decode($edit_notice['client_groups'] ?? '[]', true) ?: [];
                $edit_notice['target_clients']  = json_decode($edit_notice['target_clients'] ?? '[]', true) ?: [];
                $edit_notice['target_servers']  = json_decode($edit_notice['target_servers'] ?? '[]', true) ?: [];
                $edit_notice['target_products'] = json_decode($edit_notice['target_products'] ?? '[]', true) ?: [];
                $edit_notice['page_slugs']      = json_decode($edit_notice['page_slugs'] ?? '[]', true) ?: [];
            }
        }

        // ── License: save key ──
        if (isset($_POST['nb_license_save_key'])) {
            $newKey = trim($_POST['nb_license_key_input'] ?? '');
            noticebanner_license_save_key($newKey);
            // Force re-validate with the new key
            noticebanner_license_refresh(true);
            $licStatus = noticebanner_license_status();
            if ($licStatus['status'] === 'valid') {
                $message = '<div class="nb-alert nb-alert-success">✓ License key saved and validated successfully. Pro features are now active.</div>';
            } elseif ($newKey === '') {
                $message = '<div class="nb-alert nb-alert-success">License key cleared. Running in Free tier.</div>';
            } else {
                $errDetail = $licStatus['last_error'] ?? 'Could not validate key.';
                $message = '<div class="nb-alert nb-alert-danger">⚠ Key saved but validation failed: ' . htmlspecialchars($errDetail) . '</div>';
            }
            goto nb_post_end;
        }

        // ── License: validate now (re-check existing key) ──
        if (isset($_POST['nb_license_validate_now'])) {
            noticebanner_license_refresh(true);
            $licStatus = noticebanner_license_status();
            if ($licStatus['status'] === 'valid') {
                $message = '<div class="nb-alert nb-alert-success">✓ License validated successfully.</div>';
            } else {
                $errDetail = $licStatus['last_error'] ?? 'Unknown error.';
                $message = '<div class="nb-alert nb-alert-danger">⚠ Validation failed: ' . htmlspecialchars($errDetail) . '</div>';
            }
        }

        // ── License: connection diagnostics (no key required) ──
        if (isset($_POST['nb_license_run_diagnostics'])) {
            $licenseDiagnosticsOutput = htmlspecialchars(
                noticebanner_license_run_connection_diagnostics(),
                ENT_QUOTES,
                'UTF-8'
            );
        }

        nb_post_end:
        // Reload after any write
        $notices   = noticebanner_get_notices();
        $allTags   = noticebanner_get_all_tags();
        $templates = noticebanner_get_templates();
    }

    $isPro         = noticebanner_license_is_pro();
    $licenseStatus = noticebanner_license_status();
    $freeCap       = noticebanner_free_notice_cap();
    $activeCount   = (int)\WHMCS\Database\Capsule::table('mod_noticebanner')->where('is_template', 0)->count();
    $todoFilters   = [
        'notice_id'      => (int)($_GET['todo_notice_id'] ?? 0),
        'status'         => trim((string)($_GET['todo_status'] ?? 'all')),
        'due_from'       => trim((string)($_GET['todo_due_from'] ?? '')),
        'due_to'         => trim((string)($_GET['todo_due_to'] ?? '')),
        'completed_from' => trim((string)($_GET['todo_completed_from'] ?? '')),
        'completed_to'   => trim((string)($_GET['todo_completed_to'] ?? '')),
    ];
    $todoRows = noticebanner_get_todos_flat($todoFilters);
    $todoAdminIds = [];
    $todoNoticeIds = [];
    foreach ($todoRows as $row) {
        $todoNoticeIds[] = (int)$row['notice_id'];
        if (!empty($row['created_by_admin_id'])) $todoAdminIds[] = (int)$row['created_by_admin_id'];
        if (!empty($row['completed_by_admin_id'])) $todoAdminIds[] = (int)$row['completed_by_admin_id'];
    }
    $todoAdminMap = noticebanner_get_admin_name_map($todoAdminIds);
    $todoNoticeMap = noticebanner_get_notice_title_map($todoNoticeIds);
    $editNoticeTodos = isset($edit_notice['id']) ? noticebanner_get_todos_for_notice((int)$edit_notice['id']) : [];
    $todoBanners = array_values(array_filter($notices, fn($n) => !empty($n['is_todo_banner'])));
    $notices = array_values(array_filter($notices, fn($n) => empty($n['is_todo_banner'])));
    $todoBannerRange = trim((string)($_GET['todo_banner_range'] ?? '3m'));
    $todoBannerNoticeIds = [];
    try {
        $q = \WHMCS\Database\Capsule::table('mod_noticebanner_todos')->select('notice_id')->distinct();
        if ($todoBannerRange === '3m') {
            $q->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-3 months')));
        } elseif ($todoBannerRange === '6m') {
            $q->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-6 months')));
        }
        $todoBannerNoticeIds = array_map('intval', $q->pluck('notice_id')->toArray());
    } catch (\Exception $e) {}
    // Keep all To-Do banners visible for CRUD, even if they have no tasks yet.

    include __DIR__ . '/templates/admin.tpl';
}
}
