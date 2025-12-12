<?php
/*
* -------------------------------------------------------------------------------------
* @author: Doothemes
* @author URI: https://doothemes.com/
* -------------------------------------------------------------------------------------
*/

$classlinks = new DooLinks;
$postmeta = doo_postmeta_movies($post->ID);

// Meta Data
$trailer = doo_isset($postmeta,'youtube_id');
$pviews  = doo_isset($postmeta,'dt_views_count');
$player  = maybe_unserialize( doo_isset($postmeta,'players') );
$images  = doo_isset($postmeta,'imagenes');

// Image logic
$dynamicbg  = dbmovies_get_rand_image($images);
$poster_url = dbmovies_get_poster($post->ID,'large');

if (empty($dynamicbg)) $dynamicbg = $poster_url;

// Options
$player_ads = doo_compose_ad('_dooplay_adplayer');
$player_wht = dooplay_get_option('playsize','regular');

/* =========================================================
   UPDATED: MATCH PERCENTAGE LOGIC (Priority Cascade)
   ========================================================= */
// 1. Get all possible ratings
$imdb_rate = doo_isset($postmeta, 'imdbRating');   // IMDb
$tmdb_rate = doo_isset($postmeta, 'vote_average'); // TMDB
$user_rate = doo_isset($postmeta, 'dt_rates_average'); // User Stars

// 2. Select the best available rating
$final_rating = 0;

if (!empty($imdb_rate) && $imdb_rate > 0) {
    $final_rating = floatval($imdb_rate);
} elseif (!empty($tmdb_rate) && $tmdb_rate > 0) {
    $final_rating = floatval($tmdb_rate);
} elseif (!empty($user_rate) && $user_rate > 0) {
    $final_rating = floatval($user_rate);
}

// 3. Calculate Percentage (0 to 100)
$match_percent = round($final_rating * 10);

// 4. Determine Label and Color Class
$match_label = '';
$match_class = '';

if ($match_percent > 0) {
    $match_label = $match_percent . '% Match';
    
    // Assign Colors based on score
    if ($match_percent >= 70) {
        $match_class = 'high-score'; // Green
    } elseif ($match_percent >= 50) {
        $match_class = 'med-score';  // Yellow
    } else {
        $match_class = 'low-score';  // White/Grey
    }
} else {
    // Fallback if no data exists at all
    $match_label = 'New';
    $match_class = 'no-score';
}

/* =========================================================
   NEW: BADGE LOGIC (Top 10 & Recently Added)
   ========================================================= */

// 1. Top 10 Check
$is_top10 = false;
$trend_check_args = array(
    'post_type'      => array('movies', 'tvshows'),
    'posts_per_page' => 10,
    'meta_key'       => 'dt_views_count',
    'orderby'        => 'meta_value_num',
    'order'          => 'DESC',
    'fields'         => 'ids' 
);
$trend_query = new WP_Query($trend_check_args);
if ($trend_query->have_posts()) {
    if(in_array($post->ID, $trend_query->posts)) {
        $is_top10 = true;
    }
}
wp_reset_postdata();

// 2. Recently Added Check (2 Days)
$post_time = get_post_time('U', false, $post->ID);
$current_time = current_time('timestamp');
$diff_days = ($current_time - $post_time) / 86400; 
$is_recent = ($diff_days <= 2);


/* =========================================================
   UPDATED: COUNTRY LOGIC (Safety Check)
   ========================================================= */
$countries = get_the_terms($post->ID, 'country'); 
$country_name = '';

// Check if it's an array and not an error
if (!empty($countries) && !is_wp_error($countries) && isset($countries[0])) {
    $country_name = $countries[0]->name; 
}

/* =============================
   Helper Functions 
   ============================= */

// 1. YouTube Helper
if (!function_exists('ytembed')) {
    function ytembed($id){
        if (empty($id)) return '';
        if (strpos($id,'youtu') !== false){
            if (preg_match('/v=([^&]+)/',$id,$m)) return "https://www.youtube.com/embed/".$m[1];
            if (preg_match('#youtu\.be/([^?\s]+)#',$id,$m)) return "https://www.youtube.com/embed/".$m[1];
        }
        return "https://www.youtube.com/embed/".rawurlencode($id);
    }
}

