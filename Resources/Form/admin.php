<?php
/**
 * Woody Polylang
 * @author      Leo POIROUX
 * @copyright   2019 Raccourci Agency
 */

use Nette\Forms\Form;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

// Tabs
if (!empty(filter_input(INPUT_GET, 'tab'))) {
    $active_tab = filter_input(INPUT_GET, 'tab');
} else {
    $active_tab = 'enable_lang';
}

// https://doc.nette.org/en/2.4/forms
$form = new Form;

switch ($active_tab) {
    case 'enable_lang':
        $woody_lang_enable = get_option('woody_lang_enable', []);

        foreach ($languages as $language) {
            if (in_array($language->slug, $woody_lang_enable)) {
                $enable = true;
            } else {
                $enable = false;
            }

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
            if (array_key_exists($language->slug, $woody_lang_seasons)) {
                $season = $woody_lang_seasons[$language->slug];
            } else {
                $season = 'default';
            }

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
            'default' => 'Le site n\'a pas de saison',
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

            if (in_array($language->slug, $woody_hawwwai_lang_disable)) {
                $enable = true;
            } else {
                $enable = false;
            }

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
}

require_once(WOODY_ADDON_POLYLANG_DIR_ROOT . '/Resources/Admin/admin.php');
