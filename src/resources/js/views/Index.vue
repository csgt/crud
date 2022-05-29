<template>
    <div class="card">
        <div class="card-header">
            <SearchBar
                :columns="columns"
                :urlcreate="urlcreate"
                :extraactions="extraactions"
                @search="search"
            />
        </div>
        <div class="card-body p-0">
            <div class="d-flex justify-content-center mt-5 mb-5" v-if="loading">
                <div class="spinner-border" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
            </div>
            <div class="table-responsive" v-else>
                <table
                    class="table table-striped table-hover table-bordered table-sm mb-0"
                >
                    <IndexHeader
                        :columns="columns"
                        :sort="sort"
                        @fetchData="fetchData"
                    />
                    <tbody>
                        <IndexRow
                            v-for="row in rows"
                            :key="row.___id___"
                            :row="row"
                            :columns="columns"
                            :extrabuttons="extrabuttons"
                            :urledit="urledit"
                            :urldestroy="urldestroy"
                            @destroy="destroy"
                        />
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <Navigation :nav="nav" @fetch="fetchData" />
        </div>
    </div>
</template>
<script>
import Navigation from "../components/Navigation";
import IndexHeader from "../components/IndexHeader";
import IndexRow from "../components/IndexRow";
import SearchBar from "../components/SearchBar";
import { errorsMixin } from "../mixins/Errors.js";

export default {
    props: [
        "columns",
        "extrabuttons",
        "extraactions",
        "urldata",
        "urledit",
        "urldestroy",
        "urlcreate",
    ],
    data() {
        return {
            loading: true,
            rows: [],
            nav: {},
            sort: {
                field: null,
                direction: null,
            },
            searches: [],
        };
    },
    mounted() {
        this.fetchData();
    },
    methods: {
        fetchData() {
            this.loading = true;
            axios
                .post(this.urldata, {
                    sort: this.sort,
                    searches: this.searches,
                })
                .then((res) => {
                    this.rows = res.data.data;
                    this.nav = Object.fromEntries(
                        Object.entries(res.data).filter(
                            ([key, value]) => key != "data"
                        )
                    );
                    this.loading = false;
                })
                .catch((err) => {
                    alert(err);
                    this.loading = false;
                });
        },
        destroy(item) {
            this.rows = this.rows.filter((row) => row != item);
            toastr.info("Registro eliminado");
        },
        search(searches) {
            this.searches = searches;
            this.fetchData();
        },
    },
    mixins: [errorsMixin],
    components: {
        Navigation,
        IndexRow,
        IndexHeader,
        SearchBar,
    },
};
</script>
