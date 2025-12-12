<?php
/*
* -------------------------------------------------------------------------------------
* @author: Doothemes
* @author URI: https://doothemes.com/
* -------------------------------------------------------------------------------------
*/

$classlinks = new DooLinks;
$postmeta = doo_postmeta_movies($post->ID);

// Meta
$trailer = doo_isset($postmeta,'youtube_id');
$pviews  = doo_isset($postmeta,'dt_views_count');
$player  = maybe_unserialize( doo_isset($postmeta,'players') );
$images  = doo_isset($postmeta,'imagenes');

$tviews  = ($pviews) ? sprintf(__d('%s Views'), $pviews) : __d('0 Views');

// Image logic
$dynamicbg  = dbmovies_get_rand_image($images);
$poster_url = dbmovies_get_poster($post->ID,'large');

if (empty($dynamicbg)) $dynamicbg = $poster_url;

// Options
$player_ads = doo_compose_ad('_dooplay_adplayer');
$player_wht = dooplay_get_option('playsize','regular');

// --- NEW LOGIC: Match Percentage ---
$imdbRating = doo_isset($postmeta,'imdbRating');
$match_percent = ($imdbRating) ? (floatval($imdbRating) * 10) : 0;
$match_label = ($match_percent > 0) ? $match_percent . '% Match' : 'Match';

// --- NEW LOGIC: Country ---
$countries = get_the_terms($post->ID, 'country'); 
$country_name = '';
if ($countries && !is_wp_error($countries)) {
    $country_name = $countries[0]->name; 
}

/* =============================
   Helper Functions 
============================= */


// ... existing code ...

/* ==================================================
   FAKE PLAYER LOGIC
   ================================================== */
// 1. Get Theme Options
$enable_fake   = dooplay_get_option('fakeplayer');
$autoload_fake = dooplay_get_option('playautoload'); // Fake player usually runs when AutoLoad is OFF
$fake_links    = dooplay_get_option('fakeplayerlinks');
$fake_backdrop = !empty($dynamicbg) ? $dynamicbg : dooplay_get_option('fakebackdrop'); 

// 2. Determine if we show it
// Logic: Enabled + AutoLoad is OFF + We have links to show
$show_fake = ($enable_fake && !$autoload_fake && !empty($fake_links) && is_array($fake_links));

// 3. Select Random Link
$fake_link_url = '';
if ($show_fake) {
    $rnd = array_rand($fake_links);
    $fake_link_url = esc_url($fake_links[$rnd]['link']);
}


// 1. YouTube Helper: Converts ID/Link to Embed URL
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

// 2. Player Data Processor: Handles Iframes, MP4, Drive, & Shortcodes
function get_player_data($srv, $post_id) {
    $type = isset($srv['select']) ? $srv['select'] : 'iframe';
    $url  = isset($srv['url']) ? $srv['url'] : '';
    
    if (empty($url)) return false;

    if ($type === 'dtshcode') {
        // Render Shortcode/HTML directly
        return [
            'type' => 'html',
            'content' => do_shortcode($url)
        ];
    } elseif ($type === 'mp4' || $type === 'gdrive') {
        // Wrap MP4/GDrive in Theme Player
        $base = function_exists('doo_compose_pagelink') ? doo_compose_pagelink('jwpage') : home_url('/jwplayer/');
        $src  = $base . "?source=" . urlencode($url) . "&id=" . $post_id . "&type=" . $type;
        return [
            'type' => 'url',
            'content' => $src
        ];
    } else {
        // Standard Iframe: Clean URL
        if (preg_match('/src=["\']([^"\']+)["\']/', $url, $m)) {
            $url = $m[1];
        }
        return [
            'type' => 'url',
            'content' => $url
        ];
    }
}

/* =============================
   Data Preparation
============================= */

// 1. Generate Trailer Embed URL (Required for Hero Button)
$trailer_embed = (!empty($trailer)) ? ytembed($trailer) : '';

// 2. Process Player Sources (Required for Player Tabs)
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

// 3. Add Trailer as the Last Tab
if(!empty($trailer_embed)){
    $sources_data[] = [
        'label'   => 'Trailer',
        'type'    => 'url',
        'content' => $trailer_embed
    ];
}

?>
<style>
/* --- GLOBAL --- */
.dtsingle{width:100%;max-width:100%;background:#141414;color:#fff;font-family:'Helvetica Neue',Arial,sans-serif; overflow-x: hidden;}

/* --- FAKE PLAYER UI (Strict Fix) --- */
.fakeplayer {
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    z-index: 50;
    background: #000;
    overflow: hidden;
    cursor: pointer;
    display: block;
}

.fakeplayer a {
    display: block;
    width: 100%;
    height: 100%;
    position: relative;
    text-decoration: none;
}

/* Background Image with Dark Overlay */
.fakeplayer .cover {
    position: absolute !important;
    top: 0;
    left: 0;
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
    opacity: 0.6;
    transition: transform 0.5s ease, opacity 0.5s ease;
    will-change: transform;
    border: none !important;
    margin: 0 !important;
}

.fakeplayer:hover .cover {
    opacity: 0.4;
    transform: scale(1.05);
}

/* Centered Play Button Container */
.fakeplayer .playboxc {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 20px;
    z-index: 2;
    pointer-events: none;
    background: transparent;
    width: 100%;
    height: 100%;
}

/* The Red Play Button Circle */
.fakeplayer .play-btn {
    width: 90px;
    height: 90px;
    background: rgba(229, 9, 20, 0.9); /* Netflix Red */
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 40px rgba(229, 9, 20, 0.5);
    transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), background 0.3s;
}

.fakeplayer:hover .play-btn {
    transform: scale(1.15);
    background: #e50914;
    box-shadow: 0 0 60px rgba(229, 9, 20, 0.7);
}

