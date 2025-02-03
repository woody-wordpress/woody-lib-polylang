<?php

/**
 * Woody Lib Polylang
 * @author Léo POIROUX
 * @copyright Raccourci Agency 2022
 */

namespace Woody\Lib\Polylang\Commands;

use Woody\Lib\Polylang\Services\PolylangManager;

// WP_SITE_KEY=superot wp woody:translate post --post=1234 --target=en,de --deepl=true
// WP_SITE_KEY=superot wp woody:translate post --post=1234 --target=en,de --deepl=true --sync=true
// WP_SITE_KEY=superot wp woody:translate posts --source=fr --target=en,de --types=roadbook --deepl=true
// WP_SITE_KEY=superot wp woody:translate terms --source=fr --target=en,de --tax=themes,places --deepl=true
// WP_SITE_KEY=superot wp woody:translate fields --post=1234 --source=fr
// WP_SITE_KEY=superot wp woody:translate fields --lang=en --source=fr
// WP_SITE_KEY=superot wp woody:translate medias --target=en --sync=true

class PolylangCommands
{
    private $total;

    private $count;

    private $polylangManager;

    public function __construct(PolylangManager $polylangManager)
    {
        $this->total = 0;
        $this->count = 0;
        $this->polylangManager = $polylangManager;
    }

    private function existingLanguages($langs)
    {
        $langs = explode(',', $langs) ;
        $languages = pll_languages_list(['hide_empty' => 0]);
        return empty(array_intersect($languages, $langs)) ? [] : array_intersect($languages, $langs);
    }

    /**
     * POST
     */
    public function post($args, $assoc_args)
    {
        // Get post_id
        if (empty($assoc_args['post'])) {
            output_error('Argument manquant ou invalide "--post=1234"');
            exit();
        } elseif (is_numeric($assoc_args['post'])) {
            $post_id = $assoc_args['post'];
            $post = get_post($post_id);
            $source_lang = woody_pll_get_post_language($post_id);
        }

        // Get target
        if (empty($assoc_args['target'])) {
            output_error('Argument manquant ou invalide "--target=en,de"');
            exit();
        } else {
            $target_langs = $this->existingLanguages($assoc_args['target']);
        }

        // Get auto_translate deepL
        if (!empty($assoc_args['deepl']) && filter_var($assoc_args['deepl'], FILTER_VALIDATE_BOOLEAN) == true) {
            $auto_translate = true;
        } else {
            $auto_translate = false;
        }

        // Sync source post before translate
        if (!empty($assoc_args['sync']) && filter_var($assoc_args['sync'], FILTER_VALIDATE_BOOLEAN) == true) {
            $sync_before_translate = true;
        } else {
            $sync_before_translate = false;
        }

        if (!empty($source_lang) && !empty($target_langs) && !empty($post)) {
            foreach ($target_langs as $target_lang) {
                // Do not translate language into the same language
                if ($target_lang != $source_lang) {
                    $this->translatePost($post, $source_lang, $target_lang, $auto_translate, $sync_before_translate);
                    output_success('Post traduit avec succès');
                } else {
                    output_warning('Ne pas traduire dans la même langue');
                }
            }
        }
    }

    private function translatePost($post, $source_lang, $target_lang, $auto_translate = false, $sync_before_translate = false)
    {
        ++$this->count;
        output_h3(sprintf('%s/%s - Traduction du post N°%s vers %s', $this->count, $this->total, $post->ID, strtoupper($target_lang)));
        $this->polylangManager->translatePost($post, $source_lang, $target_lang, $auto_translate, $sync_before_translate);
    }

