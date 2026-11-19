import { useState } from 'react';
import axios from 'axios';
import { Button, Input } from '@/Components/ui';

/** Busca y elige el cliente del comprobante (nombre, DNI o RUC). Lo usan el POS y el cobro de cuentas. */
export default function ClienteCobro({ cliente, cambiar }) {
    const [busqueda, setBusqueda] = useState('');
    const [resultados, setResultados] = useState([]);
    const [estado, setEstado] = useState('');
    const [ocupado, setOcupado] = useState(false);
    const [nombrePendiente, setNombrePendiente] = useState(false);
    const [nombre, setNombre] = useState('');
    const consulta = busqueda.trim();
    const esDocumento = /^(?:\d{8}|\d{11})$/.test(consulta);

    const buscar = async (evento) => {
        evento.preventDefault();
        if (ocupado || consulta.length < 2) return;
        setOcupado(true);
        setEstado('Buscando…');
        setResultados([]);
        try {
            if (esDocumento) {
                const { data } = await axios.post('/pos/clientes', {
                    documento: consulta,
                    nombre: nombrePendiente ? nombre.trim() : null,
                });
                cambiar(data.cliente);
                setNombrePendiente(false);
                setNombre('');
                setEstado('');
            } else {
                const { data } = await axios.get('/ventas/clientes/buscar', { params: { q: consulta } });
                setResultados(data.data);
                setEstado(data.data.length ? '' : 'Sin resultados');
            }
        } catch (error) {
            setNombrePendiente(Boolean(error.response?.data?.requiere_nombre));
            setEstado(error.response?.data?.message ?? 'No se pudo consultar el catálogo de clientes.');
        } finally {
            setOcupado(false);
        }
    };

    return (
        <div className="space-y-2">
            <p className="text-sm font-medium">Cliente {cliente ? `· ${cliente.nombre_razon_social} · ${cliente.num_documento}` : 'sin seleccionar'}</p>
            <form className="space-y-2" onSubmit={buscar}>
                <div className="flex flex-wrap gap-2">
                    <Input aria-label="Buscar cliente por nombre o documento" placeholder="Nombre, DNI o RUC" value={busqueda} disabled={ocupado} onChange={(e) => {
                        setBusqueda(e.target.value);
                        setNombrePendiente(false);
                        setNombre('');
                        setEstado('');
                        setResultados([]);
                    }} />
                    <Button type="submit" variant="secondary" loading={ocupado} disabled={consulta.length < 2 || (nombrePendiente && !nombre.trim())}>
                        {nombrePendiente ? 'Registrar' : esDocumento ? 'Identificar' : 'Buscar'}
                    </Button>
                </div>
                {nombrePendiente && <Input required aria-label="Nombre o razón social" placeholder="Nombre o razón social" value={nombre} maxLength={200} disabled={ocupado} onChange={(e) => setNombre(e.target.value)} />}
            </form>
            {estado && <p role="status" className="text-sm text-slate-600">{estado}</p>}
            {resultados.length > 0 && (
                <ul className="max-h-40 divide-y overflow-auto rounded-lg border">
                    {resultados.map((resultado) => (
                        <li key={resultado.id_cliente}>
                            <button type="button" onClick={() => { cambiar(resultado); setResultados([]); }} className="block min-h-11 w-full px-3 text-left text-sm hover:bg-brand-soft">
                                {resultado.nombre_razon_social} · {resultado.num_documento}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
