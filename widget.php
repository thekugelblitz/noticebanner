<?php
/**
 * Notice Banner – WHMCS Admin Dashboard Widget
 *
 * Displays active notices and provides quick CRUD directly from the
 * WHMCS admin dashboard home page.
 *
 * File must live inside the addon folder:
 *   /modules/addons/noticebanner/widget.php
 *
 * WHMCS auto-discovers widgets placed in:
 *   /modules/widgets/
 * So we also register via a hook so it works from the addon folder.
 */

if (!defined('WHMCS')) {
    die('Access Denied');
}

use WHMCS\Database\Capsule;

// ── Register the widget via hook ─────────────────────────────────────────────
add_hook('AdminHomeWidgets', 1, function () {
    return new NoticeBannerWidget();
});

// ── Widget class ─────────────────────────────────────────────────────────────
if (!class_exists('NoticeBannerWidget')) {
class NoticeBannerWidget extends \WHMCS\Module\AbstractWidget {

    protected $title          = 'Notice Banner';
    protected $description    = 'Manage and preview active notices.';
    protected $weight         = 150;
    protected $columns        = 1;
    protected $cache          = false;
    protected $cacheExpiry    = 120;
    protected $requiredPermission = 'Addon Modules';

    // Priority colours
    private static $priorityColors = [
        'critical' => ['#dc2626', '#fef2f2'],
        'high'     => ['#f97316', '#fff7ed'],
        'normal'   => ['#2563eb', '#eff6ff'],
        'low'      => ['#6b7280', '#f3f4f6'],
    ];

    public function getData() {
        $notices = [];
        try {
            $rows = Capsule::table('mod_noticebanner')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
            foreach ($rows as $row) {
                $n = (array)$row;
                $n['poll_options']     = json_decode($n['poll_options'] ?? '[]', true) ?: [];
                $n['poll_results']     = json_decode($n['poll_results'] ?? '{}', true) ?: [];
                $n['assigned_admins']  = json_decode($n['assigned_admins'] ?? '[]', true) ?: [];
                $n['mentioned_admins'] = json_decode($n['mentioned_admins'] ?? '[]', true) ?: [];
                $notices[] = $n;
            }
        } catch (\Exception $e) {}

        return ['notices' => $notices];
    }

    public function generateOutput($data) {
        $notices = $data['notices'] ?? [];

        // ── Handle quick-action POSTs ──────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nb_widget_action'])) {
            $action = $_POST['nb_widget_action'];
            $id     = (int)($_POST['nb_id'] ?? 0);

            try {
                if ($action === 'toggle' && $id > 0) {
                    $row = Capsule::table('mod_noticebanner')->where('id', $id)->first();
                    if ($row) {
                        $enabled = ($row->show_to_admins || $row->show_to_clients) ? 0 : 1;
                        Capsule::table('mod_noticebanner')->where('id', $id)->update([
                            'show_to_admins'  => $enabled,
                            'show_to_clients' => $enabled,
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
                            'notice_title'    => $title,
                            'notice_content'  => $content,
                            'show_to_admins'  => 1,
                            'show_to_clients' => 0,
                            'display_type'    => 'banner',
                            'show_again_minutes' => 60,
                            'expandable'      => 0,
                            'bg_color'        => '#fffae6',
                            'font_color'      => '#222222',
                            'button_enabled'  => 0,
                            'button_text'     => '',
                            'button_link'     => '',
                            'button_newtab'   => 0,
                            'button_bg'       => '#2563eb',
                            'button_color'    => '#ffffff',
                            'ticket_enabled'  => 0,
                            'ticket_department_id' => '',
                            'ticket_button_text'   => '',
                            'poll_enabled'    => 0,
                            'poll_question'   => '',
                            'poll_options'    => json_encode([]),
                            'poll_results'    => json_encode([]),
                            'assigned_admins' => json_encode([]),
                            'mentioned_admins'=> json_encode([]),
                            'priority'        => $_POST['nb_priority'] ?? 'normal',
                            'sort_order'      => 0,
                            'created_at'      => date('Y-m-d H:i:s'),
                            'updated_at'      => date('Y-m-d H:i:s'),
                        ]);
                    }
                }
            } catch (\Exception $e) {}

            // Reload
            $notices = $this->getData()['notices'];
        }

        $active   = array_filter($notices, fn($n) => !empty($n['show_to_admins']) || !empty($n['show_to_clients']));
        $inactive = array_filter($notices, fn($n) => empty($n['show_to_admins']) && empty($n['show_to_clients']));

        $addonUrl = 'addonmodules.php?module=noticebanner';

        ob_start();
        ?>
        <style>
        .nbw { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .nbw-row { display:flex; align-items:flex-start; gap:10px; padding:9px 0; border-bottom:1px solid #f1f5f9; }
        .nbw-row:last-child { border-bottom:none; }
        .nbw-accent { width:4px; height:100%; min-height:36px; border-radius:2px; flex-shrink:0; margin-top:2px; }
        .nbw-body { flex:1; min-width:0; }
        .nbw-title { font-weight:700; font-size:14px; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .nbw-meta { font-size:12px; color:#94a3b8; margin-top:1px; }
        .nbw-actions { display:flex; gap:5px; flex-shrink:0; }
        .nbw-btn { display:inline-flex; align-items:center; padding:3px 9px; border-radius:5px; font-size:12px; font-weight:600; cursor:pointer; border:none; text-decoration:none; }
        .nbw-badge { display:inline-block; padding:1px 7px; border-radius:999px; font-size:11px; font-weight:700; }
        .nbw-quick-form { padding:12px 0 4px 0; border-top:1px solid #e2e8f0; margin-top:8px; }
        .nbw-quick-form input, .nbw-quick-form textarea, .nbw-quick-form select { width:100%; padding:6px 9px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; margin-bottom:7px; }
        .nbw-quick-form textarea { resize:vertical; min-height:60px; }
        .nbw-section-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#94a3b8; margin:10px 0 6px 0; }
        .nbw-empty { text-align:center; color:#94a3b8; font-size:13px; padding:14px 0; }
        </style>

        <div class="nbw">
            <!-- Stats bar -->
            <div style="display:flex;gap:10px;margin-bottom:12px;flex-wrap:wrap;">
                <div style="flex:1;min-width:80px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:8px 12px;text-align:center;">
                    <div style="font-size:22px;font-weight:800;color:#166534;"><?php echo count($active); ?></div>
                    <div style="font-size:11px;color:#166534;font-weight:600;">Active</div>
                </div>
                <div style="flex:1;min-width:80px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;text-align:center;">
                    <div style="font-size:22px;font-weight:800;color:#64748b;"><?php echo count($inactive); ?></div>
                    <div style="font-size:11px;color:#64748b;font-weight:600;">Inactive</div>
                </div>
                <div style="flex:1;min-width:80px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;text-align:center;">
                    <div style="font-size:22px;font-weight:800;color:#1e293b;"><?php echo count($notices); ?></div>
                    <div style="font-size:11px;color:#64748b;font-weight:600;">Total</div>
                </div>
            </div>

            <!-- Active notices -->
            <?php if (!empty($active)): ?>
                <div class="nbw-section-label">Active Notices</div>
                <?php foreach ($active as $n):
                    $priority = $n['priority'] ?? 'normal';
                    [$accentColor, $bgColor] = self::$priorityColors[$priority] ?? self::$priorityColors['normal'];
                    $isAdminOnly  = !empty($n['show_to_admins']) && empty($n['show_to_clients']);
                    $isClientOnly = empty($n['show_to_admins']) && !empty($n['show_to_clients']);
                    $isBoth       = !empty($n['show_to_admins']) && !empty($n['show_to_clients']);
                ?>
                <div class="nbw-row">
                    <div class="nbw-accent" style="background:<?php echo $accentColor; ?>;"></div>
                    <div class="nbw-body">
                        <div class="nbw-title"><?php echo htmlspecialchars($n['notice_title']); ?></div>
                        <div class="nbw-meta">
                            <span class="nbw-badge" style="background:<?php echo $bgColor; ?>;color:<?php echo $accentColor; ?>;"><?php echo ucfirst($priority); ?></span>
                            <?php if ($isAdminOnly): ?>
                                <span style="margin-left:4px;">👤 Admin only</span>
                            <?php elseif ($isClientOnly): ?>
                                <span style="margin-left:4px;">🌐 Client only</span>
                            <?php else: ?>
                                <span style="margin-left:4px;">👤🌐 All</span>
                            <?php endif; ?>
                            <?php if (!empty($n['assigned_admins'])): ?>
                                <span style="margin-left:4px;color:#6366f1;">📌 Assigned</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="nbw-actions">
                        <!-- Toggle off -->
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="nb_widget_action" value="toggle">
                            <input type="hidden" name="nb_id" value="<?php echo (int)$n['id']; ?>">
                            <button type="submit" class="nbw-btn" style="background:#fef9c3;color:#854d0e;" title="Disable">⏸</button>
                        </form>
                        <!-- Edit -->
                        <a href="<?php echo $addonUrl; ?>" class="nbw-btn" style="background:#ede9fe;color:#5b21b6;" title="Edit in module">✏️</a>
                        <!-- Delete -->
                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this notice?');">
                            <input type="hidden" name="nb_widget_action" value="delete">
                            <input type="hidden" name="nb_id" value="<?php echo (int)$n['id']; ?>">
                            <button type="submit" class="nbw-btn" style="background:#fee2e2;color:#991b1b;" title="Delete">🗑</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="nbw-empty">No active notices.</div>
            <?php endif; ?>

            <!-- Inactive notices (collapsed) -->
            <?php if (!empty($inactive)): ?>
                <div class="nbw-section-label" style="cursor:pointer;display:flex;align-items:center;gap:4px;" onclick="nbwToggle('nbw-inactive')">
                    <span id="nbw-inactive-arrow">▶</span> Inactive (<?php echo count($inactive); ?>)
                </div>
                <div id="nbw-inactive" style="display:none;">
                    <?php foreach ($inactive as $n):
                        $priority = $n['priority'] ?? 'normal';
                        [$accentColor, $bgColor] = self::$priorityColors[$priority] ?? self::$priorityColors['normal'];
                    ?>
                    <div class="nbw-row" style="opacity:0.65;">
                        <div class="nbw-accent" style="background:#cbd5e1;"></div>
                        <div class="nbw-body">
                            <div class="nbw-title"><?php echo htmlspecialchars($n['notice_title']); ?></div>
                            <div class="nbw-meta">
                                <span class="nbw-badge" style="background:<?php echo $bgColor; ?>;color:<?php echo $accentColor; ?>;"><?php echo ucfirst($priority); ?></span>
                            </div>
                        </div>
                        <div class="nbw-actions">
                            <!-- Toggle on -->
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="nb_widget_action" value="toggle">
                                <input type="hidden" name="nb_id" value="<?php echo (int)$n['id']; ?>">
                                <button type="submit" class="nbw-btn" style="background:#dcfce7;color:#166534;" title="Enable">▶</button>
                            </form>
                            <!-- Delete -->
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this notice?');">
                                <input type="hidden" name="nb_widget_action" value="delete">
                                <input type="hidden" name="nb_id" value="<?php echo (int)$n['id']; ?>">
                                <button type="submit" class="nbw-btn" style="background:#fee2e2;color:#991b1b;" title="Delete">🗑</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Quick-add form -->
            <div class="nbw-quick-form">
                <div class="nbw-section-label" style="cursor:pointer;display:flex;align-items:center;gap:4px;" onclick="nbwToggle('nbw-qaform')">
                    <span id="nbw-qaform-arrow">▶</span> Quick Add Notice
                </div>
                <div id="nbw-qaform" style="display:none;">
                    <form method="post" style="margin-top:8px;">
                        <input type="hidden" name="nb_widget_action" value="add">
                        <input type="text" name="nb_title" placeholder="Title *" required>
                        <textarea name="nb_content" placeholder="Content (Markdown supported)"></textarea>
                        <select name="nb_priority">
                            <option value="normal">Normal Priority</option>
                            <option value="low">Low Priority</option>
                            <option value="high">High Priority</option>
                            <option value="critical">Critical Priority</option>
                        </select>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <button type="submit" class="nbw-btn" style="background:#6366f1;color:#fff;padding:6px 14px;font-size:13px;">Add Notice</button>
                            <a href="<?php echo $addonUrl; ?>" class="nbw-btn" style="background:#f1f5f9;color:#475569;padding:6px 14px;font-size:13px;">Full Editor →</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        function nbwToggle(id) {
            var el = document.getElementById(id);
            var arrow = document.getElementById(id + '-arrow');
            var open = el.style.display !== 'none';
            el.style.display = open ? 'none' : 'block';
            if (arrow) arrow.textContent = open ? '▶' : '▼';
        }
        </script>
        <?php
        return ob_get_clean();
    }
}
}
