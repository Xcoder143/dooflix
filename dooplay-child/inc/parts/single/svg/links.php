<?php
/**
 * links.php — REBUILT FINAL
 * - Complete Netflix-style links UI
 * - Server name extraction fixed (no "Download" used)
 * - Language text only inside the red pill (no duplicate under server)
 * - Capitalized server name (Option D)
 *
 * Drop into your theme (replace existing links.php). Backup original first.
 */

if (!defined('ABSPATH')) { exit; }

/* ---------------------------
   nf_get_links_from_dooplay()
   Parse DooPlay table output (safe regex approach)
   --------------------------- */
function nf_get_links_from_dooplay($post_id, $type_label, $mode) {
    if (!class_exists('DooLinks') || !method_exists('DooLinks', 'tablelist_front')) {
        return [];
    }

    ob_start();
    DooLinks::tablelist_front($post_id, $type_label, $mode);
    $html = ob_get_clean();

    $rows = [];
    if (!preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $html, $tr_matches)) {
        return $rows;
    }

    foreach ($tr_matches[1] as $tr_content) {
        if (!preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $tr_content, $td_matches)) continue;
        $tds = $td_matches[1];
        if (count($tds) < 4) continue;

        // td0 => server/icon/link, td1 => quality, td2 => language, td3 => size
        $td0 = $tds[0];

        // favicon src, if any
        $favicon_src = null;
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $td0, $m_img)) {
            $favicon_src = $m_img[1];
        }

        // extract first anchor href within td0 if present (used later for domain)
        $first_href = null;
        if (preg_match('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $td0, $m_firsthref)) {
            $first_href = $m_firsthref[1];
        }

        // raw server text (CLEAN) — avoid using anchor text like "Download"
        $server_raw = preg_replace('/<img[^>]*>/i', '', $td0);    // remove images
        $server_raw = preg_replace('/<a[^>]*>(.*?)<\/a>/is', '', $server_raw); // remove anchors entirely (do not keep inner anchor text)
        $server_raw = strip_tags($server_raw);
        $server_raw = trim(html_entity_decode($server_raw));
        // prevent using "download" or similar button labels as server name
        if (strtolower($server_raw) === 'download' || strtolower($server_raw) === 'watch') {
            $server_raw = '';
        }

        // Extract anchors across the whole row for real link(s)
        $real_href = '#';
        $label = 'Download';
        if (preg_match_all('/<a([^>]*)>(.*?)<\/a>/is', $tr_content, $a_matches, PREG_SET_ORDER)) {
            $found = false;
            foreach ($a_matches as $am) {
                $attrs = $am[1];
                $inner = trim(strip_tags($am[2]));
                if ($inner !== '') $label = html_entity_decode($inner);

                $attrKeys = ['data-l','data-link','data-href','data-url','href'];
                foreach ($attrKeys as $k) {
                    $pattern = '/'.$k.'=["\']([^"\']+)["\']/i';
                    if (preg_match($pattern, $attrs, $m)) {
                        $candidate = $m[1];
                        if ($candidate && $candidate !== '#') {
                            $real_href = $candidate;
                            $found = true;
                            break 2;
                        }
                    }
                }
            }
            if (!$found && isset($a_matches[0])) {
                if (preg_match('/href=["\']([^"\']+)["\']/i', $a_matches[0][1], $mhref)) {
                    $real_href = $mhref[1];
                }
            }
        }

        $quality = isset($tds[1]) ? trim(strip_tags($tds[1])) : '';
        $language = isset($tds[2]) ? trim(strip_tags($tds[2])) : '';
        $size = isset($tds[3]) ? trim(html_entity_decode(strip_tags($tds[3]))) : '';

        $rows[] = [
            'server_raw' => $server_raw,
            'server_icon' => $favicon_src,
            'server_href' => $first_href,
            'quality' => $quality,
            'lang' => $language,
            'size' => $size,
            'url' => $real_href,
            'label' => $label,
        ];
    }
    return $rows;
}

