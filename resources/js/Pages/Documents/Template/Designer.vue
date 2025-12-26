<script setup>
import { ref } from "vue"
import { router } from "@inertiajs/vue3"

// Props
const props = defineProps({
    template: Object,
    type: Object,
})

// Parsed layout (sections)
const layout = ref(
    props.template.json
        ? JSON.parse(props.template.json)
        : { header: [], body: [], footer: [] }
)

const selected = ref(null)
const showFabMenu = ref(false)

/* ============================================================
| ADD SECTION (1 or 2 columns)
============================================================ */
const addSection = (region, cols) => {
    layout.value[region].push({
        id: Date.now() + Math.random(),
        type: "columns",
        columns: Array.from({ length: cols }, () => ({
            blocks: []
        }))
    })

    selected.value = null
    showFabMenu.value = false
}

/* ============================================================
| ADD COMPONENT INTO A COLUMN
============================================================ */
const addComponent = (section, colIndex, type) => {
    const block = {
        id: Date.now() + Math.random(),
        type,
        content: type === "text" ? "" : null,
        url: type === "image" ? "" : null,
        width: type === "image" ? 150 : null,
        table: type === "table" ? {
            columns: [
                { label: "Item", field: "item" },
                { label: "Qty", field: "qty" },
                { label: "Price", field: "price" },
                { label: "Total", field: "total" },
            ]
        } : null,
        style: type === "text" || type === "image"
            ? { fontSize: 14, bold: false, align: "left" }
            : null
    }

    section.columns[colIndex].blocks.push(block)
    selected.value = block
}

/* ============================================================
| DELETE BLOCK
============================================================ */
const deleteBlock = (section, blockId) => {
    layout.value[section] = layout.value[section].filter(b => b.id !== blockId)
    if (selected.value?.id === blockId) selected.value = null
}

/* ============================================================
| DELETE SUB-BLOCK INSIDE A COLUMN (block inside section)
============================================================ */
const deleteInnerBlock = (column, blockId) => {
    column.blocks = column.blocks.filter(b => b.id !== blockId)
    if (selected.value?.id === blockId) selected.value = null
}

/* ============================================================
| IMAGE UPLOAD
============================================================ */
const handleImageUpload = (event, block) => {
    const file = event.target.files[0]
    if (!file) return

    const reader = new FileReader()
    reader.onload = () => {
        block.url = reader.result
    }
    reader.readAsDataURL(file)
}

/* ============================================================
| SAVE TEMPLATE
============================================================ */
const saveTemplate = () => {
    router.post(route("documents.template.update", props.type.code), {
        json: JSON.stringify(layout.value),
        html_template: props.template.html_template,
    })
}

const cancelEdit = () => router.visit(route("documents.types"))
</script>


