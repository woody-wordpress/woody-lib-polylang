<?php

/**
 * Woody Lib Polylang
 * @author      Leo POIROUX
 * @copyright   2021 Raccourci Agency
 */

namespace Woody\Lib\Polylang;

use Woody\App\Container;
use Woody\Modules\Module;
use Woody\Services\ParameterManager;
use Woody\Lib\Polylang\Commands\PolylangCommands;

final class Polylang extends Module
{
    private $seasonsFlags;

    protected $polylangManager;

    protected static $key = 'woody_lib_polylang';

    public function initialize(ParameterManager $parameterManager, Container $container)
    {
        define('WOODY_LIB_POLYLANG_VERSION', '2.21.1');
        define('WOODY_LIB_POLYLANG_ROOT', __FILE__);
        define('WOODY_LIB_POLYLANG_DIR_ROOT', dirname(WOODY_LIB_POLYLANG_ROOT));
        define('WOODY_LIB_POLYLANG_URL', basename(__DIR__) . '/Resources/Assets');

        // Constantes
        define('WOODY_LANG_ENABLE', get_option('woody_lang_enable', []));
        define('WOODY_LANG_SEASONS', get_option('woody_lang_seasons', []));

        if (!empty(WOODY_LANG_SEASONS) && (in_array('hiver', WOODY_LANG_SEASONS) || in_array('ete', WOODY_LANG_SEASONS))) {
            define('WOODY_HAS_SEASONS', true);
        } else {
            define('WOODY_HAS_SEASONS', false);
        }

        // Define SeasonsFlags
        $this->seasonsFlags = $this->getSeasonsFlags();

        parent::initialize($parameterManager, $container);
        $this->polylangManager = $this->container->get('polylang.manager');
        require_once WOODY_LIB_POLYLANG_DIR_ROOT . '/Helpers/Helpers.php';
    }

    public static function dependencyServiceDefinitions()
    {
        return \Woody\Lib\Polylang\Configurations\Services::loadDefinitions();
    }

