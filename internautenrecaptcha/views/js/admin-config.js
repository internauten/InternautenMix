(function () {
    function decodeHtmlEntities(text) {
        var textarea = document.createElement('textarea');
        textarea.innerHTML = text;

        return textarea.value;
    }

    function setMessage(text, type) {
        var message = document.getElementById('internauten-admin-recaptcha-message');
        if (!message) {
            return;
        }

        message.className = 'alert alert-' + type;
        message.textContent = decodeHtmlEntities(text);
    }

    function mountPreview() {
        var container = document.getElementById('internauten-admin-recaptcha-preview');
        if (!container) {
            return;
        }

        if (!window.internautenRecaptchaAdminSiteKey) {
            setMessage(window.internautenRecaptchaAdminMissingKeyMessage || 'Save a Site key first to load the preview widget.', 'warning');
            return;
        }

        if (!window.grecaptcha) {
            return;
        }

        if (container.dataset.internautenRecaptchaRendered === '1') {
            return;
        }

        try {
            window.grecaptcha.render(container, {
                sitekey: window.internautenRecaptchaAdminSiteKey,
            });
            container.dataset.internautenRecaptchaRendered = '1';
            setMessage(window.internautenRecaptchaAdminInvalidKeyMessage || 'If the preview shows "Invalid key type", please use Google reCAPTCHA v2 Checkbox keys.', 'info');
        } catch (error) {
            setMessage((window.internautenRecaptchaAdminInvalidKeyMessage || 'The configured Site key could not be rendered.') + (error && error.message ? ' ' + error.message : ''), 'danger');
        }
    }

    window.internautenRecaptchaAdminOnload = mountPreview;
    document.addEventListener('DOMContentLoaded', mountPreview);
})();