<template>
<div class="flex h-screen bg-gray-100">

    <!-- ===================================================== -->
    <!-- LEFT SIDEBAR (COMPONENT LIST) -->
    <!-- ===================================================== -->
    <aside class="w-72 bg-white border-r shadow-sm">
        <div class="p-4 text-lg font-semibold text-gray-700">
            Components
        </div>

        <div class="p-4 space-y-4">

            <!-- TEXT -->
            <div class="card cursor-pointer" @click="addSection('body',1)">
                <div class="font-medium">1 Column Section</div>
                <div class="text-xs text-gray-500">Add single-column block area</div>
            </div>

            <div class="card cursor-pointer" @click="addSection('body',2)">
                <div class="font-medium">2 Column Section</div>
                <div class="text-xs text-gray-500">Side-by-side section</div>
            </div>

        </div>

        <div class="p-4 border-t mt-auto">
            <button
                class="w-full py-2 rounded bg-blue-600 text-white font-semibold shadow"
                @click="saveTemplate"
            >
                Save Template
            </button>

            <button
                class="w-full py-2 mt-2 rounded bg-gray-200 font-semibold"
                @click="cancelEdit"
            >
                Cancel
            </button>
        </div>
    </aside>

    <!-- ===================================================== -->
    <!-- MAIN A4 PAGE -->
    <!-- ===================================================== -->
    <main class="flex-1 overflow-auto flex justify-center py-10 relative">

        <!-- Floating Add Button -->
        <div class="fab" @click="showFabMenu = !showFabMenu">
            <svg width="22" height="22" viewBox="0 0 24 24"
                fill="none" stroke="white" stroke-width="3"
                stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
        </div>

        <div v-if="showFabMenu" class="fab-menu">
            <div class="fab-item" @click="addSection('body',1)">➕ One Column</div>
            <div class="fab-item" @click="addSection('body',2)">➕ Two Columns</div>
        </div>

        <div class="bg-white shadow-xl border rounded-lg"
             style="width:794px;min-height:1123px;">

            <!-- HEADER -->
            <div class="section-label">HEADER</div>
            <div class="p-6 border-b space-y-4 min-h-[120px]">
                <template v-for="(block, idx) in layout.header" :key="block.id">
                    <div class="block-card" @click="selected = block">
                        <template v-if="block.type==='text'">
                            {{ block.content || 'Text...' }}
                        </template>

                        <template v-if="block.type==='divider'">
                            <hr />
                        </template>

                        <template v-if="block.type==='signature'">
                            <div class="signature-box"></div>
                            <div class="text-xs text-gray-400 text-center">Signature</div>
                        </template>

                        <div class="delete-btn"
                            @click.stop="deleteBlock('header', block.id)">
                            ✕
                        </div>
                    </div>
                </template>
            </div>

            <!-- BODY -->
            <div class="section-label">BODY</div>
            <div class="p-6 space-y-8 min-h-[600px]">

                <!-- Loop Sections -->
                <div v-for="section in layout.body" :key="section.id" class="space-y-4">

                    <!-- Columns Layout -->
                    <div class="grid gap-4"
                        :class="section.columns.length === 2 ? 'grid-cols-2' : 'grid-cols-1'">

                        <!-- Render Each Column -->
                        <div v-for="(col, ci) in section.columns" :key="ci" class="border rounded-lg p-3 bg-gray-50">

                            <!-- Add Component Button -->
                            <div class="add-component" @click="col.showMenu = !col.showMenu">
                                + Add Component
                            </div>

                            <!-- Component Menu -->
                            <div v-if="col.showMenu" class="component-menu">
                                <div class="component-item" @click="addComponent(section,ci,'text')">Text</div>
                                <div class="component-item" @click="addComponent(section,ci,'image')">Image</div>
                                <div class="component-item" @click="addComponent(section,ci,'table')">Table</div>
                                <div class="component-item" @click="addComponent(section,ci,'divider')">Divider</div>
                                <div class="component-item" @click="addComponent(section,ci,'signature')">Signature</div>
                            </div>

                            <!-- Render Blocks Inside Column -->
                            <div v-for="block in col.blocks" :key="block.id" class="block-card"
                                @click="selected = block">

                                <!-- TEXT BLOCK -->
                                <template v-if="block.type==='text'">
                                    <div :style="{
                                        fontSize:block.style.fontSize+'px',
                                        fontWeight:block.style.bold?'600':'400',
                                        textAlign:block.style.align
                                    }">
                                        {{ block.content || 'Text...' }}
                                    </div>
                                </template>

                                <!-- IMAGE BLOCK -->
                                <template v-if="block.type==='image'">
                                    <img
                                        :src="block.url || '/placeholder-image.png'"
                                        :style="{ width: block.width + 'px'}"
                                        class="rounded border"
                                    />
                                </template>

                                <!-- TABLE BLOCK -->
                                <template v-if="block.type==='table'">
                                    <table class="w-full border text-sm">
                                        <thead class="bg-gray-100">
                                            <tr>
                                                <th v-for="col in block.table.columns"
                                                    class="border p-2">
                                                    {{ col.label }}
                                                </th>
                                            </tr>
                                        </thead>
                                    </table>
                                </template>

                                <!-- DIVIDER -->
                                <template v-if="block.type==='divider'">
                                    <hr />
                                </template>

                                <!-- SIGNATURE -->
                                <template v-if="block.type==='signature'">
                                    <div class="signature-box"></div>
                                    <div class="text-xs text-center text-gray-400">Signature</div>
                                </template>

                                <!-- DELETE -->
                                <div class="delete-btn"
                                    @click.stop="deleteInnerBlock(col, block.id)">✕</div>

                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- FOOTER -->
            <div class="section-label">FOOTER</div>
            <div class="p-6 border-t min-h-[120px] space-y-4">

                <template v-for="block in layout.footer" :key="block.id">
                    <div class="block-card" @click="selected = block">
                        <template v-if="block.type==='text'">
                            {{ block.content || 'Footer text...' }}
                        </template>

                        <template v-if="block.type==='signature'">
                            <div class="signature-box"></div>
                            <div class="text-xs text-gray-400 text-center">Signature</div>
                        </template>

                        <div class="delete-btn"
                            @click.stop="deleteBlock('footer', block.id)">✕</div>
                    </div>
                </template>

            </div>

        </div>
    </main>

    <!-- ===================================================== -->
    <!-- RIGHT SIDEBAR (PROPERTIES) -->
    <!-- ===================================================== -->
    <aside class="w-72 bg-white border-l shadow-sm p-4 overflow-auto">

        <div class="font-semibold text-gray-700 mb-4">
            Properties
        </div>

        <!-- Only show for blocks that have style -->
        <div v-if="selected && selected.style" class="space-y-4">

            <!-- TEXT CONTENT -->
            <div v-if="selected.type==='text'">
                <label class="text-xs">Text</label>
                <textarea v-model="selected.content"
                    class="w-full border rounded p-2 h-24 text-sm"></textarea>
            </div>

            <!-- FONT SIZE -->
            <div>
                <label class="text-xs">Font Size</label>
                <input type="number" v-model="selected.style.fontSize"
                    class="w-full border rounded p-2 text-sm" />
            </div>

            <!-- BOLD -->
            <div class="flex items-center gap-2">
                <input type="checkbox" v-model="selected.style.bold" />
                <label class="text-xs">Bold</label>
            </div>

            <!-- ALIGN -->
            <div>
                <label class="text-xs">Alignment</label>
                <select v-model="selected.style.align" class="w-full border rounded p-2 text-sm">
                    <option value="left">Left</option>
                    <option value="center">Center</option>
                    <option value="right">Right</option>
                </select>
            </div>

        </div>

        <!-- BLOCKS WITHOUT STYLE (example: signature, table, divider) -->
        <div v-else-if="selected" class="text-gray-500 text-sm">
            This block has no editable styling.
        </div>

        <div v-else class="text-gray-400 text-sm">
            Select a block to edit.
        </div>

    </aside>

