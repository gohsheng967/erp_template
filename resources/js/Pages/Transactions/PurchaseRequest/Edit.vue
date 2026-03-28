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
const contactUsers = computed(() => page.props.contactUsers ?? [])

const isEditable = computed(() =>
    ['draft', 'verified_own_department', 'verified_project_department', 'verified_purchasing_department'].includes(pr.value.status)
)
const isDraft = computed(() => pr.value.status === 'draft')
const isOwnDeptVerified = computed(() => pr.value.status === 'verified_own_department')
const isProjectVerified = computed(() => pr.value.status === 'verified_project_department')
const isProjectLinked = computed(() => !!pr.value.project_id)
const isPurchasingVerified = computed(() => pr.value.status === 'verified_purchasing_department')
const isBeforePurchasingVerified = computed(() =>
    isOwnDeptVerified.value || isProjectVerified.value
)
const verifyButtonLabel = computed(() => 'Verified')
const selectedQuotationId = ref(pr.value.approved_quotation_id ?? null)
const poDeliveryPeriod = ref(pr.value.delivery_period ?? '')
const poPaymentTerms = ref(pr.value.payment_terms ?? '')
const poSiteContactUserId = ref(pr.value.site_contact_user_id ?? '')
const stageRemark = ref('')
const quotationOptions = computed(() => pr.value.quotations ?? [])
const isQuotationSectionOpen = ref(true)

/* =========================
   FORM STATE (BASIC INFO)
========================= */
const form = ref({
    title: pr.value.title ?? '',
    required_date: pr.value.required_date ?? '',
    purpose: pr.value.purpose ?? '',
    department_id: pr.value.department_id ?? null,
    project_id: pr.value.project_id ?? null,
    is_subcon_purchase_request: !!pr.value.is_subcon_purchase_request,
    requester_remark: pr.value.requester_remark ?? '',
})

