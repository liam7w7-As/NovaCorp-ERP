@extends('layouts.app')

@section('title', 'Nueva Compra')

@section('content')
@include('compras._form', ['action' => route('compras.store'), 'method' => 'POST', 'compra' => null, 'proveedores' => $proveedores, 'fechaHoy' => $fechaHoy])
@endsection
