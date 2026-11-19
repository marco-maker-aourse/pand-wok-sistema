<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Impresora;
use App\Services\Impresion\CloudPrntService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CloudPrntController extends Controller
{
    public function __invoke(Request $request, int $impresora, CloudPrntService $cola): Response
    {
        abort_unless($request->isSecure(), 403);
        abort_unless(in_array($request->method(), ['POST', 'GET', 'DELETE'], true), 405);

        $dispositivo = Impresora::withoutGlobalScopes()->where('activo', true)->find($impresora);
        $secreto = $dispositivo?->cloudprnt_clave_hash;
        abort_unless(
            $secreto && $request->getUser() === (string) $impresora
                && hash_equals($secreto, hash('sha256', $request->getPassword() ?? '')),
            401,
            '',
            ['WWW-Authenticate' => 'Basic realm="CloudPRNT"'],
        );

        $token = $request->query('token', $request->header('X-Star-Token'));
        if ($request->isMethod('POST')) {
            $status = $this->codigo($request->input('statusCode'));
            abort_if($status === null, 400);
            $trabajo = $cola->consultar($dispositivo, $status, $request->boolean('printingInProgress'));

            return response()->json($trabajo === null ? ['jobReady' => false] : [
                'jobReady' => true,
                'mediaTypes' => [CloudPrntService::FORMATO],
                'jobToken' => (string) $trabajo,
            ])->header('Cache-Control', 'no-store');
        }

        abort_unless(is_string($token) && ctype_digit($token) && strlen($token) <= 18, 404);
        if ($request->isMethod('DELETE')) {
            $status = $this->codigo($request->query('code'));
            abort_if($status === null, 400);
            $cola->confirmar($dispositivo, (int) $token, $status);

            return response('', 200)->header('Cache-Control', 'no-store');
        }

        abort_unless($request->query('type') === CloudPrntService::FORMATO, 415);
        $contenido = $cola->descargar($dispositivo, (int) $token);

        return response($contenido, 200, ['Content-Type' => CloudPrntService::FORMATO, 'Cache-Control' => 'no-store']);
    }

    private function codigo(mixed $valor): ?int
    {
        if (! is_scalar($valor)) {
            return null;
        }
        if ($valor === 'OK') {
            return 200;
        }

        return preg_match('/^(\d{3,4})(?:\s|$)/', (string) $valor, $partes) ? (int) $partes[1] : null;
    }
}
