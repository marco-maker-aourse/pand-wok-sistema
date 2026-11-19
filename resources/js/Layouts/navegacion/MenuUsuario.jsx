import { Link } from '@inertiajs/react';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react';
import { ChevronDown, LogOut } from 'lucide-react';

/** Menú de cuenta en la cabecera. Solo ofrece acciones con ruta real (cerrar sesión). */
export default function MenuUsuario({ usuario }) {
    return (
        <Menu as="div" className="relative">
            <MenuButton className="flex h-10 items-center gap-2.5 rounded-full pr-2 pl-1 text-left transition-colors hover:bg-white data-open:bg-white">
                <span className="grid h-8 w-8 place-items-center rounded-full bg-brand text-xs font-semibold text-on-brand ring-2 ring-white" aria-hidden="true">
                    {usuario.iniciales}
                </span>
                <span className="hidden max-w-36 min-w-0 sm:block">
                    <span className="block truncate text-sm leading-tight font-medium text-slate-900">{usuario.nombre}</span>
                    {usuario.rol && <span className="block truncate text-xs leading-tight text-slate-500">{usuario.rol}</span>}
                </span>
                <ChevronDown className="h-4 w-4 text-slate-400" aria-hidden="true" />
                <span className="sr-only">Abrir menú de cuenta</span>
            </MenuButton>
            <MenuItems
                anchor={{ to: 'bottom end', gap: 6 }}
                transition
                className="z-50 w-60 origin-top-right rounded-xl border border-slate-200 bg-white p-1.5 shadow-[var(--shadow-flotante)] transition duration-150 ease-out focus:outline-none data-closed:scale-95 data-closed:opacity-0"
            >
                <div className="border-b border-slate-100 px-2.5 pt-1.5 pb-2.5">
                    <p className="truncate text-sm font-medium text-slate-900">{usuario.nombre}</p>
                    {usuario.email && <p className="truncate text-xs text-slate-500">{usuario.email}</p>}
                </div>
                <MenuItem>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        className="mt-1 flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-slate-700 data-focus:bg-rose-50 data-focus:text-rose-700"
                    >
                        <LogOut className="h-4 w-4" aria-hidden="true" />
                        Cerrar sesión
                    </Link>
                </MenuItem>
            </MenuItems>
        </Menu>
    );
}
