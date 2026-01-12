<script setup>
import { ref } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link } from '@inertiajs/vue3'
import OverviewTab from './Partials/OverviewTab.vue'
import QuotationTab from './Partials/QuotationTab.vue'
import PurchaseOrderTab from './Partials/PurchaseOrderTab.vue'
import InvoicesTab from './Partials/InvoicesTab.vue'

const props = defineProps({
    supplier: {
        type: Object,
        required: true,
    },
    stats: {
        type: Object,
        required: true,
    },
    quotations: {
        type: Object,
        required: true,
    },
    purchaseOrders: {
        type: Array,
        required: true,
    },
    invoices: {
        type: Object
    }
})


const activeTab = ref('overview')


</script>

<template>
    <AuthenticatedLayout>

        <!-- Page Header -->
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800">
                        Supplier Details
                    </h2>
                    <p class="text-sm text-gray-500">
                        {{ supplier.company_name }}
                    </p>
                </div>

                <div class="flex gap-2">
                    <Link
                        :href="route('suppliers.index')"
                        class="px-4 py-2 rounded-lg border text-sm"
                    >
                        Back
                    </Link>
                </div>
            </div>
        </template>

        <!-- Content -->
        <div class="py-6 space-y-6">

            <!-- Supplier Profile Card -->
            <div class="bg-white border rounded-2xl p-6 shadow-sm">

                <div class="flex flex-col md:flex-row md:items-start gap-6">

                    <!-- Avatar -->
                    <div
                        class="w-14 h-14 rounded-xl bg-indigo-100 flex items-center justify-center
                               text-indigo-600 font-bold text-lg shrink-0"
                    >
                        {{ supplier.company_name?.charAt(0) || 'S' }}
                    </div>

                    <!-- Main Content -->
                    <div class="flex-1 space-y-3">

                        <!-- Title Row -->
                        <div class="flex flex-wrap items-center gap-3">
                            <h2 class="text-2xl font-semibold text-gray-800 leading-tight">
                                {{ supplier.company_name }}
                            </h2>

                            <span
                                class="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-full"
                                :class="supplier.status
                                    ? 'bg-green-100 text-green-700'
                                    : 'bg-red-100 text-red-700'"
                            >
                                {{ supplier.status ? 'Active' : 'Inactive' }}
                            </span>
                        </div>

                        <!-- Registration -->
                        <p class="text-sm text-gray-500">
                            Registration No:
                            <span class="text-gray-700 font-medium">
                                {{ supplier.registration_no || '-' }}
                            </span>
                        </p>

                        <!-- Info Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm mt-4">
                            <div>
                                <p class="text-gray-500">Contact Person</p>
                                <p class="font-medium text-gray-800">
                                    {{ supplier.contact_person || '-' }}
                                </p>
                            </div>

                            <div>
                                <p class="text-gray-500">Phone</p>
                                <p class="font-medium text-gray-800">
                                    {{ supplier.contact_number || '-' }}
                                </p>
                            </div>

                            <div>
                                <p class="text-gray-500">Email</p>
                                <p class="font-medium text-gray-800 break-all">
                                    {{ supplier.email || '-' }}
                                </p>
                            </div>
                        </div>

                        <!-- Address -->
                        <div class="pt-4 mt-4 border-t">
                            <p class="text-xs uppercase tracking-wide text-gray-400 mb-1">
                                Address
                            </p>
                            <p class="text-sm text-gray-700 whitespace-pre-line">
                                {{ supplier.address || '-' }}
                            </p>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Tabs + Content -->
            <div class="bg-white border rounded-2xl p-6 shadow-sm">
                <!-- Tabs -->
                <div class="border-b mb-6">
                    <nav class="flex gap-8 text-sm font-medium">

                        <!-- Overview -->
                        <button
                            @click="activeTab = 'overview'"
                            class="pb-3 border-b-2"
                            :class="activeTab === 'overview'
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700'"
                        >
                            Overview
                        </button>

                        <!-- Quotations -->
                        <button
                            @click="activeTab = 'quotations'"
                            class="pb-3 border-b-2"
                            :class="activeTab === 'quotations'
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700'"
                        >
                            Quotations
                        </button>

                        <!-- Purchase Orders (with indicator) -->
                        <button
                            @click="activeTab = 'orders'"
                            class="pb-3 border-b-2 flex items-center gap-2"
                            :class="activeTab === 'orders'
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700'"
                        >
                            Purchase Orders
                            <span
                                v-if="stats.open_orders > 0"
                                class="px-2 py-0.5 rounded-full text-xs bg-blue-100 text-blue-700"
                            >
                                {{ stats.open_orders }}
                            </span>
                        </button>

                        <!-- Invoices (with indicator) -->
                        <button
                            @click="activeTab = 'invoices'"
                            class="pb-3 border-b-2 flex items-center gap-2"
                            :class="activeTab === 'invoices'
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700'"
                        >
                            Invoices
                            <span
                                v-if="stats.unpaid_invoices > 0"
                                class="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-700"
                            >
                                {{ stats.unpaid_invoices }}
                            </span>
                        </button>

                    </nav>
                </div>
                <!-- Tab Content -->

                <OverviewTab
                    v-if="activeTab === 'overview'"
                    :stats="stats"
                    :supplier="supplier"
                />

                <QuotationTab
                    v-if="activeTab === 'quotations'"
                    :quotations="quotations"
                    :supplier="supplier"
                />

                <PurchaseOrderTab
                    v-if="activeTab === 'orders'"
                    :orders="purchaseOrders"
                />

                <InvoicesTab
                    v-if="activeTab === 'invoices'"
                    :invoices="invoices"
                />
            </div>

        </div>

    </AuthenticatedLayout>
</template>
