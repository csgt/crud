<template>
    <tr>
        <td v-for="column in columns" :key="column.field" :class="column.class">
            <IndexField :column="column" :row="row" />
        </td>
        <td class="text-right">
            <div
                class="btn-group btn-group-sm"
                role="group"
                aria-label="Actions"
            >
                <button
                    v-for="(button, key) in extrabuttons"
                    :key="key"
                    :class="`btn btn-` + button.class"
                    :title="button.title"
                    @click="extraButtonAction(button)"
                >
                    <i :class="button.icon" />
                </button>
                <button
                    type="button"
                    class="btn btn-primary"
                    @click="edit"
                    title="Editar"
                >
                    <i class="fa-solid fas fa-pencil fa-pencil-alt" />
                </button>
                <button
                    type="button"
                    class="btn btn-danger"
                    @click="destroy"
                    title="Eliminar"
                >
                    <i class="fas fa-trash-alt fa-solid fa-trash-can" />
                </button>
            </div>
        </td>
    </tr>
</template>
<script>
import IndexField from "./IndexField.vue";
export default {
    props: ["row", "columns", "extrabuttons", "urledit", "urldestroy"],
    methods: {
        edit: function () {
            window.location = this.processUrl(this.urledit);
        },
        destroy: function () {
            if (confirm("¿Está seguro que desea eliminar el registro?")) {
                axios
                    .delete(this.processUrl(this.urldestroy))
                    .then(() => {
                        this.$emit("destroy", this.row);
                    })
                    .catch((err) => {
                        this.handleError(err);
                    });
            }
        },
        processUrl: function (url) {
            return url.replace("{id}", this.row.___id___);
        },
        extraButtonAction(button) {
            if (button.confirm) {
                if (!confirm(button.confirmmessage)) {
                    return;
                }
            }
            let url = this.processUrl(button.url);

            if (button.target == "" && button.target == "_self") {
                window.location = url;
            } else {
                window.open(url, button.target);
            }
        },
    },
    components: { IndexField },
};
</script>
