<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/InternautenGraphMailer.php';

class InternautenGraph extends Module
{
    const TRANSPORT_GRAPH = 'graph';
    const TRANSPORT_SMTP_STARTTLS = 'smtp_starttls';

    const CONF_ENABLED = 'INTERNAUTENGRAPH_ENABLED';
    const CONF_TRANSPORT_MODE = 'INTERNAUTENGRAPH_TRANSPORT_MODE';
    const CONF_TENANT_ID = 'INTERNAUTENGRAPH_TENANT_ID';
    const CONF_CLIENT_ID = 'INTERNAUTENGRAPH_CLIENT_ID';
    const CONF_CLIENT_SECRET = 'INTERNAUTENGRAPH_CLIENT_SECRET';
    const CONF_SENDER_MAILBOX = 'INTERNAUTENGRAPH_SENDER_MAILBOX';
    const CONF_SMTP_HOST = 'INTERNAUTENGRAPH_SMTP_HOST';
    const CONF_SMTP_PORT = 'INTERNAUTENGRAPH_SMTP_PORT';
    const CONF_SMTP_USERNAME = 'INTERNAUTENGRAPH_SMTP_USERNAME';
    const CONF_SMTP_PASSWORD = 'INTERNAUTENGRAPH_SMTP_PASSWORD';
    const CONF_SMTP_SENDER_EMAIL = 'INTERNAUTENGRAPH_SMTP_SENDER_EMAIL';
    const CONF_SMTP_SENDER_NAME = 'INTERNAUTENGRAPH_SMTP_SENDER_NAME';
    const CONF_TEST_RECIPIENT = 'INTERNAUTENGRAPH_TEST_RECIPIENT';
    const CONF_LAST_ERROR = 'INTERNAUTENGRAPH_LAST_ERROR';
    const CONF_DEBUG_TEMPLATE_VARS = 'INTERNAUTENGRAPH_DEBUG_TEMPLATE_VARS';

    public function __construct()
    {
        $this->name = 'internautengraph';
        $this->tab = 'administration';
        $this->version = '2.0.1';
        $this->author = 'die.internauten.ch GmbH';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = array(
            'min' => '1.7.0.0',
            'max' => _PS_VERSION_,
        );

        parent::__construct();

        $this->displayName = $this->l('Internauten Graph Mail');
        $this->description = $this->l('Routes PrestaShop mail sending through Microsoft Graph API or SMTP STARTTLS.');
    }

    public function install()
    {
        $installed = parent::install()
            && $this->registerHook('actionEmailSendBefore')
            && Configuration::updateValue(self::CONF_ENABLED, 0)
            && Configuration::updateValue(self::CONF_TRANSPORT_MODE, self::TRANSPORT_GRAPH)
            && Configuration::updateValue(self::CONF_TENANT_ID, '')
            && Configuration::updateValue(self::CONF_CLIENT_ID, '')
            && Configuration::updateValue(self::CONF_CLIENT_SECRET, '')
            && Configuration::updateValue(self::CONF_SENDER_MAILBOX, '')
            && Configuration::updateValue(self::CONF_SMTP_HOST, '')
            && Configuration::updateValue(self::CONF_SMTP_PORT, 587)
            && Configuration::updateValue(self::CONF_SMTP_USERNAME, '')
            && Configuration::updateValue(self::CONF_SMTP_PASSWORD, '')
            && Configuration::updateValue(self::CONF_SMTP_SENDER_EMAIL, '')
            && Configuration::updateValue(self::CONF_SMTP_SENDER_NAME, '')
            && Configuration::updateValue(self::CONF_TEST_RECIPIENT, '')
            && Configuration::updateValue(self::CONF_LAST_ERROR, '')
            && Configuration::updateValue(self::CONF_DEBUG_TEMPLATE_VARS, 0);

        if (!$installed) {
            return false;
        }

        // Migration path: remove old override installations from previous module versions.
        $manualOverrideRemoved = $this->removeInstalledMailOverrideIfOwnedByModule();
        if ($manualOverrideRemoved) {
            $this->refreshClassIndexCache();
            $this->logLifecycleInfo('install: removed legacy Mail override and refreshed class index/cache.');
        }

        return true;
    }

