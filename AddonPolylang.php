<?php

/**
 * Woody Addon Polylang
 * @author      Leo POIROUX
 * @copyright   2021 Raccourci Agency
 */

namespace Woody\Addon\AddonPolylang;

use Woody\App\Container;
use Woody\Modules\Module;
use Woody\Services\ParameterManager;

final class AddonPolylang extends Module
{
    protected static $key = 'woody_addon_polylang';
    private $seasonsFlags;

    public function initialize(ParameterManager $parameters, Container $container)
    {
        define('WOODY_ADDON_POLYLANG_VERSION', '1.1.1');
        define('WOODY_ADDON_POLYLANG_ROOT', __FILE__);
        define('WOODY_ADDON_POLYLANG_DIR_ROOT', dirname(WOODY_ADDON_POLYLANG_ROOT));
        define('WOODY_ADDON_POLYLANG_URL', basename(__DIR__) . '/Resources/Assets');

        // Define SeasonsFlags
        $this->seasonsFlags = $this->getSeasonsFlags();

        parent::initialize($parameters, $container);
    }

    public static function dependencyServiceDefinitions()
    {
        return \Woody\Addon\AddonPolylang\Configurations\Services::loadDefinitions();
    }

    public function subscribeHooks()
    {
        register_activation_hook(WOODY_ADDON_POLYLANG_ROOT, [$this, 'activate']);
        register_deactivation_hook(WOODY_ADDON_POLYLANG_ROOT, [$this, 'deactivate']);

        add_action('admin_init', [$this, 'adminInit']);
        add_action('admin_menu', [$this, 'generateMenu'], 15);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('wp_loaded', [$this, 'wpLoaded'], 30);

        // Fonction de duplication des médias
        add_filter('woody_pll_create_media_translation', [$this, 'woodyPllCreateMediaTranslation'], 10, 2);

        // Retourne toutes les langues
        // Si current_lang donné en paramètre, ne retourne que les langues de la même saison
        add_filter('woody_pll_languages_list', [$this, 'woodyPllLanguagesList'], 10, 1);

        // Retourne le codes langue courant mais en se basant sur les locales
        add_filter('woody_pll_current_language', [$this, 'woodyPllCurrentLanguage'], 10);

        // Retourne la saison de la langue courante
        add_filter('woody_pll_current_season', [$this, 'woodyPllCurrentSeason'], 10);

        // Retourne la langue du post mais en se basant sur les locales
        add_filter('woody_pll_get_post_language', [$this, 'woodyPllGetPostLanguage'], 10);

        // Retourne le code lang (issue de la locale) à partir du slug
        add_filter('woody_pll_get_lang_by_slug', [$this, 'woodyPllGetLangBySlug'], 10);

        // Retourne le switcher de langue
        // Si current_lang donné en paramètre, ne retourne que les langues de la même saison
        add_filter('woody_pll_the_languages', [$this, 'woodyPllTheLanguages'], 10, 1);

        // Retourne les codes langues sur 2 caractères mais en se basant sur les locales
        add_filter('woody_pll_the_locales', [$this, 'woodyPllTheLocales'], 10, 1);

        // Liste les saisons d'une même langue
        add_filter('woody_pll_the_seasons', [$this, 'woodyPllTheSeasons'], 10);

        // Retourne la langue par défaut mais en se basant sur la locale
        add_filter('woody_pll_default_lang', [$this, 'woodyPllDefaultLang'], 10, 1);

        // Retourne le slug du langage par défaut
        add_filter('woody_pll_default_lang_code', [$this, 'woodyPllDefaultlangCode'], 10, 1);

        // Retourne le titre d'un post dans la langue par défaut
        add_filter('woody_default_lang_post_title', [$this, 'woodyDefaultLangPostTitle'], 10, 1);

        // Surcharge de fonction Polylang par défaut (ajout de drapeau par exemple)
        add_filter('wpssoc_user_redirect_url', [$this, 'wpssocUserRedirectUrl'], 10, 1);
        add_filter('pll_is_cache_active', [$this, 'isCacheActive']);
        add_filter('pll_copy_taxonomies', [$this, 'copyAttachmentTypes'], 10, 2);
        add_filter('pll_languages_list', [$this, 'pllLanguagesList'], 10, 2);
        add_filter('pll_predefined_flags', [$this, 'pllPredefinedFlags'], 10, 2);
        add_filter('pll_flag', [$this, 'pllFlag'], 10, 2);
        add_filter('pll_rel_hreflang_attributes', [$this, 'pllRelHreflangAttributes']);
        add_filter('woody_robots_txt', [$this, 'robotsTxt'], 10, 2);

        // Override SiteConfig
        add_filter('woody_theme_siteconfig', [$this, 'woodyThemeSiteconfig']);

        // Override pll sync
        add_filter('pll_sync_post_fields', [$this, 'unsetSyncPostURL'], 10, 4);
        add_action('pll_post_synchronized', [$this, 'modifyPostName'], 10, 3);

        // Translate posts
        \WP_CLI::add_command('woody:translate_posts', [$this, 'translatePostsWpcli']);
    }

