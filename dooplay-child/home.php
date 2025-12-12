<?php
/*
* Template Name: Netflix Home Native (Final + Fixed Regex)
* Description: Homepage with Auto-Sliding Billboard, Mobile Fixes, and Fixed Logo Parsing.
*/

get_header(); 

// =========================================================
// 1. GLOBAL DATA FETCHING
// =========================================================
$global_trending_ids = array();
$trend_check_args = array(
    'post_type'      => array('movies', 'tvshows'),
    'posts_per_page' => 10,
    'meta_key'       => 'dt_views_count',
    'orderby'        => 'meta_value_num',
    'order'          => 'DESC',
    'fields'         => 'ids'
);
$trend_check_query = new WP_Query($trend_check_args);
if ($trend_check_query->have_posts()) {
    $global_trending_ids = $trend_check_query->posts;
}
wp_reset_postdata();


// =========================================================
// 2. HELPER FUNCTIONS
// =========================================================

function netflix_fix_img_url($url, $size = 'w780') {
    if(empty($url)) return '';
    // Fix: Ensure we trim any accidental whitespace
    $url = trim($url);
    if (substr($url, 0, 1) === '/' && substr($url, 0, 2) !== '//') {
        return 'https://image.tmdb.org/t/p/' . $size . $url;
    }
    return $url;
}

function netflix_get_year($post_id) {
    $terms = get_the_terms($post_id, 'dtyear');
    if (!empty($terms) && !is_wp_error($terms)) {
        return $terms[0]->name; 
    }
    $date = get_post_meta($post_id, 'release_date', true); 
    if(empty($date)) $date = get_post_meta($post_id, 'first_air_date', true);
    
    if(!empty($date)) return substr($date, 0, 4);
    return get_the_date('Y', $post_id);
}

function netflix_get_card_meta($post_id) {
    $post_type = get_post_type($post_id);

    // Runtime
    $runtime = '';
    if($post_type == 'tvshows') {
        $runtime = get_post_meta($post_id, 'episode_run_time', true);
    } else {
        $runtime = get_post_meta($post_id, 'runtime', true);
    }
    
    if(is_numeric($runtime) && $runtime > 0) {
        $hours = floor($runtime / 60);
        $minutes = $runtime % 60;
        $runtime = ($hours > 0 ? $hours . 'h ' : '') . $minutes . 'm';
    } else {
        $runtime = '';
    }

    // Age Rating
    $mpaa = get_post_meta($post_id, 'Rated', true); 
    if(empty($mpaa)) $mpaa = get_post_meta($post_id, 'dt_mpaa', true);
    if(empty($mpaa)) $mpaa = get_post_meta($post_id, 'mpaa', true);
    if(empty($mpaa)) $mpaa = '12+'; 

    // Match %
    $imdb = get_post_meta($post_id, 'imdbRating', true);
    $val = ($imdb) ? floatval($imdb) * 10 : 0;
    $match_label = ($val > 0) ? floor($val) . '% Match' : 'New';
    
    if ($val >= 70) $match_class = 'high-score'; 
    elseif ($val >= 50) $match_class = 'med-score';  
    elseif ($val > 0) $match_class = 'low-score';  
    else $match_class = 'no-score';   

    // Genres
    $terms = get_the_terms($post_id, 'genres');
    $genre_list = array();
    if ($terms && !is_wp_error($terms)) {
        foreach (array_slice($terms, 0, 2) as $term) {
            $genre_list[] = $term->name;
        }
    }
    $genre_str = implode(' &bull; ', $genre_list);

    return array(
        'runtime' => $runtime,
        'mpaa'    => $mpaa,
        'match'   => $match_label,
        'class'   => $match_class,
        'genres'  => $genre_str
    );
}

// =========================================================
// 3. BILLBOARD QUERY
// =========================================================
$billboard_args = array(
    'post_type'      => array('movies', 'tvshows'),
    'posts_per_page' => 10,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'meta_query'     => array(
        'relation' => 'AND',
        array('key' => 'dt_backdrop', 'compare' => 'EXISTS')
    )
);
$billboard_query = new WP_Query($billboard_args);
?>

