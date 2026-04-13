<?php
if (!defined('WHMCS')) {
    die('Access Denied');
}

require_once __DIR__ . '/noticebanner.php';
require_once __DIR__ . '/widget.php';

// Register hooks once (prevents duplicate banners if this file is ever loaded twice)
if (!defined('NOTICEBANNER_HOOKS_REGISTERED')) {
    define('NOTICEBANNER_HOOKS_REGISTERED', true);

    // ─── Poll vote + Acknowledge endpoints — intercept POST on ANY page ──────────
    add_hook('ClientAreaPage', 1, function ($vars) {
        NoticeBannerHelper::handleAcknowledgePost('client');
        NoticeBannerHelper::handlePollVotePost();
    });
    add_hook('AdminAreaPage', 1, function ($vars) {
        NoticeBannerHelper::handleBannerTodoPost();
        NoticeBannerHelper::handleAcknowledgePost('admin');
        NoticeBannerHelper::handlePollVotePost();
    });

    // ─── Hook registrations ───────────────────────────────────────────────────────

    add_hook('ClientAreaHeaderOutput', 1, function ($vars) {
        return NoticeBannerHelper::renderNotices('client');
    });

    // Single admin hook — avoids running the renderer 3× per request (Head/Footer/Header)
    add_hook('AdminAreaHeaderOutput', 1, function ($vars) {
        return NoticeBannerHelper::renderNotices('admin');
    });
}

// ─── Renderer ────────────────────────────────────────────────────────────────

