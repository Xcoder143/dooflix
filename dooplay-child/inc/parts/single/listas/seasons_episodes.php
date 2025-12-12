<?php
/*
* -------------------------------------------------------------------------------------
* CUSTOM EPISODE LIST (Debug Version: Fixes Watermark & Runtime)
* -------------------------------------------------------------------------------------
*/

// 1. CONTEXT CHECK
$is_single_season_page = (get_post_type($post->ID) == 'seasons');
$current_page_season_num = ($is_single_season_page) ? get_post_meta($post->ID, 'temporada', true) : null;

// 2. DATA FETCHING
$tmdb_id = get_post_meta($post->ID, 'ids', true);
$seasons = DDbmoviesHelpers::GetAllSeasons($tmdb_id);

// 3. FETCH PARENT TV SHOW DATA
$tvshow_id = doo_get_tvpermalink($tmdb_id); 

// Fallback: Manual search if theme function fails
if (!$tvshow_id) {
    $args = array(
        'post_type'  => 'tvshows',
        'meta_key'   => 'ids',
        'meta_value' => $tmdb_id,
        'posts_per_page' => 1,
        'fields'     => 'ids'
    );
    $parent_query = new WP_Query($args);
    if ($parent_query->have_posts()) {
        $tvshow_id = $parent_query->posts[0];
    }
}

// 4. PREPARE NETWORK DATA & RUNTIME
$network_name = '';
$network_logo = '';
$series_runtime = '';

if ($tvshow_id) {
    // A. GET NETWORK (Try both 'dtnetwork' and 'dtnetworks')
    $networks = get_the_terms($tvshow_id, 'dtnetwork');
    if (!$networks || is_wp_error($networks)) {
        $networks = get_the_terms($tvshow_id, 'dtnetworks');
    }

    if ($networks && !is_wp_error($networks) && !empty($networks)) {
        $network_name = $networks[0]->name; 
        
        // B. MAP NETWORK TO LOGO (Full Logos)
        $n_lower = strtolower($network_name);
        
        if (strpos($n_lower, 'netflix') !== false) {
        $logo_url = 'https://upload.wikimedia.org/wikipedia/commons/0/08/Netflix_2015_logo.svg';
        } elseif (strpos($n_lower, 'hbo') !== false) {
            $network_logo = 'https://upload.wikimedia.org/wikipedia/commons/d/de/HBO_logo.svg';
        } elseif (strpos($n_lower, 'disney') !== false) {
            $network_logo = 'https://upload.wikimedia.org/wikipedia/commons/3/3e/Disney%2B_logo.svg';
        } elseif (strpos($n_lower, 'amazon') !== false || strpos($n_lower, 'prime') !== false) {
            $network_logo = 'https://upload.wikimedia.org/wikipedia/commons/1/11/Amazon_Prime_Video_logo.svg';
        } elseif (strpos($n_lower, 'hulu') !== false) {
            $network_logo = 'https://upload.wikimedia.org/wikipedia/commons/e/e4/Hulu_Logo.svg';
        } elseif (strpos($n_lower, 'apple') !== false) {
            $network_logo = 'https://upload.wikimedia.org/wikipedia/commons/2/28/Apple_TV_Plus_Logo.svg';
        } elseif (strpos($n_lower, 'amc') !== false) {
            $network_logo = 'https://upload.wikimedia.org/wikipedia/commons/1/1d/AMC_Networks_logo.svg';
        } elseif (strpos($n_lower, 'cw') !== false) {
            $network_logo = 'https://upload.wikimedia.org/wikipedia/commons/2/26/The_CW_Network_logo.svg';
        } elseif (strpos($n_lower, 'fx') !== false) {
            $network_logo = 'https://upload.wikimedia.org/wikipedia/commons/9/9d/FX_Network_logo.svg';
        } elseif (strpos($n_lower, 'showtime') !== false) {
            $network_logo = 'https://upload.wikimedia.org/wikipedia/commons/2/22/Showtime.svg';
        } elseif (strpos($n_lower, 'starz') !== false) {
            $network_logo = 'https://upload.wikimedia.org/wikipedia/commons/6/62/Starz_logo.svg';
        } elseif (strpos($n_lower, 'paramount') !== false) {
            $network_logo = 'https://upload.wikimedia.org/wikipedia/commons/8/81/Paramount%2B_logo.svg';
        }
    }

    // C. Get Series Average Runtime (Fallback)
    $series_runtime = get_post_meta($tvshow_id, 'episode_run_time', true);
    if (is_array($series_runtime)) $series_runtime = reset($series_runtime);
    if (empty($series_runtime) || $series_runtime == '0') {
        $series_runtime = get_post_meta($tvshow_id, 'runtime', true);
    }
}

