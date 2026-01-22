export function capitalize(text) {
    if (!text) return '';
    return text.charAt(0).toUpperCase() + text.slice(1);
}

export function titleCase(text) {
    if (!text) return '';
    return text
        .replace(/_/g, ' ')
        .replace(/\w\S*/g, w => w.charAt(0).toUpperCase() + w.slice(1));
}

export function formatCurrency(
    value,
    currency = 'MYR',
    locale = 'en-MY'
) {
    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
    }).format(value ?? 0)
}

export function formatDateTime(
    value
) {
    if (!value) return "-"
    return new Date(value).toLocaleString("en-MY", {
        year: "numeric",
        month: "short",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
    })
}

export function formatDate(
    value
) {
    if (!value) return "-"
    return new Date(value).toLocaleString("en-MY", {
        year: "numeric",
        month: "short",
        day: "2-digit",
    })
}

function numberToWords(value) {
    const ones = [
        'zero', 'one', 'two', 'three', 'four', 'five',
        'six', 'seven', 'eight', 'nine', 'ten', 'eleven',
        'twelve', 'thirteen', 'fourteen', 'fifteen',
        'sixteen', 'seventeen', 'eighteen', 'nineteen',
    ]
    const tens = [
        '', '', 'twenty', 'thirty', 'forty',
        'fifty', 'sixty', 'seventy', 'eighty', 'ninety',
    ]

    if (value < 20) return ones[value]
    if (value < 100) {
        const head = tens[Math.floor(value / 10)]
        const tail = value % 10 ? ` ${ones[value % 10]}` : ''
        return `${head}${tail}`
    }
    if (value < 1000) {
        const head = `${ones[Math.floor(value / 100)]} hundred`
        const tail = value % 100 ? ` ${numberToWords(value % 100)}` : ''
        return `${head}${tail}`
    }
    if (value < 1_000_000) {
        const head = `${numberToWords(Math.floor(value / 1000))} thousand`
        const tail = value % 1000 ? ` ${numberToWords(value % 1000)}` : ''
        return `${head}${tail}`
    }
    if (value < 1_000_000_000) {
        const head = `${numberToWords(Math.floor(value / 1_000_000))} million`
        const tail = value % 1_000_000 ? ` ${numberToWords(value % 1_000_000)}` : ''
        return `${head}${tail}`
    }
    if (value < 1_000_000_000_000) {
        const head = `${numberToWords(Math.floor(value / 1_000_000_000))} billion`
        const tail = value % 1_000_000_000 ? ` ${numberToWords(value % 1_000_000_000)}` : ''
        return `${head}${tail}`
    }

    return value.toString()
}

export function amountToWords(value) {
    if (value === null || value === undefined || value === '') return ''

    const normalized = Number(value)
    if (Number.isNaN(normalized)) return ''

    const total = Math.round(normalized * 100)
    const isNegative = total < 0
    const absoluteTotal = Math.abs(total)

    const ringgit = Math.floor(absoluteTotal / 100)
    const sen = absoluteTotal % 100

    const ringgitWords = numberToWords(ringgit)
    const senWords = numberToWords(sen)

    const phrase = `${ringgitWords} ringgit and ${senWords} sen`
    const result = titleCase(phrase)

    return isNegative ? `Minus ${result}` : result
}
