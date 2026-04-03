<?php
if (!defined('WHMCS')) {
    die('Access Denied');
}

// ─── Config ──────────────────────────────────────────────────────────────────

if (!function_exists('noticebanner_config')) {
function noticebanner_config() {
    return [
        'name'        => 'Notice Banner',
        'description' => 'Display admin/client notices as banners with markdown, polls, @mentions and admin assignments.',
        'version'     => '2.0',
        'author'      => 'Dhruv from HostingSpell',
        'fields'      => [],
    ];
}
}

// ─── Activate / Deactivate ───────────────────────────────────────────────────

if (!function_exists('noticebanner_activate')) {
function noticebanner_activate() {
    try {
        $capsule = \WHMCS\Database\Capsule::schema();

        if (!$capsule->hasTable('mod_noticebanner')) {
            $capsule->create('mod_noticebanner', function ($table) {
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
                $table->text('poll_options')->nullable();   // JSON array
                $table->text('poll_results')->nullable();   // JSON object
                $table->text('assigned_admins')->nullable(); // JSON array of admin IDs
                $table->text('mentioned_admins')->nullable(); // JSON array of admin IDs
                $table->string('priority', 20)->default('normal'); // low|normal|high|critical
                $table->datetime('notice_timestamp')->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps(); // created_at, updated_at
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

        return ['status' => 'success', 'description' => 'Notice Banner v2.0 activated. Database table created.'];
    } catch (\Exception $e) {
        return ['status' => 'error', 'description' => 'Activation failed: ' . $e->getMessage()];
    }
}
}

if (!function_exists('noticebanner_deactivate')) {
function noticebanner_deactivate() {
    // Table is preserved on deactivate to avoid data loss.
    return ['status' => 'success', 'description' => 'Module deactivated. Data table preserved.'];
}
}

// ─── DB Helpers ──────────────────────────────────────────────────────────────

if (!function_exists('noticebanner_get_notices')) {
function noticebanner_get_notices() {
    try {
        $rows = \WHMCS\Database\Capsule::table('mod_noticebanner')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();
        $notices = [];
        foreach ($rows as $row) {
            $n = (array)$row;
            $n['poll_options']      = json_decode($n['poll_options'] ?? '[]', true) ?: [];
            $n['poll_results']      = json_decode($n['poll_results'] ?? '{}', true) ?: [];
            $n['assigned_admins']   = json_decode($n['assigned_admins'] ?? '[]', true) ?: [];
            $n['mentioned_admins']  = json_decode($n['mentioned_admins'] ?? '[]', true) ?: [];
            $notices[] = $n;
        }
        return $notices;
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
    try {
        return \WHMCS\Database\Capsule::table('tblsupportdepartments')
            ->orderBy('order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    } catch (\Exception $e) {
        return [];
    }
}
}

// ─── Admin Output ────────────────────────────────────────────────────────────

if (!function_exists('noticebanner_output')) {
function noticebanner_output($vars) {
    // Ensure NoticeBannerHelper (with parseMarkdown) is available in the template
    if (!class_exists('NoticeBannerHelper')) {
        require_once __DIR__ . '/hooks.php';
    }
    $notices     = noticebanner_get_notices();
    $departments = noticebanner_get_departments();
    $admins      = noticebanner_get_admins();

    $edit_notice = null;
    $message     = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // ── Poll vote ──
        if (isset($_POST['poll_vote'], $_POST['poll_notice_id'])) {
            $nid  = (int)$_POST['poll_notice_id'];
            $vote = $_POST['poll_vote'];
            try {
                $row = \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $nid)->first();
                if ($row) {
                    $results = json_decode($row->poll_results ?? '{}', true) ?: [];
                    $results[$vote] = ($results[$vote] ?? 0) + 1;
                    \WHMCS\Database\Capsule::table('mod_noticebanner')
                        ->where('id', $nid)
                        ->update(['poll_results' => json_encode($results), 'updated_at' => date('Y-m-d H:i:s')]);
                }
            } catch (\Exception $e) {}
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }

        // ── Add / Edit ──
        if (isset($_POST['save_notice'])) {
            $assignedAdmins  = isset($_POST['assigned_admins']) && is_array($_POST['assigned_admins'])
                ? array_map('intval', $_POST['assigned_admins']) : [];
            $mentionedAdmins = isset($_POST['mentioned_admins']) && is_array($_POST['mentioned_admins'])
                ? array_map('intval', $_POST['mentioned_admins']) : [];
            $pollOptions = isset($_POST['poll_options']) && is_array($_POST['poll_options'])
                ? array_values(array_filter(array_map('trim', $_POST['poll_options']), fn($v) => $v !== '')) : [];

            $ts = !empty($_POST['notice_timestamp']) ? date('Y-m-d H:i:s', strtotime($_POST['notice_timestamp'])) : null;

            $payload = [
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
                'mentioned_admins'     => json_encode($mentionedAdmins),
                'priority'             => $_POST['priority'] ?? 'normal',
                'notice_timestamp'     => $ts,
                'updated_at'           => date('Y-m-d H:i:s'),
            ];

            $editId = (int)($_POST['edit_id'] ?? 0);
            try {
                if ($editId > 0) {
                    \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $editId)->update($payload);
                    $message = '<div class="nb-alert nb-alert-success">Notice updated successfully.</div>';
                } else {
                    $payload['poll_results'] = json_encode([]);
                    $payload['sort_order']   = 0;
                    $payload['created_at']   = date('Y-m-d H:i:s');
                    // Shift existing down
                    \WHMCS\Database\Capsule::table('mod_noticebanner')
                        ->increment('sort_order');
                    \WHMCS\Database\Capsule::table('mod_noticebanner')->insert($payload);
                    $message = '<div class="nb-alert nb-alert-success">Notice added successfully.</div>';
                }
            } catch (\Exception $e) {
                $message = '<div class="nb-alert nb-alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }

        // ── Delete ──
        elseif (isset($_POST['delete_notice'])) {
            $id = (int)$_POST['delete_notice'];
            try {
                \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $id)->delete();
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
            }
        }

        // ── Reorder ──
        elseif (isset($_POST['move_up']) || isset($_POST['move_down'])) {
            $id        = (int)($_POST['move_up'] ?? $_POST['move_down']);
            $direction = isset($_POST['move_up']) ? 'up' : 'down';
            $notices   = noticebanner_get_notices();
            $ids       = array_column($notices, 'id');
            $pos       = array_search($id, $ids);
            if ($pos !== false) {
                $swapPos = $direction === 'up' ? $pos - 1 : $pos + 1;
                if (isset($ids[$swapPos])) {
                    $swapId = $ids[$swapPos];
                    $so1    = $notices[$pos]['sort_order'];
                    $so2    = $notices[$swapPos]['sort_order'];
                    \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $id)->update(['sort_order' => $so2]);
                    \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $swapId)->update(['sort_order' => $so1]);
                }
            }
        }

        // ── Load edit ──
        elseif (isset($_POST['edit_load'])) {
            $id = (int)$_POST['edit_load'];
            $row = \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $id)->first();
            if ($row) {
                $edit_notice = (array)$row;
                $edit_notice['poll_options']     = json_decode($edit_notice['poll_options'] ?? '[]', true) ?: [];
                $edit_notice['poll_results']     = json_decode($edit_notice['poll_results'] ?? '{}', true) ?: [];
                $edit_notice['assigned_admins']  = json_decode($edit_notice['assigned_admins'] ?? '[]', true) ?: [];
                $edit_notice['mentioned_admins'] = json_decode($edit_notice['mentioned_admins'] ?? '[]', true) ?: [];
            }
        }

        // Reload after any write
        $notices = noticebanner_get_notices();
    }

    include __DIR__ . '/templates/admin.tpl';
}
}
