@extends('layouts.app')

@section('title', 'Editar Compra SIAT ' . $compra->numero)

@section('content')
@if($errors->any())
  <div style="background:var(--gc-rojo-suave); color:var(--gc-rojo); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px;">{{ $errors->first() }}</div>
@endif
<div class="card-giseca" style="max-width:560px;">
  <p style="font-size:12.5px; color:var(--gc-gris);">Esta compra viene del Libro de Compras del SIAT y no tiene detalle de productos, así que no afecta el stock.</p>
  <form method="POST" action="{{ route('compras.update', $compra) }}">
    @csrf @method('PUT')
    <div style="display:grid; gap:10px;">
      <div><label class="form-label-giseca">Proveedor</label><input name="proveedor_nombre" class="form-control-giseca" value="{{ old('proveedor_nombre', $compra->proveedor_nombre) }}" required></div>
      <div><label class="form-label-giseca">Fecha</label><input type="date" name="fecha" class="form-control-giseca" value="{{ old('fecha', $compra->fecha->format('Y-m-d')) }}" required></div>
      <div><label class="form-label-giseca">N° Factura SIAT</label><input name="numero_factura_siat" class="form-control-giseca" value="{{ old('numero_factura_siat', $compra->numero_factura_siat) }}"></div>
      <div><label class="form-label-giseca">Importe total</label><input type="number" step="0.01" min="0" name="total" class="form-control-giseca" value="{{ old('total', $compra->total) }}" required></div>
      <div><label class="form-label-giseca">Base crédito fiscal</label><input type="number" step="0.01" min="0" name="base_cf" class="form-control-giseca" value="{{ old('base_cf', $compra->base_cf) }}"></div>
      <div><label class="form-label-giseca">Crédito fiscal</label><input type="number" step="0.01" min="0" name="credito_fiscal" class="form-control-giseca" value="{{ old('credito_fiscal', $compra->credito_fiscal) }}"></div>
    </div>
    <div style="display:flex; gap:8px; margin-top:16px;">
      <button class="btn-giseca btn-primario">Guardar cambios</button>
      <a href="{{ route('compras.index') }}" class="btn-giseca btn-outline">Cancelar</a>
    </div>
  </form>
</div>
@endsection
