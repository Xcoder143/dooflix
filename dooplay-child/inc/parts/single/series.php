<?php
/*
* -------------------------------------------------------------------------------------
* FINAL SERIES TEMPLATE (Updated: Base64 Netflix Logo)
* -------------------------------------------------------------------------------------
*/

// 1. GET VARIABLES
$postmeta   = doo_postmeta_tvshows($post->ID);
$adsingle   = doo_compose_ad('_dooplay_adsingle');

// Data
$pviews     = doo_isset($postmeta,'dt_views_count');
$air_date   = doo_isset($postmeta,'first_air_date');
$year       = ($air_date) ? substr($air_date, 0, 4) : '';
$seasons    = doo_isset($postmeta,'number_of_seasons');
$images     = doo_isset($postmeta,'imagenes');
$genres     = get_the_term_list($post->ID, 'genres', '', '', '');

/* =========================================================
   NETWORK LOGO LOGIC (Base64 + Full Logos)
   ========================================================= */
$network_logo_html = '';
$networks = get_the_terms($post->ID, 'dtnetwork');

// Fallback to plural 'dtnetworks' if singular is empty
if (!$networks || is_wp_error($networks)) {
    $networks = get_the_terms($post->ID, 'dtnetworks');
}

if ($networks && !is_wp_error($networks)) {
    // Get the first available network
    $net = array_values($networks)[0];
    $net_name = $net->name;
    $n_lower = strtolower($net_name);
    $logo_url = '';

    // 1. Check for NETFLIX (Use Base64)
    if (strpos($n_lower, 'netflix') !== false) {
        $logo_url = 'https://upload.wikimedia.org/wikipedia/commons/0/08/Netflix_2015_logo.svg';
    } 
    // 2. Check Other Networks
    elseif (strpos($n_lower, 'hbo') !== false) {
        $logo_url = 'https://upload.wikimedia.org/wikipedia/commons/d/de/HBO_logo.svg';
    } elseif (strpos($n_lower, 'disney') !== false) {
        $logo_url = 'https://upload.wikimedia.org/wikipedia/commons/3/3e/Disney%2B_logo.svg';
    } elseif (strpos($n_lower, 'amazon') !== false || strpos($n_lower, 'prime') !== false) {
        $logo_url = 'https://upload.wikimedia.org/wikipedia/commons/1/11/Amazon_Prime_Video_logo.svg';
    } elseif (strpos($n_lower, 'hulu') !== false) {
        $logo_url = 'https://upload.wikimedia.org/wikipedia/commons/e/e4/Hulu_Logo.svg';
    } elseif (strpos($n_lower, 'apple') !== false) {
        $logo_url = 'https://upload.wikimedia.org/wikipedia/commons/2/28/Apple_TV_Plus_Logo.svg';
    } elseif (strpos($n_lower, 'amc') !== false) {
        $logo_url = 'https://upload.wikimedia.org/wikipedia/commons/1/1d/AMC_Networks_logo.svg';
    } elseif (strpos($n_lower, 'cw') !== false) {
        $logo_url = 'https://upload.wikimedia.org/wikipedia/commons/2/26/The_CW_Network_logo.svg';
    } elseif (strpos($n_lower, 'fx') !== false) {
        $logo_url = 'https://upload.wikimedia.org/wikipedia/commons/9/9d/FX_Network_logo.svg';
    } elseif (strpos($n_lower, 'showtime') !== false) {
        $logo_url = 'https://upload.wikimedia.org/wikipedia/commons/2/22/Showtime.svg';
    } elseif (strpos($n_lower, 'starz') !== false) {
        $logo_url = 'https://upload.wikimedia.org/wikipedia/commons/6/62/Starz_logo.svg';
    } elseif (strpos($n_lower, 'paramount') !== false) {
        $logo_url = 'https://upload.wikimedia.org/wikipedia/commons/8/81/Paramount%2B_logo.svg';
    }

    // 3. Generate HTML
    $presented_by = (strpos($n_lower, 'netflix') !== false) ? 'A NETFLIX SERIES' : 'A ' . strtoupper($net_name) . ' SERIES';
    
    // Output Logo or Text Fallback
    if (!empty($logo_url)) {
        // We use the variable directly here since it's a known string or base64
        $network_logo_html = '
        <div class="nfx-hero-network">
            <span class="nfx-presented-by">' . $presented_by . '</span>
            <img src="' . $logo_url . '" alt="' . esc_attr($net_name) . '" class="nfx-net-logo-img">
        </div>';
    } else {
        $network_logo_html = '
        <div class="nfx-hero-network">
            <span class="nfx-presented-by">' . $presented_by . '</span>
            <span class="nfx-net-logo-text">' . esc_html($net_name) . '</span>
        </div>';
    }
}

