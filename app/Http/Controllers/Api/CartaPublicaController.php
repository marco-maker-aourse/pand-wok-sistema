<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Restaurante\CartaPublicaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Carta digital pública (sin sesión, solo lectura). La consume la web de la carta para
 * mostrar precios y agotados reales de cada sucursal. Caché corta para no cargar la base.
 */
class CartaPublicaController extends Controller
{
    private const SEGUNDOS_CACHE = 60;

    public function __construct(private readonly CartaPublicaService $carta) {}

    public function index(): JsonResponse
    {
        return $this->responder(Cache::remember('restaurante:carta-publica:todas', self::SEGUNDOS_CACHE, fn () => $this->carta->carta()));
    }

    public function sucursal(string $slug): JsonResponse
    {
        $carta = Cache::remember("restaurante:carta-publica:{$slug}", self::SEGUNDOS_CACHE, fn () => $this->carta->carta($slug));
        abort_if($carta === null, 404, 'Sucursal no encontrada.');

        return $this->responder($carta);
    }

    private function responder(array $carta): JsonResponse
    {
        return response()->json($carta)
            ->header('Cache-Control', 'public, max-age='.self::SEGUNDOS_CACHE);
    }
}