.fakeplayer .play-btn i {
    color: #fff;
    font-size: 35px;
    margin-left: 6px;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.fakeplayer .play-text {
    font-size: 1.1rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 2px;
    color: #fff;
    text-shadow: 0 2px 10px rgba(0,0,0,0.8);
    opacity: 0.9;
    text-align: center;
}

/* HD Badge */
.fakeplayer .quality {
    position: absolute;
    top: -10px;
    right: -20px;
    background: #46d369;
    color: #000;
    font-weight: 900;
    font-size: 0.75rem;
    padding: 2px 6px;
    border-radius: 3px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.5);
    transform: rotate(10deg);
}

/* Ad Notice */
.fakeplayer .ad-notice {
    margin-top: 10px;
    font-size: 0.85rem;
    color: #bbb;
    background: rgba(0,0,0,0.7);
    padding: 5px 12px;
    border-radius: 50px;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 1px;
    display: flex;
    align-items: center;
    gap: 6px;
    border: 1px solid rgba(255,255,255,0.1);
}
.fakeplayer .ad-notice i {
    color: #f5c518;
}



/* Mobile Adjustments */
@media (max-width: 768px) {
    .fakeplayer .play-btn { width: 70px; height: 70px; }
    .fakeplayer .play-btn i { font-size: 28px; margin-left: 4px; }
    .fakeplayer .play-text { font-size: 0.9rem; }
}

/* --- ANIMATION: BOTTOM TO TOP --- */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translate3d(0, 50px, 0);
    }
    to {
        opacity: 1;
        transform: translate3d(0, 0, 0);
    }
}

/* Base class to hide elements before animation starts */
.animate-on-load {
    opacity: 0; /* Start hidden */
    animation: fadeInUp 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
}

/* Delays for Staggered Effect (Cinematic feel) */
.delay-1 { animation-delay: 0.1s; }
.delay-2 { animation-delay: 0.2s; }
.delay-3 { animation-delay: 0.3s; }
.delay-4 { animation-delay: 0.4s; }
.delay-5 { animation-delay: 0.5s; }
.delay-long { animation-delay: 0.8s; }


/* --- SKELETON LOADING & LAZY LOAD STYLES --- */
@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

.skeleton {
    background: #222;
    background: linear-gradient(90deg, #222 25%, #333 50%, #222 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
    min-height: 100%; 
    display: block;
}

.lazy-loaded-img {
    opacity: 0;
    transition: opacity 0.5s ease-in-out;
}

.lazy-loaded-img.is-visible {
    opacity: 1;
}

/* --- HERO SECTION --- */
.hero {
    width: 100%;
    height: 80vh; 
    background-size: cover;
    background-position: center top;
    background-attachment: scroll; 
    will-change: background-position;
    position: relative;
    display: flex;
    align-items: flex-end; 
    padding-bottom: 0;
    margin-bottom: 0;
    box-shadow: inset 0 0 150px rgba(0,0,0,0.5); 
}

.hero::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, #141414 15%, rgba(20,20,20,0.8) 35%, transparent 70%);
    z-index: 1;
}

.hero::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(to right, rgba(0,0,0,0.95) 0%, rgba(0,0,0,0.7) 40%, transparent 85%);
    z-index: 1;
}

.hero-content {
    position: relative;
    z-index: 10;
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 40px 60px 40px; 
    display: flex;
    align-items: flex-end;
    gap: 40px;
    bottom: 0; 
}

.hero-poster {
    width: 240px;
    min-width: 240px;
    border-radius: 4px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0,0,0,0.8);
    display: none;
    background: #222; 
    min-height: 360px;
    /* Animation Initial State */
    opacity: 0;
    animation: fadeInUp 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
    cursor: pointer;
}


.hero-poster img { 
    width: 100%; 
    height: auto; 
    display: block; 
    border-radius: 4px;
}

.meta { flex: 1; text-shadow: 2px 2px 4px rgba(0,0,0,0.8); }

/* Apply animation to Hero Text elements */
.title { 
    font-size: 3.5rem; font-weight: 800; line-height: 1.1; margin-bottom: 15px; 
}

/* --- BADGES (PILL DESIGN) --- */
.badges { 
    display: flex; 
    align-items: center; 
    gap: 10px; 
    font-weight: 600; 
    color: #e5e5e5; 
    font-size: 0.95rem; 
    margin-bottom: 20px; 
    flex-wrap: wrap;
}

.badges span {
    background: rgba(255, 255, 255, 0.2); 
    border: 1px solid rgba(255, 255, 255, 0.3); 
    padding: 6px 16px; 
    border-radius: 50px; 
    backdrop-filter: blur(5px); 
    display: inline-flex;
    align-items: center;
    justify-content: center;
    white-space: nowrap;
}

.badges .match-score { 
    color: #46d369; 
    border-color: rgba(70, 211, 105, 0.5); 
    background: rgba(70, 211, 105, 0.1); 
    font-weight: 800;
}

.badges .country-pill {
    color: #fff;
    border-color: rgba(70, 211, 105, 0.5); 
}
.badges .country-pill i {
    margin-right: 6px;
    color: #46d369;
}


