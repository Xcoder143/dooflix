<?php
/*
 * Child theme functions.php — Netflix header + secure AJAX + DooPlay integration
 */

/*
 * 1. Enqueue Parent and Child Styles
 */
function dooplay_child_enqueue_styles() {
    wp_enqueue_style( 'dooplay-parent-style', get_template_directory_uri() . '/style.css' );
    wp_enqueue_style( 'dooplay-child-style', get_stylesheet_directory_uri() . '/style.css', array( 'dooplay-parent-style' ) );
}
add_action( 'wp_enqueue_scripts', 'dooplay_child_enqueue_styles' );

/*
 * 2. Custom Player Viewer - FIXED STRUCTURE (UL/LI)
 */
function custom_theme_player_viewer($post_id, $type, $players, $trailer, $size, $views, $ads = false, $image = false) {

    $ajax_player = dooplay_get_option('playajax');
    $play_pager  = doo_compose_pagelink('jwpage');
    $source_name = dooplay_get_option('playsource');
    $set_mode    = ($ajax_player == true) ? 'ajax_mode' : 'no_ajax';

    echo '<section class="player-wrapper">';
    echo '<div class="player-inner">';

    // --- TABS SECTION ---
    if ($players || $trailer) {
        echo '<ul id="playeroptionsul" class="players-tabs '.$set_mode.'">';

        // 1. Trailer Tab
        if ($trailer) {
            echo '<li id="player-option-trailer" class="tab dooplay_player_option" data-post="'.$post_id.'" data-type="'.$type.'" data-nume="trailer">';
            echo '<i class="fas fa-play-circle"></i> <span class="title">' . __d('Trailer') . '</span>';
            echo '<span class="loader"></span>';
            echo '</li>';
        }

        // 2. Server Tabs
        $num = 1;
        if (!empty($players) && is_array($players)) {
            foreach ($players as $play) {
                $active_class = ($num === 1 && !$trailer) ? 'on' : '';

                echo '<li id="player-option-'.$num.'" class="tab dooplay_player_option '.$active_class.'" data-type="'.$type.'" data-post="'.$post_id.'" data-nume="'.$num.'">';
                echo '<i class="fas fa-play"></i> <span class="title">'. $play['name'] .'</span>';

                if (!empty($play['idioma'])) {
                    echo ' <img src="'.DOO_URI.'/assets/img/flags/'.$play['idioma'].'.png">';
                }

                echo '<span class="loader"></span>';
                echo '</li>';
                $num++;
            }
        }
        echo '</ul>';
    }

    // --- VIDEO FRAME SECTION ---
    echo '<div class="player-frame">';

    if (!empty($ads) && $ajax_player) {
        echo "<div class='asgdc'>{$ads}</div>";
    }

    if ($ajax_player == true) {
        echo "<div id='dooplay_player_response'></div>";
    } else {
        echo "<div id='dooplay_player_content'>";
        if ($trailer) {
            echo "<div id='source-player-trailer' class='source-box'><div class='pframe'>" . doo_trailer_iframe($trailer) . "</div></div>";
        }
        $num = 1;
        if (!empty($players) && is_array($players)) {
            foreach ($players as $play) {
                $source = doo_isset($play, 'url');
                echo "<div id='source-player-{$num}' class='source-box'><div class='pframe'>";
                switch (doo_isset($play, 'select')) {
                    case 'mp4':
                        echo "<iframe class='metaframe rptss' src='{$play_pager}?source=".urlencode($source)."&id={$post_id}&type=mp4' frameborder='0' scrolling='no' allow='autoplay; encrypted-media' allowfullscreen></iframe>";
                        break;
                    case 'iframe':
                        echo "<iframe class='metaframe rptss' src='{$source}' frameborder='0' scrolling='no' allow='autoplay; encrypted-media' allowfullscreen></iframe>";
                        break;
                    case 'dtshcode':
                        echo do_shortcode($source);
                        break;
                }
                echo "</div></div>";
                $num++;
            }
        }
        echo "</div>";
    }
    echo '</div>'; // End player-frame
    echo '</div>'; // End player-inner
    echo '</section>';
}

