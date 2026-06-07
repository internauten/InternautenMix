<?php

class InternautenGraphMailer
{
    private static $lastError = '';
    private static $currentRequestId = '';

    private static function startRequestContext($template = '')
    {
        if (function_exists('random_bytes')) {
            self::$currentRequestId = bin2hex(random_bytes(6));
        } else {
            self::$currentRequestId = substr(sha1(uniqid((string) mt_rand(), true)), 0, 12);
        }

        if (self::isTemplateDebugEnabled() && class_exists('PrestaShopLogger')) {
            PrestaShopLogger::addLog(
                'internautengraph debug [rid=' . self::$currentRequestId . ']: begin send for template=' . (string) $template,
                1
            );
        }
    }

    private static function getRequestId()
    {
        return self::$currentRequestId !== '' ? self::$currentRequestId : 'n/a';
    }

    public static function getLastError()
    {
        return (string) self::$lastError;
    }

    public static function clearLastError()
    {
        self::$lastError = '';
        if (class_exists('Configuration')) {
            Configuration::updateValue('INTERNAUTENGRAPH_LAST_ERROR', '');
        }
    }

    private static function setLastError($message)
    {
        self::$lastError = trim((string) $message);

        if (class_exists('Configuration')) {
            Configuration::updateValue('INTERNAUTENGRAPH_LAST_ERROR', self::$lastError);
        }

        if (class_exists('PrestaShopLogger') && self::$lastError !== '') {
            PrestaShopLogger::addLog(
                'internautengraph [rid=' . self::getRequestId() . ']: ' . self::$lastError,
                3
            );
        }
    }

    private static function extractApiError(array $response, $defaultMessage)
    {
        $status = isset($response['status']) ? (int) $response['status'] : 0;
        $body = isset($response['body']) ? (string) $response['body'] : '';

        $detail = '';
        if ($body !== '') {
            $decoded = json_decode($body, true);
            if (is_array($decoded) && isset($decoded['error'])) {
                if (is_array($decoded['error'])) {
                    $code = isset($decoded['error']['code']) ? (string) $decoded['error']['code'] : '';
                    $message = isset($decoded['error']['message']) ? (string) $decoded['error']['message'] : '';
                    $detail = trim($code . ' ' . $message);
                } elseif (is_string($decoded['error'])) {
                    $detail = trim($decoded['error']);
                }
            }
        }

        $errorMessage = trim((string) $defaultMessage) . ' (HTTP ' . $status . ')';
        if ($detail !== '') {
            $errorMessage .= ': ' . $detail;
        }

        return $errorMessage;
    }

    public static function sendTestMail($toEmail, $toName = '')
    {
        self::startRequestContext('test_mail');
        self::clearLastError();

        if (!class_exists('InternautenGraph')) {
            require_once _PS_MODULE_DIR_ . 'internautengraph/internautengraph.php';
        }

        if (!InternautenGraph::shouldUseGraph()) {
            self::setLastError('Graph sending is disabled or incomplete configuration values are missing.');
            return false;
        }

        $config = InternautenGraph::getGraphConfig();
        $accessToken = self::requestAccessToken($config);
        if ($accessToken === null) {
            return false;
        }

        $message = array(
            'subject' => 'Internauten Graph Test Email',
            'body' => array(
                'contentType' => 'HTML',
                'content' => '<p>This is a test email sent by the PrestaShop module <strong>internautengraph</strong>.</p>'
                    . '<p>Timestamp: ' . date('Y-m-d H:i:s') . ' UTC</p>'
                    . '<p>Sender mailbox: ' . htmlspecialchars($config['sender_mailbox'], ENT_QUOTES, 'UTF-8') . '</p>',
            ),
            'toRecipients' => self::formatRecipients(array(
                array(
                    'address' => (string) $toEmail,
                    'name' => (string) $toName,
                ),
            )),
        );

        return self::sendMailRequest($config['sender_mailbox'], $accessToken, $message);
    }

