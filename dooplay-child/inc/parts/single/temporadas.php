<?php
/*
* -------------------------------------------------------------------------------------
* @author: Doothemes
* @author URI: https://doothemes.com/
* -------------------------------------------------------------------------------------
* Redesigned for Netflix Style (Child Theme) - Single Season Specific
*/

// 1. GET VARIABLES & META
$postmeta = doo_postmeta_seasons($post->ID);
$adsingle = doo_compose_ad('_dooplay_adsingle');

// Main data
$ids    = doo_isset($postmeta,'ids'); // TMDB ID of the Series
$temp   = doo_isset($postmeta,'temporada'); // Current Season Number
$clgnrt = doo_isset($postmeta,'clgnrt');

// Parent TV Show Info (To get backdrop and link)
$tvshow_id = doo_get_tvpermalink($ids); 
$tvshow_link = get_permalink($tvshow_id);
$tvshow_title = get_the_title($tvshow_id);

// Title Options
$title_opti = dooplay_get_option('dbmvstitleseasons', __d('{name}: Season {season}'));
$title_data = array(
    'name'   => $tvshow_title,
    'season' => $temp
);
$final_title = dbmovies_title_tags($title_opti, $title_data);

// Images
$poster_url = dbmovies_get_poster($post->ID, 'medium');

// Backdrop Logic: Try Season Image -> Parent TV Show Image -> Fallback
$season_images = doo_isset($postmeta, 'imagenes'); 
$parent_images = get_post_meta($tvshow_id, 'imagenes', true); 

$backdrop = '';
if(!empty($season_images)) {
    $backdrop = dbmovies_get_rand_image($season_images);
} elseif(!empty($parent_images)) {
    $backdrop = dbmovies_get_rand_image($parent_images);
}
if(empty($backdrop)) $backdrop = $poster_url;

// Air Date / Year
$air_date = doo_isset($postmeta, 'air_date');
$year = ($air_date) ? substr($air_date, 0, 4) : '';

// Description
$content = get_the_content();
$desc = wp_trim_words($content, 55, '...');

// Studio/Network Info (for watermark)
$networks = get_the_terms($tvshow_id, 'dtnetwork');
$studio_text = '';
if ($networks && !is_wp_error($networks)) {
    $studio_text = $networks[0]->name;
}

// Update Views
if(function_exists('doo_set_views')) doo_set_views($post->ID);
?>

<style>
    .sidebar, #sidebar, .secondary { display: none !important; }
    #content, .module, .content, .main-content { width: 100% !important; max-width: 100% !important; padding: 0 !important; margin: 0 !important; background: #141414; }
    .heros-poster { display: block !important; opacity: 1 !important; visibility: visible !important; }
</style>

<div id="single" class="dtsingle">

    <div class="heros" style="background-image: url('<?php echo esc_url($backdrop); ?>');">
        
        <div class="heros-content animate-on-load">
            
            <div class="heros-poster" style="display:block; position:relative;">
                <img src="<?php echo esc_url($poster_url); ?>" alt="<?php echo esc_attr($final_title); ?>">
            </div>

            <div class="meta">
                <h1 class="title animate-on-load delay-1"><?php echo $final_title; ?></h1>
                
                <div class="badges animate-on-load delay-2">
                    <?php if($year) { ?>
                        <span class="country-pill"><?php echo $year; ?></span>
                    <?php } ?>

                    <span class="country-pill" style="border: 1px solid #ccc; background:transparent;">HD</span>

                    <span class="country-pill"><?php echo sprintf(__d('Season %s'), $temp); ?></span>
                </div>

                <?php if($desc) { ?>
                <div class="desc animate-on-load delay-3">
                    <?php echo $desc; ?>
                </div>
                <?php } ?>

                <div class="hero-buttons animate-on-load delay-4">
                    <a href="#episodes-scroll" class="btn-hero btn-play">
                        <i class="fas fa-play"></i> <?php _d('Play:$tempE01'); ?>
                    </a>
                    
                    <a href="<?php echo esc_url($tvshow_link); ?>" class="btn-hero btn-more">
                        <i class="fas fa-arrow-left"></i> <?php _d('Back to Show'); ?>
                    </a>
                </div>

                <div class="netflix-rate-wrap animate-on-load delay-5">
                    <?php echo do_shortcode('[starstruck_shortcode]'); ?>
                </div>

            </div>
        </div>
    </div>
    <div class="banner__fadeBottoms"></div>
    
    <div id="episodes-scroll" class="section animate-on-load delay-long">
        
        <?php if($adsingle) echo '<div class="module_single_ads" style="margin-bottom:30px;">'.$adsingle.'</div>'; ?>

        <h3 class="netflix-section-title"><?php _d('Episodes'); ?></h3>
        
        <div class="custom-seasons-wrapper">
            <?php 
            // ============================================================
            // CUSTOM EPISODE LOOP (Only shows episodes for THIS season)
            // ============================================================
            
            // 1. Fetch Episodes specifically for this Season Number
            $episodes = DDbmoviesHelpers::GetAllEpisodes($ids, $temp);

            if($episodes && is_array($episodes)) {
                echo '<div class="nfx-episodes-list">';
                
                foreach($episodes as $episode_id) {
                    // DATA
                    $ep_title = get_post_meta($episode_id, 'episode_name', true);
                    $ep_num   = get_post_meta($episode_id, 'episodio', true);
                    $ep_plot  = get_post_meta($episode_id, 'dt_plot', true); 
                    $ep_date  = get_post_meta($episode_id, 'air_date', true);
                    $ep_link  = get_permalink($episode_id);
                    
                    if(empty($ep_title)) $ep_title = __d('Episode').' '.$ep_num;
                    
                    // IMAGE
                    $ep_img = dbmovies_get_poster($episode_id, 'dt_episode_a', 'dt_backdrop', 'w300');
                    if(empty($ep_img)) $ep_img = DOO_URI . '/assets/img/no/dt_backdrop.png';

                    ?>
                    <a href="<?php echo $ep_link; ?>" class="nfx-ep-row">
                        
                        <div class="nfx-ep-num"><?php echo $ep_num; ?></div>
                        
                        <div class="nfx-ep-thumb">
                            <img src="<?php echo $ep_img; ?>" alt="<?php echo $ep_title; ?>">
                            <div class="nfx-play-icon"></div>
                        </div>
                        
                        <div class="nfx-ep-details">
                            <div class="nfx-ep-top">
                                <h4 class="nfx-ep-title"><?php echo $ep_title; ?></h4>
                                <span class="nfx-ep-date"><?php echo ($ep_date) ? doo_date_compose($ep_date, false) : ''; ?></span>
                            </div>
                            <p class="nfx-ep-desc"><?php echo wp_trim_words($ep_plot, 25, '...'); ?></p>
                        </div>

                        <?php if($studio_text) { ?>
                            <div class="nfx-studio-mark"><?php echo $studio_text; ?></div>
                        <?php } ?>

                    </a>
                    <?php
                }
                echo '</div>'; 
            } else {
                echo '<div style="padding:30px; color:#777; text-align:center;">'.__d('No episodes available yet.').'</div>';
            }
            ?>
        </div>
    </div>

    <div class="section">
        <h2 class="netflix-section-title"><?php _d('Comments'); ?></h2>
        <?php get_template_part('inc/parts/comments'); ?>
    </div>

</div>