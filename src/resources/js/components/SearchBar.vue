<template>
    <div class="d-flex">
        <div class="justify-content-start">
            <div
                class="input-group mb-1"
                v-for="(search, n) in searches"
                :key="n"
            >
                <select
                    class="form-control"
                    v-model="search.conjunction"
                    v-if="n != 0"
                >
                    <option value="and">Y</option>
                    <option value="or">O</option>
                </select>
                <div
                    class="input-group-prepend"
                    v-if="n == 0 && searches.length > 1"
                >
                    <span class="input-group-text">&nbsp;</span>
                </div>
                <select class="form-control" v-model="search.field">
                    <option value=""></option>
                    <option
                        v-for="column in columns"
                        :key="column.field"
                        :value="column.field"
                    >
                        {{ column.name }}
                    </option>
                </select>
                <select class="form-control" v-model="search.operator">
                    <option value="=">=</option>
                    <option value=">">&gt;</option>
                    <option value="<">&lt;</option>
                    <option value="LIKE">LIKE</option>
                </select>
                <input class="form-control" v-model="search.value" />
                <div class="input-group-append">
                    <template v-if="n == 0">
                        <button class="btn btn-default" @click="addSearch">
                            <i class="fa fa-plus" />
                        </button>
                        <button
                            class="btn btn-default"
                            @click="$emit('search', searches)"
                        >
                            <i class="fa fa-search" />
                        </button>
                    </template>
                    <button
                        class="btn btn-default"
                        @click="removeSearch(search)"
                        v-else
                    >
                        <i class="fa fa-minus" />
                    </button>
                </div>
            </div>
        </div>
        <div class="justify-content-end ml-auto">
            <div class="btn-group" role="group" aria-label="Actions">
                <a
                    v-for="(extra, key) in extraactions"
                    type="button"
                    :href="extra.url"
                    :target="extra.target"
                    :key="key"
                    :class="extra.class"
                >
                    <i v-if="extra.icon" :class="extra.icon" />&nbsp;
                    {{ extra.title }}
                </a>

                <button type="button" class="btn btn-default" @click="create">
                    <i class="fa fa-plus" />&nbsp; Agregar
                </button>
            </div>
        </div>
    </div>
</template>
<script>
export default {
    props: ["columns", "extraactions", "urlcreate"],
    data() {
        return {
            searches: [
                {
                    field: null,
                    operator: "=",
                    conjunction: null,
                    value: null,
                },
            ],
        };
    },
    methods: {
        create() {
            window.location = this.urlcreate;
        },
        addSearch() {
            this.searches.push({
                field: null,
                operator: "=",
                conjunction: "and",
                value: null,
            });
        },
        removeSearch(item) {
            this.searches = this.searches.filter((search) => search != item);
        },
    },
};
</script>