    public static function send(
        $idLang,
        $template,
        $subject,
        $templateVars,
        $to,
        $toName,
        $from,
        $fromName,
        $fileAttachment,
        $templatePath,
        $idShop,
        $bcc,
        $replyTo,
        $replyToName
    ) {
        self::startRequestContext((string) $template);
        self::clearLastError();

        if (!class_exists('InternautenGraph')) {
            require_once _PS_MODULE_DIR_ . 'internautengraph/internautengraph.php';
        }

        if (!InternautenGraph::shouldUseGraph()) {
            self::setLastError('Graph sending is disabled or incomplete configuration values are missing.');
            return false;
        }

        if (class_exists('Hook')) {
            Hook::exec(
                'sendMailAlterTemplateVars',
                array(
                    'template' => $template,
                    'template_vars' => &$templateVars,
                )
            );
        }

        $config = InternautenGraph::getGraphConfig();
        $accessToken = self::requestAccessToken($config);
        if ($accessToken === null) {
            return false;
        }

        $message = self::buildMessage(
            (int) $idLang,
            (string) $template,
            (string) $subject,
            (array) $templateVars,
            $to,
            $toName,
            $from,
            $fromName,
            $fileAttachment,
            (string) $templatePath,
            $idShop,
            $bcc,
            $replyTo,
            $replyToName
        );

        if ($message === null) {
            self::setLastError('Graph message could not be built because no valid recipient was provided.');
            return false;
        }

        return self::sendMailRequest($config['sender_mailbox'], $accessToken, $message);
    }

    private static function buildMessage(
        $idLang,
        $template,
        $subject,
        array $templateVars,
        $to,
        $toName,
        $from,
        $fromName,
        $fileAttachment,
        $templatePath,
        $idShop,
        $bcc,
        $replyTo,
        $replyToName
    ) {
        $recipients = self::normalizeRecipients($to, $toName);
        if (empty($recipients)) {
            return null;
        }

        $rendered = self::renderTemplate($templatePath, $template, $idLang, $templateVars, $idShop);

        $bodyType = 'HTML';
        $bodyContent = $rendered['html'];
        if ($bodyContent === '') {
            $bodyType = 'Text';
            $bodyContent = $rendered['text'];
        }
        if ($bodyContent === '') {
            $bodyType = 'Text';
            $bodyContent = trim((string) $subject);
        }

        $message = array(
            'subject' => (string) $subject,
            'body' => array(
                'contentType' => $bodyType,
                'content' => $bodyContent,
            ),
            'toRecipients' => self::formatRecipients($recipients),
        );

        $bccRecipients = self::normalizeRecipients($bcc, null);
        if (!empty($bccRecipients)) {
            $message['bccRecipients'] = self::formatRecipients($bccRecipients);
        }

        $replyToAddress = '';
        $replyToDisplayName = '';
        if (is_string($replyTo)) {
            $replyToAddress = trim($replyTo);
            $replyToDisplayName = is_string($replyToName) ? trim($replyToName) : '';
        } elseif (is_string($from)) {
            $replyToAddress = trim($from);
            $replyToDisplayName = is_string($fromName) ? trim($fromName) : '';
        }

        if ($replyToAddress !== '') {
            $message['replyTo'] = array(
                array(
                    'emailAddress' => array(
                        'address' => $replyToAddress,
                        'name' => $replyToDisplayName,
                    ),
                ),
            );
        }

        $attachments = self::normalizeAttachments($fileAttachment);
        if (!empty($attachments)) {
            $message['attachments'] = $attachments;
        }

        return $message;
    }

    private static function renderTemplate($templatePath, $template, $idLang, array $templateVars, $idShop)
    {
        $normalizedTemplateVars = self::normalizeTemplateVars($templateVars, $template, $idLang, $idShop);

        $iso = Language::getIsoById((int) $idLang);
        if (!is_string($iso) || $iso === '') {
            $iso = 'en';
        }

        $basePath = rtrim($templatePath, '/\\') . DIRECTORY_SEPARATOR;

        $htmlPath = $basePath . $iso . DIRECTORY_SEPARATOR . $template . '.html';
        $textPath = $basePath . $iso . DIRECTORY_SEPARATOR . $template . '.txt';

        $html = '';
        if (is_file($htmlPath) && is_readable($htmlPath)) {
            $html = (string) Tools::file_get_contents($htmlPath);
            $html = strtr($html, $normalizedTemplateVars);
        }

        $text = '';
        if (is_file($textPath) && is_readable($textPath)) {
            $text = (string) Tools::file_get_contents($textPath);
            $text = strtr($text, $normalizedTemplateVars);
        }

        self::logTemplateDebug((string) $template, (int) $idLang, $normalizedTemplateVars, $html, $text);

        return array(
            'html' => $html,
            'text' => $text,
        );
    }

