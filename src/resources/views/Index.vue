<template>
    <b-card>
        {{ fields }}
        <b-row class="mb-2">
            <b-col>
                <b-form-input
                    size="sm"
                    debounce
                    id="filter-input"
                    v-model="filter"
                    type="search"
                    placeholder="Búsqueda"
                ></b-form-input>
            </b-col>
            <b-col align="end">
                <b-button size="sm">Agregar</b-button>
            </b-col>
        </b-row>
        <b-table
            striped
            small
            hover
            outlined
            :responsive="responsive"
            :current-page="currentPage"
            :per-page="perpage"
            :items="items"
            :fields="fields"
            :sort-by.sync="sortBy"
            :sort-desc.sync="sortDesc"
            :filter="filter"
            @filtered="onFiltered"
        >
            <template #cell(actions)="row">
                <b-button size="xs" class="mr-1 btn-info">
                    <i class="fa fa-pencil-alt fa-fw"></i>
                </b-button>
                <b-button size="xs" class="mr-1 btn-danger">
                    <i class="fa fa-times fa-fw"></i>
                </b-button>
            </template>
        </b-table>
        <b-pagination
            v-model="currentPage"
            :per-page="perpage"
            :total-rows="totalRows"
            align="right"
            size="sm"
            class="my-0"
        ></b-pagination>
    </b-card>
</template>
<script>
import axios from "axios";
export default {
    props: ["dataurl", "fields", "perpage", "responsive"],
    data() {
        return {
            sortBy: "age",
            sortDesc: false,
            currentPage: 1,
            filter: null,
            filteredRows: -1,
            items: [
                { longitude: 40, name: "Dickerson", latitude: "Macdonald" },
                { longitude: 21, name: "Larsen", latitude: "Shaw" },
                { longitude: 89, name: "Geneva", latitude: "Wilson" },
                { longitude: 38, name: "Jami2", latitude: "Carney" },
                { longitude: 40, name: "Dickerson2", latitude: "Macdonald" },
                { longitude: 21, name: "Larsen2", latitude: "Shaw2" },
                { longitude: 89, name: "Geneva2", latitude: "Wilson2" },
                { longitude: 38, name: "Jami2", latitude: "Carney2" },
                { longitude: 40, name: "Dickerson3", latitude: "Macdonald2" },
                { longitude: 21, name: "Larsen3", latitude: "Shaw2" },
                { longitude: 89, name: "Geneva3", latitude: "Wilson2" },
                { longitude: 38, name: "Jami3", latitude: "Carney2" },
                { longitude: 40, name: "Dickerson4", latitude: "Macdonald2" },
                { longitude: 21, name: "Larsen4", latitude: "Shaw2" },
                { longitude: 89, name: "Geneva4", latitude: "Wilson2" },
                { longitude: 38, name: "Jami4", latitude: "Carney2" },
            ],
        };
    },
    mounted() {
        console.log("mounted");
        console.log(this.dataurl);
        axios
            .post(this.dataurl, {
                search: {
                    value: null,
                },
            })
            .then((res) => {
                console.log(res);
            })
            .catch((err) => {
                console.log(err);
            });
    },
    computed: {
        totalRows: function () {
            if (this.filteredRows >= 0) {
                return this.filteredRows;
            } else {
                return this.items.length;
            }
        },
    },
    methods: {
        onFiltered(filteredItems) {
            this.filteredRows = filteredItems.length;
            // Trigger pagination to update the number of buttons/pages due to filtering
            this.currentPage = 1;
        },
    },
};
</script>