    public function uninstall()
    {
        $manualOverrideRemoved = $this->removeInstalledMailOverrideIfOwnedByModule();
        $this->logLifecycleInfo(
            $manualOverrideRemoved
                ? 'Manual cleanup removed override/classes/Mail.php owned by module.'
                : 'Manual cleanup did not remove override/classes/Mail.php (file missing or not owned by module).'
        );

        if ($manualOverrideRemoved) {
            $this->refreshClassIndexCache();
            $this->logLifecycleInfo('Class index/cache refreshed after override cleanup.');
        }

        return Configuration::deleteByName(self::CONF_ENABLED)
            && Configuration::deleteByName(self::CONF_TRANSPORT_MODE)
            && Configuration::deleteByName(self::CONF_TENANT_ID)
            && Configuration::deleteByName(self::CONF_CLIENT_ID)
            && Configuration::deleteByName(self::CONF_CLIENT_SECRET)
            && Configuration::deleteByName(self::CONF_SENDER_MAILBOX)
            && Configuration::deleteByName(self::CONF_SMTP_HOST)
            && Configuration::deleteByName(self::CONF_SMTP_PORT)
            && Configuration::deleteByName(self::CONF_SMTP_USERNAME)
            && Configuration::deleteByName(self::CONF_SMTP_PASSWORD)
            && Configuration::deleteByName(self::CONF_SMTP_SENDER_EMAIL)
            && Configuration::deleteByName(self::CONF_SMTP_SENDER_NAME)
            && Configuration::deleteByName(self::CONF_TEST_RECIPIENT)
            && Configuration::deleteByName(self::CONF_LAST_ERROR)
            && Configuration::deleteByName(self::CONF_DEBUG_TEMPLATE_VARS)
            && parent::uninstall();
    }

