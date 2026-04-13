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

// ── Client group lookup map ───────────────────────────────────────────────────
$groupMap = [];
foreach ($clientGroups as $g) {
    $groupMap[$g->id] = $g->groupname;
}

$now = date('Y-m-d H:i:s');
?>
<style>
/* ── Reset & base ── */
#nb-wrap * { box-sizing: border-box; }
#nb-wrap { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 1200px; margin: 0 auto; color: #1e293b; }

/* ── Card ── */
.nb-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); margin-bottom: 24px; overflow: hidden; }
.nb-card-header { padding: 16px 22px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; background: #f8fafc; }
.nb-card-header h2 { margin: 0; font-size: 17px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px; }
.nb-card-body { padding: 22px; }

/* ── Form grid ── */
.nb-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.nb-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
.nb-grid-4 { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 16px; }
.nb-span2 { grid-column: span 2; }
.nb-span3 { grid-column: span 3; }
.nb-span4 { grid-column: span 4; }
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

/* Every datetime picker in this module uses the same chrome (not half native / half styled) */
#nb-wrap input[type=datetime-local] {
    min-width: 220px;
    max-width: 100%;
    padding: 8px 11px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    font-size: 14px;
    line-height: 1.35;
    color: #1e293b;
    background: #fff;
    min-height: 40px;
    box-sizing: border-box;
    transition: border-color 0.15s, box-shadow 0.15s;
    color-scheme: light;
}
#nb-wrap input[type=datetime-local]:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
}
#nb-wrap input[type=datetime-local]::-webkit-calendar-picker-indicator {
    cursor: pointer;
    opacity: 0.75;
    padding: 2px;
}
/* Inline text fields next to datetime in To-Do details (match .nb-field text) */
#nb-wrap .nb-todo-details-body input[type=text]:not(.nb-tag-real-input),
#nb-wrap .nb-todo-inline-form input[type=text] {
    padding: 8px 11px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    font-size: 14px;
    color: #1e293b;
    background: #fff;
    min-height: 40px;
    box-sizing: border-box;
    transition: border-color 0.15s, box-shadow 0.15s;
}
#nb-wrap .nb-todo-details-body input[type=text]:focus,
#nb-wrap .nb-todo-inline-form input[type=text]:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
}

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
.nb-btn-warning { background: #f59e0b; color: #fff; }
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

/* ── Status badges ── */
.nb-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; margin: 1px 2px; }
.nb-badge-expired   { background: #fee2e2; color: #991b1b; }
.nb-badge-scheduled { background: #fef9c3; color: #854d0e; }
.nb-badge-template  { background: #e0e7ff; color: #3730a3; }
.nb-badge-pinned    { background: #fef9c3; color: #854d0e; }
.nb-badge-active    { background: #dcfce7; color: #166534; }
.nb-badge-off       { background: #f1f5f9; color: #64748b; }

/* ── Notice table ── */
.nb-table { width: 100%; border-collapse: collapse; }
.nb-table th { padding: 10px 12px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; background: #f8fafc; border-bottom: 2px solid #e2e8f0; text-align: left; }
.nb-table td { padding: 12px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: top; font-size: 14px; }
.nb-table tr:last-child td { border-bottom: none; }
.nb-table tr:hover td { background: #fafbff; }
.nb-row-accent { border-left: 4px solid #e2e8f0; }

/* ── Markdown preview ── */
.nb-md-preview { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 7px; padding: 12px 14px; font-size: 14px; line-height: 1.7; min-height: 80px; color: #1e293b; }
.nb-md-preview h3, .nb-md-preview h4, .nb-md-preview h5 { margin: 8px 0 4px; }
.nb-md-preview ul, .nb-md-preview ol { margin: 6px 0 6px 18px; padding: 0; }
.nb-md-preview code { background: rgba(0,0,0,0.07); padding: 1px 5px; border-radius: 3px; font-size: 0.88em; }
.nb-md-preview blockquote { border-left: 3px solid #cbd5e1; margin: 6px 0; padding: 2px 10px; color: #64748b; }

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

/* ── To-Do workspace ── */
.nb-todo-editor-hint { margin-top: 16px; padding: 12px 16px; border-radius: 10px; border: 1px solid #c7d2fe; background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%); display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; font-size: 13px; color: #475569; }
.nb-todo-app { margin-top: 4px; }
.nb-todo-toolbar { display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
.nb-todo-layout { display: grid; grid-template-columns: 260px 1fr; gap: 16px; align-items: start; }
@media (max-width: 900px) {
  .nb-todo-layout { grid-template-columns: 1fr; }
}
.nb-todo-side { border: 1px solid #e2e8f0; border-radius: 12px; padding: 8px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.nb-todo-side-title { font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; padding: 6px 8px; }
.nb-todo-side a.nb-todo-side-item { display: block; padding: 10px 11px; margin: 5px 0; border-radius: 10px; text-decoration: none; border: 1px solid #e2e8f0; background: #fff; color: #0f172a; transition: border-color 0.15s, background 0.15s; }
.nb-todo-side a.nb-todo-side-item:hover { border-color: #c7d2fe; background: #fafbff; }
.nb-todo-side a.nb-todo-side-item.active { border-color: #a5b4fc; background: #eef2ff; box-shadow: inset 0 0 0 1px rgba(99,102,241,0.15); }
.nb-todo-main { min-width: 0; }
.nb-todo-banner-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
.nb-todo-banner-title { margin: 0; font-size: 18px; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; }
.nb-todo-banner-sub { font-size: 13px; color: #64748b; margin-top: 4px; line-height: 1.45; }
.nb-todo-composer { border: 1px dashed #c7d2fe; border-radius: 12px; padding: 12px 14px; background: linear-gradient(180deg, #fafbff 0%, #fff 100%); margin-bottom: 16px; }
.nb-todo-composer-title { font-size: 11px; font-weight: 700; color: #6366f1; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 8px; }
.nb-todo-board { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; }
.nb-todo-card { border-radius: 12px; border: 1px solid #e8e0c8; background: linear-gradient(165deg, #fffdf5 0%, #fff 48%, #f8fafc 100%); box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06); padding: 12px 12px 10px; display: flex; flex-direction: column; gap: 8px; min-height: 120px; }
.nb-todo-card.nb-todo-card-done { opacity: 0.88; background: #f8fafc; border-color: #e2e8f0; }
.nb-todo-card-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
.nb-todo-card-title { font-size: 14px; font-weight: 700; line-height: 1.35; color: #0f172a; margin: 0; flex: 1; }
.nb-todo-card-title.done { text-decoration: line-through; color: #64748b; }
.nb-todo-pill { font-size: 9px; font-weight: 800; letter-spacing: 0.06em; padding: 3px 7px; border-radius: 999px; white-space: nowrap; }
.nb-todo-meta { font-size: 11px; color: #64748b; }
.nb-todo-remark { font-size: 12px; color: #334155; background: rgba(255,255,255,0.7); border: 1px solid #e7e5e4; border-radius: 8px; padding: 6px 8px; }
.nb-todo-actions { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-top: auto; padding-top: 4px; border-top: 1px solid rgba(226, 232, 240, 0.8); }
.nb-todo-actions form { display: inline; }
.nb-todo-inline-form { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.nb-todo-subwrap { margin-top: 4px; padding-left: 10px; border-left: 3px solid #e2e8f0; display: flex; flex-direction: column; gap: 6px; }
.nb-todo-subrow { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; font-size: 12px; }
.nb-todo-subrow .nb-todo-subtitle { flex: 1; min-width: 120px; }
.nb-todo-subrow .nb-todo-subtitle.done { text-decoration: line-through; color: #64748b; }

/* Kanban + simplified cards */
.nb-todo-kanban { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; align-items: start; }
@media (max-width: 768px) { .nb-todo-kanban { grid-template-columns: 1fr; } }
.nb-todo-col { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px 10px 12px; min-height: 120px; }
.nb-todo-col-h { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b; margin: 0 0 10px 4px; display: flex; align-items: center; gap: 8px; }
.nb-todo-col-c { background: #0f172a; color: #fff; font-size: 11px; padding: 2px 8px; border-radius: 999px; font-weight: 800; }
.nb-todo-card-v2 { background: #fff; border: 1px solid #e8ecf1; border-radius: 12px; padding: 12px 12px 10px; margin-bottom: 10px; box-shadow: 0 1px 3px rgba(15,23,42,0.06); }
.nb-todo-card-v2:last-child { margin-bottom: 0; }
.nb-todo-card-v2.nb-muted { opacity: 0.92; }
.nb-todo-card-v2-head { display: flex; align-items: flex-start; gap: 10px; }
.nb-todo-ring-btn { width: 22px; height: 22px; border-radius: 50%; border: 2px solid #cbd5e1; background: #fff; cursor: pointer; padding: 0; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; transition: border-color 0.15s, background 0.15s; }
.nb-todo-ring-btn:hover { border-color: #94a3b8; }
.nb-todo-ring-btn.nb-on { border: none; background: linear-gradient(145deg, #f97316, #ea580c); color: #fff; box-shadow: 0 1px 4px rgba(234,88,12,0.35); }
.nb-todo-card-v2-title { font-size: 14px; font-weight: 700; color: #0f172a; line-height: 1.35; margin: 0; }
.nb-todo-card-v2-title.done { text-decoration: line-through; color: #64748b; }
.nb-todo-date-pill { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: #f1f5f9; color: #475569; white-space: nowrap; }
.nb-todo-date-pill.due-soon { background: #fff7ed; color: #c2410c; }
.nb-todo-date-pill.overdue { background: #fef2f2; color: #b91c1c; }
.nb-todo-remark-line { font-size: 12px; color: #64748b; margin-top: 6px; line-height: 1.4; }
.nb-todo-subbar { margin-top: 8px; display: flex; align-items: center; gap: 8px; }
.nb-todo-subcount { font-size: 11px; font-weight: 700; color: #64748b; }
.nb-todo-subprog { flex: 1; height: 5px; background: #e2e8f0; border-radius: 999px; overflow: hidden; }
.nb-todo-subprog > span { display: block; height: 100%; background: linear-gradient(90deg, #6366f1, #8b5cf6); border-radius: 999px; transition: width 0.25s; }
.nb-todo-submini { display: flex; align-items: center; gap: 8px; font-size: 12px; margin-top: 4px; padding-left: 2px; }
.nb-todo-details { margin-top: 8px; border-top: 1px solid #f1f5f9; padding-top: 6px; }
.nb-todo-details > summary { cursor: pointer; font-size: 12px; font-weight: 600; color: #6366f1; list-style: none; }
.nb-todo-details > summary::-webkit-details-marker { display: none; }
.nb-todo-details-body { padding-top: 10px; display: flex; flex-direction: column; gap: 8px; }
.nb-todo-composer-v2 { border: 1px dashed #c7d2fe; border-radius: 12px; padding: 12px 14px; background: #fff; margin-bottom: 16px; }
details.nb-todo-add-fold { border: 1px dashed #c7d2fe; border-radius: 12px; background: #fff; margin-bottom: 16px; overflow: hidden; }
details.nb-todo-add-fold > summary.nb-todo-add-fold-sum { list-style: none; cursor: pointer; padding: 12px 14px; font-weight: 800; font-size: 13px; color: #6366f1; text-transform: uppercase; letter-spacing: 0.06em; user-select: none; }
details.nb-todo-add-fold > summary::-webkit-details-marker { display: none; }
details.nb-todo-add-fold > summary.nb-todo-add-fold-sum::before { content: '▸ '; font-size: 11px; opacity: 0.55; }
details.nb-todo-add-fold[open] > summary.nb-todo-add-fold-sum::before { content: '▾ '; }
.nb-todo-add-fold-body { padding: 0 14px 14px; border-top: 1px solid #f1f5f9; }
.nb-todo-flat-list { display: flex; flex-direction: column; gap: 14px; }
details.nb-todo-flat-block { border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 14px; background: #fff; box-shadow: 0 1px 3px rgba(15,23,42,0.05); }
.nb-todo-flat-block { border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 14px; background: #fff; box-shadow: 0 1px 3px rgba(15,23,42,0.05); }
details.nb-todo-task-fold > summary.nb-todo-task-fold-sum { list-style: none; display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; cursor: pointer; font-weight: 800; font-size: 14px; color: #0f172a; padding: 6px 4px 12px 4px; margin: -4px -4px 4px -4px; border-bottom: 1px solid #f1f5f9; user-select: none; }
details.nb-todo-task-fold > summary::-webkit-details-marker { display: none; }
details.nb-todo-task-fold > summary::before { content: '▾ '; font-size: 11px; opacity: 0.55; }
details.nb-todo-task-fold:not([open]) > summary::before { content: '▸ '; }
.nb-todo-task-fold-hint { font-size: 12px; font-weight: 600; color: #94a3b8; margin-left: auto; }
.nb-todo-meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 6px; }
@media (max-width: 720px) { .nb-todo-meta-grid { grid-template-columns: 1fr; } }
.nb-todo-assign-multi { min-height: 72px; width: 100%; }
.nb-todo-flat-row { display: grid; grid-template-columns: auto 1fr; gap: 10px 12px; align-items: start; margin-bottom: 12px; }
.nb-todo-flat-row:last-child { margin-bottom: 0; }
.nb-todo-flat-row.nb-todo-flat-sub { margin-left: 8px; padding-left: 12px; border-left: 3px solid #e0e7ff; }
.nb-todo-flat-toggle { margin-top: 4px; }
.nb-todo-flat-main { min-width: 0; }
.nb-todo-flat-savegrid { display: grid; grid-template-columns: 1fr; gap: 8px; }
.nb-todo-flat-savegrid .nb-todo-flat-line2 { display: grid; grid-template-columns: minmax(0,220px) auto; gap: 8px; align-items: end; justify-content: flex-start; }
@media (max-width: 720px) {
  .nb-todo-flat-savegrid .nb-todo-flat-line2 { grid-template-columns: 1fr; }
}
.nb-todo-flat-meta { font-size: 11px; font-weight: 700; color: #6366f1; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 4px; }
.nb-todo-flat-actions { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-top: 8px; padding-top: 8px; border-top: 1px solid #f1f5f9; }
.nb-todo-flat-addsub { display: grid; grid-template-columns: 1fr minmax(0,180px) auto; gap: 8px; align-items: end; margin-top: 10px; padding-top: 10px; border-top: 1px dashed #e2e8f0; }
@media (max-width: 720px) {
  .nb-todo-flat-addsub { grid-template-columns: 1fr; }
}
#nb-wrap .nb-todo-note-details { margin-top: 6px; border-radius: 8px; border: 1px solid #e2e8f0; background: #fafbff; padding: 0 10px 8px; }
#nb-wrap .nb-todo-note-details summary { list-style: none; padding: 8px 0; font-size: 12px; font-weight: 600; color: #64748b; cursor: pointer; user-select: none; }
#nb-wrap .nb-todo-note-details summary::-webkit-details-marker { display: none; }
#nb-wrap .nb-todo-note-details summary::before { content: '▸ '; font-size: 10px; opacity: 0.7; }
#nb-wrap .nb-todo-note-details[open] summary::before { content: '▾ '; }
#nb-wrap .nb-todo-note-details .nb-field textarea { min-height: 56px; font-size: 13px; }

/* ── Tag chip ── */
.nb-tag { display: inline-flex; align-items: center; gap: 3px; background: rgba(99,102,241,0.1); color: #4338ca; border-radius: 999px; padding: 2px 8px; font-size: 11px; font-weight: 600; margin: 2px; cursor: pointer; }
.nb-tag:hover { background: rgba(99,102,241,0.2); }
.nb-tag.active { background: #6366f1; color: #fff; }

/* ── Tag input ── */
.nb-tag-input-wrap { display: flex; flex-wrap: wrap; gap: 5px; padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 7px; min-height: 40px; align-items: center; cursor: text; }
.nb-tag-input-wrap:focus-within { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
.nb-tag-pill { display: inline-flex; align-items: center; gap: 4px; background: #ede9fe; color: #5b21b6; border-radius: 999px; padding: 2px 8px; font-size: 12px; font-weight: 600; }
.nb-tag-pill button { background: none; border: none; cursor: pointer; color: #7c3aed; font-size: 13px; line-height: 1; padding: 0 1px; }
.nb-tag-real-input { border: none; outline: none; font-size: 13px; min-width: 80px; flex: 1; padding: 2px 4px; }

/* ── Read count ── */
.nb-read-count { display: inline-flex; align-items: center; gap: 3px; font-size: 11px; color: #64748b; background: #f1f5f9; border-radius: 999px; padding: 1px 7px; margin: 1px; }

/* ── Activity log ── */
.nb-log-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.nb-log-table th { padding: 8px 10px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; background: #f8fafc; border-bottom: 1px solid #e2e8f0; text-align: left; }
.nb-log-table td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
.nb-log-table tr:last-child td { border-bottom: none; }

/* ── Responsive ── */
@media (max-width: 900px) {
    .nb-grid-4 { grid-template-columns: 1fr 1fr; }
    .nb-span4 { grid-column: span 2; }
}
@media (max-width: 700px) {
    .nb-grid, .nb-grid-3, .nb-grid-4 { grid-template-columns: 1fr; }
    .nb-span2, .nb-span3, .nb-span4 { grid-column: span 1; }
}

/* ── Pro lock badge ── */
.nb-pro-lock { display:inline-flex;align-items:center;gap:4px;background:#fef9c3;color:#854d0e;border:1px solid #fde68a;border-radius:999px;padding:1px 9px;font-size:11px;font-weight:700;margin-left:6px;vertical-align:middle; }
.nb-pro-section { opacity:0.55;pointer-events:none;user-select:none;position:relative; }
.nb-pro-section::after { content:'🔒 Pro'; position:absolute;top:6px;right:10px;font-size:11px;font-weight:700;color:#854d0e;background:#fef9c3;border:1px solid #fde68a;border-radius:999px;padding:1px 8px; }

/* ── License status badge ── */
.nb-lic-valid   { background:#dcfce7;color:#166534;border:1px solid #bbf7d0; }
.nb-lic-invalid { background:#fee2e2;color:#991b1b;border:1px solid #fecaca; }
.nb-lic-error   { background:#fef9c3;color:#854d0e;border:1px solid #fde68a; }
.nb-lic-unknown,.nb-lic-no_key { background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0; }
.nb-lic-badge { display:inline-flex;align-items:center;gap:5px;padding:4px 14px;border-radius:999px;font-size:13px;font-weight:700; }

/* ── Main tab panes ── */
.nb-main-tab-pane { display:none; }
.nb-main-tab-pane.active { display:block; }
</style>

<div id="nb-wrap">

<?php echo $message ?? ''; ?>

<!-- ══════════════════════════════════════════════════════════════════════════
     MAIN TAB BAR
══════════════════════════════════════════════════════════════════════════ -->
<?php
$licStatus = $licenseStatus['status'] ?? 'unknown';
$licPlan   = $licenseStatus['plan'] ?? 'free';
$licBadgeClass = in_array($licStatus, ['valid'], true) ? 'nb-lic-valid'
    : (in_array($licStatus, ['invalid', 'domain_mismatch'], true) ? 'nb-lic-invalid'
    : (in_array($licStatus, ['error'], true) ? 'nb-lic-error' : 'nb-lic-unknown'));
$licBadgeLabel = $isPro ? '✓ Pro Active' : (($licStatus === 'no_key' || $licStatus === 'unknown') ? '🔒 Free Tier' : '⚠ ' . ucfirst(str_replace('_', ' ', $licStatus)));
?>
<div class="nb-tabs" id="nb-main-tabs">
    <span class="nb-tab active" onclick="nbMainTab('notices',this)">📋 Notices
        <?php if (!$isPro): ?>
        <span style="font-size:11px;font-weight:600;color:#854d0e;background:#fef9c3;border:1px solid #fde68a;border-radius:999px;padding:1px 7px;margin-left:4px;"><?php echo $activeCount; ?>/<?php echo $freeCap; ?></span>
        <?php endif; ?>
    </span>
    <span class="nb-tab" id="nb-tab-promotions" onclick="nbMainTab('promotions',this)">🎁 Promotions</span>
    <span class="nb-tab" onclick="nbMainTab('todo-banners',this)">🧩 To-Do Banners</span>
    <span class="nb-tab" onclick="nbMainTab('license',this)">🔑 License &amp; Settings
        <span class="nb-lic-badge <?php echo $licBadgeClass; ?>" style="padding:1px 8px;font-size:11px;margin-left:4px;"><?php echo htmlspecialchars($licBadgeLabel); ?></span>
    </span>
    <span class="nb-tab" onclick="nbMainTab('log',this)">📜 Activity Log</span>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     TAB PANE: NOTICES
══════════════════════════════════════════════════════════════════════════ -->
<div id="nb-pane-notices" class="nb-main-tab-pane active">

<!-- ══════════════════════════════════════════════════════════════════════════
     TEMPLATE PICKER (Pro only — shown when templates exist and not editing)
══════════════════════════════════════════════════════════════════════════ -->
<?php if ($isPro && !empty($templates) && !isset($edit_notice)): ?>
<div class="nb-card" style="border-color:#e0e7ff;background:#f5f3ff;">
    <div class="nb-card-body" style="padding:14px 22px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        <span style="font-size:14px;font-weight:600;color:#3730a3;">Start from template:</span>
        <select id="nb-template-picker" style="padding:6px 10px;border:1px solid #c7d2fe;border-radius:7px;font-size:14px;color:#1e293b;background:#fff;">
            <option value="">— Select a template —</option>
            <?php foreach ($templates as $tpl): ?>
                <option value="<?php echo (int)$tpl['id']; ?>"
                    data-title="<?php echo htmlspecialchars($tpl['notice_title']); ?>"
                    data-content="<?php echo htmlspecialchars($tpl['notice_content'] ?? ''); ?>"
                    data-priority="<?php echo htmlspecialchars($tpl['priority'] ?? 'normal'); ?>"
                    data-bg="<?php echo htmlspecialchars($tpl['bg_color'] ?? '#fffae6'); ?>"
                    data-fg="<?php echo htmlspecialchars($tpl['font_color'] ?? '#222222'); ?>"
                    data-tags="<?php echo htmlspecialchars($tpl['tags'] ?? ''); ?>"
                    data-admins="<?php echo htmlspecialchars(json_encode($tpl['assigned_admins'] ?? [])); ?>"
                >
                    <?php echo htmlspecialchars($tpl['template_name'] ?: $tpl['notice_title']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="nb-btn nb-btn-primary nb-btn-sm" onclick="nbApplyTemplate()">Use Template</button>
    </div>
</div>
<?php endif; ?>

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
        <?php if (!$isPro): ?>
        <div style="background:#fef9c3;border:1px solid #fde68a;border-radius:8px;padding:10px 16px;margin-bottom:16px;font-size:13px;color:#854d0e;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span>🔒 <strong>Free Tier</strong> — <?php echo $activeCount; ?>/<?php echo $freeCap; ?> notices used. Advanced fields (polls, webhooks, targeting, tags, scheduling, etc.) require a <a href="https://hostingspell.com" target="_blank" rel="noopener" style="color:#854d0e;font-weight:700;">Pro license</a>.</span>
        </div>
        <?php endif; ?>
        <form method="post" id="nb-form">
            <?php if (isset($edit_notice)): ?>
                <input type="hidden" name="edit_id" value="<?php echo (int)$edit_notice['id']; ?>">
            <?php endif; ?>

            <div class="nb-grid">
                <!-- Title -->
                <div class="nb-field">
                    <label>Notice Title <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="notice_title" id="nb-title" required placeholder="e.g. Scheduled Maintenance"
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

            <!-- Audience & Display toggles -->
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
                        <input type="checkbox" name="is_pinned" value="1" <?php echo !empty($edit_notice['is_pinned']) ? 'checked' : ''; ?>>
                        <span class="nb-switch-track"></span>
                        📌 Pin to Top
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

            <!-- Colours & scheduling -->
            <div class="nb-grid-4" style="margin-top:16px;">
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
                    <label>Notice Timestamp</label>
                    <input type="datetime-local" name="notice_timestamp"
                        value="<?php echo isset($edit_notice['notice_timestamp']) && $edit_notice['notice_timestamp'] ? date('Y-m-d\TH:i', strtotime($edit_notice['notice_timestamp'])) : ''; ?>">
                </div>
                <div class="nb-field">
                    <label>Show Again After (min)</label>
                    <input type="number" name="show_again_minutes" min="0" value="<?php echo (int)($edit_notice['show_again_minutes'] ?? 60); ?>">
                </div>
            </div>

            <?php if ($isPro): ?>
            <!-- Scheduling: publish_at + expires_at (Pro) -->
            <div class="nb-grid" style="margin-top:14px;">
                <div class="nb-field">
                    <label>
                        🕐 Publish At
                        <span style="font-weight:400;color:#94a3b8;font-size:12px;margin-left:4px;">Leave blank to publish immediately.</span>
                    </label>
                    <input type="datetime-local" name="publish_at"
                        value="<?php echo isset($edit_notice['publish_at']) && $edit_notice['publish_at'] ? date('Y-m-d\TH:i', strtotime($edit_notice['publish_at'])) : ''; ?>">
                </div>
                <div class="nb-field">
                    <label>
                        ⏰ Expires At
                        <span style="font-weight:400;color:#94a3b8;font-size:12px;margin-left:4px;">Auto-hide after this time.</span>
                    </label>
                    <input type="datetime-local" name="expires_at"
                        value="<?php echo isset($edit_notice['expires_at']) && $edit_notice['expires_at'] ? date('Y-m-d\TH:i', strtotime($edit_notice['expires_at'])) : ''; ?>">
                </div>
            </div>
            <?php endif; ?>

            <?php if ($isPro): ?>
            <!-- Tags (Pro) -->
            <div class="nb-field" style="margin-top:14px;">
                <label>
                    🏷 Tags
                    <span style="font-weight:400;color:#94a3b8;font-size:12px;margin-left:4px;">Comma-separated. Press Enter or comma to add.</span>
                </label>
                <div class="nb-tag-input-wrap" id="nb-tag-wrap" onclick="document.getElementById('nb-tag-input').focus()">
                    <?php
                    $existingTags = array_filter(array_map('trim', explode(',', $edit_notice['tags'] ?? '')));
                    foreach ($existingTags as $et):
                    ?>
                        <span class="nb-tag-pill" data-tag="<?php echo htmlspecialchars($et); ?>">
                            #<?php echo htmlspecialchars($et); ?>
                            <button type="button" onclick="nbRemoveTag(this)" tabindex="-1">&times;</button>
                        </span>
                    <?php endforeach; ?>
                    <input type="text" id="nb-tag-input" class="nb-tag-real-input" placeholder="Add tag…" autocomplete="off"
                        onkeydown="nbTagKeydown(event)" list="nb-tag-suggestions">
                    <datalist id="nb-tag-suggestions">
                        <?php foreach ($allTags as $at): ?>
                            <option value="<?php echo htmlspecialchars($at); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <input type="hidden" name="tags" id="nb-tags-hidden" value="<?php echo htmlspecialchars($edit_notice['tags'] ?? ''); ?>">
            </div>
            <?php endif; // Pro: Tags ?>

            <?php if ($isPro): ?>
            <!-- CTA Button options (Pro) -->
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
                                <input type="text" name="poll_options[]" value="<?php echo htmlspecialchars($opt, ENT_NOQUOTES, 'UTF-8'); ?>" placeholder="Option text" style="flex:1;padding:7px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;">
                                <button type="button" onclick="this.closest('.nb-poll-opt-row').remove()" class="nb-btn nb-btn-ghost nb-btn-sm" style="color:#ef4444;">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" id="nb-add-opt" class="nb-btn nb-btn-ghost nb-btn-sm" style="margin-top:4px;width:fit-content;">+ Add Option</button>
                </div>
            </div>

            <!-- Assign admins -->
            <?php if (!empty($admins)): ?>
            <div style="margin-top:16px;">
                <div class="nb-field">
                    <label>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Assign to Admins
                        <span style="font-weight:400;color:#94a3b8;font-size:12px;margin-left:6px;">Only assigned admins will see this banner. Leave empty to show to all admins.</span>
                    </label>
                    <div style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap;">
                        <select name="assigned_admins[]" multiple id="nb-assigned-select"
                            style="flex:1;min-width:220px;height:120px;border:1px solid #cbd5e1;border-radius:7px;padding:4px;font-size:14px;">
                            <?php foreach ($admins as $a): ?>
                                <option value="<?php echo (int)$a->id; ?>"
                                    <?php echo in_array($a->id, $edit_notice['assigned_admins'] ?? []) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($a->firstname . ' ' . $a->lastname . ' — @' . $a->username); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div style="display:flex;flex-direction:column;gap:6px;padding-top:2px;">
                            <button type="button" class="nb-btn nb-btn-ghost nb-btn-sm" onclick="document.querySelectorAll('#nb-assigned-select option').forEach(o=>o.selected=true)">Select All</button>
                            <button type="button" class="nb-btn nb-btn-ghost nb-btn-sm" onclick="document.querySelectorAll('#nb-assigned-select option').forEach(o=>o.selected=false)">Clear</button>
                        </div>
                    </div>
                    <small style="color:#94a3b8;">Hold Ctrl / Cmd to select multiple.</small>
                </div>
            </div>
            <?php endif; ?>

            <!-- Client group targeting -->
            <?php if (!empty($clientGroups)): ?>
            <div style="margin-top:16px;">
                <div class="nb-field">
                    <label>
                        🌐 Client Group Targeting
                        <span style="font-weight:400;color:#94a3b8;font-size:12px;margin-left:6px;">Only clients in selected groups will see this. Leave empty to show to all clients.</span>
                    </label>
                    <div style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap;">
                        <select name="client_groups[]" multiple id="nb-groups-select"
                            style="flex:1;min-width:220px;height:100px;border:1px solid #cbd5e1;border-radius:7px;padding:4px;font-size:14px;">
                            <?php foreach ($clientGroups as $g): ?>
                                <option value="<?php echo (int)$g->id; ?>"
                                    <?php echo in_array($g->id, $edit_notice['client_groups'] ?? []) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($g->groupname); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div style="display:flex;flex-direction:column;gap:6px;padding-top:2px;">
                            <button type="button" class="nb-btn nb-btn-ghost nb-btn-sm" onclick="document.querySelectorAll('#nb-groups-select option').forEach(o=>o.selected=true)">Select All</button>
                            <button type="button" class="nb-btn nb-btn-ghost nb-btn-sm" onclick="document.querySelectorAll('#nb-groups-select option').forEach(o=>o.selected=false)">Clear</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── Granular Targeting (Clients / Servers / Products) ── -->
            <div style="margin-top:16px;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
                <div class="nb-section-toggle" id="nb-target-toggle" onclick="nbToggleSection('nb-target-body','nb-target-toggle')"
                    style="padding:10px 14px;background:#f0fdf4;font-size:13px;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #e2e8f0;">
                    🎯 Granular Targeting — Specific Clients / Servers / Products
                    <?php
                    $hasTargeting = !empty($edit_notice['target_clients']) || !empty($edit_notice['target_servers']) || !empty($edit_notice['target_products']);
                    if ($hasTargeting): ?>
                        <span style="margin-left:8px;background:#166534;color:#fff;border-radius:999px;padding:1px 8px;font-size:11px;font-weight:700;">Active</span>
                    <?php endif; ?>
                </div>
                <div id="nb-target-body" class="nb-collapsible <?php echo $hasTargeting ? 'open' : ''; ?>" style="padding:16px;">
                    <p style="margin:0 0 14px;font-size:13px;color:#64748b;line-height:1.6;">
                        These filters are <strong>additive</strong> — a client must match <em>all</em> non-empty conditions below to see the notice.
                        Leave all blank to show to all clients (subject to group/page filters above).
                    </p>

                    <!-- Specific Clients -->
                    <div class="nb-field" style="margin-bottom:16px;">
                        <label>
                            👤 Specific Clients
                            <span style="font-weight:400;color:#94a3b8;font-size:12px;margin-left:4px;">Search and add individual clients. Leave empty to target all.</span>
                        </label>
                        <!-- Selected clients chips -->
                        <div id="nb-client-chips" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;min-height:10px;">
                            <?php foreach ($edit_notice['target_clients'] ?? [] as $cid):
                                $cid = (int)$cid;
                            ?>
                                <span class="nb-chip" id="nb-client-chip-<?php echo $cid; ?>" data-id="<?php echo $cid; ?>">
                                    <span class="nb-client-chip-name">
                                        <?php
                                        // Resolve name inline for edit mode
                                        $cRow = null;
                                        foreach (noticebanner_get_clients_by_ids([$cid]) as $cr) { $cRow = $cr; }
                                        echo $cRow ? htmlspecialchars($cRow->firstname . ' ' . $cRow->lastname . ' (' . $cRow->email . ')') : 'Client #' . $cid;
                                        ?>
                                    </span>
                                    <button type="button" onclick="nbRemoveClient(<?php echo $cid; ?>)" style="background:none;border:none;cursor:pointer;color:#7c3aed;font-size:13px;line-height:1;padding:0 0 0 4px;">&times;</button>
                                    <input type="hidden" name="target_clients[]" value="<?php echo $cid; ?>">
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <!-- Search box -->
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <input type="text" id="nb-client-search" placeholder="Search by name or email…"
                                style="flex:1;min-width:200px;padding:8px 11px;border:1px solid #cbd5e1;border-radius:7px;font-size:14px;"
                                oninput="nbClientSearch(this.value)" autocomplete="off">
                            <div id="nb-client-results" style="display:none;position:absolute;z-index:9999;background:#fff;border:1px solid #e2e8f0;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,0.1);min-width:320px;max-height:220px;overflow-y:auto;"></div>
                        </div>
                        <small style="color:#94a3b8;margin-top:4px;">Type at least 2 characters to search.</small>
                    </div>

                    <div class="nb-grid">
                        <!-- Specific Servers -->
                        <?php if (!empty($servers)): ?>
                        <div class="nb-field">
                            <label>
                                🖥 Specific Servers
                                <span style="font-weight:400;color:#94a3b8;font-size:12px;margin-left:4px;">Show only to clients with an active service on these servers.</span>
                            </label>
                            <div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                                <select name="target_servers[]" multiple id="nb-servers-select"
                                    style="flex:1;min-width:200px;height:110px;border:1px solid #cbd5e1;border-radius:7px;padding:4px;font-size:13px;">
                                    <?php foreach ($servers as $srv): ?>
                                        <option value="<?php echo (int)$srv->id; ?>"
                                            <?php echo in_array($srv->id, $edit_notice['target_servers'] ?? []) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($srv->name . ($srv->hostname ? ' (' . $srv->hostname . ')' : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div style="display:flex;flex-direction:column;gap:5px;padding-top:2px;">
                                    <button type="button" class="nb-btn nb-btn-ghost nb-btn-sm" onclick="document.querySelectorAll('#nb-servers-select option').forEach(o=>o.selected=true)">All</button>
                                    <button type="button" class="nb-btn nb-btn-ghost nb-btn-sm" onclick="document.querySelectorAll('#nb-servers-select option').forEach(o=>o.selected=false)">None</button>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Specific Products -->
                        <?php if (!empty($products)): ?>
                        <div class="nb-field">
                            <label>
                                📦 Specific Products / Services
                                <span style="font-weight:400;color:#94a3b8;font-size:12px;margin-left:4px;">Show only to clients with an active service for these products.</span>
                            </label>
                            <div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                                <select name="target_products[]" multiple id="nb-products-select"
                                    style="flex:1;min-width:200px;height:110px;border:1px solid #cbd5e1;border-radius:7px;padding:4px;font-size:13px;">
                                    <?php
                                    $lastGroup = null;
                                    foreach ($products as $prod):
                                        if ($prod->group_name !== $lastGroup):
                                            if ($lastGroup !== null) echo '</optgroup>';
                                            echo '<optgroup label="' . htmlspecialchars($prod->group_name ?: 'Ungrouped') . '">';
                                            $lastGroup = $prod->group_name;
                                        endif;
                                    ?>
                                        <option value="<?php echo (int)$prod->id; ?>"
                                            <?php echo in_array($prod->id, $edit_notice['target_products'] ?? []) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($prod->name); ?>
                                        </option>
                                    <?php endforeach;
                                    if ($lastGroup !== null) echo '</optgroup>'; ?>
                                </select>
                                <div style="display:flex;flex-direction:column;gap:5px;padding-top:2px;">
                                    <button type="button" class="nb-btn nb-btn-ghost nb-btn-sm" onclick="document.querySelectorAll('#nb-products-select option').forEach(o=>o.selected=true)">All</button>
                                    <button type="button" class="nb-btn nb-btn-ghost nb-btn-sm" onclick="document.querySelectorAll('#nb-products-select option').forEach(o=>o.selected=false)">None</button>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Advanced section (page slugs + webhook) -->
            <div style="margin-top:16px;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
                <div class="nb-section-toggle" id="nb-adv-toggle" onclick="nbToggleSection('nb-adv-body','nb-adv-toggle')"
                    style="padding:10px 14px;background:#f8fafc;font-size:13px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;">
                    Advanced Options
                </div>
                <div id="nb-adv-body" class="nb-collapsible" style="padding:14px;">
                    <div class="nb-grid">
                        <div class="nb-field">
                            <label>
                                📄 Page Targeting (Client Area)
                                <span style="font-weight:400;color:#94a3b8;font-size:12px;margin-left:4px;">One URI pattern per line. Leave blank for all pages.</span>
                            </label>
                            <textarea name="page_slugs_raw" rows="4" placeholder="/clientarea.php?action=services&#10;/index.php*&#10;/cart.php*"
                                style="font-family:monospace;font-size:12px;"><?php
                                $existingSlugs = $edit_notice['page_slugs'] ?? [];
                                echo htmlspecialchars(implode("\n", $existingSlugs));
                            ?></textarea>
                        </div>
                        <div class="nb-field">
                            <label>
                                🔔 Webhook URL (per-notice override)
                                <span style="font-weight:400;color:#94a3b8;font-size:12px;margin-left:4px;">Overrides global config webhook.</span>
                            </label>
                            <input type="text" name="notice_webhook_url"
                                value="<?php echo htmlspecialchars($edit_notice['webhook_url'] ?? ''); ?>"
                                placeholder="https://hooks.slack.com/...">
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; // Pro: CTA/Ticket/Poll/Targeting/Webhook ?>

            <?php if (isset($edit_notice) && !empty($edit_notice['id'])): ?>
            <div class="nb-todo-editor-hint">
                <span>Checklist tasks for this notice are managed in the <strong>To-Do Banners</strong> tab (card layout, subtasks, due dates).</span>
                <a class="nb-btn nb-btn-primary nb-btn-sm" href="<?php echo htmlspecialchars('addonmodules.php?module=noticebanner&todo_banner_range=all&todo_notice_id=' . (int)$edit_notice['id'] . '#nb-todo-banners'); ?>">Open To-Do workspace</a>
            </div>
            <?php endif; ?>

            <div style="margin-top:20px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <button type="submit" name="save_notice" class="nb-btn nb-btn-primary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    <?php echo isset($edit_notice) ? 'Update Notice' : 'Save Notice'; ?>
                </button>
                <?php if (isset($edit_notice)): ?>
                    <a href="<?php echo $_SERVER['REQUEST_URI']; ?>" class="nb-btn nb-btn-ghost">Cancel</a>
                    <?php if ($isPro): ?>
                    <!-- Save as Template (Pro) -->
                    <button type="button" class="nb-btn nb-btn-ghost" onclick="nbOpenSaveTemplate(<?php echo (int)$edit_notice['id']; ?>)">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        Save as Template
                    </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </form>

        <!-- Save as Template mini-form (hidden) -->
        <div id="nb-save-tpl-form" style="display:none;margin-top:12px;padding:12px 14px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;">
            <form method="post" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <input type="hidden" name="save_as_template" id="nb-tpl-src-id" value="">
                <div class="nb-field" style="flex:1;min-width:200px;">
                    <label style="font-size:12px;">Template Name</label>
                    <input type="text" name="template_name_input" placeholder="e.g. Maintenance Notice" required>
                </div>
                <button type="submit" class="nb-btn nb-btn-primary nb-btn-sm" style="margin-top:18px;">Save Template</button>
                <button type="button" class="nb-btn nb-btn-ghost nb-btn-sm" style="margin-top:18px;" onclick="document.getElementById('nb-save-tpl-form').style.display='none'">Cancel</button>
            </form>
        </div>
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
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <!-- Tag filter bar -->
            <?php if (!empty($allTags)): ?>
            <div id="nb-tag-filter-bar" style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;">
                <span style="font-size:12px;color:#94a3b8;font-weight:600;">Filter:</span>
                <span class="nb-tag active" data-filter-tag="" onclick="nbFilterTag(this,'')">All</span>
                <?php foreach ($allTags as $ft): ?>
                    <span class="nb-tag" data-filter-tag="<?php echo htmlspecialchars($ft); ?>" onclick="nbFilterTag(this,'<?php echo htmlspecialchars($ft); ?>')">#<?php echo htmlspecialchars($ft); ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <span style="font-size:13px;color:#94a3b8;"><?php echo count($notices); ?> notice<?php echo count($notices) == 1 ? '' : 's'; ?></span>
        </div>
    </div>

    <?php if (empty($notices)): ?>
        <div class="nb-card-body" style="text-align:center;color:#94a3b8;padding:40px;">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:10px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            <div>No notices yet. Create one above.</div>
        </div>
    <?php else: ?>
    <table class="nb-table" id="nb-notices-table">
        <thead>
            <tr>
                <th style="width:38%;">Notice</th>
                <th>Priority</th>
                <th>Audience</th>
                <th>Status</th>
                <th>Reads</th>
                <th>Timestamp</th>
                <th style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($notices as $n):
            $priority  = $n['priority'] ?? 'normal';
            $pc        = $priorityConfig[$priority] ?? $priorityConfig['normal'];
            $accent    = $accentMap[$priority] ?? '#2563eb';
            $isActive  = !empty($n['show_to_admins']) || !empty($n['show_to_clients']);
            $isExpired = !empty($n['expires_at']) && $n['expires_at'] < $now;
            $isSched   = !empty($n['publish_at']) && $n['publish_at'] > $now;
            $readCounts = noticebanner_get_read_counts((int)$n['id']);
            $rowTags   = array_filter(array_map('trim', explode(',', $n['tags'] ?? '')));
        ?>
        <tr class="nb-row-accent" style="border-left-color:<?php echo $accent; ?>;" data-tags="<?php echo htmlspecialchars(implode(',', $rowTags)); ?>">
            <td>
                <!-- Title row -->
                <div style="margin-bottom:4px;display:flex;align-items:center;flex-wrap:wrap;gap:4px;">
                    <?php if (!empty($n['is_pinned'])): ?>
                        <span style="font-size:13px;">📌</span>
                    <?php endif; ?>
                    <span style="font-weight:700;font-size:15px;"><?php echo htmlspecialchars($n['notice_title']); ?></span>
                    <?php if ($isExpired): ?>
                        <span class="nb-badge nb-badge-expired">⏰ Expired</span>
                    <?php endif; ?>
                    <?php if ($isSched): ?>
                        <span class="nb-badge nb-badge-scheduled">🕐 Scheduled</span>
                    <?php endif; ?>
                </div>

                <!-- Content preview -->
                <div style="color:#475569;font-size:13px;line-height:1.5;margin-bottom:6px;">
                    <?php echo htmlspecialchars(mb_strimwidth(strip_tags($n['notice_content'] ?? ''), 0, 160, '…')); ?>
                </div>

                <!-- Tags -->
                <?php if (!empty($rowTags)): ?>
                    <div style="margin-bottom:6px;display:flex;flex-wrap:wrap;gap:3px;">
                        <?php foreach ($rowTags as $rt): ?>
                            <span class="nb-tag" onclick="nbFilterTag(document.querySelector('[data-filter-tag=\'<?php echo htmlspecialchars($rt); ?>\']'),'<?php echo htmlspecialchars($rt); ?>')">#<?php echo htmlspecialchars($rt); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Assigned admin chips -->
                <?php if (!empty($n['assigned_admins'])): ?>
                    <div style="margin-bottom:6px;display:flex;align-items:center;flex-wrap:wrap;gap:3px;">
                        <span style="font-size:11px;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-right:2px;">Assigned:</span>
                        <?php foreach ($n['assigned_admins'] as $aid): ?>
                            <span class="nb-chip">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <?php echo htmlspecialchars($adminMap[$aid] ?? 'Admin #' . $aid); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="margin-bottom:6px;">
                        <span style="font-size:11px;color:#cbd5e1;font-style:italic;">Visible to all admins</span>
                    </div>
                <?php endif; ?>

                <!-- Client group chips -->
                <?php if (!empty($n['client_groups'])): ?>
                    <div style="margin-bottom:6px;display:flex;align-items:center;flex-wrap:wrap;gap:3px;">
                        <span style="font-size:11px;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-right:2px;">Groups:</span>
                        <?php foreach ($n['client_groups'] as $gid): ?>
                            <span style="display:inline-flex;align-items:center;background:#e0f2fe;color:#0369a1;border-radius:999px;padding:1px 8px;font-size:11px;font-weight:600;margin:1px;">
                                <?php echo htmlspecialchars($groupMap[$gid] ?? 'Group #' . $gid); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Granular targeting summary -->
                <?php
                $hasGranular = !empty($n['target_clients']) || !empty($n['target_servers']) || !empty($n['target_products']);
                if ($hasGranular): ?>
                    <div style="margin-bottom:6px;display:flex;align-items:flex-start;flex-wrap:wrap;gap:4px;">
                        <span style="font-size:11px;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-right:2px;margin-top:2px;">🎯 Target:</span>
                        <?php if (!empty($n['target_clients'])): ?>
                            <span style="display:inline-flex;align-items:center;background:#fef9c3;color:#854d0e;border-radius:999px;padding:1px 8px;font-size:11px;font-weight:600;margin:1px;">
                                👤 <?php echo count($n['target_clients']); ?> client<?php echo count($n['target_clients']) == 1 ? '' : 's'; ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($n['target_servers'])): ?>
                            <span style="display:inline-flex;align-items:center;background:#f0fdf4;color:#166534;border-radius:999px;padding:1px 8px;font-size:11px;font-weight:600;margin:1px;">
                                🖥 <?php echo count($n['target_servers']); ?> server<?php echo count($n['target_servers']) == 1 ? '' : 's'; ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($n['target_products'])): ?>
                            <span style="display:inline-flex;align-items:center;background:#ede9fe;color:#5b21b6;border-radius:999px;padding:1px 8px;font-size:11px;font-weight:600;margin:1px;">
                                📦 <?php echo count($n['target_products']); ?> product<?php echo count($n['target_products']) == 1 ? '' : 's'; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Poll results -->
                <?php if (!empty($n['poll_enabled']) && !empty($n['poll_question']) && !empty($n['poll_options'])): ?>
                    <?php
                    $results    = $n['poll_results'] ?? [];
                    $totalVotes = array_sum($results);
                    $pollPanelId = 'nb-predefined-vote-' . $n['id'];
                    ?>
                    <div style="margin-top:8px;">
                        <div style="font-size:12px;font-weight:700;color:#64748b;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.04em;display:flex;align-items:center;gap:6px;">
                            Poll: <?php echo htmlspecialchars($n['poll_question']); ?>
                            <button type="button" onclick="nbToggleRow('<?php echo $pollPanelId; ?>',null)"
                                style="font-size:10px;padding:1px 7px;border-radius:4px;border:1px solid #c7d2fe;background:#e0e7ff;color:#3730a3;cursor:pointer;font-weight:600;">⚙ Manage</button>
                        </div>
                        <?php foreach ($n['poll_options'] as $opt):
                            $votes = $results[$opt] ?? 0;
                            $pct   = $totalVotes > 0 ? round(($votes / $totalVotes) * 100) : 0;
                        ?>
                        <div class="nb-poll-bar-wrap">
                            <span style="width:130px;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($opt, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($opt, ENT_NOQUOTES, 'UTF-8'); ?></span>
                            <div class="nb-poll-bar-track"><div class="nb-poll-bar-fill" style="width:<?php echo $pct; ?>%;"></div></div>
                            <span style="width:60px;font-size:12px;color:#64748b;"><?php echo $votes; ?> (<?php echo $pct; ?>%)</span>
                        </div>
                        <?php endforeach; ?>
                        <div style="font-size:11px;color:#94a3b8;margin-top:2px;"><?php echo $totalVotes; ?> total vote<?php echo $totalVotes == 1 ? '' : 's'; ?></div>

                        <!-- Poll management panel -->
                        <div id="<?php echo $pollPanelId; ?>" style="display:none;margin-top:10px;padding:14px;background:#fefce8;border:1px solid #fde68a;border-radius:8px;min-width:280px;max-width:420px;">
                            <div style="font-size:12px;font-weight:700;color:#92400e;margin-bottom:10px;display:flex;align-items:center;gap:5px;flex-wrap:wrap;">
                                ⚙ Poll Management
                                <div style="margin-left:auto;display:flex;gap:4px;flex-wrap:wrap;">
                                    <a href="addonmodules.php?module=noticebanner&nb_export_votes=<?php echo (int)$n['id']; ?>&format=csv"
                                        style="padding:2px 9px;border-radius:4px;background:#16a34a;color:#fff;font-weight:700;font-size:11px;text-decoration:none;white-space:nowrap;">⬇ CSV</a>
                                    <a href="addonmodules.php?module=noticebanner&nb_export_votes=<?php echo (int)$n['id']; ?>&format=json"
                                        style="padding:2px 9px;border-radius:4px;background:#0369a1;color:#fff;font-weight:700;font-size:11px;text-decoration:none;white-space:nowrap;">⬇ JSON</a>
                                    <form method="post" style="margin:0;" onsubmit="return confirm('Reset ALL poll votes to zero? This cannot be undone.');">
                                        <input type="hidden" name="reset_poll_id" value="<?php echo (int)$n['id']; ?>">
                                        <button type="submit" name="reset_poll" value="1"
                                            style="padding:2px 9px;border-radius:4px;background:#ef4444;color:#fff;font-weight:700;border:none;cursor:pointer;font-size:11px;">🗑 Reset</button>
                                    </form>
                                </div>
                            </div>

                            <!-- Voter list -->
                            <?php
                            $pollVoters = noticebanner_get_poll_voters((int)$n['id']);
                            $typeColors = ['admin' => ['bg'=>'#ede9fe','color'=>'#5b21b6','icon'=>'👤'], 'client' => ['bg'=>'#dbeafe','color'=>'#1e40af','icon'=>'🌐'], 'predefined' => ['bg'=>'#fef9c3','color'=>'#92400e','icon'=>'⚙']];
                            ?>
                            <?php if (!empty($pollVoters)): ?>
                            <div style="margin-bottom:10px;">
                                <div style="font-size:11px;font-weight:600;color:#78350f;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.04em;">Who Voted</div>
                                <div style="max-height:200px;overflow-y:auto;display:flex;flex-direction:column;gap:4px;">
                                    <?php foreach ($pollVoters as $v):
                                        $tc = $typeColors[$v['entity_type']] ?? $typeColors['predefined'];
                                    ?>
                                    <div style="display:flex;align-items:center;gap:6px;padding:4px 6px;background:#fff;border:1px solid #fde68a;border-radius:5px;font-size:11px;">
                                        <span style="padding:1px 6px;border-radius:10px;background:<?php echo $tc['bg']; ?>;color:<?php echo $tc['color']; ?>;font-weight:700;white-space:nowrap;flex-shrink:0;"><?php echo $tc['icon']; ?> <?php echo ucfirst($v['entity_type']); ?></span>
                                        <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($v['label'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($v['label'], ENT_NOQUOTES, 'UTF-8'); ?></span>
                                        <span style="color:#6366f1;font-weight:600;white-space:nowrap;flex-shrink:0;max-width:100px;overflow:hidden;text-overflow:ellipsis;" title="<?php echo htmlspecialchars($v['poll_option'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($v['poll_option'], ENT_NOQUOTES, 'UTF-8'); ?></span>
                                        <?php if (!empty($v['vote_count']) && $v['vote_count'] > 1): ?>
                                        <span style="background:#fef3c7;color:#92400e;border-radius:10px;padding:0 5px;font-weight:700;font-size:10px;flex-shrink:0;">×<?php echo (int)$v['vote_count']; ?></span>
                                        <?php endif; ?>
                                        <span style="color:#94a3b8;white-space:nowrap;flex-shrink:0;"><?php echo date('d M H:i', strtotime($v['voted_at'])); ?></span>
                                        <form method="post" style="margin:0;flex-shrink:0;" onsubmit="return confirm('Remove this vote record?');">
                                            <input type="hidden" name="delete_poll_vote_id" value="<?php echo (int)$v['id']; ?>">
                                            <input type="hidden" name="delete_poll_notice_id" value="<?php echo (int)$n['id']; ?>">
                                            <button type="submit" name="delete_poll_vote" value="1"
                                                style="padding:1px 6px;border-radius:4px;background:#fee2e2;color:#dc2626;font-weight:700;border:1px solid #fca5a5;cursor:pointer;font-size:11px;">✕</button>
                                        </form>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php else: ?>
                            <div style="font-size:11px;color:#a16207;margin-bottom:10px;padding:6px;background:#fff;border-radius:5px;border:1px solid #fde68a;">No votes recorded yet.</div>
                            <?php endif; ?>

                            <!-- Add predefined votes — parallel arrays (hex option + count per row) so POST keys never use +/= from base64 -->
                            <form method="post">
                                <input type="hidden" name="predefined_poll_notice_id" value="<?php echo (int)$n['id']; ?>">
                                <input type="hidden" name="predefined_poll_vote" value="1">
                                <div style="font-size:11px;font-weight:600;color:#78350f;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.04em;">Add Predefined Votes</div>
                                <div style="margin-bottom:8px;">
                                    <input type="text" name="predefined_poll_label" placeholder='Label, e.g. "Early Adopters"'
                                        style="width:100%;box-sizing:border-box;font-size:12px;padding:5px 8px;border:1px solid #fcd34d;border-radius:5px;">
                                </div>
                                <table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:10px;">
                                    <thead>
                                        <tr style="background:#fef3c7;">
                                            <th style="text-align:left;padding:4px 6px;color:#78350f;font-weight:700;font-size:10px;text-transform:uppercase;border-bottom:1px solid #fde68a;">Option</th>
                                            <th style="text-align:center;padding:4px 6px;color:#78350f;font-weight:700;font-size:10px;text-transform:uppercase;width:70px;border-bottom:1px solid #fde68a;">Add Votes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($n['poll_options'] as $opt):
                                            $optHex = bin2hex($opt);
                                        ?>
                                        <tr style="border-bottom:1px solid #fde68a;">
                                            <td style="padding:5px 6px;font-size:12px;"><?php echo htmlspecialchars($opt, ENT_NOQUOTES, 'UTF-8'); ?></td>
                                            <td style="padding:5px 6px;text-align:center;">
                                                <input type="hidden" name="predefined_poll_option_hex[]" value="<?php echo htmlspecialchars($optHex, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="number" name="predefined_poll_add_counts[]" value="0" min="0" max="9999"
                                                    style="width:60px;font-size:12px;padding:3px 5px;border:1px solid #fcd34d;border-radius:4px;text-align:center;">
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <button type="submit"
                                    style="padding:5px 14px;border-radius:5px;background:#f59e0b;color:#fff;font-weight:700;border:none;cursor:pointer;font-size:12px;">+ Apply Predefined Votes</button>
                            </form>
                        </div>
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
                    <button type="submit" class="nb-btn nb-btn-sm nb-badge <?php echo $isActive ? 'nb-badge-active' : 'nb-badge-off'; ?>"
                        style="border:none;cursor:pointer;">
                        <?php echo $isActive ? '● Active' : '○ Off'; ?>
                    </button>
                </form>
                <?php if ($isExpired): ?><div class="nb-badge nb-badge-expired" style="margin-top:3px;">Expired</div><?php endif; ?>
                <?php if ($isSched): ?><div class="nb-badge nb-badge-scheduled" style="margin-top:3px;">Scheduled</div><?php endif; ?>
            </td>

            <td style="font-size:12px;min-width:130px;">
                <?php
                $totalAcks = $readCounts['admins'] + $readCounts['clients'];
                $ackPanelId = 'nb-acks-' . $n['id'];
                ?>
                <!-- Summary badges (click to expand) -->
                <div style="display:flex;flex-wrap:wrap;gap:3px;cursor:pointer;" onclick="nbToggleRow('<?php echo $ackPanelId; ?>',null)">
                    <?php if ($readCounts['admins'] > 0): ?>
                        <span class="nb-read-count" title="Click to manage">👤 <?php echo $readCounts['admins']; ?> admin<?php echo $readCounts['admins'] == 1 ? '' : 's'; ?></span>
                    <?php endif; ?>
                    <?php if ($readCounts['clients'] > 0): ?>
                        <span class="nb-read-count" title="Click to manage">🌐 <?php echo $readCounts['clients']; ?> client<?php echo $readCounts['clients'] == 1 ? '' : 's'; ?></span>
                    <?php endif; ?>
                    <?php if ($totalAcks === 0): ?>
                        <span style="color:#cbd5e1;font-style:italic;font-size:11px;">None yet</span>
                    <?php endif; ?>
                    <span style="font-size:10px;color:#94a3b8;margin-top:1px;" title="Manage acknowledgements">⚙</span>
                </div>

                <!-- Acknowledgement management panel -->
                <div id="<?php echo $ackPanelId; ?>" style="display:none;margin-top:8px;padding:10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;min-width:240px;max-width:340px;">
                    <?php
                    $ackDetails = noticebanner_get_read_details((int)$n['id']);
                    $ackAdmins  = array_filter($ackDetails, fn($r) => $r['entity_type'] === 'admin');
                    $ackClients = array_filter($ackDetails, fn($r) => $r['entity_type'] === 'client');
                    ?>

                    <?php if (!empty($ackAdmins)): ?>
                        <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">👤 Admins</div>
                        <?php foreach ($ackAdmins as $ar): ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:6px;padding:3px 0;border-bottom:1px solid #f1f5f9;">
                            <div>
                                <div style="font-size:12px;font-weight:600;color:#1e293b;"><?php echo htmlspecialchars($ar['name']); ?></div>
                                <div style="font-size:10px;color:#94a3b8;"><?php echo date('M j g:ia', strtotime($ar['read_at'])); ?></div>
                            </div>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Remove this acknowledgement?');">
                                <input type="hidden" name="remove_ack" value="1">
                                <input type="hidden" name="remove_ack_id" value="<?php echo (int)$n['id']; ?>">
                                <input type="hidden" name="remove_ack_type" value="admin">
                                <input type="hidden" name="remove_ack_entity" value="<?php echo (int)$ar['entity_id']; ?>">
                                <button type="submit" class="nb-btn nb-btn-ghost nb-btn-sm" style="color:#ef4444;padding:2px 7px;font-size:11px;" title="Remove">✕</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($ackClients)): ?>
                        <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;margin:8px 0 4px;">🌐 Clients</div>
                        <?php foreach ($ackClients as $cr): ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:6px;padding:3px 0;border-bottom:1px solid #f1f5f9;">
                            <div>
                                <div style="font-size:12px;font-weight:600;color:#1e293b;"><?php echo htmlspecialchars($cr['name']); ?></div>
                                <div style="font-size:10px;color:#94a3b8;"><?php echo date('M j g:ia', strtotime($cr['read_at'])); ?></div>
                            </div>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Remove this acknowledgement?');">
                                <input type="hidden" name="remove_ack" value="1">
                                <input type="hidden" name="remove_ack_id" value="<?php echo (int)$n['id']; ?>">
                                <input type="hidden" name="remove_ack_type" value="client">
                                <input type="hidden" name="remove_ack_entity" value="<?php echo (int)$cr['entity_id']; ?>">
                                <button type="submit" class="nb-btn nb-btn-ghost nb-btn-sm" style="color:#ef4444;padding:2px 7px;font-size:11px;" title="Remove">✕</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (empty($ackDetails)): ?>
                        <div style="font-size:12px;color:#94a3b8;text-align:center;padding:6px 0;">No acknowledgements yet.</div>
                    <?php endif; ?>

                    <!-- Add predefined acknowledgement -->
                    <div style="margin-top:10px;padding-top:8px;border-top:1px solid #e2e8f0;">
                        <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;">+ Add Acknowledgement</div>
                        <form method="post">
                            <input type="hidden" name="add_predefined_ack" value="1">
                            <input type="hidden" name="predefined_ack_notice_id" value="<?php echo (int)$n['id']; ?>">
                            <div style="display:flex;gap:5px;flex-wrap:wrap;margin-bottom:6px;">
                                <label style="display:flex;align-items:center;gap:4px;font-size:12px;cursor:pointer;">
                                    <input type="radio" name="predefined_ack_type" value="admin" checked onchange="nbToggleAckList(<?php echo (int)$n['id']; ?>,this.value)"> 👤 Admin
                                </label>
                                <label style="display:flex;align-items:center;gap:4px;font-size:12px;cursor:pointer;">
                                    <input type="radio" name="predefined_ack_type" value="client" onchange="nbToggleAckList(<?php echo (int)$n['id']; ?>,this.value)"> 🌐 Client
                                </label>
                            </div>
                            <!-- Admin list -->
                            <div id="nb-predefined-admin-<?php echo (int)$n['id']; ?>">
                                <select name="predefined_ack_entities[]" multiple style="width:100%;height:80px;border:1px solid #cbd5e1;border-radius:6px;padding:3px;font-size:12px;">
                                    <?php foreach ($admins as $a): ?>
                                        <option value="<?php echo (int)$a->id; ?>"><?php echo htmlspecialchars($a->firstname . ' ' . $a->lastname . ' (@' . $a->username . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- Client search (lazy) -->
                            <div id="nb-predefined-client-<?php echo (int)$n['id']; ?>" style="display:none;">
                                <div style="font-size:11px;color:#94a3b8;margin-bottom:4px;">Search and select clients below:</div>
                                <input type="text" placeholder="Search clients…" style="width:100%;padding:5px 8px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;margin-bottom:4px;"
                                    oninput="nbPredefinedClientSearch(this,<?php echo (int)$n['id']; ?>)">
                                <select id="nb-predefined-client-sel-<?php echo (int)$n['id']; ?>" name="predefined_ack_entities[]" multiple style="width:100%;height:80px;border:1px solid #cbd5e1;border-radius:6px;padding:3px;font-size:12px;display:none;"></select>
                            </div>
                            <button type="submit" class="nb-btn nb-btn-success nb-btn-sm" style="margin-top:6px;width:100%;">Add</button>
                        </form>
                    </div>
                </div>
            </td>

            <td style="font-size:12px;white-space:nowrap;">
                <?php if (!empty($n['notice_timestamp'])): ?>
                    <div style="color:#1e293b;font-weight:600;"><?php echo date('M j, Y', strtotime($n['notice_timestamp'])); ?></div>
                    <div style="color:#94a3b8;"><?php echo date('g:ia', strtotime($n['notice_timestamp'])); ?></div>
                <?php else: ?>
                    <div style="color:#94a3b8;font-style:italic;">Not set</div>
                    <div style="color:#cbd5e1;font-size:11px;">Created <?php echo isset($n['created_at']) ? date('M j', strtotime($n['created_at'])) : '—'; ?></div>
                <?php endif; ?>
                <?php if (!empty($n['publish_at'])): ?>
                    <div style="color:#854d0e;font-size:11px;margin-top:2px;">Pub: <?php echo date('M j g:ia', strtotime($n['publish_at'])); ?></div>
                <?php endif; ?>
                <?php if (!empty($n['expires_at'])): ?>
                    <div style="color:#991b1b;font-size:11px;margin-top:2px;">Exp: <?php echo date('M j g:ia', strtotime($n['expires_at'])); ?></div>
                <?php endif; ?>
            </td>

            <td style="text-align:right;white-space:nowrap;">
                <div style="display:flex;gap:5px;justify-content:flex-end;flex-wrap:wrap;">
                    <!-- Quick To-Do -->
                    <a href="addonmodules.php?module=noticebanner&todo_notice_id=<?php echo (int)$n['id']; ?>#nb-todo-banners" class="nb-btn nb-btn-ghost nb-btn-sm" title="Open To-Do for this notice">✅ To-Do</a>
                    <!-- Edit -->
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="edit_load" value="<?php echo (int)$n['id']; ?>">
                        <button type="submit" class="nb-btn nb-btn-ghost nb-btn-icon" title="Edit">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                    </form>
                    <?php if ($isPro): ?>
                    <!-- Clone (Pro) -->
                    <form method="post" style="display:inline;" onsubmit="return confirm('Clone this notice?');">
                        <input type="hidden" name="clone_notice" value="<?php echo (int)$n['id']; ?>">
                        <button type="submit" class="nb-btn nb-btn-ghost nb-btn-icon" title="Clone">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        </button>
                    </form>
                    <?php endif; ?>
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

<!-- ══════════════════════════════════════════════════════════════════════════
     ACTIVITY LOG
══════════════════════════════════════════════════════════════════════════ -->
<?php
try {
    $logEntries = \WHMCS\Database\Capsule::table('mod_noticebanner_log as l')
        ->leftJoin('tbladmins as a', 'l.admin_id', '=', 'a.id')
        ->leftJoin('mod_noticebanner as n', 'l.notice_id', '=', 'n.id')
        ->orderBy('l.id', 'desc')
        ->limit(50)
        ->get(['l.id', 'l.notice_id', 'l.action', 'l.detail', 'l.created_at',
               'a.firstname', 'a.lastname', 'n.notice_title'])
        ->toArray();
} catch (\Exception $e) {
    $logEntries = [];
}
?>
<div class="nb-card">
    <div class="nb-card-header" style="cursor:pointer;" onclick="nbToggleSection('nb-log-body','nb-log-toggle')">
        <h2>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            Activity Log
        </h2>
        <span id="nb-log-toggle" class="nb-section-toggle" style="font-size:12px;color:#94a3b8;">▶ Show</span>
    </div>
    <div id="nb-log-body" class="nb-collapsible">
        <?php if (empty($logEntries)): ?>
            <div class="nb-card-body" style="text-align:center;color:#94a3b8;padding:20px;">No activity recorded yet.</div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="nb-log-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Admin</th>
                    <th>Action</th>
                    <th>Notice</th>
                    <th>Detail</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logEntries as $le): ?>
            <tr>
                <td style="white-space:nowrap;color:#94a3b8;"><?php echo date('M j g:ia', strtotime($le->created_at)); ?></td>
                <td style="white-space:nowrap;"><?php echo htmlspecialchars(($le->firstname ?? '') . ' ' . ($le->lastname ?? '') ?: '—'); ?></td>
                <td>
                    <?php
                    $actionColors = [
                        'created'           => '#166534',
                        'updated'           => '#1d4ed8',
                        'deleted'           => '#991b1b',
                        'cloned'            => '#7c3aed',
                        'enabled'           => '#166534',
                        'disabled'          => '#64748b',
                        'poll_vote'         => '#0369a1',
                        'acknowledged'      => '#0f766e',
                        'saved_as_template' => '#7c3aed',
                    ];
                    $ac = $actionColors[$le->action] ?? '#475569';
                    ?>
                    <span style="color:<?php echo $ac; ?>;font-weight:600;font-size:12px;"><?php echo htmlspecialchars($le->action); ?></span>
                </td>
                <td style="color:#475569;"><?php echo htmlspecialchars($le->notice_title ?? ($le->notice_id ? '#' . $le->notice_id : '—')); ?></td>
                <td style="color:#94a3b8;font-size:12px;"><?php echo htmlspecialchars($le->detail ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

</div><!-- /nb-pane-notices -->

<!-- ══════════════════════════════════════════════════════════════════════════
     TAB PANE: PROMOTIONS (sale banners — coupons, links, templates, audience)
══════════════════════════════════════════════════════════════════════════ -->
<div id="nb-pane-promotions" class="nb-main-tab-pane">
<div class="nb-card">
    <div class="nb-card-header">
        <h2>🎁 Promotion banners</h2>
        <span style="font-size:13px;color:#64748b;">Deploy flashy sale banners with coupon codes and CTAs</span>
    </div>
    <div class="nb-card-body">
        <?php
        $pe = $edit_promo ?? null;
        $promoTplCur = $pe['promo_template'] ?? 'gradient';
        $hasPromoTargeting = $isPro && $pe && (
            !empty($pe['client_groups']) || !empty($pe['target_clients']) || !empty($pe['target_servers'])
            || !empty($pe['target_products']) || !empty($pe['page_slugs'])
        );
        ?>
        <form method="post" id="nb-promo-form" class="nb-grid" style="gap:14px;">
            <?php if (!empty($pe)): ?>
                <input type="hidden" name="edit_id" value="<?php echo (int)$pe['id']; ?>">
            <?php endif; ?>
            <div class="nb-field nb-span2">
                <label>Headline <span style="color:#ef4444;">*</span></label>
                <input type="text" name="notice_title" required placeholder="e.g. Spring sale — 40% off hosting"
                    value="<?php echo htmlspecialchars($pe['notice_title'] ?? ''); ?>">
            </div>
            <div class="nb-field nb-span2">
                <label>Message <span style="color:#64748b;font-weight:500;">(Markdown)</span></label>
                <textarea name="notice_content" rows="4" placeholder="Short pitch, expiry, terms…"><?php echo htmlspecialchars($pe['notice_content'] ?? ''); ?></textarea>
            </div>
            <div class="nb-field">
                <label>Visual template</label>
                <select name="promo_template">
                    <?php
                    $pt = $promoTplCur;
                    $promoTplOpts = [
                        'gradient' => 'Gradient hero (vibrant)',
                        'flash'    => 'Flash shimmer (animated)',
                        'neon'     => 'Neon night',
                        'ribbon'   => 'Gold ribbon',
                        'minimal'  => 'Minimal pro',
                    ];
                    foreach ($promoTplOpts as $pv => $pl): ?>
                        <option value="<?php echo htmlspecialchars($pv); ?>" <?php echo ($pt === $pv) ? 'selected' : ''; ?>><?php echo htmlspecialchars($pl); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="nb-field">
                <label>Coupon code <span style="color:#64748b;font-weight:500;">(optional)</span></label>
                <input type="text" name="promo_coupon_code" maxlength="120" placeholder="SAVE40"
                    value="<?php echo htmlspecialchars($pe['promo_coupon_code'] ?? ''); ?>">
            </div>
            <div class="nb-field">
                <label>Accent / gradient start</label>
                <div class="nb-color-row">
                    <input type="color" id="nb-promo-bg-picker" value="<?php echo htmlspecialchars($pe['bg_color'] ?? '#6366f1'); ?>">
                    <input type="text" id="nb-promo-bg-hex" name="bg_color" value="<?php echo htmlspecialchars($pe['bg_color'] ?? '#6366f1'); ?>">
                </div>
            </div>
            <div class="nb-field">
                <label>Text color <?php if ($promoTplCur === 'minimal'): ?><span style="font-size:11px;color:#94a3b8;">(minimal)</span><?php endif; ?></label>
                <div class="nb-color-row">
                    <input type="color" id="nb-promo-fg-picker" value="<?php echo htmlspecialchars($pe['font_color'] ?? '#ffffff'); ?>">
                    <input type="text" id="nb-promo-fg-hex" name="font_color" value="<?php echo htmlspecialchars($pe['font_color'] ?? '#ffffff'); ?>">
                </div>
            </div>
            <?php if ($isPro): ?>
            <div class="nb-field nb-span2" style="padding:12px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;">
                <label class="nb-switch" style="margin-bottom:8px;">
                    <input type="checkbox" name="button_enabled" value="1" <?php echo !empty($pe['button_enabled']) ? 'checked' : ''; ?> id="nb-promo-cta-en" onchange="document.getElementById('nb-promo-cta-opts').style.display=this.checked?'block':'none';">
                    <span class="nb-switch-track"></span>
                    <span>Call-to-action link (opens in new tab recommended)</span>
                </label>
                <div id="nb-promo-cta-opts" style="display:<?php echo !empty($pe['button_enabled']) ? 'block' : 'none'; ?>;">
                    <div class="nb-grid" style="margin-top:10px;">
                        <div class="nb-field">
                            <label>Button label</label>
                            <input type="text" name="button_text" value="<?php echo htmlspecialchars($pe['button_text'] ?? 'Shop the sale'); ?>">
                        </div>
                        <div class="nb-field">
                            <label>URL</label>
                            <input type="url" name="button_link" placeholder="https://…" value="<?php echo htmlspecialchars($pe['button_link'] ?? ''); ?>">
                        </div>
                        <div class="nb-field">
                            <label class="nb-switch">
                                <input type="checkbox" name="button_newtab" value="1" <?php echo !empty($pe['button_newtab']) ? 'checked' : ''; ?>>
                                <span class="nb-switch-track"></span>
                                <span>New tab</span>
                            </label>
                        </div>
                        <div class="nb-field">
                            <label>Button colors</label>
                            <div class="nb-color-row">
                                <input type="color" id="nb-promo-bt-bg-picker" value="<?php echo htmlspecialchars($pe['button_bg'] ?? '#ffffff'); ?>">
                                <input type="text" id="nb-promo-bt-bg-hex" name="button_bg" value="<?php echo htmlspecialchars($pe['button_bg'] ?? '#ffffff'); ?>">
                            </div>
                        </div>
                        <div class="nb-field">
                            <label>&nbsp;</label>
                            <div class="nb-color-row">
                                <input type="color" id="nb-promo-bt-fg-picker" value="<?php echo htmlspecialchars($pe['button_color'] ?? '#0f172a'); ?>">
                                <input type="text" id="nb-promo-bt-fg-hex" name="button_color" value="<?php echo htmlspecialchars($pe['button_color'] ?? '#0f172a'); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="nb-field nb-span2 nb-pro-section">
                <p style="margin:0;font-size:13px;color:#64748b;">🔒 Pro unlocks CTA buttons, scheduling, and audience targeting for promotions.</p>
            </div>
            <?php endif; ?>

            <div class="nb-field nb-span2 nb-switch-row">
                <label class="nb-switch">
                    <input type="checkbox" name="show_to_clients" value="1" <?php echo !isset($pe) || !empty($pe['show_to_clients']) ? 'checked' : ''; ?>>
                    <span class="nb-switch-track"></span>
                    <span>Show to clients</span>
                </label>
                <label class="nb-switch">
                    <input type="checkbox" name="show_to_admins" value="1" <?php echo !empty($pe['show_to_admins']) ? 'checked' : ''; ?>>
                    <span class="nb-switch-track"></span>
                    <span>Show to admins</span>
                </label>
            </div>
            <div class="nb-field nb-span2" style="padding:12px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;">
                <div style="font-size:12px;font-weight:700;color:#475569;margin-bottom:8px;">Client area layout</div>
                <label class="nb-switch" style="margin-bottom:10px;">
                    <input type="checkbox" name="promo_collapsible" value="1" <?php echo !empty($pe['promo_collapsible']) ? 'checked' : ''; ?>>
                    <span class="nb-switch-track"></span>
                    <span>Collapsible on client (compact title bar + Expand — saves vertical space)</span>
                </label>
                <label class="nb-switch">
                    <input type="checkbox" name="promo_start_expanded" value="1" <?php echo !empty($pe['promo_start_expanded']) ? 'checked' : ''; ?>>
                    <span class="nb-switch-track"></span>
                    <span>Start expanded</span>
                    <span style="font-size:12px;color:#64748b;font-weight:500;margin-left:6px;">(if unchecked, the full promo stays collapsed until the client clicks Expand)</span>
                </label>
            </div>
            <?php if ($isPro): ?>
            <div class="nb-field">
                <label>Publish from</label>
                <input type="datetime-local" name="publish_at" value="<?php echo !empty($pe['publish_at']) ? date('Y-m-d\TH:i', strtotime($pe['publish_at'])) : ''; ?>">
            </div>
            <div class="nb-field">
                <label>Expires</label>
                <input type="datetime-local" name="expires_at" value="<?php echo !empty($pe['expires_at']) ? date('Y-m-d\TH:i', strtotime($pe['expires_at'])) : ''; ?>">
            </div>
            <?php endif; ?>

            <?php if ($isPro): ?>
            <div class="nb-field nb-span2">
                <label>Tags</label>
                <input type="text" name="tags" placeholder="sale, blackfriday" value="<?php echo htmlspecialchars($pe['tags'] ?? ''); ?>">
            </div>
            <div class="nb-field nb-span2">
                <label>Assign to admins <span style="color:#64748b;font-weight:500;">(optional — limit who sees it in admin)</span></label>
                <select name="assigned_admins[]" multiple class="nb-todo-assign-multi" style="min-height:88px;">
                    <?php foreach ($admins as $a): ?>
                        <option value="<?php echo (int)$a->id; ?>" <?php echo (!empty($pe) && in_array((int)$a->id, $pe['assigned_admins'] ?? [], true)) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($a->firstname . ' ' . $a->lastname); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="nb-field nb-span2">
                <label>Client groups</label>
                <select name="client_groups[]" multiple class="nb-todo-assign-multi" style="min-height:88px;">
                    <?php foreach ($clientGroups as $g): ?>
                        <option value="<?php echo (int)$g->id; ?>" <?php echo (!empty($pe) && in_array((int)$g->id, $pe['client_groups'] ?? [], true)) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($g->groupname); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="nb-field nb-span2">
                <div class="nb-section-toggle <?php echo $hasPromoTargeting ? 'open' : ''; ?>" id="nb-promo-target-toggle" onclick="nbToggleSection('nb-promo-target-body','nb-promo-target-toggle')">
                    <?php echo $hasPromoTargeting ? '▼' : '▶'; ?> Target audience (advanced)
                </div>
                <div id="nb-promo-target-body" class="nb-collapsible <?php echo $hasPromoTargeting ? 'open' : ''; ?>" style="padding:12px 0;">
                    <div class="nb-field">
                        <label>Specific clients</label>
                        <div id="nb-promo-client-chips" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;min-height:10px;">
                            <?php
                            if (!empty($pe['target_clients'])) {
                                $pclients = noticebanner_get_clients_by_ids($pe['target_clients']);
                                foreach ($pclients as $c) {
                                    $cid = (int)$c->id;
                                    $lab = htmlspecialchars($c->firstname . ' ' . $c->lastname . ' (' . $c->email . ')');
                                    echo '<span class="nb-chip" id="nb-promo-client-chip-' . $cid . '" data-id="' . $cid . '"><span>' . $lab . '</span>'
                                        . '<button type="button" onclick="nbPromoRemoveClient(' . $cid . ')" style="background:none;border:none;cursor:pointer;color:#7c3aed;font-size:13px;">&times;</button>'
                                        . '<input type="hidden" name="target_clients[]" value="' . $cid . '"></span>';
                                }
                            }
                            ?>
                        </div>
                        <div style="position:relative;">
                            <input type="text" id="nb-promo-client-search" placeholder="Search clients…" autocomplete="off"
                                oninput="nbPromoClientSearch(this.value)" onfocus="nbPromoClientSearch(this.value)" style="width:100%;max-width:420px;">
                            <div id="nb-promo-client-results" style="display:none;position:absolute;z-index:9999;background:#fff;border:1px solid #e2e8f0;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,0.1);min-width:320px;max-height:220px;overflow-y:auto;"></div>
                        </div>
                    </div>
                    <div class="nb-grid">
                        <div class="nb-field">
                            <label>Servers</label>
                            <select name="target_servers[]" multiple style="min-height:88px;width:100%;">
                                <?php foreach ($servers as $s): ?>
                                    <option value="<?php echo (int)$s->id; ?>" <?php echo (!empty($pe) && in_array((int)$s->id, $pe['target_servers'] ?? [], true)) ? 'selected' : ''; ?>><?php echo htmlspecialchars($s->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="nb-field">
                            <label>Products</label>
                            <select name="target_products[]" multiple style="min-height:88px;width:100%;">
                                <?php foreach ($products as $p): ?>
                                    <option value="<?php echo (int)$p->id; ?>" <?php echo (!empty($pe) && in_array((int)$p->id, $pe['target_products'] ?? [], true)) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="nb-field">
                        <label>Page paths <span style="color:#64748b;font-weight:500;">(one per line, client area)</span></label>
                        <textarea name="page_slugs_raw" rows="3" placeholder="/clientarea.php&#10;/cart.php*"><?php
                            if (!empty($pe['page_slugs']) && is_array($pe['page_slugs'])) {
                                echo htmlspecialchars(implode("\n", $pe['page_slugs']));
                            }
                        ?></textarea>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="nb-field nb-span2" style="margin-top:8px;">
                <input type="hidden" name="display_type" value="banner">
                <input type="hidden" name="expandable" value="0">
                <input type="hidden" name="show_again_minutes" value="60">
                <input type="hidden" name="priority" value="normal">
                <input type="hidden" name="notice_webhook_url" value="<?php echo htmlspecialchars($pe['webhook_url'] ?? ''); ?>">
                <button type="submit" name="save_promotion" value="1" class="nb-btn nb-btn-primary"><?php echo !empty($pe) ? 'Update promotion' : 'Deploy promotion'; ?></button>
                <?php if (!empty($pe)): ?>
                    <a href="addonmodules.php?module=noticebanner#nb-promotions" class="nb-btn nb-btn-ghost">Cancel edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="nb-card">
    <div class="nb-card-header">
        <h2>Active promotions</h2>
        <span style="font-size:13px;color:#64748b;"><?php echo count($promoBanners ?? []); ?> configured</span>
    </div>
    <div class="nb-card-body" style="padding:0;">
        <?php if (empty($promoBanners)): ?>
            <p style="padding:22px;margin:0;color:#64748b;">No promotion banners yet. Create one above.</p>
        <?php else: ?>
        <table class="nb-table">
            <thead>
                <tr>
                    <th>Headline</th>
                    <th>Template</th>
                    <th>Audience</th>
                    <th style="width:200px;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($promoBanners as $pb): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($pb['notice_title'] ?? ''); ?></strong>
                        <?php if (!empty($pb['promo_coupon_code'])): ?>
                            <div style="font-size:12px;color:#6366f1;font-weight:600;margin-top:4px;">Code: <?php echo htmlspecialchars($pb['promo_coupon_code']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($pb['promo_template'] ?? 'gradient'); ?></td>
                    <td style="font-size:13px;color:#475569;">
                        <?php
                        $bits = [];
                        if (!empty($pb['show_to_clients'])) $bits[] = 'Clients';
                        if (!empty($pb['show_to_admins'])) $bits[] = 'Admins';
                        if (!empty($pb['client_groups'])) $bits[] = 'Groups';
                        if (!empty($pb['target_clients'])) $bits[] = 'Specific clients';
                        if (!empty($pb['target_servers'])) $bits[] = 'Servers';
                        if (!empty($pb['target_products'])) $bits[] = 'Products';
                        if (!empty($pb['page_slugs'])) $bits[] = 'Pages';
                        echo $bits ? implode(' · ', $bits) : '—';
                        ?>
                    </td>
                    <td>
                        <a class="nb-btn nb-btn-ghost nb-btn-sm" href="addonmodules.php?module=noticebanner&amp;edit_promo_id=<?php echo (int)$pb['id']; ?>#nb-promotions">Edit</a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this promotion?');">
                            <input type="hidden" name="delete_notice" value="<?php echo (int)$pb['id']; ?>">
                            <button type="submit" class="nb-btn nb-btn-danger nb-btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
</div><!-- /nb-pane-promotions -->

<!-- ══════════════════════════════════════════════════════════════════════════
     TAB PANE: TO-DO BANNERS
══════════════════════════════════════════════════════════════════════════ -->
<div id="nb-pane-todo-banners" class="nb-main-tab-pane">
<div class="nb-card">
    <div class="nb-card-header">
        <h2>🧩 To-Do Banners</h2>
        <span style="font-size:13px;color:#64748b;"><?php echo count($todoBanners); ?> banner<?php echo count($todoBanners) === 1 ? '' : 's'; ?> · simple list</span>
    </div>
    <div class="nb-card-body nb-todo-app">
        <?php
        $selectedBannerId = (int)($todoFilters['notice_id'] ?? 0);
        if ($selectedBannerId <= 0 && !empty($todoBanners)) {
            $selectedBannerId = (int)$todoBanners[0]['id'];
        }
        $selectedBanner = null;
        foreach ($todoBanners as $tb) {
            if ((int)$tb['id'] === $selectedBannerId) {
                $selectedBanner = $tb;
                break;
            }
        }
        $visibleTodoRows = $selectedBannerId > 0 ? array_values(array_filter($todoRowsAll, fn($r) => (int)$r['notice_id'] === $selectedBannerId)) : [];
        $parentTodoRows = array_values(array_filter($visibleTodoRows, fn($r) => empty($r['parent_todo_id'])));
        usort($parentTodoRows, static function ($a, $b) {
            return ((int)($a['sort_order'] ?? 0)) <=> ((int)($b['sort_order'] ?? 0));
        });
        $childTodosByParent = [];
        foreach ($visibleTodoRows as $r) {
            if (!empty($r['parent_todo_id'])) {
                $pid = (int)$r['parent_todo_id'];
                if (!isset($childTodosByParent[$pid])) {
                    $childTodosByParent[$pid] = [];
                }
                $childTodosByParent[$pid][] = $r;
            }
        }
        foreach (array_keys($childTodosByParent) as $pk) {
            usort($childTodosByParent[$pk], static function ($a, $b) {
                return ((int)($a['sort_order'] ?? 0)) <=> ((int)($b['sort_order'] ?? 0));
            });
        }
        $parentTodoOpen = array_values(array_filter($parentTodoRows, fn($r) => empty($r['is_completed'])));
        $parentTodoDone = array_values(array_filter($parentTodoRows, fn($r) => !empty($r['is_completed'])));
        $nbTodoRangeEsc = htmlspecialchars($todoBannerRange, ENT_QUOTES, 'UTF-8');
        ?>

        <div class="nb-todo-toolbar">
            <form method="get" class="nb-todo-inline-form">
                <input type="hidden" name="module" value="noticebanner">
                <?php if ($selectedBannerId > 0): ?>
                <input type="hidden" name="todo_notice_id" value="<?php echo (int)$selectedBannerId; ?>">
                <?php endif; ?>
                <div class="nb-field" style="margin:0;min-width:170px;">
                    <label>Task history window</label>
                    <select name="todo_banner_range">
                        <option value="3m" <?php echo ($todoBannerRange === '3m') ? 'selected' : ''; ?>>Last 3 months</option>
                        <option value="6m" <?php echo ($todoBannerRange === '6m') ? 'selected' : ''; ?>>Last 6 months</option>
                        <option value="all" <?php echo ($todoBannerRange === 'all') ? 'selected' : ''; ?>>All time</option>
                    </select>
                </div>
                <button type="submit" class="nb-btn nb-btn-ghost nb-btn-sm">Apply</button>
            </form>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="button" class="nb-btn nb-btn-primary nb-btn-sm" onclick="nbToggleSection('nb-new-banner-body','nb-new-banner-toggle')">+ New banner</button>
                <button type="button" class="nb-btn nb-btn-ghost nb-btn-sm" onclick="nbToggleSection('nb-promote-banner-body','nb-promote-banner-toggle')">Use existing notice</button>
            </div>
        </div>

        <div id="nb-new-banner-body" class="nb-collapsible" style="margin-bottom:10px;">
            <form method="post" class="nb-grid" style="grid-template-columns:1.1fr 1.6fr 1.3fr auto;align-items:end;gap:10px;">
                <input type="hidden" name="todo_banner_range" value="<?php echo $nbTodoRangeEsc; ?>">
                <div class="nb-field" style="margin:0;">
                    <label>Title</label>
                    <input type="text" name="todo_banner_title" required placeholder="e.g. Sprint — billing rollout">
                </div>
                <div class="nb-field" style="margin:0;">
                    <label>Short description</label>
                    <input type="text" name="todo_banner_content" placeholder="Shown above tasks; banner body syncs from checklist">
                </div>
                <div class="nb-field" style="margin:0;">
                    <label>Assign / tag admins</label>
                    <select name="todo_banner_assigned_admins[]" multiple style="height:72px;">
                        <?php foreach ($admins as $a): ?>
                            <option value="<?php echo (int)$a->id; ?>"><?php echo htmlspecialchars($a->firstname . ' ' . $a->lastname . ' (@' . $a->username . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="create_todo_banner" value="1" class="nb-btn nb-btn-primary">Create</button>
            </form>
        </div>

        <div id="nb-promote-banner-body" class="nb-collapsible" style="margin-bottom:14px;">
            <form method="post" class="nb-todo-inline-form">
                <input type="hidden" name="todo_banner_range" value="<?php echo $nbTodoRangeEsc; ?>">
                <div class="nb-field" style="margin:0;min-width:280px;flex:1;">
                    <label>Turn a normal notice into a To-Do banner</label>
                    <select name="promote_notice_id" required>
                        <option value="">Choose notice…</option>
                        <?php foreach ($notices as $n): ?>
                            <option value="<?php echo (int)$n['id']; ?>"><?php echo htmlspecialchars($n['notice_title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="promote_notice_to_todo_banner" value="1" class="nb-btn nb-btn-ghost nb-btn-sm" style="margin-top:22px;">Promote</button>
            </form>
        </div>

        <div class="nb-todo-layout">
            <div class="nb-todo-side">
                <div class="nb-todo-side-title">Boards</div>
                <?php if (empty($todoBanners)): ?>
                    <div style="padding:10px;color:#94a3b8;font-size:13px;">No To-Do banners yet. Create one or promote a notice.</div>
                <?php else: ?>
                    <?php foreach ($todoBanners as $tb): ?>
                        <?php $active = ((int)$tb['id'] === $selectedBannerId); ?>
                        <?php
                        $tbOpen = 0;
                        foreach ($todoRowsAll as $tr) {
                            if ((int)$tr['notice_id'] === (int)$tb['id'] && empty($tr['is_completed'])) {
                                $tbOpen++;
                            }
                        }
                        ?>
                        <a class="nb-todo-side-item<?php echo $active ? ' active' : ''; ?>"
                           href="addonmodules.php?module=noticebanner&amp;todo_banner_range=<?php echo urlencode($todoBannerRange); ?>&amp;todo_notice_id=<?php echo (int)$tb['id']; ?>#nb-todo-banners">
                            <div style="font-size:13px;font-weight:700;line-height:1.35;"><?php echo htmlspecialchars($tb['notice_title']); ?></div>
                            <div style="font-size:11px;color:#64748b;margin-top:3px;"><?php echo (int)$tbOpen; ?> open</div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="nb-todo-main">
                <?php if (!$selectedBanner): ?>
                    <div style="padding:20px;border:1px dashed #cbd5e1;border-radius:12px;color:#64748b;font-size:14px;text-align:center;">
                        Select a board on the left or create a new To-Do banner.
                    </div>
                <?php else: ?>
                    <div class="nb-todo-banner-head">
                        <div>
                            <h3 class="nb-todo-banner-title"><?php echo htmlspecialchars($selectedBanner['notice_title']); ?></h3>
                            <p class="nb-todo-banner-sub" style="margin:6px 0 0 0;">
                                <span id="nb-todo-board-counts"><?php echo count($parentTodoOpen); ?> open · <?php echo count($parentTodoDone); ?> done</span>
                                <span style="color:#94a3b8;"> — On any admin page, click the circle on the banner to check items off (no need to open this tab).</span>
                            </p>
                            <?php if (!empty($selectedBanner['assigned_admins'])): ?>
                                <div style="margin-top:8px;display:flex;gap:4px;flex-wrap:wrap;">
                                    <?php foreach (($selectedBanner['assigned_admins'] ?? []) as $aid): ?>
                                        <span class="nb-chip">@<?php echo htmlspecialchars($adminMap[(int)$aid] ?? ('Admin #' . (int)$aid)); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                            <button type="button" class="nb-btn nb-btn-ghost nb-btn-sm" onclick="nbToggleSection('nb-edit-banner-body','nb-edit-banner-toggle')">Edit board</button>
                            <form method="post" onsubmit="return confirm('Delete this To-Do banner and all of its tasks?');" style="display:inline;">
                                <input type="hidden" name="todo_banner_range" value="<?php echo $nbTodoRangeEsc; ?>">
                                <input type="hidden" name="delete_notice" value="<?php echo (int)$selectedBanner['id']; ?>">
                                <button type="submit" class="nb-btn nb-btn-danger nb-btn-sm">Delete</button>
                            </form>
                        </div>
                    </div>

                    <div id="nb-edit-banner-body" class="nb-collapsible" style="margin-bottom:14px;">
                        <form method="post" style="border:1px solid #e2e8f0;border-radius:10px;padding:12px;background:#f8fafc;">
                            <input type="hidden" name="save_todo_banner_quick" value="1">
                            <input type="hidden" name="todo_banner_edit_id" value="<?php echo (int)$selectedBanner['id']; ?>">
                            <input type="hidden" name="todo_banner_range" value="<?php echo $nbTodoRangeEsc; ?>">
                            <div class="nb-field" style="margin:0 0 8px 0;">
                                <label>Title</label>
                                <input type="text" name="todo_banner_edit_title" required value="<?php echo htmlspecialchars($selectedBanner['notice_title']); ?>">
                            </div>
                            <p style="margin:0 0 10px 0;font-size:12px;color:#64748b;line-height:1.5;">
                                The <strong>admin banner body</strong> is built automatically from your tasks (checklist markdown). Edit tasks in the cards below — no separate description field, so the live banner cannot get out of sync.
                            </p>
                            <?php $nbSelBg = htmlspecialchars($selectedBanner['bg_color'] ?? '#e0f2fe', ENT_QUOTES, 'UTF-8'); $nbSelFg = htmlspecialchars($selectedBanner['font_color'] ?? '#0f172a', ENT_QUOTES, 'UTF-8'); ?>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                                <div class="nb-field" style="margin:0;">
                                    <label>Banner background</label>
                                    <div style="display:flex;gap:8px;align-items:center;">
                                        <input type="color" id="nb-todo-bedit-bg-pick" value="<?php echo $nbSelBg; ?>" style="width:44px;height:32px;padding:0;border:1px solid #e2e8f0;border-radius:6px;" title="Pick color" aria-label="Banner background">
                                        <input type="text" name="todo_banner_edit_bg_color" id="nb-todo-bedit-bg" value="<?php echo $nbSelBg; ?>" pattern="#[0-9A-Fa-f]{6}" style="flex:1;font-family:monospace;font-size:12px;" title="Hex color">
                                    </div>
                                </div>
                                <div class="nb-field" style="margin:0;">
                                    <label>Banner text</label>
                                    <div style="display:flex;gap:8px;align-items:center;">
                                        <input type="color" id="nb-todo-bedit-fg-pick" value="<?php echo $nbSelFg; ?>" style="width:44px;height:32px;padding:0;border:1px solid #e2e8f0;border-radius:6px;" title="Pick color" aria-label="Banner text">
                                        <input type="text" name="todo_banner_edit_font_color" id="nb-todo-bedit-fg" value="<?php echo $nbSelFg; ?>" pattern="#[0-9A-Fa-f]{6}" style="flex:1;font-family:monospace;font-size:12px;" title="Hex color">
                                    </div>
                                </div>
                            </div>
                            <script>
                            (function(){
                                function pair(pickId, textId){
                                    var p=document.getElementById(pickId),t=document.getElementById(textId);
                                    if(!p||!t)return;
                                    p.addEventListener('input',function(){t.value=p.value;});
                                    t.addEventListener('change',function(){if(/^#[0-9A-Fa-f]{6}$/.test(t.value.trim()))p.value=t.value.trim();});
                                }
                                pair('nb-todo-bedit-bg-pick','nb-todo-bedit-bg');
                                pair('nb-todo-bedit-fg-pick','nb-todo-bedit-fg');
                            })();
                            </script>
                            <p style="margin:0 0 10px 0;font-size:11px;color:#94a3b8;">These colors apply to the <strong>live admin banner</strong> strip (not individual task rows).</p>
                            <div class="nb-field" style="margin:0 0 8px 0;">
                                <label>Tagged admins</label>
                                <?php $selAdmins = array_map('intval', $selectedBanner['assigned_admins'] ?? []); ?>
                                <select name="todo_banner_edit_assigned_admins[]" multiple style="height:88px;">
                                    <?php foreach ($admins as $a): ?>
                                        <option value="<?php echo (int)$a->id; ?>" <?php echo in_array((int)$a->id, $selAdmins, true) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($a->firstname . ' ' . $a->lastname . ' (@' . $a->username . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span style="font-size:11px;color:#94a3b8;">Empty = visible to all admins.</span>
                            </div>
                            <label class="nb-switch" style="margin-bottom:8px;">
                                <input type="checkbox" name="todo_banner_edit_visible_admins" value="1" <?php echo !empty($selectedBanner['show_to_admins']) ? 'checked' : ''; ?>>
                                <span class="nb-switch-track"></span>
                                Visible in admin area
                            </label>
                            <button type="submit" class="nb-btn nb-btn-primary nb-btn-sm">Save board</button>
                        </form>
                    </div>

                    <details class="nb-todo-add-fold">
                        <summary class="nb-todo-add-fold-sum">Add task <span style="font-size:12px;font-weight:600;color:#94a3b8;text-transform:none;letter-spacing:0;">— click to expand</span></summary>
                        <div class="nb-todo-add-fold-body">
                        <form method="post" class="nb-grid nb-todo-ajax-form" style="grid-template-columns:1fr 1fr;gap:10px;align-items:end;">
                            <input type="hidden" name="nb_todo_action" value="add">
                            <input type="hidden" name="todo_notice_id" value="<?php echo (int)$selectedBanner['id']; ?>">
                            <input type="hidden" name="todo_redirect_notice_id" value="<?php echo (int)$selectedBannerId; ?>">
                            <input type="hidden" name="todo_banner_range" value="<?php echo $nbTodoRangeEsc; ?>">
                            <div class="nb-field nb-span2" style="margin:0;">
                                <label>Title</label>
                                <input type="text" name="todo_title" required placeholder="Task title">
                            </div>
                            <div class="nb-field" style="margin:0;">
                                <label>Due</label>
                                <input type="datetime-local" name="todo_due_at">
                            </div>
                            <div class="nb-field" style="margin:0;">
                                <label>Note</label>
                                <input type="text" name="todo_remarks" placeholder="Optional">
                            </div>
                            <div class="nb-field" style="margin:0;">
                                <label>Urgency</label>
                                <select name="todo_urgency">
                                    <option value="low">Low</option>
                                    <option value="normal" selected>Normal</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                            <div class="nb-field" style="margin:0;">
                                <label>Tags (comma-separated)</label>
                                <input type="text" name="todo_tags" placeholder="billing, follow-up">
                            </div>
                            <div class="nb-field nb-span2" style="margin:0;">
                                <label>Assign to admins (optional)</label>
                                <select name="todo_assigned_admins[]" multiple class="nb-todo-assign-multi">
                                    <?php foreach ($admins as $a): ?>
                                        <option value="<?php echo (int)$a->id; ?>"><?php echo htmlspecialchars($a->firstname . ' ' . $a->lastname . ' (@' . $a->username . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span style="font-size:11px;color:#94a3b8;">If set, only these admins see and edit this task.</span>
                            </div>
                            <div style="grid-column:1/-1;">
                                <button type="submit" class="nb-btn nb-btn-primary nb-btn-sm">Add task</button>
                            </div>
                        </form>
                        </div>
                    </details>

                    <?php if (empty($parentTodoRows)): ?>
                        <p style="color:#94a3b8;font-size:14px;margin:0;">No tasks yet. Add one above. On any admin page, the live banner uses the same checklist — click the circle to mark done without opening this screen.</p>
                    <?php else: ?>
                    <div class="nb-todo-flat-list">
                        <?php foreach ($parentTodoRows as $todo): ?>
                        <?php
                        $tid = (int)$todo['id'];
                        $subs = $childTodosByParent[$tid] ?? [];
                        $tw = isset($todo['title']) ? trim((string)$todo['title']) : '';
                        $sumTitle = $tw !== '' ? (function_exists('mb_strimwidth') ? mb_strimwidth($tw, 0, 72, '…', 'UTF-8') : substr($tw, 0, 70)) : 'Task';
                        $tu_display = (isset($todo['urgency']) && in_array($todo['urgency'], ['critical', 'high', 'normal', 'low'], true)) ? $todo['urgency'] : 'normal';
                        $subsOpen = count(array_filter($subs, static fn($x) => empty($x['is_completed'])));
                        $t_sel_ass = array_map('intval', $todo['assigned_admins'] ?? []);
                        ?>
                        <details open class="nb-todo-flat-block nb-todo-task-fold">
                            <summary class="nb-todo-task-fold-sum">
                                <span><?php echo htmlspecialchars($sumTitle); ?></span>
                                <?php if ($tu_display !== 'normal'): ?>
                                    <span class="nb-priority" style="color:<?php echo $priorityConfig[$tu_display]['color'] ?? '#2563eb'; ?>;background:<?php echo $priorityConfig[$tu_display]['bg'] ?? '#eff6ff'; ?>;"><?php echo htmlspecialchars($priorityConfig[$tu_display]['label'] ?? $tu_display); ?></span>
                                <?php endif; ?>
                                <span class="nb-todo-task-fold-hint"><?php echo count($subs); ?> sub · <?php echo (int)$subsOpen; ?> open</span>
                            </summary>
                            <div class="nb-todo-task-fold-body">
                            <div class="nb-todo-flat-row">
                                <form method="post" class="nb-todo-flat-toggle nb-todo-ajax-form">
                                    <input type="hidden" name="nb_todo_action" value="toggle">
                                    <input type="hidden" name="todo_id" value="<?php echo $tid; ?>">
                                    <input type="hidden" name="todo_redirect_notice_id" value="<?php echo (int)$selectedBannerId; ?>">
                                    <input type="hidden" name="todo_banner_range" value="<?php echo $nbTodoRangeEsc; ?>">
                                    <button type="submit" class="nb-todo-ring-btn<?php echo !empty($todo['is_completed']) ? ' nb-on' : ''; ?>" title="Toggle done" aria-label="Toggle done">
                                        <?php if (!empty($todo['is_completed'])): ?>
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                        <?php endif; ?>
                                    </button>
                                </form>
                                <div class="nb-todo-flat-main">
                                    <div class="nb-todo-flat-meta">Task</div>
                                    <form method="post" class="nb-todo-flat-savegrid nb-todo-ajax-form">
                                        <input type="hidden" name="nb_todo_action" value="save_todo_row">
                                        <input type="hidden" name="todo_id" value="<?php echo $tid; ?>">
                                        <input type="hidden" name="todo_redirect_notice_id" value="<?php echo (int)$selectedBannerId; ?>">
                                        <input type="hidden" name="todo_banner_range" value="<?php echo $nbTodoRangeEsc; ?>">
                                        <div class="nb-field" style="margin:0;">
                                            <label style="font-size:12px;">Title</label>
                                            <input type="text" name="todo_title" required value="<?php echo htmlspecialchars($todo['title'] ?? ''); ?>">
                                        </div>
                                        <div class="nb-todo-meta-grid">
                                            <div class="nb-field" style="margin:0;">
                                                <label style="font-size:12px;">Assignees</label>
                                                <select name="todo_assigned_admins[]" multiple class="nb-todo-assign-multi">
                                                    <?php foreach ($admins as $a): ?>
                                                        <option value="<?php echo (int)$a->id; ?>" <?php echo in_array((int)$a->id, $t_sel_ass, true) ? 'selected' : ''; ?>><?php echo htmlspecialchars($a->firstname . ' ' . $a->lastname . ' (@' . $a->username . ')'); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="nb-field" style="margin:0;">
                                                <label style="font-size:12px;">Urgency</label>
                                                <select name="todo_urgency">
                                                    <?php foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'critical' => 'Critical'] as $uk => $ul): ?>
                                                        <option value="<?php echo $uk; ?>" <?php echo $tu_display === $uk ? 'selected' : ''; ?>><?php echo htmlspecialchars($ul); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="nb-field" style="margin:0;">
                                                <label style="font-size:12px;">Tags</label>
                                                <input type="text" name="todo_tags" value="<?php echo htmlspecialchars($todo['tags'] ?? ''); ?>" placeholder="comma, separated">
                                            </div>
                                        </div>
                                        <details class="nb-todo-note-details">
                                            <summary>Note<?php echo trim((string)($todo['remarks'] ?? '')) !== '' ? ' (has text)' : ''; ?></summary>
                                            <div class="nb-field" style="margin:0;">
                                                <label style="font-size:12px;">Note / remarks</label>
                                                <textarea name="todo_remarks" rows="2" placeholder="Shown on the banner under the task"><?php echo htmlspecialchars($todo['remarks'] ?? ''); ?></textarea>
                                            </div>
                                        </details>
                                        <div class="nb-todo-flat-line2">
                                            <div class="nb-field" style="margin:0;">
                                                <label style="font-size:12px;">Due</label>
                                                <input type="datetime-local" name="todo_due_at" value="<?php echo !empty($todo['due_at']) ? date('Y-m-d\TH:i', strtotime($todo['due_at'])) : ''; ?>">
                                            </div>
                                            <button type="submit" class="nb-btn nb-btn-primary nb-btn-sm">Save</button>
                                        </div>
                                    </form>
                                    <div class="nb-todo-flat-actions">
                                        <form method="post" class="nb-todo-ajax-form" style="display:inline;">
                                            <input type="hidden" name="nb_todo_action" value="reorder">
                                            <input type="hidden" name="todo_id" value="<?php echo $tid; ?>">
                                            <input type="hidden" name="todo_direction" value="up">
                                            <input type="hidden" name="todo_redirect_notice_id" value="<?php echo (int)$selectedBannerId; ?>">
                                            <input type="hidden" name="todo_banner_range" value="<?php echo $nbTodoRangeEsc; ?>">
                                            <button type="submit" class="nb-btn nb-btn-ghost nb-btn-sm">Move up</button>
                                        </form>
                                        <form method="post" class="nb-todo-ajax-form" style="display:inline;">
                                            <input type="hidden" name="nb_todo_action" value="reorder">
                                            <input type="hidden" name="todo_id" value="<?php echo $tid; ?>">
                                            <input type="hidden" name="todo_direction" value="down">
                                            <input type="hidden" name="todo_redirect_notice_id" value="<?php echo (int)$selectedBannerId; ?>">
                                            <input type="hidden" name="todo_banner_range" value="<?php echo $nbTodoRangeEsc; ?>">
                                            <button type="submit" class="nb-btn nb-btn-ghost nb-btn-sm">Move down</button>
                                        </form>
                                        <form method="post" class="nb-todo-ajax-form" style="display:inline;margin-left:auto;" onsubmit="return confirm('Delete this task and its subtasks?');">
                                            <input type="hidden" name="nb_todo_action" value="delete">
                                            <input type="hidden" name="todo_id" value="<?php echo $tid; ?>">
                                            <input type="hidden" name="todo_redirect_notice_id" value="<?php echo (int)$selectedBannerId; ?>">
                                            <input type="hidden" name="todo_banner_range" value="<?php echo $nbTodoRangeEsc; ?>">
                                            <button type="submit" class="nb-btn nb-btn-danger nb-btn-sm">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <?php foreach ($subs as $sub): ?>
                            <?php
                            $sid = (int)$sub['id'];
                            $su_urg = (isset($sub['urgency']) && in_array($sub['urgency'], ['critical', 'high', 'normal', 'low'], true)) ? $sub['urgency'] : 'normal';
                            $su_sel = array_map('intval', $sub['assigned_admins'] ?? []);
                            ?>
                            <div class="nb-todo-flat-row nb-todo-flat-sub">
                                <form method="post" class="nb-todo-flat-toggle nb-todo-ajax-form">
                                    <input type="hidden" name="nb_todo_action" value="toggle">
                                    <input type="hidden" name="todo_id" value="<?php echo $sid; ?>">
                                    <input type="hidden" name="todo_redirect_notice_id" value="<?php echo (int)$selectedBannerId; ?>">
                                    <input type="hidden" name="todo_banner_range" value="<?php echo $nbTodoRangeEsc; ?>">
                                    <button type="submit" class="nb-todo-ring-btn<?php echo !empty($sub['is_completed']) ? ' nb-on' : ''; ?>" style="width:22px;height:22px;" title="Toggle subtask" aria-label="Toggle subtask">
                                        <?php if (!empty($sub['is_completed'])): ?>
                                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                        <?php endif; ?>
                                    </button>
                                </form>
                                <div class="nb-todo-flat-main">
                                    <div class="nb-todo-flat-meta">Subtask</div>
                                    <form method="post" class="nb-todo-flat-savegrid nb-todo-ajax-form">
                                        <input type="hidden" name="nb_todo_action" value="save_todo_row">
                                        <input type="hidden" name="todo_id" value="<?php echo $sid; ?>">
                                        <input type="hidden" name="todo_redirect_notice_id" value="<?php echo (int)$selectedBannerId; ?>">
                                        <input type="hidden" name="todo_banner_range" value="<?php echo $nbTodoRangeEsc; ?>">
                                        <div class="nb-field" style="margin:0;">
                                            <label style="font-size:12px;">Title</label>
                                            <input type="text" name="todo_title" required value="<?php echo htmlspecialchars($sub['title'] ?? ''); ?>">
                                        </div>
                                        <div class="nb-todo-meta-grid">
                                            <div class="nb-field" style="margin:0;">
                                                <label style="font-size:12px;">Assignees</label>
                                                <select name="todo_assigned_admins[]" multiple class="nb-todo-assign-multi">
                                                    <?php foreach ($admins as $a): ?>
                                                        <option value="<?php echo (int)$a->id; ?>" <?php echo in_array((int)$a->id, $su_sel, true) ? 'selected' : ''; ?>><?php echo htmlspecialchars($a->firstname . ' ' . $a->lastname . ' (@' . $a->username . ')'); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="nb-field" style="margin:0;">
                                                <label style="font-size:12px;">Urgency</label>
                                                <select name="todo_urgency">
                                                    <?php foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'critical' => 'Critical'] as $uk => $ul): ?>
                                                        <option value="<?php echo $uk; ?>" <?php echo $su_urg === $uk ? 'selected' : ''; ?>><?php echo htmlspecialchars($ul); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="nb-field" style="margin:0;">
                                                <label style="font-size:12px;">Tags</label>
                                                <input type="text" name="todo_tags" value="<?php echo htmlspecialchars($sub['tags'] ?? ''); ?>" placeholder="comma, separated">
                                            </div>
                                        </div>
                                        <details class="nb-todo-note-details">
                                            <summary>Note<?php echo trim((string)($sub['remarks'] ?? '')) !== '' ? ' (has text)' : ''; ?></summary>
                                            <div class="nb-field" style="margin:0;">
                                                <label style="font-size:12px;">Note / remarks</label>
                                                <textarea name="todo_remarks" rows="2" placeholder="Shown on the banner under the subtask"><?php echo htmlspecialchars($sub['remarks'] ?? ''); ?></textarea>
                                            </div>
                                        </details>
                                        <div class="nb-todo-flat-line2">
                                            <div class="nb-field" style="margin:0;">
                                                <label style="font-size:12px;">Due</label>
                                                <input type="datetime-local" name="todo_due_at" value="<?php echo !empty($sub['due_at']) ? date('Y-m-d\TH:i', strtotime($sub['due_at'])) : ''; ?>">
                                            </div>
                                            <button type="submit" class="nb-btn nb-btn-primary nb-btn-sm">Save</button>
                                        </div>
                                    </form>
                                    <div class="nb-todo-flat-actions">
                                        <form method="post" class="nb-todo-ajax-form" style="display:inline;">
                                            <input type="hidden" name="nb_todo_action" value="reorder">
                                            <input type="hidden" name="todo_id" value="<?php echo $sid; ?>">
                                            <input type="hidden" name="todo_direction" value="up">
                                            <input type="hidden" name="todo_redirect_notice_id" value="<?php echo (int)$selectedBannerId; ?>">
                                            <input type="hidden" name="todo_banner_range" value="<?php echo $nbTodoRangeEsc; ?>">
                                            <button type="submit" class="nb-btn nb-btn-ghost nb-btn-sm">Move up</button>
                                        </form>
                                        <form method="post" class="nb-todo-ajax-form" style="display:inline;">
                                            <input type="hidden" name="nb_todo_action" value="reorder">
                                            <input type="hidden" name="todo_id" value="<?php echo $sid; ?>">
                                            <input type="hidden" name="todo_direction" value="down">
                                            <input type="hidden" name="todo_redirect_notice_id" value="<?php echo (int)$selectedBannerId; ?>">
                                            <input type="hidden" name="todo_banner_range" value="<?php echo $nbTodoRangeEsc; ?>">
                                            <button type="submit" class="nb-btn nb-btn-ghost nb-btn-sm">Move down</button>
                                        </form>
                                        <form method="post" class="nb-todo-ajax-form" style="display:inline;margin-left:auto;" onsubmit="return confirm('Remove this subtask?');">
                                            <input type="hidden" name="nb_todo_action" value="delete">
                                            <input type="hidden" name="todo_id" value="<?php echo $sid; ?>">
                                            <input type="hidden" name="todo_redirect_notice_id" value="<?php echo (int)$selectedBannerId; ?>">
                                            <input type="hidden" name="todo_banner_range" value="<?php echo $nbTodoRangeEsc; ?>">
                                            <button type="submit" class="nb-btn nb-btn-danger nb-btn-sm">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <form method="post" class="nb-todo-flat-addsub nb-todo-ajax-form">
                                <input type="hidden" name="nb_todo_action" value="add">
                                <input type="hidden" name="todo_notice_id" value="<?php echo (int)$selectedBanner['id']; ?>">
                                <input type="hidden" name="todo_parent_todo_id" value="<?php echo $tid; ?>">
                                <input type="hidden" name="todo_redirect_notice_id" value="<?php echo (int)$selectedBannerId; ?>">
                                <input type="hidden" name="todo_banner_range" value="<?php echo $nbTodoRangeEsc; ?>">
                                <div class="nb-field" style="margin:0;">
                                    <label style="font-size:12px;">New subtask</label>
                                    <input type="text" name="todo_title" required placeholder="Title">
                                </div>
                                <div class="nb-field" style="margin:0;">
                                    <label style="font-size:12px;">Due (optional)</label>
                                    <input type="datetime-local" name="todo_due_at">
                                </div>
                                <button type="submit" class="nb-btn nb-btn-ghost nb-btn-sm" style="margin-top:22px;">Add subtask</button>
                            </form>
                            </div>
                        </details>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div><!-- /nb-pane-todo-banners -->
<script>
(function(){
var pane = document.getElementById('nb-pane-todo-banners');
if(!pane) return;
pane.addEventListener('submit', function(e){
var form = e.target;
if(!form || form.tagName !== 'FORM' || !form.classList.contains('nb-todo-ajax-form')) return;
e.preventDefault();
var fd = new FormData(form);
fd.append('nb_todo_ajax','1');
var act = fd.get('nb_todo_action') || '';
var url = form.getAttribute('action') || window.location.pathname + window.location.search;
fetch(url, { method:'POST', body:fd, credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}})
.then(function(r){ return r.json(); })
.then(function(d){
if(!d || !d.ok){ alert((d&&d.message)||'Request failed'); return; }
if(typeof d.parent_open === 'number' && typeof d.parent_done === 'number'){
var el = document.getElementById('nb-todo-board-counts');
if(el) el.textContent = d.parent_open + ' open · ' + d.parent_done + ' done';
}
if(act === 'add'){ window.location.reload(); return; }
if(act === 'toggle'){
var btn = form.querySelector('.nb-todo-ring-btn');
if(btn && typeof d.is_completed === 'boolean'){
if(d.is_completed){ btn.classList.add('nb-on'); btn.innerHTML = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>'; }
else { btn.classList.remove('nb-on'); btn.innerHTML = ''; }
}
return;
}
if(act === 'delete'){
var details = form.closest('details.nb-todo-task-fold');
var row = form.closest('.nb-todo-flat-row');
if(row && row.classList.contains('nb-todo-flat-sub')){ row.parentNode.removeChild(row); }
else if(details){ details.parentNode.removeChild(details); }
return;
}
})
.catch(function(){ alert('Network error'); });
});
})();
</script>

<!-- ══════════════════════════════════════════════════════════════════════════
     TAB PANE: LICENSE & SETTINGS
══════════════════════════════════════════════════════════════════════════ -->
<div id="nb-pane-license" class="nb-main-tab-pane">
<?php
$licKey        = noticebanner_license_get_key();
$licIssuedTo   = $licenseStatus['issued_to'] ?? '';
$licExpires    = $licenseStatus['license_expires_at'] ?? null;
$licLastOk     = $licenseStatus['last_ok_at'] ?? null;
$licNextCheck  = $licenseStatus['next_check_after'] ?? null;
$licLastError  = $licenseStatus['last_error'] ?? null;
?>

<!-- License key entry + status card -->
<div class="nb-card">
    <div class="nb-card-header">
        <h2>🔑 License Key</h2>
        <span class="nb-lic-badge <?php echo $licBadgeClass; ?>"><?php echo htmlspecialchars($licBadgeLabel); ?></span>
    </div>
    <div class="nb-card-body">

        <!-- Key entry form -->
        <form method="post" style="margin-bottom:20px;">
            <div class="nb-field" style="max-width:520px;">
                <label>Your License Key</label>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <input type="text" name="nb_license_key_input"
                        value="<?php echo htmlspecialchars($licKey); ?>"
                        placeholder="e.g. HSPELL-XXXXX-XXXXX"
                        style="flex:1;min-width:220px;font-family:monospace;letter-spacing:0.05em;">
                    <button type="submit" name="nb_license_save_key" value="1" class="nb-btn nb-btn-primary">
                        💾 Save &amp; Validate
                    </button>
                    <?php if ($licKey): ?>
                    <button type="submit" name="nb_license_save_key" value="1"
                        onclick="document.querySelector('[name=nb_license_key_input]').value=''"
                        class="nb-btn nb-btn-ghost nb-btn-sm" title="Clear key and revert to Free tier">
                        ✕ Clear Key
                    </button>
                    <?php endif; ?>
                </div>
                <span style="font-size:12px;color:#94a3b8;margin-top:4px;">
                    Enter the key you received from HostingSpell. It will be validated immediately.
                </span>
            </div>
        </form>

        <!-- Status details (only shown when a key exists) -->
        <?php if ($licKey): ?>
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 18px;">
            <div class="nb-grid-4" style="gap:12px;">
                <div>
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;margin-bottom:4px;">Plan</div>
                    <div style="font-size:15px;font-weight:800;color:<?php echo $isPro ? '#166534' : '#854d0e'; ?>;">
                        <?php echo $isPro ? '⭐ Pro' : '🔒 Free'; ?>
                    </div>
                </div>
                <div>
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;margin-bottom:4px;">Status</div>
                    <div style="font-size:14px;"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $licStatus))); ?></div>
                </div>
                <?php if ($licIssuedTo): ?>
                <div>
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;margin-bottom:4px;">Issued To</div>
                    <div style="font-size:14px;"><?php echo htmlspecialchars($licIssuedTo); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($licExpires): ?>
                <div>
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;margin-bottom:4px;">Expires</div>
                    <div style="font-size:14px;"><?php echo htmlspecialchars(date('M j, Y', strtotime($licExpires))); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($licLastOk): ?>
                <div>
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;margin-bottom:4px;">Last Validated</div>
                    <div style="font-size:13px;color:#475569;"><?php echo htmlspecialchars(date('M j, Y g:ia', strtotime($licLastOk))); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($licNextCheck): ?>
                <div>
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;margin-bottom:4px;">Next Check</div>
                    <div style="font-size:13px;color:#475569;"><?php echo htmlspecialchars(date('M j, Y g:ia', strtotime($licNextCheck))); ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($licLastError): ?>
            <div class="nb-alert nb-alert-danger" style="margin-top:12px;margin-bottom:0;">⚠ <?php echo htmlspecialchars($licLastError); ?></div>
            <?php endif; ?>
            <?php if (!empty($licenseStatus['last_http_debug'])): ?>
            <details style="margin-top:14px;border:1px solid #fecaca;border-radius:8px;background:#fff;padding:10px 14px;">
                <summary style="cursor:pointer;font-weight:700;color:#991b1b;font-size:13px;">🔧 Show HTTP connection debug (last failed attempt)</summary>
                <pre style="margin:12px 0 0;font-size:11px;line-height:1.45;white-space:pre-wrap;word-break:break-word;background:#fef2f2;padding:12px;border-radius:6px;border:1px solid #fecaca;color:#1e293b;"><?php echo htmlspecialchars($licenseStatus['last_http_debug']); ?></pre>
            </details>
            <?php endif; ?>
        </div>

        <form method="post" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <button type="submit" name="nb_license_validate_now" value="1" class="nb-btn nb-btn-ghost nb-btn-sm">↻ Re-check Now</button>
            <button type="submit" name="nb_license_run_diagnostics" value="1" class="nb-btn nb-btn-ghost nb-btn-sm" title="Test HTTPS reachability to the license server">🔧 Connection diagnostics</button>
            <a href="https://hostingspell.com/support" target="_blank" rel="noopener" class="nb-btn nb-btn-ghost nb-btn-sm">💬 Support</a>
        </form>
        <?php else: ?>
        <div style="padding:12px 0 4px;font-size:13px;color:#64748b;">
            No key entered — running in <strong>Free tier</strong>
            (<?php echo $activeCount; ?>/<?php echo $freeCap; ?> notices).
            <a href="https://hostingspell.com" target="_blank" rel="noopener" style="color:#6366f1;font-weight:600;">Get a Pro license →</a>
        </div>
        <form method="post" style="margin-top:10px;">
            <button type="submit" name="nb_license_run_diagnostics" value="1" class="nb-btn nb-btn-ghost nb-btn-sm">🔧 Connection diagnostics</button>
        </form>
        <?php endif; ?>

        <?php if (!empty($licenseDiagnosticsOutput)): ?>
        <details open style="margin-top:16px;border:1px solid #c7d2fe;border-radius:8px;background:#eef2ff;padding:10px 14px;">
            <summary style="cursor:pointer;font-weight:700;color:#3730a3;font-size:13px;">📋 Diagnostic report (just run)</summary>
            <pre style="margin:12px 0 0;font-size:11px;line-height:1.45;white-space:pre-wrap;word-break:break-word;background:#fff;padding:12px;border-radius:6px;border:1px solid #c7d2fe;color:#1e293b;"><?php echo $licenseDiagnosticsOutput; ?></pre>
        </details>
        <?php endif; ?>

    </div>
</div>

<!-- Plugin info card (read-only) -->
<div class="nb-card">
    <div class="nb-card-header">
        <h2>⚙ Plugin Info</h2>
    </div>
    <div class="nb-card-body">
        <div class="nb-grid-4" style="gap:12px;margin-bottom:16px;">
            <div>
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;margin-bottom:4px;">Module Version</div>
                <div style="font-size:14px;">3.1.0</div>
            </div>
            <div>
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;margin-bottom:4px;">PHP Version</div>
                <div style="font-size:14px;"><?php echo htmlspecialchars(PHP_VERSION); ?></div>
            </div>
            <div>
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;margin-bottom:4px;">Active Notices</div>
                <div style="font-size:14px;"><?php echo $activeCount; ?> / <?php echo $isPro ? '∞' : $freeCap; ?></div>
            </div>
            <div>
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;margin-bottom:4px;">Free Notice Cap</div>
                <div style="font-size:14px;"><?php echo $freeCap; ?> <span style="font-size:11px;color:#94a3b8;">(set by license server)</span></div>
            </div>
        </div>
        <div style="padding:12px 16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;color:#475569;line-height:1.6;">
            <strong>Free tier:</strong> Title, markdown content, visibility, colors, priority, timestamp, expand/dismiss — up to <?php echo $freeCap; ?> notices.<br>
            <strong>Pro:</strong> Polls, ticket button, webhooks, tags, templates, clone, scheduling, page/client/server/product targeting, admin assignment, acknowledgements, predefined votes, poll export, activity log, widget CRUD.
        </div>
    </div>
</div>

</div><!-- /nb-pane-license -->

<!-- ══════════════════════════════════════════════════════════════════════════
     TAB PANE: ACTIVITY LOG
══════════════════════════════════════════════════════════════════════════ -->
<div id="nb-pane-log" class="nb-main-tab-pane">
<div class="nb-card">
    <div class="nb-card-header">
        <h2>📜 Activity Log</h2>
        <?php if (!$isPro): ?>
        <span class="nb-pro-lock">🔒 Pro</span>
        <?php endif; ?>
    </div>
    <div class="nb-card-body">
    <?php if (!$isPro): ?>
        <div class="nb-alert" style="background:#fef9c3;color:#854d0e;border:1px solid #fde68a;">
            🔒 The activity log is a Pro feature. <a href="https://hostingspell.com" target="_blank" rel="noopener">Upgrade to Pro</a> to view detailed action history.
        </div>
    <?php else:
        try {
            $logRows = \WHMCS\Database\Capsule::table('mod_noticebanner_log')
                ->orderBy('id', 'desc')
                ->limit(200)
                ->get();
        } catch (\Exception $e) { $logRows = []; }
    ?>
        <?php if (empty($logRows)): ?>
            <p style="color:#94a3b8;font-size:14px;">No activity recorded yet.</p>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="nb-log-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Notice</th>
                    <th>Action</th>
                    <th>Detail</th>
                    <th>When</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logRows as $lr): ?>
                <tr>
                    <td style="color:#94a3b8;"><?php echo (int)$lr->id; ?></td>
                    <td><?php echo $lr->notice_id ? ('#' . (int)$lr->notice_id) : '—'; ?></td>
                    <td><code style="background:#f1f5f9;padding:1px 6px;border-radius:4px;font-size:12px;"><?php echo htmlspecialchars($lr->action ?? ''); ?></code></td>
                    <td style="max-width:320px;word-break:break-word;"><?php echo htmlspecialchars($lr->detail ?? ''); ?></td>
                    <td style="white-space:nowrap;color:#64748b;"><?php echo htmlspecialchars($lr->created_at ?? ''); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    <?php endif; ?>
    </div>
</div>
</div><!-- /nb-pane-log -->

</div><!-- #nb-wrap -->

<script>
// ── Main tab switching (Notices / License / Log) ──────────────────────────────
function nbMainTab(pane, el) {
    document.querySelectorAll('#nb-main-tabs .nb-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    ['notices','promotions','todo-banners','license','log'].forEach(function(p) {
        var el2 = document.getElementById('nb-pane-' + p);
        if (el2) el2.classList.toggle('active', p === pane);
    });
    // Persist selection in URL hash so page reload stays on same tab
    try { history.replaceState(null,'','#nb-' + pane); } catch(e) {}
}
// Restore tab from hash on load
(function() {
    var h = window.location.hash;
    var map = {'#nb-notices':'notices','#nb-promotions':'promotions','#nb-todo-banners':'todo-banners','#nb-todos':'todo-banners','#nb-license':'license','#nb-log':'log'};
    if (map[h]) {
        var tabs = document.querySelectorAll('#nb-main-tabs .nb-tab');
        var panes = ['notices','promotions','todo-banners','license','log'];
        panes.forEach(function(p, i) {
            var pEl = document.getElementById('nb-pane-' + p);
            if (pEl) pEl.classList.toggle('active', p === map[h]);
            if (tabs[i]) tabs[i].classList.toggle('active', p === map[h]);
        });
    }
})();

// ── Markdown editor tab switching ─────────────────────────────────────────────
function nbShowTab(tab, el) {
    document.querySelectorAll('.nb-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('nb-tab-write').style.display   = tab === 'write'   ? 'block' : 'none';
    document.getElementById('nb-tab-preview').style.display = tab === 'preview' ? 'block' : 'none';
    if (tab === 'preview') nbUpdatePreview();
}

// ── Markdown preview ──────────────────────────────────────────────────────────
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
    var el   = document.getElementById(id);
    if (!el) return;
    var open = el.style.display !== 'none';
    el.style.display = open ? 'none' : 'block';
    if (btn) btn.textContent = open ? (btn.dataset.closed || 'Show') : (btn.dataset.open || 'Hide');
}
function nbToggleSection(bodyId, toggleId) {
    var body   = document.getElementById(bodyId);
    var toggle = document.getElementById(toggleId);
    var open   = body.classList.contains('open');
    body.classList.toggle('open', !open);
    if (toggle) {
        toggle.classList.toggle('open', !open);
        toggle.textContent = open ? '▶ Show' : '▼ Hide';
    }
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

// ── Tag chip input ────────────────────────────────────────────────────────────
function nbSyncTagsHidden() {
    var pills = document.querySelectorAll('#nb-tag-wrap .nb-tag-pill');
    var tags  = Array.from(pills).map(p => p.dataset.tag);
    document.getElementById('nb-tags-hidden').value = tags.join(',');
}
function nbAddTag(val) {
    val = val.trim().toLowerCase().replace(/[^a-z0-9\-_]/g, '');
    if (!val) return;
    var existing = Array.from(document.querySelectorAll('#nb-tag-wrap .nb-tag-pill')).map(p => p.dataset.tag);
    if (existing.includes(val)) return;
    var wrap = document.getElementById('nb-tag-wrap');
    var inp  = document.getElementById('nb-tag-input');
    var pill = document.createElement('span');
    pill.className = 'nb-tag-pill';
    pill.dataset.tag = val;
    pill.innerHTML = '#' + val + ' <button type="button" onclick="nbRemoveTag(this)" tabindex="-1">&times;</button>';
    wrap.insertBefore(pill, inp);
    inp.value = '';
    nbSyncTagsHidden();
}
function nbRemoveTag(btn) {
    btn.closest('.nb-tag-pill').remove();
    nbSyncTagsHidden();
}
function nbTagKeydown(e) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        nbAddTag(e.target.value);
    } else if (e.key === 'Backspace' && e.target.value === '') {
        var pills = document.querySelectorAll('#nb-tag-wrap .nb-tag-pill');
        if (pills.length) pills[pills.length - 1].remove();
        nbSyncTagsHidden();
    }
}

// ── Tag filter bar ────────────────────────────────────────────────────────────
function nbFilterTag(el, tag) {
    document.querySelectorAll('#nb-tag-filter-bar .nb-tag').forEach(t => t.classList.remove('active'));
    if (el) el.classList.add('active');
    var rows = document.querySelectorAll('#nb-notices-table tbody tr');
    rows.forEach(function(row) {
        if (!tag) { row.style.display = ''; return; }
        var rowTags = (row.dataset.tags || '').split(',').map(t => t.trim());
        row.style.display = rowTags.includes(tag) ? '' : 'none';
    });
}

// ── Template picker ───────────────────────────────────────────────────────────
function nbApplyTemplate() {
    var sel = document.getElementById('nb-template-picker');
    var opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) return;
    document.getElementById('nb-title').value    = opt.dataset.title || '';
    document.getElementById('nb-content').value  = opt.dataset.content || '';
    document.getElementById('nb-priority').value = opt.dataset.priority || 'normal';
    document.getElementById('nb-bg-hex').value   = opt.dataset.bg || '#fffae6';
    document.getElementById('nb-fg-hex').value   = opt.dataset.fg || '#222222';
    document.getElementById('nb-bg-picker').value = opt.dataset.bg || '#fffae6';
    document.getElementById('nb-fg-picker').value = opt.dataset.fg || '#222222';
    // Tags
    document.querySelectorAll('#nb-tag-wrap .nb-tag-pill').forEach(p => p.remove());
    if (opt.dataset.tags) {
        opt.dataset.tags.split(',').forEach(t => nbAddTag(t));
    }
    nbUpdatePreview();
    document.getElementById('nb-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ── Save as Template ──────────────────────────────────────────────────────────
function nbOpenSaveTemplate(id) {
    document.getElementById('nb-tpl-src-id').value = id;
    document.getElementById('nb-save-tpl-form').style.display = 'block';
    document.getElementById('nb-save-tpl-form').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ── Scroll to form on edit load ───────────────────────────────────────────────
<?php if (isset($edit_notice)): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('nb-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
});
<?php endif; ?>
<?php if (!empty($edit_promo)): ?>
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('nb-promo-form')) {
        document.getElementById('nb-promo-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    try { history.replaceState(null,'','#nb-promotions'); } catch(e) {}
    document.querySelectorAll('#nb-main-tabs .nb-tab').forEach(function(t){t.classList.remove('active');});
    var pt = document.getElementById('nb-pane-promotions');
    var tt = document.getElementById('nb-tab-promotions');
    if (tt) tt.classList.add('active');
    if (pt) {
        pt.classList.add('active');
        ['nb-pane-notices','nb-pane-todo-banners','nb-pane-license','nb-pane-log'].forEach(function(id){
            var x=document.getElementById(id); if(x) x.classList.remove('active');
        });
    }
});
<?php endif; ?>

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    nbUpdatePreview();
    nbSyncTagsHidden();
    // Position client search dropdown relative to input
    var inp = document.getElementById('nb-client-search');
    if (inp) {
        inp.addEventListener('focus', function() {
            var res = document.getElementById('nb-client-results');
            var rect = inp.getBoundingClientRect();
            res.style.top  = (inp.offsetTop + inp.offsetHeight + 2) + 'px';
            res.style.left = inp.offsetLeft + 'px';
            res.style.width = inp.offsetWidth + 'px';
        });
        document.addEventListener('click', function(e) {
            if (!inp.contains(e.target)) {
                document.getElementById('nb-client-results').style.display = 'none';
            }
        });
    }
});

// ── Client search ─────────────────────────────────────────────────────────────
var nbClientTimer = null;
function nbClientSearch(val) {
    clearTimeout(nbClientTimer);
    var res = document.getElementById('nb-client-results');
    if (val.length < 2) { res.style.display = 'none'; return; }
    nbClientTimer = setTimeout(function() {
        var form = new FormData();
        form.append('nb_client_search', val);
        fetch(window.location.href, { method: 'POST', body: form })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.length) { res.style.display = 'none'; return; }
                res.innerHTML = '';
                data.forEach(function(c) {
                    var item = document.createElement('div');
                    item.style.cssText = 'padding:8px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid #f1f5f9;';
                    item.textContent = c.text;
                    item.onmouseenter = function() { item.style.background = '#f0f9ff'; };
                    item.onmouseleave = function() { item.style.background = ''; };
                    item.onclick = function() {
                        nbAddClient(c.id, c.text);
                        document.getElementById('nb-client-search').value = '';
                        res.style.display = 'none';
                    };
                    res.appendChild(item);
                });
                // Position dropdown below the input
                var inp = document.getElementById('nb-client-search');
                res.style.position = 'absolute';
                res.style.top  = (inp.getBoundingClientRect().bottom + window.scrollY + 2) + 'px';
                res.style.left = (inp.getBoundingClientRect().left + window.scrollX) + 'px';
                res.style.width = inp.offsetWidth + 'px';
                res.style.display = 'block';
            })
            .catch(function() { res.style.display = 'none'; });
    }, 300);
}
function nbAddClient(id, text) {
    if (document.getElementById('nb-client-chip-' + id)) return; // already added
    var chips = document.getElementById('nb-client-chips');
    var chip  = document.createElement('span');
    chip.className = 'nb-chip';
    chip.id = 'nb-client-chip-' + id;
    chip.dataset.id = id;
    chip.innerHTML = '<span>' + text.replace(/</g,'&lt;') + '</span>'
        + '<button type="button" onclick="nbRemoveClient(' + id + ')" style="background:none;border:none;cursor:pointer;color:#7c3aed;font-size:13px;line-height:1;padding:0 0 0 4px;">&times;</button>'
        + '<input type="hidden" name="target_clients[]" value="' + id + '">';
    chips.appendChild(chip);
}
function nbRemoveClient(id) {
    var chip = document.getElementById('nb-client-chip-' + id);
    if (chip) chip.remove();
}

// ── Promotion form: colors ───────────────────────────────────────────────────
function nbPromoBindColor(pickerId, hexId) {
    var p = document.getElementById(pickerId), h = document.getElementById(hexId);
    if (!p || !h) return;
    p.addEventListener('input', function() { h.value = p.value; });
    h.addEventListener('input', function() {
        if (/^#[0-9A-Fa-f]{6}$/.test(h.value)) p.value = h.value;
    });
}
document.addEventListener('DOMContentLoaded', function() {
    nbPromoBindColor('nb-promo-bg-picker', 'nb-promo-bg-hex');
    nbPromoBindColor('nb-promo-fg-picker', 'nb-promo-fg-hex');
    nbPromoBindColor('nb-promo-bt-bg-picker', 'nb-promo-bt-bg-hex');
    nbPromoBindColor('nb-promo-bt-fg-picker', 'nb-promo-bt-fg-hex');
});

// ── Promotion form: client search (separate IDs from main notice form) ────────
var nbPromoClientTimer = null;
function nbPromoClientSearch(val) {
    clearTimeout(nbPromoClientTimer);
    var res = document.getElementById('nb-promo-client-results');
    var inp = document.getElementById('nb-promo-client-search');
    if (!res || !inp) return;
    if (val.length < 2) { res.style.display = 'none'; return; }
    nbPromoClientTimer = setTimeout(function() {
        var form = new FormData();
        form.append('nb_client_search', val);
        fetch(window.location.href, { method: 'POST', body: form, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.length) { res.style.display = 'none'; return; }
                res.innerHTML = '';
                data.forEach(function(c) {
                    var item = document.createElement('div');
                    item.style.cssText = 'padding:8px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid #f1f5f9;';
                    item.textContent = c.text;
                    item.onmouseenter = function() { item.style.background = '#f0f9ff'; };
                    item.onmouseleave = function() { item.style.background = ''; };
                    item.onclick = function() {
                        nbPromoAddClient(c.id, c.text);
                        inp.value = '';
                        res.style.display = 'none';
                    };
                    res.appendChild(item);
                });
                res.style.position = 'absolute';
                res.style.top = (inp.offsetTop + inp.offsetHeight + 2) + 'px';
                res.style.left = inp.offsetLeft + 'px';
                res.style.width = inp.offsetWidth + 'px';
                res.style.display = 'block';
            })
            .catch(function() { res.style.display = 'none'; });
    }, 300);
}
function nbPromoAddClient(id, text) {
    if (document.getElementById('nb-promo-client-chip-' + id)) return;
    var chips = document.getElementById('nb-promo-client-chips');
    if (!chips) return;
    var chip = document.createElement('span');
    chip.className = 'nb-chip';
    chip.id = 'nb-promo-client-chip-' + id;
    chip.dataset.id = id;
    chip.innerHTML = '<span>' + text.replace(/</g,'&lt;') + '</span>'
        + '<button type="button" onclick="nbPromoRemoveClient(' + id + ')" style="background:none;border:none;cursor:pointer;color:#7c3aed;font-size:13px;line-height:1;padding:0 0 0 4px;">&times;</button>'
        + '<input type="hidden" name="target_clients[]" value="' + id + '">';
    chips.appendChild(chip);
}
function nbPromoRemoveClient(id) {
    var chip = document.getElementById('nb-promo-client-chip-' + id);
    if (chip) chip.remove();
}
document.addEventListener('click', function(e) {
    var res = document.getElementById('nb-promo-client-results');
    var inp = document.getElementById('nb-promo-client-search');
    if (res && inp && !inp.contains(e.target) && !res.contains(e.target)) res.style.display = 'none';
});

// ── Predefined acknowledgement panel helpers ─────────────────────────────────
function nbToggleAckList(noticeId, type) {
    document.getElementById('nb-predefined-admin-'  + noticeId).style.display = type === 'admin'  ? 'block' : 'none';
    document.getElementById('nb-predefined-client-' + noticeId).style.display = type === 'client' ? 'block' : 'none';
}
var nbPredefinedClientTimers = {};
function nbPredefinedClientSearch(inp, noticeId) {
    clearTimeout(nbPredefinedClientTimers[noticeId]);
    var val = inp.value;
    var sel = document.getElementById('nb-predefined-client-sel-' + noticeId);
    if (val.length < 2) { sel.style.display = 'none'; return; }
    nbPredefinedClientTimers[noticeId] = setTimeout(function() {
        var fd = new FormData();
        fd.append('nb_client_search', val);
        fetch(window.location.href, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                sel.innerHTML = '';
                data.forEach(function(c) {
                    var opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.text;
                    sel.appendChild(opt);
                });
                sel.style.display = data.length ? 'block' : 'none';
            });
    }, 300);
}
</script>
