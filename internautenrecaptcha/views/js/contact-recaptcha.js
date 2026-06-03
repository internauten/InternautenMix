(function () {
    var widgetSequence = 0;

    function getContactForm() {
        var form = document.querySelector('form.contact-form')
            || document.querySelector('form#contact-form')
            || document.querySelector('form[action*="contact-us"]')
            || document.querySelector('form[action*="contact"]');

        if (form) {
            return form;
        }

        var submitField = document.querySelector('form [name="submitMessage"]');
        if (submitField && submitField.form) {
            return submitField.form;
        }

        var messageField = document.querySelector('form textarea[name="message"]');
        if (messageField && messageField.form) {
            return messageField.form;
        }

        return null;
    }

    function getNewsletterTargets() {
        var targets = [];
        var slots = document.querySelectorAll('.internauten-recaptcha-slot[data-internauten-recaptcha="newsletter"]');

        Array.prototype.forEach.call(slots, function (slot) {
            var form = slot.closest ? slot.closest('form') : null;
            if (!form) {
                return;
            }

            targets.push({
                form: form,
                container: slot,
            });
        });

        if (targets.length > 0) {
            return targets;
        }

        var fallbackForms = document.querySelectorAll('.block_newsletter form, form input[name="submitNewsletter"]');
        Array.prototype.forEach.call(fallbackForms, function (entry) {
            var form = entry.tagName === 'FORM' ? entry : entry.form;
            if (!form) {
                return;
            }

            targets.push({
                form: form,
                container: null,
            });
        });

        return targets;
    }

    function buildTargets() {
        var targets = [];
        var contactForm = getContactForm();

        if (contactForm) {
            targets.push({
                form: contactForm,
                container: contactForm.querySelector('#internauten-contact-recaptcha'),
            });
        }

        Array.prototype.forEach.call(getNewsletterTargets(), function (target) {
            var alreadyAdded = targets.some(function (existingTarget) {
                return existingTarget.form === target.form;
            });

            if (!alreadyAdded) {
                targets.push(target);
            }
        });

        return targets;
    }

    function ensureContainer(target) {
        if (target.container && target.container.parentNode) {
            return target.container;
        }

        var container = document.createElement('div');
        container.className = 'internauten-recaptcha-slot';
        container.style.margin = '12px 0';

        var submitButton = target.form.querySelector('button[type="submit"], input[type="submit"]');
        if (submitButton && submitButton.parentNode) {
            submitButton.parentNode.insertBefore(container, submitButton);
        } else {
            target.form.appendChild(container);
        }

        target.container = container;

        return container;
    }

    function getWidgetId(form) {
        if (!form.dataset.internautenRecaptchaWidgetId) {
            return null;
        }

        return parseInt(form.dataset.internautenRecaptchaWidgetId, 10);
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

    function bindSubmitHandler(form) {
        if (form.dataset.internautenRecaptchaSubmitBound) {
            return;
        }

        form.addEventListener('submit', function (event) {
            var widgetId = getWidgetId(form);
            if (widgetId === null || isNaN(widgetId)) {
                event.preventDefault();
                addErrorAlert(form);
                return;
            }

            var token = window.grecaptcha.getResponse(widgetId);
            if (!token) {
                event.preventDefault();
                addErrorAlert(form);
            }
        });
        form.dataset.internautenRecaptchaSubmitBound = '1';
    }

    function mountRecaptcha() {
        if (!window.internautenRecaptchaSiteKey || !window.grecaptcha) {
            return;
        }

        Array.prototype.forEach.call(buildTargets(), function (target) {
            var container = ensureContainer(target);

            if (!container.id) {
                widgetSequence += 1;
                container.id = 'internauten-recaptcha-' + widgetSequence;
            }

            if (getWidgetId(target.form) === null) {
                target.form.dataset.internautenRecaptchaWidgetId = String(window.grecaptcha.render(container, {
                    sitekey: window.internautenRecaptchaSiteKey,
                }));
            }

            bindSubmitHandler(target.form);
            addErrorAlert(target.form);
        });
    }

    window.internautenRecaptchaOnload = mountRecaptcha;

    document.addEventListener('DOMContentLoaded', function () {
        mountRecaptcha();
    });
})();
