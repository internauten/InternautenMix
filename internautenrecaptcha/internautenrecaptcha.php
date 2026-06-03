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
        $this->version = '1.0.0';
        $this->author = 'die.internauten.ch GmbH';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Contact Form reCAPTCHA');
        $this->description = $this->l('Protects the contact form with Google reCAPTCHA.');
        $this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        return parent::install()
            && Configuration::updateValue(self::CONF_SITE_KEY, '')
            && Configuration::updateValue(self::CONF_SECRET_KEY, '')
            && $this->registerHook('displayHeader')
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

        return $output . $this->renderForm();
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
        if (!$this->isContactRequest()) {
            return;
        }

        $siteKey = (string) Configuration::get(self::CONF_SITE_KEY);
        if ($siteKey === '') {
            return;
        }

        $this->context->controller->registerJavascript(
            'module-' . $this->name . '-recaptcha-api',
            'https://www.google.com/recaptcha/api.js?onload=internautenRecaptchaOnload&render=explicit',
            array('server' => 'remote', 'position' => 'bottom', 'priority' => 200)
        );

        $this->context->controller->registerJavascript(
            'module-' . $this->name . '-contact',
            'modules/' . $this->name . '/views/js/contact-recaptcha.js',
            array('position' => 'bottom', 'priority' => 210)
        );

        Media::addJsDef(array(
            'internautenRecaptchaSiteKey' => $siteKey,
            'internautenRecaptchaErrorMessage' => $this->l('Please confirm that you are not a robot.'),
        ));
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

    protected function isContactRequest()
    {
        $controller = (string) Tools::getValue('controller');
        if ($controller === 'contact') {
            return true;
        }

        $fc = (string) Tools::getValue('fc');
        $module = (string) Tools::getValue('module');
        if ($fc === 'module' && $module === 'contactform' && $controller === 'contact') {
            return true;
        }

        if (isset($this->context->controller) && isset($this->context->controller->php_self)) {
            return $this->context->controller->php_self === 'contact';
        }

        return false;
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
