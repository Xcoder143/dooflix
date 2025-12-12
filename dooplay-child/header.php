<?php
// Theme options
$hcod = get_option('_dooplay_header_code');
$regi = doo_is_true('permits','eusr');
$acpg = doo_compose_pagelink('pageaccount');
$fvic = doo_compose_image_option('favicon');
$logo = doo_compose_image_option('headlogo');
$toic = doo_compose_image_option('touchlogo');
$logg = is_user_logged_in();
$bnme = get_option('blogname');
$styl = dooplay_get_option('style');
$ilgo = ($styl == 'default') ? 'dooplay_logo_dark' : 'dooplay_logo_white';

$logo = ($logo)
    ? "<img src='{$logo}' alt='{$bnme}' loading='lazy' />"
    : "<img src='". DOO_URI ."/assets/img/brand/{$ilgo}.svg' alt='{$bnme}' loading='lazy' />";
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">

<?php if($toic) echo "<link rel='apple-touch-icon' href='{$toic}'/>\n"; ?>
<?php if($fvic) echo "<link rel='shortcut icon' href='{$fvic}' type='image/x-icon' />\n"; ?>

<?php wp_head(); ?>
<?php echo stripslashes($hcod); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sekuya&display=swap" rel="stylesheet">
</head>

<body <?php body_class(); ?>>


<!-- ░░░ NETFLIX HEADER ░░░ -->
<header id="nfx-header" class="nfx-header">
    <div class="nfx-nav">

        <!-- LEFT SIDE -->
        <div class="nfx-left">

            <div class="nfx-logo">
                <a href="<?php echo esc_url(home_url()); ?>">
                    <?php echo $logo; ?>
                </a>
            </div>

            <nav class="nfx-menu">
                <?php wp_nav_menu([
                    'theme_location' => 'header',
                    'menu_class'     => 'nfx-menu-list',
                    'menu_id'        => 'nfx_menu',
                    'fallback_cb'    => false
                ]); ?>
            </nav>

        </div>

        <!-- RIGHT SIDE -->
        <div class="nfx-right">

            <!-- Search Button -->
            <button id="nfxSearchBtn" class="nfx-search-btn" type="button">
                <i class="fas fa-search"></i>
            </button>

            <!-- Search Panel -->
            <div id="nfxSearchPanel" class="nfx-search-panel">
                <form method="get" action="<?php echo esc_url(home_url('/')); ?>" onsubmit="return false;">
                    <input 
                        type="text" 
                        id="nfxSearchInput"
                        name="s"
                        placeholder="Search..."
                        autocomplete="off">
                </form>

                <div id="nfxSearchResults" class="nfx-search-results"></div>
            </div>

            <!-- User Area -->
            <?php if($logg): ?>

                <div class="nfx-profile" id="nfxProfileToggle">
                    <div class="nfx-avatar">
                        <?php doo_email_avatar_header(); ?>
                    </div>
                    <i class="fas fa-caret-down"></i>

                    <div class="nfx-profile-dropdown" id="nfxProfileMenu">
                        <a href="<?php echo esc_url($acpg); ?>">My Account</a>
                        <a href="#" id="dooplay_signout">Sign Out</a>
                    </div>
                </div>

            <?php elseif($regi): ?>

                <a href="#" class="nfx-login clicklogin">
                    <i class="fas fa-user-circle"></i>
                </a>

            <?php endif; ?>

            <!-- Mobile Button -->
            <button class="nfx-mobile-btn" id="nfxMobileOpen" type="button">
                <i class="fas fa-bars"></i>
            </button>

        </div>
    </div>
</header>


<!-- ░░░ MOBILE MENU ░░░ -->
<div id="nfxMobileMenu" class="nfx-mobile-menu">

    <div class="nfx-mobile-header">
        <div class="nfx-mobile-logo">
            <a href="<?php echo esc_url(home_url()); ?>">
                <?php echo $logo; ?>
            </a>
        </div>

        <button class="nfx-mobile-close" id="nfxMobileClose" type="button">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="nfx-mobile-menu-list">
        <?php wp_nav_menu([
            'theme_location' => 'header',
            'menu_class'     => 'nfx-mobile-menu-ul',
            'fallback_cb'    => false
        ]); ?>
    </div>

    <div class="nfx-mobile-auth">
        <?php if($logg): ?>
            <a href="<?php echo esc_url($acpg); ?>">My Account</a>
            <a href="#" id="dooplay_signout">Sign Out</a>
        <?php else: ?>
            <a href="#" class="clicklogin">Login</a>
            <a href="<?php echo esc_url($acpg . '?action=sign-in'); ?>">Sign Up</a>
        <?php endif; ?>
    </div>

</div>


<?php if(!$logg) DooAuth::LoginForm(); ?>
