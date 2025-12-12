<?php
/*
* -------------------------------------------------------------------------------------
* CUSTOM SEASON TABS (Fixed: Detached from Theme JS)
* -------------------------------------------------------------------------------------
*/

$tmdb_id = get_post_meta($post->ID, 'ids', true);
$seasons = DDbmoviesHelpers::GetAllSeasons($tmdb_id);
$season_title_opt = dooplay_get_option('dbmvstitleseasons', __d('Season {season}'));
?>

<div id="nfx-seasons" class="nfx-season-selector">
    
    <div class="nfx-season-tabs">
        <ul class="nfx-season-list">
            <?php 
            if($seasons && is_array($seasons)) {
                $count = 0;
                foreach($seasons as $season_id) {
                    $count++;
                    $season_num = get_post_meta($season_id, 'temporada', true);
                    
                    // First tab is active by default
                    $active_class = ($count == 1) ? 'active' : '';
                    
                    // Title
                    $title_data = array('name' => '', 'season' => $season_num);
                    $label = dbmovies_title_tags($season_title_opt, $title_data);
                    if($season_num == '0') $label = __d('Specials');

                    echo '<li class="nfx-tab-item">';
                    // ONCLICK event forces the switch
                    echo '<button class="nfx-season-btn '.$active_class.'" onclick="openNetflixSeason(event, \'season-'.$season_num.'\')">';
                    echo $label;
                    echo '</button>';
                    echo '</li>';
                }
            } 
            ?>
        </ul>
    </div>

    <div class="nfx-episode-container">
        <?php 
        // Load the EPISODE LISTS
        include(locate_template('inc/parts/single/listas/seasons_episodes.php')); 
        ?>
    </div>

</div>

<script>
function openNetflixSeason(evt, seasonId) {
    // Stop link defaults
    evt.preventDefault();
    
    var i, tabcontent, tablinks;
    
    // 1. Hide all season lists
    tabcontent = document.getElementsByClassName("nfx-season-grid");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
    }
    
    // 2. Remove 'active' class from all buttons
    tablinks = document.getElementsByClassName("nfx-season-btn");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }
    
    // 3. Show current season and add 'active' class to clicked button
    var selectedSeason = document.getElementById(seasonId);
    if(selectedSeason) {
        selectedSeason.style.display = "block";
    }
    evt.currentTarget.className += " active";
}
</script>