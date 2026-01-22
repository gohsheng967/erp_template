import { getCurrentInstance } from 'vue'

export function useFormat() {
    const { appContext } = getCurrentInstance()
    const { 
        $formatCurrency,
        $capitalize,
        $titleCase,
        $formatDate,
        $formatDateTime,
        $amountToWords
    } = appContext.config.globalProperties

    return {
        formatCurrency: $formatCurrency,
        capitalize: $capitalize,
        titleCase: $titleCase,
        formatDate: $formatDate,
        formatDateTime: $formatDateTime,
        amountToWords: $amountToWords
    }
}
