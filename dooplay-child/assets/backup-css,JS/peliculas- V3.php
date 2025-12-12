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
<style>
/* --- GLOBAL --- */
.dtsingle{width:100%;max-width:100%;background:#141414;color:#fff;font-family:'Helvetica Neue',Arial,sans-serif; overflow-x: hidden;}

/* --- FAKE PLAYER UI --- */
.fakeplayer { position: absolute !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 100% !important; z-index: 50; background: #000; overflow: hidden; cursor: pointer; display: block; }
.fakeplayer a { display: block; width: 100%; height: 100%; position: relative; text-decoration: none; }
.fakeplayer .cover { position: absolute !important; top: 0; left: 0; width: 100% !important; height: 100% !important; object-fit: cover !important; opacity: 0.6; transition: transform 0.5s ease, opacity 0.5s ease; will-change: transform; border: none !important; margin: 0 !important; }
.fakeplayer:hover .cover { opacity: 0.4; transform: scale(1.05); }
.fakeplayer .playboxc { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 20px; z-index: 2; pointer-events: none; background: transparent; width: 100%; height: 100%; }
.fakeplayer .play-btn { width: 90px; height: 90px; background: rgba(229, 9, 20, 0.9); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 40px rgba(229, 9, 20, 0.5); transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), background 0.3s; }
.fakeplayer:hover .play-btn { transform: scale(1.15); background: #e50914; box-shadow: 0 0 60px rgba(229, 9, 20, 0.7); }
.fakeplayer .play-btn i { color: #fff; font-size: 35px; margin-left: 6px; text-shadow: 0 2px 4px rgba(0,0,0,0.3); }
.fakeplayer .play-text { font-size: 1.1rem; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #fff; text-shadow: 0 2px 10px rgba(0,0,0,0.8); opacity: 0.9; text-align: center; }
.fakeplayer .quality { position: absolute; top: -10px; right: -20px; background: #46d369; color: #000; font-weight: 900; font-size: 0.75rem; padding: 2px 6px; border-radius: 3px; box-shadow: 0 4px 10px rgba(0,0,0,0.5); transform: rotate(10deg); }
.fakeplayer .ad-notice { margin-top: 10px; font-size: 0.85rem; color: #bbb; background: rgba(0,0,0,0.7); padding: 5px 12px; border-radius: 50px; text-transform: uppercase; font-weight: 600; letter-spacing: 1px; display: flex; align-items: center; gap: 6px; border: 1px solid rgba(255,255,255,0.1); }
.fakeplayer .ad-notice i { color: #f5c518; }
@media (max-width: 768px) {
    .fakeplayer .play-btn { width: 70px; height: 70px; }
    .fakeplayer .play-btn i { font-size: 28px; margin-left: 4px; }
    .fakeplayer .play-text { font-size: 0.9rem; }
}

/* --- ANIMATION --- */
@keyframes fadeInUp { from { opacity: 0; transform: translate3d(0, 50px, 0); } to { opacity: 1; transform: translate3d(0, 0, 0); } }
.animate-on-load { opacity: 0; animation: fadeInUp 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; }
.delay-1 { animation-delay: 0.1s; } .delay-2 { animation-delay: 0.2s; } .delay-3 { animation-delay: 0.3s; } .delay-4 { animation-delay: 0.4s; } .delay-5 { animation-delay: 0.5s; } .delay-long { animation-delay: 0.8s; }

/* --- SKELETON LOADING --- */
@keyframes shimmer { 0% { background-position: -200% 0; } 100% { background-position: 200% 0; } }
.skeleton { background: linear-gradient(90deg, #222 25%, #333 50%, #222 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; min-height: 100%; display: block; }
.lazy-loaded-img { opacity: 0; transition: opacity 0.5s ease-in-out; }
.lazy-loaded-img.is-visible { opacity: 1; }

/* --- HERO SECTION --- */
.hero { width: 100%; height: 80vh; background-size: cover; background-position: center top; background-attachment: scroll; will-change: background-position; position: relative; display: flex; align-items: flex-end; padding-bottom: 0; margin-bottom: 0; box-shadow: inset 0 0 150px rgba(0,0,0,0.5); }
.hero::before { content: ""; position: absolute; inset: 0; background: linear-gradient(to top, #141414 15%, rgba(20,20,20,0.8) 35%, transparent 70%); z-index: 1; }
.hero::after { content: ""; position: absolute; inset: 0; background: linear-gradient(to right, rgba(0,0,0,0.95) 0%, rgba(0,0,0,0.7) 40%, transparent 85%); z-index: 1; }
.hero-content { position: relative; z-index: 10; width: 100%; max-width: 1400px; margin: 0 auto; padding: 0 40px 60px 40px; display: flex; align-items: flex-end; gap: 40px; bottom: 0; }
.hero-poster { width: 240px; min-width: 240px; border-radius: 4px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.8); display: none; background: #222; min-height: 360px; opacity: 0; animation: fadeInUp 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; cursor: pointer; }
.hero-poster img { width: 100%; height: auto; display: block; border-radius: 4px; }
.meta { flex: 1; text-shadow: 2px 2px 4px rgba(0,0,0,0.8); }
.title { font-size: 3.5rem; font-weight: 800; line-height: 1.1; margin-bottom: 15px; }

/* --- BADGES (PILL DESIGN) - REVISED COLORS --- */
.badges { display: flex; align-items: center; gap: 10px; font-weight: 600; color: #e5e5e5; font-size: 0.95rem; margin-bottom: 20px; flex-wrap: wrap; }
.badges span { background: rgba(255, 255, 255, 0.2); border: 1px solid rgba(255, 255, 255, 0.3); padding: 6px 16px; border-radius: 50px; backdrop-filter: blur(5px); display: inline-flex; align-items: center; justify-content: center; white-space: nowrap; }

/* --- DYNAMIC MATCH COLORS --- */
.badges .match-score { font-weight: 800; }

/* High Score (Green - Netflix Style) */
.badges .match-score.high-score { 
    color: #46d369; 
    border-color: rgba(70, 211, 105, 0.5); 
    background: rgba(70, 211, 105, 0.15); 
}

/* Medium Score (Yellow - Average) */
.badges .match-score.med-score { 
    color: #f5c518; 
    border-color: rgba(245, 197, 24, 0.5); 
    background: rgba(245, 197, 24, 0.15); 
}

/* Low Score (White/Gray - Neutral) */
.badges .match-score.low-score,
.badges .match-score.no-score { 
    color: #e5e5e5; 
    border-color: rgba(255, 255, 255, 0.2); 
    background: rgba(255, 255, 255, 0.1); 
}


.badges .country-pill { color: #fff; border-color: rgba(255, 255, 255, 0.3); }
.badges .country-pill i { margin-right: 6px; color: #ccc; }

.desc { max-width: 700px; color: #fff; font-size: 1.2rem; line-height: 1.5; margin-bottom: 25px; text-shadow: 1px 1px 2px rgba(0,0,0,0.8); }
.hero-buttons { display: flex; gap: 15px; margin-top: 20px; }
.btn-hero { display: inline-flex; align-items: center; padding: 10px 24px; border-radius: 4px; font-size: 1.1rem; font-weight: bold; text-decoration: none; cursor: pointer; transition: transform 0.2s, opacity 0.2s; }
.btn-hero:hover { transform: scale(1.05); }
.btn-hero i { margin-right: 10px; font-size: 1.3rem; }
.btn-play { background: #fff; color: #000; }
.btn-play:hover { background: rgba(255,255,255,0.8); color: #000; }
.btn-more { background: rgba(109, 109, 110, 0.7); color: #fff; }
.btn-more:hover { background: rgba(109, 109, 110, 0.4); color: #fff; }

.genres-box { margin-top: 20px; font-size: 1rem; display: flex; flex-wrap: wrap; gap: 10px; }
.genres-box a { color: #fff; text-decoration: none; font-weight: 500; font-size: 0.85rem; background-color: rgba(255, 255, 255, 0.15); padding: 6px 16px; border-radius: 50px; border: 1px solid rgba(255, 255, 255, 0.1); display: inline-block; transition: all 0.3s ease; }
.genres-box a:hover { text-decoration: none; background-color: #e50914; border-color: #e50914; transform: translateY(-3px); box-shadow: 0 4px 12px rgba(229, 9, 20, 0.4); }

.player-wrapper { position: relative; z-index: 20; padding: 0; background: #000; border-top: none; margin-top: -1px; opacity: 0; animation: fadeInUp 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; animation-delay: 0.6s; }
.player-header { max-width: 1400px; margin: auto; display: flex; justify-content: space-between; padding: 20px 40px 20px; }
.player-tabs { list-style: none; display: flex; flex-wrap: wrap; gap: 10px; max-width: 1400px; margin: 0 auto 20px; padding: 0 40px; }
.player-tabs .tab { background: #222; border: 1px solid #333; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: 700; font-size: 0.9rem; text-transform: uppercase; color: #aaa; transition: 0.3s; }
.player-tabs .tab:hover { color: #fff; background: #333; }
.player-tabs .tab.selected { background: #e50914; color: #fff; border-color: #e50914; }
.player-frame { max-width: 1400px; height: 75vh; margin: auto; background: #000; position: relative;  }
.player-frame iframe { width: 100%; height: 100%; border: 0; }
.player-content-area { height:100%;}

.tm { position: fixed; inset: 0; background: rgba(0,0,0,0.95); display: none; align-items: center; justify-content: center; z-index: 99999; padding: 20px; }
.tm.active { display: flex; }
.tm-box { position: relative; background: #000; box-shadow: 0 0 50px rgba(0,0,0,1); width: 90%; max-width: 1200px; aspect-ratio: 16 / 9; height: auto; }
.tm-close { position: absolute; top: -40px; right: 0; background: none; border: 0; color: #fff; font-size: 30px; cursor: pointer; transition: 0.2s; opacity: 0.7; }
.tm-close:hover { opacity: 1; transform: scale(1.1); }

.section { max-width: 1400px; margin: auto; padding: 40px; opacity: 0; animation: fadeInUp 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; animation-delay: 0.8s; }
.netflix-section-title { font-size: 1.5rem; color: #e5e5e5; margin: -20px 0 35px; font-weight: 600; }

/* --- DIRECTOR & CAST CSS --- */
#single #dt-director .person { display: flex !important; flex-direction: row !important; align-items: center !important; gap: 18px !important; background: #141414 !important; padding: 16px !important; border-radius: 12px !important; border: 1px solid rgba(255,255,255,0.12) !important; max-width: 400px !important; margin-top: -12px !important; position: relative !important; width: auto !important; float: none !important; }
#single #dt-director .person .img { width: 80px !important; height: 80px !important; border-radius: 50% !important; overflow: hidden !important; border: 2px solid #e50914 !important; flex-shrink: 0 !important; margin: 0 !important; position: static !important; background: #222; }
#single #dt-director .person .img img { width: 100% !important; object-fit: cover !important; }
#single #dt-director .person .data { display: flex !important; flex-direction: column !important; justify-content: center !important; position: static !important; background: transparent !important; width: auto !important; height: auto !important; padding: 0 !important; margin: 0 !important; opacity: 1 !important; }
#single #dt-director .person .name, #single #dt-director .person .name a { font-size: 1.2rem !important; font-weight: 700 !important; color: #fff !important; text-decoration: none !important; text-align: left !important; display: block !important; }
#single #dt-director .person .caracter { font-size: 0.9rem !important; color: #ccc !important; margin-top: 2px !important; text-align: left !important; display: block !important; }

#dt-cast { display: flex !important; gap: 16px !important; overflow-x: auto !important; padding-bottom: 20px !important; scrollbar-width: thin; scrollbar-color: #e50914 #141414; white-space: nowrap !important; width: 100% !important; float: none !important; padding-top: 20px; margin-top: -35px; padding-left: 15px; }
#dt-cast::-webkit-scrollbar { height: 8px; }
#dt-cast::-webkit-scrollbar-track { background: #141414; }
#dt-cast::-webkit-scrollbar-thumb { background-color: #e50914; border-radius: 4px; }
#single #dt-cast .person { background: #141414 !important; width: 150px !important; min-width: 150px !important; border: 1px solid rgba(255,255,255,0.12) !important; border-radius: 12px !important; padding: 12px !important; text-align: center !important; cursor: pointer !important; transition: .25s !important; flex-shrink: 0 !important; position: relative !important; display: block !important; margin: 0 !important; float: none !important; height: auto !important; }
#single #dt-cast .person:hover { transform: translateY(-4px); border-color: #e50914 !important; }
#single #dt-cast .person .img { width: 100% !important; height: 185px !important; overflow: hidden !important; border-radius: 10px !important; margin-bottom: 10px !important; position: static !important; opacity: 1 !important; background: #222; }
#single #dt-cast .person .img img { width: 100% !important; height: 100% !important; object-fit: cover !important; }
#single #dt-cast .person .data { position: static !important; background: transparent !important; width: 100% !important; padding: 0 !important; margin-top: 5px !important; text-align: center !important; height: auto !important; display: block !important; white-space: normal !important; }
#single #dt-cast .person .name, #single #dt-cast .person .name a { font-weight: 800 !important; font-size: 1rem !important; color: #fff !important; text-decoration: none !important; display: block !important; line-height: 1.2 !important; }
#single #dt-cast .person .caracter { font-size: 0.85rem !important; color: #bbb !important; margin-top: 4px !important; display: block !important; }
.persons:after { content: ""; display: table; clear: both; }
.persons { float: left; width: 100%; margin-bottom: 15px; }

/* --- RESPONSIVE --- */
@media (max-width: 768px) {
    .hero { min-height: 85vh !important; height: auto !important; display: flex !important; flex-direction: column !important; justify-content: center !important; align-items: center !important; padding: 90px 0 50px !important; text-align: center !important; background-position: center top !important; box-shadow: inset 0 0 0 1200px rgba(0,0,0,0.75) !important; }
    .hero::before, .hero::after { display: none !important; }
    .hero-content { width: 100% !important; max-width: 420px !important; display: flex !important; flex-direction: column !important; justify-content: center !important; align-items: center !important; padding: 0 20px !important; margin: 0 auto !important; text-align: center !important; position: relative !important; }
    .hero-poster { display: block !important; max-width: 180px !important; margin: 0 auto 25px !important; transform: none !important; box-shadow: 0 10px 25px rgba(0,0,0,0.6) !important; }
    .hero-poster img { width: 100% !important; height: auto !important; border-radius: 6px !important; }
    .title { font-size: 2.2rem !important; margin: 10px 0 10px !important; line-height: 1.2 !important; text-align: center !important; }
    .badges { justify-content: center !important; flex-wrap: wrap !important; gap: 8px !important; margin-bottom: 15px !important; }
    .badges span { font-size: 0.85rem !important; padding: 5px 12px !important; }
    .desc { display: none !important; }
    .hero-buttons { width: 100%; display: flex !important; justify-content: center !important; align-items: center !important; flex-wrap: wrap !important; gap: 12px !important; }
    .btn-hero { min-width: 150px !important; justify-content: center !important; padding: 12px 25px !important; }
    .genres-box { text-align: center !important; margin-top: 10px !important; }
    .netflix-rate-wrap { margin: 10px 0 !important; }
    .hero-director-card { display: none !important; }
    
    .player-wrapper { padding: 30px 0 30px !important; border-top: none !important; }
    .player-header { padding: 0 20px 15px !important; }
    .player-header h3 { font-size: 1.1rem !important; font-weight: 700 !important; color: #fff !important; display: flex; align-items: center; gap: 8px; }
    .player-tabs { display: flex !important; flex-wrap: nowrap !important; overflow-x: auto !important; gap: 12px !important; padding: 0 20px 10px !important; margin: 0 !important; scrollbar-width: none; -webkit-overflow-scrolling: touch; align-items: center; }
    .player-tabs::-webkit-scrollbar { display: none; }
    .player-tabs .tab { flex: 0 0 auto !important; padding: 8px 24px !important; border-radius: 50px !important; background: #1f1f1f !important; border: 1px solid #333 !important; font-size: 0.85rem !important; font-weight: 600 !important; color: #bbb !important; text-transform: capitalize !important; letter-spacing: 0.5px; }
    .player-tabs .tab.selected { background: #e50914 !important; border-color: #e50914 !important; color: white !important; box-shadow: 0 4px 15px rgba(229, 9, 20, 0.4); }
    .player-frame { height: 240px !important; background: #000; margin-bottom: 0; }
}

/* --- 3D EFFECTS & RATING --- */
.dtsingle, .section, .hero-content { perspective: 10000px; }
#single #dt-cast .person, #single #dt-director .person, .hero-poster { transition: transform 0.3s ease, box-shadow 0.3s ease; transform-style: preserve-3d; }
#single #dt-cast .person:hover, #single #dt-director .person:hover { transform: translateY(-10px) scale(1.02); box-shadow: 0 15px 30px rgba(229, 9, 20, 0.15); z-index: 10; border-color: #e50914; }
#single #dt-cast .person .img, #single #dt-director .person .img, .hero-poster img { transition: transform 0.3s ease; }
#single #dt-cast .person:hover .img, #single #dt-director .person:hover .img { transform: translateZ(20px); }
#single #dt-cast .person .data, #single #dt-director .person .data { transition: transform 0.3s ease; }
#single #dt-cast .person:hover .data, #single #dt-director .person:hover .data { transform: translateZ(30px); }
.btn-hero { transition: transform 0.1s linear, box-shadow 0.1s linear; }
.btn-hero:active { transform: translateY(2px) scale(0.98); box-shadow: inset 0 3px 5px rgba(0,0,0,0.3); }

.netflix-rate-wrap { margin-bottom: 5px; display: inline-block; }
.netflix-rate-wrap .box-rating, .netflix-rate-wrap #star-struck-rating { background: rgba(0,0,0,0.4) !important; border: 1px solid rgba(255,255,255,0.2) !important; border-radius: 4px; box-shadow: none !important; padding: 5px 15px !important; color: #fff !important; }
.netflix-rate-wrap .rating-result .val { color: #46d369 !important; }
.netflix-rate-wrap .rating-result { border-right: 1px solid rgba(255,255,255,0.2) !important; }
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

<script>
// 1. Pass PHP Data to JS
var playerSources = <?php echo json_encode($sources_data); ?>;

document.addEventListener("DOMContentLoaded", () => {

    const tabs = document.querySelectorAll("#player-tabs .tab");
    const container = document.getElementById("playerContent");
    const fakePlayer = document.getElementById('fakeplayer');
    const fakeClick  = document.getElementById('clickfakeplayer');

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

    if(fakePlayer && fakeClick) {
        fakeClick.addEventListener('click', () => {
            fakePlayer.style.display = 'none';
            loadSource(0); 
        });
    } else {
        if(playerSources.length > 0) loadSource(0);
    }

    if(tabs.length > 0 && playerSources.length > 0){
        tabs[0].classList.add("selected");
        if(!fakePlayer) loadSource(0); 
    }

    tabs.forEach(tab => {
        tab.addEventListener("click", () => {
            if(fakePlayer) fakePlayer.style.display = 'none';
            tabs.forEach(t => t.classList.remove("selected"));
            tab.classList.add("selected");
            loadSource(tab.dataset.index);
        });
    });

    // LAZY LOAD
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

    // TRAILER MODAL
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

// 3D TILT
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

// PARALLAX
window.addEventListener("scroll", function() {
    const hero = document.querySelector(".hero");
    if (!hero) return;
    const limit = hero.offsetHeight;
    const scrolled = window.pageYOffset;
    if (scrolled <= limit) hero.style.backgroundPositionY = (scrolled * 0.5) + "px";
});
</script>