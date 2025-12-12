<?php
/*
* Template Name: Netflix Home Native (Final Optimized)
* Description: Homepage with Lazy Loading, Speed Optimizations, and Admin Settings.
*/

get_header(); 

function netflix_fix_img_url($url, $size = 'w780') {
    if(empty($url)) return '';
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
    return get_the_date('Y', $post_id);
}

// 1. BILLBOARD SECTION (Lazy Loaded Background)
$billboard_args = array(
    'post_type'      => array('movies', 'tvshows'),
    'posts_per_page' => 1,
    'orderby'        => 'rand',
    'meta_query'     => array(
        'relation' => 'AND',
        array('key' => 'imdbRating', 'value' => '6.0', 'compare' => '>='),
        array('key' => 'dt_backdrop', 'compare' => 'EXISTS')
    )
);
$billboard_query = new WP_Query($billboard_args);
?>

<div id="netflix-wrapper">
    
    <?php if ($billboard_query->have_posts()) : while ($billboard_query->have_posts()) : $billboard_query->the_post(); 
        
        // 1. Desktop Image (Landscape)
        $bg_image = netflix_fix_img_url(get_post_meta($post->ID, 'dt_backdrop', true), 'w1280');
        
        // Fallback for Desktop
        if(empty($bg_image)) $bg_image = get_the_post_thumbnail_url($post->ID, 'full');
        if(empty($bg_image) && defined('DOO_URI')) $bg_image = DOO_URI . '/assets/img/no/dt_backdrop.png';
        
        // 2. Mobile Image (Portrait Poster) - Using w780 for better quality
        $poster_image = netflix_fix_img_url(get_post_meta($post->ID, 'dt_poster', true), 'w780');
        
        // Fallback for Mobile
        if(empty($poster_image) && defined('DOO_URI')) {
            $poster_image = DOO_URI . '/assets/img/no/dt_poster.png';
        }
        // Use background if poster is still empty
        if(empty($poster_image)) {
            $poster_image = $bg_image; 
        }

        // 3. Meta Data
        $rating = get_post_meta($post->ID, 'imdbRating', true);
        $match = ($rating) ? ($rating * 10) : '95';
        $year = netflix_get_year($post->ID);
        $desc = wp_trim_words(get_the_excerpt(), 30, '...');
        
        // 4. Genres for Mobile
        $terms = get_the_terms($post->ID, 'genres');
        $genre_html = '';
        if ($terms && !is_wp_error($terms)) {
            $names = wp_list_pluck($terms, 'name');
            $genre_html = implode(' <span class="mob-dot">&bull;</span> ', array_slice($names, 0, 3)); 
        }
    ?>
    
    <div class="banner lazy-bg" data-bg="<?php echo esc_url($bg_image); ?>">
        
        <div class="banner__contents">
            <h1 class="banner__title"><?php the_title(); ?></h1>
            <div class="banner__meta">
                <span class="match-score"><?php echo $match; ?>% Match</span>
                <span class="year"><?php echo $year; ?></span>
                <span class="hd-badge">HD</span>
            </div>
            <div class="banner__description">
                <?php echo $desc; ?>
            </div>
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
                    
                    <div class="mob-genres">
                        <?php echo $genre_html; ?>
                    </div>

                    <div class="mob-actions">
                        <a href="<?php the_permalink(); ?>" class="mob-btn play">
                            <i class="fas fa-play"></i> Play
                        </a>
                        <a href="<?php the_permalink(); ?>" class="mob-btn list">
                            <i class="fas fa-plus"></i> My List
                        </a>
                    </div>
                </div>
            </div>
        </div>
        </div>
    <?php endwhile; wp_reset_postdata(); endif; ?>

    <div class="netflix-content">
        <div class="banner__fadeBottoma"></div>
        <?php 
        // --- SECTION A: TRENDING NOW ---
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
 
                         $year = netflix_get_year($post->ID);
                         $match = '98% Match';
                    ?>
                    <div class="row__item trending-item" onclick="window.location.href='<?php the_permalink(); ?>'">
                        <span class="rank-number"><?php echo $rank; ?></span>
                        <div class="poster-wrapper">
                            <img class="row__poster lazy" 
                                 src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" 
                                 data-src="<?php echo esc_url($img_src); ?>" 
                                 alt="<?php the_title(); ?>">
                        </div>
                        <div class="row__hover_info">
                            <div class="hover-buttons" onclick="event.stopPropagation();">
                                <div class="circle-btn play" onclick="window.location.href='<?php the_permalink(); ?>'"><i class="fas fa-play"></i></div>
                                <div class="circle-btn"><i class="fas fa-plus"></i></div>
                            </div>
                            <h4><?php the_title(); ?></h4>
                            <div class="meta-line">
                                <span class="match"><?php echo $match; ?></span>
                                <span class="age-box">16+</span>
                                <span><?php echo $year; ?></span>
                            </div>
                        </div>
                    </div>
                    <?php $rank++; endwhile; ?>
                </div>
            </div>
        <?php endif; wp_reset_postdata(); ?>


        <?php 
        // --- SECTION B: RECENTS SLIDERS ---
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
                    <div class="row__more">
                        <a href="<?php echo esc_url($data['link']); ?>" class="see-all-btn">See All <i class="fas fa-chevron-right"></i></a>
                    </div>
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
                    ?>
                    <div class="row__item" onclick="window.location.href='<?php the_permalink(); ?>'">
                        <div class="poster-wrapper">
                            <img class="row__poster lazy" 
                                 src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" 
                                 data-src="<?php echo esc_url($img_src); ?>" 
                                 alt="<?php the_title(); ?>">
                        </div>
                        <div class="row__hover_info">
                            <div class="hover-buttons" onclick="event.stopPropagation();">
                                <div class="circle-btn play" onclick="window.location.href='<?php the_permalink(); ?>'"><i class="fas fa-play"></i></div>
                                <div class="circle-btn"><i class="fas fa-plus"></i></div>
                            </div>
                            <h4><?php the_title(); ?></h4>
                            <div class="meta-line">
                                <span class="age-box">16+</span>
                                <span><?php echo $year; ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php endif; wp_reset_postdata(); } ?>


        <?php 
        // --- SECTION C: GENRES & CUSTOM TAGS ---
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
                    ?>
                    <div class="row__item" onclick="window.location.href='<?php the_permalink(); ?>'">
                        <div class="poster-wrapper">
                            <img class="row__poster lazy" 
                                 src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" 
                                 data-src="<?php echo esc_url($img_src); ?>" 
                                 alt="<?php the_title(); ?>">
                        </div>
                        <div class="row__hover_info">
                            <div class="hover-buttons" onclick="event.stopPropagation();">
                                <div class="circle-btn play" onclick="window.location.href='<?php the_permalink(); ?>'"><i class="fas fa-play"></i></div>
                                <div class="circle-btn"><i class="fas fa-plus"></i></div>
                            </div>
                            <h4><?php the_title(); ?></h4>
                            <div class="meta-line"><span><?php echo $year; ?></span></div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php endif; wp_reset_postdata(); } ?>

    </div>
</div>

<?php get_footer(); ?>