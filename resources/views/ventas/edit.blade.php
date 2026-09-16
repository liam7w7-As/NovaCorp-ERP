@extends('layouts.app')

@section('title', 'Editar Venta ' . $venta->numero)

@section('content')
@include('ventas._form', ['action' => route('ventas.update', $venta), 'method' => 'PUT', 'venta' => $venta, 'clientes' => $clientes, 'fechaHoy' => date('Y-m-d')])
@endsection
