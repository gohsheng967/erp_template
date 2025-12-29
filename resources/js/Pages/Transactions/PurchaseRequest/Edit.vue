<script setup>
import { ref, computed, inject } from 'vue'
import { usePage, router, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PurchaseRequestQuotations from
    '@/Pages/Transactions/PurchaseRequest/Partials/PurchaseRequestQuotations.vue'

/* =========================
   GLOBALS
========================= */
const page = usePage()
const toast = inject('toast', null)

/* =========================
   PAGE DATA
========================= */
const pr = computed(() => page.props.pr)
const departments = computed(() => page.props.departments ?? [])
const projects = computed(() => page.props.projects ?? [])
const suppliers = computed(() => page.props.suppliers ?? [])

const isDraft = computed(() => pr.value.status === 'draft')

/* =========================
   FORM STATE (BASIC INFO)
========================= */
const form = ref({
    title: pr.value.title ?? '',
    required_date: pr.value.required_date ?? '',
    purpose: pr.value.purpose ?? '',
    department_id: pr.value.department_id ?? null,
    project_id: pr.value.project_id ?? null,
    requester_remark: pr.value.requester_remark ?? '',
})

/* =========================
   ITEMS STATE
========================= */
const items = ref(
    (pr.value.items ?? []).map(item => ({
        id: item.id ?? null,
        title: item.title ?? '',
        required_date: item.required_date ?? '',
        description: item.description ?? '',
        quantity: Number(item.quantity ?? 1),
        unit_price: Number(item.unit_price ?? 0),
        total_price: Number(item.total_price ?? 0),
    }))
)

/* =========================
   ITEM HELPERS
========================= */
function recalcItem(item) {
    const qty = Number(item.quantity)
    const price = Number(item.unit_price)

    if (!qty || !price) {
        item.total_price = 0
        return
    }

    item.total_price = qty * price
}


const subtotal = computed(() =>
    items.value.reduce((sum, i) => sum + Number(i.total_price || 0), 0)
)

function addItem() {
    items.value.push({
        id: null,
        title: '',
        description: '',
        quantity: null,
        unit_price: null,
        total_price: 0,
    })
}

function removeItem(index) {
    items.value.splice(index, 1)
}

/* =========================
   VALIDATION
========================= */
const canSubmit = computed(() =>
    isDraft.value &&
    form.value.title &&
    items.value.length > 0
)

/* =========================
   ACTIONS
========================= */
function saveDraft() {
    router.put(
        route('purchase-request.update', pr.value.uuid),
        {
            ...form.value,
            items: items.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show('Draft saved', 'success')
            },
            onError: (e) => {
                toast?.value?.show(e, 'error')
            },
        }
    )
}

function submitPR() {
    router.post(
        route('purchase-request.submit', pr.value.uuid),
        {
            ...form.value,
            items: items.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show('Purchase Request submitted', 'success')
            },
            onError: (errors) => {
                const msg = Object.values(errors)[0]
                toast?.value?.show(msg, 'error')
            },
        }
    )
}
</script>