    public function subscribeHooks()
    {
        register_activation_hook(WOODY_LIB_POLYLANG_ROOT, [$this, 'activate']);
        register_deactivation_hook(WOODY_LIB_POLYLANG_ROOT, [$this, 'deactivate']);

        // Meta lang usages
        add_action('admin_init', [$this, 'metaLangUsagesRedirect']);
        add_filter('admin_body_class', [$this, 'metaLangUsagesBodyClasses']);

        add_action('admin_menu', [$this, 'generateMenu'], 15);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('wp_loaded', [$this, 'wpLoaded'], 30);

        // Surcharge de fonction Polylang par défaut (ajout de drapeau par exemple)
        add_filter('load_textdomain_mofile', [$this, 'loadTextdomainMofile'], 10, 2);
        add_filter('wpssoc_user_redirect_url', [$this, 'wpssocUserRedirectUrl'], 10, 1);
        add_filter('pll_is_cache_active', [$this, 'pllIsCacheActive']);
        add_filter('pll_copy_taxonomies', [$this, 'pllCopyTaxonomies'], 10, 2);
        add_filter('pll_predefined_flags', [$this, 'pllPredefinedFlags'], 10, 2);
        add_filter('pll_flag', [$this, 'pllFlag'], 10, 2);
        add_filter('pll_rel_hreflang_attributes', [$this, 'pllRelHreflangAttributes']);
        add_filter('woody_hreflangs', [$this, 'woodyHrefLangs']);
        add_filter('woody_robots_txt', [$this, 'robotsTxt'], 10, 2);
        add_filter('pll_language_home_url', [$this, 'pllLanguageSSL'], 10, 2);
        add_filter('pll_language_search_url', [$this, 'pllLanguageSSL'], 10, 2);
        add_filter('pll_language_flag_url', [$this, 'pllLanguageSSL'], 10, 2);
        add_filter('pll_admin_languages_filter', [$this, 'pllAdminLanguagesFilter'], 10, 2);

        // Override SiteConfig
        add_filter('woody_theme_siteconfig', [$this, 'woodyThemeSiteconfig']);

        // Override pll sync
        add_filter('pll_sync_post_fields', [$this, 'unsetSyncPostURL'], 10, 4);

        // Ne pas utiliser ces filtres directement, utiliser les Helpers
        add_filter('woody_pll_create_media_translation', [$this, 'woodyPllCreateMediaTranslation'], 10, 3);
        add_filter('woody_pll_languages_list', [$this, 'woodyPllLanguagesList'], 10, 1);
        add_filter('woody_pll_current_language', [$this, 'woodyPllCurrentLanguage'], 10);
        add_filter('woody_pll_current_season', [$this, 'woodyPllCurrentSeason'], 10);
        add_filter('woody_pll_get_post_language', [$this, 'woodyPllGetPostLanguage'], 10, 1);
        add_filter('woody_pll_get_post_season', [$this, 'woodyPllGetPostSeason'], 10, 1);
        add_filter('woody_pll_get_lang_by_slug', [$this, 'woodyPllGetLangBySlug'], 10, 1);
        add_filter('woody_pll_get_lang_by_locale', [$this, 'woodyPllGetLangByLocale'], 10, 1);
        add_filter('woody_pll_get_locale_by_slug', [$this, 'woodyPllGetLocaleBySlug'], 10, 1);
        add_filter('woody_pll_get_slug_by_locale', [$this, 'woodyPllGetSlugByLocale'], 10, 1);
        add_filter('woody_pll_the_languages', [$this, 'woodyPllTheLanguages'], 10, 1);
        add_filter('woody_pll_the_locales', [$this, 'woodyPllTheLocales'], 10);
        add_filter('woody_pll_the_seasons', [$this, 'woodyPllTheSeasons'], 10);
        add_filter('woody_pll_default_lang', [$this, 'woodyPllDefaultLang'], 10, 1);
        add_filter('woody_pll_default_lang_code', [$this, 'woodyPllDefaultlangCode'], 10, 1);
        add_filter('woody_default_lang_post_title', [$this, 'woodyDefaultLangPostTitle'], 10, 1);

        // Action polylangManager
        add_action('woody_translate_post', [$this->polylangManager, 'woodyTranslatePost'], 10, 4);
        add_action('woody_translate_fields', [$this->polylangManager, 'woodyTranslateFields'], 10, 4);
    }

    public function registerCommands()
    {
        \WP_CLI::add_command('woody:translate', new PolylangCommands($this->polylangManager));
    }