    public function hookActionEmailSendBefore($params)
    {
        if (!is_array($params)) {
            return true;
        }

        if (!self::shouldInterceptMailSending()) {
            return true;
        }

        $sentWithGraph = InternautenGraphMailer::send(
            isset($params['idLang']) ? $params['idLang'] : 0,
            isset($params['template']) ? $params['template'] : '',
            isset($params['subject']) ? $params['subject'] : '',
            isset($params['templateVars']) && is_array($params['templateVars']) ? $params['templateVars'] : array(),
            isset($params['to']) ? $params['to'] : '',
            isset($params['toName']) ? $params['toName'] : null,
            isset($params['from']) ? $params['from'] : null,
            isset($params['fromName']) ? $params['fromName'] : null,
            isset($params['fileAttachment']) ? $params['fileAttachment'] : null,
            isset($params['templatePath']) ? $params['templatePath'] : _PS_MAIL_DIR_,
            isset($params['idShop']) ? $params['idShop'] : null,
            isset($params['bcc']) ? $params['bcc'] : null,
            isset($params['replyTo']) ? $params['replyTo'] : null,
            isset($params['replyToName']) ? $params['replyToName'] : null
        );

        if ($sentWithGraph) {
            $this->logLifecycleInfo('hook: email sent with configured custom transport, native Mail::Send skipped.');

            // Returning false tells Mail::Send to stop and not send natively.
            return false;
        }

        $this->logLifecycleInfo('hook: custom transport send failed, native Mail::Send continues as fallback.');

        return true;
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

    private function logLifecycleInfo($message)
    {
        if (!class_exists('PrestaShopLogger')) {
            return;
        }

        PrestaShopLogger::addLog('internautengraph: ' . (string) $message, 1);
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitInternautenGraphTest')) {
            $testRecipient = trim((string) Tools::getValue(self::CONF_TEST_RECIPIENT));
            Configuration::updateValue(self::CONF_TEST_RECIPIENT, $testRecipient);

            if ($testRecipient === '' || !Validate::isEmail($testRecipient)) {
                $output .= $this->displayError($this->l('Please enter a valid test recipient email address.'));
            } elseif (!self::shouldInterceptMailSending()) {
                $output .= $this->displayError($this->l('Custom mail transport is not ready. Enable the module and complete all required fields for the selected transport first.'));
            } else {
                $sent = InternautenGraphMailer::sendTestMail($testRecipient);
                if ($sent) {
                    $output .= $this->displayConfirmation($this->l('Test email sent successfully using the selected transport.'));
                } else {
                    $detail = trim((string) InternautenGraphMailer::getLastError());
                    $message = $this->l('Test email could not be sent using the selected transport. Please verify your transport settings.');
                    if ($detail !== '') {
                        $message .= ' ' . $this->l('Error details:') . ' ' . $detail;
                    }
                    $output .= $this->displayError($message);
                }
            }
        }

        if (Tools::isSubmit('submitInternautenGraph')) {
            $enabled = (int) Tools::getValue(self::CONF_ENABLED, 0);
            $transportMode = trim((string) Tools::getValue(self::CONF_TRANSPORT_MODE, self::TRANSPORT_GRAPH));
            if (!in_array($transportMode, array(self::TRANSPORT_GRAPH, self::TRANSPORT_SMTP_STARTTLS), true)) {
                $transportMode = self::TRANSPORT_GRAPH;
            }

            $tenantId = trim((string) Tools::getValue(self::CONF_TENANT_ID));
            $clientId = trim((string) Tools::getValue(self::CONF_CLIENT_ID));
            $postedClientSecret = trim((string) Tools::getValue(self::CONF_CLIENT_SECRET));
            $existingClientSecret = (string) Configuration::get(self::CONF_CLIENT_SECRET);
            $clientSecret = $postedClientSecret !== '' ? $postedClientSecret : $existingClientSecret;
            $senderMailbox = trim((string) Tools::getValue(self::CONF_SENDER_MAILBOX));

            $smtpHost = trim((string) Tools::getValue(self::CONF_SMTP_HOST));
            $smtpPort = (int) Tools::getValue(self::CONF_SMTP_PORT, 587);
            if ($smtpPort <= 0) {
                $smtpPort = 587;
            }
            $smtpUsername = trim((string) Tools::getValue(self::CONF_SMTP_USERNAME));
            $postedSmtpPassword = trim((string) Tools::getValue(self::CONF_SMTP_PASSWORD));
            $existingSmtpPassword = (string) Configuration::get(self::CONF_SMTP_PASSWORD);
            $smtpPassword = $postedSmtpPassword !== '' ? $postedSmtpPassword : $existingSmtpPassword;
            $smtpSenderEmail = trim((string) Tools::getValue(self::CONF_SMTP_SENDER_EMAIL));
            $smtpSenderName = trim((string) Tools::getValue(self::CONF_SMTP_SENDER_NAME));
            $debugTemplateVars = (int) Tools::getValue(self::CONF_DEBUG_TEMPLATE_VARS, 0);

            if ($enabled && $transportMode === self::TRANSPORT_GRAPH && ($tenantId === '' || $clientId === '' || $clientSecret === '' || $senderMailbox === '')) {
                $output .= $this->displayError($this->l('All Office 365 fields are required when Graph transport is selected and enabled.'));
            } elseif ($enabled && $transportMode === self::TRANSPORT_SMTP_STARTTLS && ($smtpHost === '' || $smtpPort <= 0 || $smtpUsername === '' || $smtpPassword === '' || $smtpSenderEmail === '')) {
                $output .= $this->displayError($this->l('All SMTP STARTTLS fields except sender name are required when SMTP transport is selected and enabled.'));
            } elseif ($enabled && $transportMode === self::TRANSPORT_SMTP_STARTTLS && !Validate::isEmail($smtpSenderEmail)) {
                $output .= $this->displayError($this->l('Please provide a valid SMTP sender email address.'));
            } else {
                Configuration::updateValue(self::CONF_ENABLED, $enabled);
                Configuration::updateValue(self::CONF_TRANSPORT_MODE, $transportMode);
                Configuration::updateValue(self::CONF_TENANT_ID, $tenantId);
                Configuration::updateValue(self::CONF_CLIENT_ID, $clientId);
                Configuration::updateValue(self::CONF_CLIENT_SECRET, $clientSecret);
                Configuration::updateValue(self::CONF_SENDER_MAILBOX, $senderMailbox);
                Configuration::updateValue(self::CONF_SMTP_HOST, $smtpHost);
                Configuration::updateValue(self::CONF_SMTP_PORT, $smtpPort);
                Configuration::updateValue(self::CONF_SMTP_USERNAME, $smtpUsername);
                Configuration::updateValue(self::CONF_SMTP_PASSWORD, $smtpPassword);
                Configuration::updateValue(self::CONF_SMTP_SENDER_EMAIL, $smtpSenderEmail);
                Configuration::updateValue(self::CONF_SMTP_SENDER_NAME, $smtpSenderName);
                Configuration::updateValue(self::CONF_DEBUG_TEMPLATE_VARS, $debugTemplateVars);
                $output .= $this->displayConfirmation($this->l('Settings updated.'));
            }
        }

        return $output
            . $this->renderLastErrorPanel()
            . $this->renderForm()
            . $this->renderTransportVisibilityScript()
            . $this->renderTestMailForm();
    }

