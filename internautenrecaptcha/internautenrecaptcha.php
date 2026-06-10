<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class InternautenRecaptcha extends Module
{
    const CONF_SITE_KEY = 'INTERN_AUTEN_RECAPTCHA_SITE_KEY';
    const CONF_SECRET_KEY = 'INTERN_AUTEN_RECAPTCHA_SECRET_KEY';

    public function __construct()
    {
        $this->name = 'internautenrecaptcha';
        $this->tab = 'front_office_features';
        $this->version = '1.2.0';
        $this->author = 'die.internauten.ch GmbH';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Contact Form reCAPTCHA');
        $this->description = $this->l('Protects the contact form and newsletter signup with Google reCAPTCHA.');
        $this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        return parent::install()
            && Configuration::updateValue(self::CONF_SITE_KEY, '')
            && Configuration::updateValue(self::CONF_SECRET_KEY, '')
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayNewsletterRegistration')
            && $this->registerHook('actionNewsletterRegistrationBefore')
            && $this->registerHook('actionDispatcher');
    }

    public function uninstall()
    {
        return Configuration::deleteByName(self::CONF_SITE_KEY)
            && Configuration::deleteByName(self::CONF_SECRET_KEY)
            && parent::uninstall();
    }

    public function getContent()
    {
        $this->ensureHookRegistration();

        $output = '';

        if (Tools::isSubmit('submitInternautenRecaptcha')) {
            $siteKey = trim((string) Tools::getValue(self::CONF_SITE_KEY));
            $secretKey = trim((string) Tools::getValue(self::CONF_SECRET_KEY));

            if ($siteKey === '' || $secretKey === '') {
                $output .= $this->displayError($this->l('Both keys are required.'));
            } else {
                Configuration::updateValue(self::CONF_SITE_KEY, $siteKey);
                Configuration::updateValue(self::CONF_SECRET_KEY, $secretKey);
                $output .= $this->displayConfirmation($this->l('Settings updated.'));
            }
        }

        $this->registerAdminAssets();
        $output .= $this->renderAdminKeyStatus();

        return $output . $this->renderForm() . $this->renderAdminPreview();
    }

    protected function renderAdminKeyStatus()
    {
        if (!$this->isConfigured()) {
            return $this->displayWarning($this->l('Save both Site key and Secret key to validate the configuration.'));
        }

        $secretValidation = $this->validateSecretKey();
        if ($secretValidation['status'] === 'invalid') {
            return $this->displayError($this->l('The configured Secret key is invalid. Please verify that it matches the Site key and belongs to the same Google reCAPTCHA v2 Checkbox entry.'));
        }

        if ($secretValidation['status'] === 'unreachable') {
            return $this->displayWarning($this->l('The Secret key could not be validated because Google reCAPTCHA is currently unreachable from the server.'));
        }

        return $this->displayConfirmation($this->l('The configured Secret key is syntactically valid and accepted by Google reCAPTCHA.'));
    }

    protected function registerAdminAssets()
    {
        $siteKey = (string) Configuration::get(self::CONF_SITE_KEY);

        $this->context->controller->addJS($this->_path . 'views/js/admin-config.js');
        Media::addJsDef(array(
            'internautenRecaptchaAdminSiteKey' => $siteKey,
            'internautenRecaptchaAdminInvalidKeyMessage' => $this->l('If the preview shows "Invalid key type", please use Google reCAPTCHA v2 Checkbox keys.'),
            'internautenRecaptchaAdminMissingKeyMessage' => $this->l('Save a Site key first to load the preview widget.'),
        ));
    }

    protected function renderAdminPreview()
    {
        $siteKey = (string) Configuration::get(self::CONF_SITE_KEY);
        if ($siteKey === '') {
            return $this->displayWarning($this->l('Save a Site key first to load the preview widget.'));
        }

        return '
            <div class="panel">
                <h3>' . $this->l('reCAPTCHA key preview') . '</h3>
                <p>' . $this->l('This preview renders the saved Site key directly in the Backoffice.') . '</p>
                <p>' . $this->l('If Google shows "Invalid key type", the configured Site key is not a reCAPTCHA v2 Checkbox key.') . '</p>
                <div id="internauten-admin-recaptcha-message" class="alert alert-info">'
                    . $this->l('The preview loads below. If it does not render correctly, verify that the Site key was created as reCAPTCHA v2 Checkbox.') .
                '</div>
                <div id="internauten-admin-recaptcha-preview"></div>
            </div>
            <script src="https://www.google.com/recaptcha/api.js?onload=internautenRecaptchaAdminOnload&amp;render=explicit" async defer></script>';
    }

    protected function validateSecretKey()
    {
        $secret = (string) Configuration::get(self::CONF_SECRET_KEY);
        if ($secret === '') {
            return array('status' => 'missing');
        }

        $response = $this->requestRecaptchaVerification($secret, 'internauten-secret-check');
        if ($response === null || !is_array($response)) {
            return array('status' => 'unreachable');
        }

        $errorCodes = array();
        if (isset($response['error-codes']) && is_array($response['error-codes'])) {
            $errorCodes = $response['error-codes'];
        }

        if (in_array('invalid-input-secret', $errorCodes, true) || in_array('missing-input-secret', $errorCodes, true)) {
            return array('status' => 'invalid');
        }

        return array('status' => 'valid');
    }

    protected function requestRecaptchaVerification($secret, $token)
    {
        $url = 'https://www.google.com/recaptcha/api/siteverify?secret=' . urlencode($secret)
            . '&response=' . urlencode($token)
            . '&remoteip=' . urlencode((string) Tools::getRemoteAddr());

        $response = Tools::file_get_contents($url);
        if ($response === false || $response === '') {
            return null;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
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
        $helper->submit_action = 'submitInternautenRecaptcha';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name
            . '&tab_module=' . $this->tab
            . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->fields_value = array(
            self::CONF_SITE_KEY => (string) Configuration::get(self::CONF_SITE_KEY),
            self::CONF_SECRET_KEY => (string) Configuration::get(self::CONF_SECRET_KEY),
        );

        $fieldsForm = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Google reCAPTCHA settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Site key'),
                        'name' => self::CONF_SITE_KEY,
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Secret key'),
                        'name' => self::CONF_SECRET_KEY,
                        'required' => true,
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );

        return $helper->generateForm(array($fieldsForm));
    }

    public function hookDisplayHeader()
    {
        $this->ensureHookRegistration();

        if (!$this->isConfigured()) {
            return;
        }

        $this->context->controller->registerJavascript(
            'module-' . $this->name . '-contact',
            'modules/' . $this->name . '/views/js/contact-recaptcha.js',
            array('position' => 'bottom', 'priority' => 190)
        );

        $this->context->controller->registerJavascript(
            'module-' . $this->name . '-recaptcha-api',
            'https://www.google.com/recaptcha/api.js?onload=internautenRecaptchaOnload&render=explicit',
            array('server' => 'remote', 'position' => 'bottom', 'priority' => 200)
        );

        Media::addJsDef(array(
            'internautenRecaptchaSiteKey' => (string) Configuration::get(self::CONF_SITE_KEY),
            'internautenRecaptchaErrorMessage' => $this->l('Please confirm that you are not a robot.'),
        ));
    }

    public function hookDisplayNewsletterRegistration()
    {
        if (!$this->isConfigured()) {
            return '';
        }

        return '<div class="internauten-recaptcha-slot internauten-recaptcha-newsletter" data-internauten-recaptcha="newsletter"></div>';
    }

    public function hookActionNewsletterRegistrationBefore($params)
    {
        if (!$this->isConfigured() || !$this->isNewsletterSubscriptionRequest()) {
            return;
        }

        $token = trim((string) Tools::getValue('g-recaptcha-response'));
        if ($this->isCaptchaValid($token)) {
            return;
        }

        if (isset($params['hookError'])) {
            $params['hookError'] = $this->l('Please confirm that you are not a robot.');
        }
    }

    public function hookActionDispatcher()
    {
        if (!$this->isConfigured()) {
            return;
        }

        if (!$this->isContactRequest() || !Tools::isSubmit('submitMessage')) {
            return;
        }

        $token = trim((string) Tools::getValue('g-recaptcha-response'));
        if ($this->isCaptchaValid($token)) {
            return;
        }

        Tools::redirect($this->getContactPageUrlWithError());
    }

    protected function isConfigured()
    {
        return (string) Configuration::get(self::CONF_SITE_KEY) !== ''
            && (string) Configuration::get(self::CONF_SECRET_KEY) !== '';
    }

    protected function ensureHookRegistration()
    {
        foreach (array('displayNewsletterRegistration', 'actionNewsletterRegistrationBefore') as $hookName) {
            if (!$this->isRegisteredInHook($hookName)) {
                $this->registerHook($hookName);
            }
        }
    }

    protected function isContactRequest()
    {
        $controller = (string) Tools::getValue('controller');
        if ($controller === 'contact' || $controller === 'contact-us') {
            return true;
        }

        $fc = (string) Tools::getValue('fc');
        $module = (string) Tools::getValue('module');
        if ($fc === 'module' && $module === 'contactform') {
            return true;
        }

        if (isset($this->context->controller) && isset($this->context->controller->page_name)) {
            $pageName = (string) $this->context->controller->page_name;
            if ($pageName === 'contact' || $pageName === 'contact-us') {
                return true;
            }
        }

        if (isset($this->context->controller) && isset($this->context->controller->php_self)) {
            $phpSelf = (string) $this->context->controller->php_self;
            if ($phpSelf === 'contact' || $phpSelf === 'contact-us') {
                return true;
            }
        }

        $requestUri = (string) Tools::getValue('request_uri', '');
        if ($requestUri === '' && isset($_SERVER['REQUEST_URI'])) {
            $requestUri = (string) $_SERVER['REQUEST_URI'];
        }
        if ($requestUri !== '' && strpos($requestUri, 'contact') !== false) {
            return true;
        }

        return false;
    }

    protected function isNewsletterSubscriptionRequest()
    {
        if (!Tools::isSubmit('submitNewsletter')) {
            return false;
        }

        return (int) Tools::getValue('action', 0) === 0;
    }

    protected function isCaptchaValid($token)
    {
        if ($token === '') {
            return false;
        }

        $secret = (string) Configuration::get(self::CONF_SECRET_KEY);
        if ($secret === '') {
            return false;
        }

        $url = 'https://www.google.com/recaptcha/api/siteverify?secret=' . urlencode($secret)
            . '&response=' . urlencode($token)
            . '&remoteip=' . urlencode((string) Tools::getRemoteAddr());

        $response = Tools::file_get_contents($url);
        if ($response === false || $response === '') {
            return false;
        }

        $decoded = json_decode($response, true);

        return is_array($decoded) && !empty($decoded['success']);
    }

    protected function getContactPageUrlWithError()
    {
        $params = array('recaptcha_error' => 1);
        $idContact = (int) Tools::getValue('id_contact');
        if ($idContact > 0) {
            $params['id_contact'] = $idContact;
        }

        return $this->context->link->getPageLink('contact', true, null, $params);
    }
}