/*
 * 3. Netflinks Custom Assets & Single Movie Assets
 */
function nf_enqueue_custom_assets() {
    if ( is_single() ) {
        // Existing Netflinks assets
        wp_enqueue_style('netflinks-style', get_stylesheet_directory_uri() . '/assets/css/netflinks.css', array(), '1.0.0');
        wp_enqueue_script('netflinks-script', get_stylesheet_directory_uri() . '/assets/js/netflinks.js', array(), '1.0.0', true);
        wp_localize_script('netflinks-script', 'nfLinksData', array(
            'svg_path' => get_stylesheet_directory_uri() . '/inc/parts/single/svg/'
        ));

        // NEW: Enqueue Single Movie Split Assets
        wp_enqueue_style('single-movie-style', get_stylesheet_directory_uri() . '/assets/css/single-movie.css', array(), '1.0.0');
        wp_enqueue_script('single-movie-script', get_stylesheet_directory_uri() . '/assets/js/single-movie.js', array(), '1.0.0', true);
        wp_enqueue_style('single-series-style', get_stylesheet_directory_uri() . '/assets/css/single-series.css', array(), '1.0.0');
    }
}
add_action('wp_enqueue_scripts', 'nf_enqueue_custom_assets');


/* Theme_netflix_trigger_auto_import */
function theme_netflix_trigger_auto_import() {
    if ( ! function_exists('dooplay_get_option') ) {
        return;
    }

    // 1. Get API Key
    $api_key = dooplay_get_option('dbmv_tmdb_api');
    if ( empty($api_key) ) {
        return;
    }

    // 2. Fetch Data from TMDb
    $url      = "https://api.themoviedb.org/3/trending/movie/day?api_key=" . $api_key;
    $response = wp_remote_get($url);

    if ( is_wp_error( $response ) ) {
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ( isset($data['results']) ) {
        foreach ( $data['results'] as $movie ) {
            $tmdb_id = $movie['id'];
            
            // 3. Check if exists in DB
            $args    = array(
                'post_type'  => 'movies',
                'meta_key'   => 'ids',
                'meta_value' => $tmdb_id,
            );

            $query = new WP_Query($args);

            // 4. Import if missing
            if ( ! $query->have_posts() ) {
                if ( function_exists('dbmovies_import_movie') ) {
                    dbmovies_import_movie($tmdb_id);
                } elseif ( class_exists('Dbmvs_Importers') ) {
                    $importer = new Dbmvs_Importers();
                    $importer->import_movie($tmdb_id);
                }
            }
            wp_reset_postdata();
        }
    }
}


/* ========================================================================
   NETFLIX CORE INTEGRATION (Merged Plugin Logic)
   ======================================================================== */

// 1. ASSET LOADER (Updated paths for Child Theme)
function theme_netflix_enqueue_assets() {
    wp_enqueue_style( 'netflix-style', get_stylesheet_directory_uri() . '/assets/css/netflix-style.css', array(), '3.3', 'all' );
    wp_enqueue_script( 'netflix-script', get_stylesheet_directory_uri() . '/assets/js/netflix.js', array('jquery'), '3.3', true );
    wp_enqueue_style( 'font-awesome-cdn', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css' );
}
add_action( 'wp_enqueue_scripts', 'theme_netflix_enqueue_assets', 999 );

// 2. ADMIN SETTINGS MENU
function child_theme_netflix_create_menu() {
    add_menu_page(
        'Netflix Settings',
        'Netflix Core',
        'manage_options',
        'netflix-core-settings',
        'child_theme_netflix_settings_html',
        'dashicons-media-interactive',
        100
    );
}
add_action('admin_menu', 'child_theme_netflix_create_menu');

function child_theme_netflix_register_settings() {
    register_setting('netflix_core_settings_group', 'netflix_home_genres');
    register_setting('netflix_core_settings_group', 'netflix_custom_tag_sliders');
}
add_action('admin_init', 'child_theme_netflix_register_settings');

function child_theme_netflix_settings_html() {
    ?>
    <div class="wrap">
        <h1>Netflix Homepage Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('netflix_core_settings_group'); ?>
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <div style="background: #fff; padding: 20px; flex: 1; min-width: 300px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2>1. Standard Genre Sliders</h2>
                    <p>Check the boxes to display these categories:</p>
                    <hr>
                    <?php
                    $saved_genres = get_option('netflix_home_genres', array());
                    $available_genres = array(
                        'trending'  => 'Trending Now',
                        'action'    => 'Action',
                        'comedy'    => 'Comedy',
                        'drama'     => 'Drama',
                        'horror'    => 'Horror',
                        'romance'   => 'Romance',
                        'scifi'     => 'Sci-Fi',
                        'fantasy'   => 'Fantasy',
                        'animation' => 'Animation',
                        'adventure' => 'Adventure'
                    );
                    foreach ($available_genres as $slug => $label) :
                        $checked = (isset($saved_genres[$slug]) && $saved_genres[$slug] == 1) ? 'checked' : '';
                        ?>
                        <p><label><input type="checkbox" name="netflix_home_genres[<?php echo esc_attr($slug); ?>]" value="1" <?php echo $checked; ?>> <strong><?php echo esc_html($label); ?></strong></label></p>
                    <?php endforeach; ?>
                </div>
                <div style="background: #fff; padding: 20px; flex: 1; min-width: 300px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2>2. Advanced Custom Sliders</h2>
                    <p>Enter <code>taxonomy:slug|Title</code> (one per line).</p>
                    <hr>
                    <p><strong>Supported Taxonomies:</strong></p>
                    <ul style="list-style: disc; margin-left: 20px; color: #666; font-size: 12px;">
                        <li><strong>Tags:</strong> <code>post_tag:your-tag|Title</code></li>
                        <li><strong>Director:</strong> <code>dtdirector:name-slug|Title</code></li>
                        <li><strong>Cast:</strong> <code>dtcast:name-slug|Title</code></li>
                        <li><strong>Studio:</strong> <code>dtstudio:name-slug|Title</code></li>
                        <li><strong>Network:</strong> <code>dtnetworks:name-slug|Title</code></li>
                        <li><strong>Creator:</strong> <code>dtcreator:name-slug|Title</code></li>
                    </ul>
                    <br>

                    <textarea name="netflix_custom_tag_sliders" rows="10" class="large-text code"><?php echo esc_textarea(get_option('netflix_custom_tag_sliders')); ?></textarea>
                </div>
            </div>
            <br>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// 3. AUTOMATED IMPORTER
if ( ! wp_next_scheduled( 'theme_netflix_daily_import_event' ) ) {
    wp_schedule_event( time(), 'daily', 'theme_netflix_daily_import_event' );
}
add_action( 'theme_netflix_daily_import_event', 'theme_netflix_trigger_auto_import' );

function theme_netflix_trigger_auto_import() {
    if ( ! function_exists('dooplay_get_option') ) {
        return;
    }

    $api_key = dooplay_get_option('dbmv_tmdb_api');
    if ( empty($api_key) ) {
        return;
    }

    $url      = "https://api.themoviedb.org/3/trending/movie/day?api_key=" . $api_key;
    $response = wp_remote_get($url);

    if ( is_wp_error( $response ) ) {
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ( isset($data['results']) ) {
        foreach ( $data['results'] as $movie ) {
            $tmdb_id = $movie['id'];
            $args    = array(
                'post_type'  => 'movies',
                'meta_key'   => 'ids',
                'meta_value' => $tmdb_id,
            );

            $query = new WP_Query($args);

            if ( ! $query->have_posts() ) {
                if ( function_exists('dbmovies_import_movie') ) {
                    dbmovies_import_movie($tmdb_id);
                } elseif ( class_exists('Dbmvs_Importers') ) {
                    $importer = new Dbmvs_Importers();
                    $importer->import_movie($tmdb_id);
                }
            }
            wp_reset_postdata();
        }
    }
}

/* ---------------------------------------------------------
   ENQUEUE NETFLIX HEADER CSS + JS (FINAL) with localization
---------------------------------------------------------- */
function dooplay_netflix_header_assets() {

    /* Netflix Header CSS */
    wp_enqueue_style(
        'netflix-header-css',
        get_stylesheet_directory_uri() . '/assets/css/netflix-header.css',
        array(),
        '1.0.0',
        'all'
    );

    /* Netflix Header JS */
    wp_enqueue_script(
        'netflix-header-js',
        get_stylesheet_directory_uri() . '/assets/js/netflix-header.js',
        array(),
        '1.0.1',
        true
    );

    // Localize AJAX url + nonce + HOME URL for secure requests
    wp_localize_script(
        'netflix-header-js',
        'nfxHeaderData',
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('nfx_search_nonce'),
            'home_url' => home_url('/') // <--- ADD THIS LINE (Fixes the link)
        )
    );
}
add_action('wp_enqueue_scripts', 'dooplay_netflix_header_assets', 99);


/* NETFLIX LIVE SEARCH (secure) V1 - Backup only for 4K
add_action('wp_ajax_nfx_search', 'nfx_search');
add_action('wp_ajax_nopriv_nfx_search', 'nfx_search');

function nfx_search() {

    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'nfx_search_nonce')) {
        wp_send_json_error();
        wp_die();
    }

    $search_text = isset($_POST['q']) ? sanitize_text_field(wp_unslash($_POST['q'])) : '';
    if (strlen($search_text) < 2) {
        wp_send_json([]);
        wp_die();
    }

    $tmdb_base = 'https://image.tmdb.org/t/p/w342';
    $results = [];

    $query = new WP_Query([
        's' => $search_text,
        'post_type' => ['movies', 'tvshows'],
        'posts_per_page' => 10,
        'post_status' => 'publish'
    ]);

    while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();

        /* IMAGE 
        $img = get_the_post_thumbnail_url($post_id, 'medium');
        if (!$img) $img = get_post_meta($post_id, 'poster_url', true);
        if (!$img) $img = get_post_meta($post_id, 'dt_poster', true);

        if ($img && substr($img, 0, 1) === '/' && strpos($img, '//') === false) {
            $img = $tmdb_base . $img;
        }

        if (!$img) {
            $img = 'https://via.placeholder.com/300x450/141414/ffffff?text=No+Image';
        }

        $year = get_the_date('Y', $post_id);

        /* ✅ REAL HTML 4K SCAN 
        $is_4k = false;

        if (class_exists('DooLinks') && method_exists('DooLinks', 'tablelist_front')) {

            ob_start();
            DooLinks::tablelist_front($post_id, __d('Download'), 'download');
            $html = ob_get_clean();

            if ($html) {
                $html = strtolower($html);

                if (
                    strpos($html, '4k') !== false ||
                    strpos($html, '2160') !== false ||
                    strpos($html, 'uhd') !== false
                ) {
                    $is_4k = true;
                }
            }
        }

        $results[] = [
            'title' => get_the_title(),
            'url'   => get_permalink(),
            'img'   => esc_url($img),
            'year'  => $year,
            'is4k'  => $is_4k,
            'meta'  => (get_post_type() === 'movies') ? 'Movie' : 'TV Show'
        ];
    }

    wp_reset_postdata();
    wp_send_json($results);
}*/

/* NETFLIX LIVE SEARCH (secure) Full Netflix-style quality badge detection (4K / HDR / 1080p / 720p / SD) 
add_action('wp_ajax_nfx_search', 'nfx_search');
add_action('wp_ajax_nopriv_nfx_search', 'nfx_search');

function nfx_search() {

    /* ===========================
       SECURITY CHECK
    ============================ 
    $nonce = isset($_POST['nonce']) ? sanitize_text_field( wp_unslash($_POST['nonce']) ) : '';
    if ( ! wp_verify_nonce($nonce, 'nfx_search_nonce') ) {
        wp_send_json_error();
        wp_die();
    }

    /* ===========================
       QUERY CHECK
    ============================ 
    $q = isset($_POST['q']) ? sanitize_text_field( wp_unslash($_POST['q']) ) : '';
    if ( strlen($q) < 2 ) {
        wp_send_json([]);
        wp_die();
    }

    $tmdb_base = 'https://image.tmdb.org/t/p/w342';
    $results   = [];

    $query = new WP_Query([
        's'              => $q,
        'post_type'      => ['movies', 'tvshows'],
        'posts_per_page' => 8,
        'post_status'    => 'publish'
    ]);

    while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();

        /* ===========================
           IMAGE HANDLING
        ============================ 
        $img = get_the_post_thumbnail_url($post_id, 'medium');

        if (!$img) $img = get_post_meta($post_id, 'poster_url', true);
        if (!$img) $img = get_post_meta($post_id, 'dt_poster', true);

        if ($img && substr($img, 0, 1) === '/' && strpos($img, '//') === false) {
            $img = $tmdb_base . $img;
        }

        if (!$img) {
            $img = 'https://via.placeholder.com/300x450/141414/ffffff?text=No+Image';
        }

        /* ===========================
           YEAR
        ============================ 
        $year = get_the_date('Y', $post_id);

        /* ===========================
           QUALITY DETECTION
        ============================
        $is_4k   = false;
        $is_1080 = false;
        $is_720  = false;
        $is_sd   = false;
        $is_hdr  = false;

        $repeatable = get_post_meta($post_id, 'repeatable_fields', true);

        if (!empty($repeatable)) {

            $txt = strtolower(print_r($repeatable, true));

            if (strpos($txt, '4k') !== false || strpos($txt, '2160') !== false) $is_4k = true;
            if (strpos($txt, 'hdr') !== false) $is_hdr = true;
            if (strpos($txt, '1080') !== false) $is_1080 = true;
            if (strpos($txt, '720') !== false) $is_720 = true;
            if (strpos($txt, '480') !== false || strpos($txt, 'sd') !== false) $is_sd = true;
        }

        /* ===========================
           FINAL RESULT ITEM
        ============================
        $results[] = [
            'title'  => get_the_title(),
            'url'    => get_permalink(),
            'img'    => esc_url($img),
            'year'   => $year,
            'meta'   => get_post_type() === 'movies' ? 'Movie' : 'TV Show',
            'is4k'   => $is_4k,
            'isHDR'  => $is_hdr,
            'is1080' => $is_1080,
            'is720'  => $is_720,
            'isSD'   => $is_sd
        ];
    }

    wp_reset_postdata();
    wp_send_json($results);
}
*/

/* NETFLIX LIVE SEARCH V3
add_action('wp_ajax_nfx_search', 'nfx_search');
add_action('wp_ajax_nopriv_nfx_search', 'nfx_search');

function nfx_search() {
    // 1. SECURITY
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'nfx_search_nonce')) {
        wp_send_json_error(['message' => 'Invalid Nonce']);
        wp_die();
    }

    // 2. VALIDATE QUERY
    $q = isset($_POST['q']) ? sanitize_text_field(wp_unslash($_POST['q'])) : '';
    if (strlen($q) < 2) {
        wp_send_json([]);
        wp_die();
    }

    $tmdb_base = 'https://image.tmdb.org/t/p/w342';
    $results   = [];

    // 3. SEARCH QUERY
    $query = new WP_Query([
        's'              => $q,
        'post_type'      => ['movies', 'tvshows'],
        'posts_per_page' => 8,
        'post_status'    => 'publish'
    ]);

    while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();

        // IMAGE HANDLING
        $img = get_the_post_thumbnail_url($post_id, 'medium');
        if (!$img) $img = get_post_meta($post_id, 'poster_url', true);
        if (!$img) $img = get_post_meta($post_id, 'dt_poster', true);

        // Fix TMDB relative paths
        if ($img && substr($img, 0, 1) === '/' && strpos($img, '//') === false) {
            $img = $tmdb_base . $img;
        }
        // Fallback Image
        if (!$img) {
            $img = get_template_directory_uri() . '/assets/img/no-poster.jpg'; // Better to use a local fallback
        }

        // METADATA
        $year = get_the_date('Y');
        $type = get_post_type() === 'movies' ? 'Movie' : 'TV';

        // QUALITY DETECTION (Scanning Player Links)
        $is_4k   = false;
        $is_1080 = false;
        $is_720  = false;
        $is_hdr  = false;

        $repeatable = get_post_meta($post_id, 'repeatable_fields', true);

        if (!empty($repeatable) && is_array($repeatable)) {
            // Convert array to string for quick keyword search
            $txt = strtolower(print_r($repeatable, true));

            if (strpos($txt, '4k') !== false || strpos($txt, '2160') !== false) $is_4k = true;
            if (strpos($txt, 'hdr') !== false) $is_hdr = true;
            if (strpos($txt, '1080') !== false) $is_1080 = true;
            if (strpos($txt, '720') !== false) $is_720 = true;
        }

        // CALCULATE "TOP BADGE" (Logic: 4K > 1080p > 720p)
        $badge = ''; // Default empty
        if ($is_4k) {
            $badge = '4K'; // HDR is usually implied with 4K, or handled separately
        } elseif ($is_1080) {
            $badge = 'HD'; // Netflix uses "HD" for 1080p
        } elseif ($is_720) {
            $badge = '720p';
        }

        // ADD TO RESULTS
        $results[] = [
            'title'   => get_the_title(),
            'url'     => get_permalink(),
            'img'     => esc_url($img),
            'year'    => $year,
            'type'    => $type,
            'badge'   => $badge,   // "4K", "HD", or ""
            'isHDR'   => $is_hdr,  // True/False
        ];
    }

    wp_reset_postdata();
    wp_send_json($results);
}*/

/* NETFLIX LIVE SEARCH V4 */
add_action('wp_ajax_nfx_search', 'nfx_search');
add_action('wp_ajax_nopriv_nfx_search', 'nfx_search');

function nfx_search() {
    // 1. SECURITY
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'nfx_search_nonce')) {
        wp_send_json_error(['message' => 'Invalid Nonce']);
        wp_die();
    }

    // 2. QUERY CHECKS
    $q = isset($_POST['q']) ? sanitize_text_field(wp_unslash($_POST['q'])) : '';
    if (strlen($q) < 2) {
        wp_send_json([]);
        wp_die();
    }

    $tmdb_base = 'https://image.tmdb.org/t/p/w342';
    $results   = [];

    $query = new WP_Query([
        's'              => $q,
        'post_type'      => ['movies', 'tvshows'],
        'posts_per_page' => 10,
        'post_status'    => 'publish'
    ]);

    while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();

        // --- IMAGE HANDLING ---
        $img = get_the_post_thumbnail_url($post_id, 'medium');
        if (!$img) $img = get_post_meta($post_id, 'poster_url', true);
        if (!$img) $img = get_post_meta($post_id, 'dt_poster', true);
        
        if ($img && substr($img, 0, 1) === '/' && strpos($img, '//') === false) {
            $img = $tmdb_base . $img;
        }
        if (!$img) {
            $img = get_template_directory_uri() . '/assets/img/no-poster.jpg';
        }

        // --- METADATA ---
        $year = get_the_date('Y');
        $type = get_post_type() === 'movies' ? 'Movie' : 'TV';
        $title = get_the_title();

        // --- DEEP QUALITY SCAN ---
        $is_4k   = false;
        $is_1080 = false;
        $is_720  = false;
        $is_hdr  = false;

        // 1. Check Title
        $search_text = strtolower($title);

        // 2. Check Player Data (Streaming Servers)
        $players_1 = get_post_meta($post_id, 'repeatable_fields', true);
        $players_2 = get_post_meta($post_id, '_dooplay_player_option', true);
        
        if (is_array($players_1)) $search_text .= strtolower(print_r($players_1, true));
        if (is_array($players_2)) $search_text .= strtolower(print_r($players_2, true));

        // 3. Check Download Links (DooLinks Table) ✅ NEW ADDITION
        if (class_exists('DooLinks') && method_exists('DooLinks', 'tablelist_front')) {
            // We buffer the output of the download table to read its text
            ob_start();
            DooLinks::tablelist_front($post_id, 'Download', 'download'); 
            $d_html = ob_get_clean();
            $search_text .= strtolower($d_html);
        }

        // --- SCAN THE TEXT ---
        if (strpos($search_text, '4k') !== false || strpos($search_text, '2160') !== false || strpos($search_text, 'uhd') !== false) {
            $is_4k = true;
        }
        if (strpos($search_text, 'hdr') !== false) {
            $is_hdr = true;
        }
        if (strpos($search_text, '1080') !== false || strpos($search_text, 'fhd') !== false) {
            $is_1080 = true;
        }
        if (strpos($search_text, '720') !== false || strpos($search_text, 'hdrip') !== false) {
            $is_720 = true;
        }

        // --- DETERMINE BADGE ---
        $badge = ''; 
        if ($is_4k) {
            $badge = '4K'; 
        } elseif ($is_1080) {
            $badge = 'HD';
        } elseif ($is_720) {
            $badge = '720p';
        }

        $results[] = [
            'title'   => $title,
            'url'     => get_permalink(),
            'img'     => esc_url($img),
            'year'    => $year,
            'type'    => $type,
            'badge'   => $badge,  
            'isHDR'   => $is_hdr, 
        ];
    }

    wp_reset_postdata();
    wp_send_json($results);
}

