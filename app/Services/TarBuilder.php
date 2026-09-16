<?php

namespace App\Services;

/**
 * Constructor mínimo de archivos .tar (formato ustar) en PHP puro,
 * sin depender de phar.readonly. Soporta nombres largos vía
 * campo prefix (hasta 155 + 100 caracteres).
 */
class TarBuilder
{
    public const MAX_POR_PAQUETE = 500;

    protected string $buffer = '';

    protected int $archivos = 0;

    public function agregar(string $nombre, string $contenido): self
    {
        $nombre = str_replace('\\', '/', $nombre);
        if ($nombre === '' || str_contains($nombre, "\0")) {
            throw new \InvalidArgumentException('Nombre de archivo inválido para el paquete.');
        }
        // USTAR: name (100) + prefix (155); se parte por la última '/' que quepa.
        $prefijo = '';
        if (strlen($nombre) > 100) {
            $corte = strrpos(substr($nombre, 0, strlen($nombre) - 100 + 155), '/');
            if ($corte === false || $corte > 155 || strlen($nombre) - $corte - 1 > 100) {
                throw new \InvalidArgumentException("Nombre demasiado largo para tar USTAR: {$nombre}");
            }
            $prefijo = substr($nombre, 0, $corte);
            $nombre = substr($nombre, $corte + 1);
        }
        $tam = strlen($contenido);

        $cabecera = str_pad($nombre, 100, "\0")
            .'0000644'."\0"
            .'0000000'."\0"
            .'0000000'."\0"
            .str_pad(decoct($tam), 11, '0', STR_PAD_LEFT)."\0"
            .str_pad(decoct(time()), 11, '0', STR_PAD_LEFT)."\0"
            .'        ' // checksum temporal (8 espacios)
            .'0'
            .str_repeat("\0", 100)
            .'ustar'."\0".'00'
            .str_repeat("\0", 32) // usuario
            .str_repeat("\0", 32) // grupo
            .str_repeat("\0", 8) // devmajor
            .str_repeat("\0", 8) // devminor
            .str_pad($prefijo, 155, "\0")
            .str_repeat("\0", 12);

        $suma = 0;
        for ($i = 0; $i < 512; $i++) {
            $suma += ord($cabecera[$i]);
        }
        $cabecera = substr_replace($cabecera, str_pad(decoct($suma), 6, '0', STR_PAD_LEFT)."\0 ", 148, 8);

        $this->buffer .= $cabecera.$contenido.str_repeat("\0", (512 - ($tam % 512)) % 512);
        $this->archivos++;

        return $this;
    }

    public function totalArchivos(): int
    {
        return $this->archivos;
    }

    public function contenidoTar(): string
    {
        return $this->buffer.str_repeat("\0", 1024);
    }

    public function contenidoTarGz(): string
    {
        $gz = gzencode($this->contenidoTar(), 9);
        if ($gz === false) {
            throw new \RuntimeException('No se pudo comprimir el paquete.');
        }

        return $gz;
    }
}
