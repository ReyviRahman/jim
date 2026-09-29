document.addEventListener('alpine:init', () => {
    window.Alpine.data('membershipFormValidation', () => ({
        findField(key) {
            if (!key) return undefined;

            const aliases = {
                reason: 'operational_reason',
                split_payment: 'split_cash',
            };
            const name = aliases[key] ?? key;
            return Array.from(this.$el.querySelectorAll('input, select, textarea, [tabindex], [data-validation-field]'))
                .find((element) => element.id === name
                    || element.dataset.validationField === name
                    || Array.from(element.attributes).some((attribute) =>
                        attribute.name.startsWith('wire:model') && attribute.value === name));
        },

        focusField(key) {
            const field = this.findField(key);
            const target = field?.getClientRects().length ? field : this.$el.querySelector('[data-membership-validation-summary]');
            target?.focus({ preventScroll: true });
            target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },

        showErrors(fields) {
            this.$nextTick(() => {
                this.$el.querySelectorAll('[data-membership-invalid]').forEach((element) => {
                    element.removeAttribute('aria-invalid');
                    element.removeAttribute('data-membership-invalid');
                    element.classList.remove('outline-2', 'outline-red-600');
                });
                const invalidFields = fields.map((key) => ({ key, element: this.findField(key) }))
                    .filter(({ element }) => element?.getClientRects().length);
                invalidFields.forEach(({ element }) => {
                    element.setAttribute('aria-invalid', 'true');
                    element.setAttribute('data-membership-invalid', '');
                    element.classList.add('outline-2', 'outline-red-600');
                });
                invalidFields.sort((a, b) => a.element === b.element ? 0
                    : a.element.compareDocumentPosition(b.element) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1);
                this.focusField(invalidFields[0]?.key ?? '');
            });
        },
    }));
});
