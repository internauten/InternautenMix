<?php

class Mail extends MailCore
{
    public static function Send(
        $idLang,
        $template,
        $subject,
        $templateVars,
        $to,
        $toName = null,
        $from = null,
        $fromName = null,
        $fileAttachment = null,
        $modeSmtp = null,
        $templatePath = _PS_MAIL_DIR_,
        $die = false,
        $idShop = null,
        $bcc = null,
        $replyTo = null,
        $replyToName = null
    ) {
        if (!class_exists('InternautenGraph')) {
            $moduleClass = _PS_MODULE_DIR_ . 'internautengraph/internautengraph.php';
            if (is_file($moduleClass)) {
                require_once $moduleClass;
            }
        }

        if (!class_exists('InternautenGraphMailer')) {
            $mailerClass = _PS_MODULE_DIR_ . 'internautengraph/classes/InternautenGraphMailer.php';
            if (is_file($mailerClass)) {
                require_once $mailerClass;
            }
        }

        if (class_exists('InternautenGraph') && class_exists('InternautenGraphMailer') && InternautenGraph::shouldUseGraph()) {
            $sentWithGraph = InternautenGraphMailer::send(
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
            );

            if ($sentWithGraph) {
                return true;
            }
        }

        return parent::Send(
            $idLang,
            $template,
            $subject,
            $templateVars,
            $to,
            $toName,
            $from,
            $fromName,
            $fileAttachment,
            $modeSmtp,
            $templatePath,
            $die,
            $idShop,
            $bcc,
            $replyTo,
            $replyToName
        );
    }
}