<div id="netflix-wrapper">
    
    <div id="nfx-billboard-slider" class="nfx-billboard-slider">
    <?php 
    if ($billboard_query->have_posts()) : 
        $slide_index = 0;
        while ($billboard_query->have_posts()) : $billboard_query->the_post(); 
            
            // -------------------------------------------------------------
            // 1. GET DATA
            // -------------------------------------------------------------
            $raw_backdrop = get_post_meta($post->ID, 'dt_backdrop', true);
            
            // Try standard gallery key first, then dt_images
            $gallery_images = get_post_meta($post->ID, 'dt_images', true); 
            if(empty($gallery_images)) $gallery_images = get_post_meta($post->ID, 'dt_gallery', true);

            $logo_url = '';

            // -------------------------------------------------------------
            // 2. SEARCH FOR LOGO (Fixed Regex)
            // -------------------------------------------------------------
            
            // CHECK A: Look inside the "Backdrops" list
            if (!empty($gallery_images) && strpos($gallery_images, '[logo]') !== false) {
                // BUG FIX: Added \[ to excluded chars so we stop AT the bracket
                if (preg_match('/([^\s\r\n\[]+)\s*\[logo\]/i', $gallery_images, $matches)) {
                    $logo_url = netflix_fix_img_url($matches[1], 'original');
                }
            }

            // CHECK B: Look inside "Main Backdrop" field
            if (empty($logo_url) && !empty($raw_backdrop) && strpos($raw_backdrop, '[logo]') !== false) {
                if (preg_match('/([^\s\r\n\[]+)\s*\[logo\]/i', $raw_backdrop, $matches)) {
                    $logo_url = netflix_fix_img_url($matches[1], 'original');
                    // Remove logo tag from background string
                    $raw_backdrop = str_replace($matches[0], '', $raw_backdrop);
                }
            }
            
            $bg_image = netflix_fix_img_url(trim($raw_backdrop), 'original');
            
            // Fallbacks
            if(empty($bg_image)) $bg_image = get_the_post_thumbnail_url($post->ID, 'full');
            if(empty($bg_image) && defined('DOO_URI')) $bg_image = DOO_URI . '/assets/img/no/dt_backdrop.png';
            
            $poster_image = netflix_fix_img_url(get_post_meta($post->ID, 'dt_poster', true), 'w780');
            if(empty($poster_image) && defined('DOO_URI')) $poster_image = DOO_URI . '/assets/img/no/dt_poster.png';
            if(empty($poster_image)) $poster_image = $bg_image; 

            $b_meta = netflix_get_card_meta($post->ID);
            $desc = wp_trim_words(get_the_excerpt(), 30, '...');
            
            $terms = get_the_terms($post->ID, 'genres');
            $genre_html = '';
            if ($terms && !is_wp_error($terms)) {
                $names = wp_list_pluck($terms, 'name');
                $genre_html = implode(' <span class="mob-dot">&bull;</span> ', array_slice($names, 0, 3)); 
            }

            $active_class = ($slide_index === 0) ? 'active' : '';
    ?>
    
    <div class="banner-slide <?php echo $active_class; ?>" style="background-image: url('<?php echo esc_url($bg_image); ?>');">
        
        <div class="banner__contents">
            
            <?php if (!empty($logo_url)) : ?>
                <div class="banner__logo_wrapper" style="margin-bottom: 20px;">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php the_title_attribute(); ?>" style="max-width: 450px; max-height: 180px; width: auto; display: block;" class="movie-logo-img">
                </div>
            <?php else : ?>
                <h1 class="banner__title"><?php the_title(); ?></h1>
            <?php endif; ?>

            <div class="banner__meta">
                <span class="match-score <?php echo $b_meta['class']; ?>"><?php echo $b_meta['match']; ?></span>
                <span class="year"><?php echo netflix_get_year($post->ID); ?></span>
                <span class="hd-badge">HD</span>
                <span class="age-badge"><?php echo $b_meta['mpaa']; ?></span>
            </div>
            <div class="banner__description"><?php echo $desc; ?></div>
            <div class="banner__buttons">
                <a href="<?php the_permalink(); ?>" class="banner__button play"><i class="fas fa-play"></i> Play</a>
                <a href="<?php the_permalink(); ?>" class="banner__button info"><i class="fas fa-info-circle"></i> More Info</a>
            </div>
        </div>
        <div class="banner__fadeBottom"></div>

        <div class="nfx-mobile-billboard">
            <div class="mob-poster-card">
                <img src="<?php echo esc_url($poster_image); ?>" alt="<?php the_title(); ?>" class="mob-poster-img">
                <div class="mob-poster-overlay">
                    <h2 class="mob-title"><?php the_title(); ?></h2>
                    <div class="mob-genres"><?php echo $genre_html; ?></div>
                    <div class="mob-actions">
                        <a href="<?php the_permalink(); ?>" class="mob-btn play"><i class="fas fa-play"></i> Play</a>
                        <a href="<?php the_permalink(); ?>" class="mob-btn list"><i class="fas fa-plus"></i> My List</a>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <?php 
        $slide_index++;
        endwhile; 
        wp_reset_postdata(); 
    endif; 
    ?>
    </div> <div class="netflix-content">
        <div class="banner__fadeBottoma"></div>

        <?php 
        // =========================================================
        // SECTION A: TRENDING NOW (Ranked)
        // =========================================================
        $trending_args = array(
            'post_type'      => array('movies', 'tvshows'),
            'posts_per_page' => 10,
            'meta_key'       => 'dt_views_count',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC'
        );
        $trending_query = new WP_Query($trending_args);
        if($trending_query->have_posts()) :
        ?>
            <div class="row">
                <h2>Trending Now</h2>
                <div class="row__sliders">
                    <?php 
                    $rank = 1; 
                    while ($trending_query->have_posts()) : $trending_query->the_post(); 
                         $poster = get_post_meta($post->ID, 'dt_poster', true);
                         $img_src = netflix_fix_img_url($poster, 'w342');
                         if(empty($img_src)) $img_src = DOO_URI . '/assets/img/no/dt_poster.png';
                    ?>
                    <div class="row__item trending-item" onclick="window.location.href='<?php the_permalink(); ?>'">
                        <span class="rank-number"><?php echo $rank; ?></span>
                        <div class="poster-wrapper">
                            <img class="row__poster lazy" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-src="<?php echo esc_url($img_src); ?>" alt="<?php the_title(); ?>">
                        </div>
                    </div>
                    <?php $rank++; endwhile; ?>
                </div>
            </div>
        <?php endif; wp_reset_postdata(); ?>


        <?php 
        // =========================================================
        // SECTION: RECENTLY ADDED (MIXED)
        // =========================================================
        $recent_mixed_args = array(
            'post_type'      => array('movies', 'tvshows'),
            'posts_per_page' => 10,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'post_status'    => 'publish'
        );
        $recent_mixed_query = new WP_Query($recent_mixed_args);
        if($recent_mixed_query->have_posts()) :
        ?>
            <div class="row auto-slide-row">
                <div class="row__header">
                    <h2 class="row__title">Recently Added</h2>
                </div>
                <div class="row__sliders">
                    <?php while ($recent_mixed_query->have_posts()) : $recent_mixed_query->the_post(); 
                        $backdrop = get_post_meta($post->ID, 'dt_backdrop', true);
                        $poster   = get_post_meta($post->ID, 'dt_poster', true);
                        $img_src  = netflix_fix_img_url($backdrop, 'w300'); 
                        if(empty($img_src)) $img_src = netflix_fix_img_url($poster, 'w342');
                        if(empty($img_src)) $img_src = DOO_URI . '/assets/img/no/dt_poster.png';
                        
                        $year = netflix_get_year($post->ID);
                        $meta = netflix_get_card_meta($post->ID);

                        $post_time = get_the_time('U');
                        $current_time = current_time('timestamp');
                        $is_recent = (($current_time - $post_time) / 86400) <= 2;
                    ?>
                    <div class="row__item" onclick="window.location.href='<?php the_permalink(); ?>'">
                        
                        <?php if (in_array($post->ID, $global_trending_ids)) : ?>
                            <div class="top10-badge">
                                <span class="top-text">TOP</span>
                                <span class="ten-text">10</span>
                            </div>
                        <?php endif; ?>

                        <?php if ($is_recent) : ?>
                            <div class="recent-badge">Recently added</div>
                        <?php endif; ?>

                        <div class="poster-wrapper">
                            <img class="row__poster lazy" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-src="<?php echo esc_url($img_src); ?>" alt="<?php the_title(); ?>">
                        </div>
                        
                        <div class="row__hover_info">
                            <div class="hover-buttons" onclick="event.stopPropagation();">
                                <div class="circle-btn play" onclick="window.location.href='<?php the_permalink(); ?>'"></i></div>
                                <div class="circle-btn"></i></div>
                            </div>
                            <h4><?php the_title(); ?></h4>
                            <div class="meta-line">
                                <span class="match <?php echo $meta['class']; ?>"><?php echo $meta['match']; ?></span>
                                <span class="age-box"><?php echo esc_html($meta['mpaa']); ?></span>
                                <span><?php echo $year; ?></span>
                            </div>
                            <div class="hover-genres"><?php echo $meta['genres']; ?></div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php endif; wp_reset_postdata(); ?>


        <?php 
        // =========================================================
        // SECTION B: CATEGORY SLIDERS
        // =========================================================
        $recent_sliders = array(
            'movies'   => array('title' => 'Latest Movies',    'link' => get_post_type_archive_link('movies')),
            'tvshows'  => array('title' => 'Latest TV Shows', 'link' => get_post_type_archive_link('tvshows')),
            'seasons'  => array('title' => 'New Seasons',     'link' => get_post_type_archive_link('seasons')),
            'episodes' => array('title' => 'New Episodes',    'link' => get_post_type_archive_link('episodes'))
        );

        foreach ($recent_sliders as $post_type => $data) {
            $is_vertical = ($post_type == 'seasons');
            $row_class   = $is_vertical ? 'vertical-row' : '';

            $args = array(
                'post_type'      => $post_type,
                'posts_per_page' => 10,  
                'orderby'        => 'date',
                'order'          => 'DESC'
            );
            $slider_query = new WP_Query($args);
            if($slider_query->have_posts()) :
        ?>
            <div class="row <?php echo $row_class; ?>">
                <div class="row__header">
                    <h2 class="row__title"><a href="<?php echo esc_url($data['link']); ?>"><?php echo $data['title']; ?></a></h2>
                    <div class="row__more"><a href="<?php echo esc_url($data['link']); ?>" class="see-all-btn">See All <i class="fas fa-chevron-right"></i></a></div>
                </div>
                <div class="row__sliders">
                    <?php while ($slider_query->have_posts()) : $slider_query->the_post(); 
                        $backdrop = get_post_meta($post->ID, 'dt_backdrop', true);
                        $poster   = get_post_meta($post->ID, 'dt_poster', true);
                        
                        if ($is_vertical) {
                            $img_src = netflix_fix_img_url($poster, 'w185');
                            if(empty($img_src)) $img_src = netflix_fix_img_url($backdrop, 'w300');
                        } else {
                            $img_src = netflix_fix_img_url($backdrop, 'w300');
                            if(empty($img_src)) $img_src = netflix_fix_img_url($poster, 'w342');
                        }
                        if(empty($img_src)) $img_src = DOO_URI . '/assets/img/no/dt_poster.png';
                        
                        $year = netflix_get_year($post->ID);
                        $meta = netflix_get_card_meta($post->ID);

                        $post_time = get_the_time('U');
                        $current_time = current_time('timestamp');
                        $is_recent = (($current_time - $post_time) / 86400) <= 2;
                    ?>
                    <div class="row__item" onclick="window.location.href='<?php the_permalink(); ?>'">
                        
                        <?php if (in_array($post->ID, $global_trending_ids)) : ?>
                            <div class="top10-badge">
                                <span class="top-text">TOP</span>
                                <span class="ten-text">10</span>
                            </div>
                        <?php endif; ?>

                        <?php if ($is_recent) : ?>
                            <div class="recent-badge">Recently added</div>
                        <?php endif; ?>

                        <div class="poster-wrapper">
                            <img class="row__poster lazy" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-src="<?php echo esc_url($img_src); ?>" alt="<?php the_title(); ?>">
                        </div>
                        
                        <div class="row__hover_info">
                            <div class="hover-buttons" onclick="event.stopPropagation();">
                                <div class="circle-btn play" onclick="window.location.href='<?php the_permalink(); ?>'"></div>
                                <div class="circle-btn"></div>
                            </div>
                            <h4><?php the_title(); ?></h4>
                            <div class="meta-line">
                                <span class="match <?php echo $meta['class']; ?>"><?php echo $meta['match']; ?></span>
                                <span class="age-box"><?php echo esc_html($meta['mpaa']); ?></span>
                                <span><?php echo $year; ?></span>
                            </div>
                            <div class="hover-genres"><?php echo $meta['genres']; ?></div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php endif; wp_reset_postdata(); } ?>


        <?php 
        // =========================================================
        // SECTION C: GENRES & CUSTOM TAGS
        // =========================================================
        $sliders_to_show = array();
        $saved_genres = get_option('netflix_home_genres');
        $genre_titles = array('action'=>'Action','comedy'=>'Comedy','drama'=>'Drama','horror'=>'Horror','romance'=>'Romance','scifi'=>'Sci-Fi','fantasy'=>'Fantasy','animation'=>'Animation','adventure'=>'Adventure');
        if(!empty($saved_genres)) {
            foreach($saved_genres as $slug => $enabled) {
                if($enabled == 1 && $slug !== 'trending') {
                    $label = isset($genre_titles[$slug]) ? $genre_titles[$slug] : ucfirst($slug);
                    $sliders_to_show[] = array('type'=>'genre', 'slug'=>$slug, 'title'=>$label);
                }
            }
        } else {
            $sliders_to_show[] = array('type'=>'genre', 'slug'=>'action', 'title'=>'Action');
        }
        $custom_tags = get_option('netflix_custom_tag_sliders');
        if(!empty($custom_tags)) {
            foreach(explode("\n", $custom_tags) as $line) {
                $line = trim($line);
                if(empty($line)) continue;
                $parts = explode('|', $line);
                $config = trim($parts[0]);
                $title = isset($parts[1]) ? trim($parts[1]) : '';
                if(strpos($config, ':') !== false) {
                    list($tax, $slug) = explode(':', $config, 2);
                } else {
                    $tax = 'post_tag'; $slug = $config;
                }
                if(empty($title)) $title = ucfirst(str_replace('-',' ',$slug));
                $sliders_to_show[] = array('type'=>'tax', 'tax'=>$tax, 'slug'=>$slug, 'title'=>$title);
            }
        }

        foreach($sliders_to_show as $slider) {
            $args = array('post_type'=>array('movies','tvshows'), 'posts_per_page'=>15);
            if($slider['type'] == 'genre') {
                $args['tax_query'] = array(array('taxonomy'=>'genres', 'field'=>'slug', 'terms'=>$slider['slug']));
                $link = get_term_link($slider['slug'], 'genres');
            } else {
                $args['tax_query'] = array(array('taxonomy'=>$slider['tax'], 'field'=>'slug', 'terms'=>$slider['slug']));
                $link = get_term_link($slider['slug'], $slider['tax']);
            }
            if(is_wp_error($link)) $link = '#';

            $row_query = new WP_Query($args);
            if($row_query->have_posts()) :
        ?>
            <div class="row">
                <div class="row__header">
                    <h2 class="row__title"><a href="<?php echo esc_url($link); ?>"><?php echo $slider['title']; ?></a></h2>
                    <div class="row__more"><a href="<?php echo esc_url($link); ?>" class="see-all-btn">See All <i class="fas fa-chevron-right"></i></a></div>
                </div>
                <div class="row__sliders">
                    <?php while ($row_query->have_posts()) : $row_query->the_post(); 
                        $backdrop = get_post_meta($post->ID, 'dt_backdrop', true);
                        $poster   = get_post_meta($post->ID, 'dt_poster', true);
                        $img_src = netflix_fix_img_url($backdrop, 'w300');
                        if(empty($img_src)) $img_src = netflix_fix_img_url($poster, 'w342');
                        if(empty($img_src)) $img_src = DOO_URI . '/assets/img/no/dt_poster.png';
                        
                        $year = netflix_get_year($post->ID);
                        $meta = netflix_get_card_meta($post->ID);

                        $post_time = get_the_time('U');
                        $current_time = current_time('timestamp');
                        $is_recent = (($current_time - $post_time) / 86400) <= 2;
                    ?>
                    <div class="row__item" onclick="window.location.href='<?php the_permalink(); ?>'">
                        
                        <?php if (in_array($post->ID, $global_trending_ids)) : ?>
                            <div class="top10-badge">
                                <span class="top-text">TOP</span>
                                <span class="ten-text">10</span>
                            </div>
                        <?php endif; ?>

                        <?php if ($is_recent) : ?>
                            <div class="recent-badge">Recently added</div>
                        <?php endif; ?>

                        <div class="poster-wrapper">
                            <img class="row__poster lazy" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-src="<?php echo esc_url($img_src); ?>" alt="<?php the_title(); ?>">
                        </div>
                        
                        <div class="row__hover_info">
                            <div class="hover-buttons" onclick="event.stopPropagation();">
                                <div class="circle-btn play" onclick="window.location.href='<?php the_permalink(); ?>'"></i></div>
                                <div class="circle-btn"></i></div>
                            </div>
                            <h4><?php the_title(); ?></h4>
                            <div class="meta-line">
                                <span class="match <?php echo $meta['class']; ?>"><?php echo $meta['match']; ?></span>
                                <span class="age-box"><?php echo esc_html($meta['mpaa']); ?></span>
                                <span><?php echo $year; ?></span>
                            </div>
                            <div class="hover-genres"><?php echo $meta['genres']; ?></div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php endif; wp_reset_postdata(); } ?>

    </div>
</div>

<?php get_footer(); ?>