<template>
<AuthenticatedLayout>

    <!-- =====================
         HEADER
    ====================== -->
    <template #header>
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800">
                    Purchase Request
                </h2>
                <div class="text-sm text-gray-500">
                    {{ pr.code }} •
                    <span class="uppercase font-medium">
                        {{ pr.status }}
                    </span>
                </div>
            </div>

            <div class="flex gap-3">
                <Link
                    :href="route('purchase-request.index')"
                    class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300"
                                    title="Edit"

                >
                Back
                </Link>
                <button
                    v-if="isDraft"
                    @click="saveDraft"
                    class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300"
                >
                    Save Draft
                </button>

                <button
                    v-if="isDraft"
                    @click="submitPR"
                    :disabled="!canSubmit"
                    class="px-4 py-2 bg-indigo-600 text-white rounded
                           hover:bg-indigo-700
                           disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    Submit
                </button>
            </div>
        </div>
    </template>

    <div class="p-6 space-y-8">

        <!-- =====================
             SECTION 1: BASIC INFO
        ====================== -->
        <section class="bg-white rounded-lg shadow border p-6">
            <h3 class="font-semibold text-lg mb-4">
                Basic Information
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="text-sm font-medium">Title</label>
                    <input
                        v-model="form.title"
                        :disabled="!isDraft"
                        class="w-full border rounded px-3 py-2"
                    />
                </div>

                <div>
                    <label class="text-sm font-medium">Department</label>
                    <select
                        v-model="form.department_id"
                        :disabled="!isDraft"
                        class="w-full border rounded px-3 py-2"
                    >
                        <option :value="null">Select department</option>
                        <option
                            v-for="d in departments"
                            :key="d.id"
                            :value="d.id"
                        >
                            {{ d.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium">Required Date</label>
                    <input
                        type="date"
                        v-model="form.required_date"
                        :disabled="!isDraft"
                        class="w-full border rounded px-3 py-2"
                    />
                </div>

                <div>
                    <label class="text-sm font-medium">Project</label>
                    <select
                        v-model="form.project_id"
                        :disabled="!isDraft"
                        class="w-full border rounded px-3 py-2"
                    >
                        <option :value="null">No project</option>
                        <option
                            v-for="p in projects"
                            :key="p.id"
                            :value="p.id"
                        >
                            {{ p.name }}
                        </option>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="text-sm font-medium">Purpose</label>
                    <textarea
                        v-model="form.purpose"
                        :disabled="!isDraft"
                        rows="3"
                        class="w-full border rounded px-3 py-2"
                    />
                </div>

                <div class="md:col-span-2">
                    <label class="text-sm font-medium">Requester Remark</label>
                    <textarea
                        v-model="form.requester_remark"
                        :disabled="!isDraft"
                        rows="2"
                        class="w-full border rounded px-3 py-2"
                    />
                </div>
            </div>
        </section>

        <!-- =====================
             SECTION 2: ITEMS
        ====================== -->
        <section class="bg-white rounded-lg shadow border p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-semibold text-lg">Items</h3>

                <button
                    v-if="isDraft"
                    @click="addItem"
                    class="px-3 py-1 bg-indigo-600 text-white rounded text-sm"
                >
                    + Add Item
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm border">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="border px-3 py-2 text-left">Title</th>
                            <th class="border px-3 py-2 text-left">Description</th>
                            <th class="border px-3 py-2 text-right w-24">Qty</th>
                            <th class="border px-3 py-2 text-right w-32">Unit Price</th>
                            <th class="border px-3 py-2 text-right w-32">Total</th>
                            <th v-if="isDraft" class="border px-3 py-2 w-16"></th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="(item, index) in items"
                            :key="index"
                        >
                            <td class="border px-3 py-2">
                                <input
                                    v-model="item.title"
                                    :disabled="!isDraft"
                                    class="w-full border rounded px-2 py-1"
                                />
                            </td>

                            <td class="border px-3 py-2">
                                <input
                                    v-model="item.description"
                                    :disabled="!isDraft"
                                    class="w-full border rounded px-2 py-1"
                                />
                            </td>

                            <td class="border px-3 py-2 text-right">
                                <input
                                    type="number"
                                    min="0"
                                    v-model.number="item.quantity"
                                    @input="recalcItem(item)"
                                    :disabled="!isDraft"
                                    class="w-full border rounded px-2 py-1 text-right"
                                />
                            </td>

                            <td class="border px-3 py-2 text-right">
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    v-model.number="item.unit_price"
                                    @input="recalcItem(item)"
                                    :disabled="!isDraft"
                                    class="w-full border rounded px-2 py-1 text-right"
                                />
                            </td>

                            <td class="border px-3 py-2 text-right tabular-nums">
                                {{ item.total_price.toFixed(2) }}
                            </td>

                            <td
                                v-if="isDraft"
                                class="border px-3 py-2 text-center"
                            >
                                <button
                                    @click="removeItem(index)"
                                    class="text-red-600 hover:text-red-800"
                                >
                                    <i class="mdi mdi-delete"></i>
                                </button>
                            </td>
                        </tr>

                        <tr v-if="!items.length">
                            <td
                                :colspan="isDraft ? 6 : 5"
                                class="border px-3 py-6 text-center text-gray-400"
                            >
                                No items added
                            </td>
                        </tr>
                    </tbody>

                    <tfoot v-if="items.length">
                        <tr class="bg-gray-50 font-semibold">
                            <td colspan="4" class="border px-3 py-2 text-right">
                                Subtotal
                            </td>
                            <td class="border px-3 py-2 text-right tabular-nums">
                                {{ subtotal.toFixed(2) }}
                            </td>
                            <td v-if="isDraft"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <!-- =====================
             SECTION 3: SUPPLIERS & QUOTATIONS
        ====================== -->
        <PurchaseRequestQuotations
            :pr="pr"
            :isDraft="isDraft"
            :suppliers="suppliers"
        />

    </div>
</AuthenticatedLayout>
</template>
