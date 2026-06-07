<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/InternautenGraphMailer.php';

class InternautenGraph extends Module
{
    const CONF_ENABLED = 'INTERNAUTENGRAPH_ENABLED';
    const CONF_TENANT_ID = 'INTERNAUTENGRAPH_TENANT_ID';
    const CONF_CLIENT_ID = 'INTERNAUTENGRAPH_CLIENT_ID';
    const CONF_CLIENT_SECRET = 'INTERNAUTENGRAPH_CLIENT_SECRET';
    const CONF_SENDER_MAILBOX = 'INTERNAUTENGRAPH_SENDER_MAILBOX';
    const CONF_TEST_RECIPIENT = 'INTERNAUTENGRAPH_TEST_RECIPIENT';
    const CONF_LAST_ERROR = 'INTERNAUTENGRAPH_LAST_ERROR';
    const CONF_DEBUG_TEMPLATE_VARS = 'INTERNAUTENGRAPH_DEBUG_TEMPLATE_VARS';

    public function __construct()
    {
        $this->name = 'internautengraph';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'die.internauten.ch GmbH';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = array(
            'min' => '1.7.0.0',
            'max' => _PS_VERSION_,
        );

        parent::__construct();

        $this->displayName = $this->l('Internauten Graph Mail');
        $this->description = $this->l('Overrides PrestaShop mail sending and uses Microsoft Graph API for Office 365.');
    }

    public function install()
    {
        return parent::install()
            && Configuration::updateValue(self::CONF_ENABLED, 0)
            && Configuration::updateValue(self::CONF_TENANT_ID, '')
            && Configuration::updateValue(self::CONF_CLIENT_ID, '')
            && Configuration::updateValue(self::CONF_CLIENT_SECRET, '')
            && Configuration::updateValue(self::CONF_SENDER_MAILBOX, '')
            && Configuration::updateValue(self::CONF_TEST_RECIPIENT, '')
            && Configuration::updateValue(self::CONF_LAST_ERROR, '')
            && Configuration::updateValue(self::CONF_DEBUG_TEMPLATE_VARS, 0)
            && $this->installOverrides();
    }

    public function uninstall()
    {
        $overridesRemoved = $this->uninstallOverrides();
        $this->logUninstallInfo(
            $overridesRemoved
                ? 'uninstallOverrides removed module overrides successfully.'
                : 'uninstallOverrides returned false; trying manual override cleanup.'
        );

        $manualOverrideRemoved = $this->removeInstalledMailOverrideIfOwnedByModule();
        $this->logUninstallInfo(
            $manualOverrideRemoved
                ? 'Manual cleanup removed override/classes/Mail.php owned by module.'
                : 'Manual cleanup did not remove override/classes/Mail.php (file missing or not owned by module).'
        );

        if ($overridesRemoved || $manualOverrideRemoved) {
            $this->refreshClassIndexCache();
            $this->logUninstallInfo('Class index/cache refreshed after override cleanup.');
        }

        return $overridesRemoved
            && Configuration::deleteByName(self::CONF_ENABLED)
            && Configuration::deleteByName(self::CONF_TENANT_ID)
            && Configuration::deleteByName(self::CONF_CLIENT_ID)
            && Configuration::deleteByName(self::CONF_CLIENT_SECRET)
            && Configuration::deleteByName(self::CONF_SENDER_MAILBOX)
            && Configuration::deleteByName(self::CONF_TEST_RECIPIENT)
            && Configuration::deleteByName(self::CONF_LAST_ERROR)
            && Configuration::deleteByName(self::CONF_DEBUG_TEMPLATE_VARS)
            && parent::uninstall();
    }

    private function removeInstalledMailOverrideIfOwnedByModule()
    {
        if (!defined('_PS_ROOT_DIR_')) {
            return false;
        }

        $overridePath = _PS_ROOT_DIR_ . '/override/classes/Mail.php';
        if (!is_file($overridePath) || !is_readable($overridePath)) {
            return false;
        }

        $content = (string) Tools::file_get_contents($overridePath);
        if ($content === '') {
            return false;
        }

        $isOwnedByModule = strpos($content, 'InternautenGraphMailer') !== false
            && strpos($content, 'internautengraph') !== false;

        if (!$isOwnedByModule) {
            return false;
        }

        return @unlink($overridePath);
    }

