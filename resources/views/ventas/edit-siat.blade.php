@extends('layouts.app')

@section('title', 'Editar Venta SIAT ' . $venta->numero)

@section('content')
@if($errors->any())
  <div style="background:var(--gc-rojo-suave); color:var(--gc-rojo); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px;">{{ $errors->first() }}</div>
@endif
<div class="card-giseca" style="max-width:560px;">
  <p style="font-size:12.5px; color:var(--gc-gris);">Esta venta viene del Libro de Ventas del SIAT y no tiene detalle de productos, así que no afecta el stock.</p>
  <form method="POST" action="{{ route('ventas.update', $venta) }}">
    @csrf @method('PUT')
    <div style="display:grid; gap:10px;">
      <div><label class="form-label-giseca">Cliente</label><input name="cliente_nombre" class="form-control-giseca" value="{{ old('cliente_nombre', $venta->cliente_nombre) }}" required></div>
      <div><label class="form-label-giseca">Fecha</label><input type="date" name="fecha" class="form-control-giseca" value="{{ old('fecha', $venta->fecha->format('Y-m-d')) }}" required></div>
      <div><label class="form-label-giseca">N° Factura SIAT</label><input name="numero_factura_siat" class="form-control-giseca" value="{{ old('numero_factura_siat', $venta->numero_factura_siat) }}"></div>
      <div><label class="form-label-giseca">Importe total</label><input type="number" step="0.01" min="0" name="total" class="form-control-giseca" value="{{ old('total', $venta->total) }}" required></div>
      <div><label class="form-label-giseca">Base débito fiscal</label><input type="number" step="0.01" min="0" name="base_df" class="form-control-giseca" value="{{ old('base_df', $venta->base_df) }}"></div>
      <div><label class="form-label-giseca">Débito fiscal</label><input type="number" step="0.01" min="0" name="debito_fiscal" class="form-control-giseca" value="{{ old('debito_fiscal', $venta->debito_fiscal) }}"></div>
    </div>
    <div style="display:flex; gap:8px; margin-top:16px;">
      <button class="btn-giseca btn-primario">Guardar cambios</button>
      <a href="{{ route('ventas.index') }}" class="btn-giseca btn-outline">Cancelar</a>
    </div>
  </form>
</div>
@endsection