    protected function renderLastErrorPanel()
    {
        $lastError = trim((string) Configuration::get(self::CONF_LAST_ERROR));
        if ($lastError === '') {
            return '';
        }

        return $this->displayWarning(
            $this->l('Last custom transport error:') . ' ' . Tools::safeOutput($lastError)
        );
    }

    protected function renderForm()
    {
        $hasStoredClientSecret = trim((string) Configuration::get(self::CONF_CLIENT_SECRET)) !== '';
        $hasStoredSmtpPassword = trim((string) Configuration::get(self::CONF_SMTP_PASSWORD)) !== '';

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
            self::CONF_TRANSPORT_MODE => (string) Configuration::get(self::CONF_TRANSPORT_MODE, self::TRANSPORT_GRAPH),
            'internautengraph_section_graph' => '',
            'internautengraph_section_smtp' => '',
            self::CONF_TENANT_ID => (string) Configuration::get(self::CONF_TENANT_ID),
            self::CONF_CLIENT_ID => (string) Configuration::get(self::CONF_CLIENT_ID),
            self::CONF_CLIENT_SECRET => (string) Configuration::get(self::CONF_CLIENT_SECRET),
            self::CONF_SENDER_MAILBOX => (string) Configuration::get(self::CONF_SENDER_MAILBOX),
            self::CONF_SMTP_HOST => (string) Configuration::get(self::CONF_SMTP_HOST),
            self::CONF_SMTP_PORT => (int) Configuration::get(self::CONF_SMTP_PORT, 587),
            self::CONF_SMTP_USERNAME => (string) Configuration::get(self::CONF_SMTP_USERNAME),
            self::CONF_SMTP_PASSWORD => (string) Configuration::get(self::CONF_SMTP_PASSWORD),
            self::CONF_SMTP_SENDER_EMAIL => (string) Configuration::get(self::CONF_SMTP_SENDER_EMAIL),
            self::CONF_SMTP_SENDER_NAME => (string) Configuration::get(self::CONF_SMTP_SENDER_NAME),
            self::CONF_DEBUG_TEMPLATE_VARS => (int) Configuration::get(self::CONF_DEBUG_TEMPLATE_VARS, 0),
        );