.desc { max-width: 700px; color: #fff; font-size: 1.2rem; line-height: 1.5; margin-bottom: 25px; text-shadow: 1px 1px 2px rgba(0,0,0,0.8); }

.hero-buttons { display: flex; gap: 15px; margin-top: 20px; }
.btn-hero {
    display: inline-flex; align-items: center;
    padding: 10px 24px; border-radius: 4px;
    font-size: 1.1rem; font-weight: bold;
    text-decoration: none; cursor: pointer;
    transition: transform 0.2s, opacity 0.2s;
}
.btn-hero:hover { transform: scale(1.05); }
.btn-hero i { margin-right: 10px; font-size: 1.3rem; }

.btn-play { background: #fff; color: #000; }
.btn-play:hover { background: rgba(255,255,255,0.8); color: #000; }

.btn-more { background: rgba(109, 109, 110, 0.7); color: #fff; }
.btn-more:hover { background: rgba(109, 109, 110, 0.4); color: #fff; }

/* --- DIRECTOR CARD --- */
.hero-director-card {
    margin-left: auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    background: rgba(0, 0, 0, 0.6);
    padding: 15px;
    border-radius: 12px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    max-width: 150px;
    text-align: center;
    /* Animation handled by class below */
}
.hero-director-card img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e50914;
    box-shadow: 0 4px 15px rgba(0,0,0,0.5);
}
.hero-director-card span {
    font-size: 0.9rem;
    font-weight: 600;
    color: #ddd;
}
.hero-director-card .label {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: #aaa;
    margin-bottom: -5px;
    letter-spacing: 1px;
}

/* --- GENRES BOX --- */
.genres-box {
    margin-top: 20px;
    font-size: 1rem;
    color: #ccc;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.8);
}
.genres-box a {
    color: #fff;
    text-decoration: none;
    font-weight: 500;
}
.genres-box a:hover {
    text-decoration: underline;
}
.genres-dot {
    color: #e50914; 
    margin: 0 6px;
    font-weight: bold;
}


/* --- PLAYER SECTION --- */
.player-wrapper { 
    position: relative;
    z-index: 20;
    padding: 0;
    background: #000; 
    border-top: none; 
    margin-top: -1px;
    /* Animate player wrapper */
    opacity: 0; 
    animation: fadeInUp 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
    animation-delay: 0.6s;
    
}

.player-header { 
    max-width: 1400px; 
    margin: auto; 
    display: flex; 
    justify-content: space-between; 
    padding: 20px 40px 20px; 
}

.player-tabs { list-style: none; display: flex; flex-wrap: wrap; gap: 10px; max-width: 1400px; margin: 0 auto 20px; padding: 0 40px; }
.player-tabs .tab { 
    background: #222; border: 1px solid #333; 
    padding: 10px 20px; border-radius: 4px; 
    cursor: pointer; font-weight: 700; font-size: 0.9rem; 
    text-transform: uppercase; color: #aaa; transition: 0.3s;
}
.player-tabs .tab:hover { color: #fff; background: #333; }
.player-tabs .tab.selected { background: #e50914; color: #fff; border-color: #e50914; }

.player-frame { max-width: 1400px; height: 75vh; margin: auto; background: #000; position: relative;  }
.player-frame iframe { width: 100%; height: 100%; border: 0; }
.player-content-area { height:100%;}

/* --- MODAL --- */
.tm { position: fixed; inset: 0; background: rgba(0,0,0,0.95); display: none; align-items: center; justify-content: center; z-index: 99999; padding: 20px; }
.tm.active { display: flex; }
.tm-box { position: relative; background: #000; box-shadow: 0 0 50px rgba(0,0,0,1); width: 90%; max-width: 1200px; aspect-ratio: 16 / 9; height: auto; }
.tm-close { position: absolute; top: -40px; right: 0; background: none; border: 0; color: #fff; font-size: 30px; cursor: pointer; transition: 0.2s; opacity: 0.7; }
.tm-close:hover { opacity: 1; transform: scale(1.1); }

/* --- SECTIONS --- */
.section { 
    max-width: 1400px; margin: auto; padding: 40px; 
    /* Animate Content Section */
    opacity: 0;
    animation: fadeInUp 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
    animation-delay: 0.8s;
}

.netflix-section-title { 
    font-size: 1.5rem; 
    color: #e5e5e5; 
    margin: -20px 0 35px; 
    font-weight: 600; 
}


/* =========================================
   FIXED CSS: STRONGER SELECTORS
========================================= */

/* --- DIRECTOR (Horizontal Card) --- */
#single #dt-director .person {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    gap: 18px !important;
    background: #141414 !important;
    padding: 16px !important;
    border-radius: 12px !important;
    border: 1px solid rgba(255,255,255,0.12) !important;
    max-width: 400px !important;
    margin-top: -12px !important;
    position: relative !important;
    width: auto !important;
    float: none !important;
}

#single #dt-director .person .img {
    width: 80px !important;
    height: 80px !important;
    border-radius: 50% !important;
    overflow: hidden !important;
    border: 2px solid #e50914 !important;
    flex-shrink: 0 !important;
    margin: 0 !important;
    position: static !important;
    background: #222; 
}
#single #dt-director .person .img img {
    width: 100% !important;
    object-fit: cover !important;
}

#single #dt-director .person .data {
    display: flex !important;
    flex-direction: column !important;
    justify-content: center !important;
    position: static !important;
    background: transparent !important;
    width: auto !important;
    height: auto !important;
    padding: 0 !important;
    margin: 0 !important;
    opacity: 1 !important;
}
#single #dt-director .person .name, 
#single #dt-director .person .name a {
    font-size: 1.2rem !important;
    font-weight: 700 !important;
    color: #fff !important;
    text-decoration: none !important;
    text-align: left !important;
    display: block !important;
}
#single #dt-director .person .caracter {
    font-size: 0.9rem !important;
    color: #ccc !important;
    margin-top: 2px !important;
    text-align: left !important;
    display: block !important;
}


/* --- CAST (Horizontal Strip) --- */
#dt-cast {
    display: flex !important;
    gap: 16px !important;
    overflow-x: auto !important;
    padding-bottom: 20px !important;
    scrollbar-width: thin;
    scrollbar-color: #e50914 #141414;
    white-space: nowrap !important;
    width: 100% !important;
    float: none !important;
    padding-top: 20px;
    margin-top: -35px;
    padding-left: 15px;
}
#dt-cast::-webkit-scrollbar { height: 8px; }
#dt-cast::-webkit-scrollbar-track { background: #141414; }
#dt-cast::-webkit-scrollbar-thumb { background-color: #e50914; border-radius: 4px; }

/* Cast Card */
#single #dt-cast .person {
    background: #141414 !important;
    width: 150px !important;
    min-width: 150px !important;
    border: 1px solid rgba(255,255,255,0.12) !important;
    border-radius: 12px !important;
    padding: 12px !important;
    text-align: center !important;
    cursor: pointer !important;
    transition: .25s !important;
    flex-shrink: 0 !important;
    position: relative !important;
    display: block !important;
    margin: 0 !important;
    float: none !important;
    height: auto !important; 
}
#single #dt-cast .person:hover {
    transform: translateY(-4px);
    border-color: #e50914 !important;
}