if (!class_exists('NoticeBannerHelper')) {
    class NoticeBannerHelper {

        private static $rendered = [];

        // ── Minimal Markdown → HTML ──────────────────────────────────────────
        public static function parseMarkdown(string $text): string {
            $t = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

            $t = preg_replace('/^### (.+)$/m', '<h5 style="margin:8px 0 4px;">$1</h5>', $t);
            $t = preg_replace('/^## (.+)$/m',  '<h4 style="margin:10px 0 4px;">$1</h4>', $t);
            $t = preg_replace('/^# (.+)$/m',   '<h3 style="margin:12px 0 4px;">$1</h3>', $t);

            $t = preg_replace('/\*\*\*(.+?)\*\*\*/s', '<strong><em>$1</em></strong>', $t);
            $t = preg_replace('/\*\*(.+?)\*\*/s',     '<strong>$1</strong>', $t);
            $t = preg_replace('/\*(.+?)\*/s',          '<em>$1</em>', $t);
            $t = preg_replace('/__(.+?)__/s',          '<strong>$1</strong>', $t);
            $t = preg_replace('/_(.+?)_/s',            '<em>$1</em>', $t);

            $t = preg_replace('/`(.+?)`/', '<code style="background:rgba(0,0,0,0.08);padding:1px 5px;border-radius:3px;font-size:0.9em;">$1</code>', $t);

            $t = preg_replace(
                '/\[([^\]]+)\]\((https?:\/\/[^\)]+)\)/',
                '<a href="$2" target="_blank" rel="noopener noreferrer" style="text-decoration:underline;">$1</a>',
                $t
            );

            $t = preg_replace_callback('/(?:^[-*] .+\n?)+/m', function ($m) {
                $items = preg_split('/\n/', trim($m[0]));
                $li = '';
                foreach ($items as $item) {
                    $item = preg_replace('/^[-*] /', '', $item);
                    $li  .= '<li>' . $item . '</li>';
                }
                return '<ul style="margin:6px 0 6px 18px;padding:0;">' . $li . '</ul>';
            }, $t);

            $t = preg_replace_callback('/(?:^\d+\. .+\n?)+/m', function ($m) {
                $items = preg_split('/\n/', trim($m[0]));
                $li = '';
                foreach ($items as $item) {
                    $item = preg_replace('/^\d+\. /', '', $item);
                    $li  .= '<li>' . $item . '</li>';
                }
                return '<ol style="margin:6px 0 6px 18px;padding:0;">' . $li . '</ol>';
            }, $t);

            $t = preg_replace('/^&gt; (.+)$/m', '<blockquote style="border-left:3px solid #ccc;margin:6px 0;padding:2px 10px;color:#555;">$1</blockquote>', $t);
            $t = preg_replace('/^---+$/m', '<hr style="border:none;border-top:1px solid #ddd;margin:10px 0;">', $t);
            $t = nl2br($t);
            $t = preg_replace('/@(\w+)/', '<span style="background:rgba(99,102,241,0.15);color:#4f46e5;border-radius:3px;padding:0 3px;font-weight:600;">@$1</span>', $t);

            return $t;
        }

        /**
         * To-Do banner body: render synced markdown tasks as clean rows (no raw "[ ]", no bullet quirks).
         */
        public static function parseTodoBannerBody(string $raw): string {
            $raw = str_replace(["\r\n", "\r"], "\n", $raw);
            $lines = explode("\n", $raw);
            $blocks = [];
            foreach ($lines as $line) {
                $line = rtrim($line);
                if ($line === '') {
                    continue;
                }
                if (preg_match('/^###\s+(.+)$/', $line, $m)) {
                    $blocks[] = ['type' => 'h', 'text' => $m[1]];
                    continue;
                }
                if (preg_match('/^_No tasks yet\._$/i', trim($line))) {
                    $blocks[] = ['type' => 'empty', 'text' => 'No tasks yet.'];
                    continue;
                }
                if (preg_match('/^(\s*)-\s*\[([ xX])\]\s*(.+)$/', $line, $m)) {
                    $spaces = str_replace("\t", '  ', $m[1]);
                    $depth = min(4, (int) floor(strlen($spaces) / 2));
                    $done = strtoupper(trim($m[2])) === 'X';
                    $rest = $m[3];
                    $noteLabel = '';
                    $noteMark = ' · Note: ';
                    $p = strrpos($rest, $noteMark);
                    if ($p !== false) {
                        $noteLabel = trim(substr($rest, $p + strlen($noteMark)));
                        $rest = trim(substr($rest, 0, $p));
                    }
                    $dueLabel = '';
                    $main = $rest;
                    if (preg_match('/^(.+?)\s*\(Due:\s*(.+?)\)\s*$/', $rest, $dm)) {
                        $main = trim($dm[1]);
                        $dueLabel = trim($dm[2]);
                    }
                    $main = trim((string) preg_replace('/\s*·\s*Tags:\s*.+$/u', '', $main));
                    $main = trim((string) preg_replace('/\s*·\s*\[(?:Critical|High|Normal|Low)\]\s*$/iu', '', $main));
                    $blocks[] = [
                        'type'  => 'task',
                        'depth' => $depth,
                        'done'  => $done,
                        'text'  => $main,
                        'due'   => $dueLabel,
                        'note'  => $noteLabel,
                    ];
                    continue;
                }
                $blocks[] = ['type' => 'plain', 'text' => $line];
            }

            $html = '<div class="nb-todo-banner-body">';
            foreach ($blocks as $b) {
                if ($b['type'] === 'h') {
                    $html .= '<div class="nb-todo-banner-heading">' . htmlspecialchars($b['text'], ENT_QUOTES, 'UTF-8') . '</div>';
                    continue;
                }
                if ($b['type'] === 'empty') {
                    $html .= '<div class="nb-todo-banner-empty">' . htmlspecialchars($b['text'], ENT_QUOTES, 'UTF-8') . '</div>';
                    continue;
                }
                if ($b['type'] === 'plain') {
                    $html .= '<div class="nb-todo-banner-plain">' . htmlspecialchars($b['text'], ENT_QUOTES, 'UTF-8') . '</div>';
                    continue;
                }
                if ($b['type'] === 'task') {
                    $cb = $b['done']
                        ? '<span class="nb-todo-cb nb-todo-cb-done" title="Done" aria-hidden="true">'
                            . '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>'
                            . '</span>'
                        : '<span class="nb-todo-cb nb-todo-cb-open" title="Open" aria-hidden="true"></span>';
                    $text = htmlspecialchars($b['text'], ENT_QUOTES, 'UTF-8');
                    $text = preg_replace(
                        '/@([\w.-]+)/u',
                        '<span class="nb-todo-at">@$1</span>',
                        $text
                    );
                    $due = $b['due'] !== ''
                        ? '<span class="nb-todo-due-pill">' . htmlspecialchars($b['due'], ENT_QUOTES, 'UTF-8') . '</span>'
                        : '';
                    $noteRaw = trim((string)($b['note'] ?? ''));
                    $noteHtml = $noteRaw !== ''
                        ? '<div class="nb-todo-note">' . htmlspecialchars($noteRaw, ENT_QUOTES, 'UTF-8') . '</div>'
                        : '';
                    $depth = (int) ($b['depth'] ?? 0);
                    $cls = 'nb-todo-row nb-todo-depth-' . $depth . ($b['done'] ? ' nb-todo-row-done' : '');
                    $html .= '<div class="' . $cls . '">' . $cb . '<div class="nb-todo-row-main">'
                        . '<div class="nb-todo-row-line1"><span class="nb-todo-row-text">' . $text . '</span>' . $due . '</div>'
                        . $noteHtml . '</div></div>';
                }
            }
            $html .= '</div>';
            return $html;
        }

        /** Inline CSS for To-Do checklist (injected once per page when a To-Do banner renders). */
        public static function todoBannerStyles(): string {
            return '<style id="nb-todo-banner-css">
.nb-todo-banner-body{font-size:14px;line-height:1.55;color:inherit;}
.nb-todo-banner-heading{font-weight:800;font-size:15px;margin:10px 0 6px;letter-spacing:-0.02em;color:inherit;}
.nb-todo-banner-empty{font-size:13px;opacity:0.65;font-style:italic;margin:6px 0;}
.nb-todo-banner-plain{font-size:13px;opacity:0.8;margin:4px 0;}
.nb-todo-row{display:flex;align-items:flex-start;gap:10px;margin:6px 0;padding:4px 0;border-radius:8px;}
.nb-todo-depth-1{padding-left:14px;opacity:0.95;}
.nb-todo-depth-2{padding-left:28px;opacity:0.9;}
.nb-todo-depth-3{padding-left:42px;opacity:0.88;}
.nb-todo-depth-4{padding-left:56px;opacity:0.86;}
.nb-todo-row-done .nb-todo-row-text{text-decoration:line-through;opacity:0.72;}
.nb-todo-cb{flex-shrink:0;width:18px;height:18px;margin-top:2px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;box-sizing:border-box;}
.nb-todo-cb-open{border:2px solid rgba(15,23,42,0.28);background:transparent;}
.nb-todo-cb-done{border:none;background:linear-gradient(145deg,#f97316,#ea580c);color:#fff;box-shadow:0 1px 3px rgba(234,88,12,0.45);}
.nb-todo-row-main{flex:1;min-width:0;display:flex;flex-direction:column;align-items:stretch;gap:4px;}
.nb-todo-row-line1{display:flex;flex-wrap:wrap;align-items:center;gap:6px 10px;}
.nb-todo-row-text{word-break:break-word;}
.nb-todo-note{font-size:12px;line-height:1.45;opacity:0.88;margin:0;padding:5px 0 2px 0;border-top:1px dashed rgba(15,23,42,0.1);width:100%;word-break:break-word;}
.nb-todo-row-done .nb-todo-note{text-decoration:none;opacity:0.72;}
.nb-todo-due-pill{font-size:11px;font-weight:600;padding:2px 8px;border-radius:999px;background:rgba(15,23,42,0.06);color:rgba(15,23,42,0.65);white-space:nowrap;}
.nb-todo-at{font-weight:700;color:#4f46e5;background:rgba(99,102,241,0.12);border-radius:4px;padding:0 4px;}
.nb-todo-banner-hit{background:none;border:none;padding:0;margin:0;cursor:pointer;display:inline-flex;align-items:flex-start;line-height:0;}
.nb-todo-banner-hit .nb-todo-cb{margin-top:2px;}
.nb-todo-banner-hit:focus-visible{outline:2px solid #6366f1;outline-offset:2px;border-radius:4px;}
.nb-todo-banner-live .nb-todo-row:hover{background:rgba(99,102,241,0.04);}
.nb-todo-banner-rows-neutral .nb-todo-banner-live .nb-todo-row:hover{background:rgba(15,23,42,0.05);}
.nb-todo-banner-rows-neutral .nb-todo-tag-pill{background:rgba(241,245,249,0.95);color:#334155;border:1px solid rgba(15,23,42,0.12);}
.nb-todo-banner-rows-neutral .nb-todo-at{font-weight:700;color:inherit;background:rgba(15,23,42,0.08);border-radius:4px;padding:0 4px;}
.nb-todo-banner-rows-neutral .nb-todo-urg-pill{background:rgba(15,23,42,0.08);color:inherit;}
.nb-todo-urg-pill{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:0.04em;padding:2px 8px;border-radius:999px;background:rgba(15,23,42,0.07);color:rgba(15,23,42,0.75);}
.nb-todo-tag-pill{display:inline-flex;align-items:center;gap:1px;font-size:10px;font-weight:700;padding:2px 7px 2px 6px;border-radius:4px;background:#f1f5f9;color:#334155;border:1px solid #cbd5e1;margin-right:4px;box-shadow:0 1px 0 rgba(15,23,42,0.04);}
.nb-todo-tag-pill .nb-todo-tag-prefix{font-weight:800;color:#64748b;margin-right:1px;}
.nb-todo-assignee-pill{display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:600;padding:2px 10px 2px 8px;border-radius:999px;background:linear-gradient(180deg,#ecfeff 0%,#cffafe 100%);color:#0f766e;border:1px solid #5eead4;margin-right:4px;white-space:nowrap;max-width:100%;overflow:hidden;text-overflow:ellipsis;box-shadow:0 1px 0 rgba(13,148,136,0.12);}
.nb-todo-assignee-pill .nb-todo-assignee-mark{font-weight:800;font-size:11px;line-height:1;color:#0d9488;opacity:0.95;}
.nb-todo-banner-rows-neutral .nb-todo-assignee-pill{background:rgba(240,253,250,0.95);color:#115e59;border:1px solid rgba(20,184,166,0.35);}
.nb-todo-banner-rows-neutral .nb-todo-assignee-pill .nb-todo-assignee-mark{color:#0f766e;}
details.nb-todo-banner-task-fold{border:1px solid rgba(15,23,42,0.1);border-radius:10px;margin:8px 0;background:rgba(255,255,255,0.35);overflow:hidden;}
details.nb-todo-banner-task-fold > summary.nb-todo-banner-task-sum{list-style:none;cursor:pointer;user-select:none;padding:2px 4px;display:flex;align-items:flex-start;gap:6px;}
details.nb-todo-banner-task-fold > summary::-webkit-details-marker{display:none;}
details.nb-todo-banner-task-fold > summary.nb-todo-banner-task-sum::before{content:"▸";flex-shrink:0;margin-top:6px;font-size:11px;opacity:0.55;line-height:1;}
details.nb-todo-banner-task-fold[open] > summary.nb-todo-banner-task-sum::before{content:"▾";}
.nb-todo-banner-task-sum-row{flex:1;min-width:0;display:flex;align-items:flex-start;gap:10px;margin:0;padding:4px 6px;border-radius:8px;}
.nb-todo-banner-task-body{padding:2px 8px 10px 14px;border-top:1px dashed rgba(15,23,42,0.1);}
.nb-todo-banner-task-body-row{padding-top:4px;}
.nb-todo-banner-task-hint{font-size:11px;font-weight:600;color:rgba(15,23,42,0.45);margin-left:4px;white-space:nowrap;}
details.nb-todo-banner-sub-fold{margin-top:8px;border-radius:8px;border:1px dashed rgba(99,102,241,0.28);background:rgba(99,102,241,0.04);}
details.nb-todo-banner-sub-fold > summary.nb-todo-banner-sub-sum{list-style:none;cursor:pointer;user-select:none;padding:8px 10px;font-size:11px;font-weight:800;letter-spacing:0.06em;text-transform:uppercase;color:#4f46e5;display:flex;align-items:center;gap:6px;}
details.nb-todo-banner-sub-fold > summary::-webkit-details-marker{display:none;}
details.nb-todo-banner-sub-fold > summary.nb-todo-banner-sub-sum::before{content:"▸";font-size:11px;opacity:0.55;}
details.nb-todo-banner-sub-fold[open] > summary.nb-todo-banner-sub-sum::before{content:"▾";}
.nb-todo-banner-sub-meta{font-weight:700;opacity:0.75;text-transform:none;letter-spacing:0;}
.nb-todo-banner-sub-body{padding:4px 8px 10px 10px;}
details.nb-todo-banner-outer{display:block;width:100%;box-sizing:border-box;}
details.nb-todo-banner-outer > summary.nb-todo-banner-outer-sum{list-style:none;cursor:pointer;user-select:none;display:flex;align-items:flex-start;gap:8px;width:100%;box-sizing:border-box;}
details.nb-todo-banner-outer > summary::-webkit-details-marker{display:none;}
details.nb-todo-banner-outer > summary.nb-todo-banner-outer-sum::before{content:"▸";flex-shrink:0;font-size:11px;opacity:0.55;line-height:1.5;margin-top:3px;}
details.nb-todo-banner-outer[open] > summary.nb-todo-banner-outer-sum::before{content:"▾";}
.nb-todo-banner-outer-meta{font-size:12px;font-weight:600;opacity:0.65;}
details.nb-todo-banner-outer:not([open]) .nb-todo-fold-hint-col{display:none;}
details.nb-todo-banner-outer[open] .nb-todo-fold-hint-exp{display:none;}
.nb-todo-banner-outer-body{padding-top:8px;margin-top:4px;border-top:1px solid rgba(15,23,42,0.08);}
</style>';
        }

        /** Inline CSS for promotion / sale banners (injected once per page when needed). */
        public static function promotionBannerStyles(): string {
            return '<style id="nb-promo-banner-css">
@keyframes nb-promo-shimmer{0%{background-position:0% 50%}100%{background-position:200% 50%}}
.nb-promo-surface{border-radius:14px;padding:16px 18px;position:relative;overflow:hidden;max-width:880px;margin-left:auto;margin-right:auto;text-align:left;}
.nb-promo-surface .nb-promo-headline{font-size:18px;font-weight:800;line-height:1.25;margin:0 0 8px;letter-spacing:-0.02em;}
.nb-promo-surface .nb-promo-sub{font-size:14px;line-height:1.6;opacity:0.95;}
.nb-promo-surface .nb-promo-actions{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-top:14px;}
.nb-promo-codebox{display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap;background:rgba(255,255,255,0.15);border:1px dashed rgba(255,255,255,0.45);border-radius:10px;padding:8px 12px;font-size:14px;font-weight:700;letter-spacing:0.04em;}
.nb-promo-codebox.nb-promo-code-dark{background:rgba(15,23,42,0.08);border-color:rgba(15,23,42,0.15);}
.nb-promo-copy{border:none;border-radius:8px;padding:6px 12px;font-size:12px;font-weight:700;cursor:pointer;background:rgba(255,255,255,0.95);color:#0f172a;box-shadow:0 1px 3px rgba(0,0,0,0.12);}
.nb-promo-copy:hover{filter:brightness(1.05);}
.nb-promo-cta{display:inline-flex;align-items:center;justify-content:center;padding:9px 20px;border-radius:10px;font-weight:700;font-size:14px;text-decoration:none;transition:transform .12s,box-shadow .12s;}
.nb-promo-cta:hover{transform:translateY(-1px);}
.nb-promo-gradient{color:#fff;background:linear-gradient(135deg,var(--nb-g1,#6366f1),var(--nb-g2,#a855f7));box-shadow:0 8px 28px rgba(99,102,241,0.35);}
.nb-promo-neon{color:#e2e8f0;background:linear-gradient(165deg,#0f172a,#1e1b4b);border:2px solid #22d3ee;box-shadow:0 0 0 1px rgba(34,211,238,0.35),0 8px 32px rgba(34,211,238,0.15);}
.nb-promo-neon .nb-promo-codebox{border-color:rgba(34,211,238,0.45);background:rgba(15,23,42,0.5);}
.nb-promo-ribbon{color:#0f172a;background:linear-gradient(180deg,#fffbeb,#fef3c7);border:1px solid #fcd34d;box-shadow:0 6px 20px rgba(245,158,11,0.2);}
.nb-promo-ribbon .nb-promo-ribbon-corner{position:absolute;top:0;right:0;width:0;height:0;border-style:solid;border-width:0 56px 56px 0;border-color:transparent #f59e0b transparent transparent;z-index:1;}
.nb-promo-ribbon .nb-promo-ribbon-label{position:absolute;top:10px;right:6px;z-index:2;font-size:10px;font-weight:900;color:#fff;transform:rotate(45deg);transform-origin:center;text-transform:uppercase;letter-spacing:0.06em;}
.nb-promo-minimal{color:var(--nb-m-fg,#1e293b);background:#fff;border:1px solid #e2e8f0;border-left:5px solid var(--nb-m-accent,#6366f1);box-shadow:0 4px 16px rgba(15,23,42,0.06);}
.nb-promo-minimal .nb-promo-headline,.nb-promo-minimal .nb-promo-sub{color:var(--nb-m-fg,#1e293b);}
.nb-promo-minimal .nb-promo-codebox.nb-promo-code-dark{background:#f8fafc;}
.nb-promo-ribbon .nb-promo-headline,.nb-promo-ribbon .nb-promo-sub{color:#0f172a;}
.nb-promo-flash{color:#fff;background:linear-gradient(90deg,#7c3aed,#6366f1,#ec4899,#6366f1,#7c3aed);background-size:200% 100%;animation:nb-promo-shimmer 4s ease-in-out infinite;box-shadow:0 8px 28px rgba(124,58,237,0.35);}
.nb-banner-promo-root{display:block;width:100%!important;max-width:100%!important;box-sizing:border-box;clear:both;overflow:visible;}
.nb-promo-client-slot{width:100%;max-width:100%;box-sizing:border-box;}
.nb-promo-client-slot .nb-promo-surface{max-width:100%;}
/* Beat aggressive client-theme resets so headline/body stay visible */
.nb-promo-gradient .nb-promo-headline,.nb-promo-flash .nb-promo-headline,.nb-promo-neon .nb-promo-headline{color:#fff!important;}
.nb-promo-neon .nb-promo-sub{color:#e2e8f0!important;}
.nb-promo-tags{margin-top:14px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;}
.nb-promo-tag-chip{display:inline-block;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:700;}
.nb-promo-gradient .nb-promo-tag-chip,.nb-promo-flash .nb-promo-tag-chip{background:rgba(255,255,255,0.22)!important;color:#fff!important;border:1px solid rgba(255,255,255,0.35);}
.nb-promo-neon .nb-promo-tag-chip{background:rgba(34,211,238,0.14)!important;color:#ecfeff!important;border:1px solid rgba(34,211,238,0.35);}
.nb-promo-ribbon .nb-promo-tag-chip{background:rgba(245,158,11,0.25)!important;color:#78350f!important;}
.nb-promo-minimal .nb-promo-tag-chip{background:rgba(99,102,241,0.12)!important;color:#4338ca!important;}
</style>';
        }

        /** Full-viewport-width shell for client area (breaks out of centered theme containers). */
        public static function clientAreaBleedStyles(): string {
            return '<style id="nb-client-bleed-css">
.nb-noticebanner-bleed{width:100vw;max-width:100vw;position:relative;left:50%;right:50%;margin-left:-50vw;margin-right:-50vw;box-sizing:border-box;padding:0;}
.nb-noticebanner-bleed .nb-client-notice-bar{width:100%!important;max-width:100%!important;margin:0!important;border-radius:0!important;box-sizing:border-box;}
.nb-banner-promo--clientStrip{width:100%!important;max-width:100%!important;margin:0!important;padding:0!important;border:none!important;border-radius:0!important;box-shadow:none!important;}
.nb-banner-promo--clientStrip .nb-promo-client-slot--strip{margin-top:0!important;padding:0!important;}
.nb-banner-promo--clientStrip .nb-promo-surface{border-radius:0!important;max-width:none!important;width:100%!important;margin:0!important;box-sizing:border-box;box-shadow:none!important;}
.nb-banner-promo--clientStrip .nb-promo-gradient,.nb-banner-promo--clientStrip .nb-promo-flash,.nb-banner-promo--clientStrip .nb-promo-neon,.nb-banner-promo--clientStrip .nb-promo-ribbon,.nb-banner-promo--clientStrip .nb-promo-minimal{box-shadow:none!important;}
.nb-banner-promo--clientStrip .nb-promo-surface:not(.nb-promo--collapsible-unified){padding-top:52px!important;padding-left:clamp(16px,4vw,28px)!important;padding-right:clamp(16px,4vw,28px)!important;padding-bottom:22px!important;}
.nb-promo--collapsible-unified{padding:0!important;overflow:hidden;position:relative;border-radius:0!important;}
.nb-promo--collapsible-unified .nb-promo-collapse-head{background:transparent!important;box-shadow:none!important;border:none!important;margin:0!important;cursor:pointer;}
.nb-promo--collapsible-unified .nb-promo-expanded-panel{padding:4px clamp(16px,4vw,28px) 22px;box-sizing:border-box;}
.nb-promo--collapsible-unified .nb-promo-expanded-panel .nb-promo-sub:first-child{margin-top:0;}
.nb-promo-collapse-head{display:flex;align-items:center;justify-content:space-between;gap:12px;width:100%;box-sizing:border-box;padding:14px clamp(16px,4vw,28px);min-height:48px;font-size:15px;font-weight:800;border-radius:0!important;box-shadow:none!important;}
.nb-promo-collapse-head .nb-promo-collapse-title{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.nb-promo--collapsible-unified.nb-promo-gradient .nb-promo-collapse-title,.nb-promo--collapsible-unified.nb-promo-flash .nb-promo-collapse-title{color:#fff!important;}
.nb-promo--collapsible-unified.nb-promo-neon .nb-promo-collapse-title{color:#e2e8f0!important;}
.nb-promo--collapsible-unified.nb-promo-ribbon .nb-promo-collapse-title{color:#0f172a!important;}
.nb-promo--collapsible-unified.nb-promo-minimal .nb-promo-collapse-title{color:var(--nb-m-fg,#1e293b)!important;}
.nb-promo--collapsible-unified .nb-promo-expand-toggle{border-radius:8px!important;font-weight:700!important;}
.nb-promo--collapsible-unified.nb-promo-gradient .nb-promo-expand-toggle,.nb-promo--collapsible-unified.nb-promo-flash .nb-promo-expand-toggle{border:1px solid rgba(255,255,255,0.45)!important;background:rgba(255,255,255,0.15)!important;color:#fff!important;}
.nb-promo--collapsible-unified.nb-promo-neon .nb-promo-expand-toggle{border:1px solid rgba(34,211,238,0.55)!important;background:rgba(15,23,42,0.45)!important;color:#ecfeff!important;}
.nb-promo--collapsible-unified.nb-promo-ribbon .nb-promo-expand-toggle{border:1px solid rgba(245,158,11,0.45)!important;background:rgba(255,255,255,0.65)!important;color:#78350f!important;}
.nb-promo--collapsible-unified.nb-promo-minimal .nb-promo-expand-toggle{border:1px solid rgba(15,23,42,0.15)!important;background:#f1f5f9!important;color:#0f172a!important;}
</style>';
        }

        /**
         * Normalized promo template id + same CSS variables as the promo card surface (keeps collapse bar in sync).
         *
         * @return array{tpl:string,surfaceStyle:string,bg:string,fg:string,g2:string}
         */
        private static function promoTemplateContext(array $n): array {
            $tpl = preg_replace('/[^a-z]/', '', strtolower((string)($n['promo_template'] ?? 'gradient')));
            if ($tpl === '') {
                $tpl = 'gradient';
            }
            $allowed = ['gradient', 'neon', 'ribbon', 'minimal', 'flash'];
            if (!in_array($tpl, $allowed, true)) {
                $tpl = 'gradient';
            }
            $bg = $n['bg_color'] ?: '#6366f1';
            $fg = $n['font_color'] ?: '#ffffff';
            $g2 = '#7c3aed';
            if (preg_match('/^#[0-9A-Fa-f]{6}$/', (string)$bg)) {
                $g2 = $bg;
            }
            $surfaceStyle = '';
            if ($tpl === 'gradient') {
                $surfaceStyle = ' style="--nb-g1:' . htmlspecialchars($bg, ENT_QUOTES, 'UTF-8') . ';--nb-g2:' . htmlspecialchars($g2, ENT_QUOTES, 'UTF-8') . '"';
            } elseif ($tpl === 'minimal') {
                $surfaceStyle = ' style="--nb-m-fg:' . htmlspecialchars($fg, ENT_QUOTES, 'UTF-8') . ';--nb-m-accent:' . htmlspecialchars($bg, ENT_QUOTES, 'UTF-8') . '"';
            }

            return ['tpl' => $tpl, 'surfaceStyle' => $surfaceStyle, 'bg' => $bg, 'fg' => $fg, 'g2' => $g2];
        }

        /**
         * Shared promo fragments (one animated/gradient paint when wrapped in a single .nb-promo-surface).
         *
         * @return array{tpl:string,surfaceStyle:string,ribbon:string,headlineBlock:string,belowHead:string}
         */
        private static function buildPromotionBannerParts(array $n, string $area, bool $isPro, bool $omitHeadlineForCollapsible): array {
            $ctx          = self::promoTemplateContext($n);
            $tpl          = $ctx['tpl'];
            $surfaceStyle = $ctx['surfaceStyle'];
            $headline     = htmlspecialchars($n['notice_title'] ?? '', ENT_QUOTES, 'UTF-8');
            $sub          = self::parseMarkdown($n['notice_content'] ?? '');
            $code         = trim((string)($n['promo_coupon_code'] ?? ''));
            $codeEsc      = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

            $codeRow = '';
            if ($code !== '') {
                $dark = ($tpl === 'minimal' || $tpl === 'ribbon') ? ' nb-promo-code-dark' : '';
                $codeRow = '<div class="nb-promo-codebox' . $dark . '" role="group" aria-label="Coupon code">'
                    . '<span style="opacity:0.85;font-size:12px;font-weight:600;">Code</span> '
                    . '<span class="nb-promo-code-val">' . $codeEsc . '</span> '
                    . '<button type="button" class="nb-promo-copy" data-nb-copy="' . $codeEsc . '" onclick="nbCopyPromoCode(this)">Copy</button>'
                    . '</div>';
            }

            $cta = '';
            if ($isPro && !empty($n['button_enabled']) && !empty($n['button_text']) && !empty($n['button_link'])) {
                $target = !empty($n['button_newtab']) ? ' target="_blank" rel="noopener noreferrer"' : '';
                $cta = '<a class="nb-promo-cta" href="' . htmlspecialchars($n['button_link'], ENT_QUOTES, 'UTF-8') . '"' . $target
                    . ' style="background:' . htmlspecialchars($n['button_bg'] ?? '#fff', ENT_QUOTES, 'UTF-8')
                    . ';color:' . htmlspecialchars($n['button_color'] ?? '#0f172a', ENT_QUOTES, 'UTF-8') . ';">'
                    . htmlspecialchars($n['button_text'], ENT_QUOTES, 'UTF-8') . '</a>';
            }

            $ribbon = '';
            if ($tpl === 'ribbon') {
                $ribbon = '<span class="nb-promo-ribbon-corner" aria-hidden="true"></span><span class="nb-promo-ribbon-label">Sale</span>';
            }

            $tagsBlock = '';
            if ($isPro && !empty($n['tags'])) {
                $tagsBlock = '<div class="nb-promo-tags">';
                foreach (array_map('trim', explode(',', (string)$n['tags'])) as $tag) {
                    if ($tag === '') {
                        continue;
                    }
                    $tagsBlock .= '<span class="nb-promo-tag-chip">#'
                        . htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') . '</span>';
                }
                $tagsBlock .= '</div>';
            }

            $headlineBlock = $omitHeadlineForCollapsible ? '' : '<div class="nb-promo-headline">' . $headline . '</div>';
            $belowHead     = ($sub !== '' ? '<div class="nb-promo-sub">' . $sub . '</div>' : '')
                . '<div class="nb-promo-actions">' . $codeRow . $cta . '</div>'
                . $tagsBlock;

            return [
                'tpl'            => $tpl,
                'surfaceStyle'   => $surfaceStyle,
                'ribbon'         => $ribbon,
                'headlineBlock'  => $headlineBlock,
                'belowHead'      => $belowHead,
            ];
        }

        /**
         * Rich promotion banner body: headline, markdown, optional coupon + CTA.
         */
        public static function renderPromotionBannerBody(array $n, string $area, bool $isPro, bool $omitHeadlineForCollapsible = false): string {
            $p = self::buildPromotionBannerParts($n, $area, $isPro, $omitHeadlineForCollapsible);

            return '<div class="nb-promo-surface nb-promo-' . $p['tpl'] . '"' . $p['surfaceStyle'] . '>' . $p['ribbon']
                . $p['headlineBlock'] . $p['belowHead'] . '</div>';
        }

        /** One-line script for coupon copy (injected once per page when promos with codes exist). */
        public static function promotionCopyScript(): string {
            return '<script>
if(typeof nbCopyPromoCode==="undefined"){
function nbCopyPromoCode(btn){
var v=btn.getAttribute("data-nb-copy")||"";
if(!v)return;
if(navigator.clipboard&&navigator.clipboard.writeText){
navigator.clipboard.writeText(v).then(function(){var o=btn.textContent;btn.textContent="Copied!";setTimeout(function(){btn.textContent=o;},1600);});
}else{var t=document.createElement("textarea");t.value=v;t.style.position="fixed";t.style.left="-9999px";document.body.appendChild(t);t.select();try{document.execCommand("copy");var o=btn.textContent;btn.textContent="Copied!";setTimeout(function(){btn.textContent=o;},1600);}catch(e){}document.body.removeChild(t);}
}
}
</script>';
        }

        private static function countTodoTreeNodes(array $tree): int {
            $n = 0;
            foreach ($tree as $node) {
                $n++;
                if (!empty($node['children']) && is_array($node['children'])) {
                    $n += self::countTodoTreeNodes($node['children']);
                }
            }
            return $n;
        }

        /** Count markdown checklist lines (client / synced body) for summary hints. */
        private static function countTodoMarkdownTasks(string $raw): int {
            $raw = str_replace(["\r\n", "\r"], "\n", $raw);
            $c = 0;
            foreach (explode("\n", $raw) as $line) {
                if (preg_match('/^(\s*)-\s*\[([ xX])\]\s*(.+)$/', rtrim($line))) {
                    $c++;
                }
            }
            return $c;
        }

        /**
         * Admin-only: interactive checklist HTML + task count (no outer fold — fold wraps whole banner in renderNotices).
         *
         * @return array{html: string, count: int}
         */
        private static function adminTodoBannerContentAndCount(int $noticeId): array {
            $empty = [
                'html' => '<div class="nb-todo-banner-body nb-todo-banner-live nb-todo-banner-rows-neutral"><div class="nb-todo-banner-empty">No tasks yet.</div></div>',
                'count' => 0,
            ];
            if ($noticeId <= 0 || !function_exists('noticebanner_get_todos_for_notice')) {
                return $empty;
            }
            $tree = noticebanner_get_todos_for_notice($noticeId, self::currentAdminId());
            $count = self::countTodoTreeNodes($tree);
            if (empty($tree)) {
                return $empty;
            }
            $html = '<div class="nb-todo-banner-body nb-todo-banner-live nb-todo-banner-rows-neutral">';
            foreach ($tree as $task) {
                $html .= self::renderAdminTodoNodeHtml($task, 0, true);
            }
            $html .= '</div>';
            return ['html' => $html, 'count' => $count];
        }

        /**
         * @param bool $neutralRows When true (banner checklist), skip per-task accent colors so notice banner colors dominate.
         */
        private static function renderAdminTodoNodeHtml(array $task, int $depth, bool $neutralRows = false): string {
            $id = (int)($task['id'] ?? 0);
            $done = !empty($task['is_completed']);
            $titleRaw = (string)($task['title'] ?? '');
            $title = htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8');
            $title = preg_replace('/@([\w.-]+)/u', '<span class="nb-todo-at">@$1</span>', $title);
            $urgency = function_exists('noticebanner_normalize_todo_urgency')
                ? noticebanner_normalize_todo_urgency((string)($task['urgency'] ?? 'normal'))
                : 'normal';
            $accentEsc = '';
            if (!$neutralRows) {
                $accent = !empty($task['accent_color'])
                    ? (string)$task['accent_color']
                    : (function_exists('noticebanner_todo_urgency_default_hex') ? noticebanner_todo_urgency_default_hex($urgency) : '#2563eb');
                $accentEsc = htmlspecialchars($accent, ENT_QUOTES, 'UTF-8');
            }
            $dueHtml = '';
            if (!empty($task['due_at'])) {
                $dueHtml = '<span class="nb-todo-due-pill">' . htmlspecialchars(date('M j, Y g:ia', strtotime($task['due_at'])), ENT_QUOTES, 'UTF-8') . '</span>';
            }
            $urgHtml = '';
            if ($urgency !== 'normal') {
                $urgHtml = '<span class="nb-todo-urg-pill">' . htmlspecialchars(ucfirst($urgency), ENT_QUOTES, 'UTF-8') . '</span>';
            }
            $tagsHtml = '';
            $tagsRaw = trim((string)($task['tags'] ?? ''));
            if ($tagsRaw !== '') {
                foreach (array_map('trim', explode(',', $tagsRaw)) as $tg) {
                    if ($tg === '') {
                        continue;
                    }
                    $tagsHtml .= '<span class="nb-todo-tag-pill"><span class="nb-todo-tag-prefix" aria-hidden="true">#</span>'
                        . '<span>' . htmlspecialchars($tg, ENT_QUOTES, 'UTF-8') . '</span></span>';
                }
            }
            $assHtml = '';
            $assIds = [];
            if (!empty($task['assigned_admins']) && is_array($task['assigned_admins'])) {
                $assIds = array_values(array_unique(array_filter(array_map('intval', $task['assigned_admins']))));
            }
            if ($assIds !== []) {
                $nameMap = self::adminNames($assIds);
                foreach ($assIds as $aid) {
                    if ($aid <= 0) {
                        continue;
                    }
                    $nm = trim((string)($nameMap[$aid] ?? '')) !== '' ? trim((string)$nameMap[$aid]) : ('Admin #' . $aid);
                    $assHtml .= '<span class="nb-todo-assignee-pill" title="Assigned">'
                        . '<span class="nb-todo-assignee-mark" aria-hidden="true">@</span>'
                        . '<span>' . htmlspecialchars($nm, ENT_QUOTES, 'UTF-8') . '</span></span>';
                }
            }
            $remarks = trim((string)($task['remarks'] ?? ''));
            $noteHtml = $remarks !== ''
                ? '<div class="nb-todo-note">' . htmlspecialchars($remarks, ENT_QUOTES, 'UTF-8') . '</div>'
                : '';
            $cbInner = $done
                ? '<span class="nb-todo-cb nb-todo-cb-done" aria-hidden="true"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>'
                : '<span class="nb-todo-cb nb-todo-cb-open" aria-hidden="true"></span>';
            $btn = '<button type="button" class="nb-todo-banner-hit" onclick="nbBannerTodoToggle(this,' . $id . ')" title="Toggle done">' . $cbInner . '</button>';
            $d = min(4, $depth);
            $cls = 'nb-todo-row nb-todo-depth-' . $d . ($done ? ' nb-todo-row-done' : '');
            $metaLine = ($urgHtml !== '' || $tagsHtml !== '' || $assHtml !== '')
                ? '<div class="nb-todo-row-line1" style="margin-top:2px;">' . $urgHtml . $tagsHtml . $assHtml . '</div>'
                : '';
            $rowAttr = '';
            if (!$neutralRows && $accentEsc !== '') {
                $rowAttr = ' style="--nb-todo-accent:' . $accentEsc . ';"';
            }

            $hasChildren = !empty($task['children']) && is_array($task['children']);
            $childCount = $hasChildren ? count($task['children']) : 0;

            $line1 = '<div class="nb-todo-row-line1"><span class="nb-todo-row-text">' . $title . '</span>' . $dueHtml . '</div>';

            $subHtml = '';
            if ($hasChildren) {
                foreach ($task['children'] as $ch) {
                    $subHtml .= self::renderAdminTodoNodeHtml($ch, $depth + 1, $neutralRows);
                }
            }

            $subtasksFold = '';
            if ($hasChildren) {
                $subtasksFold = '<details class="nb-todo-banner-sub-fold">'
                    . '<summary class="nb-todo-banner-sub-sum">Subtasks <span class="nb-todo-banner-sub-meta">(' . $childCount . ')</span></summary>'
                    . '<div class="nb-todo-banner-sub-body">' . $subHtml . '</div>'
                    . '</details>';
            }

            if ($depth === 0) {
                $hint = $hasChildren
                    ? '<span class="nb-todo-banner-task-hint"> · ' . $childCount . ' sub' . ($childCount === 1 ? '' : 's') . '</span>'
                    : '';
                $sumLine1 = '<div class="nb-todo-row-line1"><span class="nb-todo-row-text">' . $title . '</span>' . $dueHtml . $hint . '</div>';
                $sumRow = '<div class="' . $cls . ' nb-todo-banner-task-sum-row"' . $rowAttr . '>' . $btn
                    . '<div class="nb-todo-row-main">' . $sumLine1 . '</div></div>';
                $bodyInner = $metaLine . $noteHtml . $subtasksFold;
                $bodyBlock = $bodyInner !== ''
                    ? '<div class="' . $cls . ' nb-todo-banner-task-body-row"' . $rowAttr . '><div class="nb-todo-row-main">' . $bodyInner . '</div></div>'
                    : '';

                return '<details class="nb-todo-banner-task-fold">'
                    . '<summary class="nb-todo-banner-task-sum">' . $sumRow . '</summary>'
                    . '<div class="nb-todo-banner-task-body">' . $bodyBlock . '</div>'
                    . '</details>';
            }

            $row = '<div class="' . $cls . '"' . $rowAttr . '>' . $btn . '<div class="nb-todo-row-main">'
                . $line1 . $metaLine . $noteHtml . '</div></div>';

            return $row . $subtasksFold;
        }

        private static function bannerTodoToggleScript(): string {
            return '<script>
if(typeof nbBannerTodoToggle==="undefined"){
function nbBannerTodoToggle(btn,todoId){
if(!todoId||btn.dataset.nbLoading)return;
btn.dataset.nbLoading="1";
var fd=new FormData();
fd.append("nb_banner_todo_toggle","1");
fd.append("todo_id",String(todoId));
fetch(window.location.href,{method:"POST",body:fd,credentials:"same-origin",headers:{"X-Requested-With":"XMLHttpRequest","Accept":"application/json"}})
.then(function(r){return r.json();})
.then(function(d){
btn.dataset.nbLoading="";
if(d&&d.ok){
var row=btn.closest(".nb-todo-row");
var done=!!d.is_completed;
if(row){
if(done){row.classList.add("nb-todo-row-done");}else{row.classList.remove("nb-todo-row-done");}
}
if(done){
btn.innerHTML="<span class=\\"nb-todo-cb nb-todo-cb-done\\" aria-hidden=\\"true\\"><svg width=\\"11\\" height=\\"11\\" viewBox=\\"0 0 24 24\\" fill=\\"none\\" stroke=\\"currentColor\\" stroke-width=\\"3\\"><polyline points=\\"20 6 9 17 4 12\\"/></svg></span>";
}else{
btn.innerHTML="<span class=\\"nb-todo-cb nb-todo-cb-open\\" aria-hidden=\\"true\\"></span>";
}
}else{
alert((d&&d.message)||"Could not update task");
}
})
.catch(function(){btn.dataset.nbLoading="";alert("Network error");});
}
}
</script>';
        }

        // ── Priority badge ───────────────────────────────────────────────────
        private static function priorityBadge(string $priority): string {
            $map = [
                'critical' => ['#dc2626', '#fff', '🔴 Critical'],
                'high'     => ['#f97316', '#fff', '🟠 High'],
                'normal'   => ['#2563eb', '#fff', '🔵 Normal'],
                'low'      => ['#6b7280', '#fff', '⚪ Low'],
            ];
            [$bg, $fg, $label] = $map[$priority] ?? $map['normal'];
            return '<span style="display:inline-block;padding:1px 8px;border-radius:12px;font-size:11px;font-weight:700;background:' . $bg . ';color:' . $fg . ';margin-left:8px;vertical-align:middle;">' . $label . '</span>';
        }

        /** Left stripe color derived from banner background (notice “Background” picker), not per-task accents. */
        private static function todoBannerLeftBorderColor(string $bgHex, string $fallbackAccent): string {
            $hex = trim($bgHex);
            if (!preg_match('/^#([0-9A-Fa-f]{6})$/', $hex, $m)) {
                return $fallbackAccent;
            }
            $r = hexdec(substr($m[1], 0, 2));
            $g = hexdec(substr($m[1], 2, 2));
            $b = hexdec(substr($m[1], 4, 2));
            $mix = 0.42;
            $r2 = (int) round($r * (1 - $mix) + 30 * $mix);
            $g2 = (int) round($g * (1 - $mix) + 58 * $mix);
            $b2 = (int) round($b * (1 - $mix) + 138 * $mix);
            return sprintf('#%02x%02x%02x', max(0, min(255, $r2)), max(0, min(255, $g2)), max(0, min(255, $b2)));
        }

        // ── Get current admin ID ─────────────────────────────────────────────
        private static function currentAdminId(): int {
            if (!empty($_SESSION['adminid'])) return (int)$_SESSION['adminid'];
            if (class_exists('\WHMCS\Authentication\CurrentUser')) {
                try {
                    $user = \WHMCS\Authentication\CurrentUser::adminUser();
                    if ($user) return (int)$user->id;
                } catch (\Exception $e) {}
            }
            return 0;
        }

        // ── Get current client ID ────────────────────────────────────────────
        private static function currentClientId(): int {
            if (!empty($_SESSION['uid'])) return (int)$_SESSION['uid'];
            return 0;
        }

        // ── Get current client's group ID ────────────────────────────────────
        private static function currentClientGroupId(): int {
            $uid = self::currentClientId();
            if (!$uid) return 0;
            try {
                $row = \WHMCS\Database\Capsule::table('tblclients')
                    ->where('id', $uid)
                    ->value('groupid');
                return (int)($row ?? 0);
            } catch (\Exception $e) {
                return 0;
            }
        }

        // ── Get server IDs the current client has active services on ─────────
        private static function currentClientServerIds(): array {
            $uid = self::currentClientId();
            if (!$uid) return [];
            try {
                return \WHMCS\Database\Capsule::table('tblhosting')
                    ->where('userid', $uid)
                    ->whereIn('domainstatus', ['Active', 'Suspended'])
                    ->whereNotNull('server')
                    ->where('server', '>', 0)
                    ->pluck('server')
                    ->map(fn($v) => (int)$v)
                    ->unique()
                    ->values()
                    ->toArray();
            } catch (\Exception $e) {
                return [];
            }
        }

        // ── Get product IDs the current client has active services for ───────
        private static function currentClientProductIds(): array {
            $uid = self::currentClientId();
            if (!$uid) return [];
            try {
                return \WHMCS\Database\Capsule::table('tblhosting')
                    ->where('userid', $uid)
                    ->whereIn('domainstatus', ['Active', 'Suspended'])
                    ->pluck('packageid')
                    ->map(fn($v) => (int)$v)
                    ->unique()
                    ->values()
                    ->toArray();
            } catch (\Exception $e) {
                return [];
            }
        }

        // ── Resolve admin names from IDs ─────────────────────────────────────
        private static function adminNames(array $ids): array {
            if (empty($ids)) return [];
            try {
                $rows = \WHMCS\Database\Capsule::table('tbladmins')
                    ->whereIn('id', $ids)
                    ->get(['id', 'firstname', 'lastname', 'username'])
                    ->toArray();
                $map = [];
                foreach ($rows as $r) {
                    $map[(int)$r->id] = $r->firstname . ' ' . $r->lastname;
                }
                return $map;
            } catch (\Exception $e) {
                return [];
            }
        }

        // ── Handle poll vote POST on any page (called from hook, exits with JSON) ──
        public static function handlePollVotePost(): void {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
            if (!noticebanner_license_is_pro()) {
                if (!empty($_POST['nb_poll_vote']) || !empty($_POST['nb_poll_reset_vote'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => false, 'pro_required' => true]);
                    exit;
                }
                return;
            }

            // ── Reset own vote ──────────────────────────────────────────────
            if (!empty($_POST['nb_poll_reset_vote'])) {
                $nid        = (int)($_POST['poll_notice_id'] ?? 0);
                $isAdmin    = !empty($_SESSION['adminid']);
                $entityType = $isAdmin ? 'admin' : 'client';
                $entityId   = $isAdmin ? (int)$_SESSION['adminid'] : (int)($_SESSION['uid'] ?? 0);

                if ($nid && $entityId) {
                    try {
                        noticebanner_ensure_table();
                        noticebanner_ensure_columns();
                        // Find the existing vote record
                        $vrow = \WHMCS\Database\Capsule::table('mod_noticebanner_poll_votes')
                            ->where('notice_id',    $nid)
                            ->where('entity_type',  $entityType)
                            ->where('entity_id',    $entityId)
                            ->where('is_predefined', 0)
                            ->first();
                        if ($vrow) {
                            \WHMCS\Database\Capsule::table('mod_noticebanner_poll_votes')
                                ->where('id', $vrow->id)->delete();
                            // Decrement aggregate
                            $nrow = \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $nid)->first();
                            if ($nrow) {
                                $results = json_decode($nrow->poll_results ?? '{}', true) ?: [];
                                $opt     = $vrow->poll_option;
                                if (isset($results[$opt]) && $results[$opt] > 0) $results[$opt]--;
                                \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $nid)
                                    ->update(['poll_results' => json_encode($results), 'updated_at' => date('Y-m-d H:i:s')]);
                                $total = array_sum($results);
                                noticebanner_log($nid, 'poll_vote_reset', "$entityType #$entityId reset vote");
                                header('Content-Type: application/json');
                                echo json_encode(['ok' => true, 'reset' => true, 'results' => $results, 'total' => $total]);
                                exit;
                            }
                        }
                    } catch (\Exception $e) {}
                }
                header('Content-Type: application/json');
                echo json_encode(['ok' => false]);
                exit;
            }

            if (empty($_POST['nb_poll_vote'])) return;

            // ── Cast vote ───────────────────────────────────────────────────
            $nid  = (int)($_POST['poll_notice_id'] ?? 0);
            $vote = html_entity_decode($_POST['poll_vote'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($nid && $vote !== '') {
                try {
                    noticebanner_ensure_table();
                    noticebanner_ensure_columns();

                    $isAdmin    = !empty($_SESSION['adminid']);
                    $entityType = $isAdmin ? 'admin' : 'client';
                    $entityId   = $isAdmin ? (int)$_SESSION['adminid'] : (int)($_SESSION['uid'] ?? 0);

                    // Must be logged in; guests cannot vote
                    if ($entityId <= 0) {
                        header('Content-Type: application/json');
                        echo json_encode(['ok' => false, 'auth_required' => true]);
                        exit;
                    }

                    $row = \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $nid)->first();
                    if (!$row || !function_exists('noticebanner_poll_vote_is_valid') || !noticebanner_poll_vote_is_valid($row, $vote)) {
                        header('Content-Type: application/json');
                        echo json_encode(['ok' => false, 'invalid_option' => true]);
                        exit;
                    }

                    // Block duplicate votes — return current state so JS can show it
                    if (self::hasVoted($nid, $entityType, $entityId)) {
                        $existing = self::getVotedOption($nid, $entityType, $entityId);
                        $nrow     = $row;
                        $results  = json_decode($nrow->poll_results ?? '{}', true) ?: [];
                        $total    = array_sum($results);
                        header('Content-Type: application/json');
                        echo json_encode(['ok' => false, 'already_voted' => true, 'voted_option' => $existing, 'results' => $results, 'total' => $total]);
                        exit;
                    }

                    $voteKey = trim(html_entity_decode($vote, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    $results = json_decode($row->poll_results ?? '{}', true) ?: [];
                    $results[$voteKey] = ($results[$voteKey] ?? 0) + 1;
                    \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $nid)
                        ->update(['poll_results' => json_encode($results), 'updated_at' => date('Y-m-d H:i:s')]);

                    $label = '';
                    try {
                        if ($isAdmin) {
                            $u = \WHMCS\Database\Capsule::table('tbladmins')->where('id', $entityId)->first(['firstname', 'lastname', 'username']);
                            if ($u) $label = trim($u->firstname . ' ' . $u->lastname) . ' (@' . $u->username . ')';
                        } else {
                            $u = \WHMCS\Database\Capsule::table('tblclients')->where('id', $entityId)->first(['firstname', 'lastname', 'email']);
                            if ($u) $label = trim($u->firstname . ' ' . $u->lastname) . ' (' . $u->email . ')';
                        }
                    } catch (\Exception $e) {}

                    \WHMCS\Database\Capsule::table('mod_noticebanner_poll_votes')->insert([
                        'notice_id'     => $nid,
                        'entity_type'   => $entityType,
                        'entity_id'     => $entityId,
                        'entity_label'  => $label,
                        'poll_option'   => $voteKey,
                        'is_predefined' => 0,
                        'voted_at'      => date('Y-m-d H:i:s'),
                    ]);

                    noticebanner_log($nid, 'poll_vote', "$entityType #$entityId voted: $voteKey");
                    $total = array_sum($results);
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => true, 'results' => $results, 'total' => $total, 'voted_option' => $voteKey]);
                    exit;
                } catch (\Exception $e) {}
            }

            header('Content-Type: application/json');
            echo json_encode(['ok' => false]);
            exit;
        }

        // ── Handle acknowledge POST on any page (called from hook, exits with JSON) ──
        /** Toggle To-Do from live admin banner (any admin page, AJAX). */
        public static function handleBannerTodoPost(): void {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['nb_banner_todo_toggle'])) {
                return;
            }
            header('Content-Type: application/json; charset=utf-8');
            if (!function_exists('noticebanner_admin_toggle_todo_by_id')) {
                echo json_encode(['ok' => false, 'message' => 'Module not loaded']);
                exit;
            }
            $tid = (int)($_POST['todo_id'] ?? 0);
            $out = noticebanner_admin_toggle_todo_by_id($tid);
            echo json_encode($out);
            exit;
        }

        public static function handleAcknowledgePost(string $area): void {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
            if (empty($_POST['nb_acknowledge'])) return;
            if (!noticebanner_license_is_pro()) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'pro_required' => true]);
                exit;
            }

            $nid = (int)($_POST['mark_read_id'] ?? 0);
            // Always bind to the logged-in session — never trust POST type/entity (spoofing)
            if ($area === 'client') {
                $type = 'client';
                $eid  = (int)($_SESSION['uid'] ?? 0);
            } else {
                $type = 'admin';
                $eid  = (int)($_SESSION['adminid'] ?? 0);
            }

            $ok = false;
            if ($nid && $eid) {
                try {
                    noticebanner_ensure_table();
                    noticebanner_ensure_columns();
                    \WHMCS\Database\Capsule::table('mod_noticebanner_reads')->updateOrInsert(
                        ['notice_id' => $nid, 'entity_type' => $type, 'entity_id' => $eid],
                        ['read_at' => date('Y-m-d H:i:s')]
                    );
                    noticebanner_log($nid, 'acknowledged', "Type: $type, Entity: $eid");
                    $ok = true;
                } catch (\Exception $e) {}
            }

            header('Content-Type: application/json');
            echo json_encode(['ok' => $ok]);
            exit;
        }

        // ── Check if entity has already voted on a poll (non-predefined only) ──
        private static function hasVoted(int $noticeId, string $type, int $entityId): bool {
            if (!$entityId) return false;
            try {
                return \WHMCS\Database\Capsule::table('mod_noticebanner_poll_votes')
                    ->where('notice_id',    $noticeId)
                    ->where('entity_type',  $type)
                    ->where('entity_id',    $entityId)
                    ->where('is_predefined', 0)
                    ->exists();
            } catch (\Exception $e) { return false; }
        }

        // ── Get the option the entity voted for (null if not voted) ──────────
        private static function getVotedOption(int $noticeId, string $type, int $entityId): ?string {
            if (!$entityId) return null;
            try {
                $row = \WHMCS\Database\Capsule::table('mod_noticebanner_poll_votes')
                    ->where('notice_id',    $noticeId)
                    ->where('entity_type',  $type)
                    ->where('entity_id',    $entityId)
                    ->where('is_predefined', 0)
                    ->orderBy('voted_at', 'desc')
                    ->first(['poll_option']);
                return $row ? $row->poll_option : null;
            } catch (\Exception $e) { return null; }
        }

        // ── Check if entity has already acknowledged a notice ────────────────
        private static function hasAcknowledged(int $noticeId, string $type, int $entityId): bool {
            if (!$entityId) return false;
            try {
                return \WHMCS\Database\Capsule::table('mod_noticebanner_reads')
                    ->where('notice_id', $noticeId)
                    ->where('entity_type', $type)
                    ->where('entity_id', $entityId)
                    ->exists();
            } catch (\Exception $e) {
                return false;
            }
        }

        // ── Main render ──────────────────────────────────────────────────────
        public static function renderNotices(string $area): string {
            if (!empty(self::$rendered[$area])) return '';
            self::$rendered[$area] = true;

            // Use rendering mode — applies expiry + publish_at filters
            $notices = function_exists('noticebanner_get_notices') ? noticebanner_get_notices(true) : [];
            if (empty($notices)) return '';

            $isPro = function_exists('noticebanner_license_is_pro') && noticebanner_license_is_pro();

            $currentAdminId      = ($area === 'admin')  ? self::currentAdminId()          : 0;
            $currentClientId     = ($area === 'client') ? self::currentClientId()         : 0;
            $currentGroupId      = ($area === 'client') ? self::currentClientGroupId()    : 0;
            $clientServerIds     = ($area === 'client') ? self::currentClientServerIds()  : [];
            $clientProductIds    = ($area === 'client') ? self::currentClientProductIds() : [];
            $requestUri          = $_SERVER['REQUEST_URI'] ?? '';

            $html = '';
            $stylePrefix = '';
            $needsBannerTodoJs = false;
            $needsPromoCopyJs = false;
            foreach ($notices as $n) {
                // ── Audience gate ──
                $show = ($area === 'admin' && !empty($n['show_to_admins']))
                     || ($area === 'client' && !empty($n['show_to_clients']));
                if (!$show) continue;

                $assignedAdmins = array_map('intval', (array)($n['assigned_admins'] ?? []));

                // ── Client group gate ──
                $clientGroups = $n['client_groups'] ?? [];
                if ($area === 'client' && !empty($clientGroups)) {
                    if ($currentGroupId === 0 || !in_array($currentGroupId, $clientGroups, true)) {
                        continue;
                    }
                }

                // ── Page slug gate (client only) ──
                $pageSlugs = $n['page_slugs'] ?? [];
                if ($area === 'client' && !empty($pageSlugs)) {
                    $matched = false;
                    foreach ($pageSlugs as $pattern) {
                        if (fnmatch($pattern, $requestUri) || strpos($requestUri, $pattern) !== false) {
                            $matched = true;
                            break;
                        }
                    }
                    if (!$matched) continue;
                }

                // ── Specific client gate ──
                $targetClients = $n['target_clients'] ?? [];
                if ($area === 'client' && !empty($targetClients)) {
                    if ($currentClientId === 0 || !in_array($currentClientId, $targetClients, true)) {
                        continue;
                    }
                }

                // ── Specific server gate (client must have an active service on one of these servers) ──
                $targetServers = $n['target_servers'] ?? [];
                if ($area === 'client' && !empty($targetServers)) {
                    if (empty(array_intersect($targetServers, $clientServerIds))) {
                        continue;
                    }
                }

                // ── Specific product gate (client must have an active service for one of these products) ──
                $targetProducts = $n['target_products'] ?? [];
                if ($area === 'client' && !empty($targetProducts)) {
                    if (empty(array_intersect($targetProducts, $clientProductIds))) {
                        continue;
                    }
                }

                $id       = 'nb_' . $n['id'];
                $todoTaskCount = 0;
                $bg       = $n['bg_color']   ?: '#fffae6';
                $color    = $n['font_color'] ?: '#222';
                $priority = $n['priority']   ?? 'normal';
                $isPromo  = !empty($n['is_promotion_banner']);
                $promoCollapsibleUnifiedFrag = null;

                $accentMap = ['critical' => '#dc2626', 'high' => '#f97316', 'normal' => '#2563eb', 'low' => '#9ca3af'];
                $accent    = $accentMap[$priority] ?? '#2563eb';

                $title = htmlspecialchars($n['notice_title'] ?? '');
                $promoCollapsibleClient = $isPromo && $area === 'client' && !empty($n['promo_collapsible']);
                if ($isPromo) {
                    if (strpos($stylePrefix, 'nb-promo-banner-css') === false) {
                        $stylePrefix .= self::promotionBannerStyles();
                    }
                    if ($promoCollapsibleClient) {
                        $promoCollapsibleUnifiedFrag = self::buildPromotionBannerParts($n, $area, $isPro, true);
                        $content                      = '';
                    } else {
                        $content = self::renderPromotionBannerBody($n, $area, $isPro, false);
                    }
                    if (trim((string)($n['promo_coupon_code'] ?? '')) !== '') {
                        $needsPromoCopyJs = true;
                    }
                } elseif (!empty($n['is_todo_banner'])) {
                    if ($stylePrefix === '') {
                        $stylePrefix = self::todoBannerStyles();
                    }
                    if ($area === 'admin' && $currentAdminId > 0) {
                        $todoParts = self::adminTodoBannerContentAndCount((int)$n['id']);
                        $content = $todoParts['html'];
                        $todoTaskCount = $todoParts['count'];
                        $needsBannerTodoJs = true;
                    } else {
                        $content = self::parseTodoBannerBody($n['notice_content'] ?? '');
                        $todoTaskCount = self::countTodoMarkdownTasks($n['notice_content'] ?? '');
                    }
                } else {
                    $content = self::parseMarkdown($n['notice_content'] ?? '');
                }

                // ── Pinned indicator (Pro) ──
                $pinnedHtml = ($isPro && !empty($n['is_pinned']))
                    ? '<span style="display:inline-block;padding:1px 7px;border-radius:12px;font-size:10px;font-weight:700;background:#fef9c3;color:#854d0e;margin-left:6px;vertical-align:middle;">📌 Pinned</span>'
                    : '';

                // ── Timestamp ──
                $tsHtml = '';
                if (!empty($n['notice_timestamp'])) {
                    $tsHtml = '<span style="font-size:12px;opacity:0.6;margin-left:10px;font-weight:400;">'
                        . '🕐 ' . htmlspecialchars(date('M j, Y g:ia', strtotime($n['notice_timestamp'])))
                        . '</span>';
                }

                // ── Tags (Pro) ──
                $tagsHtml = '';
                if ($isPro && !empty($n['tags'])) {
                    $tagsHtml = '<div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:4px;">';
                    foreach (array_map('trim', explode(',', $n['tags'])) as $tag) {
                        if ($tag === '') continue;
                        $tagsHtml .= '<span style="display:inline-block;padding:1px 8px;border-radius:999px;font-size:11px;font-weight:600;background:rgba(99,102,241,0.1);color:#4338ca;">#' . htmlspecialchars($tag) . '</span>';
                    }
                    $tagsHtml .= '</div>';
                }

                // ── Assigned admins footer (not on To-Do banners — collapsed summary covers assignees) ──
                $assignedHtml = '';
                if ($area === 'admin' && !empty($assignedAdmins) && empty($n['is_todo_banner'])) {
                    $nameMap = self::adminNames($assignedAdmins);
                    $chips   = '';
                    foreach ($assignedAdmins as $aid) {
                        $name  = $nameMap[$aid] ?? ('Admin #' . $aid);
                        $chips .= '<span style="display:inline-flex;align-items:center;gap:3px;background:rgba(99,102,241,0.15);color:#4338ca;border-radius:999px;padding:1px 8px;font-size:11px;font-weight:600;margin:1px 2px;">'
                            . '<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'
                            . htmlspecialchars($name) . '</span>';
                    }
                    $assignedHtml = '<div style="margin-top:8px;font-size:12px;opacity:0.75;display:flex;align-items:center;flex-wrap:wrap;gap:4px;">'
                        . '<span style="font-weight:600;margin-right:2px;">Assigned:</span>' . $chips
                        . '</div>';
                }

                // ── Acknowledge (Pro) or Manage Tasks (admin To-Do banner → task manager) ──
                $ackBtn = '';
                $entityId   = ($area === 'admin') ? $currentAdminId : $currentClientId;
                $entityType = $area === 'admin' ? 'admin' : 'client';
                $isAdminTodoBanner = !empty($n['is_todo_banner']) && $area === 'admin' && $currentAdminId > 0;
                if ($isAdminTodoBanner) {
                    $todoMgrUrl = function_exists('noticebanner_admin_todo_redirect_url')
                        ? noticebanner_admin_todo_redirect_url((int)$n['id'], 'all')
                        : ('addonmodules.php?module=noticebanner&todo_banner_range=all&todo_notice_id=' . (int)$n['id'] . '#nb-todo-banners');
                    $ackBtn = '<a href="' . htmlspecialchars($todoMgrUrl, ENT_QUOTES, 'UTF-8') . '"'
                        . ' title="Manage tasks" aria-label="Manage tasks"'
                        . ' style="padding:7px;border-radius:6px;background:#312e81;color:#eef2ff;border:1px solid #1e1b4b;flex-shrink:0;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;line-height:0;box-sizing:border-box;">'
                        . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
                        . '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>'
                        . '<path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>'
                        . '</svg></a>';
                } elseif ($isPro && $entityId) {
                    $acked    = self::hasAcknowledged((int)$n['id'], $entityType, $entityId);
                    $btnId    = 'nb-ack-' . $n['id'];
                    if ($acked) {
                        $ackBtn = '<span id="' . $btnId . '" style="display:inline-flex;align-items:center;gap:4px;padding:3px 11px;border-radius:5px;background:#dcfce7;color:#166534;font-size:12px;font-weight:700;border:1px solid #bbf7d0;flex-shrink:0;white-space:nowrap;">✓ Acknowledged</span>';
                    } else {
                        $ackBtn = '<button id="' . $btnId . '" type="button"'
                            . ' onclick="nbAcknowledge(this,' . (int)$n['id'] . ',\'' . $entityType . '\',' . $entityId . ')"'
                            . ' style="padding:3px 11px;border-radius:5px;background:#e0e7ff;color:#3730a3;border:1px solid #c7d2fe;cursor:pointer;font-size:12px;font-weight:700;flex-shrink:0;white-space:nowrap;">Acknowledge</button>';
                    }
                }

                // ── CTA button (Pro) — skipped for promotion banners (CTA is inside the promo card) ──
                $btnHtml = '';
                if (!$isPromo && $isPro && !empty($n['button_enabled']) && !empty($n['button_text']) && !empty($n['button_link'])) {
                    $target  = !empty($n['button_newtab']) ? ' target="_blank" rel="noopener noreferrer"' : '';
                    $btnHtml = '<a href="' . htmlspecialchars($n['button_link']) . '"' . $target
                        . ' style="display:inline-block;margin-top:10px;padding:7px 22px;border-radius:6px;'
                        . 'background:' . htmlspecialchars($n['button_bg'] ?? '#2563eb') . ';'
                        . 'color:' . htmlspecialchars($n['button_color'] ?? '#fff') . ';'
                        . 'font-weight:600;text-decoration:none;font-size:14px;box-shadow:0 2px 6px rgba(0,0,0,0.12);">'
                        . htmlspecialchars($n['button_text']) . '</a>';
                }

                // ── Ticket button (Pro) ──
                $ticketHtml = '';
                if ($isPro && !empty($n['ticket_enabled']) && !empty($n['ticket_department_id'])) {
                    $deptId  = urlencode($n['ticket_department_id'] ?? '');
                    $subject = urlencode($n['notice_title'] ?? '');
                    $msgBody = urlencode(strip_tags($n['notice_content'] ?? ''));
                    $btnTxt  = htmlspecialchars($n['ticket_button_text'] ?: 'Create Ticket');
                    // Works for both client area (/submitticket.php) and admin area (/supporttickets.php)
                    $ticketUrl = $area === 'admin'
                        ? 'supporttickets.php?action=open&deptid=' . $deptId . '&subject=' . $subject
                        : 'submitticket.php?step=2&deptid=' . $deptId . '&subject=' . $subject . '&message=' . $msgBody;
                    $ticketHtml = '<a href="' . $ticketUrl . '"'
                        . ' style="display:inline-block;margin-top:10px;margin-left:8px;padding:7px 22px;border-radius:6px;'
                        . 'background:#10b981;color:#fff;font-weight:600;text-decoration:none;font-size:14px;box-shadow:0 2px 6px rgba(0,0,0,0.12);">'
                        . $btnTxt . '</a>';
                }

                // ── Poll (Pro) ──
                $pollHtml = '';
                if ($isPro && !empty($n['poll_enabled']) && !empty($n['poll_question']) && !empty($n['poll_options'])) {
                    $results    = $n['poll_results'] ?? [];
                    $total      = array_sum($results);
                    $pollDivId  = 'nb-poll-' . (int)$n['id'];
                    $pollNid    = (int)$n['id'];

                    // Check if this viewer already voted
                    $isAdmin      = !empty($_SESSION['adminid']);
                    $pollEntType  = $isAdmin ? 'admin' : 'client';
                    $pollEntId    = $isAdmin ? (int)($_SESSION['adminid'] ?? 0) : (int)($_SESSION['uid'] ?? 0);
                    $alreadyVoted = $pollEntId ? self::hasVoted($pollNid, $pollEntType, $pollEntId) : false;
                    $votedOption  = $alreadyVoted ? self::getVotedOption($pollNid, $pollEntType, $pollEntId) : null;

                    $pollHtml = '<div id="' . $pollDivId . '" style="margin-top:14px;padding:12px 16px;background:rgba(0,0,0,0.04);border-radius:8px;max-width:480px;">'
                        . '<div style="font-weight:600;margin-bottom:8px;">' . htmlspecialchars($n['poll_question'], ENT_NOQUOTES, 'UTF-8') . '</div>';

                    foreach ($n['poll_options'] as $opt) {
                        $votes    = $results[$opt] ?? 0;
                        $pct      = $total > 0 ? round(($votes / $total) * 100) : 0;
                        $optB64   = base64_encode($opt);
                        $optAttr  = htmlspecialchars($opt, ENT_QUOTES, 'UTF-8');
                        $optDisp  = htmlspecialchars($opt, ENT_NOQUOTES, 'UTF-8');
                        $barW     = $total > 0 ? $pct : 0;
                        $isChosen = ($alreadyVoted && $votedOption === $opt);

                        if ($alreadyVoted) {
                            // Read-only results row — highlight the chosen option
                            $chosenStyle = $isChosen ? 'font-weight:700;color:#4f46e5;' : 'opacity:0.75;';
                            $barColor    = $isChosen ? '#6366f1' : '#94a3b8';
                            $checkMark   = $isChosen ? ' <span style="color:#16a34a;font-size:13px;">✓</span>' : '';
                            $pollHtml .= '<div data-poll-row="' . $optB64 . '" style="margin-bottom:8px;font-size:14px;' . $chosenStyle . '">'
                                . '<div style="display:flex;align-items:center;gap:6px;">'
                                . '<span style="flex:1;">' . $optDisp . $checkMark . '</span>'
                                . '<span class="nb-poll-stat" style="font-size:11px;opacity:0.7;white-space:nowrap;">' . $votes . ' vote' . ($votes == 1 ? '' : 's') . ' (' . $pct . '%)</span>'
                                . '</div>'
                                . '<div style="height:4px;background:#e2e8f0;border-radius:2px;margin-top:4px;">'
                                . '<div class="nb-poll-bar" style="height:4px;background:' . $barColor . ';border-radius:2px;width:' . $barW . '%;transition:width 0.4s;"></div>'
                                . '</div>'
                                . '</div>';
                        } else {
                            // Voting form row
                            $pollHtml .= '<label data-poll-row="' . $optB64 . '" style="display:block;margin-bottom:8px;font-size:14px;cursor:pointer;">'
                                . '<div style="display:flex;align-items:center;gap:8px;">'
                                . '<input type="radio" name="nb_poll_opt_' . $pollNid . '" data-b64="' . $optB64 . '" value="' . $optAttr . '" style="margin:0;flex-shrink:0;">'
                                . '<span>' . $optDisp . '</span>'
                                . '<span class="nb-poll-stat" style="font-size:11px;opacity:0.6;margin-left:auto;white-space:nowrap;">' . $votes . ' vote' . ($votes == 1 ? '' : 's') . ' (' . $pct . '%)</span>'
                                . '</div>'
                                . '<div style="height:4px;background:#e2e8f0;border-radius:2px;margin-top:3px;margin-left:20px;">'
                                . '<div class="nb-poll-bar" style="height:4px;background:#6366f1;border-radius:2px;width:' . $barW . '%;transition:width 0.4s;"></div>'
                                . '</div>'
                                . '</label>';
                        }
                    }

                    if ($alreadyVoted && $pollEntId) {
                        // Already voted — show result summary + Change Vote button
                        $pollHtml .= '<div style="display:flex;align-items:center;gap:10px;margin-top:8px;flex-wrap:wrap;">'
                            . '<span style="font-size:12px;background:#dcfce7;color:#166534;padding:3px 10px;border-radius:12px;font-weight:600;">✓ You voted: ' . htmlspecialchars($votedOption ?? '', ENT_NOQUOTES, 'UTF-8') . '</span>'
                            . '<button type="button" id="nb-poll-change-' . $pollNid . '" '
                            . 'onclick="nbPollReset(this,' . $pollNid . ')" '
                            . 'style="padding:3px 12px;border-radius:5px;background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;cursor:pointer;font-size:12px;font-weight:600;">↺ Change Vote</button>'
                            . '<span class="nb-poll-total" style="font-size:12px;opacity:0.55;">' . $total . ' total vote' . ($total == 1 ? '' : 's') . '</span>'
                            . '</div>';
                    } else {
                        $pollHtml .= '<div style="display:flex;align-items:center;gap:10px;margin-top:8px;">'
                            . '<button type="button" onclick="nbPollVote(this,' . $pollNid . ')" '
                            . 'style="padding:5px 18px;border-radius:5px;background:#6366f1;color:#fff;font-weight:600;border:none;cursor:pointer;font-size:13px;">Vote</button>'
                            . '<span class="nb-poll-total" style="font-size:12px;opacity:0.55;">' . $total . ' total vote' . ($total == 1 ? '' : 's') . '</span>'
                            . '</div>';
                    }

                    $pollHtml .= '</div>';
                }

                $todoOuterCollapse = !empty($n['is_todo_banner']) && !$isPromo && empty($n['expandable']);

                // ── Body ──
                // Promos: full-width slot (do not squeeze card beside Ack/Dismiss — fixes client theme flex bugs)
                $bodyTop = $isPromo ? '0' : '10px';
                if ($isPromo) {
                    $promoSlotClass = 'nb-promo-client-slot' . ($area === 'client' ? ' nb-promo-client-slot--strip' : '');
                    $promoSlotMt     = ($area === 'client') ? '0' : '8px';
                    // Tags render inside .nb-promo-surface — do not append $tagsHtml here
                    if ($promoCollapsibleClient && $promoCollapsibleUnifiedFrag !== null) {
                        $bodyHtml = '';
                    } else {
                        $bodyHtml = '<div class="' . $promoSlotClass . '" style="margin-top:' . $promoSlotMt . ';font-size:14px;line-height:1.7;text-align:left;">'
                            . $content . $btnHtml . $ticketHtml . $pollHtml
                            . $assignedHtml
                            . '</div>';
                    }
                } else {
                    $bodyMt = $todoOuterCollapse ? '0' : $bodyTop;
                    $bodyHtml = '<div style="margin-top:' . $bodyMt . ';font-size:14px;line-height:1.7;max-width:880px;margin-left:auto;margin-right:auto;text-align:left;">'
                        . $content . $btnHtml . $ticketHtml . $pollHtml
                        . $tagsHtml . $assignedHtml
                        . '</div>';
                }

                // ── Banner wrapper ──
                $headerRow = $isPromo
                    ? '<div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px;">'
                        . '<span style="font-size:11px;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:#7c3aed;">Promotion</span>'
                        . $pinnedHtml
                        . $tsHtml
                        . '</div>'
                    : '<div style="display:flex;align-items:center;flex-wrap:wrap;gap:6px;">'
                        . '<span style="font-size:16px;font-weight:700;">' . $title . '</span>'
                        . (empty($n['is_todo_banner']) ? self::priorityBadge($priority) : '')
                        . $pinnedHtml
                        . $tsHtml
                        . '</div>';

                $todoMetaHtml = '';
                if ($todoOuterCollapse) {
                    $foldHints = ' · <span class="nb-todo-fold-hint-exp">Click to expand</span><span class="nb-todo-fold-hint-col">Click to collapse</span></span>';
                    if ($area === 'admin' && $currentAdminId > 0 && !empty($n['is_todo_banner']) && function_exists('noticebanner_count_incomplete_todos_for_admin')) {
                        $boardAss = $assignedAdmins;
                        $isBannerAssignee = !empty($boardAss) && in_array($currentAdminId, $boardAss, true);
                        $pendingYou = noticebanner_count_incomplete_todos_for_admin((int)$n['id'], $currentAdminId);
                        $pendingAll = function_exists('noticebanner_count_incomplete_todos_on_notice')
                            ? noticebanner_count_incomplete_todos_on_notice((int)$n['id'])
                            : (int)$todoTaskCount;
                        if ($isBannerAssignee) {
                            $line = $pendingYou === 0
                                ? 'You have no pending tasks'
                                : ('You have ' . $pendingYou . ' pending task' . ($pendingYou === 1 ? '' : 's'));
                        } else {
                            $line = $pendingAll === 0
                                ? 'No open tasks'
                                : ($pendingAll === 1 ? '1 open task' : $pendingAll . ' open tasks');
                        }
                        $todoMetaHtml = '<span class="nb-todo-banner-outer-meta"> · ' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . $foldHints;
                    } else {
                        $hint = $todoTaskCount === 0 ? 'Empty' : ($todoTaskCount === 1 ? '1 task' : $todoTaskCount . ' tasks');
                        $todoMetaHtml = '<span class="nb-todo-banner-outer-meta"> · ' . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') . $foldHints;
                    }
                }

                $dismissBtn = '<button type="button" onclick="document.getElementById(\'' . $id . '\').style.display=\'none\'" '
                    . 'style="padding:3px 10px;font-size:16px;line-height:1;border-radius:5px;border:1px solid rgba(0,0,0,0.15);background:rgba(0,0,0,0.06);cursor:pointer;flex-shrink:0;" title="Dismiss">&times;</button>';

                // Controls: Acknowledge + optional Expand + Dismiss — always top-right
                $controls = '<div style="display:flex;gap:6px;align-items:center;flex-shrink:0;flex-wrap:wrap;">'
                    . $ackBtn;

                $stripBorder = $accent;
                if (!empty($n['is_todo_banner']) && !$isPromo) {
                    $stripBorder = self::todoBannerLeftBorderColor($bg, $accent);
                }
                $bannerStyle = $isPromo
                    ? ($area === 'client'
                        ? ('color:' . $color . ';position:relative;z-index:99999;background:transparent;border:none;padding:0;margin:0;box-shadow:none;width:100%;max-width:100%;box-sizing:border-box;clear:both;overflow:visible;')
                        : ('color:' . $color . ';position:relative;z-index:99999;background:transparent;border:none;padding:10px 12px;box-shadow:none;width:100%;max-width:100%;box-sizing:border-box;clear:both;overflow:visible;'))
                    : ('background:' . $bg . ';border-left:4px solid ' . $stripBorder . ';padding:12px 20px;'
                    . 'color:' . $color . ';position:relative;z-index:99999;box-shadow:0 2px 8px rgba(0,0,0,0.06);');

                // Never hide promo body behind Expand — template card must always show on client
                $useExpandable = !empty($n['expandable']) && empty($n['is_promotion_banner']);

                $clientAttr = $area === 'client' ? ' class="nb-client-notice-bar"' : '';

                if ($useExpandable) {
                    $expandBtn = '<button type="button" onclick="(function(b,c){var open=c.style.display!==\'none\';c.style.display=open?\'none\':\'block\';b.textContent=open?\'Expand\':\'Collapse\';})(this,document.getElementById(\'' . $id . '_body\'))" '
                        . 'style="padding:3px 14px;font-size:13px;border-radius:5px;border:1px solid rgba(0,0,0,0.15);background:rgba(0,0,0,0.06);cursor:pointer;font-weight:500;">Expand</button>';
                    $controls .= $expandBtn . $dismissBtn . '</div>';
                    $html .= '<div id="' . $id . '"' . $clientAttr . ' style="' . $bannerStyle . '">'
                        . '<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">'
                        . $headerRow
                        . $controls
                        . '</div>'
                        . '<div id="' . $id . '_body" style="display:none;">' . $bodyHtml . '</div>'
                        . '</div>';
                } elseif ($isPromo) {
                    $controls .= $dismissBtn . '</div>';
                    $promoCollapsible = ($area === 'client' && !empty($n['promo_collapsible']));

                    if ($area === 'client') {
                        if ($promoCollapsible) {
                            $p        = $promoCollapsibleUnifiedFrag;
                            $bodyId   = $id . '_body';
                            $bodyDisp = 'none';
                            $expandBtn = '<button type="button" id="' . $id . '_expand_btn" class="nb-promo-expand-toggle" onclick="event.stopPropagation();nbPromoCollapseToggle(\'' . $id . '\');" '
                                . 'style="padding:5px 12px;font-size:12px;cursor:pointer;">Expand</button>';
                            $collapseHead = '<div class="nb-promo-collapse-head" id="' . $id . '_collapse_head" role="button" tabindex="0" aria-expanded="false" onclick="nbPromoCollapseToggle(\'' . $id . '\')" onkeydown="if(event.key===\'Enter\'||event.key===\' \'){event.preventDefault();nbPromoCollapseToggle(\'' . $id . '\');}">'
                                . '<span class="nb-promo-collapse-title">' . htmlspecialchars($n['notice_title'] ?? '', ENT_QUOTES, 'UTF-8') . '</span>'
                                . '<div style="display:flex;gap:6px;align-items:center;flex-shrink:0;flex-wrap:wrap;">'
                                . $expandBtn
                                . '</div></div>';
                            $metaMini = ($pinnedHtml !== '' || $tsHtml !== '')
                                ? '<div style="font-size:11px;opacity:0.9;margin-top:4px;padding:0 clamp(16px,4vw,24px);">' . $pinnedHtml . $tsHtml . '</div>'
                                : '';
                            $tplEsc = htmlspecialchars($p['tpl'], ENT_QUOTES, 'UTF-8');
                            $html .= '<div id="' . $id . '" class="nb-banner-promo-root nb-banner-promo--clientStrip nb-promo--collapsible" style="' . $bannerStyle . '">'
                                . '<div class="nb-promo-client-slot nb-promo-client-slot--strip" style="margin-top:0;font-size:14px;line-height:1.7;text-align:left;">'
                                . '<div class="nb-promo-surface nb-promo-' . $tplEsc . ' nb-promo--collapsible-unified"' . $p['surfaceStyle'] . '>'
                                . $p['ribbon']
                                . $collapseHead
                                . '<div id="' . $bodyId . '" class="nb-promo-expanded-panel" style="display:' . $bodyDisp . ';">'
                                . $p['belowHead'] . $btnHtml . $ticketHtml . $pollHtml . $assignedHtml
                                . '</div></div>'
                                . $metaMini
                                . '</div></div>';
                        } else {
                            // Single full-width strip: no separate "Promotion" header row (avoids double-band look)
                            $promoTopBar = '<div style="position:absolute;top:10px;right:12px;z-index:30;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">'
                                . $ackBtn . $dismissBtn . '</div>';
                            $promoMeta = ($pinnedHtml !== '' || $tsHtml !== '')
                                ? '<div style="position:absolute;top:10px;left:14px;z-index:29;display:flex;flex-wrap:wrap;gap:6px;align-items:center;max-width:52%;">'
                                . $pinnedHtml . $tsHtml . '</div>'
                                : '';
                            $html .= '<div id="' . $id . '" class="nb-banner-promo-root nb-banner-promo--clientStrip" style="' . $bannerStyle . '">'
                                . '<div style="position:relative;width:100%;min-height:48px;">'
                                . $promoTopBar
                                . $promoMeta
                                . $bodyHtml
                                . '</div></div>';
                        }
                    } else {
                        $html .= '<div id="' . $id . '" class="nb-banner-promo-root" style="' . $bannerStyle . '">'
                            . '<div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:8px;width:100%;box-sizing:border-box;">'
                            . '<div style="flex:1;min-width:200px;">' . $headerRow . '</div>'
                            . $controls
                            . '</div>'
                            . $bodyHtml
                            . '</div>';
                    }
                } else {
                    $controls .= $dismissBtn . '</div>';
                    if ($todoOuterCollapse) {
                        $todoDetailsClass = ($area === 'client' ? 'nb-client-notice-bar ' : '') . 'nb-todo-banner-outer';
                        $html .= '<details id="' . $id . '" class="' . htmlspecialchars($todoDetailsClass, ENT_QUOTES, 'UTF-8') . '" style="' . $bannerStyle . '">'
                            . '<summary class="nb-todo-banner-outer-sum">'
                            . '<div style="flex:1;min-width:0;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:8px;box-sizing:border-box;">'
                            . '<div style="flex:1;min-width:0;display:flex;flex-wrap:wrap;align-items:center;gap:6px;">'
                            . $headerRow . $todoMetaHtml
                            . '</div>'
                            . $controls
                            . '</div>'
                            . '</summary>'
                            . '<div class="nb-todo-banner-outer-body">' . $bodyHtml . '</div>'
                            . '</details>';
                    } else {
                        $html .= '<div id="' . $id . '"' . $clientAttr . ' style="' . $bannerStyle . '">'
                            . '<div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:8px;">'
                            . '<div style="flex:1;min-width:0;">'
                            . $headerRow
                            . $bodyHtml
                            . '</div>'
                            . $controls
                            . '</div>'
                            . '</div>';
                    }
                }
            }

            if ($html !== '') {
                // Inject JS helpers (acknowledge + poll vote) once per page
                $html .= '<script>
if(typeof nbPromoCollapseToggle==="undefined"){
function nbPromoCollapseToggle(prefix){
var body=document.getElementById(prefix+"_body");
var btn=document.getElementById(prefix+"_expand_btn");
var head=document.getElementById(prefix+"_collapse_head");
if(!body)return;
var wasOpen=body.style.display!=="none"&&body.style.display!=="";
body.style.display=wasOpen?"none":"block";
if(btn)btn.textContent=wasOpen?"Expand":"Collapse";
if(head)head.setAttribute("aria-expanded",wasOpen?"false":"true");
}
}
if(typeof nbAcknowledge==="undefined"){
function nbAcknowledge(btn,noticeId,entityType,entityId){
    btn.disabled=true;
    btn.textContent="Saving\u2026";
    var fd=new FormData();
    fd.append("nb_acknowledge","1");
    fd.append("mark_read_id",noticeId);
    fd.append("mark_read_type",entityType);
    fd.append("mark_read_entity",entityId);
    fetch(window.location.href,{method:"POST",body:fd,credentials:"same-origin",headers:{"X-Requested-With":"XMLHttpRequest"}})
        .then(function(r){return r.json();})
        .then(function(data){
            if(data && data.ok){
                btn.outerHTML=\'<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 11px;border-radius:5px;background:#dcfce7;color:#166534;font-size:12px;font-weight:700;border:1px solid #bbf7d0;flex-shrink:0;white-space:nowrap;">\u2713 Acknowledged</span>\';
            } else {
                btn.disabled=false;
                btn.textContent="Acknowledge";
            }
        })
        .catch(function(){
            btn.disabled=false;
            btn.textContent="Acknowledge";
        });
}
}
if(typeof nbPollVote==="undefined"){
// Rebuild the poll wrap to show voted/unvoted state from server data
function nbPollApplyResults(wrap,noticeId,results,total,votedOption){
    var rows=wrap.querySelectorAll("[data-poll-row]");
    rows.forEach(function(row){
        var b64=row.getAttribute("data-poll-row");
        var key=b64?atob(b64):row.getAttribute("data-poll-key");
        var cnt=results[key]||0;
        var pct=total>0?Math.round(cnt/total*100):0;
        var stat=row.querySelector(".nb-poll-stat");
        var bar=row.querySelector(".nb-poll-bar");
        if(stat)stat.textContent=cnt+" vote"+(cnt===1?"":"s")+" ("+pct+"%)";
        if(bar)bar.style.width=pct+"%";
        // Highlight chosen
        if(votedOption!==undefined){
            var isChosen=(key===votedOption);
            row.style.fontWeight=isChosen?"700":"";
            row.style.opacity=isChosen?"1":"0.75";
            row.style.color=isChosen?"#4f46e5":"";
            if(bar)bar.style.background=isChosen?"#6366f1":"#94a3b8";
        }
    });
    var tot=wrap.querySelector(".nb-poll-total");
    if(tot)tot.textContent=total+" total vote"+(total===1?"":"s");
}
function nbPollVote(btn,noticeId){
    var wrap=document.getElementById("nb-poll-"+noticeId);
    if(!wrap)return;
    var sel=wrap.querySelector("input[type=radio][name=\'nb_poll_opt_"+noticeId+"\']:checked");
    if(!sel){alert("Please select an option first.");return;}
    btn.disabled=true;
    btn.textContent="Submitting\u2026";
    var fd=new FormData();
    fd.append("nb_poll_vote","1");
    fd.append("poll_notice_id",noticeId);
    var raw=sel.getAttribute("data-b64");
    var vote=raw?atob(raw):sel.value;
    fd.append("poll_vote",vote);
    fetch(window.location.href,{method:"POST",body:fd,credentials:"same-origin",headers:{"X-Requested-With":"XMLHttpRequest"}})
        .then(function(r){return r.json();})
        .then(function(data){
            if(data&&data.ok){
                // Disable all radios, update bars
                wrap.querySelectorAll("input[type=radio]").forEach(function(i){i.disabled=true;});
                nbPollApplyResults(wrap,noticeId,data.results,data.total,data.voted_option);
                // Swap Vote button for "You voted + Change Vote"
                var btnWrap=btn.parentNode;
                btnWrap.innerHTML=\'<span style="font-size:12px;background:#dcfce7;color:#166534;padding:3px 10px;border-radius:12px;font-weight:600;">\u2713 You voted: \'+data.voted_option+\'</span>\'
                    +\'<button type="button" onclick="nbPollReset(this,\'+noticeId+\')" style="padding:3px 12px;border-radius:5px;background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;cursor:pointer;font-size:12px;font-weight:600;">\u21ba Change Vote</button>\'
                    +\'<span class="nb-poll-total" style="font-size:12px;opacity:0.55;">\'+data.total+\' total vote\'+(data.total===1?"":"s")+\'</span>\';
            } else if(data&&data.auth_required){
                alert("Please log in to vote.");
                btn.disabled=false;
                btn.textContent="Vote";
            } else if(data&&data.already_voted){
                // Already voted — show current state
                wrap.querySelectorAll("input[type=radio]").forEach(function(i){i.disabled=true;});
                nbPollApplyResults(wrap,noticeId,data.results,data.total,data.voted_option);
                var btnWrap=btn.parentNode;
                btnWrap.innerHTML=\'<span style="font-size:12px;background:#fef9c3;color:#92400e;padding:3px 10px;border-radius:12px;font-weight:600;">You already voted: \'+data.voted_option+\'</span>\'
                    +\'<button type="button" onclick="nbPollReset(this,\'+noticeId+\')" style="padding:3px 12px;border-radius:5px;background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;cursor:pointer;font-size:12px;font-weight:600;">\u21ba Change Vote</button>\'
                    +\'<span class="nb-poll-total" style="font-size:12px;opacity:0.55;">\'+data.total+\' total vote\'+(data.total===1?"":"s")+\'</span>\';
            } else {
                btn.disabled=false;
                btn.textContent="Vote";
            }
        })
        .catch(function(){
            btn.disabled=false;
            btn.textContent="Vote";
        });
}
function nbPollReset(btn,noticeId){
    btn.disabled=true;
    btn.textContent="Resetting\u2026";
    var fd=new FormData();
    fd.append("nb_poll_reset_vote","1");
    fd.append("poll_notice_id",noticeId);
    fetch(window.location.href,{method:"POST",body:fd,credentials:"same-origin",headers:{"X-Requested-With":"XMLHttpRequest"}})
        .then(function(r){return r.json();})
        .then(function(data){
            if(data&&data.ok&&data.reset){
                // Reload the poll widget to show fresh voting form
                // Simplest reliable approach: reload the page
                window.location.reload();
            } else {
                btn.disabled=false;
                btn.textContent="\u21ba Change Vote";
            }
        })
        .catch(function(){
            btn.disabled=false;
            btn.textContent="\u21ba Change Vote";
        });
}
}
</script>';
            }

            $bannerTodoScript = $needsBannerTodoJs ? self::bannerTodoToggleScript() : '';
            $promoCopyScript  = !empty($needsPromoCopyJs) ? self::promotionCopyScript() : '';

            $out = $stylePrefix . $html . $bannerTodoScript . $promoCopyScript;
            if ($area === 'client' && $html !== '') {
                $out = self::clientAreaBleedStyles()
                    . '<div class="nb-noticebanner-bleed" role="region" aria-label="Announcements">'
                    . $stylePrefix . $html . $bannerTodoScript . $promoCopyScript
                    . '</div>';
            }

            return $out;
        }
    }
}
