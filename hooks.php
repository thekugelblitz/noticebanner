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

            $currentAdminId  = ($area === 'admin')  ? self::currentAdminId()       : 0;
            $currentClientId = ($area === 'client') ? self::currentClientId()      : 0;
            $currentGroupId  = ($area === 'client') ? self::currentClientGroupId() : 0;
            $requestUri      = $_SERVER['REQUEST_URI'] ?? '';

            $html = '';
            foreach ($notices as $n) {
                // ── Audience gate ──
                $show = ($area === 'admin' && !empty($n['show_to_admins']))
                     || ($area === 'client' && !empty($n['show_to_clients']));
                if (!$show) continue;

                // ── Assigned-admin gate ──
                $assignedAdmins = $n['assigned_admins'] ?? [];
                if ($area === 'admin' && !empty($assignedAdmins)) {
                    if ($currentAdminId === 0 || !in_array($currentAdminId, $assignedAdmins, true)) {
                        continue;
                    }
                }

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

                $id       = 'nb_' . $n['id'];
                $bg       = $n['bg_color']   ?: '#fffae6';
                $color    = $n['font_color'] ?: '#222';
                $priority = $n['priority']   ?? 'normal';

                $accentMap = ['critical' => '#dc2626', 'high' => '#f97316', 'normal' => '#2563eb', 'low' => '#9ca3af'];
                $accent    = $accentMap[$priority] ?? '#2563eb';

                $title   = htmlspecialchars($n['notice_title'] ?? '');
                $content = self::parseMarkdown($n['notice_content'] ?? '');

                // ── Pinned indicator ──
                $pinnedHtml = !empty($n['is_pinned'])
                    ? '<span style="display:inline-block;padding:1px 7px;border-radius:12px;font-size:10px;font-weight:700;background:#fef9c3;color:#854d0e;margin-left:6px;vertical-align:middle;">📌 Pinned</span>'
                    : '';

                // ── Timestamp ──
                $tsHtml = '';
                if (!empty($n['notice_timestamp'])) {
                    $tsHtml = '<span style="font-size:12px;opacity:0.6;margin-left:10px;font-weight:400;">'
                        . '🕐 ' . htmlspecialchars(date('M j, Y g:ia', strtotime($n['notice_timestamp'])))
                        . '</span>';
                }

                // ── Tags ──
                $tagsHtml = '';
                if (!empty($n['tags'])) {
                    $tagsHtml = '<div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:4px;">';
                    foreach (array_map('trim', explode(',', $n['tags'])) as $tag) {
                        if ($tag === '') continue;
                        $tagsHtml .= '<span style="display:inline-block;padding:1px 8px;border-radius:999px;font-size:11px;font-weight:600;background:rgba(99,102,241,0.1);color:#4338ca;">#' . htmlspecialchars($tag) . '</span>';
                    }
                    $tagsHtml .= '</div>';
                }

                // ── Assigned admins footer ──
                $assignedHtml = '';
                if ($area === 'admin' && !empty($assignedAdmins)) {
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

                // ── Acknowledge button ──
                $ackHtml = '';
                $entityId   = ($area === 'admin') ? $currentAdminId : $currentClientId;
                $entityType = $area === 'admin' ? 'admin' : 'client';
                if ($entityId && function_exists('noticebanner_ensure_columns')) {
                    $acked = self::hasAcknowledged((int)$n['id'], $entityType, $entityId);
                    if ($acked) {
                        $ackHtml = '<span style="display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:6px;background:#dcfce7;color:#166534;font-size:13px;font-weight:600;margin-top:8px;">✓ Acknowledged</span>';
                    } else {
                        $ackHtml = '<form method="post" action="" style="display:inline;margin-top:8px;">'
                            . '<input type="hidden" name="mark_read" value="1">'
                            . '<input type="hidden" name="mark_read_id" value="' . (int)$n['id'] . '">'
                            . '<input type="hidden" name="mark_read_type" value="' . $entityType . '">'
                            . '<input type="hidden" name="mark_read_entity" value="' . $entityId . '">'
                            . '<button type="submit" style="padding:4px 14px;border-radius:6px;background:#e0e7ff;color:#3730a3;border:none;cursor:pointer;font-size:13px;font-weight:600;">Acknowledge</button>'
                            . '</form>';
                    }
                }

                // ── CTA button ──
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

                // ── Ticket button (client only) ──
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

                // ── Poll ──
                $pollHtml = '';
                if (!empty($n['poll_enabled']) && !empty($n['poll_question']) && !empty($n['poll_options'])) {
                    $results  = $n['poll_results'] ?? [];
                    $total    = array_sum($results);
                    $pollHtml = '<div style="margin-top:14px;padding:12px 16px;background:rgba(0,0,0,0.04);border-radius:8px;max-width:480px;">'
                        . '<div style="font-weight:600;margin-bottom:8px;">' . htmlspecialchars($n['poll_question']) . '</div>'
                        . '<form method="post" action="">'
                        . '<input type="hidden" name="poll_notice_id" value="' . (int)$n['id'] . '">';
                    foreach ($n['poll_options'] as $opt) {
                        $votes    = $results[$opt] ?? 0;
                        $pct      = $total > 0 ? round(($votes / $total) * 100) : 0;
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

                // ── Body ──
                $bodyHtml = '<div style="margin-top:10px;font-size:14px;line-height:1.7;max-width:800px;margin-left:auto;margin-right:auto;text-align:left;">'
                    . $content . $btnHtml . $ticketHtml . $pollHtml
                    . $tagsHtml . $assignedHtml
                    . '<div>' . $ackHtml . '</div>'
                    . '</div>';

                // ── Banner wrapper ──
                $headerRow = '<div style="display:flex;align-items:center;flex-wrap:wrap;gap:6px;">'
                    . '<span style="font-size:16px;font-weight:700;">' . $title . '</span>'
                    . self::priorityBadge($priority)
                    . $pinnedHtml
                    . $tsHtml
                    . '</div>';

                $dismissBtn = '<button type="button" onclick="document.getElementById(\'' . $id . '\').style.display=\'none\'" '
                    . 'style="padding:3px 10px;font-size:16px;line-height:1;border-radius:5px;border:1px solid rgba(0,0,0,0.15);background:rgba(0,0,0,0.06);cursor:pointer;flex-shrink:0;" title="Dismiss">&times;</button>';

                $bannerStyle = 'background:' . $bg . ';border-left:4px solid ' . $accent . ';padding:12px 20px;'
                    . 'color:' . $color . ';position:relative;z-index:99999;box-shadow:0 2px 8px rgba(0,0,0,0.06);';

                if (!empty($n['expandable'])) {
                    $expandBtn = '<button type="button" onclick="(function(b,c){var open=c.style.display!==\'none\';c.style.display=open?\'none\':\'block\';b.textContent=open?\'Expand\':\'Collapse\';})(this,document.getElementById(\'' . $id . '_body\'))" '
                        . 'style="padding:3px 14px;font-size:13px;border-radius:5px;border:1px solid rgba(0,0,0,0.15);background:rgba(0,0,0,0.06);cursor:pointer;font-weight:500;">Expand</button>';
                    $html .= '<div id="' . $id . '" style="' . $bannerStyle . '">'
                        . '<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">'
                        . $headerRow
                        . '<div style="display:flex;gap:8px;align-items:center;">' . $expandBtn . $dismissBtn . '</div>'
                        . '</div>'
                        . '<div id="' . $id . '_body" style="display:none;">' . $bodyHtml . '</div>'
                        . '</div>';
                } else {
                    $html .= '<div id="' . $id . '" style="' . $bannerStyle . '">'
                        . '<div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:8px;">'
                        . '<div style="flex:1;min-width:0;">'
                        . $headerRow
                        . $bodyHtml
                        . '</div>'
                        . $dismissBtn
                        . '</div>'
                        . '</div>';
                }
            }

            return $html;
        }
    }
}
