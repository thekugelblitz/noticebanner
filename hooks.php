<?php
if (!defined('WHMCS')) {
    die('Access Denied');
}

require_once __DIR__ . '/noticebanner.php';
require_once __DIR__ . '/widget.php';

// ─── Poll vote + Acknowledge endpoints — intercept POST on ANY page ──────────
add_hook('ClientAreaPage', 1, function ($vars) {
    NoticeBannerHelper::handleAcknowledgePost('client');
    NoticeBannerHelper::handlePollVotePost();
});
add_hook('AdminAreaPage', 1, function ($vars) {
    NoticeBannerHelper::handleAcknowledgePost('admin');
    NoticeBannerHelper::handlePollVotePost();
});

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

                    // Block duplicate votes — return current state so JS can show it
                    if ($entityId && self::hasVoted($nid, $entityType, $entityId)) {
                        $existing = self::getVotedOption($nid, $entityType, $entityId);
                        $nrow     = \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $nid)->first();
                        $results  = $nrow ? (json_decode($nrow->poll_results ?? '{}', true) ?: []) : [];
                        $total    = array_sum($results);
                        header('Content-Type: application/json');
                        echo json_encode(['ok' => false, 'already_voted' => true, 'voted_option' => $existing, 'results' => $results, 'total' => $total]);
                        exit;
                    }

                    $row = \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $nid)->first();
                    if ($row) {
                        $results        = json_decode($row->poll_results ?? '{}', true) ?: [];
                        $results[$vote] = ($results[$vote] ?? 0) + 1;
                        \WHMCS\Database\Capsule::table('mod_noticebanner')->where('id', $nid)
                            ->update(['poll_results' => json_encode($results), 'updated_at' => date('Y-m-d H:i:s')]);

                        // Cache display label
                        $label = '';
                        if ($entityId) {
                            try {
                                if ($isAdmin) {
                                    $u = \WHMCS\Database\Capsule::table('tbladmins')->where('id', $entityId)->first(['firstname', 'lastname', 'username']);
                                    if ($u) $label = trim($u->firstname . ' ' . $u->lastname) . ' (@' . $u->username . ')';
                                } else {
                                    $u = \WHMCS\Database\Capsule::table('tblclients')->where('id', $entityId)->first(['firstname', 'lastname', 'email']);
                                    if ($u) $label = trim($u->firstname . ' ' . $u->lastname) . ' (' . $u->email . ')';
                                }
                            } catch (\Exception $e) {}
                        }
                        \WHMCS\Database\Capsule::table('mod_noticebanner_poll_votes')->insert([
                            'notice_id'     => $nid,
                            'entity_type'   => $entityType,
                            'entity_id'     => $entityId,
                            'entity_label'  => $label,
                            'poll_option'   => $vote,
                            'is_predefined' => 0,
                            'voted_at'      => date('Y-m-d H:i:s'),
                        ]);

                        noticebanner_log($nid, 'poll_vote', "$entityType #$entityId voted: $vote");
                        $total = array_sum($results);
                        header('Content-Type: application/json');
                        echo json_encode(['ok' => true, 'results' => $results, 'total' => $total, 'voted_option' => $vote]);
                        exit;
                    }
                } catch (\Exception $e) {}
            }

            header('Content-Type: application/json');
            echo json_encode(['ok' => false]);
            exit;
        }

        // ── Handle acknowledge POST on any page (called from hook, exits with JSON) ──
        public static function handleAcknowledgePost(string $area): void {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
            if (empty($_POST['nb_acknowledge'])) return;

            $nid  = (int)($_POST['mark_read_id']     ?? 0);
            $type = $_POST['mark_read_type']          ?? $area;
            $eid  = (int)($_POST['mark_read_entity']  ?? 0);

            // Resolve entity ID from session if not provided
            if (!$eid) {
                $eid = $area === 'admin'
                    ? (int)($_SESSION['adminid'] ?? 0)
                    : (int)($_SESSION['uid']     ?? 0);
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

            $currentAdminId      = ($area === 'admin')  ? self::currentAdminId()          : 0;
            $currentClientId     = ($area === 'client') ? self::currentClientId()         : 0;
            $currentGroupId      = ($area === 'client') ? self::currentClientGroupId()    : 0;
            $clientServerIds     = ($area === 'client') ? self::currentClientServerIds()  : [];
            $clientProductIds    = ($area === 'client') ? self::currentClientProductIds() : [];
            $requestUri          = $_SERVER['REQUEST_URI'] ?? '';

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

                // ── Acknowledge button — uses AJAX so no page reload needed ──
                $ackBtn = '';
                $entityId   = ($area === 'admin') ? $currentAdminId : $currentClientId;
                $entityType = $area === 'admin' ? 'admin' : 'client';
                if ($entityId) {
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

                // ── Ticket button ──
                $ticketHtml = '';
                if (!empty($n['ticket_enabled']) && !empty($n['ticket_department_id'])) {
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

                // ── Poll ──
                $pollHtml = '';
                if (!empty($n['poll_enabled']) && !empty($n['poll_question']) && !empty($n['poll_options'])) {
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

                // ── Body ──
                $bodyHtml = '<div style="margin-top:10px;font-size:14px;line-height:1.7;max-width:800px;margin-left:auto;margin-right:auto;text-align:left;">'
                    . $content . $btnHtml . $ticketHtml . $pollHtml
                    . $tagsHtml . $assignedHtml
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

                // Controls: Acknowledge + optional Expand + Dismiss — always top-right
                $controls = '<div style="display:flex;gap:6px;align-items:center;flex-shrink:0;flex-wrap:wrap;">'
                    . $ackBtn;

                $bannerStyle = 'background:' . $bg . ';border-left:4px solid ' . $accent . ';padding:12px 20px;'
                    . 'color:' . $color . ';position:relative;z-index:99999;box-shadow:0 2px 8px rgba(0,0,0,0.06);';

                if (!empty($n['expandable'])) {
                    $expandBtn = '<button type="button" onclick="(function(b,c){var open=c.style.display!==\'none\';c.style.display=open?\'none\':\'block\';b.textContent=open?\'Expand\':\'Collapse\';})(this,document.getElementById(\'' . $id . '_body\'))" '
                        . 'style="padding:3px 14px;font-size:13px;border-radius:5px;border:1px solid rgba(0,0,0,0.15);background:rgba(0,0,0,0.06);cursor:pointer;font-weight:500;">Expand</button>';
                    $controls .= $expandBtn . $dismissBtn . '</div>';
                    $html .= '<div id="' . $id . '" style="' . $bannerStyle . '">'
                        . '<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">'
                        . $headerRow
                        . $controls
                        . '</div>'
                        . '<div id="' . $id . '_body" style="display:none;">' . $bodyHtml . '</div>'
                        . '</div>';
                } else {
                    $controls .= $dismissBtn . '</div>';
                    $html .= '<div id="' . $id . '" style="' . $bannerStyle . '">'
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

            if ($html !== '') {
                // Inject JS helpers (acknowledge + poll vote) once per page
                $html .= '<script>
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

            return $html;
        }
    }
}
