<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Venta;

class TributarioController extends Controller
{
    public function index()
    {
        // Solo origen SIAT: son las únicas con datos fiscales oficiales del SIN
        $comprasSiat = Compra::where('origen_siat', true)->orderByDesc('id')->get();
        $ventasSiat = Venta::where('origen_siat', true)->where('estado', 'activa')->orderByDesc('id')->get();

        $creditoFiscal = (float) $comprasSiat->sum('credito_fiscal');
        $debitoFiscal = (float) $ventasSiat->sum('debito_fiscal');
        $saldo = round($debitoFiscal - $creditoFiscal, 2);

        return view('tributario.index', compact(
            'comprasSiat', 'ventasSiat', 'creditoFiscal', 'debitoFiscal', 'saldo'
        ));
    }
}