/* ---------------------------
   nf_extract_root_service()
   --------------------------- */

function nf_extract_root_service($domain) {

    // normalize
    $domain = strtolower(trim($domain));
    $domain = preg_replace('/^www\./', '', $domain);

    // split
    $parts = explode('.', $domain);

    // If domain has subdomains → pick the second-last part
    // new21.gdtot.dad → gdtot
    if (count($parts) >= 2) {
        $root = $parts[count($parts) - 2];
    } else {
        $root = $parts[0];
    }

    // uppercase brand
    return strtoupper($root);
}

/* ---------------------------
   nf_pretty_server_name()
   --------------------------- */

function nf_pretty_server_name($raw, $href, $favicon = null) {

    // 1) Try extracting server from favicon domain FIRST
    if ($favicon && preg_match('/domain=([^&]+)/i', $favicon, $m)) {
        $favDomain = strtolower($m[1]);
        $favDomain = preg_replace('/^www\./', '', $favDomain);

        // Friendly mapping
        $map = [
            't.me'        => 'Telegram',
            'telegram'    => 'Telegram',
            'mega'        => 'Mega',
            'mixdrop'     => 'MixDrop',
            'uptobox'     => 'UptoBox',
            'drive'       => 'Google Drive',
            'google'      => 'Google Drive',
            'mediafire'   => 'MediaFire',
            'dropbox'     => 'Dropbox',
            'zippyshare'  => 'Zippyshare',
            'ok.ru'       => 'OK.ru',
            'fembed'      => 'Fembed',
            'gdtot'       => 'GDTOT',  // NEW DIRECT MAPPING
        ];

        foreach ($map as $key => $name) {
            if (strpos($favDomain, $key) !== false) {
                return $name;
            }
        }

        // universal fallback → extract root brand name
        return nf_extract_root_service($favDomain);
    }

    // 2) If favicon not available → fallback: raw text extracted from <td>
    if ($raw && strtolower($raw) !== 'download') {
        return ucwords($raw);
    }

    // 3) LAST RESORT fallback: Try href
    if ($href && preg_match('/https?:\/\/([^\/]+)/i', $href, $m)) {
        $domain = strtolower($m[1]);

        // ignore localhost internal links
        if (!in_array($domain, ['localhost','127.0.0.1','::1'])) {

            $domain = preg_replace('/^www\./', '', $domain);

            // Extract root brand
            return nf_extract_root_service($domain);
        }
    }

    return 'Unknown Server';
}



/* ---------------------------
   nf_lang_to_flag()
   --------------------------- */
