@extends('layouts.app')

@section('title', 'Nueva Venta')

@section('content')
@include('ventas._form', ['action' => route('ventas.store'), 'method' => 'POST', 'venta' => null, 'clientes' => $clientes, 'fechaHoy' => $fechaHoy])
@endsection