// 2. Player Data Processor
function get_player_data($srv, $post_id) {
    $type = isset($srv['select']) ? $srv['select'] : 'iframe';
    $url  = isset($srv['url']) ? $srv['url'] : '';
    
    if (empty($url)) return false;

    if ($type === 'dtshcode') {
        return ['type' => 'html', 'content' => do_shortcode($url)];
    } elseif ($type === 'mp4' || $type === 'gdrive') {
        $base = function_exists('doo_compose_pagelink') ? doo_compose_pagelink('jwpage') : home_url('/jwplayer/');
        $src  = $base . "?source=" . urlencode($url) . "&id=" . $post_id . "&type=" . $type;
        return ['type' => 'url', 'content' => $src];
    } else {
        if (preg_match('/src=["\']([^"\']+)["\']/', $url, $m)) {
            $url = $m[1];
        }
        return ['type' => 'url', 'content' => $url];
    }
}

/* ==================================================
   FAKE PLAYER LOGIC
   ================================================== */
$enable_fake   = dooplay_get_option('fakeplayer');
$autoload_fake = dooplay_get_option('playautoload'); 
$fake_links    = dooplay_get_option('fakeplayerlinks');
$fake_backdrop = !empty($dynamicbg) ? $dynamicbg : dooplay_get_option('fakebackdrop'); 
$show_fake = ($enable_fake && !$autoload_fake && !empty($fake_links) && is_array($fake_links));

$fake_link_url = '';
if ($show_fake) {
    $rnd = array_rand($fake_links);
    $fake_link_url = esc_url($fake_links[$rnd]['link']);
}

/* =============================
   Data Preparation for Player
   ============================= */
$trailer_embed = (!empty($trailer)) ? ytembed($trailer) : '';
$sources_data = [];
$count = 0;

if (!empty($player) && is_array($player)) {
    foreach($player as $srv) {
        $processed = get_player_data($srv, $post->ID);
        if ($processed) {
            $label = !empty($srv['name']) ? $srv['name'] : "Server " . ($count + 1);
            $sources_data[] = [
                'label'   => $label,
                'type'    => $processed['type'],
                'content' => $processed['content']
            ];
            $count++;
        }
    }
}

if(!empty($trailer_embed)){
    $sources_data[] = [
        'label'   => 'Trailer',
        'type'    => 'url',
        'content' => $trailer_embed
    ];
}

?>

<div id="edit_link"></div>

<div id="single" class="dtsingle" itemscope itemtype="http://schema.org/Movie">

<?php if(have_posts()): while(have_posts()): the_post(); ?>

<section class="heroc" style="background-image:url('<?php echo $dynamicbg; ?>')">
    <div class="heroc-content">
        
        <div class="heroc-poster" style="display:block; position:relative;">
            
            <?php if($is_top10): ?>
            <div class="nfx-single-badge-top10">
                <span class="top">TOP</span>
                <span class="ten">10</span>
            </div>
            <?php endif; ?>

            <?php if($is_recent): ?>
            <div class="nfx-single-badge-recent">Recently added</div>
            <?php endif; ?>

            <img 
                class="lazy-load-hero skeleton lazy-loaded-img" 
                src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=" 
                data-src="<?php echo $poster_url; ?>" 
                alt="<?php the_title(); ?>"
            >
        </div>

        <div class="meta">
            <h1 class="title animate-on-load delay-2"><?php the_title(); ?></h1>
            
            <div class="badges animate-on-load delay-3">
                <span class="match-score <?php echo $match_class; ?>">
                    <?php echo $match_label; ?>
                </span> 
                
                <span><?php echo doo_isset($postmeta,'release_date'); ?></span>
                <?php if($r = doo_isset($postmeta,'runtime')) echo "<span>{$r} Min</span>"; ?>
                
                <?php if(!empty($country_name)): ?>
                <span class="country-pill">
                    <i class="fa-solid fa-earth-americas"></i> <?php echo $country_name; ?>
                </span>
                <?php endif; ?>
            </div>

            <div class="desc animate-on-load delay-4">
                <?php echo wp_trim_words(get_the_excerpt(), 45, '...'); ?>
            </div>

            <div class="hero-buttons animate-on-load delay-5">
                <a href="#player-wrapper" class="btn-hero btn-play">
                    <i class="fa fa-play"></i> <?php _d('Play'); ?>
                </a>
                
                <?php if(!empty($trailer_embed)): ?>
                <button class="btn-hero btn-more" id="openTrailerHero">
                    <i class="fa-solid fa-circle-info"></i> <?php _d('Trailer'); ?>
                </button>
                <?php endif; ?>
            </div>
            
            <div class="netflix-rate-wrap animate-on-load delay-5">
                <?php get_template_part('inc/parts/single/rate-post'); ?>
            </div>

            <div class="genres-box animate-on-load delay-5">
                <?php echo get_the_term_list($post->ID, 'genres', '', '  ', ''); ?>
            </div>

        </div>

        
    <div id="dt-director" class="hero-persons animate-on-load delay-5">
        <?php doo_director(doo_isset($postmeta,'dt_dir'), "img", true); ?>
    </div>

    </div>
    