    private static function isTemplateDebugEnabled()
    {
        if (!class_exists('InternautenGraph')) {
            $moduleClass = _PS_MODULE_DIR_ . 'internautengraph/internautengraph.php';
            if (is_file($moduleClass)) {
                require_once $moduleClass;
            }
        }

        return class_exists('InternautenGraph') && InternautenGraph::isTemplateVarDebugEnabled();
    }

    private static function logTemplateDebug($template, $idLang, array $normalizedTemplateVars, $html, $text)
    {
        if (!self::isTemplateDebugEnabled() || !class_exists('PrestaShopLogger')) {
            return;
        }

        $keys = array_keys($normalizedTemplateVars);
        sort($keys);

        $unresolved = array();
        $contentToCheck = (string) $html . "\n" . (string) $text;
        if ($contentToCheck !== '') {
            if (preg_match_all('/\{[a-zA-Z0-9_\-\.]+\}/', $contentToCheck, $matches)) {
                $unresolved = array_values(array_unique($matches[0]));
                sort($unresolved);
            }
        }

        $summary = sprintf(
            'internautengraph debug [rid=%s]: template=%s, idLang=%d, vars=%d, unresolved=%d',
            self::getRequestId(),
            (string) $template,
            (int) $idLang,
            count($keys),
            count($unresolved)
        );
        PrestaShopLogger::addLog($summary, 1);

        $keysLog = 'internautengraph debug [rid=' . self::getRequestId() . '] keys: ' . implode(', ', $keys);
        PrestaShopLogger::addLog($keysLog, 1);

        if (!empty($unresolved)) {
            $unresolvedLog = 'internautengraph debug [rid=' . self::getRequestId() . '] unresolved placeholders: ' . implode(', ', $unresolved);
            PrestaShopLogger::addLog($unresolvedLog, 2);
        }
    }