function enqueue_netflix_footer_styles() {
    // This loads your specific custom file
    wp_enqueue_style( 
        'netflix-footer-css', 
        get_stylesheet_directory_uri() . '/assets/css/netflix-footer.css', 
        array(), 
        '1.0.0' 
    );
}
add_action( 'wp_enqueue_scripts', 'enqueue_netflix_footer_styles' );


/*Temp

add_action('admin_footer', function() {
    if (!isset($_GET['post'])) return;

    $post_id = (int) $_GET['post'];
    $meta = get_post_meta($post_id);

    $allText = '';

    echo '<div style="
        background:#000;
        color:#0f0;
        padding:16px;
        font-family:monospace;
        max-height:300px;
        overflow:auto;
        margin:20px 0;
        border-radius:8px;
    ">';

    echo '<h3 style="color:#f33;margin:0 0 10px;">META DEBUG</h3>';

    echo '<button id="copyMetaBtn" style="
        background:#e50914;
        color:#fff;
        border:none;
        padding:10px 16px;
        border-radius:8px;
        cursor:pointer;
        font-weight:bold;
        margin-bottom:10px;
    ">📋 COPY ALL META</button>';

    echo '<pre id="metaDump" style="white-space:pre-wrap;">';

    foreach ($meta as $k => $v) {
        $allText .= "META KEY: ".$k."\n";

        foreach ($v as $val) {
            $allText .= $val."\n\n";
        }
    }

    echo htmlentities($allText);
    echo '</pre>';
    ?>

    <script>
    document.getElementById('copyMetaBtn').addEventListener('click', () => {
        const text = document.getElementById('metaDump').innerText;
        navigator.clipboard.writeText(text).then(() => {
            const btn = document.getElementById('copyMetaBtn');
            btn.innerText = '✅ Copied!';
            setTimeout(() => btn.innerText = '📋 COPY ALL META', 1500);
        });
    });
    </script>

    <?php
    echo '</div>';
});*/

