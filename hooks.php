<?php
if (!defined('WHMCS')) {
    die('Access Denied');
}

require_once __DIR__ . '/noticebanner.php';
require_once __DIR__ . '/widget.php';

// ─── Hook registrations ───────────────────────────────────────────────────────

add_hook('ClientAreaHeaderOutput', 1, function ($vars) {
    return NoticeBannerHelper::renderNotices('client');
});

add_hook('AdminAreaHeaderOutput', 1, function ($vars) {
    return NoticeBannerHelper::renderNotices('admin');
});

add_hook('AdminAreaHeadOutput', 1, function ($vars) {
    return NoticeBannerHelper::renderNotices('admin');
});

add_hook('AdminAreaFooterOutput', 1, function ($vars) {
    return NoticeBannerHelper::renderNotices('admin');
});

// ─── Renderer ────────────────────────────────────────────────────────────────

if (!class_exists('NoticeBannerHelper')) {
    class NoticeBannerHelper {

        private static $rendered = [];

        // ── Minimal Markdown → HTML ──────────────────────────────────────────
        public static function parseMarkdown(string $text): string {
            // Escape HTML first, then selectively apply markdown
            $t = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

            // Headings
            $t = preg_replace('/^### (.+)$/m', '<h5 style="margin:8px 0 4px;">$1</h5>', $t);
            $t = preg_replace('/^## (.+)$/m',  '<h4 style="margin:10px 0 4px;">$1</h4>', $t);
            $t = preg_replace('/^# (.+)$/m',   '<h3 style="margin:12px 0 4px;">$1</h3>', $t);

            // Bold / italic
            $t = preg_replace('/\*\*\*(.+?)\*\*\*/s', '<strong><em>$1</em></strong>', $t);
            $t = preg_replace('/\*\*(.+?)\*\*/s',     '<strong>$1</strong>', $t);
            $t = preg_replace('/\*(.+?)\*/s',          '<em>$1</em>', $t);
            $t = preg_replace('/__(.+?)__/s',          '<strong>$1</strong>', $t);
            $t = preg_replace('/_(.+?)_/s',            '<em>$1</em>', $t);

            // Inline code
            $t = preg_replace('/`(.+?)`/', '<code style="background:rgba(0,0,0,0.08);padding:1px 5px;border-radius:3px;font-size:0.9em;">$1</code>', $t);

            // Links  [text](url)
            $t = preg_replace(
                '/\[([^\]]+)\]\((https?:\/\/[^\)]+)\)/',
                '<a href="$2" target="_blank" rel="noopener noreferrer" style="text-decoration:underline;">$1</a>',
                $t
            );

            // Unordered lists  (lines starting with - or *)
            $t = preg_replace_callback('/(?:^[-*] .+\n?)+/m', function ($m) {
                $items = preg_split('/\n/', trim($m[0]));
                $li = '';
                foreach ($items as $item) {
                    $item = preg_replace('/^[-*] /', '', $item);
                    $li  .= '<li>' . $item . '</li>';
                }
                return '<ul style="margin:6px 0 6px 18px;padding:0;">' . $li . '</ul>';
            }, $t);

            // Ordered lists
            $t = preg_replace_callback('/(?:^\d+\. .+\n?)+/m', function ($m) {
                $items = preg_split('/\n/', trim($m[0]));
                $li = '';
                foreach ($items as $item) {
                    $item = preg_replace('/^\d+\. /', '', $item);
                    $li  .= '<li>' . $item . '</li>';
                }
                return '<ol style="margin:6px 0 6px 18px;padding:0;">' . $li . '</ol>';
            }, $t);

            // Blockquote
            $t = preg_replace('/^&gt; (.+)$/m', '<blockquote style="border-left:3px solid #ccc;margin:6px 0;padding:2px 10px;color:#555;">$1</blockquote>', $t);

            // Horizontal rule
            $t = preg_replace('/^---+$/m', '<hr style="border:none;border-top:1px solid #ddd;margin:10px 0;">', $t);

            // Line breaks
            $t = nl2br($t);

            // @mention highlight  (already-escaped so @word)
            $t = preg_replace('/@(\w+)/', '<span style="background:rgba(99,102,241,0.15);color:#4f46e5;border-radius:3px;padding:0 3px;font-weight:600;">@$1</span>', $t);

            return $t;
        }

        // ── Priority badge ───────────────────────────────────────────────────
        private static function priorityBadge(string $priority): string {
            $map = [
                'critical' => ['#dc2626', '#fff',    '🔴 Critical'],
                'high'     => ['#f97316', '#fff',    '🟠 High'],
                'normal'   => ['#2563eb', '#fff',    '🔵 Normal'],
                'low'      => ['#6b7280', '#fff',    '⚪ Low'],
            ];
            [$bg, $fg, $label] = $map[$priority] ?? $map['normal'];
            return '<span style="display:inline-block;padding:1px 8px;border-radius:12px;font-size:11px;font-weight:700;background:' . $bg . ';color:' . $fg . ';margin-left:8px;vertical-align:middle;">' . $label . '</span>';
        }

        // ── Main render ──────────────────────────────────────────────────────
        public static function renderNotices(string $area): string {
            if (!empty(self::$rendered[$area])) return '';
            self::$rendered[$area] = true;

            $notices = function_exists('noticebanner_get_notices') ? noticebanner_get_notices() : [];
            if (empty($notices)) return '';

            $html = '';
            foreach ($notices as $n) {
                $show = ($area === 'admin' && !empty($n['show_to_admins']))
                     || ($area === 'client' && !empty($n['show_to_clients']));
                if (!$show) continue;

                $id       = 'nb_' . $n['id'];
                $bg       = $n['bg_color']   ?: '#fffae6';
                $color    = $n['font_color'] ?: '#222';
                $priority = $n['priority']   ?? 'normal';

                // Left accent colour based on priority
                $accentMap = ['critical' => '#dc2626', 'high' => '#f97316', 'normal' => '#2563eb', 'low' => '#9ca3af'];
                $accent    = $accentMap[$priority] ?? '#2563eb';

                $title   = htmlspecialchars($n['notice_title'] ?? '');
                $content = self::parseMarkdown($n['notice_content'] ?? '');

                // Timestamp
                $tsHtml = '';
                if (!empty($n['notice_timestamp'])) {
                    $tsHtml = '<span style="font-size:12px;opacity:0.65;margin-left:10px;">'
                        . htmlspecialchars(date('M j, Y g:ia', strtotime($n['notice_timestamp'])))
                        . '</span>';
                }

                // CTA button
                $btnHtml = '';
                if (!empty($n['button_enabled']) && !empty($n['button_text']) && !empty($n['button_link'])) {
                    $target  = !empty($n['button_newtab']) ? ' target="_blank" rel="noopener noreferrer"' : '';
                    $btnHtml = '<a href="' . htmlspecialchars($n['button_link']) . '"' . $target
                        . ' style="display:inline-block;margin-top:10px;padding:7px 22px;border-radius:6px;'
                        . 'background:' . htmlspecialchars($n['button_bg'] ?? '#2563eb') . ';'
                        . 'color:' . htmlspecialchars($n['button_color'] ?? '#fff') . ';'
                        . 'font-weight:600;text-decoration:none;font-size:14px;box-shadow:0 2px 6px rgba(0,0,0,0.12);">'
                        . htmlspecialchars($n['button_text']) . '</a>';
                }

                // Ticket button (client only)
                $ticketHtml = '';
                if (!empty($n['ticket_enabled']) && $area === 'client') {
                    $deptId  = urlencode($n['ticket_department_id'] ?? '');
                    $subject = urlencode($n['notice_title'] ?? '');
                    $body    = urlencode(strip_tags($n['notice_content'] ?? ''));
                    $btnTxt  = htmlspecialchars($n['ticket_button_text'] ?: 'Create Ticket');
                    $ticketHtml = '<a href="/submitticket.php?step=2&deptid=' . $deptId . '&subject=' . $subject . '&message=' . $body . '"'
                        . ' style="display:inline-block;margin-top:10px;margin-left:8px;padding:7px 22px;border-radius:6px;'
                        . 'background:#10b981;color:#fff;font-weight:600;text-decoration:none;font-size:14px;box-shadow:0 2px 6px rgba(0,0,0,0.12);">'
                        . $btnTxt . '</a>';
                }

                // Poll
                $pollHtml = '';
                if (!empty($n['poll_enabled']) && !empty($n['poll_question']) && !empty($n['poll_options'])) {
                    $pollId  = 'nb_poll_' . $n['id'];
                    $results = $n['poll_results'] ?? [];
                    $total   = array_sum($results);
                    $pollHtml = '<div style="margin-top:14px;padding:12px 16px;background:rgba(0,0,0,0.04);border-radius:8px;max-width:480px;">'
                        . '<div style="font-weight:600;margin-bottom:8px;">' . htmlspecialchars($n['poll_question']) . '</div>'
                        . '<form method="post" action="">'
                        . '<input type="hidden" name="poll_notice_id" value="' . (int)$n['id'] . '">';
                    foreach ($n['poll_options'] as $opt) {
                        $votes   = $results[$opt] ?? 0;
                        $pct     = $total > 0 ? round(($votes / $total) * 100) : 0;
                        $pollHtml .= '<label style="display:block;margin-bottom:6px;font-size:14px;">'
                            . '<input type="radio" name="poll_vote" value="' . htmlspecialchars($opt) . '" style="margin-right:6px;">'
                            . htmlspecialchars($opt)
                            . ' <span style="font-size:12px;opacity:0.6;">(' . $votes . ' vote' . ($votes == 1 ? '' : 's') . ', ' . $pct . '%)</span>'
                            . '</label>';
                    }
                    $pollHtml .= '<button type="submit" style="margin-top:6px;padding:5px 16px;border-radius:5px;background:#6366f1;color:#fff;font-weight:600;border:none;cursor:pointer;font-size:13px;">Vote</button>'
                        . '<span style="font-size:12px;opacity:0.55;margin-left:10px;">' . $total . ' total vote' . ($total == 1 ? '' : 's') . '</span>'
                        . '</form></div>';
                }

                $bodyHtml = '<div style="margin-top:10px;font-size:14px;line-height:1.7;max-width:800px;margin-left:auto;margin-right:auto;text-align:left;">'
                    . $content . $btnHtml . $ticketHtml . $pollHtml . '</div>';

                if (!empty($n['expandable'])) {
                    $html .= '<div id="' . $id . '" style="background:' . $bg . ';border-left:4px solid ' . $accent . ';padding:12px 20px;color:' . $color . ';position:relative;z-index:99999;box-shadow:0 2px 8px rgba(0,0,0,0.06);">'
                        . '<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">'
                        . '<div style="display:flex;align-items:center;flex-wrap:wrap;gap:6px;">'
                        . '<span style="font-size:16px;font-weight:700;">' . $title . '</span>'
                        . self::priorityBadge($priority)
                        . $tsHtml
                        . '</div>'
                        . '<div style="display:flex;gap:8px;align-items:center;">'
                        . '<button type="button" onclick="(function(b,c){var open=c.style.display!==\'none\';c.style.display=open?\'none\':\'block\';b.textContent=open?\'Expand\':\'Collapse\';})(this,document.getElementById(\'' . $id . '_body\'))" style="padding:3px 14px;font-size:13px;border-radius:5px;border:1px solid rgba(0,0,0,0.15);background:rgba(0,0,0,0.06);cursor:pointer;font-weight:500;">Expand</button>'
                        . '<button type="button" onclick="document.getElementById(\'' . $id . '\').style.display=\'none\'" style="padding:3px 10px;font-size:16px;line-height:1;border-radius:5px;border:1px solid rgba(0,0,0,0.15);background:rgba(0,0,0,0.06);cursor:pointer;" title="Dismiss">&times;</button>'
                        . '</div></div>'
                        . '<div id="' . $id . '_body" style="display:none;">' . $bodyHtml . '</div>'
                        . '</div>';
                } else {
                    $html .= '<div id="' . $id . '" style="background:' . $bg . ';border-left:4px solid ' . $accent . ';padding:12px 20px;color:' . $color . ';position:relative;z-index:99999;box-shadow:0 2px 8px rgba(0,0,0,0.06);">'
                        . '<div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:8px;">'
                        . '<div style="flex:1;min-width:0;">'
                        . '<div style="display:flex;align-items:center;flex-wrap:wrap;gap:6px;margin-bottom:6px;">'
                        . '<span style="font-size:16px;font-weight:700;">' . $title . '</span>'
                        . self::priorityBadge($priority)
                        . $tsHtml
                        . '</div>'
                        . $bodyHtml
                        . '</div>'
                        . '<button type="button" onclick="document.getElementById(\'' . $id . '\').style.display=\'none\'" style="padding:3px 10px;font-size:16px;line-height:1;border-radius:5px;border:1px solid rgba(0,0,0,0.15);background:rgba(0,0,0,0.06);cursor:pointer;flex-shrink:0;" title="Dismiss">&times;</button>'
                        . '</div>'
                        . '</div>';
                }
            }

            return $html;
        }
    }
}