/* =========================
   ITEMS STATE
========================= */
const items = ref(
    (pr.value.items ?? []).map(item => ({
        id: item.id ?? null,
        parent_id: item.parent_id ?? null,
        temp_key: `persist-${item.id ?? Math.random().toString(36).slice(2)}`,
        parent_temp_key: null,
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


const subtotal = computed(() => {
    const parentKeys = new Set()
    items.value.forEach((item) => {
        const parentKey = item.parent_id
            ? `id:${item.parent_id}`
            : (item.parent_temp_key ? `tmp:${item.parent_temp_key}` : null)
        if (parentKey) parentKeys.add(parentKey)
    })

    return items.value.reduce((sum, item) => {
        const itemKey = item.id ? `id:${item.id}` : `tmp:${item.temp_key}`
        const isParent = parentKeys.has(itemKey)
        if (isParent) return sum
        return sum + Number(item.total_price || 0)
    }, 0)
})
const parentChildMismatches = computed(() => {
    const grouped = new Map()

    items.value.forEach((item) => {
        const parentKey = item.parent_id ? `id:${item.parent_id}` : (item.parent_temp_key ? `tmp:${item.parent_temp_key}` : null)
        if (!parentKey) return
        grouped.set(parentKey, (grouped.get(parentKey) ?? 0) + Number(item.total_price || 0))
    })

    const mismatches = []
    items.value.forEach((item) => {
        const itemKey = item.id ? `id:${item.id}` : `tmp:${item.temp_key}`
        if (!grouped.has(itemKey)) return

        const parentTotal = Number(item.total_price || 0)
        const childrenTotal = Number(grouped.get(itemKey) || 0)
        if (Math.abs(parentTotal - childrenTotal) > 0.01) {
            mismatches.push({
                title: (item.title || '').trim() || 'Untitled Item',
                parentTotal,
                childrenTotal,
            })
        }
    })

    return mismatches
})

function makeTempKey() {
    return `tmp-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
}

function itemIdentifier(item) {
    if (item.id) return `id:${item.id}`
    if (item.temp_key) return `tmp:${item.temp_key}`
    return null
}

function isDescendant(candidate, ancestor) {
    const targetKey = itemIdentifier(ancestor)
    if (!targetKey) return false

    let walker = candidate
    for (let i = 0; i < items.value.length; i++) {
        const parentById = walker.parent_id
            ? items.value.find(x => x.id === walker.parent_id)
            : null
        const parentByTemp = walker.parent_temp_key
            ? items.value.find(x => x.temp_key === walker.parent_temp_key)
            : null
        const parent = parentById ?? parentByTemp
        if (!parent) return false
        if (itemIdentifier(parent) === targetKey) return true
        walker = parent
    }

    return false
}

function normalizeParentReference(item) {
    if (!item.parent_id && !item.parent_temp_key) return

    const parent = item.parent_id
        ? items.value.find(x => x.id === item.parent_id)
        : items.value.find(x => x.temp_key === item.parent_temp_key)

    if (!parent || parent === item || isDescendant(parent, item)) {
        item.parent_id = null
        item.parent_temp_key = null
    }
}

function addItem() {
    items.value.push({
        id: null,
        parent_id: null,
        temp_key: makeTempKey(),
        parent_temp_key: null,
        title: '',
        description: '',
        quantity: null,
        unit_price: null,
        total_price: 0,
    })
}

function addSubItem(parentIndex) {
    const parent = items.value[parentIndex]
    if (!parent) return

    const insertIndex = parentIndex + 1
    items.value.splice(insertIndex, 0, {
        id: null,
        parent_id: parent.id ?? null,
        parent_temp_key: parent.id ? null : parent.temp_key,
        temp_key: makeTempKey(),
        title: '',
        description: '',
        quantity: null,
        unit_price: null,
        total_price: 0,
    })
}

function removeItem(index) {
    const target = items.value[index]
    if (!target) return

    const targetId = target.id ?? null
    const targetTempKey = target.temp_key ?? null

    items.value = items.value.filter((item, i) => {
        if (i === index) return false
        if (targetId && item.parent_id === targetId) return false
        if (targetTempKey && item.parent_temp_key === targetTempKey) return false
        return true
    })

    items.value.forEach(normalizeParentReference)
}

function parentLabel(item) {
    if (!item) return ''
    const title = (item.title || '').trim() || 'Untitled Item'
    return item.id ? `${title} (#${item.id})` : `${title} (new)`
}

/* =========================
   VALIDATION
========================= */
const canSubmit = computed(() =>
    isDraft.value &&
    form.value.title &&
    items.value.length > 0 &&
    !!selectedQuotationId.value
)
const canVerifyToPurchasing = computed(() =>
    isBeforePurchasingVerified.value &&
    form.value.title &&
    items.value.length > 0 &&
    (form.value.is_subcon_purchase_request || !!poDeliveryPeriod.value) &&
    !!poPaymentTerms.value &&
    !!poSiteContactUserId.value
)
const missingVerifyFields = computed(() => {
    const missing = []

    if (!form.value.is_subcon_purchase_request && !poDeliveryPeriod.value) missing.push('Delivery Period')
    if (!poPaymentTerms.value) missing.push('Terms & Condition')
    if (!poSiteContactUserId.value) missing.push('Site Contact Person')

    return missing
})
const canIssuePo = computed(() =>
    isPurchasingVerified.value &&
    form.value.title &&
    items.value.length > 0 &&
    !!selectedQuotationId.value
)

/* =========================
   ACTIONS
========================= */
function saveDraft() {
    if (parentChildMismatches.value.length) {
        toast?.value?.show('Sub-item sum must equal main item amount.', 'error')
        return
    }

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
    if (parentChildMismatches.value.length) {
        toast?.value?.show('Sub-item sum must equal main item amount.', 'error')
        return
    }

    router.post(
        route('purchase-request.submit', pr.value.uuid),
        {
            ...form.value,
            items: items.value,
            quotation_id: selectedQuotationId.value,
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

function verifyToPurchasing() {
    router.post(
        route('purchase-request.approval', pr.value.uuid),
        {
            status: 'verify',
            remark: stageRemark.value || null,
            delivery_period: poDeliveryPeriod.value,
            payment_terms: poPaymentTerms.value,
            site_contact_user_id: poSiteContactUserId.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show('Moved to Purchasing Verified', 'success')
                router.visit(route('purchase-request.index', { tab: 'verified_purchasing_department' }))
            },
            onError: (errors) => {
                const msg = Object.values(errors)[0]
                toast?.value?.show(msg, 'error')
            },
        }
    )
}

function submitForPoIssue() {
    router.post(
        route('purchase-request.approval', pr.value.uuid),
        {
            status: 'approved',
            quotation_id: selectedQuotationId.value,
            remark: stageRemark.value || null,
            delivery_period: poDeliveryPeriod.value,
            payment_terms: poPaymentTerms.value,
            site_contact_user_id: poSiteContactUserId.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show('CEO approved and PO created', 'success')
                router.visit(route('purchase-request.index'))
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

            <div class="flex items-center gap-2">
                <Link
                    :href="route('purchase-request.index')"
                    class="h-8 px-2.5 text-xs font-medium bg-gray-200 rounded-md hover:bg-gray-300 inline-flex items-center"
                                    title="Edit"

                >
                Back
                </Link>
                <button
                    v-if="isEditable"
                    @click="saveDraft"
                    class="h-8 px-2.5 text-xs font-medium bg-gray-200 rounded-md hover:bg-gray-300"
                >
                    Save Draft
                </button>

                <button
                    v-if="isDraft"
                    @click="submitPR"
                    :disabled="!canSubmit"
                    class="h-8 px-2.5 text-xs font-medium bg-indigo-600 text-white rounded-md
                           hover:bg-indigo-700
                           disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    Submit
                </button>

                <button
                    v-if="isBeforePurchasingVerified"
                    @click="verifyToPurchasing"
                    :disabled="!canVerifyToPurchasing"
                    class="h-8 px-2.5 text-xs font-medium bg-blue-600 text-white rounded-md
                           hover:bg-blue-700
                           disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    {{ verifyButtonLabel }}
                </button>

                <button
                    v-if="isPurchasingVerified"
                    @click="submitForPoIssue"
                    :disabled="!canIssuePo"
                    class="h-8 px-2.5 text-xs font-medium bg-emerald-600 text-white rounded-md
                           hover:bg-emerald-700
                           disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    CEO Approve & Create PO
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
                        :disabled="!isEditable"
                        class="w-full border rounded px-3 py-2"
                    />
                </div>

                <div>
                    <label class="text-sm font-medium">Department</label>
                    <select
                        v-model="form.department_id"
                        :disabled="!isEditable"
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
                        :disabled="!isEditable"
                        class="w-full border rounded px-3 py-2"
                    />
                </div>

                <div>
                    <label class="text-sm font-medium">Project</label>
                    <select
                        v-model="form.project_id"
                        :disabled="!isEditable"
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
                    <label class="inline-flex items-center gap-2 text-sm font-medium">
                        <input
                            v-model="form.is_subcon_purchase_request"
                            type="checkbox"
                            :disabled="!isEditable"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        This is Sub Con Purchase Request
                    </label>
                    <p class="mt-1 text-xs text-gray-500">
                        When enabled, quotation attach section uses Sub Con instead of Supplier.
                    </p>
                </div>

                <div class="md:col-span-2">
                    <label class="text-sm font-medium">Purpose</label>
                    <textarea
                        v-model="form.purpose"
                        :disabled="!isEditable"
                        rows="3"
                        class="w-full border rounded px-3 py-2"
                    />
                </div>

                <div class="md:col-span-2">
                    <label class="text-sm font-medium">Requester Remark</label>
                    <textarea
                        v-model="form.requester_remark"
                        :disabled="!isEditable"
                        rows="2"
                        class="w-full border rounded px-3 py-2"
                    />
                </div>

                <div class="md:col-span-2">
                    <label class="text-sm font-medium">
                        Quotation For Submission <span class="text-red-500">*</span>
                    </label>
                    <select
                        v-model="selectedQuotationId"
                        class="w-full border rounded px-3 py-2"
                        :disabled="!isEditable"
                    >
                        <option :value="null">Select quotation</option>
                        <option
                            v-for="q in quotationOptions"
                            :key="q.id"
                            :value="q.id"
                        >
                            {{ q.quotation_no }} - RM {{ Number(q.amount ?? 0).toFixed(2) }}
                        </option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">
                        Required when submitting PR or submitting for PO issue.
                    </p>
                </div>

                <template v-if="isBeforePurchasingVerified">
                    <div v-if="!form.is_subcon_purchase_request">
                        <label class="text-sm font-medium">
                            Delivery Period <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="poDeliveryPeriod"
                            type="date"
                            class="w-full border rounded px-3 py-2"
                        />
                    </div>

                    <div>
                        <label class="text-sm font-medium">
                            Site Contact Person <span class="text-red-500">*</span>
                        </label>
                        <select
                            v-model="poSiteContactUserId"
                            class="w-full border rounded px-3 py-2"
                        >
                            <option value="">Select contact person</option>
                            <option
                                v-for="u in contactUsers"
                                :key="u.id"
                                :value="u.id"
                            >
                                {{ u.name }}
                            </option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-sm font-medium">
                            Terms & Condition <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            v-model="poPaymentTerms"
                            rows="2"
                            class="w-full border rounded px-3 py-2"
                            placeholder="Enter terms and condition"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-sm font-medium">Stage Remark</label>
                        <textarea
                            v-model="stageRemark"
                            rows="2"
                            class="w-full border rounded px-3 py-2"
                            placeholder="Stage remark (optional)"
                        />
                    </div>

                    <div
                        v-if="!canVerifyToPurchasing"
                        class="md:col-span-2 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700"
                    >
                        Please complete: {{ missingVerifyFields.join(', ') }}. You cannot proceed to Purchasing Verify until all required fields are filled.
                    </div>
                </template>

                <div v-if="isPurchasingVerified" class="md:col-span-2">
                    <label class="text-sm font-medium">Stage Remark</label>
                    <textarea
                        v-model="stageRemark"
                        rows="2"
                        class="w-full border rounded px-3 py-2"
                        placeholder="Stage remark (optional)"
                    />
                </div>
            </div>
        </section>

        <!-- =====================
             SECTION 2: SUPPLIERS & QUOTATIONS
        ====================== -->
        <section class="bg-white rounded-lg shadow border p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-lg">Suppliers & Quotations</h3>
                <button
                    type="button"
                    class="px-3 py-1 text-xs font-medium bg-gray-100 border rounded hover:bg-gray-200"
                    @click="isQuotationSectionOpen = !isQuotationSectionOpen"
                >
                    {{ isQuotationSectionOpen ? 'Collapse' : 'Expand' }}
                </button>
            </div>

            <div v-show="isQuotationSectionOpen">
                <PurchaseRequestQuotations
                    :pr="pr"
                    :isDraft="isEditable"
                    :is-sub-con-purchase-request="!!form.is_subcon_purchase_request"
                />
            </div>
        </section>

        <!-- =====================
             SECTION 3: ITEMS
        ====================== -->
        <section class="bg-white rounded-lg shadow border p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-semibold text-lg">Items</h3>

                <button
                    v-if="isEditable"
                    @click="addItem"
                    class="px-3 py-1 bg-indigo-600 text-white rounded text-sm"
                >
                    + Add Item
                </button>
            </div>

            <div class="overflow-x-auto">
                <div
                    v-if="parentChildMismatches.length"
                    class="mb-3 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800"
                >
                    Main item amount must equal sub-item sum.
                </div>
                <table class="min-w-full text-sm border">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="border px-3 py-2 text-left w-56">Parent</th>
                            <th class="border px-3 py-2 text-left">Title</th>
                            <th class="border px-3 py-2 text-left">Description</th>
                            <th class="border px-3 py-2 text-right w-24">Qty</th>
                            <th class="border px-3 py-2 text-right w-32">Unit Price</th>
                            <th class="border px-3 py-2 text-right w-32">Total</th>
                            <th v-if="isEditable" class="border px-3 py-2 w-16"></th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="(item, index) in items"
                            :key="index"
                        >
                            <td class="border px-3 py-2">
                                <select
                                    :value="item.parent_id ? `id:${item.parent_id}` : (item.parent_temp_key ? `tmp:${item.parent_temp_key}` : '')"
                                    :disabled="!isEditable"
                                    class="w-full border rounded px-2 py-1"
                                    @change="(e) => {
                                        const value = e.target.value
                                        if (!value) {
                                            item.parent_id = null
                                            item.parent_temp_key = null
                                            return
                                        }
                                        if (value.startsWith('id:')) {
                                            item.parent_id = Number(value.slice(3))
                                            item.parent_temp_key = null
                                            return
                                        }
                                        item.parent_id = null
                                        item.parent_temp_key = value.slice(4)
                                    }"
                                >
                                    <option value="">Main Item</option>
                                    <option
                                        v-for="(p, pIndex) in items"
                                        :key="`parent-${index}-${pIndex}`"
                                        :value="p.id ? `id:${p.id}` : `tmp:${p.temp_key}`"
                                        :disabled="p === item || isDescendant(p, item)"
                                    >
                                        {{ parentLabel(p) }}
                                    </option>
                                </select>
                            </td>

                            <td class="border px-3 py-2">
                                <input
                                    v-model="item.title"
                                    :disabled="!isEditable"
                                    class="w-full border rounded px-2 py-1"
                                />
                            </td>

                            <td class="border px-3 py-2">
                                <textarea
                                    v-model="item.description"
                                    :disabled="!isEditable"
                                    rows="2"
                                    class="w-full border rounded px-2 py-1"
                                ></textarea>
                            </td>

                            <td class="border px-3 py-2 text-right">
                                <input
                                    type="number"
                                    min="0"
                                    v-model.number="item.quantity"
                                    @input="recalcItem(item)"
                                    :disabled="!isEditable"
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
                                    :disabled="!isEditable"
                                    class="w-full border rounded px-2 py-1 text-right"
                                />
                            </td>

                            <td class="border px-3 py-2 text-right tabular-nums">
                                {{ item.total_price.toFixed(2) }}
                            </td>

                            <td
                                v-if="isEditable"
                                class="border px-3 py-2 text-center"
                            >
                                <div class="flex items-center justify-center gap-2">
                                    <button
                                        @click="addSubItem(index)"
                                        class="text-indigo-600 hover:text-indigo-800"
                                        title="Add sub item"
                                    >
                                        <i class="mdi mdi-plus-box-outline"></i>
                                    </button>
                                    <button
                                        @click="removeItem(index)"
                                        class="text-red-600 hover:text-red-800"
                                        title="Remove item"
                                    >
                                        <i class="mdi mdi-delete"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!items.length">
                            <td
                                :colspan="isEditable ? 7 : 6"
                                class="border px-3 py-6 text-center text-gray-400"
                            >
                                No items added
                            </td>
                        </tr>
                    </tbody>

                    <tfoot v-if="items.length">
                        <tr class="bg-gray-50 font-semibold">
                            <td colspan="5" class="border px-3 py-2 text-right">
                                Subtotal
                            </td>
                            <td class="border px-3 py-2 text-right tabular-nums">
                                {{ subtotal.toFixed(2) }}
                            </td>
                            <td v-if="isEditable"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

    </div>
</AuthenticatedLayout>
</template>
