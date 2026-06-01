<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/InternautenChSwissLocaleDataSourceDecorator.php';

class Internautench extends Module
{
    private const CONFIG_ENABLED = 'INTERNAUTENCH_ENABLED';
    private const CONFIG_LOCALES = 'INTERNAUTENCH_LOCALES';
    private const CONFIG_DECIMAL_SEPARATOR = 'INTERNAUTENCH_DECIMAL_SEPARATOR';
    private const CONFIG_GROUP_SEPARATOR = 'INTERNAUTENCH_GROUP_SEPARATOR';

    public function __construct()
    {
        $this->name = 'internautench';
        $this->tab = 'administration';
        $this->version = '1.1.0';
        $this->author = 'Internauten';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = [
            'min' => '1.7.8.0',
            'max' => _PS_VERSION_,
        ];

        parent::__construct();

        $this->displayName = $this->trans('Internauten CH Number Format', [], 'Modules.Internautench.Admin');
        $this->description = $this->trans(
            'Formats numbers and prices for Swiss locales with apostrophe grouping and a dot as decimal separator.',
            [],
            'Modules.Internautench.Admin'
        );
    }

    public function install()
    {
        $installed = parent::install()
            && Configuration::updateValue(self::CONFIG_ENABLED, 1)
            && Configuration::updateValue(self::CONFIG_LOCALES, 'de-CH,fr-CH,it-CH,rm-CH')
            && Configuration::updateValue(self::CONFIG_DECIMAL_SEPARATOR, '.')
            && Configuration::updateValue(self::CONFIG_GROUP_SEPARATOR, "'");

        if ($installed) {
            $this->clearLocalizationCaches();
        }

        return $installed;
    }

    public function uninstall()
    {
        $uninstalled = Configuration::deleteByName(self::CONFIG_ENABLED)
            && Configuration::deleteByName(self::CONFIG_LOCALES)
            && Configuration::deleteByName(self::CONFIG_DECIMAL_SEPARATOR)
            && Configuration::deleteByName(self::CONFIG_GROUP_SEPARATOR)
            && parent::uninstall();

        if ($uninstalled) {
            $this->clearLocalizationCaches();
        }

        return $uninstalled;
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitInternautench')) {
            $output .= $this->handleConfigurationSubmit();
        }