#single #dt-cast .person .img {
    width: 100% !important;
    height: 185px !important;
    overflow: hidden !important;
    border-radius: 10px !important;
    margin-bottom: 10px !important;
    position: static !important;
    opacity: 1 !important;
    background: #222; 
}
#single #dt-cast .person .img img {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
}

#single #dt-cast .person .data {
    position: static !important;
    background: transparent !important;
    width: 100% !important;
    padding: 0 !important;
    margin-top: 5px !important;
    text-align: center !important;
    height: auto !important;
    display: block !important;
    white-space: normal !important; 
}

#single #dt-cast .person .name, 
#single #dt-cast .person .name a {
    font-weight: 800 !important;
    font-size: 1rem !important;
    color: #fff !important;
    text-decoration: none !important;
    display: block !important;
    line-height: 1.2 !important;
}
#single #dt-cast .person .caracter {
    font-size: 0.85rem !important;
    color: #bbb !important;
    margin-top: 4px !important;
    display: block !important;
}

.persons:after { content: ""; display: table; clear: both; }

/* --- RESPONSIVE --- */
@media (max-width: 768px) {
    .hero { height: 65vh; }
    .hero-content { flex-direction: column; align-items: center; text-align: center; padding: 0 20px; bottom: 0; }
    .hero-poster { display: none; }
    
    .hero-director-card { 
        margin-left: 0; 
        margin-top: 20px;
        order: 5; 
        /* Mobile Animation */
        animation-delay: 0.9s;
    }

    .title { font-size: 2.5rem; }
    .badges { justify-content: center; font-size: 0.9rem; }
    .desc { display: none; }
    .hero-buttons { justify-content: center; }
    .player-frame { height: 35vh; }
    
    #single #dt-director .person {
        max-width: 100% !important;
        justify-content: center !important;
        text-align: center !important;
        flex-direction: column !important;
    }
    #single #dt-director .person .img {
        width: 90px !important;
        height: 90px !important;
        margin-bottom: 10px !important;
        margin-right: 0 !important;
    }
    
    .tm { align-items: center; padding: 0; }
    .tm-box { width: 100%; max-width: 100%; aspect-ratio: 16/9; }
    .tm-close { top: -45px; right: 10px; font-size: 35px; }
}

.persons {
    float: left;
    width: 100%;
    margin-bottom: 15px;
}


/* ================================
   FIXED MOBILE HERO CENTERING
================================ */
@media (max-width: 768px) {

    /* HERO WRAPPER */
    .hero {
        min-height: 85vh !important;
        height: auto !important;

        display: flex !important;
        flex-direction: column !important;
        justify-content: center !important;
        align-items: center !important;

        padding: 90px 0 50px !important;

        text-align: center !important;
        background-position: center top !important;

        /* Strong dark overlay for readability */
        box-shadow: inset 0 0 0 1200px rgba(0,0,0,0.75) !important;
    }

    /* Remove gradients (mobile only) */
    .hero::before,
    .hero::after {
        display: none !important;
    }

    /* CONTENT WRAPPER */
    .hero-content {
        width: 100% !important;
        max-width: 420px !important;

        display: flex !important;
        flex-direction: column !important;
        justify-content: center !important;
        align-items: center !important;

        padding: 0 20px !important;
        margin: 0 auto !important;

        text-align: center !important;
        position: relative !important;
    }

    /* POSTER */
    .hero-poster {
        display: block !important;
        max-width: 180px !important;
        margin: 0 auto 25px !important;

        transform: none !important;
        box-shadow: 0 10px 25px rgba(0,0,0,0.6) !important;
    }

    .hero-poster img {
        width: 100% !important;
        height: auto !important;
        border-radius: 6px !important;
    }

    /* TITLE */
    .title {
        font-size: 2.2rem !important;
        margin: 10px 0 10px !important;
        line-height: 1.2 !important;
        text-align: center !important;
    }

    /* BADGES */
    .badges {
        justify-content: center !important;
        flex-wrap: wrap !important;
        gap: 8px !important;
        margin-bottom: 15px !important;
    }

    .badges span {
        font-size: 0.85rem !important;
        padding: 5px 12px !important;
    }

    /* HIDE DESCRIPTION ON MOBILE */
    .desc {
        display: none !important;
    }

    /* BUTTONS */
    .hero-buttons {
        width: 100%;
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        flex-wrap: wrap !important;
        gap: 12px !important;
    }

    .btn-hero {
        min-width: 150px !important;
        justify-content: center !important;
        padding: 12px 25px !important;
    }

    /* Center Genres, Rating */
    .genres-box {
        text-align: center !important;
        margin-top: 10px !important;
    }

    .netflix-rate-wrap {
        margin: 10px 0 !important;
    }

    /* Hide director card inside hero for mobile */
    .hero-director-card {
        display: none !important;
    }
}