</div>
</template>


<style scoped>
.card {
    @apply bg-white p-4 rounded-lg shadow hover:shadow-md border cursor-pointer transition;
}
.section-label {
    @apply text-xs text-gray-400 px-6 py-2 uppercase tracking-wider;
}
.block-card {
    @apply bg-white p-4 rounded-lg shadow border relative hover:shadow-md cursor-pointer;
}
.delete-btn {
    @apply absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm shadow cursor-pointer;
}
.signature-box {
    @apply border-b w-48 h-10 mx-auto;
}

.add-component {
    @apply bg-gray-200 text-gray-700 rounded p-2 text-center cursor-pointer text-sm mb-2 hover:bg-gray-300;
}

.component-menu {
    @apply bg-white rounded shadow border mb-2 overflow-hidden;
}

.component-item {
    @apply p-2 text-sm cursor-pointer hover:bg-gray-100 border-b;
}

/* Floating button */
.fab {
    position: fixed;
    right: 32px;
    bottom: 32px;
    background: #2563eb;
    width: 54px;
    height: 54px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    cursor: pointer;
    box-shadow: 0 6px 18px rgba(0,0,0,0.25);
    z-index: 50;
    transition: transform .2s;
}
.fab:hover { transform: scale(1.08); }

.fab-menu {
    position: fixed;
    right: 32px;
    bottom: 100px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.2);
    overflow: hidden;
    z-index: 50;
}

.fab-item {
    padding: 12px 20px;
    cursor: pointer;
    font-size: 14px;
    border-bottom: 1px solid #eee;
}
.fab-item:hover {
    background: #f3f4f6;
}
</style>
