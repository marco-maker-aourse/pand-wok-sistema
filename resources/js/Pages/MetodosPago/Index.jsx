import { useRemember } from '@inertiajs/react';
import CatalogoPagina, { AccionesFila } from '@/Components/Catalogo/CatalogoPagina';
import useCatalogoCrud, { useFiltroTexto } from '@/Components/Catalogo/useCatalogoCrud';
import { Badge, CheckboxField, Field, Input, Select, Td, Tr } from '@/Components/ui';

const CAMPOS_BUSQUEDA = ['nombre', 'tipo'];
const COLUMNAS = [
    { label: 'Método de cobro', campo: 'nombre' },
    { label: 'Tipo', campo: 'tipo' },
    { label: 'N.° de operación', align: 'center', className: 'hidden md:table-cell' },
    { label: 'Comisión', align: 'right' },
];

function tipoDeMetodo(tipo) {
    const t = (tipo || '').toUpperCase();
    if (t === 'PASARELA') return { etiqueta: 'Terminal físico', tono: 'info' };
    if (t.includes('BILLETERA') || t.includes('YAPE') || t.includes('PLIN')) return { etiqueta: 'Billetera digital', tono: 'brand' };
    if (t.includes('TARJETA') || t.includes('POS')) return { etiqueta: 'Tarjeta', tono: 'info' };
    if (t.includes('TRANSFERENCIA') || t.includes('BANCO')) return { etiqueta: 'Transferencia', tono: 'neutral' };
    return { etiqueta: 'Efectivo', tono: 'success' };
}

/** Acepta solo números con hasta 2 decimales y, si se indica, un máximo. */
function limpiarDecimal(valor, maximo = null) {
    let limpio = valor.replace(/[^0-9.]/g, '');
    const partes = limpio.split('.');
    if (partes.length > 2) limpio = `${partes[0]}.${partes.slice(1).join('')}`;
    const [entero, decimales] = limpio.split('.');
    if (decimales && decimales.length > 2) limpio = `${entero}.${decimales.slice(0, 2)}`;
    if (maximo !== null && Number(limpio) > maximo) limpio = String(maximo);
    return limpio;
}

const formatearDecimal = (valor) => {
    const texto = String(valor ?? '').trim();
    return texto === '' || texto === '.' ? '0.00' : Number(texto).toFixed(2);
};

