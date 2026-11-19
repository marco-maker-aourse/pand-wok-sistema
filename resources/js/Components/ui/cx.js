/** Une clases condicionales sin dependencias externas. */
export function cx(...valores) {
    return valores.filter(Boolean).join(' ');
}
