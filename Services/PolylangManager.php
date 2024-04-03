<?php

/**
 * @author Léo POIROUX
 * @copyright Raccourci Agency 2023
 */

namespace Woody\Lib\Polylang\Services;

class PolylangManager
{
    private $pllacfAutoTranslate;

    public function __construct()
    {
        $this->pllacfAutoTranslate = new \PLL_ACF_Auto_Translate();
    }

    public function woodyTranslatePost($post, $target_lang, $auto_translate = false, $sync_before_translate = false)
    {
        // Si on post_id est passé à la fonction
        if (is_numeric($post)) {
            $post = get_post($post);
        }

        $source_lang = woody_pll_get_post_language($post->ID);

        $this->translatePost($post, $source_lang, $target_lang, $auto_translate, $sync_before_translate);
    }

    public function woodyTranslateFields($post, $source_lang)
    {
        // Si on post_id est passé à la fonction
        if (is_numeric($post)) {
            $post = get_post($post);
        }

        $this->translateFields($post, $source_lang);
    }

    /**
     * POST
     */
    public function translatePost($post, $source_lang, $target_lang, $auto_translate = false, $sync_before_translate = false)
    {
        if ($target_lang == $source_lang) {
            output_error('Ne pas traduire dans la même langue');
            exit();
        }

        // Check if translated post already exists
        $tr_post_id = pll_get_post($post->ID, $target_lang);

        if (!empty($tr_post_id)) {
            output_warning(sprintf('Post N°%s déjà traduit vers %s (traduction N°%s)', $post->ID, strtoupper($target_lang), $tr_post_id));

            // Si on est en mode "auto_translate" == force, on cherche un traducteur automatique (dans un autre addon par exemple)
            // Et on importe le contenu source dans le contenu target pour refaire une traduction complète
            if ($sync_before_translate) {

                // On récupère la liste des posts synchronisés avec le post source
                $synchronized_posts = PLL()->sync_post->sync_model->get($post->ID);
                $synchronized_langs = (empty($synchronized_posts)) ? [] : array_keys($synchronized_posts);

                // On sauvegarde le fait que le post traduit doit être synchronisé avec le post source
                PLL()->sync_post->sync_model->save_group($post->ID, array_merge($synchronized_langs, [$target_lang]));

                // On sauvegarde le post source pour importer la langue source dans le post traduit.
                do_action('save_post', $post->ID, $post, true);

                // On remet les synchronisations comme avant
                PLL()->sync_post->sync_model->save_group($post->ID, $synchronized_posts);

                // On traduit le post synchronisé (qui contient désormais des textes dans la langue source)
                if ($auto_translate) {
                    do_action('woody_auto_translate_post', $tr_post_id, $source_lang);
                }

                output_success(sprintf('Post N°%s re-traduit intégralement vers %s (traduction N°%s)', $post->ID, strtoupper($target_lang), $tr_post_id));
            }
        } else {
            $tr_post_id = PLL()->sync_post->sync_model->copy_post($post->ID, $target_lang, false);

            // Si on est en mode "auto_translate", on cherche un traducteur automatique (dans un autre addon par exemple)
            if ($auto_translate) {
                do_action('woody_auto_translate_post', $tr_post_id, $source_lang);
            } else {
                // Permet de créer une page avec suffixe de langue dans le titre et le permalien lors de la traduction de pages en masse
                // Don't use wp_update_post to avoid conflict (reverse sync).
                global $wpdb;

                $post_title = get_the_title($post->ID);
                $post_title .= ' - ' . strtoupper($target_lang);
                $wpdb->update($wpdb->posts, ['post_title' => $post_title, 'post_name' => sanitize_title($post_title)], ['ID' => $tr_post_id]);
                output_success(sprintf('Titre du post changé en "%s"', $post_title));
                clean_post_cache($tr_post_id);

                $tr_post = get_post($tr_post_id);
                do_action('save_post', $tr_post_id, $tr_post, true);
            }
        }
    }

