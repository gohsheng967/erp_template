import '../css/app.css'
import './bootstrap'

import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { createApp, h, ref } from 'vue'
import { ZiggyVue } from '../../vendor/tightenco/ziggy'
import { capitalize, titleCase, formatCurrency, formatDate, formatDateTime } from './helpers/string'
import axios from 'axios'
import Toast from "@/Components/Toast.vue"
import "@mdi/font/css/materialdesignicons.min.css"

axios.defaults.withCredentials = true
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

// CSRF token
const token = document.querySelector('meta[name="csrf-token"]')
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel'

createInertiaApp({
    title: (title) => `${title} - ${appName}`,

    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue')
        ),

    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })

        app.use(plugin)
        app.use(ZiggyVue)

        // ✅ Global helpers
        app.config.globalProperties.$capitalize = capitalize
        app.config.globalProperties.$titleCase = titleCase
        app.config.globalProperties.$formatCurrency = formatCurrency
        app.config.globalProperties.$formatDate = formatDate
        app.config.globalProperties.$formatDateTime = formatDateTime

        // ✅ GLOBAL TOAST REF
        const toastRef = ref(null)
        app.provide("toast", toastRef)

        // ✅ Mount Inertia app
        app.mount(el)

        // ✅ Mount Toast ONCE (outside Inertia tree)
        const toastContainer = document.createElement("div")
        document.body.appendChild(toastContainer)

        // Mount and CAPTURE instance
        const toastApp = createApp(Toast)
        const toastInstance = toastApp.mount(toastContainer)

        // Assign instance to ref
        toastRef.value = toastInstance
    },

    progress: {
        color: '#4B5563',
    },
})
