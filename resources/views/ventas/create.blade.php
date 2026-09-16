@extends('layouts.app')

@section('title', 'Nueva Venta')

@section('content')
@if (! empty($leadOrigen))
    <div style="background:var(--gc-info-suave); color:var(--gc-info); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px;">
        <i class="bi bi-whatsapp"></i> Venta desde el lead <strong>{{ $leadOrigen->nombre ?: $leadOrigen->telefono }}</strong>: completa los items y guarda.
    </div>
@endif
@include('ventas._form', ['action' => route('ventas.store'), 'method' => 'POST', 'venta' => null, 'clientes' => $clientes, 'fechaHoy' => $fechaHoy, 'clientePreseleccionado' => $clientePreseleccionado ?? null, 'leadOrigen' => $leadOrigen ?? null])
@endsection