export default function Index({ metodos_pago = [] }) {
    const [busqueda, setBusqueda] = useRemember('', 'busqueda:metodospago');
    const filtrados = useFiltroTexto(metodos_pago, busqueda, CAMPOS_BUSQUEDA);

    const crud = useCatalogoCrud({
        endpoint: '/metodos-pago',
        getId: (metodo) => metodo.id_metodo,
        valoresIniciales: { nombre: '', tipo: 'EFECTIVO', comision_porcentaje: '0.00', comision_fija: '0.00', requiere_referencia: false },
        aFormulario: (metodo) => ({
            nombre: (metodo.nombre || '').toUpperCase(),
            tipo: metodo.tipo ? metodo.tipo.toUpperCase() : 'EFECTIVO',
            comision_porcentaje: Number(metodo.comision_porcentaje || 0).toFixed(2),
            comision_fija: Number(metodo.comision_fija || 0).toFixed(2),
            requiere_referencia: Boolean(metodo.requiere_referencia),
        }),
        validar: (data, { editando }) => {
            const nombre = data.nombre.trim().toUpperCase();
            if (nombre.length < 3) return { campo: 'nombre', mensaje: 'Ingresa al menos 3 caracteres (ej. YAPE, PLIN, TARJETA BCP).' };
            const idActual = editando?.id_metodo ?? null;
            if (metodos_pago.some((m) => m.id_metodo !== idActual && m.nombre.trim().toUpperCase() === nombre)) {
                return { campo: 'nombre', mensaje: `Ya existe un método de pago llamado "${nombre}".` };
            }
            const porcentaje = Number(data.comision_porcentaje);
            if (Number.isNaN(porcentaje) || porcentaje < 0 || porcentaje > 100) return { campo: 'comision_porcentaje', mensaje: 'Debe estar entre 0 % y 100 %.' };
            const fija = Number(data.comision_fija);
            if (Number.isNaN(fija) || fija < 0) return { campo: 'comision_fija', mensaje: 'No puede ser negativa.' };
            return null;
        },
        mensajes: {
            guardado: (data, esEdicion) => ({ titulo: esEdicion ? 'Método actualizado' : 'Método registrado', mensaje: data.nombre.trim() }),
            eliminado: (metodo) => ({ titulo: 'Método eliminado', mensaje: metodo.nombre }),
            errorEliminar: 'No se puede eliminar este método porque ya está asociado a ventas registradas.',
        },
    });
    const { form, dialogo, eliminacion } = crud;

    return (
        <CatalogoPagina
            titulo="Métodos de pago"
            seccion="Configuración"
            descripcion="Formas de cobro disponibles en caja, con sus comisiones y referencias."
            entidad="método"
            crud={crud}
            busqueda={busqueda}
            onBusqueda={setBusqueda}
            placeholderBusqueda="Buscar por nombre o tipo"
            columnas={COLUMNAS}
            registros={filtrados}
            total={metodos_pago.length}
            etiquetaEliminar="Eliminar"
            mensajeEliminar={(metodo) => <>Se eliminará el método <strong>{metodo.nombre}</strong>. Si ya tiene ventas asociadas, el sistema no lo permitirá.</>}
            renderFila={(metodo) => {
                const tipo = tipoDeMetodo(metodo.tipo);
                const porcentaje = Number(metodo.comision_porcentaje || 0);
                const fija = Number(metodo.comision_fija || 0);
                return (
                    <Tr key={metodo.id_metodo}>
                        <Td className="font-medium text-slate-900">{metodo.nombre}</Td>
                        <Td><Badge tone={tipo.tono}>{tipo.etiqueta}</Badge></Td>
                        <Td align="center" className="hidden md:table-cell">{metodo.requiere_referencia ? 'Obligatorio' : <span className="text-slate-400">Opcional</span>}</Td>
                        <Td align="right" className="tabular-nums">
                            {porcentaje > 0 || fija > 0 ? (
                                <>
                                    {porcentaje > 0 && <span>{porcentaje.toFixed(2)} %</span>}
                                    {porcentaje > 0 && fija > 0 && <span className="text-slate-400"> + </span>}
                                    {fija > 0 && <span>S/ {fija.toFixed(2)}</span>}
                                </>
                            ) : (
                                <span className="text-slate-400">Sin comisión</span>
                            )}
                        </Td>
                        <Td align="right">
                            <AccionesFila nombre={metodo.nombre} etiquetaEliminar="Eliminar" onEditar={() => dialogo.abrirEdicion(metodo)} onEliminar={() => eliminacion.solicitar(metodo)} />
                        </Td>
                    </Tr>
                );
            }}
            renderFormulario={() => (
                <>
                    <Field label="Nombre" error={form.errors.nombre} required>
                        <Input value={form.data.nombre} onChange={(e) => form.setData('nombre', e.target.value.toUpperCase())} placeholder="EJ. YAPE, PLIN, TARJETA DE CRÉDITO" autoFocus />
                    </Field>
                    <Field label="Tipo" error={form.errors.tipo}>
                        <Select value={form.data.tipo} onChange={(e) => form.setData('tipo', e.target.value.toUpperCase())}>
                            <option value="EFECTIVO">Efectivo (billetes y monedas)</option>
                            <option value="BILLETERA_DIRECTA">Billetera directa (Yape o Plin con referencia)</option>
                            <option value="PASARELA">Terminal físico (tarjeta)</option>
                        </Select>
                    </Field>
                    {form.data.tipo === 'PASARELA' && (
                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Comisión (%)" error={form.errors.comision_porcentaje}>
                                <Input
                                    inputMode="decimal"
                                    className="tabular-nums"
                                    value={form.data.comision_porcentaje}
                                    onChange={(e) => form.setData('comision_porcentaje', limpiarDecimal(e.target.value, 100))}
                                    onBlur={() => form.setData('comision_porcentaje', formatearDecimal(form.data.comision_porcentaje))}
                                    placeholder="0.00"
                                />
                            </Field>
                            <Field label="Comisión fija (S/)" error={form.errors.comision_fija}>
                                <Input
                                    inputMode="decimal"
                                    className="tabular-nums"
                                    value={form.data.comision_fija}
                                    onChange={(e) => form.setData('comision_fija', limpiarDecimal(e.target.value))}
                                    onBlur={() => form.setData('comision_fija', formatearDecimal(form.data.comision_fija))}
                                    placeholder="0.00"
                                />
                            </Field>
                        </div>
                    )}
                    <CheckboxField
                        label="Exigir número de operación"
                        description="El cajero debe registrar la referencia del pago."
                        checked={form.data.requiere_referencia}
                        onChange={(valor) => form.setData('requiere_referencia', valor)}
                    />
                </>
            )}
        />
    );
}
