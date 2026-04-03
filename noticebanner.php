<?php
if (!defined('WHMCS')) {
    die('Access Denied');
}

// ─── Config ──────────────────────────────────────────────────────────────────

if (!function_exists('noticebanner_config')) {
function noticebanner_config() {
    return [
        'name'        => 'Notice Banner',
        'description' => 'Display admin/client notices as banners with markdown, polls, @mentions, assignments, scheduling and more.',
        'version'     => '3.0',
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
    return ['status' => 'success', 'description' => 'Notice Banner v3.0 activated. Database ready.'];
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
            // v3.1 — granular targeting
            if (!$schema->hasColumn('mod_noticebanner', 'target_clients'))
                $table->text('target_clients')->nullable()->after('is_pinned');
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
    if (!$url) return;

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

    return [
        'notice_title'         => trim($_POST['notice_title'] ?? ''),
        'notice_content'       => $_POST['notice_content'] ?? '',
        'show_to_clients'      => isset($_POST['show_to_clients']) ? 1 : 0,
        'show_to_admins'       => isset($_POST['show_to_admins']) ? 1 : 0,
        'display_type'         => $_POST['display_type'] ?? 'banner',
        'show_again_minutes'   => (int)($_POST['show_again_minutes'] ?? 60),
        'expandable'           => isset($_POST['expandable']) ? 1 : 0,
        'bg_color'             => $_POST['bg_color'] ?? '#fffae6',
        'font_color'           => $_POST['font_color'] ?? '#222222',
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
        'priority'             => $_POST['priority'] ?? 'normal',
        'notice_timestamp'     => !empty($_POST['notice_timestamp']) ? date('Y-m-d H:i:s', strtotime($_POST['notice_timestamp'])) : null,
        // v3
        'expires_at'           => !empty($_POST['expires_at'])   ? date('Y-m-d H:i:s', strtotime($_POST['expires_at']))   : null,
        'publish_at'           => !empty($_POST['publish_at'])   ? date('Y-m-d H:i:s', strtotime($_POST['publish_at']))   : null,
        'tags'                 => $tags,
        'client_groups'        => json_encode($clientGroups),
        'target_clients'       => json_encode($targetClients),
        'target_servers'       => json_encode($targetServers),
        'target_products'      => json_encode($targetProducts),
        'page_slugs'           => json_encode($pageSlugs),
        'webhook_url'          => trim($_POST['notice_webhook_url'] ?? ''),
        'is_pinned'            => isset($_POST['is_pinned']) ? 1 : 0,
        'updated_at'           => date('Y-m-d H:i:s'),
    ];
}
}

// ─── Admin Output ────────────────────────────────────────────────────────────

if (!function_exists('noticebanner_output')) {
function noticebanner_output($vars) {
    noticebanner_ensure_table();
    noticebanner_ensure_columns();

    // ── Export poll votes: ?nb_export_votes=<id>&format=csv|json ──
    if (!empty($_GET['nb_export_votes'])) {
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
        $filename = 'poll-votes-' . $nid . '-' . $slug;

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

    // ── Debug: ?nb_debug_depts=1 — dumps raw department query info ──
    if (!empty($_GET['nb_debug_depts'])) {
        header('Content-Type: text/plain');
        foreach (['tblticketdepartments', 'tblsupportdepts', 'tblsupportdepartments'] as $tbl) {
            echo "=== $tbl ===\n";
            try {
                $rows = \WHMCS\Database\Capsule::table($tbl)->get()->toArray();
                echo count($rows) . " rows\n";
                foreach ($rows as $r) { echo json_encode($r) . "\n"; }
            } catch (\Exception $e) {
                echo "ERROR: " . $e->getMessage() . "\n";
            }
            echo "\n";
        }
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

        // ── Predefined votes (admin only — multi-option, deduplicated by label+option) ──
        if (isset($_POST['fake_poll_vote'], $_POST['fake_poll_notice_id'])) {
            $nid      = (int)$_POST['fake_poll_notice_id'];
            $label    = trim($_POST['fake_poll_label'] ?? '') ?: 'Predefined Vote';
            // New multi-option form: fake_poll_options[] + fake_poll_counts[idx]
            $options  = isset($_POST['fake_poll_options']) && is_array($_POST['fake_poll_options'])
                        ? $_POST['fake_poll_options'] : [];
            $counts   = isset($_POST['fake_poll_counts'])  && is_array($_POST['fake_poll_counts'])
                        ? $_POST['fake_poll_counts']  : [];

            // Legacy single-option fallback
            if (empty($options) && !empty($_POST['fake_poll_option'])) {
                $options = [$_POST['fake_poll_option']];
                $counts  = [0 => max(1, (int)($_POST['fake_poll_count'] ?? 1))];
            }

            if ($nid && !empty($options)) {
                try {
                    $row = \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $nid)->first();
                    if ($row) {
                        $results = json_decode($row->poll_results ?? '{}', true) ?: [];
                        $now     = date('Y-m-d H:i:s');

                        foreach ($options as $idx => $opt) {
                            $opt   = (string)$opt;
                            $count = max(0, (int)($counts[$idx] ?? 1));
                            if ($count === 0) continue;

                            // Update aggregate counter
                            $results[$opt] = ($results[$opt] ?? 0) + $count;

                            // Deduplicate: find existing predefined row with same notice+label+option
                            $existing = \WHMCS\Database\Capsule::table('mod_noticebanner_poll_votes')
                                ->where('notice_id',    $nid)
                                ->where('is_predefined', 1)
                                ->where('entity_label', $label)
                                ->where('poll_option',  $opt)
                                ->first(['id', 'entity_id']);

                            if ($existing) {
                                // entity_id stores the running count for predefined rows
                                \WHMCS\Database\Capsule::table('mod_noticebanner_poll_votes')
                                    ->where('id', $existing->id)
                                    ->update([
                                        'entity_id' => $existing->entity_id + $count,
                                        'voted_at'  => $now,
                                    ]);
                            } else {
                                \WHMCS\Database\Capsule::table('mod_noticebanner_poll_votes')->insert([
                                    'notice_id'     => $nid,
                                    'entity_type'   => 'predefined',
                                    'entity_id'     => $count,   // running count stored here
                                    'entity_label'  => $label,
                                    'poll_option'   => $opt,
                                    'is_predefined' => 1,
                                    'voted_at'      => $now,
                                ]);
                            }
                            noticebanner_log($nid, 'predefined_poll_vote', "Option: $opt, +$count, Label: $label");
                        }

                        \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $nid)
                            ->update(['poll_results' => json_encode($results), 'updated_at' => $now]);
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

        // ── Remove acknowledgement (undo / fake management) ──
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

        // ── Add fake acknowledgement ──
        if (isset($_POST['add_fake_ack'])) {
            $nid  = (int)($_POST['fake_ack_notice_id'] ?? 0);
            $type = $_POST['fake_ack_type'] ?? 'admin';
            $eids = isset($_POST['fake_ack_entities']) && is_array($_POST['fake_ack_entities'])
                ? array_map('intval', $_POST['fake_ack_entities']) : [];
            if ($nid && !empty($eids)) {
                foreach ($eids as $eid) {
                    try {
                        \WHMCS\Database\Capsule::table('mod_noticebanner_reads')->updateOrInsert(
                            ['notice_id' => $nid, 'entity_type' => $type, 'entity_id' => $eid],
                            ['read_at' => date('Y-m-d H:i:s')]
                        );
                    } catch (\Exception $e) {}
                }
                noticebanner_log($nid, 'fake_ack_added', "Type: $type, Count: " . count($eids));
                $message = '<div class="nb-alert nb-alert-success">Added ' . count($eids) . ' acknowledgement(s).</div>';
            }
        }

        // ── Save notice (add or edit) ──
        if (isset($_POST['save_notice'])) {
            $payload = noticebanner_build_payload();
            $editId  = (int)($_POST['edit_id'] ?? 0);
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

        // ── Save as template ──
        elseif (isset($_POST['save_as_template'])) {
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

        // ── Clone notice ──
        elseif (isset($_POST['clone_notice'])) {
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

        // Reload after any write
        $notices   = noticebanner_get_notices();
        $allTags   = noticebanner_get_all_tags();
        $templates = noticebanner_get_templates();
    }

    include __DIR__ . '/templates/admin.tpl';
}
}