/* =========================================================
   MATCH PERCENTAGE LOGIC
   ========================================================= */
$imdb_rate = doo_isset($postmeta, 'imdbRating');
$tmdb_rate = doo_isset($postmeta, 'vote_average');
$user_rate = doo_isset($postmeta, 'dt_rates_average');

$final_rating = 0;
if (!empty($imdb_rate) && $imdb_rate > 0) $final_rating = floatval($imdb_rate);
elseif (!empty($tmdb_rate) && $tmdb_rate > 0) $final_rating = floatval($tmdb_rate);
elseif (!empty($user_rate) && $user_rate > 0) $final_rating = floatval($user_rate);

$match_percent = round($final_rating * 10);
$match_label   = ($match_percent > 0) ? $match_percent . '% Match' : 'New';

// Color Class
if ($match_percent >= 70) $match_class = 'high-score'; 
elseif ($match_percent >= 50) $match_class = 'med-score';  
else $match_class = 'low-score';

/* =========================================================
   BADGE LOGIC (Top 10 & Recently Added)
   ========================================================= */
$is_top10 = false;
$trend_check_args = array(
    'post_type'      => array('movies', 'tvshows'),
    'posts_per_page' => 10,
    'meta_key'       => 'dt_views_count',
    'orderby'        => 'meta_value_num',
    'order'          => 'DESC',
    'fields'         => 'ids',
    'no_found_rows'  => true
);
$trend_query = new WP_Query($trend_check_args);
if ($trend_query->have_posts() && in_array($post->ID, $trend_query->posts)) {
    $is_top10 = true;
}

// Recently Added Check
$post_time = get_post_time('U', false, $post->ID);
$is_recent = ((current_time('timestamp') - $post_time) / 86400) <= 2;

// CAST & CREATORS
$cast_raw = doo_isset($postmeta, 'cast');
if (empty($cast_raw)) $cast_raw = doo_isset($postmeta, 'dt_cast');
$cast = maybe_unserialize($cast_raw); 

$creator_raw = doo_isset($postmeta, 'creator');
if (empty($creator_raw)) $creator_raw = doo_isset($postmeta, 'dt_creator');
$creators = maybe_unserialize($creator_raw);

// Images
$poster_url = dbmovies_get_poster($post->ID, 'medium'); 
$backdrop   = dbmovies_get_rand_image($images); 
if(empty($backdrop)) $backdrop = $poster_url;

doo_set_views($post->ID); 
?>

