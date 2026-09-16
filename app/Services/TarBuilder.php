<?php

namespace App\Services;

/**
 * Constructor mínimo de archivos .tar (formato ustar) en PHP puro,
 * sin depender de phar.readonly. Para nombres cortos (<100 car.).
 */
class TarBuilder
{
    protected string $buffer = '';

    public function agregar(string $nombre, string $contenido): self
    {
        $nombre = substr(str_replace('\\', '/', $nombre), 0, 99);
        $tam = strlen($contenido);

        $cabecera = str_pad($nombre, 100, "\0")
            .'0000777'."\0"
            .'0000000'."\0"
            .'0000000'."\0"
            .str_pad(decoct($tam), 11, '0', STR_PAD_LEFT)."\0"
            .str_pad(decoct(time()), 11, '0', STR_PAD_LEFT)."\0"
            .'        ' // checksum temporal (8 espacios)
            .'0'
            .str_repeat("\0", 100)
            .'ustar'."\0".'00'
            .str_repeat("\0", 247);

        $suma = 0;
        for ($i = 0; $i < 512; $i++) {
            $suma += ord($cabecera[$i]);
        }
        $cabecera = substr_replace($cabecera, str_pad(decoct($suma), 6, '0', STR_PAD_LEFT)."\0 ", 148, 8);

        $this->buffer .= $cabecera.$contenido.str_repeat("\0", (512 - ($tam % 512)) % 512);

        return $this;
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
