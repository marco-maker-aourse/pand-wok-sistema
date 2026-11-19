import { router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { toast } from '@/Components/ui/Toast';

const primerError = (errores) => {
    const valor = Object.values(errores ?? {})[0];
    return valor ? String(valor) : null;
};

/**
 * Estado y acciones comunes de los catálogos simples (alta, edición en diálogo,
 * desactivación con confirmación). Cada pantalla define sus campos y mensajes.
 *
 * - `endpoint`: ruta REST base, por ejemplo '/marcas'.
 * - `getId(registro)`: identificador del registro.
 * - `valoresIniciales`: datos de un registro nuevo.
 * - `aFormulario(registro)`: datos del formulario al editar.
 * - `validar(data, { editando })`: devuelve { campo, mensaje } o null.
 * - `mensajes`: { guardado(data, esEdicion), eliminado(registro), errorEliminar }.
 */
export default function useCatalogoCrud({ endpoint, getId, valoresIniciales, aFormulario, validar, mensajes = {} }) {
    const form = useForm(valoresIniciales);
    const [abierto, setAbierto] = useState(false);
    const [editando, setEditando] = useState(null);
    const [porEliminar, setPorEliminar] = useState(null);
    const [eliminando, setEliminando] = useState(false);

    const abrirNuevo = (valores = {}) => {
        setEditando(null);
        form.clearErrors();
        form.setData({ ...valoresIniciales, ...valores });
        setAbierto(true);
    };

    const abrirEdicion = (registro) => {
        setEditando(registro);
        form.clearErrors();
        form.setData(aFormulario(registro));
        setAbierto(true);
    };

    const cerrar = () => {
        if (form.processing) return;
        setAbierto(false);
    };

    const guardar = (event) => {
        event?.preventDefault();
        const esEdicion = Boolean(editando);
        const problema = validar?.(form.data, { editando });

        if (problema) {
            form.setError(problema.campo, problema.mensaje);
            return;
        }

        const datos = form.data;
        const opciones = {
            preserveScroll: true,
            onSuccess: () => {
                setAbierto(false);
                setEditando(null);
                form.reset();
                const { titulo, mensaje } = mensajes.guardado?.(datos, esEdicion) ?? { titulo: 'Cambios guardados' };
                toast.success(titulo, mensaje);
            },
            onError: (errores) => {
                const camposDelFormulario = Object.keys(errores).some((campo) => campo in datos);
                if (!camposDelFormulario) toast.error('No se pudo guardar', primerError(errores));
            },
        };

        if (esEdicion) form.put(`${endpoint}/${getId(editando)}`, opciones);
        else form.post(endpoint, opciones);
    };

    const confirmarEliminacion = () => {
        if (!porEliminar) return;
        const registro = porEliminar;

        router.delete(`${endpoint}/${getId(registro)}`, {
            preserveScroll: true,
            onStart: () => setEliminando(true),
            onFinish: () => {
                setEliminando(false);
                setPorEliminar(null);
            },
            onSuccess: () => {
                const { titulo, mensaje } = mensajes.eliminado?.(registro) ?? { titulo: 'Registro desactivado' };
                toast.success(titulo, mensaje);
            },
            onError: (errores) => toast.error('Operación no permitida', primerError(errores) ?? mensajes.errorEliminar),
        });
    };

    return {
        form,
        dialogo: { abierto, editando, abrirNuevo, abrirEdicion, cerrar, guardar },
        eliminacion: {
            registro: porEliminar,
            procesando: eliminando,
            solicitar: setPorEliminar,
            cancelar: () => !eliminando && setPorEliminar(null),
            confirmar: confirmarEliminacion,
        },
    };
}

/** Filtro local por texto. `campos` debe ser una constante del módulo para no recalcular. */
export function useFiltroTexto(registros, texto, campos) {
    return useMemo(() => {
        const q = texto.trim().toLowerCase();
        if (!q) return registros;
        return registros.filter((registro) => campos.some((campo) => String(registro[campo] ?? '').toLowerCase().includes(q)));
    }, [registros, texto, campos]);
}

/** Acepta colecciones planas o paginadas ({ data: [] }). */
export function normalizarColeccion(...fuentes) {
    for (const fuente of fuentes) {
        if (Array.isArray(fuente) && fuente.length) return fuente;
        if (Array.isArray(fuente?.data) && fuente.data.length) return fuente.data;
    }
    const primera = fuentes[0];
    return Array.isArray(primera) ? primera : primera?.data ?? [];
}
