/**
 * Sucursales a las que el usuario puede cambiar además de su sede principal.
 * La sede se excluye de la lista; el servidor la conserva siempre y valida
 * que solo un administrador conceda estos accesos.
 */
export default function SucursalesAdicionales({ sucursales = [], idSede, seleccionadas = [], onCambiar, error }) {
    const opciones = sucursales.filter((sucursal) => String(sucursal.id_sucursal) !== String(idSede));
    const marcadas = new Set(seleccionadas.map(String));

    const alternar = (id) => {
        const clave = String(id);
        onCambiar(marcadas.has(clave)
            ? seleccionadas.filter((actual) => String(actual) !== clave)
            : [...seleccionadas, id]);
    };

    return (
        <fieldset className="rounded-xl border border-slate-200 p-3">
            <legend className="px-1 text-xs font-semibold text-slate-600">Acceso a otras sucursales</legend>
            <p className="mb-2 text-xs text-slate-500">Podrá cambiar a estas sucursales desde la barra superior. Los administradores acceden a todas.</p>

            {opciones.length === 0 ? (
                <p className="text-xs text-slate-500">No hay otras sucursales activas.</p>
            ) : (
                <div className="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                    {opciones.map((sucursal) => (
                        <label key={sucursal.id_sucursal} className="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-xs font-semibold uppercase text-slate-700 hover:bg-slate-50">
                            <input
                                type="checkbox"
                                className="rounded border-slate-300 text-brand focus:ring-brand-ring"
                                checked={marcadas.has(String(sucursal.id_sucursal))}
                                onChange={() => alternar(sucursal.id_sucursal)}
                            />
                            {sucursal.nombre}
                        </label>
                    ))}
                </div>
            )}

            {error && <p className="mt-1 text-xs font-bold text-rose-600">{error}</p>}
        </fieldset>
    );
}