        return $output . $this->renderConfigurationForm();
    }

    private function handleConfigurationSubmit()
    {
        $enabled = (int) Tools::getValue(self::CONFIG_ENABLED, 1);
        $locales = (string) Tools::getValue(self::CONFIG_LOCALES, 'de-CH,fr-CH,it-CH,rm-CH');
        $decimalSeparator = (string) Tools::getValue(self::CONFIG_DECIMAL_SEPARATOR, '.');
        $groupSeparator = (string) Tools::getValue(self::CONFIG_GROUP_SEPARATOR, "'");

        $errors = $this->validateConfiguration($locales, $decimalSeparator, $groupSeparator);

        if (!empty($errors)) {
            return $this->displayError(implode(' ', $errors));
        }

        $updated = Configuration::updateValue(self::CONFIG_ENABLED, $enabled)
            && Configuration::updateValue(self::CONFIG_LOCALES, $this->normalizeLocales($locales))
            && Configuration::updateValue(self::CONFIG_DECIMAL_SEPARATOR, $decimalSeparator)
            && Configuration::updateValue(self::CONFIG_GROUP_SEPARATOR, $groupSeparator);

        if ($updated) {
            $this->clearLocalizationCaches();

            return $this->displayConfirmation(
                $this->trans('Configuration saved.', [], 'Admin.Notifications.Success')
            );
        }

        return $this->displayError(
            $this->trans('Configuration could not be saved.', [], 'Admin.Notifications.Error')
        );
    }

    private function renderConfigurationForm()
    {
        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->submit_action = 'submitInternautench';
        $helper->fields_value = $this->getConfigurationValues();

        return $helper->generateForm([
            [
                'form' => [
                    'legend' => [
                        'title' => $this->trans('Swiss number formatting', [], 'Modules.Internautench.Admin'),
                        'icon' => 'icon-cogs',
                    ],
                    'input' => [
                        [
                            'type' => 'switch',
                            'label' => $this->trans('Enable formatting', [], 'Modules.Internautench.Admin'),
                            'name' => self::CONFIG_ENABLED,
                            'is_bool' => true,
                            'values' => [
                                [
                                    'id' => self::CONFIG_ENABLED . '_on',
                                    'value' => 1,
                                    'label' => $this->trans('Yes', [], 'Admin.Global'),
                                ],
                                [
                                    'id' => self::CONFIG_ENABLED . '_off',
                                    'value' => 0,
                                    'label' => $this->trans('No', [], 'Admin.Global'),
                                ],
                            ],
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->trans('Locales', [], 'Modules.Internautench.Admin'),
                            'name' => self::CONFIG_LOCALES,
                            'class' => 'fixed-width-xxl',
                            'desc' => $this->trans('Comma-separated locale list, for example de-CH,fr-CH,it-CH.', [], 'Modules.Internautench.Admin'),
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->trans('Decimal separator', [], 'Modules.Internautench.Admin'),
                            'name' => self::CONFIG_DECIMAL_SEPARATOR,
                            'class' => 'fixed-width-sm',
                            'maxlength' => 1,
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->trans('Thousands separator', [], 'Modules.Internautench.Admin'),
                            'name' => self::CONFIG_GROUP_SEPARATOR,
                            'class' => 'fixed-width-sm',
                            'maxlength' => 1,
                        ],
                    ],
                    'submit' => [
                        'title' => $this->trans('Save', [], 'Admin.Actions'),
                    ],
                ],
            ],
        ]);
    }

    private function getConfigurationValues()
    {
        return [
            self::CONFIG_ENABLED => (int) Configuration::get(self::CONFIG_ENABLED, 1),
            self::CONFIG_LOCALES => (string) Configuration::get(self::CONFIG_LOCALES, 'de-CH,fr-CH,it-CH,rm-CH'),
            self::CONFIG_DECIMAL_SEPARATOR => (string) Configuration::get(self::CONFIG_DECIMAL_SEPARATOR, '.'),
            self::CONFIG_GROUP_SEPARATOR => (string) Configuration::get(self::CONFIG_GROUP_SEPARATOR, "'"),
        ];
    }

    private function validateConfiguration($locales, $decimalSeparator, $groupSeparator)
    {
        $errors = [];
        $normalizedLocales = $this->normalizeLocales($locales);

        if ('' === $normalizedLocales) {
            $errors[] = $this->trans('Please provide at least one locale.', [], 'Modules.Internautench.Admin');
        }

        if (1 !== Tools::strlen($decimalSeparator)) {
            $errors[] = $this->trans('The decimal separator must contain exactly one character.', [], 'Modules.Internautench.Admin');
        }

        if (1 !== Tools::strlen($groupSeparator)) {
            $errors[] = $this->trans('The thousands separator must contain exactly one character.', [], 'Modules.Internautench.Admin');
        }

        if ('' !== $normalizedLocales) {
            foreach (explode(',', $normalizedLocales) as $locale) {
                if (1 !== preg_match('/^[a-z]{2,3}-[a-z]{2}$/i', $locale)) {
                    $errors[] = $this->trans('Locales must use the format ll-CC, for example de-CH.', [], 'Modules.Internautench.Admin');
                    break;
                }
            }
        }

        if ($decimalSeparator === $groupSeparator) {
            $errors[] = $this->trans('Decimal and thousands separator must be different.', [], 'Modules.Internautench.Admin');
        }

        return $errors;
    }

    private function normalizeLocales($locales)
    {
        $parts = array_filter(array_map('trim', explode(',', (string) $locales)));
        $normalized = [];

        foreach ($parts as $part) {
            $segments = preg_split('/[-_]/', $part);
            if (2 !== count($segments)) {
                continue;
            }

            $normalized[] = Tools::strtolower($segments[0]) . '-' . Tools::strtoupper($segments[1]);
        }

        return implode(',', array_values(array_unique($normalized)));
    }

    private function clearLocalizationCaches()
    {
        if (class_exists('Cache')) {
            Cache::clean('*');
        }

        if (class_exists('Tools') && method_exists('Tools', 'clearSf2Cache')) {
            Tools::clearSf2Cache();
        }
    }
}