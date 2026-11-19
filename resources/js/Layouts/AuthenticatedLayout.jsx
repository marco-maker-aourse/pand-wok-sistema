import { usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Menu, Search, WifiOff } from 'lucide-react';
import { navigationModules } from '@/modules';
import { Confirmador, Kbd, Toaster } from '@/Components/ui';
import useAtajoTeclado from '@/hooks/useAtajoTeclado';
import useConexion from '@/hooks/useConexion';
import MenuUsuario from './navegacion/MenuUsuario';
import PaletaComandos from './navegacion/PaletaComandos';
import SelectorSucursal from './navegacion/SelectorSucursal';
import BarraLateral from './navegacion/BarraLateral';
import { filtrarNavegacionPorRol } from './navegacion/filtrarNavegacionPorRol';

const CLAVE_MENU_COMPACTO = 'svamm_menu_compacto';

function leerPreferenciaCompacto() {
    try {
        return localStorage.getItem(CLAVE_MENU_COMPACTO) === '1';
    } catch {
        return false;
    }
}

function iniciales(nombre) {
    return (nombre || 'Usuario')
        .split(' ')
        .filter(Boolean)
        .map((palabra) => palabra[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

/**
 * Estructura de las pantallas autenticadas: menú lateral filtrado por rol,
 * barra superior con el título de la sección y área de contenido.
 * `fullWidth` quita el ancho máximo (POS y pantallas operativas densas).
 */
export default function AuthenticatedLayout({ header, children, fullWidth = false }) {
    const { props, url = '/' } = usePage();
    const organizacion = props.organization ?? props.empresa ?? {};
    const user = props.auth?.user ?? {};

    const [abiertoMovil, setAbiertoMovil] = useState(false);
    const [compacto, setCompacto] = useState(leerPreferenciaCompacto);
    const [paletaAbierta, setPaletaAbierta] = useState(false);
    const enLinea = useConexion();

    useAtajoTeclado('mod+k', () => setPaletaAbierta((abierta) => !abierta));

    useEffect(() => {
        try {
            localStorage.setItem(CLAVE_MENU_COMPACTO, compacto ? '1' : '0');
        } catch {
            // Sin almacenamiento disponible solo se pierde la preferencia.
        }
    }, [compacto]);

    useEffect(() => {
        if (!abiertoMovil) return undefined;
        const cerrarConEscape = (event) => event.key === 'Escape' && setAbiertoMovil(false);
        window.addEventListener('keydown', cerrarConEscape);
        return () => window.removeEventListener('keydown', cerrarConEscape);
    }, [abiertoMovil]);

    const rolNombre = user.rol_nombre || user.rol || '';
    const rolId = Number(user.id_rol || 0);
    const modulos = useMemo(() => filtrarNavegacionPorRol(navigationModules, rolNombre, rolId), [rolNombre, rolId]);

    const nombreUsuario = user.nombres || user.name || 'Usuario';
    const usuario = { nombre: nombreUsuario, email: user.email || '', rol: rolNombre, iniciales: iniciales(nombreUsuario) };

    return (
        <div className="min-h-screen bg-canvas text-slate-800">
            <a href="#contenido" className="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[60] focus:rounded-md focus:bg-white focus:px-3 focus:py-2 focus:text-sm focus:shadow">
                Saltar al contenido
            </a>

            {abiertoMovil && <div className="fixed inset-0 z-40 bg-slate-900/30 lg:hidden" onClick={() => setAbiertoMovil(false)} aria-hidden="true" />}

            <BarraLateral
                modulos={modulos}
                url={url}
                organizacion={organizacion}
                compacto={compacto}
                onToggleCompacto={() => setCompacto((valor) => !valor)}
                abiertoMovil={abiertoMovil}
                onCerrarMovil={() => setAbiertoMovil(false)}
            />

            <div className={compacto ? 'lg:pl-16' : 'lg:pl-60'}>
                <header className="sticky top-0 z-30 flex h-16 items-center gap-3 bg-canvas/85 px-4 backdrop-blur-md sm:px-6">
                    <button
                        type="button"
                        className="grid h-9 w-9 place-items-center rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50 lg:hidden"
                        onClick={() => setAbiertoMovil(true)}
                        aria-label="Abrir menú de navegación"
                        aria-expanded={abiertoMovil}
                    >
                        <Menu className="h-4 w-4" aria-hidden="true" />
                    </button>
                    <p className="min-w-0 flex-1 truncate text-lg font-semibold tracking-[-0.015em] text-slate-900 sm:text-xl">{header ?? 'Panel de control'}</p>
                    <button
                        type="button"
                        onClick={() => setPaletaAbierta(true)}
                        className="hidden h-10 w-72 items-center gap-2 rounded-full bg-white px-4 text-sm text-slate-500 shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.06] transition-shadow hover:shadow-[var(--shadow-elevado)] md:flex"
                    >
                        <Search className="h-4 w-4" aria-hidden="true" />
                        <span className="flex-1 text-left">Ir a un módulo…</span>
                        <Kbd>Ctrl K</Kbd>
                    </button>
                    <SelectorSucursal sucursal={props.sucursal} />
                    <MenuUsuario usuario={usuario} />
                </header>

                {!enLinea && (
                    <div role="alert" className="flex items-center gap-2 border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900 sm:px-6">
                        <WifiOff className="h-4 w-4 shrink-0" aria-hidden="true" />
                        Sin conexión a internet. Las ventas y cambios no se podrán guardar hasta recuperarla.
                    </div>
                )}

                <main id="contenido" className={`animate-entrada ${fullWidth ? 'w-full p-4 sm:p-6' : 'mx-auto w-full max-w-[1440px] p-4 sm:p-6'}`}>
                    {children}
                </main>
            </div>
            <Toaster />
            <Confirmador />
            <PaletaComandos modulos={modulos} abierta={paletaAbierta} onCerrar={() => setPaletaAbierta(false)} />
        </div>
    );
}
