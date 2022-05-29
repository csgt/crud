<template>
    <thead>
        <tr class="table-primary">
            <th
                v-for="column in columns"
                :key="column.field"
                :class="`sortable ` + column.class"
                @click="setSort(column)"
            >
                {{ column.name }}
                <i
                    class="fa fa-fw fa-sort muted"
                    v-if="sort.field != column.field"
                ></i>
                <template v-else>
                    <i
                        class="fa fa-fw fa-sort-down"
                        v-if="sort.direction == 'desc'"
                    ></i>
                    <i class="fa fa-fw fa-sort-up" v-else></i>
                </template>
            </th>
            <th></th>
        </tr>
    </thead>
</template>
<script>
export default {
    props: ["columns", "sort"],
    methods: {
        setSort(column) {
            this.sort.field = column.field;
            this.sort.direction = this.sort.direction == "asc" ? "desc" : "asc";
            this.$emit("fetchData");
        },
    },
};
</script>
<style scoped>
th.sortable {
    cursor: pointer;
}
th.sortable .muted {
    color: #ccc;
}
</style>