/* =========================================================
   PURE CSS 3D EFFECTS (Cast, Director & Poster)
========================================================= */

.dtsingle, .section, .hero-content {
    perspective: 10000px; 
}

#single #dt-cast .person, 
#single #dt-director .person,
.hero-poster { /* Added Poster back here */
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    transform-style: preserve-3d; 
}

#single #dt-cast .person:hover, 
#single #dt-director .person:hover {
    transform: translateY(-10px) scale(1.02);
    box-shadow: 0 15px 30px rgba(229, 9, 20, 0.15); 
    z-index: 10;
    border-color: #e50914;
}

#single #dt-cast .person .img,
#single #dt-director .person .img,
.hero-poster img { /* Added Poster Image back here for depth */
    transition: transform 0.3s ease;
}

#single #dt-cast .person:hover .img,
#single #dt-director .person:hover .img {
    transform: translateZ(20px); 
}

#single #dt-cast .person .data,
#single #dt-director .person .data {
    transition: transform 0.3s ease;
}

#single #dt-cast .person:hover .data,
#single #dt-director .person:hover .data {
    transform: translateZ(30px); 
}

.btn-hero {
    transition: transform 0.1s linear, box-shadow 0.1s linear;
}

.btn-hero:active {
    transform: translateY(2px) scale(0.98);
    box-shadow: inset 0 3px 5px rgba(0,0,0,0.3);
}

/* =========================================
   MOBILE PLAYER & SOURCES FIX (Netflix Style)
   ========================================= */
@media (max-width: 768px) {
    
    .player-wrapper {
        padding: 30px 0 30px !important; 
        border-top: none !important;
    }

    .player-header {
        padding: 0 20px 15px !important;
    }
    .player-header h3 {
        font-size: 1.1rem !important;
        font-weight: 700 !important;
        color: #fff !important;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .player-tabs {
        display: flex !important;
        flex-wrap: nowrap !important;       
        overflow-x: auto !important;        
        gap: 12px !important;
        padding: 0 20px 10px !important;    
        margin: 0 !important;
        scrollbar-width: none;              
        -webkit-overflow-scrolling: touch;  
        align-items: center;
    }

    .player-tabs::-webkit-scrollbar {
        display: none;
    }

    .player-tabs .tab {
        flex: 0 0 auto !important;          
        padding: 8px 24px !important;
        border-radius: 50px !important;     
        background: #1f1f1f !important;     
        border: 1px solid #333 !important;
        font-size: 0.85rem !important;
        font-weight: 600 !important;
        color: #bbb !important;
        text-transform: capitalize !important; 
        letter-spacing: 0.5px;
    }

    .player-tabs .tab.selected {
        background: #e50914 !important;     
        border-color: #e50914 !important;
        color: white !important;
        box-shadow: 0 4px 15px rgba(229, 9, 20, 0.4); 
    }

    .player-frame {
        height: 240px !important; 
        background: #000;
        margin-bottom: 0;
    }
}
/* --- RATING CSS (Netflix Style) --- */
.netflix-rate-wrap {
    margin-bottom: 5px; 
    display: inline-block;
}
.netflix-rate-wrap .box-rating,
.netflix-rate-wrap #star-struck-rating {
    background: rgba(0,0,0,0.4) !important; 
    border: 1px solid rgba(255,255,255,0.2) !important;
    border-radius: 4px;
    box-shadow: none !important;
    padding: 5px 15px !important;
    color: #fff !important;
}
.netflix-rate-wrap .rating-result .val {
    color: #46d369 !important; 
}
.netflix-rate-wrap .rating-result {
    border-right: 1px solid rgba(255,255,255,0.2) !important;
}


</style>

<div id="edit_link"></div>

<div id="single" class="dtsingle" itemscope itemtype="http://schema.org/Movie">

<?php if(have_posts()): while(have_posts()): the_post(); ?>

