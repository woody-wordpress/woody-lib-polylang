<?php

/**
 * Woody Lib Polylang
 * @author Léo POIROUX
 * @copyright Raccourci Agency 2022
 */

namespace Woody\Lib\Polylang\Commands;

// WP_SITE_KEY=superot wp woody:translate post --post=1234 --target=en,de --deepl=true
// WP_SITE_KEY=superot wp woody:translate posts --source=fr --target=en,de --addon=roadbook --deepl=true
// WP_SITE_KEY=superot wp woody:translate terms --source=fr --target=en,de --tax=themes,places --deepl=true
// WP_SITE_KEY=superot wp woody:translate fields

class TranslateCommands
{
    public function post($args, $assoc_args)
    {
        // Get post_id
        if (empty($assoc_args['post'])) {
            output_error('Argument manquant ou invalide "--post=1234"');
        } elseif (is_numeric($assoc_args['post'])) {
            $post_id = $assoc_args['post'];
            $post = get_post($post_id);
            $translate_from = pll_get_post_language($post_id);
        }

        // Get target
        if (empty($assoc_args['target'])) {
            output_error('Argument manquant ou invalide "--target=en,de"');
        } else {
            $translate_to = $this->existingLanguages($assoc_args['target']);
        }

        // Get auto_translate deepL
        if (!empty($assoc_args['deepl']) && filter_var($assoc_args['deepl'], FILTER_VALIDATE_BOOLEAN) == true) {
            $auto_translate = true;
        } else {
            $auto_translate = false;
        }

        if (!empty($translate_from) && !empty($translate_to) && !empty($post)) {
            foreach ($translate_to as $lang) {
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
            $translate_from = current($this->existingLanguages('fr'));
            if (empty($translate_from)) {
                output_error('Argument manquant ou invalide "--source=fr"');
            }
        } else {
            $translate_from = current($this->existingLanguages($assoc_args['source']));
        }

        // Get target
        if (empty($assoc_args['target'])) {
            output_error('Argument manquant ou invalide "--target=en,de"');
        } else {
            $translate_to = $this->existingLanguages($assoc_args['target']);
        }

        // Get target
        if (empty($assoc_args['types'])) {
            $post_types = (strpos($assoc_args['types'], ',') !== false) ? explode(',', $assoc_args['types']) : $assoc_args['types'];
        } elseif (!empty($assoc_args['types']) && $assoc_args['types'] == 'roadbook') {
            $post_types = ['woody_rdbk_leaflets', 'woody_rdbk_feeds'];
        } else {
            $post_types = ['page', 'profile'];
        }

        // Get auto_translate deepL
        if (!empty($assoc_args['deepl']) && filter_var($assoc_args['deepl'], FILTER_VALIDATE_BOOLEAN) == true) {
            $auto_translate = true;
        } else {
            $auto_translate = false;
        }

        if (!empty($translate_from) && !empty($translate_to)) {
            $args = [
                'post_status' => 'any',
                'post_parent' => 0,
                'posts_per_page' => -1,
                'post_type' => $post_types,
                'lang' => $translate_from,
                'orderby' => 'menu_order',
                'order' => 'ASC'
            ];

            $query_result = new \WP_Query($args);
            output_h1(sprintf('%s posts trouvés en %s à traduire vers %s', $query_result->found_posts, $translate_from, implode(',', $translate_to)));
            if (!empty($query_result->posts)) {
                foreach ($translate_to as $lang) {
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
        $languages = pll_languages_list(['hide_empty' => 0]);
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
                do_action('woody_auto_translate_term', $new_post_id);
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

        $args = [
            'post_status' => 'any',
            'post_parent' => $post->ID,
            'posts_per_page' => -1,
            'post_type' => 'page',
            'lang' => $translate_from,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ];

        $query_result = new \WP_Query($args);
        if (!empty($query_result->posts)) {
            foreach ($query_result->posts as $children_post) {
                $this->translatePosts($children_post, $translate_from, $lang, $auto_translate);
            }
        }
    }

    public function terms($args, $assoc_args)
    {
        // Get source
        if (empty($assoc_args['source'])) {
            $translate_from = current($this->existingLanguages('fr'));
            if (empty($translate_from)) {
                output_error('Argument manquant ou invalide "--source=fr"');
            }
        } else {
            $translate_from = current($this->existingLanguages($assoc_args['source']));
        }

        // Get target
        if (empty($assoc_args['target'])) {
            output_error('Argument manquant ou invalide "--target=en,de"');
        } else {
            $translate_to = $this->existingLanguages($assoc_args['target']);
        }

        // Get source
        if (empty($assoc_args['tax'])) {
            $taxonomies = ['themes', 'places', 'seasons'];
        } else {
            $taxonomies = explode(',', $assoc_args['tax']);
        }

        // Get auto_translate deepL
        if (!empty($assoc_args['deepl']) && filter_var($assoc_args['deepl'], FILTER_VALIDATE_BOOLEAN) == true) {
            $auto_translate = true;
        } else {
            $auto_translate = false;
        }

        if (!empty($translate_from) && !empty($translate_to)) {
            foreach ($translate_to as $lang) {
                // Do not translate language into the same language
                if ($lang != $translate_from) {
                    $this->translateTerms($taxonomies, $translate_from, $lang, $auto_translate);
                    output_success('Taxonomy traduite avec succès');
                } else {
                    output_warning('Ne pas traduire dans la même langue');
                }
            }
        }
    }

    private function translateTerms($taxonomies, $translate_from, $translate_to, $auto_translate = false, $parent = 0)
    {
        if (!empty($taxonomies)) {
            $taxonomies = (is_array($taxonomies)) ? $taxonomies : [$taxonomies];
            foreach ($taxonomies as $taxonomy) {
                if (!get_taxonomy($taxonomy)) {
                    output_error(sprintf("La taxonomie '%s' n'existe pas", $taxonomy));
                    exit();
                }
            }
        }

        foreach ($taxonomies as $taxonomy) {
            output_h2(sprintf('Traduction des termes de la taxonomie %s', $taxonomy));
            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
                'parent' => $parent,
                'lang' => $translate_from
            ]);

            if (!empty($terms)) {
                foreach ($terms as $term) {
                    output_log(sprintf('Traduction du terme %s (%s)', $term->name, $term->term_id));
                    $tr_term_id = pll_get_term($term->term_id, $translate_to);

                    if (!empty($tr_term_id)) {
                        output_warning(sprintf('Tag N°%s déjà traduit vers %s (traduction N°%s)', $term->term_id, strtoupper($translate_to), $tr_term_id));
                    } else {
                        $tr_term_id = PLL()->sync_content->duplicate_term(null, $term, $translate_to);

                        if ($auto_translate) {
                            do_action('woody_auto_translate_term', $tr_term_id, $taxonomy);
                        }

                        output_success(sprintf('%s (N°%s) > (N°%s)', $term->name, $term->term_id, $tr_term_id));
                    }

                    // Traduire les enfants
                    $this->translateTerms($taxonomy, $translate_from, $translate_to, $auto_translate, $term->term_id);
                }
            }
        }
    }

    public function fields($args, $assoc_args)
    {
        // Get target
        if (empty($assoc_args['source'])) {
            $source = 'fr';
        } else {
            $source = $this->existingLanguages($assoc_args['source']);
        }

        // Get target
        if (!empty($assoc_args['post']) && is_numeric($assoc_args['post'])) {
            $post_id = $assoc_args['post'];
            $post_metas = $this->getTranslateFields($post_id, $source);
        } else {
            // Get target
            if (empty($assoc_args['lang'])) {
                output_error('Argument manquant ou invalide "--lang=en"');
            } else {
                $lang = $this->existingLanguages($assoc_args['lang']);
            }

            // Get target
            if (empty($assoc_args['types'])) {
                $post_types = (strpos($assoc_args['types'], ',') !== false) ? explode(',', $assoc_args['types']) : $assoc_args['types'];
            } elseif (!empty($assoc_args['types']) && $assoc_args['types'] == 'roadbook') {
                $post_types = ['woody_rdbk_leaflets', 'woody_rdbk_feeds'];
            } else {
                $post_types = ['page', 'profile'];
            }

            $args = [
                'post_status' => 'any',
                'post_parent' => 0,
                'posts_per_page' => -1,
                'lang' => $lang,
                'post_type' => $post_types,
                'orderby' => 'menu_order',
                'order' => 'ASC'
            ];

            // $query_result = new \WP_Query($args);
            // output_h1(sprintf('%s posts trouvés en %s à corriger', $query_result->found_posts, $lang));
            // if (!empty($query_result->posts)) {
            //     foreach ($query_result->posts as $post) {
            //         $post_metas = $this->getTranslateFields($post->ID, $source);
            //         print_r($post_metas);
            //         exit();
            //     }
            //     output_success('Posts traduits avec succès, ' . $query_result->found_posts . ' posts traduits vers '. $lang);
            // } else {
            //     output_error('0 post à traduire. Etes-vous certain que la langue ('.$translate_from.') existe, et que des pages existent dans cette langue.');
            // }
        }
    }

    private function getTranslateFields($post_id, $source)
    {
        $post_id_from = pll_get_post($post_id, $source);
        $post_metas = get_post_meta($post_id);
        $lang = pll_get_post_language($post_id);
        if (!empty($post_metas) && !empty($lang)) {
            $pll_acf = new \PLL_ACF_Auto_Translate();
            foreach ($post_metas as $key => $value) {
                if (substr($key, 0, 1) != '_') {
                    $new_value = $pll_acf->translate_meta($value, $key, $lang, $post_id_from, $post_id);

                    // Si différent on met à jour
                    $value = (is_array($value)) ? current($value) : $value;
                    $new_value = (is_array($new_value)) ? current($new_value) : $new_value;
                    if (!empty($value) && ((!empty($new_value) && $value != $new_value) || empty($new_value))) {
                        //update_post_meta($post_id, $key, $new_value);
                        output_success(sprintf('%s (%s > %s)', $key, $value, $new_value));
                    }
                }
            }
        }
    }
}
