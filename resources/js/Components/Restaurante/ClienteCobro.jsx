import { useState } from 'react';
import axios from 'axios';
import { Button, Input } from '@/Components/ui';

/** Busca y elige el cliente del comprobante (nombre, DNI o RUC). Lo usan el POS y el cobro de cuentas. */
export default function ClienteCobro({ cliente, cambiar }) {
    const [busqueda, setBusqueda] = useState('');
    const [resultados, setResultados] = useState([]);
    const [estado, setEstado] = useState('');

    const buscar = async (evento) => {
        evento.preventDefault();
        if (busqueda.trim().length < 2) return;
        setEstado('Buscando…');
        try {
            const { data } = await axios.get('/ventas/clientes/buscar', { params: { q: busqueda.trim() } });
            setResultados(data.data);
            setEstado(data.data.length ? '' : 'Sin resultados');
        } catch {
            setEstado('No se pudo consultar el catálogo de clientes.');
        }
    };

    return (
        <div className="space-y-2">
            <p className="text-sm font-medium">Cliente {cliente ? `· ${cliente.nombre_razon_social} · ${cliente.num_documento}` : 'sin seleccionar'}</p>
            <form className="flex gap-2" onSubmit={buscar}>
                <Input aria-label="Buscar cliente por nombre o documento" placeholder="Nombre, DNI o RUC" value={busqueda} onChange={(e) => setBusqueda(e.target.value)} />
                <Button type="submit" variant="secondary" disabled={busqueda.trim().length < 2}>Buscar</Button>
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