<section class="hero" style="background-image:url('<?php echo $dynamicbg; ?>')">
    <div class="hero-content">
        
        <div class="hero-poster" style="display:block;">
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
                <span class="match-score"><?php echo $match_label; ?></span> 
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
                <?php echo get_the_term_list($post->ID, 'genres', '', ' <span class="genres-dot">&bull;</span> ', ''); ?>
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

        <svg xmlns="http://www.w3.org/2000/svg"
            width="50" height="50"
            viewBox="0 0 512 512"
            fill="#E50914"
            style="margin-right:6px;">
            <path d="m147.672 110.469 3.882-.012q4.039-.008 8.078.022c3.44.022 6.877.01 10.315-.015 3.304-.018 6.608-.005 9.912.005l3.722-.025c6.41.07 10.6.137 15.419 4.556 4.605 5.227 5.358 8.307 5.277 15.172-.425 4.336-1.599 6.374-4.277 9.828-12.758 10.1-26.857 5-44 5v117h200V145h-34c-6.695-2.87-11.217-5.171-14-12-.68-5.24-.657-9.661 1.688-14.437 6.68-7.402 6.68-7.402 11.725-7.71l3.071-.03 3.482-.039 3.784-.022 3.888-.025q4.086-.022 8.171-.032c3.465-.011 6.93-.045 10.395-.085 3.328-.033 6.656-.037 9.983-.046l3.736-.053c13.208.033 22.303 4.688 32.077 13.479 8.961 9.88 12.108 21.364 14.857 34.15l.775 3.518q1.037 4.724 2.057 9.453.64 2.966 1.286 5.93a9153 9153 0 0 1 4.49 20.762c1.38 6.417 2.78 12.83 4.19 19.24a3795 3795 0 0 1 3.607 16.618q1.062 4.944 2.15 9.882c.81 3.676 1.597 7.356 2.378 11.038l.729 3.235c2.215 10.677.908 20.809-.946 31.446l-.424 2.518q-.687 4.046-1.387 8.089l-.976 5.71q-1.02 5.952-2.05 11.903c-.875 5.053-1.735 10.11-2.592 15.166a4194 4194 0 0 1-2.02 11.77q-.482 2.793-.954 5.589c-3.08 18.207-6.272 33.676-22.17 44.983-6.945 3.57-13.77 6.104-21.63 6.147l-2.727.022-2.954.008-3.14.02q-5.134.028-10.268.041l-3.544.013q-9.278.03-18.555.044c-6.392.011-12.785.046-19.177.085-4.914.026-9.828.035-14.742.038q-3.535.008-7.071.035c-3.294.025-6.587.024-9.881.017l-2.954.039c-4.288-.033-6.705-.115-10.44-2.37-2.365-2.638-2.844-4.428-3.429-7.909l-.604-3.424-.572-3.618-.627-3.672c-1.429-8.482-2.675-16.974-3.685-25.516h-22l-1.25 9.188c-.563 3.94-1.152 7.877-1.75 11.812l-.454 2.991a563 563 0 0 1-1.421 8.572l-.415 2.682-.452 2.509-.37 2.197c-1.373 3.169-2.938 4.226-5.888 6.049-3.393.593-6.746.547-10.184.518l-3.101.02c-3.384.018-6.768.007-10.153-.007l-7.082.012q-7.429.008-14.858-.022c-6.32-.022-12.64-.01-18.96.015-4.88.014-9.76.01-14.64 0q-3.492-.005-6.984.01c-18.27.057-32.712-.959-46.788-13.796-5.957-6.37-8.37-13.468-10.179-21.89l-.519-2.381c-1.914-8.933-3.475-17.923-5.005-26.928l-.925-5.388a7462 7462 0 0 1-1.905-11.17q-1.215-7.14-2.448-14.276-.953-5.537-1.894-11.075-.45-2.633-.905-5.266-.63-3.668-1.248-7.34l-.378-2.161c-1.453-8.737-.388-16.024 1.463-24.57l.744-3.517q1.01-4.753 2.034-9.503.857-3.983 1.707-7.968 2.011-9.416 4.042-18.828 2.08-9.66 4.134-19.33a6420 6420 0 0 1 3.567-16.668q1.069-4.959 2.121-9.922 1.182-5.552 2.39-11.097l.682-3.253c3.622-16.468 9.364-28.385 23.17-38.43 8.935-5.153 16.502-6.388 26.774-6.32M122.64 137.73c-4.828 6.68-6.635 14.51-8.298 22.45l-.711 3.274c-.767 3.544-1.52 7.09-2.273 10.636q-.797 3.707-1.597 7.413a6984 6984 0 0 0-3.318 15.49c-1.552 7.289-3.119 14.574-4.687 21.86-1.22 5.661-2.433 11.324-3.648 16.987l-.787 3.667q-1.094 5.108-2.182 10.22l-.661 3.08-.595 2.8-.52 2.439c-.425 1.95-.425 1.95-.364 3.954h46V129c-6.65 0-11.746 4.307-16.36 8.73M373 129v133h46c-4.004-20.359-8.244-40.664-12.583-60.954a4249 4249 0 0 1-3.197-15.067q-1.557-7.41-3.149-14.812-.596-2.787-1.176-5.577c-3.22-17.281-3.22-17.281-13.395-31.09l-2.219-1.906C379.885 130.22 377.161 129 373 129M93 279c.609 6.09 1.239 11.947 2.228 17.953l.36 2.202q.583 3.548 1.178 7.095l.413 2.475q1.081 6.477 2.176 12.95.898 5.323 1.769 10.65 1.06 6.478 2.162 12.95.413 2.448.808 4.898c2.599 16.708 2.599 16.708 12.88 29.545 6.441 4.076 13.323 4.716 20.768 4.623h2.812c3.036-.001 6.07-.024 9.106-.048q3.175-.009 6.351-.013a4223 4223 0 0 0 16.645-.074c5.672-.03 11.343-.044 17.014-.06q16.665-.05 33.33-.146l.595-3.5c.733-4.298 1.48-8.593 2.23-12.888q.486-2.785.958-5.572.684-4.012 1.393-8.02l.417-2.507c.784-4.376 1.59-7.934 4.407-11.513 3.95-1.317 7.747-1.182 11.867-1.203l2.642-.017q2.766-.014 5.533-.02c2.815-.01 5.63-.041 8.446-.072q2.69-.01 5.38-.016l2.544-.038c4.636.018 7.912.208 11.588 3.366.871 1.827.871 1.827 1.223 3.895l.443 2.404c.13.83.262 1.659.397 2.514l.408 2.29q.454 2.564.882 5.13c.87 5.063 1.793 10.115 2.715 15.168l.563 3.092L289 384q18.346.106 36.693.155c5.68.016 11.36.037 17.04.071 5.485.033 10.97.05 16.456.059q3.133.008 6.266.032c2.934.022 5.868.025 8.803.024l2.593.032c8.137-.041 15.583-1.82 21.603-7.56 7.292-8.55 8.344-19.659 10.097-30.333q.458-2.685.92-5.369.958-5.595 1.888-11.198c.794-4.781 1.606-9.56 2.424-14.337.783-4.573 1.557-9.147 2.33-13.72l.447-2.632q.623-3.69 1.234-7.38l.373-2.193c.6-3.653.833-6.917.833-10.651z" fill="#e50914"/><path d="m310.566 296.369 2.58-.022a618 618 0 0 1 8.416.024q2.931 0 5.862-.007 6.138-.003 12.276.04c5.243.033 10.484.027 15.727.008 4.034-.01 8.067-.001 12.101.014q2.9.008 5.799-.002c2.703-.005 5.404.015 8.107.043l2.415-.02c3.634.062 5.957.44 9.124 2.274 2.306 2.593 2.833 3.844 3.027 7.279-.201 2.58-.201 2.58-.665 5.354l-.517 3.165-.584 3.387-.588 3.508q-.619 3.667-1.251 7.333a1883 1883 0 0 0-1.883 11.218q-.606 3.564-1.215 7.129l-.56 3.387-.554 3.165-.474 2.774c-.806 2.934-1.974 5.088-3.709 7.58-2.921 1.46-5.51 1.152-8.773 1.177l-2.11.02q-3.46.028-6.922.041l-2.386.013q-6.251.03-12.502.044c-4.307.011-8.613.046-12.92.085-3.309.026-6.618.035-9.928.038q-2.382.008-4.763.035c-2.222.025-4.442.024-6.664.017l-3.831.018c-3.765-.574-4.913-1.51-7.201-4.488-.742-1.922-.742-1.922-1.09-3.965l-.43-2.382-.418-2.59-.475-2.728q-.52-3-1.022-6.004a889 889 0 0 0-1.928-10.85q-.695-3.865-1.387-7.731l-.676-3.71c-2.268-12.982-2.268-12.982-1.574-19.04 3.847-5.227 7.42-5.721 13.566-5.631m-179.416.486 2.553-.01q4.19-.013 8.383-.013l5.814-.01q6.1-.009 12.202-.007c5.214 0 10.428-.014 15.643-.031q6.006-.015 12.014-.013 2.884 0 5.767-.013c2.689-.01 5.377-.007 8.065 0 .796-.007 1.592-.012 2.412-.018 6.858.04 6.858.04 9.997 2.26 5.777 8.382-.676 27.037-2.274 36.339-.542 3.179-1.06 6.361-1.58 9.544q-.518 3.043-1.04 6.086l-.459 2.872-.474 2.673-.395 2.34c-1.073 2.959-2.242 4.27-4.778 6.146-3.225.515-3.225.515-7.092.533l-2.14.027c-2.33.022-4.657.015-6.987.006q-2.432.008-4.864.02-5.094.014-10.188-.01c-4.352-.017-8.702.007-13.054.042-3.347.022-6.694.02-10.042.012q-2.407 0-4.813.02c-2.244.018-4.485.004-6.728-.019l-3.867-.003C130 365 130 365 127.63 362.742c-1.526-2.568-2.298-4.338-2.803-7.25l-.474-2.612-.458-2.79-.514-2.92a903 903 0 0 1-1.046-6.114c-.517-3.086-1.056-6.168-1.599-9.25-4.714-27.086-4.714-27.086-1.923-31.994 3.98-3.297 7.366-2.96 12.337-2.957"/>
        </svg>

        Watch NOW
    </h3>