        $fieldsForm = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Mail transport settings (Graph / SMTP STARTTLS)'),
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
                        'type' => 'select',
                        'label' => $this->l('Mail transport'),
                        'name' => self::CONF_TRANSPORT_MODE,
                        'options' => array(
                            'query' => array(
                                array(
                                    'id_option' => self::TRANSPORT_GRAPH,
                                    'name' => $this->l('Microsoft Graph API'),
                                ),
                                array(
                                    'id_option' => self::TRANSPORT_SMTP_STARTTLS,
                                    'name' => $this->l('SMTP with STARTTLS'),
                                ),
                            ),
                            'id' => 'id_option',
                            'name' => 'name',
                        ),
                        'desc' => $this->l('Select which external transport should be used when this module is enabled.'),
                    ),
                    array(
                        'type' => 'free',
                        'name' => 'internautengraph_section_graph',
                        'label' => '',
                        'desc' => '<div class="internautengraph-section-graph"><hr><h4 style="margin:0;">' . $this->l('Microsoft Graph API settings') . '</h4></div>',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Graph - Tenant ID'),
                        'name' => self::CONF_TENANT_ID,
                        'required' => false,
                        'desc' => $this->l('Microsoft Graph section: fill this field only when Mail transport is set to Microsoft Graph API.'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Graph - Client ID'),
                        'name' => self::CONF_CLIENT_ID,
                        'required' => false,
                    ),
                    array(
                        'type' => 'password',
                        'label' => $this->l('Graph - Client Secret'),
                        'name' => self::CONF_CLIENT_SECRET,
                        'required' => false,
                        'autocomplete' => 'off',
                        'desc' => $hasStoredClientSecret
                            ? $this->l('A client secret is already stored. Leave empty to keep it unchanged.')
                            : $this->l('No client secret is stored yet. Enter a value to enable Graph sending.'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Graph - Sender mailbox'),
                        'name' => self::CONF_SENDER_MAILBOX,
                        'required' => false,
                        'desc' => $this->l('Office 365 user mailbox used for Graph /users/{mailbox}/sendMail, for example shop@example.com'),
                    ),
                    array(
                        'type' => 'free',
                        'name' => 'internautengraph_section_smtp',
                        'label' => '',
                        'desc' => '<div class="internautengraph-section-smtp"><hr><h4 style="margin:0;">' . $this->l('SMTP STARTTLS settings') . '</h4></div>',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('STARTTLS - SMTP host'),
                        'name' => self::CONF_SMTP_HOST,
                        'required' => false,
                        'desc' => $this->l('SMTP STARTTLS section: fill this and the following SMTP fields only when Mail transport is set to SMTP with STARTTLS.'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('STARTTLS - SMTP port'),
                        'name' => self::CONF_SMTP_PORT,
                        'required' => false,
                        'desc' => $this->l('SMTP port for STARTTLS, usually 587.'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('STARTTLS - SMTP username'),
                        'name' => self::CONF_SMTP_USERNAME,
                        'required' => false,
                    ),
                    array(
                        'type' => 'password',
                        'label' => $this->l('STARTTLS - SMTP password'),
                        'name' => self::CONF_SMTP_PASSWORD,
                        'required' => false,
                        'autocomplete' => 'off',
                        'desc' => $hasStoredSmtpPassword
                            ? $this->l('An SMTP password is already stored. Leave empty to keep it unchanged.')
                            : $this->l('No SMTP password is stored yet. Enter a value to enable SMTP transport.'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('STARTTLS - SMTP sender email'),
                        'name' => self::CONF_SMTP_SENDER_EMAIL,
                        'required' => false,
                        'desc' => $this->l('Envelope and From address used for SMTP sending, for example shop@example.com.'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('STARTTLS - SMTP sender name'),
                        'name' => self::CONF_SMTP_SENDER_NAME,
                        'required' => false,
                        'desc' => $this->l('Optional display name for SMTP sender.'),
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
                <p>' . $this->l('Use this form to validate the currently selected transport directly from the module settings.') . '</p>
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

    protected function renderTransportVisibilityScript()
    {
        $transportField = self::CONF_TRANSPORT_MODE;

        $graphFields = array(
            self::CONF_TENANT_ID,
            self::CONF_CLIENT_ID,
            self::CONF_CLIENT_SECRET,
            self::CONF_SENDER_MAILBOX,
        );

        $smtpFields = array(
            self::CONF_SMTP_HOST,
            self::CONF_SMTP_PORT,
            self::CONF_SMTP_USERNAME,
            self::CONF_SMTP_PASSWORD,
            self::CONF_SMTP_SENDER_EMAIL,
            self::CONF_SMTP_SENDER_NAME,
        );

        return '<script>
            (function () {
                function findRowByName(name) {
                    var field = document.querySelector("[name=\"" + name + "\"]");
                    if (!field) {
                        return null;
                    }

                    var row = field.closest(".form-group");
                    return row ? row : null;
                }

                function toggleRows(fieldNames, isVisible) {
                    fieldNames.forEach(function (name) {
                        var row = findRowByName(name);
                        if (!row) {
                            return;
                        }

                        row.style.display = isVisible ? "" : "none";
                    });
                }

                function toggleRowsBySelector(selector, isVisible) {
                    var nodes = document.querySelectorAll(selector);
                    nodes.forEach(function (node) {
                        var row = node.closest(".form-group");
                        if (!row && node.parentElement) {
                            row = node.parentElement;
                        }
                        if (!row) {
                            return;
                        }

                        row.style.display = isVisible ? "" : "none";
                    });
                }

                function applyTransportVisibility() {
                    var transport = document.querySelector("[name=\"' . $transportField . '\"]");
                    if (!transport) {
                        return;
                    }

                    var mode = transport.value;
                    var isGraph = mode === "graph";
                    var isSmtp = mode === "smtp_starttls";

                    toggleRows(' . json_encode($graphFields) . ', isGraph);
                    toggleRows(' . json_encode($smtpFields) . ', isSmtp);
                    toggleRowsBySelector(".internautengraph-section-graph", isGraph);
                    toggleRowsBySelector(".internautengraph-section-smtp", isSmtp);
                }

                document.addEventListener("DOMContentLoaded", function () {
                    applyTransportVisibility();

                    var transport = document.querySelector("[name=\"' . $transportField . '\"]");
                    if (transport) {
                        transport.addEventListener("change", applyTransportVisibility);
                    }
                });
            })();
        </script>';
    }

    public static function shouldInterceptMailSending()
    {
        if (!Module::isInstalled('internautengraph')) {
            return false;
        }

        if ((int) Configuration::get(self::CONF_ENABLED, 0) !== 1) {
            return false;
        }

        $transportMode = self::getMailTransportMode();
        if ($transportMode === self::TRANSPORT_SMTP_STARTTLS) {
            return self::isSmtpConfigured();
        }

        return self::isGraphConfigured();
    }

    public static function shouldUseGraph()
    {
        return self::shouldInterceptMailSending() && self::getMailTransportMode() === self::TRANSPORT_GRAPH;
    }

    public static function shouldUseSmtpStartTls()
    {
        return self::shouldInterceptMailSending() && self::getMailTransportMode() === self::TRANSPORT_SMTP_STARTTLS;
    }

    public static function getMailTransportMode()
    {
        $mode = trim((string) Configuration::get(self::CONF_TRANSPORT_MODE, self::TRANSPORT_GRAPH));
        if (!in_array($mode, array(self::TRANSPORT_GRAPH, self::TRANSPORT_SMTP_STARTTLS), true)) {
            return self::TRANSPORT_GRAPH;
        }

        return $mode;
    }

    private static function isGraphConfigured()
    {
        $config = self::getGraphConfig();

        return $config['tenant_id'] !== ''
            && $config['client_id'] !== ''
            && $config['client_secret'] !== ''
            && $config['sender_mailbox'] !== '';
    }

    private static function isSmtpConfigured()
    {
        $config = self::getSmtpConfig();

        return $config['host'] !== ''
            && (int) $config['port'] > 0
            && $config['username'] !== ''
            && $config['password'] !== ''
            && $config['sender_email'] !== '';
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

    public static function getSmtpConfig()
    {
        return array(
            'host' => trim((string) Configuration::get(self::CONF_SMTP_HOST)),
            'port' => (int) Configuration::get(self::CONF_SMTP_PORT, 587),
            'username' => trim((string) Configuration::get(self::CONF_SMTP_USERNAME)),
            'password' => (string) Configuration::get(self::CONF_SMTP_PASSWORD),
            'sender_email' => trim((string) Configuration::get(self::CONF_SMTP_SENDER_EMAIL)),
            'sender_name' => trim((string) Configuration::get(self::CONF_SMTP_SENDER_NAME)),
        );
    }

    public static function isTemplateVarDebugEnabled()
    {
        return (int) Configuration::get(self::CONF_DEBUG_TEMPLATE_VARS, 0) === 1;
    }
}
