<?php
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

    // --- TABS SECTION (Must be UL/LI for Theme JS to work) ---
    if ($players || $trailer) {
        
        // The ID 'playeroptionsul' is critical for the theme's JS
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
                // Determine if active (JS usually handles this)
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

    // Ajax Container
    if ($ajax_player == true) {
        echo "<div id='dooplay_player_response'></div>";
    } else {
        // Non-Ajax Fallback
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
        echo "</div>"; // End player_content
    }
    echo '</div>'; // End player-frame

    echo '</div>'; // End player-inner
    echo '</section>';
}

function nf_enqueue_custom_assets() {
    // Only load on single posts to save performance
    if ( is_single() ) {
        
        // Enqueue CSS
        wp_enqueue_style(
            'netflinks-style', 
            get_stylesheet_directory_uri() . '/assets/css/netflinks.css', 
            array(), 
            '1.0.0'
        );

        // Enqueue JS
        wp_enqueue_script(
            'netflinks-script', 
            get_stylesheet_directory_uri() . '/assets/js/netflinks.js', 
            array(), // no dependencies (vanilla JS)
            '1.0.0', 
            true // load in footer
        );

        // Pass PHP data to JS (The SVG Path)
        wp_localize_script('netflinks-script', 'nfLinksData', array(
            'svg_path' => get_stylesheet_directory_uri() . '/inc/parts/single/svg/'
        ));
    }
}
add_action('wp_enqueue_scripts', 'nf_enqueue_custom_assets');

?>