(function () {
    'use strict';

    const nativeValue = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
    let generatedId = 0;

    const formatIsoDate = (value) => {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
        return match ? `${match[3]}-${match[2]}-${match[1]}` : '';
    };

    const parseDisplayDate = (value) => {
        const match = /^(\d{2})-(\d{2})-(\d{4})$/.exec((value || '').trim());
        if (!match) return '';

        const day = Number(match[1]);
        const month = Number(match[2]);
        const year = Number(match[3]);
        const date = new Date(Date.UTC(year, month - 1, day));

        if (date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day) {
            return '';
        }

        return `${match[3]}-${match[2]}-${match[1]}`;
    };

    const formatIsoDateTime = (value) => {
        const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(value || '');
        return match ? `${match[3]}-${match[2]}-${match[1]} ${match[4]}:${match[5]}` : '';
    };

    const parseDisplayDateTime = (value) => {
        const match = /^(\d{2})-(\d{2})-(\d{4}) (\d{2}):(\d{2})$/.exec((value || '').trim());
        if (!match || Number(match[4]) > 23 || Number(match[5]) > 59) return '';

        const isoDate = parseDisplayDate(`${match[1]}-${match[2]}-${match[3]}`);
        return isoDate ? `${isoDate}T${match[4]}:${match[5]}` : '';
    };

    const maskDate = (value) => {
        const digits = (value || '').replace(/\D/g, '').slice(0, 8);
        return [digits.slice(0, 2), digits.slice(2, 4), digits.slice(4, 8)]
            .filter(Boolean)
            .join('-');
    };

    const maskDateTime = (value) => {
        const digits = (value || '').replace(/\D/g, '').slice(0, 12);
        const date = [digits.slice(0, 2), digits.slice(2, 4), digits.slice(4, 8)]
            .filter(Boolean)
            .join('-');
        const time = [digits.slice(8, 10), digits.slice(10, 12)].filter(Boolean).join(':');
        return date + (time ? ` ${time}` : '');
    };

    const enhanceDateInput = (input) => {
        if (!input || input.dataset.displayDateEnhanced === '1') return;

        input.dataset.displayDateEnhanced = '1';
        const isDateTime = input.type === 'datetime-local';
        const originalId = input.id || `iso_date_${++generatedId}`;
        input.id = originalId;

        const display = document.createElement('input');
        display.type = 'text';
        display.id = `${originalId}_display`;
        display.className = input.className;
        display.value = isDateTime ? formatIsoDateTime(input.value) : formatIsoDate(input.value);
        display.placeholder = isDateTime ? 'DD-MM-YYYY HH:MM' : 'DD-MM-YYYY';
        display.inputMode = 'numeric';
        display.autocomplete = 'off';
        display.maxLength = isDateTime ? 16 : 10;
        display.required = input.required;
        display.disabled = input.disabled;
        display.readOnly = input.readOnly;
        display.setAttribute('data-date-format', isDateTime ? 'DD-MM-YYYY HH:MM' : 'DD-MM-YYYY');

        ['aria-label', 'aria-describedby', 'title'].forEach((attribute) => {
            if (input.hasAttribute(attribute)) display.setAttribute(attribute, input.getAttribute(attribute));
        });

        document.querySelectorAll(`label[for="${CSS.escape(originalId)}"]`).forEach((label) => {
            label.setAttribute('for', display.id);
        });

        input.required = false;
        input.type = 'hidden';
        input.insertAdjacentElement('afterend', display);

        const syncDisplay = () => {
            display.value = isDateTime
                ? formatIsoDateTime(nativeValue.get.call(input))
                : formatIsoDate(nativeValue.get.call(input));
            display.disabled = input.disabled;
            display.readOnly = input.readOnly;
        };

        const syncIso = (reportError) => {
            const displayValue = display.value.trim();
            const isoValue = isDateTime ? parseDisplayDateTime(displayValue) : parseDisplayDate(displayValue);
            const isMissing = displayValue === '';
            const isInvalid = (!isMissing && !isoValue) || (display.required && isMissing);

            display.setCustomValidity(isInvalid
                ? `Enter a valid ${isDateTime ? 'date and time in DD-MM-YYYY HH:MM' : 'date in DD-MM-YYYY'} format.`
                : '');
            if (isInvalid) {
                if (reportError) display.reportValidity();
                return false;
            }

            nativeValue.set.call(input, isoValue);
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        };

        Object.defineProperty(input, 'value', {
            configurable: true,
            get() {
                return nativeValue.get.call(input);
            },
            set(value) {
                nativeValue.set.call(input, value || '');
                syncDisplay();
            },
        });

        display.addEventListener('input', function () {
            const cursorAtEnd = this.selectionStart === this.value.length;
            this.value = isDateTime ? maskDateTime(this.value) : maskDate(this.value);
            if (cursorAtEnd) this.setSelectionRange(this.value.length, this.value.length);
            display.setCustomValidity('');
        });
        display.addEventListener('blur', () => syncIso(false));
        display.addEventListener('change', () => syncIso(false));

        const observer = new MutationObserver(syncDisplay);
        observer.observe(input, { attributes: true, attributeFilter: ['disabled', 'readonly'] });

        const form = input.form;
        if (form) {
            form.addEventListener('submit', (event) => {
                if (!syncIso(true)) event.preventDefault();
            }, true);
            form.addEventListener('reset', () => window.setTimeout(syncDisplay, 0));
        }
    };

    const enhanceAll = (root) => {
        if (root instanceof HTMLInputElement && ['date', 'datetime-local'].includes(root.type)) enhanceDateInput(root);
        root.querySelectorAll?.('input[type="date"], input[type="datetime-local"]').forEach(enhanceDateInput);
    };

    document.addEventListener('DOMContentLoaded', () => {
        enhanceAll(document);
        new MutationObserver((mutations) => {
            mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => {
                if (node instanceof Element) enhanceAll(node);
            }));
        }).observe(document.body, { childList: true, subtree: true });
    });
}());