    /**
     * FIELDS
     */
    public function translateFields($post, $source_lang, $assoc_args = [])
    {
        $tr_post_id = pll_get_post($post->ID, $source_lang);
        $post_metas = get_post_meta($post->ID);
        $lang = pll_get_post_language($post->ID);

        $total_post_metas = is_countable($post_metas) ? count($post_metas) : 0;
        $fixed_post_metas = 0;

        if (!empty($post_metas) && !empty($lang)) {
            foreach ($post_metas as $key => $value) {
                if (substr($key, 0, 1) != '_') {
                    $value = (is_array($value)) ? current($value) : maybe_unserialize($value);
                    $new_value = $this->translate_meta($value, $key, $lang, $tr_post_id, $post->ID);

                    // Si le retour est un tableau on serialize pour comparer
                    $compare_new_value = (!empty($new_value) && (is_array($new_value) || is_object($data))) ? serialize($new_value) : $new_value;

                    // Si différent on met à jour
                    if (!empty($value) && (!empty($new_value) && $value != $compare_new_value)) {
                        if ($assoc_args['dry']) {
                            output_log(sprintf('wp_postmeta %s : %s will be replaced by %s for post %s', $key, $value, $new_value, $post->ID));
                        } else {
                            update_post_meta($post->ID, $key, $new_value);
                            output_success(sprintf('%s', $key));
                            output_log(sprintf(' - Avant : %s', $value));
                            output_log(sprintf(' - Après : %s', maybe_serialize($new_value)));
                        }

                        ++$fixed_post_metas;
                    }
                }
            }
        }

        if (empty($fixed_post_metas)) {
            output_log('Aucune méta à corriger');
        } else {
            output_success(sprintf('%s/%s métas corrigées', $fixed_post_metas, $total_post_metas));
        }
    }

    private function translate_meta($value, $key, $lang, $tr_post_id, $post_id)
    {
        if ((substr($key, -4) == 'text') || (substr($key, -11) == 'description') || (substr($key, -5) == 'title') || (substr($key, -4) == 'desc')) {
            if (!empty($value)) {
                preg_match_all('#href="([^"]+)"#', $value, $matches);
                if (is_array($matches) && is_array($matches[1])) {
                    foreach ($matches[1] as $url) {
                        $url_to_postid = $this->url_to_postid($url);
                        $pll_post_id = (empty($url_to_postid)) ? null : pll_get_post($url_to_postid, $lang);
                        $permalink = (empty($pll_post_id)) ? null : get_permalink($pll_post_id);
                        $value = (empty($permalink)) ? $value : str_replace($url, $permalink, $value);
                    }

                    return $value;
                }
            }
        } elseif (substr($key, -4) == 'link') {
            $value = maybe_unserialize($value);
            if (is_array($value) && !empty($value['url'])) {
                $url_to_postid = $this->url_to_postid($value['url']);
                $pll_post_id = (empty($url_to_postid)) ? null : pll_get_post($url_to_postid, $lang);
                $value['url'] = (empty($pll_post_id)) ? $value['url'] : get_permalink($pll_post_id);
                return $value;
            }
        } else {
            return $this->pllacfAutoTranslate->translate_meta($value, $key, $lang, $tr_post_id, $post_id);
        }
    }

    private function url_to_postid($url)
    {
        // On supprime le domaine qui perturbe url_to_postid
        $parsed_url = parse_url($url);
        $url = (!empty($parsed_url['host']) && !empty($parsed_url['scheme'])) ? $parsed_url['path'] : $url;

        // On cherche le post_id avec l'url
        $url_to_postid = url_to_postid($url);
        if (empty($url_to_postid)) {
            $parse_url = parse_url($url);
            if (!empty($parse_url['path'])) {
                $path = (substr($parse_url['path'], -1) == '/') ? substr($parse_url['path'], 0, -1) : $parse_url['path'];

                global $wpdb;
                $sql = "SELECT action_data FROM `{$wpdb->base_prefix}redirection_items` WHERE match_url = '" . $path . "' AND status = 'enabled' ORDER BY position ASC LIMIT 1;";
                $results = $wpdb->get_results($sql);
                if (!empty($results) && !empty($results[0]) && !empty($results[0]->action_data)) {
                    return url_to_postid($results[0]->action_data);
                }
            }
        }

        return $url_to_postid;
    }
}