    public function adminInit()
    {
        $langs_usages = get_option('meta_lang_usages');
        $current_lang = function_exists('pll_current_language') ? pll_current_language() : PLL_DEFAULT_LANGUAGE;
        $addons = apply_filters('woody_meta_lang_usages_post_types', []);

        if (!empty($langs_usages) && !empty($addons)) {
            foreach ($addons as $addon_key => $addon) {
                if (!in_array($addon_key, $langs_usages[$current_lang])) {
                    foreach ($addon['posts_types'] as $post_type) {
                        global $wp;
                        $current_page = preg_replace('/&lang=(.*)/', '', add_query_arg($wp->query_vars));
                        if (strpos($current_page, 'post_type=' . $post_type) !== false) {
                            if (strpos($current_page, 'post-new.php') !== false || strpos($current_page, 'edit-tags.php') !== false) {
                                wp_redirect($current_page . '&lang=' . $addon['default_lang'], 301, 'Meta Lang Usage');
                            }
                        }
                    }
                }
            }
        }
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
            require_once(WOODY_ADDON_POLYLANG_DIR_ROOT . '/Resources/Form/admin.php');
        }
    }

    public function enqueueAdminAssets()
    {
        $screen = get_current_screen();
        if (!empty($screen->id) && strpos($screen->id, 'languages_page_woody-polylang-options') !== false) {
            wp_enqueue_style('addon-admin-polylang-stylesheet', $this->addonAssetPath('woody-addon-polylang', 'css/main.css'), [], null);
        }
    }

    public function woodyThemeSiteconfig($siteConfig)
    {
        $siteConfig['current_lang'] = $this->woodyPllCurrentLanguage();
        $siteConfig['current_season'] = $this->woodyPllCurrentSeason();
        $siteConfig['languages'] = $this->woodyPllTheLocales();
        return $siteConfig;
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
        if (function_exists('pll_current_language')) {
            if (strpos($user_redirect_set, 'wp-admin') !== false) {
                if (strpos($user_redirect_set, '?') !== false) {
                    $user_redirect_set .= '&lang=' . pll_current_language();
                } else {
                    $user_redirect_set .= '?lang=' . pll_current_language();
                }
            }
        }

        return $user_redirect_set;
    }

    /**
     * @return boolean
     */
    public function isCacheActive()
    {
        return true;
    }

    // define the pll_copy_taxonomies callback
    public function copyAttachmentTypes($taxonomies, $sync)
    {
        $custom_taxs = [
            'attachment_types' => 'attachment_types',
            'attachment_hashtags' => 'attachment_hashtags',
            'attachment_categories' => 'attachment_categories',
        ];

        $taxonomies = array_merge($custom_taxs, $taxonomies);
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
        $return = [];

        if (!function_exists('pll_languages_list')) {
            return;
        }

        $languages = pll_languages_list(['fields' => '']);

        // On garde uniquement les langues de la même saison
        if (!empty($current_season) || $current_season == 'auto') {
            $woody_lang_seasons = get_option('woody_lang_seasons', []);

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
            $woody_lang_seasons = get_option('woody_lang_seasons', []);

            if (!empty($woody_lang_seasons)) {
                if ($current_season == 'auto') {
                    $current_lang = pll_current_language();
                    $current_season = (!empty($woody_lang_seasons[$current_lang])) ? $woody_lang_seasons[$current_lang] : 'default';
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

        $woody_lang_seasons = get_option('woody_lang_seasons', []);
        if (!empty($woody_lang_seasons)) {
            $languages = pll_the_languages(array(
                'display_names_as'       => 'name',
                'hide_if_no_translation' => 0,
                'raw'                    => true
            ));

            $current_lang = pll_current_language();
            $season_current = !empty($woody_lang_seasons[$current_lang]) ? $woody_lang_seasons[$current_lang] : "default" ;

            // On créé le switcher que si on a de la saisonnalité
            if ($season_current == 'default') {
                return;
            }

            foreach ($languages as $key => $language) {
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
        if (function_exists('pll_languages_list')) {
            if (!empty($season)) {
                $woody_lang_seasons = get_option('woody_lang_seasons', []);
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
        }
        return PLL_DEFAULT_LANG;
    }

    public function woodyPllDefaultlangCode()
    {
        $locale = pll_default_language('locale');
        return $this->locale_to_lang($locale);
    }

    public function woodyPllGetLangBySlug($slug = null)
    {
        if (function_exists('pll_languages_list') && !empty($slug)) {
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
        }
    }

    public function woodyPllGetPostLanguage($post_id = null)
    {
        if (function_exists('pll_get_post_language') && !empty($post_id)) {
            $locale = pll_get_post_language($post_id, 'locale');
            return $this->locale_to_lang($locale);
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
            $woody_lang_seasons = get_option('woody_lang_seasons', []);
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
    public function woodyPllCreateMediaTranslation($post_id, $lang)
    {
        if (empty($post_id)) {
            return $post_id;
        }

        $post = get_post($post_id);

        if (empty($post)) {
            return $post;
        }

        // Create a new attachment ( translate attachment parent if exists )
        add_filter('pll_enable_duplicate_media', '__return_false', 99); // Avoid a conflict with automatic duplicate at upload
        $post->ID = null; // Will force the creation
        $post->post_parent = ($post->post_parent && $tr_parent = pll_get_post($post->post_parent, $lang)) ? $tr_parent : 0;
        $post->tax_input = array('language' => array($lang)); // Assigns the language
        $tr_id = wp_insert_attachment($post);
        remove_filter('pll_enable_duplicate_media', '__return_false', 99); // Restore automatic duplicate at upload

        // Copy metadata, attached file and alternative text
        foreach (array('_wp_attachment_metadata', '_wp_attached_file', '_wp_attachment_image_alt') as $key) {
            if ($meta = get_post_meta($post_id, $key, true)) {
                add_post_meta($tr_id, $key, $meta);
            }
        }

        pll_set_post_language($tr_id, $lang);

        $translations = pll_get_post_translations($post_id);
        if (!$translations && $src_lang = pll_get_post($post_id)) {
            $translations[$src_lang->slug] = $post_id;
        }

        $translations[$lang] = $tr_id;
        pll_save_post_translations($translations);

        /**
         * Fires after a media translation is created
         *
         * @since 1.6.4
         *
         * @param int    $post_id post id of the source media
         * @param int    $tr_id   post id of the new media translation
         * @param string $slug    language code of the new translation
         */
        do_action('pll_translate_media', $post_id, $tr_id, $lang);
        return $tr_id;
    }

    // --------------------------------
    // Polylang Flags
    // --------------------------------
    public function pllLanguagesList($languages, $obj)
    {
        foreach ($languages as $key => $language) {
            if (FORCE_SSL_ADMIN) {
                $languages[$key]->home_url = str_replace('http://', 'https://', $language->home_url);
                $languages[$key]->search_url = str_replace('http://', 'https://', $language->search_url);
                $languages[$key]->flag_url = str_replace('http://', 'https://', $language->search_url);
            }

            if (array_key_exists($languages[$key]->flag_code, $this->seasonsFlags)) {
                $languages[$key]->flag = '<img src="' . $this->getSeasonFlagUrl($languages[$key]->flag_code) . '" title="' . $languages[$key]->name . '" alt="' . $languages[$key]->name . '" />';
                $languages[$key]->flag_url = $this->getSeasonFlagUrl($languages[$key]->flag_code);
            }
        }

        return $languages;
    }

    public function pllFlag($flag, $code)
    {
        if (array_key_exists($code, $this->seasonsFlags)) {
            $flag['url'] = $this->getSeasonFlagUrl($code);
        }

        return $flag;
    }

    public function pllRelHreflangAttributes($hreflangs)
    {
        $woody_lang_enable = get_option('woody_lang_enable', []);

        // Dans le cas des saisons, on remplace le tableau hreflangs envoyé par polylang
        // pour ne renvoyer que les hreflangs de la même saison
        $currentSeasonLangs = $this->woodyPllLanguagesList(pll_current_language());
        if (!empty($currentSeasonLangs)) {
            $hreflangs = [];
            foreach ($currentSeasonLangs as $key => $langObject) {

                // On exclut les langues non actives. PLL n'exclut pas la langue courante, donc on fait de même
                //(Google recommends to include self link https://support.google.com/webmasters/answer/189077?hl=en)
                if (!in_array($langObject->slug, $woody_lang_enable)) {
                    continue;
                }

                $translation = apply_filters('woody_get_permalink', pll_get_post(get_the_ID(), $langObject->slug));
                if (!empty($translation)) {
                    $hreflangs[$this->locale_to_lang($langObject->locale)] = $translation;
                }
            }
        } else {
            foreach ($hreflangs as $lang => $hreflang) {
                if (!in_array($lang, $woody_lang_enable)) {
                    unset($hreflangs[$lang]);
                }
            }
        }

        return $hreflangs;
    }

    public function robotsTxt($output, $public)
    {
        if ('0' != $public) {
            $polylang = get_option('polylang');
            $woody_lang_enable = get_option('woody_lang_enable', []);

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
                foreach ($languages as $key => $slug) {
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
        $seasons = [
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

        return $seasons;
    }

    private function getSeasonFlagUrl($code)
    {
        return $this->addonAssetPath('woody-addon-polylang', 'img/flags/' . $this->seasonsFlags[$code]['img'] . '.png');
    }

    public function unsetSyncPostURL($fields, $post_id, $lang, $save_group)
    {
        unset($fields['post_name']);
        unset($fields['post_title']);

        return $fields;
    }

    public function modifyPostName($post_id, $tr_id, $lang)
    {
        wp_update_post([
            'ID' => $tr_id,
            'post_title' => get_the_title($post_id) . ' - ' . strtoupper($lang),
            'post_name' => ''
        ]);
    }

    public function woodyDefaultLangPostTitle($post_id)
    {
        return get_the_title(pll_get_post($post_id, $this->woodyPllDefaultLang()));
    }

    /**
     * Translate posts
     */
    public function translatePostsWpcli($args, $assoc_args)
    {
        if (!empty($args)) {
            $translate_from = !empty($args[0]) ? $args[0] : '' ;
            $translate_in = [] ;

            // TODO: Add filter to push new post types
            if (!empty($args[2]) && $args[2] == 'roadbook') {
                $post_types = ['woody_rdbk_leaflets', 'woody_rdbk_feeds'];
            } else {
                $post_types = ['page'];
            }

            output_log('Arg 0 is ' . $args[0]);
            output_log('Arg 1 is ' . $args[1]);
            output_log('Arg 2 is ' . $args[2]);


            if (!empty($args[1])) {
                $translate_in = $this->existingLanguages($args[1]);
            } else {
                output_error('Missing "translate_in" argument. Use : WP_SITE_KEY=<sitename> wp woody:translate_posts <translate from> <translate_in_lang>');
            }

            if (!empty($translate_from) && !empty($translate_in)) {
                $args = array(
                    'post_status' => 'any',
                    'post_parent' => 0,
                    'posts_per_page' => -1,
                    'post_type' => $post_types,
                    'lang' => $translate_from,
                    'orderby' => 'menu_order',
                    'order' => 'ASC'
                );

                $query_result = new \WP_Query($args);

                if (!empty($query_result->posts)) {
                    foreach ($translate_in as $lang) {
                        // Do not translate language into the same language
                        if ($lang != $translate_from) {
                            foreach ($query_result->posts as $post) {
                                $this->translatePosts($post, $translate_from, $lang);
                            }
                            output_success('Posts translated successfully, ' . $query_result->found_posts . ' posts translated in '. $lang);
                        } else {
                            output_warning('Do not translate a language into the same language.');
                        }
                    }
                } else {
                    output_error('0 post to translate. Make sure that this language ('.$translate_from.') exists, and that pages are associated with it.');
                }
            } else {
                output_error('Missing or invalid arguments.');
            }
        } else {
            output_error('Missing or invalid arguments.');
        }
    }

    private function existingLanguages($args)
    {
        $translate_in = explode(',', $args) ;
        $languages = pll_languages_list(array('hide_empty' => 0));
        $return = !empty(array_intersect($languages, $translate_in)) ? array_intersect($languages, $translate_in) : [];

        return $return;
    }

    /**
     * Recursive function that translate firstly the parent post, and then try to translate children if they exists
     */
    private function translatePosts($post_parent, $translate_from, $lang)
    {
        // Check if translated post already exists
        $result = pll_get_post($post_parent->ID, $lang);

        if ($result) {
            output_log('Post '. $post_parent->ID . ' already translated in '. $lang . '. Post ID : '. $result);
        } else {
            $new_post = PLL()->sync_post->copy_post($post_parent->ID, $lang, false);
        }

        $args = array(
            'post_status' => 'any',
            'post_parent' => $post_parent->ID,
            'posts_per_page' => -1,
            'post_type' => 'page',
            'lang' => $translate_from,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        );

        $query_result = new \WP_Query($args);
        if (!empty($query_result->posts)) {
            foreach ($query_result->posts as $post) {
                $this->translatePosts($post, $translate_from, $lang);
            }
        }
    }
}
