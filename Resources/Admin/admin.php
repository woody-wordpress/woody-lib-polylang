<?php
/**
 * Woody Polylang
 * @author      Leo POIROUX
 * @copyright   2019 Raccourci Agency
 */

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly
?>

<div class="woody-polylang-container">
    <header class="woody-polylang-header">
        <h1>
            Woody Polylang
            <span>Made with ♥ by Raccourci Agency</span>
        </h1>
    </header>

    <h2 class="nav-tab-wrapper">
        <a href="?page=woody-polylang-options&tab=enable_lang" class="nav-tab <?php echo $active_tab == 'enable_lang' ? 'nav-tab-active' : ''; ?>">Activer l'indexation</a>
        <a href="?page=woody-polylang-options&tab=seasons_lang" class="nav-tab <?php echo $active_tab == 'seasons_lang' ? 'nav-tab-active' : ''; ?>">Saisonnalité</a>
        <a href="?page=woody-polylang-options&tab=hawwwai_lang" class="nav-tab <?php echo $active_tab == 'hawwwai_lang' ? 'nav-tab-active' : ''; ?>">Hawwwai</a>
        <a href="?page=woody-polylang-options&tab=usage_lang" class="nav-tab <?php echo $active_tab == 'usage_lang' ? 'nav-tab-active' : ''; ?>">Usages</a>
        <?php
            foreach ($custom_tabs as $tabSlug => $tabTitle) {
        ?>
                <a href="?page=woody-polylang-options&tab=<?php echo $tabSlug; ?>" class="nav-tab <?php echo $active_tab == $tabSlug ? 'nav-tab-active' : ''; ?>"> <?php echo $tabTitle; ?></a>
        <?php
            }
        ?>
    </h2>

    <section class="woody-polylang-wrapper">
        <?php
        switch ($active_tab) {
            case 'enable_lang':
                ?>
            <div class="help">
                Tant que la langue n'est pas activée sur cette page, le site est masqué pour Google :
                <ul>
                    <li>Le robots.txt du site bloque l'indexation du site</li>
                    <li>Les métas "hreflang" sont désactivées sur toutes les pages</li>
                    <li>Les métas "hreflang" sont désactivées sur toutes les fiches SIT</li>
                    <li>Une méta "noindex, nofollow" est positionnée sur toutes les pages</li>
                    <li>Le sitemap.xml est désactivé</li>
                </ul>
            </div>
            <?php
            break;

        case 'seasons_lang':
            # code...
            break;

        case 'hawwwai_lang':
            ?>
            <div class="help">
                ATTENTION ! Si vous cochez une langue les fiches ne seront plus importées dans cette langue.
                La langue par défaut ne peut être désactivée.
            </div>
            <?php
            break;
    }
    ?>

        <?php echo $form; ?>
    </section>
</div>
