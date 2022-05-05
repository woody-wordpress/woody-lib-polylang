<?php

/**
 * Woody Lib Polylang
 * @author Léo POIROUX
 * @copyright Raccourci Agency 2022
 */

namespace Woody\Lib\Polylang\Commands;

// WP_SITE_KEY=superot wp woody:translate post --post=1234 --source=fr --target=en,de --addon=roadbook --deepl=true
// WP_SITE_KEY=superot wp woody:translate posts --source=fr --target=en,de --addon=roadbook --deepl=true

class TranslateCommands
{
    public function post($args, $assoc_args)
    {
        // Get post_id
        if (empty($assoc_args['post'])) {
            output_error('Argument manquant ou invalide "--post=1234"');
        } else {
            $post_id = $assoc_args['post'];
            $post = get_post($post_id);
            $translate_from = pll_get_post_language($post_id);
        }

        // Get target
        if (empty($assoc_args['target'])) {
            output_error('Argument manquant ou invalide "--target=en,de"');
        } else {
            $translate_in = $this->existingLanguages($assoc_args['target']);
        }

        // Get auto_translate deepL
        if (!empty($assoc_args['deepl']) && filter_var($assoc_args['deepl'], FILTER_VALIDATE_BOOLEAN) == true) {
            $auto_translate = true;
        } else {
            $auto_translate = false;
        }

        if (!empty($translate_from) && !empty($translate_in) && !empty($post)) {
            foreach ($translate_in as $lang) {
                // Do not translate language into the same language
                if ($lang != $translate_from) {
                    $this->translatePost($post, $translate_from, $lang, $auto_translate);
                    output_success('Post traduit avec succès');
                } else {
                    output_warning('Ne pas traduire dans la même langue');
                }
            }
        }
    }

    public function posts($args, $assoc_args)
    {
        // Get source
        if (empty($assoc_args['source'])) {
            output_error('Argument manquant ou invalide "--source=fr"');
        } else {
            $translate_from = current($this->existingLanguages($assoc_args['source']));
        }

        // Get target
        if (empty($assoc_args['target'])) {
            output_error('Argument manquant ou invalide "--target=en,de"');
        } else {
            $translate_in = $this->existingLanguages($assoc_args['target']);
        }

        // Get addon
        // TODO: Add filter to push new post types
        if (!empty($assoc_args['addon']) && $assoc_args['addon'] == 'roadbook') {
            $post_types = ['woody_rdbk_leaflets', 'woody_rdbk_feeds'];
        } else {
            $post_types = ['page'];
        }

        // Get auto_translate deepL
        if (!empty($assoc_args['deepl']) && filter_var($assoc_args['deepl'], FILTER_VALIDATE_BOOLEAN) == true) {
            $auto_translate = true;
        } else {
            $auto_translate = false;
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
            output_h1(sprintf('%s posts trouvés en %s à traduire vers %s', $query_result->found_posts, $translate_from, implode(',', $translate_in)));
            if (!empty($query_result->posts)) {
                foreach ($translate_in as $lang) {
                    // Do not translate language into the same language
                    if ($lang != $translate_from) {
                        foreach ($query_result->posts as $post) {
                            $this->translatePosts($post, $translate_from, $lang, $auto_translate);
                        }
                        output_success('Posts traduits avec succès, ' . $query_result->found_posts . ' posts traduits vers '. $lang);
                    } else {
                        output_warning('Ne pas traduire dans la même langue');
                    }
                }
            } else {
                output_error('0 post à traduire. Etes-vous certain que la langue ('.$translate_from.') existe, et que des pages existent dans cette langue.');
            }
        }
    }

    private function existingLanguages($langs)
    {
        $langs = explode(',', $langs) ;
        $languages = pll_languages_list(array('hide_empty' => 0));
        return !empty(array_intersect($languages, $langs)) ? array_intersect($languages, $langs) : [];
    }

    /**
     * Recursive function that translate firstly the parent post, and then try to translate children if they exists
     */
    private function translatePost($post, $translate_from, $lang, $auto_translate = false)
    {
        // Check if translated post already exists
        $result = pll_get_post($post->ID, $lang);

        if (!empty($result)) {
            output_warning(sprintf('Post N°%s déjà traduit vers %s (traduction N°%s)', $post->ID, strtoupper($lang), $result));
        } else {
            output_h2(sprintf('Traduction du post N°%s vers %s', $post->ID, strtoupper($lang)));
            $new_post_id = PLL()->sync_post->copy_post($post->ID, $lang, false);

            // Si on est en mode "auto_translate", on cherche un traducteur automatique (dans un autre addon par exemple)
            if ($auto_translate) {
                do_action('woody_polylang_auto_translate', $new_post_id);
            } else {
                // Permet de créer une page avec suffixe de langue dans le titre et le permalien lors de la traduction de pages en masse
                // Don't use wp_update_post to avoid conflict (reverse sync).
                global $wpdb;

                $post_title = get_the_title($post->ID);
                $post_title .= ' - ' . strtoupper($lang);
                $wpdb->update($wpdb->posts, ['post_title' => $post_title, 'post_name' => sanitize_title($post_title)], ['ID' => $new_post_id]);
                output_success(sprintf('Titre du post changé en "%s"', $post_title));
                clean_post_cache($new_post_id);

                $new_post = get_post($new_post_id);
                do_action('save_post', $new_post_id, $new_post, true);
            }
        }
    }

    private function translatePosts($post, $translate_from, $lang, $auto_translate = false)
    {
        $this->translatePost($post, $translate_from, $lang, $auto_translate);

        $args = array(
            'post_status' => 'any',
            'post_parent' => $post->ID,
            'posts_per_page' => -1,
            'post_type' => 'page',
            'lang' => $translate_from,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        );

        $query_result = new \WP_Query($args);
        if (!empty($query_result->posts)) {
            foreach ($query_result->posts as $children_post) {
                $this->translatePosts($children_post, $translate_from, $lang, $auto_translate);
            }
        }
    }
}
