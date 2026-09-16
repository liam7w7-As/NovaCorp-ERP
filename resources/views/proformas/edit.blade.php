@extends('layouts.app')

@section('title', 'Editar Proforma ' . $proforma->numero)

@section('content')
@include('proformas._form', ['action' => route('proformas.update', $proforma), 'method' => 'PUT', 'proforma' => $proforma, 'clientes' => $clientes, 'fechaHoy' => date('Y-m-d'), 'fechaValidez' => date('Y-m-d', strtotime('+15 days'))])
@endsection
