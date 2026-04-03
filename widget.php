<?php
/**
 * Notice Banner – WHMCS Admin Dashboard Widget
 */

if (!defined('WHMCS')) {
    die('Access Denied');
}

use WHMCS\Database\Capsule;

add_hook('AdminHomeWidgets', 1, function () {
    return new NoticeBannerWidget();
});

if (!class_exists('NoticeBannerWidget')) {
class NoticeBannerWidget extends \WHMCS\Module\AbstractWidget {

    protected $title              = 'Notice Banner';
    protected $description        = 'Manage and preview notices.';
    protected $weight             = 150;
    protected $columns            = 2;
    protected $cache              = false;
    protected $requiredPermission = 'Addon Modules';

    private static $priorityColors = [
        'critical' => ['#dc2626', '#fef2f2'],
        'high'     => ['#f97316', '#fff7ed'],
        'normal'   => ['#2563eb', '#eff6ff'],
        'low'      => ['#6b7280', '#f3f4f6'],
    ];

    // ── Fetch all notices ────────────────────────────────────────────────────
    public function getData() {
        if (function_exists('noticebanner_ensure_table')) {
            noticebanner_ensure_table();
        }
        $notices = [];
        try {
            $rows = Capsule::table('mod_noticebanner')
                ->orderBy('sort_order')->orderBy('id')->get();
            foreach ($rows as $row) {
                $n = (array)$row;
                $n['assigned_admins'] = json_decode($n['assigned_admins'] ?? '[]', true) ?: [];
                $n['client_groups']   = json_decode($n['client_groups'] ?? '[]', true) ?: [];
                $n['page_slugs']      = json_decode($n['page_slugs'] ?? '[]', true) ?: [];
                $notices[] = $n;
            }
        } catch (\Exception $e) {}
        return ['notices' => $notices];
    }

    // ── Resolve admin names ──────────────────────────────────────────────────
    private function adminNames(array $ids): array {
        if (empty($ids)) return [];
        try {
            $rows = Capsule::table('tbladmins')->whereIn('id', $ids)
                ->get(['id', 'firstname', 'lastname'])->toArray();
            $map = [];
            foreach ($rows as $r) $map[(int)$r->id] = $r->firstname . ' ' . $r->lastname;
            return $map;
        } catch (\Exception $e) { return []; }
    }

    // ── Handle POSTs ─────────────────────────────────────────────────────────
    private function handlePost(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['nb_widget_action'])) return;

        $action = $_POST['nb_widget_action'];
        $id     = (int)($_POST['nb_id'] ?? 0);

        try {
            // ── Toggle admins/clients independently ──
            if ($action === 'toggle_admins' && $id > 0) {
                $row = Capsule::table('mod_noticebanner')->where('id', $id)->first();
                if ($row) {
                    Capsule::table('mod_noticebanner')->where('id', $id)->update([
                        'show_to_admins' => $row->show_to_admins ? 0 : 1,
                        'updated_at'     => date('Y-m-d H:i:s'),
                    ]);
                }
            } elseif ($action === 'toggle_clients' && $id > 0) {
                $row = Capsule::table('mod_noticebanner')->where('id', $id)->first();
                if ($row) {
                    Capsule::table('mod_noticebanner')->where('id', $id)->update([
                        'show_to_clients' => $row->show_to_clients ? 0 : 1,
                        'updated_at'      => date('Y-m-d H:i:s'),
                    ]);
                }
            } elseif ($action === 'delete' && $id > 0) {
                Capsule::table('mod_noticebanner')->where('id', $id)->delete();

            } elseif ($action === 'add') {
                $title   = trim($_POST['nb_title'] ?? '');
                $content = trim($_POST['nb_content'] ?? '');
                if ($title !== '') {
                    Capsule::table('mod_noticebanner')->increment('sort_order');
                    Capsule::table('mod_noticebanner')->insert([
                        'notice_title'         => $title,
                        'notice_content'       => $content,
                        'show_to_admins'       => isset($_POST['nb_show_admins'])  ? 1 : 0,
                        'show_to_clients'      => isset($_POST['nb_show_clients']) ? 1 : 0,
                        'display_type'         => 'banner',
                        'show_again_minutes'   => 60,
                        'expandable'           => isset($_POST['nb_expandable']) ? 1 : 0,
                        'bg_color'             => '#fffae6',
                        'font_color'           => '#222222',
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
                        'priority'             => $_POST['nb_priority'] ?? 'normal',
                        'sort_order'           => 0,
                        'expires_at'           => null,
                        'publish_at'           => null,
                        'tags'                 => '',
                        'client_groups'        => json_encode([]),
                        'page_slugs'           => json_encode([]),
                        'webhook_url'          => '',
                        'is_pinned'            => 0,
                        'is_template'          => 0,
                        'template_name'        => '',
                        'created_at'           => date('Y-m-d H:i:s'),
                        'updated_at'           => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        } catch (\Exception $e) {}
    }

    // ── Render ───────────────────────────────────────────────────────────────
    public function generateOutput($data) {
        $this->handlePost();
        $data    = $this->getData();
        $notices = $data['notices'];
        $now     = date('Y-m-d H:i:s');

        $active   = array_values(array_filter($notices, fn($n) => !empty($n['show_to_admins']) || !empty($n['show_to_clients'])));
        $inactive = array_values(array_filter($notices, fn($n) => empty($n['show_to_admins']) && empty($n['show_to_clients'])));

        $addonUrl = 'addonmodules.php?module=noticebanner';

        ob_start(); ?>
<style>
/* ── Force WHMCS widget container to grow with content ── */
.widget-noticebanner .widget-content-padded,
.widget-noticebanner .widget-body,
.widget-noticebanner .panel-body,
.widget-noticebanner > div,
.widget-noticebanner .sortable-widget,
[data-widget-id="noticebanner"] .widget-body,
[data-widget-id="noticebanner"] .panel-body {
    overflow: visible !important;
    height: auto !important;
    max-height: none !important;
}

/* ── Notice Banner Widget ── */
#nb-widget-wrap,#nb-widget-wrap *{box-sizing:border-box;}
#nb-widget-wrap{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:13px;color:#1e293b;width:100%;min-width:0;overflow:visible;}

/* Stats row */
.nbw-stats{display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;}
.nbw-stat{flex:1 1 80px;border-radius:8px;padding:8px 10px;text-align:center;}
.nbw-stat-num{font-size:22px;font-weight:800;line-height:1;}
.nbw-stat-lbl{font-size:10px;font-weight:700;margin-top:2px;text-transform:uppercase;letter-spacing:0.05em;}

/* Notice card */
.nbw-card{background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;margin-bottom:8px;border-left-width:3px;}
.nbw-card-head{display:flex;align-items:flex-start;justify-content:space-between;padding:9px 12px;gap:8px;flex-wrap:wrap;}
.nbw-card-body{padding:0 12px 10px 12px;}

/* Title + meta */
.nbw-title-row{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:5px;}
.nbw-title{font-weight:700;font-size:13px;color:#0f172a;word-break:break-word;}
.nbw-badge{display:inline-block;padding:1px 7px;border-radius:999px;font-size:11px;font-weight:700;white-space:nowrap;}
.nbw-meta{display:flex;align-items:center;flex-wrap:wrap;gap:5px;}
.nbw-chip{display:inline-flex;align-items:center;gap:2px;background:#ede9fe;color:#5b21b6;border-radius:999px;padding:1px 7px;font-size:11px;font-weight:600;white-space:nowrap;}
.nbw-ts{font-size:11px;color:#94a3b8;white-space:nowrap;}

/* Audience toggles */
.nbw-toggle{display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:5px;font-size:11px;font-weight:700;cursor:pointer;border:none;white-space:nowrap;}
.nbw-toggle-on {background:#dcfce7;color:#166534;}
.nbw-toggle-off{background:#f1f5f9;color:#94a3b8;}

/* Action buttons */
.nbw-actions{display:flex;gap:4px;flex-shrink:0;align-items:flex-start;flex-wrap:wrap;}
.nbw-btn{display:inline-flex;align-items:center;gap:3px;padding:4px 10px;border-radius:5px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid transparent;text-decoration:none;line-height:1.4;white-space:nowrap;}

/* Content preview */
.nbw-preview{font-size:12px;color:#475569;line-height:1.6;white-space:pre-wrap;word-break:break-word;overflow-wrap:anywhere;}
.nbw-expand-btn{font-size:11px;color:#6366f1;cursor:pointer;background:none;border:none;padding:0 0 0 4px;font-weight:600;text-decoration:underline;text-underline-offset:2px;vertical-align:baseline;}

/* Section headers (collapsible) */
.nbw-section{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#94a3b8;margin:10px 0 5px 0;display:flex;align-items:center;gap:5px;cursor:pointer;user-select:none;}
.nbw-section::before{content:'▶';font-size:8px;transition:transform .2s;display:inline-block;}
.nbw-section.open::before{transform:rotate(90deg);}

/* Quick-add form */
.nbw-form-row{display:flex;flex-direction:column;gap:4px;margin-bottom:8px;}
.nbw-form-row label{font-size:12px;font-weight:600;color:#475569;}
.nbw-form-row input[type=text],
.nbw-form-row textarea,
.nbw-form-row select{width:100%;padding:6px 9px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;color:#1e293b;background:#fff;min-width:0;}
.nbw-form-row textarea{resize:vertical;min-height:70px;font-family:inherit;}
.nbw-check-row{display:flex;flex-wrap:wrap;gap:10px;align-items:center;}
.nbw-check-row label{display:flex;align-items:center;gap:5px;font-size:12px;font-weight:500;cursor:pointer;}
</style>

<div id="nb-widget-wrap">

    <!-- Stats -->
    <div class="nbw-stats">
        <div class="nbw-stat" style="background:#f0fdf4;border:1px solid #bbf7d0;">
            <div class="nbw-stat-num" style="color:#166534;"><?php echo count($active); ?></div>
            <div class="nbw-stat-lbl" style="color:#166534;">Active</div>
        </div>
        <div class="nbw-stat" style="background:#f8fafc;border:1px solid #e2e8f0;">
            <div class="nbw-stat-num" style="color:#64748b;"><?php echo count($inactive); ?></div>
            <div class="nbw-stat-lbl" style="color:#94a3b8;">Inactive</div>
        </div>
        <div class="nbw-stat" style="background:#f8fafc;border:1px solid #e2e8f0;">
            <div class="nbw-stat-num" style="color:#1e293b;"><?php echo count($notices); ?></div>
            <div class="nbw-stat-lbl" style="color:#94a3b8;">Total</div>
        </div>
    </div>

    <!-- Active notices -->
    <?php if (!empty($active)): ?>
        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#94a3b8;margin-bottom:6px;">Active Notices</div>
        <?php foreach ($active as $n):
            $priority       = $n['priority'] ?? 'normal';
            [$ac, $bgc]     = self::$priorityColors[$priority] ?? self::$priorityColors['normal'];
            $assignedAdmins = $n['assigned_admins'] ?? [];
            $nameMap        = $this->adminNames($assignedAdmins);
            $bodyId         = 'nbw-b-' . $n['id'];
            $fullContent    = $n['notice_content'] ?? '';
            $stripped       = strip_tags($fullContent);
            $preview        = mb_strimwidth($stripped, 0, 140, '…');
            $needsExpand    = mb_strlen($stripped) > 140;
            $hasTs          = !empty($n['notice_timestamp']);
            $isExpired      = !empty($n['expires_at']) && $n['expires_at'] < $now;
            $isSched        = !empty($n['publish_at']) && $n['publish_at'] > $now;
            $isPinned       = !empty($n['is_pinned']);
        ?>
        <div class="nbw-card" style="border-left-color:<?php echo $ac; ?>;">
            <div class="nbw-card-head">
                <div style="flex:1;min-width:0;">
                    <div class="nbw-title-row">
                        <?php if ($isPinned): ?><span style="font-size:12px;">📌</span><?php endif; ?>
                        <span class="nbw-title"><?php echo htmlspecialchars($n['notice_title']); ?></span>
                        <span class="nbw-badge" style="background:<?php echo $bgc; ?>;color:<?php echo $ac; ?>;"><?php echo ucfirst($priority); ?></span>
                        <?php if ($isExpired): ?><span class="nbw-badge" style="background:#fee2e2;color:#991b1b;">⏰ Expired</span><?php endif; ?>
                        <?php if ($isSched): ?><span class="nbw-badge" style="background:#fef9c3;color:#854d0e;">🕐 Scheduled</span><?php endif; ?>
                    </div>
                    <div class="nbw-meta">
                        <form method="post" style="display:contents;">
                            <input type="hidden" name="nb_widget_action" value="toggle_admins">
                            <input type="hidden" name="nb_id" value="<?php echo (int)$n['id']; ?>">
                            <button type="submit" class="nbw-toggle <?php echo !empty($n['show_to_admins']) ? 'nbw-toggle-on' : 'nbw-toggle-off'; ?>">
                                👤 <?php echo !empty($n['show_to_admins']) ? 'Admins ✓' : 'Admins ✗'; ?>
                            </button>
                        </form>
                        <form method="post" style="display:contents;">
                            <input type="hidden" name="nb_widget_action" value="toggle_clients">
                            <input type="hidden" name="nb_id" value="<?php echo (int)$n['id']; ?>">
                            <button type="submit" class="nbw-toggle <?php echo !empty($n['show_to_clients']) ? 'nbw-toggle-on' : 'nbw-toggle-off'; ?>">
                                🌐 <?php echo !empty($n['show_to_clients']) ? 'Clients ✓' : 'Clients ✗'; ?>
                            </button>
                        </form>
                        <?php foreach ($assignedAdmins as $aid): ?>
                            <span class="nbw-chip">👤 <?php echo htmlspecialchars($nameMap[$aid] ?? 'Admin #'.$aid); ?></span>
                        <?php endforeach; ?>
                        <?php if ($hasTs): ?>
                            <span class="nbw-ts">🕐 <?php echo date('M j, Y g:ia', strtotime($n['notice_timestamp'])); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="nbw-actions">
                    <a href="<?php echo $addonUrl; ?>" class="nbw-btn" style="background:#ede9fe;color:#5b21b6;border-color:#ddd6fe;">✏️ Edit</a>
                    <form method="post" style="display:contents;" onsubmit="return confirm('Delete this notice?');">
                        <input type="hidden" name="nb_widget_action" value="delete">
                        <input type="hidden" name="nb_id" value="<?php echo (int)$n['id']; ?>">
                        <button type="submit" class="nbw-btn" style="background:#fee2e2;color:#991b1b;border-color:#fecaca;">🗑</button>
                    </form>
                </div>
            </div>
            <?php if (!empty(trim($fullContent))): ?>
            <div class="nbw-card-body">
                <div id="<?php echo $bodyId; ?>-short" class="nbw-preview"><?php echo htmlspecialchars($preview); ?><?php if ($needsExpand): ?><button class="nbw-expand-btn" onclick="nbwExpand('<?php echo $bodyId; ?>')">Show more</button><?php endif; ?></div>
                <?php if ($needsExpand): ?>
                <div id="<?php echo $bodyId; ?>-full" class="nbw-preview" style="display:none;"><?php echo htmlspecialchars($fullContent); ?><button class="nbw-expand-btn" onclick="nbwCollapse('<?php echo $bodyId; ?>')"> Show less</button></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="text-align:center;color:#94a3b8;font-size:13px;padding:12px 0;">No active notices.</div>
    <?php endif; ?>

    <!-- Inactive notices -->
    <?php if (!empty($inactive)): ?>
        <div class="nbw-section" id="nbw-inactive-lbl" onclick="nbwToggle('nbw-inactive','nbw-inactive-lbl')">Inactive (<?php echo count($inactive); ?>)</div>
        <div id="nbw-inactive" style="display:none;">
            <?php foreach ($inactive as $n):
                $priority       = $n['priority'] ?? 'normal';
                [$ac, $bgc]     = self::$priorityColors[$priority] ?? self::$priorityColors['normal'];
                $assignedAdmins = $n['assigned_admins'] ?? [];
                $nameMap        = $this->adminNames($assignedAdmins);
                $bodyId         = 'nbw-ib-' . $n['id'];
                $fullContent    = $n['notice_content'] ?? '';
                $stripped       = strip_tags($fullContent);
                $preview        = mb_strimwidth($stripped, 0, 140, '…');
                $needsExpand    = mb_strlen($stripped) > 140;
                $hasTs          = !empty($n['notice_timestamp']);
                $isExpired      = !empty($n['expires_at']) && $n['expires_at'] < $now;
                $isSched        = !empty($n['publish_at']) && $n['publish_at'] > $now;
                $isPinned       = !empty($n['is_pinned']);
            ?>
            <div class="nbw-card" style="border-left-color:#cbd5e1;opacity:0.85;">
                <div class="nbw-card-head">
                    <div style="flex:1;min-width:0;">
                        <div class="nbw-title-row">
                            <?php if ($isPinned): ?><span style="font-size:12px;">📌</span><?php endif; ?>
                            <span class="nbw-title"><?php echo htmlspecialchars($n['notice_title']); ?></span>
                            <span class="nbw-badge" style="background:<?php echo $bgc; ?>;color:<?php echo $ac; ?>;"><?php echo ucfirst($priority); ?></span>
                            <?php if ($isExpired): ?><span class="nbw-badge" style="background:#fee2e2;color:#991b1b;">⏰ Expired</span><?php endif; ?>
                            <?php if ($isSched): ?><span class="nbw-badge" style="background:#fef9c3;color:#854d0e;">🕐 Scheduled</span><?php endif; ?>
                        </div>
                        <div class="nbw-meta">
                            <form method="post" style="display:contents;">
                                <input type="hidden" name="nb_widget_action" value="toggle_admins">
                                <input type="hidden" name="nb_id" value="<?php echo (int)$n['id']; ?>">
                                <button type="submit" class="nbw-toggle nbw-toggle-off">👤 Admins ✗</button>
                            </form>
                            <form method="post" style="display:contents;">
                                <input type="hidden" name="nb_widget_action" value="toggle_clients">
                                <input type="hidden" name="nb_id" value="<?php echo (int)$n['id']; ?>">
                                <button type="submit" class="nbw-toggle nbw-toggle-off">🌐 Clients ✗</button>
                            </form>
                            <?php foreach ($assignedAdmins as $aid): ?>
                                <span class="nbw-chip">👤 <?php echo htmlspecialchars($nameMap[$aid] ?? 'Admin #'.$aid); ?></span>
                            <?php endforeach; ?>
                            <?php if ($hasTs): ?>
                                <span class="nbw-ts">🕐 <?php echo date('M j, Y g:ia', strtotime($n['notice_timestamp'])); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="nbw-actions">
                        <a href="<?php echo $addonUrl; ?>" class="nbw-btn" style="background:#ede9fe;color:#5b21b6;border-color:#ddd6fe;">✏️ Edit</a>
                        <form method="post" style="display:contents;" onsubmit="return confirm('Delete this notice?');">
                            <input type="hidden" name="nb_widget_action" value="delete">
                            <input type="hidden" name="nb_id" value="<?php echo (int)$n['id']; ?>">
                            <button type="submit" class="nbw-btn" style="background:#fee2e2;color:#991b1b;border-color:#fecaca;">🗑</button>
                        </form>
                    </div>
                </div>
                <?php if (!empty(trim($fullContent))): ?>
                <div class="nbw-card-body">
                    <div id="<?php echo $bodyId; ?>-short" class="nbw-preview"><?php echo htmlspecialchars($preview); ?><?php if ($needsExpand): ?><button class="nbw-expand-btn" onclick="nbwExpand('<?php echo $bodyId; ?>')">Show more</button><?php endif; ?></div>
                    <?php if ($needsExpand): ?>
                    <div id="<?php echo $bodyId; ?>-full" class="nbw-preview" style="display:none;"><?php echo htmlspecialchars($fullContent); ?><button class="nbw-expand-btn" onclick="nbwCollapse('<?php echo $bodyId; ?>')"> Show less</button></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Quick Add -->
    <div style="border-top:1px solid #e2e8f0;margin-top:12px;padding-top:8px;">
        <div class="nbw-section open" id="nbw-qaform-lbl" onclick="nbwToggle('nbw-qaform','nbw-qaform-lbl')">Quick Add Notice</div>
        <div id="nbw-qaform" style="display:block;margin-top:8px;">
            <form method="post">
                <input type="hidden" name="nb_widget_action" value="add">
                <div class="nbw-form-row">
                    <label>Title *</label>
                    <input type="text" name="nb_title" placeholder="Notice title" required>
                </div>
                <div class="nbw-form-row">
                    <label>Content <span style="font-weight:400;color:#94a3b8;">(Markdown supported)</span></label>
                    <textarea name="nb_content" placeholder="Write notice content…"></textarea>
                </div>
                <div class="nbw-form-row">
                    <label>Priority</label>
                    <select name="nb_priority">
                        <option value="normal">🔵 Normal</option>
                        <option value="low">⚪ Low</option>
                        <option value="high">🟠 High</option>
                        <option value="critical">🔴 Critical</option>
                    </select>
                </div>
                <div class="nbw-form-row">
                    <label>Show To</label>
                    <div class="nbw-check-row">
                        <label><input type="checkbox" name="nb_show_admins" value="1" checked> 👤 Admins</label>
                        <label><input type="checkbox" name="nb_show_clients" value="1"> 🌐 Clients</label>
                        <label><input type="checkbox" name="nb_expandable" value="1"> Expandable</label>
                    </div>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px;">
                    <button type="submit" class="nbw-btn" style="background:#6366f1;color:#fff;border-color:#6366f1;">Add Notice</button>
                    <a href="<?php echo $addonUrl; ?>" class="nbw-btn" style="background:#f1f5f9;color:#475569;border-color:#e2e8f0;">Full Editor →</a>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function nbwToggle(id, lblId) {
    var el  = document.getElementById(id);
    var lbl = lblId ? document.getElementById(lblId) : null;
    var open = el.style.display !== 'none';
    el.style.display = open ? 'none' : 'block';
    if (lbl) { open ? lbl.classList.remove('open') : lbl.classList.add('open'); }
    nbwUnclip();
}
function nbwExpand(id) {
    document.getElementById(id+'-short').style.display = 'none';
    document.getElementById(id+'-full').style.display  = 'block';
    nbwUnclip();
}
function nbwCollapse(id) {
    document.getElementById(id+'-full').style.display  = 'none';
    document.getElementById(id+'-short').style.display = 'block';
}
// Walk up the DOM from our widget and remove any overflow/height constraints
// imposed by the WHMCS theme on widget containers.
function nbwUnclip() {
    var el = document.getElementById('nb-widget-wrap');
    if (!el) return;
    var node = el.parentNode;
    var depth = 0;
    while (node && node !== document.body && depth < 8) {
        node.style.overflow  = 'visible';
        node.style.height    = 'auto';
        node.style.maxHeight = 'none';
        node = node.parentNode;
        depth++;
    }
}
// Run on page load too
document.addEventListener('DOMContentLoaded', nbwUnclip);
// Also run after a short delay in case WHMCS JS sets height after load
setTimeout(nbwUnclip, 300);
setTimeout(nbwUnclip, 800);
</script>
        <?php
        return ob_get_clean();
    }
}
}