</section>

<section id="player-wrapper" class="player-wrapper">
    <div class="player-header">
    <h3 style="color:white; margin:0; display:flex; align-items:center;">
    <img src="<?php echo get_stylesheet_directory_uri(); ?>/inc/parts/single/svg/watchnow.svg" width="50" height="50" alt="Watch Now" style="margin-right:6px;">
    Watch NOW
    </h3>
    </div>

    <div class="dooplay-ad-player" style="text-align:center; margin: 0 auto 15px; max-width: 100%; overflow: hidden;">
        <?php echo doo_compose_ad('_dooplay_adplayer'); ?>
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

    <?php if($show_fake): ?>
    <div class="fakeplayer-wrapper">
        <div id="fakeplayer" class="fakeplayer">
            <a id="clickfakeplayer" href="<?php echo $fake_link_url; ?>" target="_blank" rel="nofollow">
                <img class="cover" src="<?php echo $fake_backdrop; ?>" alt="Cover">
                <div class="playboxc">
                    <div style="position:relative;">
                        <div class="play-btn"><i class="fa-solid fa-play"></i></div>
                        <?php if(doo_is_true('fakeoptions','qua')) echo '<span class="quality">HD</span>'; ?>
                    </div>
                    <span class="play-text"><?php _d('Click to Play'); ?></span>
                    <?php if(doo_is_true('fakeoptions','ads')): ?>
                    <span class="ad-notice"><i class="fa-solid fa-circle-info"></i> <?php _d('Advertisement'); ?></span>
                    <?php endif; ?>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

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

    <div class="dooplay-ad-player" style="text-align:center; margin: 0 auto 15px; max-width: 100%; overflow: hidden;">
        <?php echo doo_compose_ad('_dooplay_adplayer'); ?>
    </div>

    <script>
        var playerSources = <?php echo json_encode($sources_data); ?>;
    </script>

</section>

<div class="tm" id="trailerModal">
    <div class="tm-box">
        <button class="tm-close" id="closeTrailer"><i class="fa-solid fa-xmark"></i></button>
        <?php if(!empty($trailer_embed)): ?>
        <iframe id="trailerFrame" src="" data-src="<?php echo $trailer_embed; ?>" allow="autoplay" allowfullscreen></iframe>
        <?php endif; ?>
    </div>
</div>

<div class="section">
    
    <h2 class="netflix-section-title"><?php _d('Director'); ?></h2>
    <div id="dt-director" class="persons">
        <?php doo_director(doo_isset($postmeta,'dt_dir'), "img", true); ?>
    </div>

    <h2 class="netflix-section-title"><?php _d('Cast'); ?></h2>
    <div id="dt-cast" class="persons">
        <?php doo_cast(doo_isset($postmeta,'dt_cast'), "img", true); ?>
    </div>

    <div class="dooplay-ad-single" style="text-align:center; margin: 40px 0 20px;">
        <?php echo doo_compose_ad('_dooplay_adsingle'); ?>
    </div>

    <?php if(DOO_THEME_DOWNLOAD_MOD): ?>
        <?php get_template_part('inc/parts/single/links'); ?>
    <?php endif; ?>
    <div class="dooplay-ad-single" style="text-align:center; margin: 40px 0 20px;">
        <?php echo doo_compose_ad('_dooplay_adsingle'); ?>
    </div>

    <?php if(DOO_THEME_RELATED): ?>
        <h2 class="netflix-section-title"><?php _d('More Like This'); ?></h2>
        <?php get_template_part('inc/parts/single/relacionados'); ?>
    <?php endif; ?>

    <div class="dooplay-ad-comments" style="text-align:center; margin: 20px 0;">
        <?php echo doo_compose_ad('_dooplay_ad_single'); ?>
    </div>

    <div style="margin-top:40px;">
        <?php get_template_part('inc/parts/comments'); ?>
    </div>

</div>

<?php endwhile; endif; ?>

</div>