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

export function formatDate(
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