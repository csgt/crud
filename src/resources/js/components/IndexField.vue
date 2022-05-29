<template>
    <div v-if="column.type == 'bool'">
        <span
            v-if="row[column.field]"
            class="badge badge-pill badge-success pr-2 pl-2"
            >si</span
        >
        <span v-else class="badge badge-pill badge-danger pr-2 pl-2">no</span>
    </div>
    <div v-else-if="column.type == 'numeric'">
        {{
            parseFloat(row[column.field]).toLocaleString(undefined, {
                minimumFractionDigits: column.decimals,
            })
        }}
    </div>
    <div v-else-if="column.type == 'datetime'">
        {{ formatDatetime(row[column.field]) }}
    </div>
    <div v-else-if="column.type == 'date'">
        {{ formatDate(row[column.field]) }}
    </div>
    <div v-else-if="column.type == 'time'">
        {{ formatTime(row[column.field]) }}
    </div>
    <div v-else-if="column.type == 'url' && row[column.url]">
        <a :href="row[column.url]" :target="column.target">
            <i class="fas fa-file"></i>
        </a>
    </div>
    <span v-else>
        {{ row[column.field] }}
    </span>
</template>
<script>
export default {
    props: ["column", "row"],
    methods: {
        formatDatetime(str) {
            if (!str) {
                return str;
            }
            let date = new Date(str);
            return date.toLocaleString();
        },
        formatDate(str) {
            if (!str) {
                return str;
            }
            let date = new Date(str);
            return date.toLocaleDateString();
        },
        formatTime(str) {
            if (!str) {
                return str;
            }
            let date = new Date(str);
            return date.toLocaleTimeString();
        },
    },
};
</script>