    private static function normalizeTemplateVars(array $templateVars, $template, $idLang, $idShop)
    {
        $normalized = array();

        foreach ($templateVars as $key => $value) {
            if (!is_scalar($value) && $value !== null) {
                continue;
            }

            $valueString = (string) $value;
            $keyString = is_string($key) ? trim($key) : '';

            if ($keyString === '') {
                continue;
            }

            $normalized[$keyString] = $valueString;

            if (preg_match('/^\{.+\}$/', $keyString)) {
                $withoutBraces = trim($keyString, '{}');
                if ($withoutBraces !== '') {
                    $normalized[$withoutBraces] = $valueString;
                }
            } else {
                $normalized['{' . $keyString . '}'] = $valueString;
            }
        }

        $idShop = (int) $idShop;
        if ($idShop <= 0 && class_exists('Context')) {
            $context = Context::getContext();
            if (isset($context->shop) && isset($context->shop->id)) {
                $idShop = (int) $context->shop->id;
            }
        }

        if (class_exists('Context') && !(Context::getContext()->link instanceof Link)) {
            Context::getContext()->link = new Link();
        }

        $configuration = array(
            'PS_SHOP_NAME' => '',
            'PS_MAIL_COLOR' => '',
            'PS_LOGO_MAIL' => '',
            'PS_LOGO' => '',
        );

        if (class_exists('Configuration')) {
            $loaded = Configuration::getMultiple(
                array('PS_SHOP_NAME', 'PS_MAIL_COLOR', 'PS_LOGO_MAIL', 'PS_LOGO'),
                null,
                null,
                $idShop > 0 ? $idShop : null
            );
            if (is_array($loaded)) {
                $configuration = array_merge($configuration, $loaded);
            }
        }

        $shopName = trim((string) $configuration['PS_SHOP_NAME']);
        if ($shopName === '' && class_exists('Context')) {
            $context = Context::getContext();
            if (isset($context->shop) && isset($context->shop->name)) {
                $shopName = trim((string) $context->shop->name);
            }
        }

        if ($shopName !== '') {
            if (!isset($normalized['{shop_name}'])) {
                $normalized['{shop_name}'] = $shopName;
            }
            if (!isset($normalized['shop_name'])) {
                $normalized['shop_name'] = $shopName;
            }
        }

        if (class_exists('Context') && Context::getContext()->link instanceof Link) {
            $link = Context::getContext()->link;

            if (!isset($normalized['{shop_url}'])) {
                $normalized['{shop_url}'] = $link->getPageLink('index', null, (int) $idLang, null, false, $idShop > 0 ? $idShop : null);
            }
            if (!isset($normalized['{my_account_url}'])) {
                $normalized['{my_account_url}'] = $link->getPageLink('my-account', null, (int) $idLang, null, false, $idShop > 0 ? $idShop : null);
            }
            if (!isset($normalized['{guest_tracking_url}'])) {
                $normalized['{guest_tracking_url}'] = $link->getPageLink('guest-tracking', null, (int) $idLang, null, false, $idShop > 0 ? $idShop : null);
            }
            if (!isset($normalized['{history_url}'])) {
                $normalized['{history_url}'] = $link->getPageLink('history', null, (int) $idLang, null, false, $idShop > 0 ? $idShop : null);
            }

            if (!isset($normalized['{shop_logo}'])) {
                $logoPath = '';
                $logoMail = (string) $configuration['PS_LOGO_MAIL'];
                $logoDefault = (string) $configuration['PS_LOGO'];

                if ($logoMail !== '' && defined('_PS_IMG_DIR_') && is_file(_PS_IMG_DIR_ . $logoMail)) {
                    $logoPath = $logoMail;
                } elseif ($logoDefault !== '' && defined('_PS_IMG_DIR_') && is_file(_PS_IMG_DIR_ . $logoDefault)) {
                    $logoPath = $logoDefault;
                }

                if ($logoPath !== '') {
                    $baseLink = rtrim((string) $link->getBaseLink(null, true), '/') . '/';
                    $normalized['{shop_logo}'] = $baseLink . 'img/' . ltrim($logoPath, '/');
                } else {
                    $normalized['{shop_logo}'] = '';
                }
            }
        }

        if (!isset($normalized['{color}'])) {
            $normalized['{color}'] = Tools::safeOutput((string) $configuration['PS_MAIL_COLOR']);
        }

        if (class_exists('Hook')) {
            $extraTemplateVars = array();
            Hook::exec(
                'actionGetExtraMailTemplateVars',
                array(
                    'template' => $template,
                    'template_vars' => $normalized,
                    'extra_template_vars' => &$extraTemplateVars,
                    'id_lang' => (int) $idLang,
                ),
                null,
                true
            );

            if (is_array($extraTemplateVars) && !empty($extraTemplateVars)) {
                foreach ($extraTemplateVars as $key => $value) {
                    if (!is_scalar($value) && $value !== null) {
                        continue;
                    }
                    $keyString = is_string($key) ? trim($key) : '';
                    if ($keyString === '') {
                        continue;
                    }

                    $valueString = (string) $value;
                    $normalized[$keyString] = $valueString;

                    if (preg_match('/^\{.+\}$/', $keyString)) {
                        $withoutBraces = trim($keyString, '{}');
                        if ($withoutBraces !== '') {
                            $normalized[$withoutBraces] = $valueString;
                        }
                    } else {
                        $normalized['{' . $keyString . '}'] = $valueString;
                    }
                }
            }
        }

        // Keep both notations because some modules inject keys without braces.
        foreach ($normalized as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (preg_match('/^\{.+\}$/', $key)) {
                $withoutBraces = trim($key, '{}');
                if ($withoutBraces !== '' && !isset($normalized[$withoutBraces])) {
                    $normalized[$withoutBraces] = $value;
                }
            } else {
                $withBraces = '{' . $key . '}';
                if (!isset($normalized[$withBraces])) {
                    $normalized[$withBraces] = $value;
                }
            }
        }

        return $normalized;
    }

    private static function normalizeRecipients($emails, $names)
    {
        $result = array();

        if (is_array($emails)) {
            foreach ($emails as $key => $email) {
                $address = trim((string) $email);
                if ($address === '') {
                    continue;
                }

                $name = '';
                if (is_array($names) && isset($names[$key])) {
                    $name = trim((string) $names[$key]);
                } elseif (is_string($names)) {
                    $name = trim($names);
                } elseif (!is_int($key) && is_string($key)) {
                    $name = trim($key);
                }

                $result[] = array(
                    'address' => $address,
                    'name' => $name,
                );
            }

            return $result;
        }

        if (is_string($emails)) {
            $address = trim($emails);
            if ($address !== '') {
                $result[] = array(
                    'address' => $address,
                    'name' => is_string($names) ? trim($names) : '',
                );
            }
        }

        return $result;
    }