    /**
     * POSTS
     */
    public function posts($args, $assoc_args)
    {
        // Get source
        if (empty($assoc_args['source'])) {
            $source_lang = woody_woody_pll_default_lang();
        } else {
            $source_lang = current($this->existingLanguages($assoc_args['source']));
            if (empty($source_lang)) {
                output_error('Argument manquant ou invalide "--source=fr"');
                exit();
            }
        }

        // Get target
        if (empty($assoc_args['target'])) {
            output_error('Argument manquant ou invalide "--target=en,de"');
            exit();
        } else {
            $target_langs = $this->existingLanguages($assoc_args['target']);
        }

        // Get target
        if (!empty($assoc_args['types'])) {
            if ($assoc_args['types'] == 'roadbook') {
                //$post_types = ['woody_rdbk_leaflets', 'woody_rdbk_feeds'];
                $post_types = ['woody_rdbk_leaflets'];
            } else {
                $post_types = (strpos($assoc_args['types'], ',') !== false) ? explode(',', $assoc_args['types']) : $assoc_args['types'];
            }
        } else {
            $post_types = ['page', 'profile'];
        }

        // Get auto_translate deepL
        if (!empty($assoc_args['deepl']) && filter_var($assoc_args['deepl'], FILTER_VALIDATE_BOOLEAN) == true) {
            $auto_translate = true;
        } else {
            $auto_translate = false;
        }

        // Sync source post before translate
        if (!empty($assoc_args['sync']) && filter_var($assoc_args['sync'], FILTER_VALIDATE_BOOLEAN) == true) {
            $sync_before_translate = true;
        } else {
            $sync_before_translate = false;
        }

        if (!empty($source_lang) && !empty($target_langs)) {
            // Count Total posts
            $args = [
                'post_status' => 'any',
                'post_type' => $post_types,
                'lang' => $source_lang,
                'posts_per_page' => 1,
            ];

            $query_result = new \WP_Query($args);
            $this->count = 0;
            $this->total = $query_result->found_posts;
            output_h1(sprintf('%s posts trouvés en %s à traduire vers %s', $this->total, $source_lang, implode(',', $target_langs)));

            // Search only "post_parent = 0" posts
            $args = [
                'post_status' => 'any',
                'post_parent' => 0,
                'post_type' => $post_types,
                'lang' => $source_lang,
                'posts_per_page' => -1,
                'orderby' => 'menu_order',
                'order' => 'ASC'
            ];

            $query_result = new \WP_Query($args);
            if (!empty($query_result->posts)) {
                foreach ($target_langs as $target_lang) {
                    output_h2(sprintf('Traduction %s > %s', $source_lang, $target_lang));
                    if ($target_lang != $source_lang) {
                        foreach ($query_result->posts as $post) {
                            $this->translatePostAndChildren($post, $source_lang, $target_lang, $auto_translate, $sync_before_translate);
                        }
                    } else {
                        output_error('Ne pas traduire dans la même langue');
                    }
                }
            } else {
                output_error(sprintf('0 post à traduire. Etes-vous certain que la langue (%s) existe, et que des pages existent dans cette langue.', $source_lang));
            }
        }
    }

    private function translatePostAndChildren($post, $source_lang, $target_lang, $auto_translate = false, $sync_before_translate = false)
    {
        $this->translatePost($post, $source_lang, $target_lang, $auto_translate, $sync_before_translate);

        $args = [
            'post_status' => 'any',
            'post_parent' => $post->ID,
            'post_type' => $post->post_type,
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ];

        $wpQuery = new \WP_Query($args);
        if (!empty($wpQuery->posts)) {
            foreach ($wpQuery->posts as $children_post) {
                $this->translatePostAndChildren($children_post, $source_lang, $target_lang, $auto_translate);
            }
        }
    }