</div>


    <div class="dooplay-ad-player" style="text-align:center; margin: 0 auto 15px; max-width: 100%; overflow: hidden;">
        <?php echo doo_compose_ad('_dooplay_adplayer'); ?>
    </div>

    <ul class="player-tabs" id="player-tabs">
        <?php
        // Prepare Data for JS and Tabs
        $sources_data = [];
        $count = 0;

        // 1. Process Main Players
        if (!empty($player) && is_array($player)) {
            foreach($player as $srv) {
                // Check if our new helper function exists
                if(function_exists('get_player_data')) {
                    $processed = get_player_data($srv, $post->ID);
                    
                    if ($processed) {
                        $label = !empty($srv['name']) ? $srv['name'] : "Server " . ($count + 1);
                        
                        // Add to data array for JS
                        $sources_data[] = [
                            'label'   => $label,
                            'type'    => $processed['type'],
                            'content' => $processed['content']
                        ];
                        
                        // Print the Tab
                        echo "<li class='tab' data-index='{$count}'>{$label}</li>";
                        $count++;
                    }
                }
            }
        }

        // 2. Process Trailer (if available)
        if(!empty($trailer_embed)){
            $sources_data[] = [
                'label'   => 'Trailer',
                'type'    => 'url',
                'content' => $trailer_embed
            ];
            echo "<li class='tab' data-index='{$count}'>Trailer</li>";
        }

        // 3. Fallback if empty
        if(empty($sources_data)){
            echo "<li class='tab' style='cursor:default; opacity:0.5;'>No video available</li>";
        }
        ?>
    </ul>

    <div class="player-frame">

    <?php if($show_fake): ?>
    <!-- FIXED ASPECT-RATIO WRAPPER -->
    <div class="fakeplayer-wrapper">

        <div id="fakeplayer" class="fakeplayer">
            <a id="clickfakeplayer" href="<?php echo $fake_link_url; ?>" target="_blank" rel="nofollow">
                
                <img class="cover" src="<?php echo $fake_backdrop; ?>" alt="Cover">
                
                <div class="playboxc">
                    <div style="position:relative;">
                        <div class="play-btn">
                            <i class="fa-solid fa-play"></i>
                        </div>

                        <?php if(doo_is_true('fakeoptions','qua')) echo '<span class="quality">HD</span>'; ?>
                    </div>

                    <span class="play-text"><?php _d('Click to Play'); ?></span>

                    <?php if(doo_is_true('fakeoptions','ads')): ?>
                    <span class="ad-notice">
                        <i class="fa-solid fa-circle-info"></i> <?php _d('Advertisement'); ?>
                    </span>
                    <?php endif; ?>
                </div>

            </a>
        </div>

    </div> <!-- END fakeplayer-wrapper -->
    <?php endif; ?>


    <!-- REAL PLAYER AREA -->
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