    private static function formatRecipients(array $recipients)
    {
        $formatted = array();

        foreach ($recipients as $recipient) {
            $formatted[] = array(
                'emailAddress' => array(
                    'address' => $recipient['address'],
                    'name' => $recipient['name'],
                ),
            );
        }

        return $formatted;
    }

    private static function normalizeAttachments($fileAttachment)
    {
        if (empty($fileAttachment)) {
            return array();
        }

        $attachments = array();

        $rawAttachments = array();
        if (isset($fileAttachment['content']) || isset($fileAttachment['name'])) {
            $rawAttachments[] = $fileAttachment;
        } elseif (is_array($fileAttachment)) {
            $rawAttachments = $fileAttachment;
        }

        foreach ($rawAttachments as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $content = isset($entry['content']) ? $entry['content'] : '';
            $name = isset($entry['name']) ? $entry['name'] : 'attachment.bin';
            $mime = isset($entry['mime']) ? $entry['mime'] : 'application/octet-stream';

            if ($content === '' || $name === '') {
                continue;
            }

            $attachments[] = array(
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => (string) $name,
                'contentType' => (string) $mime,
                'contentBytes' => base64_encode((string) $content),
            );
        }

        return $attachments;
    }

    private static function requestAccessToken(array $config)
    {
        $tenantId = $config['tenant_id'];
        $tokenUrl = 'https://login.microsoftonline.com/' . rawurlencode($tenantId) . '/oauth2/v2.0/token';

        $postFields = http_build_query(array(
            'grant_type' => 'client_credentials',
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'scope' => 'https://graph.microsoft.com/.default',
        ));

        $response = self::httpRequest(
            $tokenUrl,
            'POST',
            array(
                'Content-Type: application/x-www-form-urlencoded',
            ),
            $postFields
        );

        if (!is_array($response)) {
            self::setLastError('Token request failed because no response was received from Microsoft identity platform.');
            return null;
        }

        if ((int) $response['status'] < 200 || (int) $response['status'] > 299) {
            self::setLastError(self::extractApiError($response, 'Token request failed'));
            return null;
        }

        $decoded = json_decode((string) $response['body'], true);
        if (!is_array($decoded) || !isset($decoded['access_token'])) {
            self::setLastError('Token response does not contain an access token.');
            return null;
        }

        return (string) $decoded['access_token'];
    }

    private static function sendMailRequest($senderMailbox, $accessToken, array $message)
    {
        $url = 'https://graph.microsoft.com/v1.0/users/' . rawurlencode($senderMailbox) . '/sendMail';

        $payload = array(
            'message' => $message,
            'saveToSentItems' => true,
        );

        $response = self::httpRequest(
            $url,
            'POST',
            array(
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ),
            json_encode($payload)
        );

        if (!is_array($response)) {
            self::setLastError('Graph sendMail request failed because no response was received.');
            return false;
        }

        if ((int) $response['status'] < 200 || (int) $response['status'] >= 300) {
            self::setLastError(self::extractApiError($response, 'Graph sendMail request failed'));
            return false;
        }

        self::clearLastError();

        return true;
    }

    private static function httpRequest($url, $method, array $headers, $body)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            if ($ch === false) {
                return null;
            }

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);

            if ($body !== null && $body !== '') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }

            $responseBody = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return array(
                'status' => $status,
                'body' => is_string($responseBody) ? $responseBody : '',
            );
        }

        $headerString = implode("\r\n", $headers);
        $context = stream_context_create(array(
            'http' => array(
                'method' => $method,
                'header' => $headerString,
                'content' => (string) $body,
                'timeout' => 20,
                'ignore_errors' => true,
            ),
        ));

        $responseBody = @file_get_contents($url, false, $context);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
            $status = (int) $matches[1];
        }

        return array(
            'status' => $status,
            'body' => is_string($responseBody) ? $responseBody : '',
        );
    }
}