    /**
     * TERMS
     */
    public function terms($args, $assoc_args)
    {
        // Get source
        if (empty($assoc_args['source'])) {
            $source_lang = woody_pll_default_lang();
        } else {
            $source_lang = current($this->existingLanguages($assoc_args['source']));
            if (empty($source_lang)) {
                output_error('Argument manquant ou invalide "--source=fr"');
                exit();
            }
        }

        // Get target
        if (empty($assoc_args['target'])) {
            output_error('Argument manquant ou invalide "--target=en,de"');
            exit();
        } else {
            $target_langs = $this->existingLanguages($assoc_args['target']);
        }

        // Get source
        $taxonomies = empty($assoc_args['tax']) ? ['themes', 'places', 'seasons', 'targets'] : explode(',', $assoc_args['tax']);

        // Get auto_translate deepL
        if (!empty($assoc_args['deepl']) && filter_var($assoc_args['deepl'], FILTER_VALIDATE_BOOLEAN) == true) {
            $auto_translate = true;
        } else {
            $auto_translate = false;
        }

        // Sync source post before translate
        if (!empty($assoc_args['sync']) && filter_var($assoc_args['sync'], FILTER_VALIDATE_BOOLEAN) == true) {
            $sync_before_translate = true;
        } else {
            $sync_before_translate = false;
        }

        if (!empty($source_lang) && !empty($target_langs)) {
            foreach ($target_langs as $target_lang) {
                output_h1(sprintf('Traduction %s > %s', $source_lang, $target_lang));
                // Do not translate language into the same language
                if ($target_lang != $source_lang) {
                    foreach ($taxonomies as $taxonomy) {
                        if (!get_taxonomy($taxonomy)) {
                            output_error(sprintf("La taxonomie '%s' n'existe pas", $taxonomy));
                        } else {
                            output_h2(sprintf('Traduction des termes de la taxonomie %s', $taxonomy));
                            $this->translateTerms($taxonomy, $source_lang, $target_lang, $auto_translate, $sync_before_translate);
                        }
                    }

                    output_success('Taxonomy traduite avec succès');
                } else {
                    output_warning('Ne pas traduire dans la même langue');
                }
            }
        }
    }

