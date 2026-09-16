@extends('layouts.app')

@section('title', 'Editar ' . $comprobante->numero)

@section('content')
@if($errors->any())
  <div style="background:var(--gc-rojo-suave); color:var(--gc-rojo); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px;">{{ $errors->first() }}</div>
@endif
<div class="card-giseca" style="max-width:560px;">
  @if(!$comprobante->es_manual)
    <p style="font-size:12.5px; color:var(--gc-gris);">Comprobante ligado a un documento ({{ $comprobante->referencia }}). El <strong>monto total no se puede editar aquí</strong>: cambia automáticamente con el documento origen.</p>
  @endif
  <form method="POST" action="{{ route('comprobantes.update', $comprobante) }}">
    @csrf @method('PUT')
    <div style="display:grid; gap:10px;">
      <div><label class="form-label-giseca">Concepto</label><input name="concepto" class="form-control-giseca" value="{{ old('concepto', $comprobante->concepto) }}" required></div>
      <div><label class="form-label-giseca">{{ $comprobante->tipo === 'egreso' ? 'Beneficiario (a quién se le paga)' : 'Recibido de (quién nos paga)' }} *</label><input name="entidad" class="form-control-giseca" value="{{ old('entidad', $comprobante->entidad) }}" required></div>
      <div><label class="form-label-giseca">Monto total {{ $comprobante->es_manual ? '' : '(bloqueado: viene del documento)' }}</label><input type="number" step="0.01" min="0.01" name="monto" class="form-control-giseca" value="{{ old('monto', $comprobante->monto) }}" {{ $comprobante->es_manual ? 'required' : 'disabled' }}></div>
      <div><label class="form-label-giseca">Fecha</label><input type="date" name="fecha" class="form-control-giseca" value="{{ old('fecha', $comprobante->fecha->format('Y-m-d')) }}" required></div>
      <div><label class="form-label-giseca">Documento relacionado</label><input name="referencia" class="form-control-giseca" value="{{ old('referencia', $comprobante->referencia) }}"></div>
      <div><label class="form-label-giseca">Nota / Observación</label><textarea name="nota" class="form-control-giseca" rows="2">{{ old('nota', $comprobante->nota) }}</textarea></div>
    </div>
    <div style="display:flex; gap:8px; margin-top:16px;">
      <button class="btn-giseca btn-primario">Guardar cambios</button>
      <a href="{{ route('comprobantes.index', ['ver' => $comprobante->id]) }}" class="btn-giseca btn-outline">Cancelar</a>
    </div>
  </form>
</div>
@endsection
