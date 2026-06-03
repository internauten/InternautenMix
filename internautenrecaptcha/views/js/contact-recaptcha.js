(function () {
    var widgetId = null;

    function getContactForm() {
        return document.querySelector('form.contact-form') || document.querySelector('form[action*="contact"]');
    }

    function addErrorAlert(form) {
        var url = new URL(window.location.href);
        if (url.searchParams.get('recaptcha_error') !== '1') {
            return;
        }

        if (form.querySelector('.internauten-recaptcha-error')) {
            return;
        }

        var alert = document.createElement('div');
        alert.className = 'alert alert-danger internauten-recaptcha-error';
        alert.textContent = window.internautenRecaptchaErrorMessage || 'Please confirm that you are not a robot.';
        form.insertBefore(alert, form.firstChild);
    }

    function mountRecaptcha() {
        var form = getContactForm();
        if (!form || !window.internautenRecaptchaSiteKey || !window.grecaptcha) {
            return;
        }

        var existing = form.querySelector('#internauten-contact-recaptcha');
        var container = existing || document.createElement('div');
        container.id = 'internauten-contact-recaptcha';
        container.style.margin = '12px 0';

        if (!existing) {
            var submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitButton && submitButton.parentNode) {
                submitButton.parentNode.insertBefore(container, submitButton);
            } else {
                form.appendChild(container);
            }
        }

        if (widgetId === null) {
            widgetId = window.grecaptcha.render(container, {
                sitekey: window.internautenRecaptchaSiteKey,
            });
        }

        form.addEventListener('submit', function (event) {
            if (widgetId === null) {
                event.preventDefault();
                return;
            }

            var token = window.grecaptcha.getResponse(widgetId);
            if (!token) {
                event.preventDefault();
                addErrorAlert(form);
            }
        });

        addErrorAlert(form);
    }

    window.internautenRecaptchaOnload = mountRecaptcha;

    document.addEventListener('DOMContentLoaded', function () {
        mountRecaptcha();
    });
})();