// DEBUG: Output invisible comments to check data
echo "";

if($seasons && is_array($seasons)) {
    
    $count = 0;
    foreach($seasons as $season_id) {
        $count++;
        $season_num = get_post_meta($season_id, 'temporada', true);
        
        // Filter for Single Season Page
        if ($is_single_season_page && $season_num != $current_page_season_num) {
            continue; 
        }

        // Visibility
        $display_style = ($count == 1 && !$is_single_season_page) ? 'display:block;' : 'display:none;';
        if ($is_single_season_page) $display_style = 'display:block;';

        // Get Episodes
        $episodes = DDbmoviesHelpers::GetAllEpisodes($tmdb_id, $season_num);
        
        echo '<div class="nfx-season-grid" id="season-'.$season_num.'" style="'.$display_style.'">';
        
        if($episodes && is_array($episodes)) {
            echo '<div class="nfx-episodes-list">';
            
            foreach($episodes as $episode_id) {
                // DATA
                $title  = get_post_meta($episode_id, 'episode_name', true);
                $ep_num = get_post_meta($episode_id, 'episodio', true);
                $plot   = get_post_meta($episode_id, 'dt_plot', true); 
                $air_date = get_post_meta($episode_id, 'air_date', true);
                $link   = get_permalink($episode_id);
                
                // Runtime Logic
                $ep_runtime = get_post_meta($episode_id, 'runtime', true);
                if(empty($ep_runtime)) $ep_runtime = get_post_meta($episode_id, 'episode_run_time', true);
                $final_runtime = (intval($ep_runtime) > 0) ? $ep_runtime : $series_runtime;

                if(empty($title)) $title = __d('Episode').' '.$ep_num;
                
                // IMAGE
                $img = dbmovies_get_poster($episode_id, 'dt_episode_a', 'dt_backdrop', 'w300');
                if(empty($img)) $img = DOO_URI . '/assets/img/no/dt_backdrop.png';

                ?>
                <a href="<?php echo $link; ?>" class="nfx-ep-row">
                    
                    <div class="nfx-ep-num"><?php echo $ep_num; ?></div>
                    
                    <div class="nfx-ep-thumb">
                        <img src="<?php echo $img; ?>" alt="<?php echo $title; ?>">
                        <div class="nfx-play-icon"></div>
                    </div>
                    
                    <div class="nfx-ep-details">
                        <div class="nfx-ep-top">
                            <h4 class="nfx-ep-title"><?php echo $title; ?></h4>
                            <?php if(!empty($final_runtime) && $final_runtime > 0): ?>
                                <span class="nfx-ep-runtime" style="color:#a3a3a3; font-size:0.9rem; font-weight:600; margin-left:12px;">
                                    <?php echo $final_runtime; ?>m
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="nfx-ep-desc"><?php echo wp_trim_words($plot, 25, '...'); ?></p>
                        
                        <?php if($air_date): ?>
                            <div style="color:#666; font-size:0.8rem; margin-top:4px;">
                                <?php echo doo_date_compose($air_date, false); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="nfx-studio-mark">
                        <?php if(!empty($network_logo)): ?>
                            <img src="<?php echo esc_url($network_logo); ?>" alt="<?php echo esc_attr($network_name); ?>" 
                                 style="max-height:40px; width:auto; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5)); opacity:0.8;">
                        <?php elseif(!empty($network_name)): ?>
                            <?php echo esc_html($network_name); ?>
                        <?php endif; ?>
                    </div>

                </a>
                <?php
            }
            echo '</div>'; 
        } else {
            echo '<div style="padding:30px; color:#777; text-align:center;">'.__d('No episodes available.').'</div>';
        }
        
        echo '</div>'; 
    }

} else {
    echo '<div style="padding:30px; color:#777;">'.__d('No seasons found.').'</div>';
}
?>