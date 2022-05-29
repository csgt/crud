export const errorsMixin = {
    methods: {
        errorClassMS(item) {
            if (!this.validationErrors) {
                return {}
            }
            return {'is-invalid': this.validationErrors.hasOwnProperty(item) }
        },
        errorClass(item, sm = false) {
            return { 'form-control': true, 'form-control-sm': sm, 'is-invalid': (this.validationErrors && this.validationErrors.hasOwnProperty(item)) }
        },
        buttonClass() {
            return ['btn', 'btn-primary', this.saving ? 'disabled' : '']
        },
        handleError(error) {
            this.saving = false
            if (error.response.status == 422) {
                this.validationErrors = error.response.data.errors;
            } else {
                toastr.error(error.response.data.message)
            }
        },
        getError(item) {
            return (this.validationErrors && this.validationErrors.hasOwnProperty(item)) ? 
                this.validationErrors[item][0] : null
        }
    },
    computed: {
        hasErrors() {
            return _.isEmpty(this.validationErrors)
        }
    },
}
