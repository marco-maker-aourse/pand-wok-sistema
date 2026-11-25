<?php

namespace Tests\Feature\Restaurante;

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** El listado de usuarios pagina en el servidor; la búsqueda debe abarcar todas las páginas. */
class UsuariosBusquedaTest extends TestCase
{
    use PreparaOperacionRestaurante;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararOperacionRestaurante();
        foreach (['id_tipo_documento INTEGER', 'num_documento TEXT', 'pin_cajero TEXT'] as $columna) {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE seguridad.usuarios ADD COLUMN {$columna}");
        }
    }

    public function test_buscar_filtra_usuarios_en_el_servidor(): void
    {
        $admin = User::query()->findOrFail(10);
        $otro = User::query()->where('id_usuario', '!=', 10)->firstOrFail();

        $this->actingAs($admin)->get('/usuarios?buscar='.urlencode(mb_strtolower(mb_substr($otro->nombres, 0, 4))))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Usuarios/Index')
                ->where('filters.buscar', mb_strtolower(mb_substr($otro->nombres, 0, 4)))
                ->where('usuarios.data', fn ($filas) => collect($filas)->pluck('id_usuario')->contains($otro->id_usuario)
                    && collect($filas)->every(fn ($fila) => str_contains(mb_strtolower($fila['nombres'].' '.$fila['email'].' '.$fila['num_documento']), mb_strtolower(mb_substr($otro->nombres, 0, 4))))));
    }
}
