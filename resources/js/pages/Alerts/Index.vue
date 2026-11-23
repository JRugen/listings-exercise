<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';
import ListingCard from '../../components/ListingCard.vue';
import Pagination from '../../components/Pagination.vue';
import { formatDate } from '../../format';

defineProps({
    alerts: { type: Object, required: true },
});
</script>

<template>
    <Head title="Alerts" />

    <AppLayout heading="Alerts" subheading="New listings that match your saved searches.">
        <div v-if="alerts.data.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="alert in alerts.data" :key="alert.id">
                <p class="mb-1 text-xs text-slate-400">Alerted {{ formatDate(alert.created_at) }}</p>
                <ListingCard :listing="alert.listing" />
            </div>
        </div>

        <p
            v-else
            class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500"
        >
            No alerts yet. We'll add new listings here when they match one of your
            <Link href="/saved-searches" class="underline underline-offset-2">saved searches</Link>.
        </p>

        <Pagination :meta="alerts.meta" :links="alerts.links" />
    </AppLayout>
</template>
