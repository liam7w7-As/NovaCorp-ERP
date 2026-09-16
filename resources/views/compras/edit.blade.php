@extends('layouts.app')

@section('title', 'Editar Compra ' . $compra->numero)

@section('content')
@include('compras._form', ['action' => route('compras.update', $compra), 'method' => 'PUT', 'compra' => $compra, 'proveedores' => $proveedores, 'fechaHoy' => date('Y-m-d')])
@endsection