function nf_lang_to_flag($lang) {
    if (!$lang) return '';
    $l = strtolower($lang);
    $map = [
        'english' => '🇺🇸', 'en' => '🇺🇸',
        'hindi' => '🇮🇳', 'hi' => '🇮🇳',
        'spanish' => '🇪🇸', 'es' => '🇪🇸',
        'french' => '🇫🇷', 'fr' => '🇫🇷',
        'german' => '🇩🇪', 'de' => '🇩🇪',
        'portuguese' => '🇵🇹', 'pt' => '🇵🇹',
        'italian' => '🇮🇹', 'it' => '🇮🇹',
        'korean' => '🇰🇷', 'kr' => '🇰🇷',
        'japanese' => '🇯🇵', 'jp' => '🇯🇵',
        'arabic' => '🇸🇦', 'ru' => '🇷🇺',
        'turkish' => '🇹🇷', 'ur' => '🇵🇰',
    ];
    foreach ($map as $k => $v) {
        if (strpos($l, $k) !== false) return $v;
    }
    $short = strtoupper(substr($lang,0,2));
    return $short;
}
?>
<!-- INLINE CSS -->
<style type="text/css">
/* Layout */
.netflinks-wrapper { width:100%; box-sizing:border-box; margin-bottom:28px; color:#e5e5e5; font-family: Arial, Helvetica, sans-serif; }
.netflinks-tabbar { display:flex; gap:10px; align-items:center; margin-bottom:14px; flex-wrap:wrap; }
.netflinks-tab { cursor:pointer; padding:8px 14px; border-radius:8px; background:#0d0d0d; border:1px solid rgba(255,255,255,0.04); color:#dcdcdc; text-decoration:none; }
.netflinks-tab.active { background:#e50914; color:#fff; border-color:#e50914; box-shadow:0 6px 18px rgba(229,9,20,0.12); }

/* Grid & Card */
.netflinks-grid { display:grid; grid-template-columns:repeat(3, minmax(240px,1fr)); gap:16px; transition:opacity .18s ease; }
@media(max-width:900px){
    .netflinks-grid {
        grid-template-columns:repeat(2, 1fr);
    }
}

@media(max-width:600px){
    .netflinks-grid {
        grid-template-columns:1fr;
    }
}
.netflinks-card { background:#141414;padding:14px;border-radius:10px;border:1px solid rgba(255,255,255,0.04); display:flex;flex-direction:column; gap:10px; transform:translateY(0); transition:transform .16s ease, box-shadow .16s ease; }
.netflinks-card:hover { transform:translateY(-6px); box-shadow:0 18px 42px rgba(0,0,0,0.6); border-color:#e50914; }

/* quality pill & size pill row */
.netflinks-toprow { display:flex; justify-content:space-between; align-items:center; gap:12px; }
.netflinks-quality-pill { display:inline-flex; align-items:center; gap:8px; background:#000;padding:6px 12px;border-radius:20px;border:1px solid rgba(255,255,255,0.08); font-weight:700;color:#fff;font-size:.95rem; }
.netflinks-size-pill { display:inline-flex; align-items:center; gap:8px; background:#000;padding:6px 12px;border-radius:20px;border:1px solid rgba(255,255,255,0.08); font-weight:700;color:#fff;font-size:.92rem; }

/* server row */
.netflinks-server { display:flex; align-items:center; gap:12px; background:#000;padding:10px;border-radius:12px;border:1px solid rgba(255,255,255,0.02); color:#fff; font-weight:700; }
.netflinks-server img { width:36px;height:36px;object-fit:cover;border-radius:50%; }

/* language pill (Netflix red) */
.netflinks-lang-pill { display:inline-flex; align-items:center; gap:8px; background:#e50914;padding:6px 10px;border-radius:12px;color:#fff;font-weight:700;font-size:.95rem; }

/* meta, size */
.netflinks-meta { color:#cfcfcf; font-size:.95rem; }
.netflinks-size { font-size:1rem; color:#e5e5e5; }

/* actions - download button style C: dark pill with glowing red outline */
.netflinks-actions { display:flex; gap:10px; margin-top:8px; }
.netflinks-actions a.download-btn {
    display:inline-flex; align-items:center; gap:10px; justify-content:center;
    padding:10px 14px; border-radius:10px; background:#0b0b0b; color:#fff; text-decoration:none; font-weight:700;
    border:2px solid rgba(229,9,20,0.12);
    box-shadow:0 0 0 0 rgba(229,9,20,0);
    width: 100%;
    transition:box-shadow .18s ease, transform .12s ease, border-color .12s ease;
}
.netflinks-actions a.download-btn svg { stroke:#e50914; }
.netflinks-actions a.download-btn:hover {
    transform:translateY(-2px);
    border-color:rgba(229,9,20,0.35);
    box-shadow:0 10px 30px rgba(229,9,20,0.12);
}

/* skeleton placeholders */
.skeleton-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(240px,1fr)); gap:16px; }
.skeleton { background:linear-gradient(90deg, #1a1a1a 0%, #232323 50%, #1a1a1a 100%); background-size:200% 100%; animation:skeleton-shimmer 1.2s linear infinite; border-radius:10px; padding:14px; min-height:120px; }
@keyframes skeleton-shimmer { 0% { background-position:200% 0 } 100% { background-position:-200% 0 } }

@media (max-width:480px) {
    .netflinks-actions a.download-btn { padding:8px 10px; font-size:.95rem; }
    .netflinks-server img { width:30px;height:30px; }
}
</style>

<!-- Minimal Netflix-style download icon symbol (stroke-only) -->
<svg style="display:none;" xmlns="http://www.w3.org/2000/svg">
    <symbol id="nf-download" viewBox="0 0 24 24">
        <path d="M12 3v12"></path>
        <path d="M6 11l6 6 6-6"></path>
        <path d="M5 21h14"></path>
    </symbol>

    <!-- server icon fallback (red rounded rect) -->
    <symbol id="nf-server" viewBox="0 0 24 24">
        <rect x="2" y="5" width="20" height="14" rx="2" ry="2" fill="#e50914"></rect>
    </symbol>
</svg>
<div class="netflinks-wrapper">
    <!-- TAB BAR -->
    <div class="netflinks-tabbar" role="tablist" aria-label="Links types">
        <button class="netflinks-tab active" data-mode="download" role="tab" aria-selected="true">Download</button>
        <button class="netflinks-tab" data-mode="torrent" role="tab" aria-selected="false">Torrent</button>
        <button class="netflinks-tab" data-mode="videos" role="tab" aria-selected="false">Watch Online</button>
    </div>

    <!-- Skeleton placeholders -->
    <div id="netflinks-skeleton" class="skeleton-grid" aria-hidden="false" role="status" aria-live="polite">
        <div class="skeleton"></div>
        <div class="skeleton"></div>
        <div class="skeleton"></div>
        <div class="skeleton"></div>
    </div>

<?php
$post_id = isset($post->ID) ? $post->ID : (function_exists('get_the_ID') ? get_the_ID() : 0);
if (!$post_id) {
    echo '<!-- links.php: no post id -->';
    return;
}

$types = [
    ['label' => __d('Download'), 'mode' => 'download', 'title' => 'Download'],
    ['label' => __d('Torrent'), 'mode' => 'torrent', 'title' => 'Torrent'],
    ['label' => __d('Watch online'), 'mode' => 'videos', 'title' => 'Watch Online'],
];

// Gather all rows server-side and embed as JSON for client switching
$all_data = [];
foreach ($types as $t) {
    $rows = [];
    if (function_exists('doo_here_type_links') && !doo_here_type_links($post_id, $t['label'])) {
        $rows = [];
    } else {
        $rows = nf_get_links_from_dooplay($post_id, $t['label'], $t['mode']);
        foreach ($rows as &$rr) {
            // determine pretty server name using nf_pretty_server_name (capitalized)
            $rr['pretty_server'] = nf_pretty_server_name(
    $rr['server_raw'],
    $rr['server_href'],
    $rr['server_icon']   // <---- favicon used here
);

            $rr['flag'] = nf_lang_to_flag($rr['lang']);
            // ensure lang text is only used in pill (we won't print it under server)
        }
        unset($rr);
    }
    $all_data[$t['mode']] = $rows;
}

// Output grids (only download visible initially)
foreach ($types as $t):
    $mode = $t['mode'];
    $rows = isset($all_data[$mode]) ? $all_data[$mode] : [];
    $json_rows = json_encode($rows, JSON_HEX_APOS | JSON_HEX_QUOT);
?>
    <div class="netflinks-grid" data-mode="<?php echo esc_attr($mode); ?>" data-rows='<?php echo $json_rows; ?>' style="display:<?php echo $mode === 'download' ? 'grid' : 'none'; ?>;">
        <?php
        if (empty($rows)) {
            echo '<div style="grid-column:1/-1;color:#cfcfcf;padding:12px;background:#0f0f0f;border-radius:8px;">' . __('No links available') . '</div>';
        } else {
            foreach ($rows as $r):
                $pretty_server = isset($r['pretty_server']) ? $r['pretty_server'] : nf_pretty_server_name($r['server_raw'], isset($r['server_href']) ? $r['server_href'] : $r['url']);
                $icon = isset($r['server_icon']) && !empty($r['server_icon']) ? esc_url($r['server_icon']) : '';
                $quality = isset($r['quality']) ? $r['quality'] : '';
                $lang = isset($r['lang']) ? $r['lang'] : '';
                $flag = isset($r['flag']) ? $r['flag'] : nf_lang_to_flag($lang);
                $size = isset($r['size']) ? $r['size'] : '';
                $url = isset($r['url']) ? $r['url'] : '#';
                $label = isset($r['label']) ? $r['label'] : __('Download');
                $url_esc = esc_url($url);
        ?>
        <article class="netflinks-card" role="article" aria-label="<?php echo esc_attr($pretty_server . ' ' . $quality); ?>">

            <!-- TOP ROW: quality pill left, size pill right -->
            <div class="netflinks-toprow">
                <div class="netflinks-quality-pill">
                    <?php
                        $q = strtolower($quality);
                        if (strpos($q,'4k') !== false) {
                            echo '<span style="font-size:14px;">🔶</span><span>4K</span>';
                        } elseif (strpos($q,'1080') !== false || strpos($q,'hd') !== false) {
                            echo '<span style="font-size:14px;">🟦</span><span>' . esc_html($quality) . '</span>';
                        } elseif (strpos($q,'720') !== false) {
                            echo '<span style="font-size:14px;">🟩</span><span>' . esc_html($quality) . '</span>';
                        } else {
                            echo '<span style="font-size:14px;">📺</span><span>' . esc_html($quality) . '</span>';
                        }
                    ?>
                </div>

                <?php if ($size): ?>
                    <div class="netflinks-size-pill">📦 <?php echo esc_html($size); ?></div>
                <?php endif; ?>
            </div>
            <!-- SERVER ROW: circular icon + server name | language pill (Netflix red) -->
            <div style="height:8px;"></div>
            <div class="netflinks-server" role="group" aria-label="Server and language">
                <div style="display:flex;align-items:center;gap:12px;flex:1;">
                    <?php if ($icon): ?>
                        <img src="<?php echo $icon; ?>" alt="<?php echo esc_attr($pretty_server); ?>">
                    <?php else: ?>
                        <svg width="36" height="36" aria-hidden="true"><use xlink:href="#nf-server"></use></svg>
                    <?php endif; ?>

                    <!-- SERVER NAME (Capitalized) -->
                    <div style="font-size:1rem;font-weight:700;color:#fff;"><?php echo esc_html($pretty_server); ?></div>
                </div>

                <!-- LANGUAGE in Netflix-red pill ONLY -->
                <?php if ($flag || $lang): ?>
                    <div style="margin-left:12px;">
                        <span class="netflinks-lang-pill" role="note" aria-label="<?php echo esc_attr($lang); ?>">
                            <?php if ($flag) echo $flag . ' '; ?><?php echo esc_html($lang); ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ACTIONS -->
            <div class="netflinks-actions" role="group" aria-label="Download actions">
                <a class="download-btn" href="<?php echo $url_esc; ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr($label); ?>">
                    <!-- Inline minimal Netflix-style SVG icon -->
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M12 3v12"></path>
                        <path d="M6 11l6 6 6-6"></path>
                        <path d="M5 21h14"></path>
                    </svg>
                    <span><?php echo esc_html($label); ?></span>
                </a>
            </div>

        </article>
        <?php
            endforeach;
        }
        ?>
    </div>
<?php endforeach; ?>
<!-- Uploader form (keeps original functionality) -->
<?php
if (is_user_logged_in() && class_exists('DooLinks') && method_exists('DooLinks','front_publisher_role') && DooLinks::front_publisher_role() === true):
?>
    <h2 style="margin-top:18px;color:#e5e5e5;border-left:4px solid #e50914;padding-left:12px;"><?php _d('Submit Links'); ?></h2>
    <div style="background:#141414;padding:16px;border-radius:10px;border:1px solid rgba(255,255,255,0.04);margin-top:10px;">
        <div id="resultado_link_form"></div>
        <form id="doopostlinks" enctype="application/json" style="display:block;">
            <table style="width:100%;border-collapse:collapse;color:#fff;">
                <thead>
                    <tr>
                        <th style="text-align:left;padding:6px 8px;"><?php _d('Type'); ?></th>
                        <th style="text-align:left;padding:6px 8px;"><?php _d('URL'); ?></th>
                        <th style="text-align:left;padding:6px 8px;"><?php _d('Quality'); ?></th>
                        <th style="text-align:left;padding:6px 8px;"><?php _d('Language'); ?></th>
                        <th style="text-align:left;padding:6px 8px;"><?php _d('File size'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="tbody">
                    <tr class="row first_tr">
                        <td style="padding:6px 8px;">
                            <select name="type" style="padding:6px;border-radius:6px;background:#0b0b0b;border:1px solid rgba(255,255,255,0.03);color:#fff;">
                                <?php foreach( DooLinks::types() as $type) { echo "<option>" . esc_html($type) . "</option>"; } ?>
                            </select>
                        </td>
                        <td style="padding:6px 8px;">
                            <input name="url" type="text" class="url" placeholder="http://" style="width:100%;padding:8px;border-radius:6px;background:#0b0b0b;border:1px solid rgba(255,255,255,0.03);color:#fff;">
                        </td>
                        <td style="padding:6px 8px;">
                            <select name="quality" style="padding:6px;border-radius:6px;background:#0b0b0b;border:1px solid rgba(255,255,255,0.03);color:#fff;">
                                <?php foreach( DooLinks::resolutions() as $resolution) { echo "<option>" . esc_html($resolution) . "</option>"; } ?>
                            </select>
                        </td>
                        <td style="padding:6px 8px;">
                            <select name="lang" style="padding:6px;border-radius:6px;background:#0b0b0b;border:1px solid rgba(255,255,255,0.03);color:#fff;">
                                <?php foreach( DooLinks::langs() as $lang_item) { echo "<option>" . esc_html($lang_item) . "</option>"; } ?>
                            </select>
                        </td>
                        <td style="padding:6px 8px;">
                            <input name="size" type="text" class="size" style="padding:8px;border-radius:6px;background:#0b0b0b;border:1px solid rgba(255,255,255,0.03);color:#fff;">
                        </td>
                        <td style="padding:6px 8px;"><a data-repeater-delete class="remove_row" style="color:#e50914;text-decoration:none;cursor:pointer;">X</a></td>
                    </tr>
                </tbody>
            </table>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;">
                <a data-repeater-create id="add_row" class="add_row" style="background:#111;padding:10px 12px;border-radius:8px;color:#fff;text-decoration:none;"><?php _d('+ Add row'); ?></a>
                <input type="submit" value="<?php _d('Send link(s)'); ?>" style="background:#e50914;color:#fff;border:none;padding:10px 14px;border-radius:8px;cursor:pointer;">
            </div>

            <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">
            <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('doolinks')); ?>">
            <input type="hidden" name="action" value="doopostlinks">
        </form>
    </div>
<?php endif; ?>

<!-- JS: Tabs, skeleton reveal, client-side population -->
<script type="text/javascript">
(function(){
    function $qa(sel, ctx){ return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
    function $q(sel, ctx){ return (ctx || document).querySelector(sel); }

    var tabs = $qa('.netflinks-tab');
    var skeleton = $q('#netflinks-skeleton');
    var grids = $qa('.netflinks-grid');

    function revealAfterDelay() {
        if (!skeleton) return;
        skeleton.style.display = 'grid';
        grids.forEach(function(g){ g.style.opacity = '0'; });
        setTimeout(function(){
            skeleton.style.display = 'none';
            grids.forEach(function(g){
                g.style.opacity = '1';
                g.style.transition = 'opacity .28s ease';
            });
        }, 320);
    }
    revealAfterDelay();

    tabs.forEach(function(tab){
        tab.addEventListener('click', function(){
            var mode = tab.getAttribute('data-mode');
            tabs.forEach(function(t){ t.classList.remove('active'); t.setAttribute('aria-selected','false'); });
            tab.classList.add('active'); tab.setAttribute('aria-selected','true');

            grids.forEach(function(g){
                if (g.getAttribute('data-mode') === mode) {
                    // If grid is empty but has data-rows, populate
                    if (g.children.length === 0) {
                        try {
                            var rows = JSON.parse(g.getAttribute('data-rows') || '[]');
                            rows.forEach(function(r){
                                var art = document.createElement('article');
                                art.className = 'netflinks-card';
                                var html = '';
                                // top row
                                html += '<div class="netflinks-toprow">';
                                // quality pill left
                                var q = (r.quality||'').toLowerCase();
                                if (q.indexOf('4k') !== -1) html += '<div class="netflinks-quality-pill">🔶 4K</div>';
                                else if (q.indexOf('1080') !== -1 || q.indexOf('hd') !== -1) html += '<div class="netflinks-quality-pill">🟦 '+(r.quality||'HD')+'</div>';
                                else html += '<div class="netflinks-quality-pill">📺 '+(r.quality||'')+'</div>';
                                // size pill right
                                if (r.size) html += '<div class="netflinks-size-pill">📦 '+(r.size||'')+'</div>';
                                html += '</div>';

                                // server row
                                html += '<div style="height:8px;"></div>';
                                html += '<div class="netflinks-server">';
                                if (r.server_icon) html += '<div style="display:flex;align-items:center;gap:12px;flex:1;"><img src="'+r.server_icon+'" style="width:36px;height:36px;border-radius:50%;object-fit:cover;"><div style="font-size:1rem;font-weight:700;color:#fff;">'+(r.pretty_server||r.server_raw||'Unknown')+'</div></div>';
                                else html += '<div style="display:flex;align-items:center;gap:12px;flex:1;"><svg width="36" height="36"><use xlink:href="#nf-server"></use></svg><div style="font-size:1rem;font-weight:700;color:#fff;">'+(r.pretty_server||r.server_raw||'Unknown')+'</div></div>';
                                // language pill
                                if (r.flag || r.lang) html += '<div style="margin-left:12px;"><span class="netflinks-lang-pill">'+(r.flag?r.flag+' ':'')+(r.lang||'')+'</span></div>';
                                html += '</div>';

                                // actions
                                html += '<div class="netflinks-actions"><a class="download-btn" href="'+(r.url||'#')+'" target="_blank" rel="noopener noreferrer"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"></path><path d="M6 11l6 6 6-6"></path><path d="M5 21h14"></path></svg><span>'+(r.label||'Download')+'</span></a></div>';

                                art.innerHTML = html;
                                g.appendChild(art);
                            });
                        } catch(e){}
                    }
                    g.style.display = 'grid';
                    g.style.opacity = '1';
                } else {
                    g.style.display = 'none';
                }
            });
        });
    });

    // keyboard nav for tabs
    tabs.forEach(function(t){
        t.addEventListener('keydown', function(e){
            var idx = tabs.indexOf(t);
            if (e.key === 'ArrowRight') {
                var next = tabs[(idx+1)%tabs.length]; next.focus(); next.click();
            } else if (e.key === 'ArrowLeft') {
                var prev = tabs[(idx-1+tabs.length)%tabs.length]; prev.focus(); prev.click();
            }
        });
    });

})();
</script>

</div> <!-- wrapper end -->