    private function translateTerms($taxonomy, $source_lang, $target_lang, $auto_translate = false, $sync_before_translate = false, $parent = 0)
    {
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'parent' => $parent,
            'lang' => $source_lang
        ]);

        if (!empty($terms)) {
            foreach ($terms as $term) {
                ++$this->count;
                output_h3(sprintf('Traduction du tag "%s" (%s)', $term->name, $term->term_id));
                $tr_term_id = pll_get_term($term->term_id, $target_lang);

                if (!empty($tr_term_id)) {
                    // Si on est en mode "sync_before_translate" == true, on cherche un traducteur automatique (dans un autre addon par exemple)
                    if ($sync_before_translate) {
                        do_action('woody_auto_translate_term', $tr_term_id, $taxonomy, $source_lang);
                    }

                    output_warning(sprintf('Tag N°%s déjà traduit vers "%s" (traduction N°%s)', $term->term_id, strtoupper($target_lang), $tr_term_id));
                } else {
                    $tr_term_id = PLL()->sync_content->duplicate_term(null, $term, $target_lang);

                    if ($auto_translate) {
                        do_action('woody_auto_translate_term', $tr_term_id, $taxonomy, $source_lang);
                    }

                    output_success(sprintf('%s (N°%s) > (N°%s)', $term->name, $term->term_id, $tr_term_id));
                }

                // Traduire les enfants
                $this->translateTerms($taxonomy, $source_lang, $target_lang, $auto_translate, false, $term->term_id);
            }
        } elseif ($parent == 0) {
            output_error(sprintf('Aucun tag à traduire dans la taxonomie %s', $taxonomy));
        }
    }

    /**
     * FIELDS
     */
    public function fields($args, $assoc_args)
    {
        // Get target
        $source_lang = empty($assoc_args['source']) ? woody_pll_default_lang() : current($this->existingLanguages($assoc_args['source']));

        // Get target
        if (!empty($assoc_args['post']) && is_numeric($assoc_args['post'])) {
            $post = get_post($assoc_args['post']);
            $this->translateFields($post, $source_lang, $assoc_args);
        } else {
            // Get target
            if (empty($assoc_args['lang'])) {
                output_error('Argument manquant ou invalide "--lang=en"');
                exit();
            } else {
                $target_lang = $this->existingLanguages($assoc_args['lang']);
            }

            if ($target_lang == $source_lang) {
                output_error('Ne pas corriger en se basant sur la même langue');
                exit();
            }

            // Get target
            if (!empty($assoc_args['types']) && $assoc_args['types'] == 'roadbook') {
                $post_types = ['woody_rdbk_leaflets', 'woody_rdbk_feeds'];
            } elseif (!empty($assoc_args['types'])) {
                $post_types = (strpos($assoc_args['types'], ',') !== false) ? explode(',', $assoc_args['types']) : $assoc_args['types'];
            } else {
                $post_types = ['page', 'profile'];
            }

            // Count Total posts
            $args = [
                'post_status' => 'any',
                'post_type' => $post_types,
                'lang' => $target_lang,
                'posts_per_page' => 1,
            ];

            $query_result = new \WP_Query($args);
            $this->count = 0;
            $this->total = $query_result->found_posts;
            output_h1(sprintf('%s posts trouvés à corriger', $this->total));

            $args = [
                'post_status' => 'any',
                'post_parent' => 0,
                'lang' => $target_lang,
                'post_type' => $post_types,
                'posts_per_page' => -1,
                'orderby' => 'menu_order',
                'order' => 'ASC'
            ];

            $query_result = new \WP_Query($args);
            if (!empty($query_result->posts)) {
                foreach ($query_result->posts as $post) {
                    $this->translateFieldsAndChildren($post, $source_lang, $assoc_args);
                }

                output_success('Posts corrigés avec succès');
            } else {
                output_error(sprintf('0 post à corriger. Etes-vous certain que la langue (%s) existe, et que des pages existent dans cette langue.', $target_lang));
            }
        }
    }

    private function translateFieldsAndChildren($post, $source_lang, $assoc_args)
    {
        $this->translateFields($post, $source_lang, $assoc_args);

        $args = [
            'post_status' => 'any',
            'post_parent' => $post->ID,
            'post_type' => $post->post_type,
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ];

        $wpQuery = new \WP_Query($args);
        if (!empty($wpQuery->posts)) {
            foreach ($wpQuery->posts as $children_post) {
                $this->translateFieldsAndChildren($children_post, $source_lang, $assoc_args);
            }
        }
    }

    private function translateFields($post, $source_lang, $assoc_args = [])
    {
        ++$this->count;
        output_h3(sprintf('%s/%s - Correction du post N°%s', $this->count, $this->total, $post->ID));
        $this->polylangManager->translateFields($post, $source_lang, $assoc_args);
    }

    /**
     * MEDIAS
     */
    public function medias($args, $assoc_args)
    {
        $source_lang = (empty($assoc_args['source'])) ? woody_pll_default_lang() : $assoc_args['source'];
        $syncMeta = filter_var($assoc_args['sync'], FILTER_VALIDATE_BOOLEAN);

        // Count Total posts
        $args = [
            'post_status' => 'any',
            'post_type' => 'attachment',
            'lang' => $source_lang,
            'posts_per_page' => -1,
        ];

        $wpQuery = new \WP_Query($args);
        $this->count = 0;
        $this->total = $wpQuery->found_posts;
        output_h1(sprintf('%s médias trouvés à traduire', $this->total));
        if (!empty($wpQuery->posts)) {
            foreach ($wpQuery->posts as $post) {
                ++$this->count;
                output_h3(sprintf('%s/%s - Traduction du média N°%s', $this->count, $this->total, $post->ID));
                if (wp_attachment_is_image($post->ID)) {
                        do_action('woody_save_attachment', $post->ID, $syncMeta);
                } else {
                    output_log("Ce média n'est pas une image");
                }
            }
        } else {
            output_error(sprintf('0 post à traduire. Etes-vous certain que la langue (%s) existe, et que des médias existent dans cette langue.', $source_lang));
        }
    }
}