    private function refreshClassIndexCache()
    {
        if (class_exists('PrestaShopAutoload') && method_exists('PrestaShopAutoload', 'getInstance')) {
            $autoload = PrestaShopAutoload::getInstance();
            if (is_object($autoload) && method_exists($autoload, 'generateIndex')) {
                $autoload->generateIndex();
            }
        }

        if (class_exists('Tools') && method_exists('Tools', 'clearSf2Cache')) {
            Tools::clearSf2Cache();
        }
    }

    private function logUninstallInfo($message)
    {
        if (!class_exists('PrestaShopLogger')) {
            return;
        }

        PrestaShopLogger::addLog('internautengraph uninstall: ' . (string) $message, 1);
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitInternautenGraphTest')) {
            $testRecipient = trim((string) Tools::getValue(self::CONF_TEST_RECIPIENT));
            Configuration::updateValue(self::CONF_TEST_RECIPIENT, $testRecipient);

            if ($testRecipient === '' || !Validate::isEmail($testRecipient)) {
                $output .= $this->displayError($this->l('Please enter a valid test recipient email address.'));
            } elseif (!self::shouldUseGraph()) {
                $output .= $this->displayError($this->l('Graph sending is not ready. Please enable Graph and complete all Office 365 fields first.'));
            } else {
                $sent = InternautenGraphMailer::sendTestMail($testRecipient);
                if ($sent) {
                    $output .= $this->displayConfirmation($this->l('Test email sent successfully using Microsoft Graph.'));
                } else {
                    $detail = trim((string) InternautenGraphMailer::getLastError());
                    $message = $this->l('Test email could not be sent via Microsoft Graph. Please verify app permissions and mailbox settings.');
                    if ($detail !== '') {
                        $message .= ' ' . $this->l('Error details:') . ' ' . $detail;
                    }
                    $output .= $this->displayError($message);
                }
            }
        }

        if (Tools::isSubmit('submitInternautenGraph')) {
            $enabled = (int) Tools::getValue(self::CONF_ENABLED, 0);
            $tenantId = trim((string) Tools::getValue(self::CONF_TENANT_ID));
            $clientId = trim((string) Tools::getValue(self::CONF_CLIENT_ID));
            $postedClientSecret = trim((string) Tools::getValue(self::CONF_CLIENT_SECRET));
            $existingClientSecret = (string) Configuration::get(self::CONF_CLIENT_SECRET);
            $clientSecret = $postedClientSecret !== '' ? $postedClientSecret : $existingClientSecret;
            $senderMailbox = trim((string) Tools::getValue(self::CONF_SENDER_MAILBOX));
            $debugTemplateVars = (int) Tools::getValue(self::CONF_DEBUG_TEMPLATE_VARS, 0);

            if ($enabled && ($tenantId === '' || $clientId === '' || $clientSecret === '' || $senderMailbox === '')) {
                $output .= $this->displayError($this->l('All Office 365 fields are required when Graph sending is enabled.'));
            } else {
                Configuration::updateValue(self::CONF_ENABLED, $enabled);
                Configuration::updateValue(self::CONF_TENANT_ID, $tenantId);
                Configuration::updateValue(self::CONF_CLIENT_ID, $clientId);
                Configuration::updateValue(self::CONF_CLIENT_SECRET, $clientSecret);
                Configuration::updateValue(self::CONF_SENDER_MAILBOX, $senderMailbox);
                Configuration::updateValue(self::CONF_DEBUG_TEMPLATE_VARS, $debugTemplateVars);
                $output .= $this->displayConfirmation($this->l('Settings updated.'));
            }
        }

