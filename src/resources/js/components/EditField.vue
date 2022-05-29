<template>
    <div :class="column.editClass">
        <label
            :for="column.field"
            class="form-label"
            v-if="column.type != 'bool'"
            >{{ column.name }}</label
        >
        <textarea
            v-if="column.type == 'textarea'"
            v-model="state[column.field]"
            :id="column.field"
            :class="errorClass(column.field)"
        />
        <select
            v-else-if="column.type == 'combobox'"
            v-model="state[column.field]"
            :id="column.field"
            :class="errorClass(column.field)"
        >
            <option
                v-for="item in column.collection"
                :key="item[Object.keys(item)[0]]"
                :value="item[Object.keys(item)[0]]"
            >
                {{ item[Object.keys(item)[1]] }}
            </option>
        </select>
        <multiselect
            v-else-if="column.type == 'multi'"
            :id="column.field"
            :options="column.collection"
            v-model="state[column.field]"
            :customLabel="multiselectLabel"
            :hideSelected="true"
            :showLabels="false"
            :closeOnSelect="true"
            :multiple="true"
            :class="errorClassMS(column.field)"
        />
        <div class="form-check" v-else-if="column.type == 'bool'">
            <input
                class="form-check-input"
                type="checkbox"
                v-model="state[column.field]"
                :id="column.field"
            />
            <label class="form-check-label" :for="column.field">
                {{ column.name }}
            </label>
        </div>
        <input
            v-else
            v-model="state[column.field]"
            :type="inputType"
            :id="column.field"
            :class="errorClass(column.field)"
        />
        <div class="invalid-feedback">
            {{
                validationErrors
                    ? validationErrors[column.field]
                        ? validationErrors[column.field][0]
                        : ""
                    : ""
            }}
        </div>
    </div>
</template>
<script>
import { errorsMixin } from "../mixins/Errors.js";
import Multiselect from "vue-multiselect";

export default {
    props: ["column", "state", "validationErrors"],
    data() {
        return {
            hola: null,
            times: [1, 2],
        };
    },
    mounted() {
        // if (["date", "datetime", "time"].includes(this.column.type)) {
        //     this.state[this.column.field] = new Date(
        //         this.state[this.column.field]
        //     ).toString();
        // }
    },
    computed: {
        inputType: function () {
            switch (this.column.type) {
                case "datetime":
                    return "datetime-local";
                default:
                    return this.column.type;
            }
        },
    },
    methods: {
        multiselectLabel: function (item) {
            return item[Object.keys(item)[1]];
        },
    },
    mixins: [errorsMixin],
    components: { Multiselect },
};
</script>
