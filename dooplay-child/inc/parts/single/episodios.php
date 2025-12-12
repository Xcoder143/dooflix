<?php
/*
* -------------------------------------------------------------------------------------
* FINAL EPISODE TEMPLATE (Fixed: Nav Buttons ID Error)
* -------------------------------------------------------------------------------------
*/

// 1. GET VARIABLES & META
$postmeta = doo_postmeta_episodes($post->ID);
$adsingle = doo_compose_ad('_dooplay_adsingle');

// Main IDs
$tmdb_id  = doo_isset($postmeta,'ids');
$season   = doo_isset($postmeta,'temporada');
$episode  = doo_isset($postmeta,'episodio');
$air_date = doo_isset($postmeta,'air_date');
$ep_name  = doo_isset($postmeta,'episode_name');

// Parent Show Info
$tvshow_id    = doo_get_tvpermalink($tmdb_id);
$tvshow_title = get_the_title($tvshow_id);
$tvshow_link  = get_permalink($tvshow_id);

// 2. NAVIGATION LOGIC
$ep_nav  = DDbmoviesHelpers::EpisodeNav($tmdb_id, $season, $episode);
$next_ep = doo_isset($ep_nav, 'next'); 
$prev_ep = doo_isset($ep_nav, 'prev'); 

// 3. IMAGE LOGIC
$images = doo_isset($postmeta, 'imagenes');
$backdrop = doo_rand_images($images,'original',true,true);
if(empty($backdrop)) {
    $parent_images = get_post_meta($tvshow_id, 'imagenes', true);
    $backdrop = doo_rand_images($parent_images, 'original', true, true);
}

// Poster: Use PARENT TV SHOW Poster (2:3)
$poster_url = dbmovies_get_poster($tvshow_id, 'medium'); 
if(empty($poster_url)) $poster_url = DOO_URI . '/assets/img/no/dt_poster.png';

// 4. RECENTLY ADDED CHECK
$post_time = get_post_time('U', false, $post->ID);
$is_recent = ((current_time('timestamp') - $post_time) / 86400) <= 2;

// Player Data
$player = maybe_unserialize(doo_isset($postmeta,'players'));
$player_ads = doo_compose_ad('_dooplay_adplayer');

/* =========================================================
   NETWORK LOGO LOGIC
   ========================================================= */
$network_logo_html = '';
if ($tvshow_id) {
    $networks = get_the_terms($tvshow_id, 'dtnetwork');
    if (!$networks || is_wp_error($networks)) $networks = get_the_terms($tvshow_id, 'dtnetworks');

    if ($networks && !is_wp_error($networks)) {
        $net_name = $networks[0]->name;
        $n_lower = strtolower($net_name);
        $logo_url = '';

        if (strpos($n_lower, 'netflix') !== false) $logo_url = 'https://upload.wikimedia.org/wikipedia/commons/0/08/Netflix_2015_logo.svg';
        elseif (strpos($n_lower, 'hbo') !== false) $logo_url = 'https://upload.wikimedia.org/wikipedia/commons/1/17/HBO_logo.svg';
        elseif (strpos($n_lower, 'disney') !== false) $logo_url = 'https://upload.wikimedia.org/wikipedia/commons/3/3e/Disney%2B_logo.svg';
        elseif (strpos($n_lower, 'amazon') !== false || strpos($n_lower, 'prime') !== false) $logo_url = 'https://upload.wikimedia.org/wikipedia/commons/1/11/Amazon_Prime_Video_logo.svg';
        elseif (strpos($n_lower, 'hulu') !== false) $logo_url = 'https://upload.wikimedia.org/wikipedia/commons/e/e4/Hulu_Logo.svg';
        elseif (strpos($n_lower, 'apple') !== false) $logo_url = 'https://upload.wikimedia.org/wikipedia/commons/2/28/Apple_TV_Plus_Logo.svg';
        elseif (strpos($n_lower, 'amc') !== false) $logo_url = 'https://upload.wikimedia.org/wikipedia/commons/1/1d/AMC_Networks_logo.svg';
        elseif (strpos($n_lower, 'cw') !== false) $logo_url = 'https://upload.wikimedia.org/wikipedia/commons/2/26/The_CW_Network_logo.svg';
        elseif (strpos($n_lower, 'fx') !== false) $logo_url = 'https://upload.wikimedia.org/wikipedia/commons/9/9d/FX_Network_logo.svg';
        elseif (strpos($n_lower, 'showtime') !== false) $logo_url = 'https://upload.wikimedia.org/wikipedia/commons/2/22/Showtime.svg';
        elseif (strpos($n_lower, 'starz') !== false) $logo_url = 'https://upload.wikimedia.org/wikipedia/commons/6/62/Starz_logo.svg';
        elseif (strpos($n_lower, 'paramount') !== false) $logo_url = 'https://upload.wikimedia.org/wikipedia/commons/8/81/Paramount%2B_logo.svg';

        if (!empty($logo_url)) {
            $network_logo_html = '<div class="nfx-hero-network"><span class="nfx-presented-by">A '.(strpos($n_lower, 'netflix') !== false ? 'NETFLIX' : strtoupper($net_name)).' SERIES</span><img src="' . esc_url($logo_url) . '" class="nfx-net-logo-img"></div>';
        } else {
            $network_logo_html = '<div class="nfx-hero-network"><span class="nfx-presented-by">A SERIES BY</span><span class="nfx-net-logo-text">' . esc_html($net_name) . '</span></div>';
        }
    }
}

