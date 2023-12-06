<?php

/**
 * Woody Lib Polylang
 * @author Léo POIROUX
 * @copyright Raccourci Agency 2022
 */

// Fonction de duplication des médias
function woody_pll_create_media_translation($post_id, $source_lang, $target_lang)
{
    return apply_filters('woody_pll_create_media_translation', $post_id, $source_lang, $target_lang);
}

// Retourne pll_languages_list(['fields' => ''])
// Si current_season passé en paramètre, ne retourne que les langues de la même saison
// ex : $current_season = 'hiver'
// ex : $current_season = 'auto' (va chercher la saison de la pll_current_language)
function woody_pll_languages_list($current_season = null)
{
    return apply_filters('woody_pll_languages_list', $current_season);
}

// Retourne le code langue courant mais en se basant sur les locales (ex: fr)
function woody_pll_current_language()
{
    return apply_filters('woody_pll_current_language', null);
}

// Retourne la saison de la langue courante (ex: hiver)
function woody_pll_current_season()
{
    return apply_filters('woody_pll_current_season', null);
}

// Retourne la langue du post mais en se basant sur les locales (ex: fr)
function woody_pll_get_post_language($post_id)
{
    return apply_filters('woody_pll_get_post_language', $post_id);
}

// Retourne la saison du post (ex: hiver)
function woody_pll_get_post_season($post_id)
{
    return apply_filters('woody_pll_get_post_season', $post_id);
}

// Retourne le code lang (issue de la locale) à partir du slug (ex: fr)
function woody_pll_get_lang_by_slug($slug)
{
    return apply_filters('woody_pll_get_lang_by_slug', $slug);
}

// Retourne le code locale à partir du slug (ex: fr_FR)
function woody_pll_get_locale_by_slug($slug)
{
    return apply_filters('woody_pll_get_locale_by_slug', $slug);
}

// Retourne le slug à partir de la locale (ex: fr_FR ou fr-FR)
function woody_pll_get_slug_by_locale($locale)
{
    return apply_filters('woody_pll_get_slug_by_locale', $locale);
}

// Retourne le switcher de langue
// Si current_season passé en paramètre, ne retourne que les langues de la même saison
// ex : $current_season = 'hiver'
// ex : $current_season = 'auto' (va chercher la saison de la pll_current_language)
function woody_pll_the_languages($current_season = null)
{
    return apply_filters('woody_pll_the_languages', $current_season);
}

// Retourne les codes langues sur 2 caractères mais en se basant sur les locales (ex ['fr', 'en'])
function woody_pll_the_locales()
{
    return apply_filters('woody_pll_the_locales', null);
}

// Liste les saisons d'une même langue
function woody_pll_the_seasons()
{
    return apply_filters('woody_pll_the_seasons', null);
}

// Retourne la langue par défaut mais en se basant sur la locale si on est sur un site avec saison (ex: fr)
function woody_pll_default_lang($season = null)
{
    return apply_filters('woody_pll_default_lang', $season);
}

// Retourne le titre d'un post dans la langue par défaut
function woody_default_lang_post_title($post_id)
{
    return apply_filters('woody_default_lang_post_title', $post_id);
}