<style>
    .sidebar, #sidebar, .secondary { display: none !important; }
    #content, .module, .content, .main-content { width: 100% !important; max-width: 100% !important; padding: 0 !important; margin: 0 !important; background: #141414; }
    .heroc-poster { display: block !important; opacity: 1 !important; visibility: visible !important; }
    .match-score.high-score { color: #46d369; font-weight: bold; }
    .match-score.med-score { color: #ffc107; font-weight: bold; }
    .match-score.low-score { color: #fff; }
    .match-score.no-score { color: #ccc; }
    
    /* Logo Styles Override */
    .nfx-net-logo-img {
        height: 60px !important; 
        width: auto;
        display: block;
        filter: drop-shadow(0 4px 8px rgba(0,0,0,0.6));
    }
</style>

<div id="single" class="dtsingle">

    <div class="heros" style="background-image: url('<?php echo $backdrop; ?>');">
        
        

        <div class="heros-content animate-on-load">
            
            <div class="heros-poster" style="display:block; position:relative;">
                <?php if($is_top10): ?>
                <div class="nfx-single-badge-top10">
                    <span class="top">TOP</span>
                    <span class="ten">10</span>
                </div>
                <?php endif; ?>
                
                <?php if($is_recent): ?>
                    <div class="nfx-single-badge-recent">Recently added</div>
                <?php endif; ?>

                <img src="<?php echo $poster_url; ?>" alt="<?php the_title(); ?>">
            </div>

            <div class="meta">
                <h1 class="title animate-on-load delay-1"><?php the_title(); ?></h1>
                
                <div class="badges animate-on-load delay-2">
                    <?php if(!empty($match_label)) { ?>
                        <span class="match-score <?php echo $match_class; ?>"><?php echo $match_label; ?></span>
                    <?php } ?>

                    <?php if($year) { ?>
                        <span class="country-pill"><?php echo $year; ?></span>
                    <?php } ?>

                    <span class="country-pill" style="border: 1px solid #ccc; background:transparent;">HD</span>

                    <?php if($seasons) { ?>
                        <span class="country-pill"><?php echo $seasons; ?> <?php _d('Seasons'); ?></span>
                    <?php } ?>
                </div>

                <div class="desc animate-on-load delay-3">
                    <?php echo wp_trim_words( get_the_content(), 55, '...' ); ?>
                </div>

                <div class="hero-buttons animate-on-load delay-4">
                    <a href="#seasons-scroll" class="btn-hero btn-play">
                        <i class="fas fa-play"></i> <?php _d('Play'); ?>
                    </a>
                    <a href="#details-scroll" class="btn-hero btn-more">
                        <i class="fas fa-info-circle"></i> <?php _d('More Info'); ?>
                    </a>
                </div>

                <div class="netflix-rate-wrap animate-on-load delay-5">
                    <?php get_template_part('inc/parts/single/rate-post'); ?>
                </div>

                <div class="genres-box animate-on-load delay-5">
                    <?php echo $genres; ?>
                </div>

                <?php echo $network_logo_html; ?>

            </div>
        </div>
    </div>
    <div class="banner__fadeBottoms"></div>
    
    <div id="seasons-scroll" class="section animate-on-load delay-long">
        <h3 class="netflix-section-title"><?php _d('Seasons & Episodes'); ?></h3>
        <div class="custom-seasons-wrapper">
            <?php get_template_part('inc/parts/single/listas/seasons'); ?>
        </div>
    </div>

    <div id="details-scroll" class="section">
        <?php if($creators) { ?>
            <h2 class="netflix-section-title"><?php _d('Creators'); ?></h2>
            <div id="dt-director" class="persons">
                <?php doo_creator($creators, "img", true); ?>
            </div>
        <?php } ?>

        <?php if($cast) { ?>
            <div style="margin-top: 40px;">
                <h2 class="netflix-section-title"><?php _d('Cast'); ?></h2>
                <div id="dt-cast" class="persons">
                    <?php doo_cast($cast, "img", true); ?>
                </div>
            </div>
        <?php } ?>
    </div>

    <?php if(defined('DOO_THEME_RELATED') && DOO_THEME_RELATED) { ?>
    <div class="section">
        <h2 class="netflix-section-title"><?php _d('More Like This'); ?></h2>
        <?php get_template_part('inc/parts/single/relacionados'); ?>
    </div>
    <?php } ?>

    <div class="section">
        <h2 class="netflix-section-title"><?php _d('Comments'); ?></h2>
        <?php get_template_part('inc/parts/comments'); ?>
    </div>

</div>