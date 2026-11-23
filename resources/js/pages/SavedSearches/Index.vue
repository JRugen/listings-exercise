<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';
import { formatDate, formatPrice } from '../../format';

defineProps({
    savedSearches: { type: Array, required: true },
});

const criteriaKeys = ['property_type', 'region', 'min_bedrooms', 'max_price'];

function describe(search) {
    return [
        search.property_type_label,
        search.region,
        search.min_bedrooms && `${search.min_bedrooms}+ beds`,
        search.max_price !== null && `up to ${formatPrice(search.max_price)}`,
    ].filter(Boolean);
}

// Links back to the listings page with the same filters applied.
function listingsUrl(search) {
    const query = Object.fromEntries(
        criteriaKeys.filter((key) => search[key] !== null).map((key) => [key, search[key]]),
    );

    return `/?${new URLSearchParams(query)}`;
}

function remove(search) {
    router.delete(`/saved-searches/${search.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Saved searches" />

    <AppLayout heading="Saved searches" subheading="We'll alert you when a new listing matches.">
        <ul v-if="savedSearches.length" class="flex flex-col gap-3">
            <li
                v-for="search in savedSearches"
                :key="search.id"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4"
            >
                <div>
                    <p class="font-medium text-slate-900">{{ describe(search).join(' · ') }}</p>
                    <p class="mt-1 text-xs text-slate-400">Saved {{ formatDate(search.created_at) }}</p>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        :href="listingsUrl(search)"
                        class="rounded-lg px-3 py-2 text-sm text-slate-600 underline-offset-2 hover:underline"
                    >
                        View listings
                    </Link>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 transition hover:border-red-600 hover:text-red-600"
                        @click="remove(search)"
                    >
                        Delete
                    </button>
                </div>
            </li>
        </ul>

        <p
            v-else
            class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500"
        >
            No saved searches yet.
            <Link href="/" class="underline underline-offset-2">Filter the listings</Link>
            and save the search to be alerted about new matches.
        </p>
    </AppLayout>
</template>