/* =========================================================
   PLAYER DATA PREPARATION
   ========================================================= */
$sources_data = [];
$count = 0;

if (!function_exists('get_player_data')) {
    function get_player_data($srv, $post_id) {
        $type = isset($srv['select']) ? $srv['select'] : 'iframe';
        $url  = isset($srv['url']) ? $srv['url'] : '';
        if (empty($url)) return false;
        if ($type === 'dtshcode') return ['type' => 'html', 'content' => do_shortcode($url)];
        elseif ($type === 'mp4' || $type === 'gdrive') {
            $base = function_exists('doo_compose_pagelink') ? doo_compose_pagelink('jwpage') : home_url('/jwplayer/');
            $src  = $base . "?source=" . urlencode($url) . "&id=" . $post_id . "&type=" . $type;
            return ['type' => 'url', 'content' => $src];
        } else {
            if (preg_match('/src=["\']([^"\']+)["\']/', $url, $m)) $url = $m[1];
            return ['type' => 'url', 'content' => $url];
        }
    }
}

if (!empty($player) && is_array($player)) {
    foreach($player as $srv) {
        $processed = get_player_data($srv, $post->ID);
        if ($processed) {
            $label = !empty($srv['name']) ? $srv['name'] : "Server " . ($count + 1);
            $sources_data[] = ['label' => $label, 'type' => $processed['type'], 'content' => $processed['content']];
            $count++;
        }
    }
}

doo_set_views($post->ID);
?>

