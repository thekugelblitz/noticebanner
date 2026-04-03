<?php if (!defined('WHMCS')) die('Access Denied'); ?>
<?php
// ── Priority helpers ──────────────────────────────────────────────────────────
$priorityConfig = [
    'low'      => ['label' => 'Low',      'color' => '#6b7280', 'bg' => '#f3f4f6'],
    'normal'   => ['label' => 'Normal',   'color' => '#2563eb', 'bg' => '#eff6ff'],
    'high'     => ['label' => 'High',     'color' => '#f97316', 'bg' => '#fff7ed'],
    'critical' => ['label' => 'Critical', 'color' => '#dc2626', 'bg' => '#fef2f2'],
];
$accentMap = ['critical' => '#dc2626', 'high' => '#f97316', 'normal' => '#2563eb', 'low' => '#9ca3af'];

// ── Admin lookup map ──────────────────────────────────────────────────────────
$adminMap = [];
foreach ($admins as $a) {
    $adminMap[$a->id] = $a->firstname . ' ' . $a->lastname;
}
?>
<style>
/* ── Reset & base ── */
#nb-wrap * { box-sizing: border-box; }
#nb-wrap { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 1100px; margin: 0 auto; color: #1e293b; }

/* ── Card ── */
.nb-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); margin-bottom: 24px; overflow: hidden; }
.nb-card-header { padding: 16px 22px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; background: #f8fafc; }
.nb-card-header h2 { margin: 0; font-size: 17px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px; }
.nb-card-body { padding: 22px; }

/* ── Form grid ── */
.nb-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.nb-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
.nb-span2 { grid-column: span 2; }
.nb-field { display: flex; flex-direction: column; gap: 5px; }
.nb-field label { font-size: 13px; font-weight: 600; color: #475569; }
.nb-field input[type=text],
.nb-field input[type=url],
.nb-field input[type=number],
.nb-field input[type=datetime-local],
.nb-field select,
.nb-field textarea { width: 100%; padding: 8px 11px; border: 1px solid #cbd5e1; border-radius: 7px; font-size: 14px; color: #1e293b; background: #fff; transition: border-color 0.15s, box-shadow 0.15s; outline: none; }
.nb-field input:focus, .nb-field select:focus, .nb-field textarea:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
.nb-field textarea { resize: vertical; min-height: 120px; font-family: 'Fira Mono', 'Consolas', monospace; font-size: 13px; }

/* ── Color row ── */
.nb-color-row { display: flex; align-items: center; gap: 6px; }
.nb-color-row input[type=color] { width: 36px; height: 32px; padding: 2px; border: 1px solid #cbd5e1; border-radius: 5px; cursor: pointer; }
.nb-color-row input[type=text] { flex: 1; }

/* ── Toggle switches ── */
.nb-switch-row { display: flex; flex-wrap: wrap; gap: 14px; align-items: center; }
.nb-switch { display: flex; align-items: center; gap: 7px; cursor: pointer; font-size: 14px; font-weight: 500; color: #374151; user-select: none; }
.nb-switch input { display: none; }
.nb-switch-track { width: 38px; height: 21px; background: #cbd5e1; border-radius: 999px; position: relative; transition: background 0.2s; flex-shrink: 0; }
.nb-switch input:checked ~ .nb-switch-track { background: #6366f1; }
.nb-switch-track::after { content: ''; position: absolute; top: 3px; left: 3px; width: 15px; height: 15px; background: #fff; border-radius: 50%; transition: left 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
.nb-switch input:checked ~ .nb-switch-track::after { left: 20px; }

/* ── Buttons ── */
.nb-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; border-radius: 7px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; transition: filter 0.15s, transform 0.1s; text-decoration: none; }
.nb-btn:active { transform: scale(0.97); }
.nb-btn-primary { background: #6366f1; color: #fff; }
.nb-btn-primary:hover { filter: brightness(1.1); }
.nb-btn-success { background: #10b981; color: #fff; }
.nb-btn-danger { background: #ef4444; color: #fff; }
.nb-btn-ghost { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.nb-btn-ghost:hover { background: #e2e8f0; }
.nb-btn-sm { padding: 4px 12px; font-size: 13px; }
.nb-btn-icon { padding: 5px 9px; font-size: 15px; }

/* ── Alerts ── */
.nb-alert { padding: 11px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; margin-bottom: 16px; }
.nb-alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.nb-alert-danger  { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

/* ── Priority badge ── */
.nb-priority { display: inline-flex; align-items: center; padding: 2px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; }

/* ── Notice table ── */
.nb-table { width: 100%; border-collapse: collapse; }
.nb-table th { padding: 10px 12px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; background: #f8fafc; border-bottom: 2px solid #e2e8f0; text-align: left; }
.nb-table td { padding: 12px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: top; font-size: 14px; }
.nb-table tr:last-child td { border-bottom: none; }
.nb-table tr:hover td { background: #fafbff; }

/* ── Notice row accent ── */
.nb-row-accent { border-left: 4px solid #e2e8f0; }

/* ── Markdown preview ── */
.nb-md-preview { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 7px; padding: 12px 14px; font-size: 14px; line-height: 1.7; min-height: 80px; color: #1e293b; }
.nb-md-preview h3, .nb-md-preview h4, .nb-md-preview h5 { margin: 8px 0 4px; }
.nb-md-preview ul, .nb-md-preview ol { margin: 6px 0 6px 18px; padding: 0; }
.nb-md-preview code { background: rgba(0,0,0,0.07); padding: 1px 5px; border-radius: 3px; font-size: 0.88em; }
.nb-md-preview blockquote { border-left: 3px solid #cbd5e1; margin: 6px 0; padding: 2px 10px; color: #64748b; }
.nb-mention { background: rgba(99,102,241,0.12); color: #4f46e5; border-radius: 3px; padding: 0 3px; font-weight: 600; }

/* ── Collapsible section ── */
.nb-section-toggle { cursor: pointer; user-select: none; display: flex; align-items: center; gap: 6px; }
.nb-section-toggle::before { content: '▶'; font-size: 10px; transition: transform 0.2s; display: inline-block; }
.nb-section-toggle.open::before { transform: rotate(90deg); }
.nb-collapsible { display: none; }
.nb-collapsible.open { display: block; }

/* ── Tab bar ── */
.nb-tabs { display: flex; gap: 4px; border-bottom: 2px solid #e2e8f0; margin-bottom: 16px; }
.nb-tab { padding: 8px 16px; font-size: 14px; font-weight: 600; color: #64748b; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: color 0.15s, border-color 0.15s; }
.nb-tab.active { color: #6366f1; border-bottom-color: #6366f1; }

/* ── Poll bars ── */
.nb-poll-bar-wrap { display: flex; align-items: center; gap: 8px; margin-bottom: 5px; font-size: 13px; }
.nb-poll-bar-track { flex: 1; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; }
.nb-poll-bar-fill { height: 8px; background: #6366f1; border-radius: 4px; transition: width 0.4s; }

/* ── Admin chip ── */
.nb-chip { display: inline-flex; align-items: center; gap: 4px; background: #ede9fe; color: #5b21b6; border-radius: 999px; padding: 2px 9px; font-size: 12px; font-weight: 600; margin: 2px; }

/* ── Responsive ── */
@media (max-width: 700px) {
    .nb-grid, .nb-grid-3 { grid-template-columns: 1fr; }
    .nb-span2 { grid-column: span 1; }
}
</style>

<div id="nb-wrap">

<?php echo $message ?? ''; ?>

<!-- ══════════════════════════════════════════════════════════════════════════
     FORM CARD
══════════════════════════════════════════════════════════════════════════ -->
<div class="nb-card">
    <div class="nb-card-header">
        <h2>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
            <?php echo isset($edit_notice) ? 'Edit Notice' : 'New Notice'; ?>
        </h2>
        <?php if (isset($edit_notice)): ?>
            <a href="<?php echo $_SERVER['REQUEST_URI']; ?>" class="nb-btn nb-btn-ghost nb-btn-sm">Cancel Edit</a>
        <?php endif; ?>
    </div>
    <div class="nb-card-body">
        <form method="post" id="nb-form">
            <?php if (isset($edit_notice)): ?>
                <input type="hidden" name="edit_id" value="<?php echo (int)$edit_notice['id']; ?>">
            <?php endif; ?>

            <div class="nb-grid">
                <!-- Title -->
                <div class="nb-field">
                    <label>Notice Title <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="notice_title" required placeholder="e.g. Scheduled Maintenance"
                        value="<?php echo htmlspecialchars($edit_notice['notice_title'] ?? ''); ?>">
                </div>

                <!-- Priority -->
                <div class="nb-field">
                    <label>Priority</label>
                    <select name="priority" id="nb-priority">
                        <?php foreach ($priorityConfig as $pk => $pv): ?>
                            <option value="<?php echo $pk; ?>" <?php echo (($edit_notice['priority'] ?? 'normal') === $pk) ? 'selected' : ''; ?>>
                                <?php echo $pv['label']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Content (full width) -->
                <div class="nb-field nb-span2">
                    <label>
                        Notice Content
                        <span style="font-weight:400;color:#94a3b8;font-size:12px;margin-left:6px;">Supports Markdown — **bold**, *italic*, `code`, [link](url), lists, @mention</span>
                    </label>
                    <!-- Tabs -->
                    <div class="nb-tabs" style="margin-bottom:8px;">
                        <div class="nb-tab active" onclick="nbShowTab('write',this)">Write</div>
                        <div class="nb-tab" onclick="nbShowTab('preview',this)">Preview</div>
                    </div>
                    <div id="nb-tab-write">
                        <textarea name="notice_content" id="nb-content" rows="6"
                            placeholder="Write your notice here. Use **bold**, *italic*, - lists, @AdminName to mention..."
                            oninput="nbUpdatePreview()"><?php echo htmlspecialchars($edit_notice['notice_content'] ?? ''); ?></textarea>
                    </div>
                    <div id="nb-tab-preview" style="display:none;">
                        <div class="nb-md-preview" id="nb-preview-box">Start typing to preview…</div>
                    </div>
                </div>
            </div>

            <!-- Audience & Display -->
            <div style="margin-top:16px;">
                <div class="nb-switch-row">
                    <label class="nb-switch">
                        <input type="checkbox" name="show_to_admins" value="1" <?php echo !isset($edit_notice) || !empty($edit_notice['show_to_admins']) ? 'checked' : ''; ?>>
                        <span class="nb-switch-track"></span>
                        Show to Admins
                    </label>
                    <label class="nb-switch">
                        <input type="checkbox" name="show_to_clients" value="1" <?php echo !empty($edit_notice['show_to_clients']) ? 'checked' : ''; ?>>
                        <span class="nb-switch-track"></span>
                        Show to Clients
                    </label>
                    <label class="nb-switch">
                        <input type="checkbox" name="expandable" id="nb-expandable" value="1" <?php echo !empty($edit_notice['expandable']) ? 'checked' : ''; ?>>
                        <span class="nb-switch-track"></span>
                        Expandable
                    </label>
                    <label class="nb-switch">
                        <input type="checkbox" name="button_enabled" id="nb-btn-check" value="1" <?php echo !empty($edit_notice['button_enabled']) ? 'checked' : ''; ?> onchange="nbToggle('nb-btn-opts',this)">
                        <span class="nb-switch-track"></span>
                        CTA Button
                    </label>
                    <label class="nb-switch">
                        <input type="checkbox" name="ticket_enabled" id="nb-ticket-check" value="1" <?php echo !empty($edit_notice['ticket_enabled']) ? 'checked' : ''; ?> onchange="nbToggle('nb-ticket-opts',this)">
                        <span class="nb-switch-track"></span>
                        Ticket Button
                    </label>
                    <label class="nb-switch">
                        <input type="checkbox" name="poll_enabled" id="nb-poll-check" value="1" <?php echo !empty($edit_notice['poll_enabled']) ? 'checked' : ''; ?> onchange="nbToggle('nb-poll-opts',this)">
                        <span class="nb-switch-track"></span>
                        Poll
                    </label>
                </div>
            </div>

            <!-- Colours & display settings -->
            <div class="nb-grid-3" style="margin-top:16px;">
                <div class="nb-field">
                    <label>Background Colour</label>
                    <div class="nb-color-row">
                        <input type="color" id="nb-bg-picker" value="<?php echo htmlspecialchars($edit_notice['bg_color'] ?? '#fffae6'); ?>"
                            oninput="document.getElementById('nb-bg-hex').value=this.value">
                        <input type="text" id="nb-bg-hex" name="bg_color" value="<?php echo htmlspecialchars($edit_notice['bg_color'] ?? '#fffae6'); ?>"
                            oninput="document.getElementById('nb-bg-picker').value=this.value" maxlength="30">
                    </div>
                </div>
                <div class="nb-field">
                    <label>Font Colour</label>
                    <div class="nb-color-row">
                        <input type="color" id="nb-fg-picker" value="<?php echo htmlspecialchars($edit_notice['font_color'] ?? '#222222'); ?>"
                            oninput="document.getElementById('nb-fg-hex').value=this.value">
                        <input type="text" id="nb-fg-hex" name="font_color" value="<?php echo htmlspecialchars($edit_notice['font_color'] ?? '#222222'); ?>"
                            oninput="document.getElementById('nb-fg-picker').value=this.value" maxlength="30">
                    </div>
                </div>
                <div class="nb-field">
                    <label>Timestamp</label>
                    <input type="datetime-local" name="notice_timestamp"
                        value="<?php echo isset($edit_notice['notice_timestamp']) && $edit_notice['notice_timestamp'] ? date('Y-m-d\TH:i', strtotime($edit_notice['notice_timestamp'])) : ''; ?>">
                </div>
            </div>

            <!-- CTA Button options -->
            <div id="nb-btn-opts" style="display:<?php echo !empty($edit_notice['button_enabled']) ? 'block' : 'none'; ?>;margin-top:14px;padding:14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
                <div style="font-size:13px;font-weight:700;color:#64748b;margin-bottom:10px;text-transform:uppercase;letter-spacing:0.05em;">CTA Button</div>
                <div class="nb-grid">
                    <div class="nb-field">
                        <label>Button Text</label>
                        <input type="text" name="button_text" value="<?php echo htmlspecialchars($edit_notice['button_text'] ?? ''); ?>" placeholder="Learn More">
                    </div>
                    <div class="nb-field">
                        <label>Button URL</label>
                        <input type="text" name="button_link" value="<?php echo htmlspecialchars($edit_notice['button_link'] ?? ''); ?>" placeholder="https://...">
                    </div>
                    <div class="nb-field">
                        <label>Button Background</label>
                        <div class="nb-color-row">
                            <input type="color" id="nb-btnbg-picker" value="<?php echo htmlspecialchars($edit_notice['button_bg'] ?? '#2563eb'); ?>"
                                oninput="document.getElementById('nb-btnbg-hex').value=this.value">
                            <input type="text" id="nb-btnbg-hex" name="button_bg" value="<?php echo htmlspecialchars($edit_notice['button_bg'] ?? '#2563eb'); ?>"
                                oninput="document.getElementById('nb-btnbg-picker').value=this.value" maxlength="30">
                        </div>
                    </div>
                    <div class="nb-field">
                        <label>Button Text Colour</label>
                        <div class="nb-color-row">
                            <input type="color" id="nb-btnfg-picker" value="<?php echo htmlspecialchars($edit_notice['button_color'] ?? '#ffffff'); ?>"
                                oninput="document.getElementById('nb-btnfg-hex').value=this.value">
                            <input type="text" id="nb-btnfg-hex" name="button_color" value="<?php echo htmlspecialchars($edit_notice['button_color'] ?? '#ffffff'); ?>"
                                oninput="document.getElementById('nb-btnfg-picker').value=this.value" maxlength="30">
                        </div>
                    </div>
                </div>
                <div style="margin-top:10px;">
                    <label class="nb-switch">
                        <input type="checkbox" name="button_newtab" value="1" <?php echo !empty($edit_notice['button_newtab']) ? 'checked' : ''; ?>>
                        <span class="nb-switch-track"></span>
                        Open in new tab
                    </label>
                </div>
            </div>

            <!-- Ticket options -->
            <div id="nb-ticket-opts" style="display:<?php echo !empty($edit_notice['ticket_enabled']) ? 'block' : 'none'; ?>;margin-top:14px;padding:14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
                <div style="font-size:13px;font-weight:700;color:#64748b;margin-bottom:10px;text-transform:uppercase;letter-spacing:0.05em;">Ticket Button</div>
                <div class="nb-grid">
                    <div class="nb-field">
                        <label>Support Department</label>
                        <select name="ticket_department_id">
                            <option value="">— Select Department —</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept->id); ?>"
                                    <?php echo (($edit_notice['ticket_department_id'] ?? '') == $dept->id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="nb-field">
                        <label>Button Label</label>
                        <input type="text" name="ticket_button_text" value="<?php echo htmlspecialchars($edit_notice['ticket_button_text'] ?? ''); ?>" placeholder="Create Ticket">
                    </div>
                </div>
            </div>

            <!-- Poll options -->
            <div id="nb-poll-opts" style="display:<?php echo !empty($edit_notice['poll_enabled']) ? 'block' : 'none'; ?>;margin-top:14px;padding:14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
                <div style="font-size:13px;font-weight:700;color:#64748b;margin-bottom:10px;text-transform:uppercase;letter-spacing:0.05em;">Poll</div>
                <div class="nb-field" style="margin-bottom:12px;">
                    <label>Poll Question</label>
                    <input type="text" name="poll_question" id="nb-poll-q" value="<?php echo htmlspecialchars($edit_notice['poll_question'] ?? ''); ?>" placeholder="What do you think?">
                </div>
                <div class="nb-field">
                    <label>Options <span style="font-weight:400;color:#94a3b8;font-size:12px;">(one per line or use + button)</span></label>
                    <div id="nb-poll-options-list">
                        <?php
                        $existingOpts = $edit_notice['poll_options'] ?? [];
                        if (empty($existingOpts)) $existingOpts = ['', ''];
                        foreach ($existingOpts as $opt): ?>
                            <div class="nb-poll-opt-row" style="display:flex;gap:8px;margin-bottom:6px;">
                                <input type="text" name="poll_options[]" value="<?php echo htmlspecialchars($opt); ?>" placeholder="Option text" style="flex:1;padding:7px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;">
                                <button type="button" onclick="this.closest('.nb-poll-opt-row').remove()" class="nb-btn nb-btn-ghost nb-btn-sm" style="color:#ef4444;">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" id="nb-add-opt" class="nb-btn nb-btn-ghost nb-btn-sm" style="margin-top:4px;width:fit-content;">+ Add Option</button>
                </div>
            </div>

            <!-- Assign / Mention admins -->
            <?php if (!empty($admins)): ?>
            <div style="margin-top:16px;display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="nb-field">
                    <label>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:3px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Assign to Admins
                    </label>
                    <select name="assigned_admins[]" multiple style="height:110px;border-radius:7px;padding:4px;">
                        <?php foreach ($admins as $a): ?>
                            <option value="<?php echo (int)$a->id; ?>"
                                <?php echo in_array($a->id, $edit_notice['assigned_admins'] ?? []) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($a->firstname . ' ' . $a->lastname . ' (@' . $a->username . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color:#94a3b8;">Hold Ctrl/Cmd to select multiple</small>
                </div>
                <div class="nb-field">
                    <label>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:3px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        @Mention Admins
                    </label>
                    <select name="mentioned_admins[]" multiple style="height:110px;border-radius:7px;padding:4px;">
                        <?php foreach ($admins as $a): ?>
                            <option value="<?php echo (int)$a->id; ?>"
                                <?php echo in_array($a->id, $edit_notice['mentioned_admins'] ?? []) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($a->firstname . ' ' . $a->lastname . ' (@' . $a->username . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color:#94a3b8;">Also use @username inline in content</small>
                </div>
            </div>
            <?php endif; ?>

            <div style="margin-top:20px;display:flex;gap:10px;align-items:center;">
                <button type="submit" name="save_notice" class="nb-btn nb-btn-primary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    <?php echo isset($edit_notice) ? 'Update Notice' : 'Save Notice'; ?>
                </button>
                <?php if (isset($edit_notice)): ?>
                    <a href="<?php echo $_SERVER['REQUEST_URI']; ?>" class="nb-btn nb-btn-ghost">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     NOTICES TABLE
══════════════════════════════════════════════════════════════════════════ -->
<div class="nb-card">
    <div class="nb-card-header">
        <h2>
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            All Notices
        </h2>
        <span style="font-size:13px;color:#94a3b8;"><?php echo count($notices); ?> notice<?php echo count($notices) == 1 ? '' : 's'; ?></span>
    </div>

    <?php if (empty($notices)): ?>
        <div class="nb-card-body" style="text-align:center;color:#94a3b8;padding:40px;">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:10px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            <div>No notices yet. Create one above.</div>
        </div>
    <?php else: ?>
    <table class="nb-table">
        <thead>
            <tr>
                <th style="width:44%;">Notice</th>
                <th>Priority</th>
                <th>Audience</th>
                <th>Status</th>
                <th>Created</th>
                <th style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($notices as $n):
            $priority = $n['priority'] ?? 'normal';
            $pc       = $priorityConfig[$priority] ?? $priorityConfig['normal'];
            $accent   = $accentMap[$priority] ?? '#2563eb';
            $isActive = !empty($n['show_to_admins']) || !empty($n['show_to_clients']);
            $rowId    = 'nb-row-' . $n['id'];
        ?>
        <tr class="nb-row-accent" style="border-left-color:<?php echo $accent; ?>;">
            <td>
                <!-- Title row -->
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
                    <span style="font-weight:700;font-size:15px;"><?php echo htmlspecialchars($n['notice_title']); ?></span>
                    <?php if (!empty($n['notice_timestamp'])): ?>
                        <span style="font-size:12px;color:#94a3b8;"><?php echo date('M j, Y', strtotime($n['notice_timestamp'])); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Content preview (collapsible) -->
                <div style="color:#475569;font-size:13px;line-height:1.5;margin-bottom:6px;">
                    <?php
                    $preview = mb_strimwidth(strip_tags($n['notice_content'] ?? ''), 0, 160, '…');
                    echo htmlspecialchars($preview);
                    ?>
                </div>

                <!-- Assigned / Mentioned chips -->
                <?php if (!empty($n['assigned_admins'])): ?>
                    <div style="margin-bottom:4px;">
                        <span style="font-size:11px;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-right:4px;">Assigned:</span>
                        <?php foreach ($n['assigned_admins'] as $aid): ?>
                            <span class="nb-chip">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <?php echo htmlspecialchars($adminMap[$aid] ?? 'Admin #' . $aid); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($n['mentioned_admins'])): ?>
                    <div style="margin-bottom:4px;">
                        <span style="font-size:11px;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-right:4px;">Mentions:</span>
                        <?php foreach ($n['mentioned_admins'] as $aid): ?>
                            <span class="nb-chip" style="background:#dbeafe;color:#1d4ed8;">@<?php echo htmlspecialchars($adminMap[$aid] ?? 'Admin #' . $aid); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Poll results -->
                <?php if (!empty($n['poll_enabled']) && !empty($n['poll_question']) && !empty($n['poll_options'])): ?>
                    <div style="margin-top:8px;">
                        <div style="font-size:12px;font-weight:700;color:#64748b;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.04em;">
                            Poll: <?php echo htmlspecialchars($n['poll_question']); ?>
                        </div>
                        <?php
                        $results    = $n['poll_results'] ?? [];
                        $totalVotes = array_sum($results);
                        foreach ($n['poll_options'] as $opt):
                            $votes = $results[$opt] ?? 0;
                            $pct   = $totalVotes > 0 ? round(($votes / $totalVotes) * 100) : 0;
                        ?>
                        <div class="nb-poll-bar-wrap">
                            <span style="width:130px;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></span>
                            <div class="nb-poll-bar-track"><div class="nb-poll-bar-fill" style="width:<?php echo $pct; ?>%;"></div></div>
                            <span style="width:60px;font-size:12px;color:#64748b;"><?php echo $votes; ?> (<?php echo $pct; ?>%)</span>
                        </div>
                        <?php endforeach; ?>
                        <div style="font-size:11px;color:#94a3b8;margin-top:2px;"><?php echo $totalVotes; ?> total vote<?php echo $totalVotes == 1 ? '' : 's'; ?></div>

                        <!-- Quick vote form -->
                        <form method="post" style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
                            <input type="hidden" name="poll_notice_id" value="<?php echo (int)$n['id']; ?>">
                            <select name="poll_vote" style="padding:4px 8px;border:1px solid #cbd5e1;border-radius:5px;font-size:12px;">
                                <?php foreach ($n['poll_options'] as $opt): ?>
                                    <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="nb-btn nb-btn-sm" style="background:#6366f1;color:#fff;padding:4px 12px;">Vote</button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Full content accordion -->
                <div style="margin-top:8px;">
                    <button type="button" class="nb-btn nb-btn-ghost nb-btn-sm"
                        onclick="nbToggleRow('nb-full-<?php echo $n['id']; ?>',this)"
                        data-open="Hide" data-closed="Full Content">Full Content</button>
                </div>
                <div id="nb-full-<?php echo $n['id']; ?>" style="display:none;margin-top:8px;padding:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:7px;font-size:13px;line-height:1.7;">
                    <?php echo NoticeBannerHelper::parseMarkdown($n['notice_content'] ?? ''); ?>
                </div>
            </td>

            <td>
                <span class="nb-priority" style="background:<?php echo $pc['bg']; ?>;color:<?php echo $pc['color']; ?>;">
                    <?php echo $pc['label']; ?>
                </span>
            </td>

            <td style="font-size:13px;white-space:nowrap;">
                <?php if (!empty($n['show_to_admins'])): ?>
                    <div style="color:#5b21b6;">👤 Admins</div>
                <?php endif; ?>
                <?php if (!empty($n['show_to_clients'])): ?>
                    <div style="color:#0369a1;">🌐 Clients</div>
                <?php endif; ?>
                <?php if (empty($n['show_to_admins']) && empty($n['show_to_clients'])): ?>
                    <span style="color:#94a3b8;">Hidden</span>
                <?php endif; ?>
            </td>

            <td>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="toggle_show" value="<?php echo (int)$n['id']; ?>">
                    <button type="submit" class="nb-btn nb-btn-sm"
                        style="background:<?php echo $isActive ? '#dcfce7' : '#f1f5f9'; ?>;color:<?php echo $isActive ? '#166534' : '#64748b'; ?>;border:1px solid <?php echo $isActive ? '#bbf7d0' : '#e2e8f0'; ?>;">
                        <?php echo $isActive ? '● Active' : '○ Off'; ?>
                    </button>
                </form>
            </td>

            <td style="font-size:12px;color:#94a3b8;white-space:nowrap;">
                <?php echo isset($n['created_at']) ? date('M j, Y', strtotime($n['created_at'])) : '—'; ?>
            </td>

            <td style="text-align:right;white-space:nowrap;">
                <div style="display:flex;gap:5px;justify-content:flex-end;flex-wrap:wrap;">
                    <!-- Edit -->
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="edit_load" value="<?php echo (int)$n['id']; ?>">
                        <button type="submit" class="nb-btn nb-btn-ghost nb-btn-icon" title="Edit">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                    </form>
                    <!-- Move up -->
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="move_up" value="<?php echo (int)$n['id']; ?>">
                        <button type="submit" class="nb-btn nb-btn-ghost nb-btn-icon" title="Move Up">↑</button>
                    </form>
                    <!-- Move down -->
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="move_down" value="<?php echo (int)$n['id']; ?>">
                        <button type="submit" class="nb-btn nb-btn-ghost nb-btn-icon" title="Move Down">↓</button>
                    </form>
                    <!-- Delete -->
                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this notice?');">
                        <input type="hidden" name="delete_notice" value="<?php echo (int)$n['id']; ?>">
                        <button type="submit" class="nb-btn nb-btn-danger nb-btn-icon" title="Delete">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                        </button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

</div><!-- #nb-wrap -->

<script>
// ── Tab switching ─────────────────────────────────────────────────────────────
function nbShowTab(tab, el) {
    document.querySelectorAll('.nb-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('nb-tab-write').style.display   = tab === 'write'   ? 'block' : 'none';
    document.getElementById('nb-tab-preview').style.display = tab === 'preview' ? 'block' : 'none';
    if (tab === 'preview') nbUpdatePreview();
}

// ── Markdown preview (client-side, mirrors server logic) ──────────────────────
function nbMd(t) {
    t = t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    t = t.replace(/^### (.+)$/gm,'<h5 style="margin:8px 0 4px;">$1</h5>');
    t = t.replace(/^## (.+)$/gm, '<h4 style="margin:10px 0 4px;">$1</h4>');
    t = t.replace(/^# (.+)$/gm,  '<h3 style="margin:12px 0 4px;">$1</h3>');
    t = t.replace(/\*\*\*(.+?)\*\*\*/gs,'<strong><em>$1</em></strong>');
    t = t.replace(/\*\*(.+?)\*\*/gs,    '<strong>$1</strong>');
    t = t.replace(/\*(.+?)\*/gs,         '<em>$1</em>');
    t = t.replace(/__(.+?)__/gs,         '<strong>$1</strong>');
    t = t.replace(/_(.+?)_/gs,           '<em>$1</em>');
    t = t.replace(/`(.+?)`/g, '<code style="background:rgba(0,0,0,0.08);padding:1px 5px;border-radius:3px;font-size:0.9em;">$1</code>');
    t = t.replace(/\[([^\]]+)\]\((https?:\/\/[^\)]+)\)/g, '<a href="$2" target="_blank" style="text-decoration:underline;">$1</a>');
    t = t.replace(/^[-*] (.+)$/gm, '<li>$1</li>').replace(/(<li>.*<\/li>\n?)+/g, m => '<ul style="margin:6px 0 6px 18px;padding:0;">'+m+'</ul>');
    t = t.replace(/^\d+\. (.+)$/gm, '<li>$1</li>');
    t = t.replace(/^&gt; (.+)$/gm, '<blockquote style="border-left:3px solid #ccc;margin:6px 0;padding:2px 10px;color:#555;">$1</blockquote>');
    t = t.replace(/^---+$/gm, '<hr style="border:none;border-top:1px solid #ddd;margin:10px 0;">');
    t = t.replace(/@(\w+)/g, '<span style="background:rgba(99,102,241,0.15);color:#4f46e5;border-radius:3px;padding:0 3px;font-weight:600;">@$1</span>');
    t = t.replace(/\n/g, '<br>');
    return t;
}
function nbUpdatePreview() {
    var box = document.getElementById('nb-preview-box');
    if (!box) return;
    var val = document.getElementById('nb-content').value;
    box.innerHTML = val ? nbMd(val) : '<span style="color:#94a3b8;">Nothing to preview.</span>';
}

// ── Toggle collapsible sections ───────────────────────────────────────────────
function nbToggle(id, checkbox) {
    document.getElementById(id).style.display = checkbox.checked ? 'block' : 'none';
}
function nbToggleRow(id, btn) {
    var el = document.getElementById(id);
    var open = el.style.display !== 'none';
    el.style.display = open ? 'none' : 'block';
    btn.textContent = open ? (btn.dataset.closed || 'Show') : (btn.dataset.open || 'Hide');
}

// ── Poll option add ───────────────────────────────────────────────────────────
document.getElementById('nb-add-opt').addEventListener('click', function() {
    var list = document.getElementById('nb-poll-options-list');
    var row  = document.createElement('div');
    row.className = 'nb-poll-opt-row';
    row.style.cssText = 'display:flex;gap:8px;margin-bottom:6px;';
    row.innerHTML = '<input type="text" name="poll_options[]" placeholder="Option text" style="flex:1;padding:7px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;">'
                  + '<button type="button" onclick="this.closest(\'.nb-poll-opt-row\').remove()" class="nb-btn nb-btn-ghost nb-btn-sm" style="color:#ef4444;">&times;</button>';
    list.appendChild(row);
    row.querySelector('input').focus();
});

// ── Scroll to form on edit load ───────────────────────────────────────────────
<?php if (isset($edit_notice)): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('nb-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
});
<?php endif; ?>

// ── Init preview if editing ───────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    nbUpdatePreview();
});
</script>
