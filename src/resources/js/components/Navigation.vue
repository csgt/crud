<template>
    <div class="d-flex">
        <div class="justify-content-start mr-auto text-secondary">
            {{ nav.from }} - {{ nav.to }} [ {{ nav.total }} ]
        </div>
        <nav aria-label="Page navigation">
            <ul class="pagination pagination-sm justify-content-end">
                <li
                    v-for="(link, i) in nav.links"
                    :key="i"
                    :class="{
                        'page-item': true,
                        active: link.active,
                        disabled: !link.url,
                    }"
                >
                    <button class="page-link" @click="fetch(link.url)">
                        {{ link.label }}
                    </button>
                </li>
            </ul>
        </nav>
    </div>
</template>
<script>
export default {
    props: ["nav"],
    methods: {
        fetch(url) {
            this.$emit("fetch", url);
        },
    },
};
</script>