<script>
// 1. Pass PHP Data to JS
var playerSources = <?php echo json_encode($sources_data); ?>;

document.addEventListener("DOMContentLoaded", () => {

    /* =========================================================
       VARIABLES & SETUP
       ========================================================= */
    const tabs = document.querySelectorAll("#player-tabs .tab");
    const container = document.getElementById("playerContent");
    const fakePlayer = document.getElementById('fakeplayer');
    const fakeClick  = document.getElementById('clickfakeplayer');

    /* =========================================================
       PLAYER LOGIC (iframe / html switcher)
       ========================================================= */
    function loadSource(index) {
        if(!playerSources[index]) return;
        const data = playerSources[index];
        
        // Clear container
        container.innerHTML = '';
        
        if (data.type === 'html') {
            // MODE A: Raw HTML (Shortcodes)
            container.innerHTML = data.content;
        } else {
            // MODE B: URL (Iframe, MP4 wrapper, Trailer)
            const iframe = document.createElement('iframe');
            iframe.src = data.content;
            iframe.setAttribute('allow', 'autoplay; fullscreen; encrypted-media');
            iframe.setAttribute('allowfullscreen', '');
            
            // Apply styles
            iframe.style.width = "100%";
            iframe.style.height = "100%";
            iframe.style.border = "none";
            
            container.appendChild(iframe);
        }
    }

    /* =========================================================
       FAKE PLAYER INTERACTION
       ========================================================= */
    // Check if Fake Player exists and is visible
    if(fakePlayer && fakeClick) {
        // Event: Click on Fake Player -> Hide it -> Load First Video
        fakeClick.addEventListener('click', () => {
            fakePlayer.style.display = 'none';
            // Now loadSource is accessible here!
            loadSource(0); 
        });
    } else {
        // No Fake Player? Load video immediately (if sources exist)
        if(playerSources.length > 0) {
            loadSource(0);
        }
    }

    // Initialize First Tab UI (if exists)
    if(tabs.length > 0 && playerSources.length > 0){
        tabs[0].classList.add("selected");
        // Note: We don't call loadSource(0) here if fake player is present, 
        // strictly waiting for the click above.
        if(!fakePlayer) loadSource(0); 
    }

    // Tab Click Event Listener
    tabs.forEach(tab => {
        tab.addEventListener("click", () => {
            // Ensure fake player is hidden if user clicks a specific server tab
            if(fakePlayer) fakePlayer.style.display = 'none';

            // Update UI
            tabs.forEach(t => t.classList.remove("selected"));
            tab.classList.add("selected");
            // Load Content
            loadSource(tab.dataset.index);
        });
    });

    /* =========================================================
       SKELETON & LAZY LOAD LOGIC
       ========================================================= */
    const lazyImageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src; 
                img.onload = () => {
                    img.classList.remove("skeleton");
                    img.classList.add("is-visible");
                };
                observer.unobserve(img);
            }
        });
    });

    const heroImages = document.querySelectorAll('.lazy-load-hero');
    heroImages.forEach(img => lazyImageObserver.observe(img));

    const castImages = document.querySelectorAll('#dt-cast .person .img img, #dt-director .person .img img');
    castImages.forEach(img => {
        img.classList.add('skeleton', 'lazy-loaded-img');
        if(img.complete) {
            img.classList.remove('skeleton');
            img.classList.add('is-visible');
        } else {
            img.addEventListener('load', () => {
                img.classList.remove('skeleton');
                img.classList.add('is-visible');
            });
        }
    });

    /* =========================================================
       MODAL LOGIC (Trailer)
       ========================================================= */
    const heroTrailerBtn = document.getElementById("openTrailerHero");
    const closeTrailerBtn = document.getElementById("closeTrailer");
    const modal = document.getElementById("trailerModal");
    const trailerFrame = document.getElementById("trailerFrame");

    if(heroTrailerBtn){
        heroTrailerBtn.addEventListener("click", (e) => {
            e.preventDefault();
            modal.classList.add("active");
            if(trailerFrame) trailerFrame.src = trailerFrame.dataset.src + "?autoplay=1";
        });
    }

    if(closeTrailerBtn){
        closeTrailerBtn.addEventListener("click", () => {
            modal.classList.remove("active");
            if(trailerFrame) trailerFrame.src = ""; 
        });
    }
    
    window.addEventListener("click", (e) => {
        if (e.target == modal) {
            modal.classList.remove("active");
            if(trailerFrame) trailerFrame.src = "";
        }
    });

});

/* ==================================================
   3D TILT LOGIC (Cast, Director & Poster)
   ================================================== */
const tiltElements = document.querySelectorAll('.hero-poster, #dt-cast .person, #dt-director .person');

if (window.matchMedia("(hover: hover)").matches) {
    tiltElements.forEach(card => {
        card.addEventListener('mousemove', (e) => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left; 
            const y = e.clientY - rect.top;  
            
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            
            const rotateX = ((y - centerY) / centerY) * -15; 
            const rotateY = ((x - centerX) / centerX) * 15;

            card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.05, 1.05, 1.05)`;
        });

        card.addEventListener('mouseleave', () => {
            card.style.transform = `perspective(1000px) rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)`;
        });
    });
}

/* ==================================================
   PARALLAX SCROLL EFFECT
   ================================================== */
window.addEventListener("scroll", function() {
    const hero = document.querySelector(".hero");
    if (!hero) return;
    const limit = hero.offsetHeight;
    const scrolled = window.pageYOffset;

    if (scrolled <= limit) {
        hero.style.backgroundPositionY = (scrolled * 0.5) + "px";
    }
});
</script>