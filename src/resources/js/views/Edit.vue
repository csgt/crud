<template>
    <div class="card">
        <div class="card-body">
            <div class="row">
                <EditField
                    class="mb-2"
                    v-for="column in columns"
                    :column="column"
                    :state="state"
                    :validationErrors="validationErrors"
                    :key="column.field"
                />
            </div>
        </div>
        <div class="card-footer">
            <button :class="buttonClass()" @click="save">Guardar</button>
        </div>
    </div>
</template>
<script>
import EditField from "../components/EditField";
import { errorsMixin } from "../mixins/Errors.js";

export default {
    props: ["urlupdate", "urlindex", "columns", "combos", "queryParamters"],
    data() {
        return {
            state: state,
            validationErrors: null,
            saving: false,
        };
    },
    methods: {
        save() {
            this.saving = true;
            axios
                .patch(this.urlupdate, state)
                .then((res) => {
                    this.saving = false;
                    toastr.info("Registro almacenado");
                    window.location = this.urlindex;
                })
                .catch((err) => {
                    this.handleError(err);
                });
        },
    },
    mixins: [errorsMixin],
    components: { EditField },
};
</script>
