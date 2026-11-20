<?php

namespace Tests\Feature\Restaurante;

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** La app Android del agente se ofrece en Impresoras y terminales, independiente del instalador Windows. */
class DescargaAgenteAndroidTest extends TestCase
{
    use PreparaOperacionRestaurante;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararOperacionRestaurante();
    }

    public function test_ofrece_el_apk_publicado_con_su_version_y_huella(): void
    {
        config()->set('print_agent.installer_url', null);
        config()->set('print_agent.android_url', null);
        $datos = json_decode(file_get_contents(public_path('descargas/agente-impresion-android.json')), true);

        $this->actingAs(User::findOrFail(10))->get('/impresoras')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Configuracion/Impresoras')
            ->where('installerUrl', null)
            ->where('android.url', asset('descargas/agente-impresion-android.apk'))
            ->where('android.version', $datos['version'])
            ->where('android.sha256', $datos['sha256']));

        $this->assertSame($datos['sha256'], hash_file('sha256', public_path('descargas/agente-impresion-android.apk')), 'La huella publicada corresponde al APK.');
    }

    public function test_una_url_https_externa_tiene_prioridad_y_se_rechaza_http(): void
    {
        config()->set('print_agent.android_url', 'https://descargas.ejemplo.test/agente.apk');
        config()->set('print_agent.android_version', '0.7.0');
        config()->set('print_agent.android_sha256', str_repeat('a', 64));
        $this->actingAs(User::findOrFail(10))->get('/impresoras')->assertInertia(fn (Assert $page) => $page
            ->where('android.url', 'https://descargas.ejemplo.test/agente.apk')->where('android.version', '0.7.0'));

        config()->set('print_agent.android_url', 'http://inseguro.test/agente.apk');
        $this->actingAs(User::findOrFail(10))->get('/impresoras')->assertInertia(fn (Assert $page) => $page
            ->where('android.url', asset('descargas/agente-impresion-android.apk')));
    }
}
