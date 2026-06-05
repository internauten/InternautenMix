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
            && $this->installOverrides();
    }

    public function uninstall()
    {
        return $this->uninstallOverrides()
            && Configuration::deleteByName(self::CONF_ENABLED)
            && Configuration::deleteByName(self::CONF_TENANT_ID)
            && Configuration::deleteByName(self::CONF_CLIENT_ID)
            && Configuration::deleteByName(self::CONF_CLIENT_SECRET)
            && Configuration::deleteByName(self::CONF_SENDER_MAILBOX)
            && Configuration::deleteByName(self::CONF_TEST_RECIPIENT)
            && Configuration::deleteByName(self::CONF_LAST_ERROR)
            && parent::uninstall();
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
            $clientSecret = trim((string) Tools::getValue(self::CONF_CLIENT_SECRET));
            $senderMailbox = trim((string) Tools::getValue(self::CONF_SENDER_MAILBOX));

            if ($enabled && ($tenantId === '' || $clientId === '' || $clientSecret === '' || $senderMailbox === '')) {
                $output .= $this->displayError($this->l('All Office 365 fields are required when Graph sending is enabled.'));
            } else {
                Configuration::updateValue(self::CONF_ENABLED, $enabled);
                Configuration::updateValue(self::CONF_TENANT_ID, $tenantId);
                Configuration::updateValue(self::CONF_CLIENT_ID, $clientId);
                Configuration::updateValue(self::CONF_CLIENT_SECRET, $clientSecret);
                Configuration::updateValue(self::CONF_SENDER_MAILBOX, $senderMailbox);
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
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Sender mailbox'),
                        'name' => self::CONF_SENDER_MAILBOX,
                        'required' => false,
                        'desc' => $this->l('Office 365 user mailbox used for Graph /users/{mailbox}/sendMail, for example shop@example.com'),
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
}