    public function metaLangUsagesRedirect()
    {
        global $pagenow;
        if ($pagenow == 'post-new.php' || $pagenow == 'edit-tags.php') {
            $current_lang = function_exists('pll_current_language') ? pll_current_language() : false;
            $addons = apply_filters('woody_meta_lang_usages_post_types', []);

            $meta_lang_usages = get_option('meta_lang_usages');
            if (!empty($meta_lang_usages) && !empty($addons)) {
                foreach ($addons as $addon_key => $addon) {
                    if ($current_lang != false && !in_array($addon_key, $meta_lang_usages[$current_lang])) {
                        foreach ($addon['posts_types'] as $post_type) {
                            global $typenow;
                            $current_page = preg_replace('#&lang=(.*)#', '', $_SERVER['REQUEST_URI']);
                            if ($typenow == $post_type) {
                                if (!in_array($typenow, $meta_lang_usages[$addon['default_lang']])) {
                                    wp_redirect('/wp/wp-admin', 301, 'Meta Lang Usage');
                                } else {
                                    wp_redirect($current_page . '&lang=' . $addon['default_lang'], 301, 'Meta Lang Usage');
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public function metaLangUsagesBodyClasses($classes)
    {
        global $pagenow;
        if ($pagenow == 'post-new.php' || $pagenow == 'edit-tags.php' || $pagenow == 'edit.php') {
            $meta_lang_usages = get_option('meta_lang_usages');
            if (!empty($meta_lang_usages)) {
                $classes .= ' langs-to-hide';
                $addons = apply_filters('woody_meta_lang_usages_post_types', []);
                global $typenow;
                foreach ($addons as $addon_key => $addon) {
                    if (in_array($typenow, $addon['posts_types'])) {
                        foreach ($meta_lang_usages as $lang_code => $lang_usage) {
                            if (!in_array($addon_key, $lang_usage)) {
                                $classes .= ' hide-' . $lang_code;
                            }
                        }
                    }
                }
            }
        }

        return $classes;
    }

    public function generateMenu()
    {
        add_submenu_page(
            'mlang',
            __('Activation & Saisonnalité'),
            __('Activation & Saisonnalité'),
            'manage_options',
            'woody-polylang-options',
            [$this, 'adminPage']
        );
    }

    public function adminPage()
    {
        if (function_exists('pll_languages_list')) {
            $languages = pll_languages_list(['fields' => '']);
            require_once(WOODY_LIB_POLYLANG_DIR_ROOT . '/Resources/Form/admin.php');
        }
    }

    public function enqueueAdminAssets()
    {
        $screen = get_current_screen();
        if (!empty($screen->id) && strpos($screen->id, 'languages_page_woody-polylang-options') !== false) {
            wp_enqueue_style('woody-lib-polylang-admin-options-stylesheet', $this->addonAssetPath('woody-lib-polylang', 'css/admin-options.css'), [], null);
            wp_enqueue_script('woody-lib-polylang-admin-options', $this->addonAssetPath('woody-lib-polylang', 'js/admin-options.js'), ['jquery'], WOODY_LIB_POLYLANG_VERSION, true);
        }

        global $pagenow;
        if ($pagenow == 'post.php' || $pagenow == 'post-new.php') {
            wp_enqueue_style('woody-lib-polylang-edit-post-stylesheet', $this->addonAssetPath('woody-lib-polylang', 'css/edit-post.css'), [], null);
            wp_enqueue_script('woody-lib-polylang-edit-post', $this->addonAssetPath('woody-lib-polylang', 'js/edit-post.js'), ['jquery'], WOODY_LIB_POLYLANG_VERSION, true);
        } elseif ($pagenow == 'post.php' || $pagenow == 'post-new.php' || $pagenow == 'edit-tags.php' || $pagenow == 'edit.php') {
            wp_enqueue_script('woody-lib-polylang-admin-post', $this->addonAssetPath('woody-lib-polylang', 'js/admin-post.js'), [], WOODY_LIB_POLYLANG_VERSION, true);
        }
    }

    public function woodyThemeSiteconfig($siteConfig)
    {
        $siteConfig['current_lang'] = $this->woodyPllCurrentLanguage();
        $siteConfig['current_season'] = $this->woodyPllCurrentSeason();
        $siteConfig['languages'] = $this->woodyPllTheLocales();
        return $siteConfig;
    }

    public function loadTextdomainMofile($mofile, $domain)
    {
        if (preg_match('#([a-z]{2}_[A-Z]{2})#i', $mofile, $matches)) {
            if(in_array($matches[0], ['en_US', 'en_AU', 'en_NZ', 'en_SG'])) {
                return str_replace($matches[0], 'en_GB', $mofile);
            } elseif(in_array($matches[0], ['fr_BE', 'fr_CA', 'fr_CH'])) {
                return str_replace($matches[0], 'fr_FR', $mofile);
            }
        }

        return $mofile;
    }

    /**
     * Fonction qui corrige un conflit de Polylang avec Enhanced Media Library lorsque l'on a plus de 2 langues
     * Sans ce fix, les traductions des images ne sont plus liées entre elles
     */
    public function wpLoaded()
    {
        global $wp_taxonomies;
        $wp_taxonomies['language']->update_count_callback = '';
        $wp_taxonomies['post_translations']->update_count_callback = '_update_generic_term_count';
    }

    public function wpssocUserRedirectUrl($user_redirect_set)
    {
        if (function_exists('pll_current_language') && strpos($user_redirect_set, 'wp-admin') !== false) {
            if (strpos($user_redirect_set, '?') !== false) {
                $user_redirect_set .= '&lang=' . pll_current_language();
            } else {
                $user_redirect_set .= '?lang=' . pll_current_language();
            }
        }

        return $user_redirect_set;
    }

    /**
     * @return boolean
     */
    public function pllIsCacheActive()
    {
        return true;
    }

    // define the pll_copy_taxonomies callback
    public function pllCopyTaxonomies($taxonomies, $sync)
    {
        $taxonomies[] = 'attachment_types';
        $taxonomies[] = 'attachment_hashtags';
        $taxonomies[] = 'attachment_categories';
        return $taxonomies;
    }

    // --------------------------------
    // FILTERS
    // --------------------------------
    public function woodyPllTheLocales()
    {
        $return = [];

        if (!function_exists('pll_languages_list')) {
            return;
        }

        $languages = pll_languages_list(['fields' => 'locale']);
        foreach ($languages as $locale) {
            $return[] = $this->locale_to_lang($locale);
        }

        return array_values(array_unique($return));
    }

    public function woodyPllLanguagesList($current_season = null)
    {
        if (!function_exists('pll_languages_list')) {
            return;
        }

        $languages = pll_languages_list(['fields' => '']);

        // On garde uniquement les langues de la même saison
        if (!empty($current_season) || $current_season == 'auto') {
            $woody_lang_seasons = (defined('WOODY_LANG_SEASONS') && is_array(WOODY_LANG_SEASONS)) ? WOODY_LANG_SEASONS : [];
            if (!empty($woody_lang_seasons)) {
                if ($current_season == 'auto') {
                    $current_lang = pll_current_language();
                    $current_season = $woody_lang_seasons[$current_lang];
                }

                $same_season_langs = [];
                foreach ($woody_lang_seasons as $slug => $season) {
                    if ($season == $current_season) {
                        $same_season_langs[] = $slug;
                    }
                }

                foreach ($languages as $key => $language) {
                    if (!in_array($language->slug, $same_season_langs)) {
                        unset($languages[$key]);
                    }
                }
            }
        }

        return $languages;
    }

    public function woodyPllTheLanguages($current_season = null)
    {
        if (!function_exists('pll_the_languages')) {
            return;
        }

        $languages = pll_the_languages(array(
            'display_names_as'       => 'name',
            'hide_if_no_translation' => 0,
            'raw'                    => true
        ));

        // On garde uniquement les langues de la même saison
        if (!empty($current_season) || $current_season == 'auto') {
            $woody_lang_seasons = (defined('WOODY_LANG_SEASONS') && is_array(WOODY_LANG_SEASONS)) ? WOODY_LANG_SEASONS : [];
            if (!empty($woody_lang_seasons)) {
                if ($current_season == 'auto') {
                    $current_lang = pll_current_language();
                    $current_season = (empty($woody_lang_seasons[$current_lang])) ? 'default' : $woody_lang_seasons[$current_lang];
                }

                $same_season_langs = [];
                foreach ($woody_lang_seasons as $slug => $season) {
                    if ($season == $current_season) {
                        $same_season_langs[] = $slug;
                    }
                }

                foreach ($languages as $key => $language) {
                    if (!in_array($language['slug'], $same_season_langs)) {
                        unset($languages[$key]);
                    }
                }
            }
        }

        return $languages;
    }

    public function woodyPllTheSeasons()
    {
        if (!function_exists('pll_the_languages')) {
            return;
        }

        $woody_lang_seasons = (defined('WOODY_LANG_SEASONS') && is_array(WOODY_LANG_SEASONS)) ? WOODY_LANG_SEASONS : [];
        if (!empty($woody_lang_seasons)) {
            $languages = pll_the_languages(array(
                'display_names_as'       => 'name',
                'hide_if_no_translation' => 0,
                'raw'                    => true
            ));

            $current_lang = pll_current_language();
            $season_current = empty($woody_lang_seasons[$current_lang]) ? "default" : $woody_lang_seasons[$current_lang] ;

            // On créé le switcher que si on a de la saisonnalité
            if ($season_current == 'default') {
                return;
            }

            foreach ($languages as $language) {
                if ($language['slug'] == $current_lang) {
                    $current_language = $language;
                    break;
                }
            }

            $langs_on_other_seasons = [];
            foreach ($woody_lang_seasons as $slug => $season) {
                if ($season != $season_current && $season != 'default') {
                    $langs_on_other_seasons[] = $slug;
                }
            }

            foreach ($languages as $key => $language) {
                if ($language['slug'] == $current_lang || (in_array($language['slug'], $langs_on_other_seasons) && $language['locale'] == $current_language['locale'])) {
                    $languages[$key]['season'] = $woody_lang_seasons[$language['slug']];
                    continue;
                } else {
                    unset($languages[$key]);
                }
            }

            return $languages;
        }
    }

    public function woodyPllDefaultLang($season = null)
    {
        if (function_exists('pll_languages_list') && !empty($season)) {
            $woody_lang_seasons = (defined('WOODY_LANG_SEASONS') && is_array(WOODY_LANG_SEASONS)) ? WOODY_LANG_SEASONS : [];
            $default_season = $woody_lang_seasons[PLL_DEFAULT_LANG];
            if (empty($default_season) || $default_season == $season) {
                return PLL_DEFAULT_LANG;
            } else {
                $languages = pll_languages_list(['fields' => '']);
                foreach ($languages as $language) {
                    if ($language->locale == PLL_DEFAULT_LOCALE && $woody_lang_seasons[$language->slug] == $season) {
                        return $language->slug;
                    }
                }
            }
        }

        return PLL_DEFAULT_LANG;
    }

    public function woodyPllDefaultlangCode()
    {
        $locale = pll_default_language('locale');
        return $this->locale_to_lang($locale);
    }

    public function woodyPllGetLangBySlug($slug)
    {
        if (function_exists('pll_languages_list')) {
            if (empty($slug)) {
                output_error('Impossible de trouver la langue de ce slug vide');
                return;
            }

            $languages = pll_languages_list(['fields' => '']);
            foreach ($languages as $language) {
                if ($language->slug == $slug) {
                    return $this->locale_to_lang($language->locale);
                }
            }

            // Fallback if $slug is already a 2-character code_lang
            foreach ($languages as $language) {
                $code_lang = $this->locale_to_lang($language->locale);
                if ($code_lang == $slug) {
                    return $code_lang;
                }
            }

            output_error(sprintf('Impossible de trouver la langue de ce slug "%s"', $slug));
        }
    }

    public function woodyPllGetLangByLocale($locale)
    {
        if (function_exists('pll_languages_list')) {
            if (empty($locale)) {
                output_error('Impossible de trouver la langue de cette locale vide');
                return;
            }

            $locale = str_replace('-', '_', $locale);

            if(strpos($locale, '_') !== false) {
                $split_locale = explode('_', $locale);
                // On prend les 2 premiers caractères de la locale
                if(in_array($locale, ['en_GB', 'ja_JP', 'zh_CN', 'ko_KR', 'ca_ES', 'pt_BR'])) {
                    return current($split_locale);
                }

                // On prend les 2 derniers caractères de la locale
                return strtolower(end($split_locale));
            } else {
                return PLL_DEFAULT_LANG;
            }

            output_error(sprintf('Impossible de trouver la langue de cette locale "%s"', $locale));
        }
    }

    public function woodyPllGetLocaleBySlug($slug)
    {
        if (function_exists('pll_languages_list')) {
            if (empty($slug)) {
                output_error('Impossible de trouver la langue de ce slug vide');
                return;
            }

            $languages = pll_languages_list(['fields' => '']);
            foreach ($languages as $language) {
                if ($language->slug == $slug) {
                    return $language->locale;
                }
            }

            // Fallback if $slug is already a xx_XX code_locale
            foreach ($languages as $language) {
                $code_locale = $language->locale;
                if ($code_locale == $slug) {
                    return $code_locale;
                }
            }

            output_error(sprintf('Impossible de trouver la langue de ce slug "%s"', $slug));
        }
    }

    public function woodyPllGetSlugByLocale($locale)
    {
        if (function_exists('pll_languages_list')) {
            if (empty($locale)) {
                output_error('Impossible de trouver la langue de ce slug vide');
                return;
            }

            // Dans le cas d'un hreflang on reçoit la locale au format fr-FR
            $locale = str_replace('-', '_', $locale);

            $languages = pll_languages_list(['fields' => '']);
            foreach ($languages as $language) {
                if ($language->locale == $locale) {
                    return $language->slug;
                }
            }

            output_error(sprintf('Impossible de trouver la langue de cette locale "%s"', $locale));
        }
    }

    public function woodyPllGetPostLanguage($post_id = null)
    {
        if (function_exists('pll_get_post_language') && !empty($post_id)) {
            $locale = pll_get_post_language($post_id, 'locale');
            return $this->locale_to_lang($locale);
        }
    }

    public function woodyPllGetPostSeason($post_id = null)
    {
        $woody_lang_seasons = (defined('WOODY_LANG_SEASONS') && is_array(WOODY_LANG_SEASONS)) ? WOODY_LANG_SEASONS : [];
        if (function_exists('pll_get_post_language') && !empty($post_id) && !empty($woody_lang_seasons)) {
            $slug = pll_get_post_language($post_id);
            if (!empty($woody_lang_seasons[$slug]) && $woody_lang_seasons[$slug] != 'default') {
                return $woody_lang_seasons[$slug];
            }
        }
    }

    public function woodyPllCurrentLanguage()
    {
        if (function_exists('pll_current_language')) {
            $locale = pll_current_language('locale');
            return $this->locale_to_lang($locale);
        }
    }

    public function woodyPllCurrentSeason()
    {
        if (function_exists('pll_current_language')) {
            $slug = pll_current_language();
            $woody_lang_seasons = (defined('WOODY_LANG_SEASONS') && is_array(WOODY_LANG_SEASONS)) ? WOODY_LANG_SEASONS : [];
            if (!empty($woody_lang_seasons) && !empty($woody_lang_seasons[$slug]) && $woody_lang_seasons[$slug] != 'default') {
                return $woody_lang_seasons[$slug];
            }
        }
    }

    private function locale_to_lang($locale)
    {
        return (strpos($locale, '_') !== false) ? current(explode('_', $locale)) : PLL_DEFAULT_LANG;
    }

    // --------------------------------
    // Copy of native Polylang function
    // PLL()->posts->create_media_translation($attachment_id, $lang);
    // --------------------------------
    public function woodyPllCreateMediaTranslation($attachment_id, $source_lang, $target_lang)
    {
        if (empty($attachment_id)) {
            return;
        }

        $post = get_post($attachment_id);
        if (empty($post)) {
            return;
        }

        // Create a new attachment ( translate attachment parent if exists )
        add_filter('pll_enable_duplicate_media', '__return_false', 99); // Avoid a conflict with automatic duplicate at upload
        $post->ID = null; // Will force the creation
        $post->post_parent = ($post->post_parent && $tr_parent = pll_get_post($post->post_parent, $target_lang)) ? $tr_parent : 0;
        $post->tax_input = array('language' => array($target_lang)); // Assigns the language
        $tr_id = wp_insert_attachment($post);
        remove_filter('pll_enable_duplicate_media', '__return_false', 99); // Restore automatic duplicate at upload

        // Copy metadata, attached file and alternative text
        foreach (array('_wp_attachment_metadata', '_wp_attached_file', '_wp_attachment_image_alt') as $key) {
            if ($meta = get_post_meta($attachment_id, $key, true)) {
                //output_log([' - add_post_meta', $tr_id, $key, $meta]);
                add_post_meta($tr_id, $key, $meta);
            }
        }

        // On assigne la langue
        pll_set_post_language($tr_id, $target_lang);

        $translations = pll_get_post_translations($attachment_id);
        if (empty($translations) && $src_lang = pll_get_post($attachment_id)) {
            $translations[$src_lang->slug] = $attachment_id;
        }

        $translations[$target_lang] = $tr_id;
        pll_save_post_translations($translations);

        /**
         * Fires after a media translation is created
         *
         * @since 1.6.4
         *
         * @param int    $attachment_id post id of the source media
         * @param int    $tr_id   post id of the new media translation
         * @param string $slug    language code of the new translation
         */
        do_action('pll_translate_media', $attachment_id, $tr_id, $target_lang);

        // Sync Trads
        do_action('woody_sync_attachment', $attachment_id);

        /**
         * Ajout de Raccourci Agency pour traduire automatiquement avec DeepL
         */
        do_action('woody_auto_translate_media', $tr_id, $source_lang);
        output_success(sprintf('Média traduit en %s (%s > %s)', strtoupper($target_lang), $attachment_id, $tr_id));

        return $tr_id;
    }

    public function pllLanguageSSL($url)
    {
        return str_replace('http://', '//', $url);
    }

    // --------------------------------
    // Polylang Flags
    // --------------------------------

    // Permet de surcharger les drapeaux pour les saisons le sélecteur en haut à gauche
    public function pllAdminLanguagesFilter($items)
    {
        foreach ($items as $key => $item) {
            if(get_class($item) == 'PLL_Language' && array_key_exists($items[$key]->flag_code, $this->seasonsFlags)) {
                $items[$key]->flag = '<img src="' . $this->getSeasonFlagUrl($items[$key]->flag_code) . '" title="' . $items[$key]->name . '" alt="' . $items[$key]->name . '" />';
            }
        }

        return $items;
    }

    // Permet de surcharger les drapeaux pour les saisons dans "Langues"
    public function pllFlag($flag, $code)
    {
        if (array_key_exists($code, $this->seasonsFlags)) {
            $flag['url'] = $this->getSeasonFlagUrl($code);
            $flag['src'] = $this->getSeasonFlagUrl($code);
        }

        return $flag;
    }

    public function pllRelHreflangAttributes($hreflangs)
    {
        $woody_lang_enable = (defined('WOODY_LANG_ENABLE') && is_array(WOODY_LANG_ENABLE)) ? WOODY_LANG_ENABLE : [];

        // Dans le cas des saisons, on remplace le tableau hreflangs envoyé par polylang
        // pour ne renvoyer que les hreflangs de la même saison
        $currentSeasonLangs = $this->woodyPllLanguagesList('auto');
        if (!empty($currentSeasonLangs)) {
            $hreflangs = [];
            $post_id = get_the_ID();
            foreach ($currentSeasonLangs as $langObject) {
                // On exclut les langues non actives. PLL n'exclut pas la langue courante, donc on fait de même
                //(Google recommends to include self link https://support.google.com/webmasters/answer/189077?hl=en)
                if (!in_array($langObject->slug, $woody_lang_enable)) {
                    continue;
                }

                $tt_post_id = pll_get_post($post_id, $langObject->slug);
                if(!empty($tt_post_id)) {
                    $translation = woody_get_permalink($tt_post_id);
                    if (!empty($translation)) {
                        $hreflangs[$this->locale_to_lang($langObject->locale)] = $translation;
                    }
                }
            }
        } else {
            foreach ($hreflangs as $lang => $hreflang) {
                // Si on reçoit un code hreflang au format en-GB car nous avons plusieurs variantes d'anglais
                $slug = (strpos($lang, '-') !== false) ? woody_pll_get_slug_by_locale($lang) : $lang;

                if (!in_array($slug, $woody_lang_enable)) {
                    unset($hreflangs[$lang]);
                }
            }
        }

        // Si une page n'est pas publiée, alors on supprime la balise hreflang correspondante
        if(!empty($hreflangs)) {
            foreach ($hreflangs as $lang => $hreflang) {
                $post_id = url_to_postid($hreflang);
                $post_status = get_post_status($post_id);
                if(!empty($post_status) && $post_status != 'publish') {
                    unset($hreflangs[$lang]);
                }
            }
        }

        return apply_filters('woody_hreflangs', $hreflangs);
    }

    public function woodyHrefLangs($hreflangs)
    {
        $woody_hreflangs = get_field('woody_hreflangs');
        if(is_array($woody_hreflangs) && !empty($woody_hreflangs)) {
            foreach ($woody_hreflangs as $woody_hreflang) {
                $hreflangs[$woody_hreflang['langcode']] = $woody_hreflang['url'];
            }
        }

        return $hreflangs;
    }

    public function robotsTxt($output, $public)
    {
        if ('0' != $public) {
            $polylang = get_option('polylang');
            $woody_lang_enable = (defined('WOODY_LANG_ENABLE') && is_array(WOODY_LANG_ENABLE)) ? WOODY_LANG_ENABLE : [];

            if ($polylang['force_lang'] == 3 && !empty($polylang['domains'])) {
                // Si le site est en multi domaines, on cree un robots par domaine
                $current_lang = pll_current_language();
                if (!in_array($current_lang, $woody_lang_enable)) {
                    $output = [
                        '# Woody Robots Force ' . WP_SITE_KEY . ' (' . WP_ENV . ')',
                        '# Generated by Raccourci Agency',
                        'User-agent: *',
                        'Disallow: /',
                    ];
                }
            } else {
                // Si le site est alias, on rajoute les langues inactives au robots.txt
                $languages = pll_languages_list();
                foreach ($languages as $slug) {
                    if (!in_array($slug, $woody_lang_enable)) {
                        $output[] = 'Disallow: /' . $slug . '/';
                    }
                }
            }
        }

        return $output;
    }

    public function pllPredefinedFlags($flags)
    {
        foreach ($this->seasonsFlags as $code => $flag) {
            $flags[$code] = $flag['name'];
        }

        return $flags;
    }

    private function getSeasonsFlags()
    {
        return [
            'ad'   => ['name' => __('Français (Hiver)', 'polylang'), 'img' => 'fr_hiver'],
            'ae'   => ['name' => __('Français (Eté)', 'polylang'), 'img' => 'fr_ete'],
            'af'   => ['name' => __('Anglais (Hiver)', 'polylang'), 'img' => 'en_hiver'],
            'ag'   => ['name' => __('Anglais (Eté)', 'polylang'), 'img' => 'en_ete'],
            'ai'   => ['name' => __('Allemand (Hiver)', 'polylang'), 'img' => 'de_hiver'],
            'al'   => ['name' => __('Allemand (Eté)', 'polylang'), 'img' => 'de_ete'],
            'am'   => ['name' => __('Italien (Hiver)', 'polylang'), 'img' => 'it_hiver'],
            'an'   => ['name' => __('Italien (Eté)', 'polylang'), 'img' => 'it_ete'],
            'ao'   => ['name' => __('Néerlandais (Hiver)', 'polylang'), 'img' => 'nl_hiver'],
            'ar'   => ['name' => __('Néerlandais (Eté)', 'polylang'), 'img' => 'nl_ete'],
            'arab' => ['name' => __('Espagnol (Hiver)', 'polylang'), 'img' => 'es_hiver'],
            'as'   => ['name' => __('Espagnol (Eté)', 'polylang'), 'img' => 'es_ete'],
            'at'   => ['name' => __('Portugais (Hiver)', 'polylang'), 'img' => 'pt_hiver'],
            'au'   => ['name' => __('Portugais (Eté)', 'polylang'), 'img' => 'pt_ete'],
            'aw'   => ['name' => __('Breton', 'polylang'), 'img' => 'br']
        ];
    }

    private function getSeasonFlagUrl($code)
    {
        return $this->addonAssetPath('woody-lib-polylang', 'img/flags/' . $this->seasonsFlags[$code]['img'] . '.png');
    }

    // Filters the post fields to synchronize when synchronizing posts
    public function unsetSyncPostURL($fields, $post_id, $lang, $save_group)
    {
        // Il ne faut pas synchroniser ces champs car on les rajoute ensuite avec une requête SQL
        // Pour éviter de générer une url qui viendrait en conflit avec le post source
        unset($fields['post_name']);
        unset($fields['post_title']);

        return $fields;
    }

    public function woodyDefaultLangPostTitle($post_id)
    {
        return get_the_title(pll_get_post($post_id, $this->woodyPllDefaultLang()));
    }
}