        return $output . $this->renderLastErrorPanel() . $this->renderForm() . $this->renderTestMailForm();
    }

    protected function renderLastErrorPanel()
    {
        $lastError = trim((string) Configuration::get(self::CONF_LAST_ERROR));
        if ($lastError === '') {
            return '';
        }

        return $this->displayWarning(
            $this->l('Last Microsoft Graph error:') . ' ' . Tools::safeOutput($lastError)
        );
    }

    protected function renderForm()
    {
        $hasStoredClientSecret = trim((string) Configuration::get(self::CONF_CLIENT_SECRET)) !== '';

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitInternautenGraph';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name
            . '&tab_module=' . $this->tab
            . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->fields_value = array(
            self::CONF_ENABLED => (int) Configuration::get(self::CONF_ENABLED, 0),
            self::CONF_TENANT_ID => (string) Configuration::get(self::CONF_TENANT_ID),
            self::CONF_CLIENT_ID => (string) Configuration::get(self::CONF_CLIENT_ID),
            self::CONF_CLIENT_SECRET => (string) Configuration::get(self::CONF_CLIENT_SECRET),
            self::CONF_SENDER_MAILBOX => (string) Configuration::get(self::CONF_SENDER_MAILBOX),
            self::CONF_DEBUG_TEMPLATE_VARS => (int) Configuration::get(self::CONF_DEBUG_TEMPLATE_VARS, 0),
        );

        $fieldsForm = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Microsoft Graph mail settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enable Graph sending'),
                        'name' => self::CONF_ENABLED,
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => self::CONF_ENABLED . '_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ),
                            array(
                                'id' => self::CONF_ENABLED . '_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ),
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Tenant ID'),
                        'name' => self::CONF_TENANT_ID,
                        'required' => false,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Client ID'),
                        'name' => self::CONF_CLIENT_ID,
                        'required' => false,
                    ),
                    array(
                        'type' => 'password',
                        'label' => $this->l('Client Secret'),
                        'name' => self::CONF_CLIENT_SECRET,
                        'required' => false,
                        'autocomplete' => 'off',
                        'desc' => $hasStoredClientSecret
                            ? $this->l('A client secret is already stored. Leave empty to keep it unchanged.')
                            : $this->l('No client secret is stored yet. Enter a value to enable Graph sending.'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Sender mailbox'),
                        'name' => self::CONF_SENDER_MAILBOX,
                        'required' => false,
                        'desc' => $this->l('Office 365 user mailbox used for Graph /users/{mailbox}/sendMail, for example shop@example.com'),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Debug template variables'),
                        'name' => self::CONF_DEBUG_TEMPLATE_VARS,
                        'is_bool' => true,
                        'desc' => $this->l('Logs Graph template variable keys and unresolved placeholders to PrestaShop logs. Disable in production unless troubleshooting.'),
                        'values' => array(
                            array(
                                'id' => self::CONF_DEBUG_TEMPLATE_VARS . '_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ),
                            array(
                                'id' => self::CONF_DEBUG_TEMPLATE_VARS . '_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ),
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );

        return $helper->generateForm(array($fieldsForm));
    }

    protected function renderTestMailForm()
    {
        $action = $this->context->link->getAdminLink('AdminModules', true)
            . '&configure=' . $this->name
            . '&tab_module=' . $this->tab
            . '&module_name=' . $this->name;

        $recipient = Tools::safeOutput((string) Configuration::get(self::CONF_TEST_RECIPIENT));

        return '
            <div class="panel">
                <h3><i class="icon-envelope"></i> ' . $this->l('Send test email') . '</h3>
                <p>' . $this->l('Use this form to validate your Microsoft Graph configuration directly from the module settings.') . '</p>
                <form method="post" action="' . $action . '">
                    <div class="form-group">
                        <label class="control-label" for="internautengraph_test_recipient">' . $this->l('Test recipient email') . '</label>
                        <input id="internautengraph_test_recipient" class="form-control" type="email" name="' . self::CONF_TEST_RECIPIENT . '" value="' . $recipient . '" required>
                    </div>
                    <button type="submit" class="btn btn-primary" name="submitInternautenGraphTest" value="1">
                        <i class="icon-send"></i> ' . $this->l('Send test email') . '
                    </button>
                </form>
            </div>';
    }

    public static function shouldUseGraph()
    {
        if (!Module::isInstalled('internautengraph')) {
            return false;
        }

        if ((int) Configuration::get(self::CONF_ENABLED, 0) !== 1) {
            return false;
        }

        $config = self::getGraphConfig();

        return $config['tenant_id'] !== ''
            && $config['client_id'] !== ''
            && $config['client_secret'] !== ''
            && $config['sender_mailbox'] !== '';
    }

    public static function getGraphConfig()
    {
        return array(
            'tenant_id' => trim((string) Configuration::get(self::CONF_TENANT_ID)),
            'client_id' => trim((string) Configuration::get(self::CONF_CLIENT_ID)),
            'client_secret' => (string) Configuration::get(self::CONF_CLIENT_SECRET),
            'sender_mailbox' => trim((string) Configuration::get(self::CONF_SENDER_MAILBOX)),
        );
    }

    public static function isTemplateVarDebugEnabled()
    {
        return (int) Configuration::get(self::CONF_DEBUG_TEMPLATE_VARS, 0) === 1;
    }
}
