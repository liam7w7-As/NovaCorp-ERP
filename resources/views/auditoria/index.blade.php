@extends('layouts.app')

@section('title', 'Auditoría')

@section('content')
@php $badgeAccion = ['creado' => 'estado-aprobada', 'actualizado' => 'estado-enviada', 'eliminado' => 'estado-rechazada', 'restaurado' => 'estado-convertida']; @endphp

<div class="card-giseca" style="margin-bottom:16px;">
  <form method="GET" action="{{ route('auditoria.index') }}" style="margin:0; display:flex; gap:8px; flex-wrap:wrap;">
    <select name="modelo" class="form-control-giseca" style="width:200px;" onchange="this.form.submit()">
      <option value="">Todos los módulos</option>
      @foreach($modelos as $m)
        <option value="{{ $m }}" {{ $modelo === $m ? 'selected' : '' }}>{{ $m }}</option>
      @endforeach
    </select>
    <select name="accion" class="form-control-giseca" style="width:170px;" onchange="this.form.submit()">
      <option value="">Todas las acciones</option>
      @foreach(['creado', 'actualizado', 'eliminado', 'restaurado'] as $a)
        <option value="{{ $a }}" {{ $accion === $a ? 'selected' : '' }}>{{ ucfirst($a) }}</option>
      @endforeach
    </select>
    <input type="text" name="q" class="form-control-giseca" style="width:240px;" placeholder="Usuario, descripción o ID..." value="{{ $q }}">
  </form>
</div>

<div class="card-giseca" style="padding:0; overflow:hidden;">
  <table class="tabla-giseca">
    <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Módulo</th><th>Registro</th><th>IP</th></tr></thead>
    <tbody>
      @forelse($registros as $r)
        <tr>
          <td style="white-space:nowrap;">{{ $r->created_at->format('Y-m-d H:i') }}</td>
          <td>{{ $r->usuario_nombre }}</td>
          <td><span class="estado {{ $badgeAccion[$r->accion] ?? 'estado-borrador' }}">{{ strtoupper($r->accion) }}</span></td>
          <td>{{ $r->modelo }}</td>
          <td>{{ $r->descripcion ?: '#'.$r->modelo_id }}</td>
          <td style="color:var(--gc-gris-claro);">{{ $r->ip }}</td>
        </tr>
      @empty
        <tr><td colspan="6" style="text-align:center; color:var(--gc-gris-claro); padding:40px;"><i class="bi bi-clock-history" style="font-size:30px; display:block; margin-bottom:8px; opacity:.5;"></i>Sin movimientos registrados.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

@if($registros->hasPages())
  <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:14px;">
    @if(!$registros->onFirstPage())<a class="btn-giseca btn-outline btn-sm" href="{{ $registros->previousPageUrl() }}">← Anterior</a>@endif
    @if($registros->hasMorePages())<a class="btn-giseca btn-outline btn-sm" href="{{ $registros->nextPageUrl() }}">Siguiente →</a>@endif
  </div>
@endif
@endsection
