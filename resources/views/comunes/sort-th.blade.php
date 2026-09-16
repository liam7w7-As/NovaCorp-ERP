{{-- Cabecera ordenable. Params: $campo, $etiqueta, $sort, $dir, $clase (opcional) --}}
@php
  $activo = ($sort ?? '') === $campo;
  $siguiente = $activo && ($dir ?? 'asc') === 'asc' ? 'desc' : 'asc';
  $url = request()->fullUrlWithQuery(['sort' => $campo, 'dir' => $siguiente]);
@endphp
<th @if(!empty($clase))class="{{ $clase }}"@endif><a href="{{ $url }}" style="color:inherit; white-space:nowrap;">{{ $etiqueta }} @if($activo)<i class="bi bi-caret-{{ ($dir ?? 'asc') === 'asc' ? 'up' : 'down' }}-fill"></i>@endif</a></th>
