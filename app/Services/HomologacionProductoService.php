<?php

namespace App\Services;

use App\Models\CatalogoSin;
use App\Models\Producto;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class HomologacionProductoService
{
    /** @var list<string> */
    private const PALABRAS_VACIAS = [
        'de', 'del', 'la', 'las', 'el', 'los', 'para', 'por', 'con', 'sin',
        'un', 'una', 'y', 'o', 'en', 'tipo', 'marca', 'modelo',
    ];

    /**
     * @param  Collection<int, CatalogoSin>  $catalogo
     * @return list<array{codigo:string, descripcion:string, actividad_economica:?string, confianza:int, nivel:string}>
     */
    public function sugerencias(Producto $producto, Collection $catalogo, int $limite = 3): array
    {
        $textoProducto = implode(' ', array_filter([
            $producto->descripcion,
            $producto->equivalente,
            $producto->marca,
        ]));
        $tokensProducto = $this->tokens($textoProducto);

        if ($tokensProducto === []) {
            return [];
        }

        return $catalogo
            ->map(function (CatalogoSin $item) use ($textoProducto, $tokensProducto): array {
                $tokensCatalogo = $this->tokens($item->descripcion);
                $interseccion = count(array_intersect($tokensProducto, $tokensCatalogo));
                $coberturaProducto = $interseccion / max(1, count($tokensProducto));
                $coberturaCatalogo = $interseccion / max(1, count($tokensCatalogo));
                similar_text($this->normalizar($textoProducto), $this->normalizar($item->descripcion), $similitud);

                $confianza = (int) round(min(99, max(0,
                    ($coberturaProducto * 35)
                    + ($coberturaCatalogo * 45)
                    + ($similitud * 0.20)
                )));

                $extra = is_array($item->extra) ? $item->extra : [];

                return [
                    'codigo' => (string) $item->codigo,
                    'descripcion' => (string) $item->descripcion,
                    'actividad_economica' => filled($extra['actividad_economica'] ?? null)
                        ? (string) $extra['actividad_economica']
                        : null,
                    'confianza' => $confianza,
                    'nivel' => $confianza >= 70 ? 'alta' : ($confianza >= 45 ? 'media' : 'baja'),
                ];
            })
            ->filter(fn (array $sugerencia): bool => $sugerencia['confianza'] >= 20)
            ->sortByDesc('confianza')
            ->take($limite)
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function tokens(string $texto): array
    {
        return collect(explode(' ', $this->normalizar($texto)))
            ->filter(fn (string $token): bool => mb_strlen($token) >= 3 && ! in_array($token, self::PALABRAS_VACIAS, true))
            ->map(function (string $token): string {
                if (mb_strlen($token) > 6 && str_ends_with($token, 'es')) {
                    return mb_substr($token, 0, -2);
                }
                if (mb_strlen($token) > 5 && str_ends_with($token, 's')) {
                    return mb_substr($token, 0, -1);
                }

                return $token;
            })
            ->unique()
            ->values()
            ->all();
    }

    private function normalizar(string $texto): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(mb_strtolower($texto))) ?? '');
    }
}
