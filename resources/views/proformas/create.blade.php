@extends('layouts.app')

@section('title', 'Nueva Proforma')

@section('content')
@include('proformas._form', ['action' => route('proformas.store'), 'method' => 'POST', 'proforma' => null, 'clientes' => $clientes, 'fechaHoy' => $fechaHoy, 'fechaValidez' => $fechaValidez])
@endsection
