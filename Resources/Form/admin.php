<?php
/**
 * Woody Polylang
 * @author      Leo POIROUX
 * @copyright   2019 Raccourci Agency
 */

use Nette\Forms\Form;

if (!defined('ABSPATH')) {
    // Exit if accessed directly
    exit;
}

// Tabs
$active_tab = empty(filter_input(INPUT_GET, 'tab')) ? 'enable_lang' : filter_input(INPUT_GET, 'tab');

// https://doc.nette.org/en/2.4/forms
$form = new Form();

$custom_tabs = apply_filters("polylang_custom_tabs", []);
switch ($active_tab) {
    case 'enable_lang':
        $woody_lang_enable = get_option('woody_lang_enable', []);

        foreach ($languages as $language) {
            $enable = in_array($language->slug, $woody_lang_enable);

            $form->addCheckbox($language->slug, $language->name)
                ->setDefaultValue($enable);
        }

        $form->addSubmit('import', 'Activer les langues')
            ->setHtmlAttribute('class', 'button button-primary');

        if ($form->isSuccess()) {
            $options = [];
            foreach ($form->getValues() as $lang => $bool) {
                if ($bool) {
                    $options[] = $lang;
                }
            }

            update_option('woody_lang_enable', $options);
        }

        break;

    case 'seasons_lang':
        $woody_lang_seasons = get_option('woody_lang_seasons', []);
        $woody_season_priority = get_option('woody_season_priority', '');

        $form->addGroup('Saisonnalité des langues');
        foreach ($languages as $language) {
            $season = array_key_exists($language->slug, $woody_lang_seasons) ? $woody_lang_seasons[$language->slug] : 'default';

            $seasons_choices = [
                'default' => 'Pas de saison',
                'hiver' => 'Hiver',
                'ete' => 'Été',
            ];

            if (substr($language->locale, 3, 2) == 'SP') {
                $seasons_choices[$language->slug] = $language->name;
            }

            $form->addRadioList($language->slug, $language->name . ' : ', $seasons_choices)
                ->setDefaultValue($season)
                ->getSeparatorPrototype()->setName('');
        }

        if (empty($woody_season_priority)) {
            $woody_season_priority = 'default';
        }

        $form->addGroup('Langue prioritaire pour le calcul des canoniques');
        $form->addRadioList('priority', 'Saison prioritaire : ', [
            'default' => "Le site n'a pas de saison",
            'hiver' => 'Hiver',
            'ete' => 'Été',
        ])
            ->setDefaultValue($woody_season_priority)
            ->getSeparatorPrototype()->setName('');

        $form->addSubmit('import', 'Enregistrer')
            ->setHtmlAttribute('class', 'button button-primary');

        if ($form->isSuccess()) {
            $options = (array)$form->getValues();
            $priority = $options['priority'];
            unset($options['priority']);

            update_option('woody_season_priority', $priority);
            update_option('woody_lang_seasons', $options);
        }

        break;

    case 'hawwwai_lang':
        $woody_hawwwai_lang_disable = get_option('woody_hawwwai_lang_disable', []);

        foreach ($languages as $language) {
            if ($language->slug == PLL_DEFAULT_LANG) {
                continue;
            }

            $enable = in_array($language->slug, $woody_hawwwai_lang_disable);

            $form->addCheckbox($language->slug, $language->name)
                ->setDefaultValue($enable);
        }

        $form->addSubmit('import', 'Désactiver les langues')
            ->setHtmlAttribute('class', 'button button-primary');

        if ($form->isSuccess()) {
            $options = [];
            foreach ($form->getValues() as $lang => $bool) {
                if ($bool) {
                    $options[] = $lang;
                }
            }

            update_option('woody_hawwwai_lang_disable', $options);
        }

        break;

    case 'youbook_lang':
        $woody_youbook_lang_disable = get_option('woody_youbook_lang_disable', []);
        foreach ($languages as $language) {
            if ($language->slug == PLL_DEFAULT_LANG) {
                continue;
            }

            $enable = in_array($language->slug, $woody_youbook_lang_disable);

            $form->addCheckbox($language->slug, $language->name)
                ->setDefaultValue($enable);
        }

        $form->addSubmit('import', 'Désactiver les langues')
            ->setHtmlAttribute('class', 'button button-primary');

        if ($form->isSuccess()) {
            $options = [];
            foreach ($form->getValues() as $lang => $bool) {
                if ($bool) {
                    $options[] = $lang;
                }
            }

            update_option('woody_youbook_lang_disable', $options);
        }

        break;

    case 'usage_lang':
        $meta_lang_usages_options = get_option('meta_lang_usages');
        $meta_lang_usages = apply_filters('meta_lang_usages', [
            'page' => 'Page'
        ]);
        foreach ($languages as $language) {
            $form->addGroup($language->name);
            foreach ($meta_lang_usages as $meta_lang_usage_key => $meta_lang_usage) {
                $form->addCheckbox($language->slug.'_'.$meta_lang_usage_key, $meta_lang_usage)
                    ->setDefaultValue(!empty($meta_lang_usages_options[$language->slug]) && in_array($meta_lang_usage_key, $meta_lang_usages_options[$language->slug]));
            }
        }

        $form->addSubmit('save', 'Enregistrer')
            ->setHtmlAttribute('class', 'button button-primary');

        if ($form->isSuccess()) {
            $options = [];
            foreach ($form->getValues() as $lang_usage => $lang_usage_value) {
                [$lang, $usage] = explode('_', $lang_usage);
                if ($lang_usage_value) {
                    $options[$lang][] = $usage;
                } elseif (!isset($options[$lang])) {
                    $options[$lang] = [];
                }
            }

            $update = apply_filters('allow_update_meta_lang_usages', ['status' => true], $options);

            if ($update['status']) {
                update_option('meta_lang_usages', $options);
                do_action('update_meta_lang_usages', $options);
            } else {
                $form->addError($update['message']);
            }
        }

        break;

    default:
        foreach ($custom_tabs as $tab_slug => $tab_title) {
            if ($active_tab == $tab_slug) {
                $form = apply_filters(sprintf('custom_tab_form_%s', $tab_slug), $form);
            }
        }

        break;
}

require_once(WOODY_LIB_POLYLANG_DIR_ROOT . '/Resources/Admin/admin.php');
