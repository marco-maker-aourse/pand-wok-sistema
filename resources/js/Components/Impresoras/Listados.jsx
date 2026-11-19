import { Activity, Laptop, Link2, Pencil, Printer, Unplug } from 'lucide-react';
import { Badge, Button, Card, CardHeader, EmptyState, IconButton, IconoTile } from '@/Components/ui';
import { EstadoConexion, estadoAgente, fechaHora } from './estado';

/** Impresoras de la sucursal con diagnóstico y ticket de prueba. */
export function ListaImpresoras({ impresoras, onEditar, onAccion, onNueva }) {
    return (
        <Card>
            <CardHeader icon={Printer} title="Impresoras" description={`${impresoras.length} ${impresoras.length === 1 ? 'equipo' : 'equipos'} en la sucursal`} actions={<Button size="sm" variant="secondary" onClick={onNueva}>Agregar</Button>} />
            {impresoras.length === 0 ? (
                <EmptyState icon={Printer} title="Aún no hay impresoras" description="Registra la primera para asignarla a una terminal." />
            ) : (
                <ul className="divide-y divide-slate-100">
                    {impresoras.map((p) => (
                        <li key={p.id_impresora} className="flex flex-col gap-3 px-5 py-4 transition-colors hover:bg-brand-soft/30 sm:flex-row sm:items-center">
                            <IconoTile icon={Printer} tone={p.activo ? 'brand' : 'neutral'} size="lg" />
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <p className="font-medium text-slate-900">{p.nombre}</p>
                                    <EstadoConexion valor={p.estado_conexion} />
                                    {!p.activo && <Badge>Inactiva</Badge>}
                                </div>
                                <p className="mt-0.5 text-sm text-slate-500">{[p.marca || 'Sin marca', p.modelo, `${p.ancho_papel_mm} mm`, p.tipo_conexion].filter(Boolean).join(' · ')}</p>
                                <p className="mt-0.5 font-mono text-xs break-all text-slate-500">{p.identificador}{p.puerto ? `:${p.puerto}` : ''}</p>
                                <p className="mt-1.5 text-xs text-slate-500">
                                    Verificada: {fechaHora(p.ultima_verificacion) ?? 'sin verificar'} · Última impresión: {p.ultimo_resultado_impresion ?? 'desconocida'}
                                </p>
                                {p.ultimo_error_conexion && <p className="mt-1 text-xs text-rose-600">Conexión: {p.ultimo_error_conexion}</p>}
                                {p.ultimo_error_impresion && <p className="mt-1 text-xs text-rose-600">Impresión: {p.ultimo_error_impresion}</p>}
                            </div>
                            <div className="flex shrink-0 items-center gap-1.5">
                                <Button size="sm" variant="secondary" icon={Activity} onClick={() => onAccion(p.id_impresora, 'diagnosticar')}>Diagnosticar</Button>
                                <Button size="sm" onClick={() => onAccion(p.id_impresora, 'prueba')}>Prueba</Button>
                                <IconButton icon={Pencil} label={`Editar ${p.nombre}`} onClick={() => onEditar(p)} />
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}

/** Terminales POS con estado del agente, vinculación y revocación. */
export function ListaTerminales({ terminales, onEditar, onVincular, onRevocar, onNueva }) {
    return (
        <Card>
            <CardHeader icon={Laptop} title="Terminales POS" description={`${terminales.length} ${terminales.length === 1 ? 'terminal' : 'terminales'} registradas`} actions={<Button size="sm" variant="secondary" onClick={onNueva}>Agregar</Button>} />
            {terminales.length === 0 ? (
                <EmptyState icon={Laptop} title="Aún no hay terminales" description="Registra una terminal para los próximos turnos." />
            ) : (
                <ul className="divide-y divide-slate-100">
                    {terminales.map((t) => {
                        const estado = estadoAgente(t);
                        return (
                            <li key={t.id_terminal} className="flex flex-col gap-3 px-5 py-4 transition-colors hover:bg-brand-soft/30 sm:flex-row sm:items-center">
                                <IconoTile icon={Laptop} tone={estado.valor === 'conectada' ? 'success' : 'brand'} size="lg" />
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="font-medium text-slate-900">{t.nombre}</p>
                                        <span className="font-mono text-xs text-slate-400">{t.codigo}</span>
                                        <EstadoConexion valor={estado.valor} etiqueta={estado.etiqueta} />
                                    </div>
                                    <p className="mt-0.5 text-sm text-slate-500">
                                        {t.impresora ? `Imprime en ${t.impresora.nombre}` : 'Sin impresora predeterminada'} · {t.impresion_automatica ? 'Impresión automática' : 'Impresión manual'}
                                    </p>
                                    <p className="mt-0.5 text-xs text-slate-500">
                                        {t.vinculado_en ? `Último contacto: ${fechaHora(t.ultimo_contacto_en) ?? 'sin contacto'}` : 'Genera un código para vincular esta terminal'}
                                        {t.impresora_sistema_nombre ? ` · Cola Windows: ${t.impresora_sistema_nombre}` : ''}
                                    </p>
                                </div>
                                <div className="flex shrink-0 items-center gap-1.5">
                                    {t.vinculado_en ? (
                                        <Button size="sm" variant="danger-ghost" icon={Unplug} onClick={() => onRevocar(t)}>Revocar</Button>
                                    ) : (
                                        <Button size="sm" icon={Link2} onClick={() => onVincular(t.id_terminal)}>Vincular agente</Button>
                                    )}
                                    <IconButton icon={Pencil} label={`Editar ${t.nombre}`} onClick={() => onEditar(t)} />
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}
        </Card>
    );
}
