import { getCurrentInstance } from 'vue'

export function useFormat() {
    const { appContext } = getCurrentInstance()
    const { 
        $formatCurrency,
        $capitalize,
        $titleCase,
    } = appContext.config.globalProperties

    return {
        formatCurrency: $formatCurrency,
        capitalize: $capitalize,
        titleCase: $titleCase,
    }
}