<style>
    /* Full Width & Poster Styles */
    .sidebar, #sidebar, .secondary { display: none !important; }
    #content, .module, .content, .main-content { width: 100% !important; max-width: 100% !important; padding: 0 !important; margin: 0 !important; background: #141414; }
    
    .heros-poster { display: block !important; opacity: 1 !important; visibility: visible !important; }
    .nfx-net-logo-img { height: 60px !important; width: auto; filter: drop-shadow(0 4px 8px rgba(0,0,0,0.6)); }
    .player-header h3 { color: white; margin: 0; display: flex; align-items: center; }

    /* Custom Season/Ep Badge Style */
    .nfx-ep-badge {
        position: absolute;
        top: 0;
        right: 0;
        background: #e50914;
        color: #fff;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 4px 8px;
        z-index: 20;
        line-height: 1;
        box-shadow: 0 4px 10px rgba(0,0,0,0.5);
        border-bottom-left-radius: 4px;
        pointer-events: none;
    }
    .nfx-ep-badge .top { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2px; }
    .nfx-ep-badge .ten { font-size: 18px; font-weight: 800; }

    /* --- UNDER PLAYER NAVIGATION --- */
    .nfx-player-nav {
        display: flex;
        justify-content: space-around;
        align-items: center;
        padding: 15px 0;
        border-bottom: 1px solid #222;
        margin-bottom: 20px;
    }
    .nfx-nav-btn {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #1f1f1f;
        padding: 12px 20px;
        border-radius: 4px;
        color: #fff;
        text-decoration: none;
        font-weight: 600;
        transition: 0.3s;
        border: 1px solid #333;
    }
    .nfx-nav-btn:hover {
        background: #e50914;
        border-color: #e50914;
        color: white;
    }
    .nfx-nav-btn span { font-size: 0.9rem; color: #aaa; font-weight: normal; margin-left: 5px; }
    .nfx-nav-btn:hover span { color: #eee; }
    
    @media (max-width: 768px) {
        .nfx-player-nav { flex-direction: column; gap: 10px; }
        .nfx-nav-btn { width: 100%; justify-content: center; }
    }
</style>

<div id="edit_link"></div>

<div id="single" class="dtsingle">

    <div class="heros" style="background-image: url('<?php echo $backdrop; ?>');">
        
        <?php echo $network_logo_html; ?>

        <div class="heros-content animate-on-load">
            
            <div class="heros-poster" style="position: relative;">
                
                <div class="nfx-ep-badge">
                    <span class="top">S<?php echo $season; ?></span>
                    <span class="ten">E<?php echo $episode; ?></span>
                </div>

                <?php if($is_recent): ?>
                    <div class="nfx-single-badge-recent">Recently added</div>
                <?php endif; ?>

                <img src="<?php echo $poster_url; ?>" alt="<?php echo $tvshow_title; ?>">
            </div>

            <div class="meta">
                <h1 class="title animate-on-load delay-1"><?php echo $ep_name; ?></h1>
                
                <div class="badges animate-on-load delay-2">
                    <span class="match-score high-score">S<?php echo $season; ?> : E<?php echo $episode; ?></span>
                    
                    <?php if($air_date) { ?>
                        <span class="country-pill"><?php echo doo_date_compose($air_date, false); ?></span>
                    <?php } ?>

                    <span class="country-pill" style="border: 1px solid #ccc; background:transparent;">HD</span>
                </div>

                <div class="desc animate-on-load delay-3">
                    <?php echo wp_trim_words( get_the_content(), 55, '...' ); ?>
                </div>

                <div class="hero-buttons animate-on-load delay-4">
                    <a href="#player-wrapper" class="btn-hero btn-play">
                        <i class="fas fa-play"></i> <?php _d('Play'); ?>
                    </a>
                    
                    <?php if(!empty($prev_ep)): 
                        // FIX: Get ID from URL since it is not in array
                        $prev_id = url_to_postid($prev_ep['permalink']);
                        $prev_s = ($prev_id) ? get_post_meta($prev_id, 'temporada', true) : '';
                        $prev_e = ($prev_id) ? get_post_meta($prev_id, 'episodio', true) : '';
                    ?>
                        <a href="<?php echo $prev_ep['permalink']; ?>" class="btn-hero btn-more" style="background: rgba(109, 109, 110, 0.7); color: #fff;">
                            <i class="fas fa-backward"></i> <?php _d('Prev'); ?>
                        </a>
                    <?php endif; ?>

                    <?php if(!empty($next_ep)): 
                        // FIX: Get ID from URL
                        $next_id = url_to_postid($next_ep['permalink']);
                        $next_s = ($next_id) ? get_post_meta($next_id, 'temporada', true) : '';
                        $next_e = ($next_id) ? get_post_meta($next_id, 'episodio', true) : '';
                    ?>
                        <a href="<?php echo $next_ep['permalink']; ?>" class="btn-hero btn-more" style="background: white; color: black;">
                            <i class="fas fa-forward"></i> <?php _d('Next'); ?>
                        </a>
                    <?php endif; ?>

                    <?php if(empty($prev_ep) && empty($next_ep)): ?>
                        <a href="<?php echo esc_url($tvshow_link); ?>" class="btn-hero btn-more">
                            <i class="fas fa-arrow-left"></i> <?php _d('Back to Show'); ?>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="genres-box animate-on-load delay-5">
                    <strong><?php echo $tvshow_title; ?></strong>
                </div>

            </div>
        </div>
    </div>
    <div class="banner__fadeBottoms"></div>

    <section id="player-wrapper" class="player-wrapper">
        <div class="player-header">
            <h3>
                <img src="<?php echo get_stylesheet_directory_uri(); ?>/inc/parts/single/svg/watchnow.svg" width="50" height="50" alt="Watch Now" style="margin-right:6px;">
                Watch NOW
            </h3>
        </div>

        <div class="dooplay-ad-player" style="text-align:center; margin: 0 auto 15px;">
            <?php echo $player_ads; ?>
        </div>

        <ul class="player-tabs" id="player-tabs">
            <?php
            if (!empty($sources_data)) {
                $idx = 0;
                foreach($sources_data as $src) {
                    echo "<li class='tab' data-index='{$idx}'>{$src['label']}</li>";
                    $idx++;
                }
            } else {
                echo "<li class='tab' style='cursor:default; opacity:0.5;'>No video available</li>";
            }
            ?>
        </ul>

        <div class="player-frame">
            <div id="playerContent" class="player-content-area">
                <?php if(!empty($sources_data)): ?>
                   <div style="display:flex;align-items:center;justify-content:center;height:100%;">
                       <i class="fa fa-circle-notch fa-spin fa-3x" style="color:#333;"></i>
                   </div>
                <?php else: ?>
                   <div style="display:flex;align-items:center;justify-content:center;height:100%;color:#666;">
                       <p>No video sources found.</p>
                   </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="nfx-player-nav">
            
            <?php if(!empty($prev_ep)): 
                // Using previously fetched data or refetching safely
                $prev_id = url_to_postid($prev_ep['permalink']);
                $prev_s = ($prev_id) ? get_post_meta($prev_id, 'temporada', true) : '';
                $prev_e = ($prev_id) ? get_post_meta($prev_id, 'episodio', true) : '';
            ?>
                <a href="<?php echo $prev_ep['permalink']; ?>" class="nfx-nav-btn">
                    <i class="fas fa-backward"></i> Previous 
                    <?php if($prev_s && $prev_e): ?><span>(S<?php echo $prev_s; ?> Ep<?php echo $prev_e; ?>)</span><?php endif; ?>
                </a>
            <?php else: ?>
                <div></div> <?php endif; ?>

            <?php if(!empty($next_ep)): 
                $next_id = url_to_postid($next_ep['permalink']);
                $next_s = ($next_id) ? get_post_meta($next_id, 'temporada', true) : '';
                $next_e = ($next_id) ? get_post_meta($next_id, 'episodio', true) : '';
            ?>
                <a href="<?php echo $next_ep['permalink']; ?>" class="nfx-nav-btn">
                    Next <i class="fas fa-forward"></i>
                    <?php if($next_s && $next_e): ?><span>(S<?php echo $next_s; ?> Ep<?php echo $next_e; ?>)</span><?php endif; ?>
                </a>
            <?php else: ?>
                <div></div>
            <?php endif; ?>

        </div>

    </section>

    <?php if(DOO_THEME_DOWNLOAD_MOD): ?>
    <div class="section">
        <?php get_template_part('inc/parts/single/links'); ?>
    </div>
    <?php endif; ?>

    <div class="section">
        <h2 class="netflix-section-title"><?php _d('Comments'); ?></h2>
        <?php get_template_part('inc/parts/comments'); ?>
    </div>

</div>

<script>
var playerSources = <?php echo json_encode($sources_data); ?>;

document.addEventListener("DOMContentLoaded", () => {
    const tabs = document.querySelectorAll("#player-tabs .tab");
    const container = document.getElementById("playerContent");

    function loadSource(index) {
        if(!playerSources[index]) return;
        const data = playerSources[index];
        container.innerHTML = '';
        if (data.type === 'html') {
            container.innerHTML = data.content;
        } else {
            const iframe = document.createElement('iframe');
            iframe.src = data.content;
            iframe.setAttribute('allow', 'autoplay; fullscreen; encrypted-media');
            iframe.setAttribute('allowfullscreen', '');
            iframe.style.width = "100%";
            iframe.style.height = "100%";
            iframe.style.border = "none";
            container.appendChild(iframe);
        }
    }

    if(tabs.length > 0 && playerSources.length > 0){
        tabs[0].classList.add("selected");
        loadSource(0); 
    }

    tabs.forEach(tab => {
        tab.addEventListener("click", () => {
            tabs.forEach(t => t.classList.remove("selected"));
            tab.classList.add("selected");
            loadSource(tab.dataset.index);
        });
    });
});
</script>