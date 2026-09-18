@extends('layouts.app')

@section('title', 'Cuentas por Cobrar y Pagar')

@section('content')
    <div class="cobranza-kpis">
        <div class="kpi azul">
            <div class="label"><i class="bi bi-hourglass-split"></i> Por cobrar</div>
            <div class="value">Bs {{ formatoMoneda($totalCobrar) }}</div>
        </div>
        <div class="kpi rojo">
            <div class="label"><i class="bi bi-exclamation-triangle"></i> Vencido</div>
            <div class="value">Bs {{ formatoMoneda($totalVencido) }}</div>
        </div>
        <div class="kpi naranja">
            <div class="label"><i class="bi bi-calendar-week"></i> Por vencer</div>
            <div class="value">{{ $porVencer }}</div>
        </div>
        <div class="kpi verde">
            <div class="label"><i class="bi bi-cash-coin"></i> Cobrado hoy</div>
            <div class="value">Bs {{ formatoMoneda($cobradoHoy) }}</div>
        </div>
        <div class="kpi info">
            <div class="label"><i class="bi bi-wallet2"></i> Por pagar</div>
            <div class="value">Bs {{ formatoMoneda($totalPagar) }}</div>
        </div>
    </div>

    <form method="GET" action="{{ route('cuentas.index') }}" class="cobranza-toolbar">
        <div class="cobranza-buscador">
            <i class="bi bi-search"></i>
            <input name="q" class="form-control-giseca" value="{{ $q }}" placeholder="Buscar venta, cliente, compra o proveedor">
        </div>
        <button class="btn-giseca btn-outline btn-sm"><i class="bi bi-funnel"></i> Filtrar</button>
        @if ($q !== '')
            <a class="btn-giseca btn-outline btn-sm" href="{{ route('cuentas.index') }}"><i class="bi bi-x-lg"></i> Limpiar</a>
        @endif
    </form>

    <div class="cobranza-grid">
        <section class="card-giseca cobranza-panel">
            <div class="cobranza-panel-head">
                <div>
                    <h6>Por cobrar</h6>
                    <span>{{ $porCobrar->total() }} venta(s) con saldo</span>
                </div>
            </div>
            <div class="cobranza-scroll">
                <table class="tabla-giseca cobranza-tabla">
                    <thead>
                        <tr>
                            <th>Venta</th>
                            <th>Cliente</th>
                            <th>Próxima cuota</th>
                            <th>Estado</th>
                            <th class="text-end">Saldo</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($porCobrar as $v)
                            @php
                                $proxima = $v->proxima_cuota;
                                $estado = $v->estado_cobranza;
                                $estadoLabel = [
                                    'cobrada' => 'Cobrada',
                                    'sin_plan' => 'Sin plan',
                                    'vencida' => 'Vencida',
                                    'por_vencer' => 'Por vencer',
                                    'vigente' => 'Vigente',
                                ][$estado] ?? ucfirst($estado);
                                $estadoClase = [
                                    'vencida' => 'estado-vencida',
                                    'por_vencer' => 'estado-por-vencer',
                                    'vigente' => 'estado-vigente',
                                    'sin_plan' => 'estado-sin-plan',
                                    'cobrada' => 'estado-cobrada',
                                ][$estado] ?? 'estado-sin-plan';
                                $saldoCuota = $proxima ? $proxima->saldo : $v->saldo;
                            @endphp
                            <tr>
                                <td>
                                    <span class="codigo-chip">{{ $v->numero }}</span>
                                    <div class="cobranza-muted">{{ $v->fecha->format('Y-m-d') }}</div>
                                </td>
                                <td>
                                    <strong>{{ \Illuminate\Support\Str::limit($v->cliente_nombre, 26) }}</strong>
                                    <div class="cobranza-muted">{{ ucfirst($v->modalidad) }}</div>
                                </td>
                                <td>
                                    @if ($proxima)
                                        <strong>#{{ $proxima->numero }} · Bs {{ formatoMoneda($saldoCuota) }}</strong>
                                        <div class="cobranza-muted">{{ $proxima->fecha_vencimiento->format('Y-m-d') }}</div>
                                    @else
                                        <strong>Saldo directo</strong>
                                        <div class="cobranza-muted">Sin cuotas</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="cobranza-estado {{ $estadoClase }}">{{ $estadoLabel }}</span>
                                    @if ($v->total_vencido > 0)
                                        <div class="cobranza-muted">Bs {{ formatoMoneda($v->total_vencido) }} vencido</div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <strong>Bs {{ formatoMoneda($v->saldo) }}</strong>
                                    <div class="cobranza-muted">Total Bs {{ formatoMoneda($v->total) }}</div>
                                </td>
                                <td class="text-end">
                                    <button
                                        type="button"
                                        class="btn-giseca btn-primario btn-sm abrir-cobro"
                                        data-url="{{ route('cuentas.cobrar', $v) }}"
                                        data-numero="{{ $v->numero }}"
                                        data-saldo="{{ number_format($v->saldo, 2, '.', '') }}"
                                        data-cuota-id="{{ $proxima?->id }}"
                                        data-cuota-numero="{{ $proxima?->numero }}"
                                        data-cuota-saldo="{{ number_format($saldoCuota, 2, '.', '') }}">
                                        <i class="bi bi-cash-coin"></i> Cobrar
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="cobranza-empty">Sin saldos por cobrar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($porCobrar->hasPages())
                <div class="cobranza-paginacion">{{ $porCobrar->links() }}</div>
            @endif
        </section>

        <section class="card-giseca cobranza-panel">
            <div class="cobranza-panel-head">
                <div>
                    <h6>Por pagar</h6>
                    <span>{{ $porPagar->total() }} compra(s) con saldo</span>
                </div>
            </div>
            <div class="cobranza-scroll">
                <table class="tabla-giseca cobranza-tabla pagar">
                    <thead>
                        <tr>
                            <th>Compra</th>
                            <th>Proveedor</th>
                            <th>Fecha</th>
                            <th class="text-end">Saldo</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($porPagar as $c)
                            <tr>
                                <td><span class="codigo-chip">{{ $c->numero }}</span></td>
                                <td>{{ \Illuminate\Support\Str::limit($c->proveedor_nombre, 26) }}</td>
                                <td>{{ $c->fecha->format('Y-m-d') }}</td>
                                <td class="text-end fw-bold">Bs {{ formatoMoneda($c->saldo) }}</td>
                                <td class="text-end">
                                    <button
                                        type="button"
                                        class="btn-giseca btn-outline btn-sm abrir-pago"
                                        data-url="{{ route('cuentas.pagar', $c) }}"
                                        data-numero="{{ $c->numero }}"
                                        data-saldo="{{ number_format($c->saldo, 2, '.', '') }}">
                                        <i class="bi bi-wallet2"></i> Pagar
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="cobranza-empty">Sin saldos por pagar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($porPagar->hasPages())
                <div class="cobranza-paginacion">{{ $porPagar->links() }}</div>
            @endif
        </section>
    </div>

    <div class="modal-giseca cobranza-modal" id="modalMov">
        <div class="modal-box">
            <h6 id="movTitulo">Registrar</h6>
            <form method="POST" action="" id="formMov">
                @csrf
                <input type="hidden" name="cuota_id" id="movCuotaId">
                <div class="cobranza-modal-grid">
                    <div>
                        <label class="form-label-giseca">Monto (saldo: Bs <span id="movSaldo"></span>)</label>
                        <input type="number" step="0.01" min="0.01" name="monto" id="movMonto" class="form-control-giseca" required>
                    </div>
                    <div>
                        <label class="form-label-giseca">Fecha</label>
                        <input type="date" name="fecha" id="movFecha" class="form-control-giseca" value="{{ now()->toDateString() }}">
                    </div>
                    <div>
                        <label class="form-label-giseca">Forma de pago</label>
                        <select name="metodo" class="form-control-giseca">
                            <option>Efectivo</option>
                            <option>Transferencia</option>
                            <option>QR</option>
                            <option>Tarjeta</option>
                            <option>Cheque</option>
                        </select>
                    </div>
                    <div id="movCuotaBox">
                        <label class="form-label-giseca">Cuota sugerida</label>
                        <input id="movCuotaTexto" class="form-control-giseca" readonly>
                    </div>
                    <div>
                        <label class="form-label-giseca">Referencia</label>
                        <input name="referencia" class="form-control-giseca" maxlength="120">
                    </div>
                    <div class="cobranza-modal-full">
                        <label class="form-label-giseca">Observaciones</label>
                        <textarea name="observaciones" class="form-control-giseca" rows="2"></textarea>
                    </div>
                </div>
                <div class="cobranza-modal-actions">
                    <button class="btn-giseca btn-primario">Guardar</button>
                    <button type="button" class="btn-giseca btn-outline" onclick="cerrarMovimiento()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .cobranza-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin-bottom:18px}.cobranza-kpis .kpi.rojo{border-top-color:var(--gc-rojo)}.cobranza-toolbar{display:flex;justify-content:flex-end;gap:8px;margin-bottom:14px;align-items:center;flex-wrap:wrap}.cobranza-buscador{position:relative;width:min(100%,420px)}.cobranza-buscador i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--gc-gris-claro)}.cobranza-buscador input{padding-left:34px}.cobranza-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(430px,.9fr);gap:16px;align-items:start}.cobranza-panel{padding:0;overflow:hidden}.cobranza-panel-head{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:14px 16px;border-bottom:1px solid var(--gc-borde)}.cobranza-panel-head h6{margin:0;font-size:14px}.cobranza-panel-head span,.cobranza-muted{font-size:10.5px;color:var(--gc-gris-claro);margin-top:3px}.cobranza-scroll{overflow-x:auto}.cobranza-tabla{min-width:760px}.cobranza-tabla.pagar{min-width:530px}.cobranza-empty{text-align:center;color:var(--gc-gris-claro);padding:34px}.cobranza-paginacion{padding:12px 16px;border-top:1px solid var(--gc-borde);overflow-x:auto}.cobranza-estado{display:inline-flex;align-items:center;border-radius:999px;padding:4px 8px;font-size:10.5px;font-weight:800}.estado-vencida{background:var(--gc-rojo-suave);color:var(--gc-rojo)}.estado-por-vencer{background:var(--gc-amarillo-suave);color:#9a6400}.estado-vigente{background:var(--gc-verde-suave);color:var(--gc-verde)}.estado-sin-plan{background:var(--gc-fondo);color:var(--gc-gris)}.estado-cobrada{background:var(--gc-info-suave);color:var(--gc-info)}.cobranza-modal .modal-box{width:min(94vw,560px)}.cobranza-modal-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.cobranza-modal-full{grid-column:1/-1}.cobranza-modal-actions{display:flex;gap:8px;margin-top:16px}@media(max-width:1400px){.cobranza-grid{grid-template-columns:1fr}.cobranza-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:700px){.cobranza-kpis{grid-template-columns:1fr}.cobranza-toolbar{display:grid;justify-content:stretch}.cobranza-buscador{width:100%}.cobranza-toolbar .btn-giseca{justify-content:center}.cobranza-modal-grid{grid-template-columns:1fr}.cobranza-modal-full{grid-column:auto}.cobranza-modal-actions{display:grid}.cobranza-modal-actions .btn-giseca{justify-content:center}}
    </style>
@endpush

@push('scripts')
    <script>
        function abrirMovimiento(button, tipo) {
            const modal = document.getElementById('modalMov');
            const saldo = Number(button.dataset.saldo || 0).toFixed(2);
            const cuotaSaldo = Number(button.dataset.cuotaSaldo || saldo).toFixed(2);
            const cuotaId = button.dataset.cuotaId || '';
            const cuotaNumero = button.dataset.cuotaNumero || '';
            const esCobro = tipo === 'cobro';

            document.getElementById('formMov').action = button.dataset.url;
            document.getElementById('movTitulo').textContent = (esCobro ? 'Cobrar venta ' : 'Pagar compra ') + button.dataset.numero;
            document.getElementById('movSaldo').textContent = saldo;
            document.getElementById('movMonto').value = esCobro ? cuotaSaldo : saldo;
            document.getElementById('movMonto').max = esCobro ? cuotaSaldo : saldo;
            document.getElementById('movCuotaId').value = cuotaId;
            document.getElementById('movCuotaBox').style.display = esCobro ? 'block' : 'none';
            document.getElementById('movCuotaTexto').value = cuotaNumero ? ('Cuota #' + cuotaNumero + ' · Bs ' + cuotaSaldo) : 'Saldo sin plan';
            modal.classList.add('abierto');
        }

        function cerrarMovimiento() {
            document.getElementById('modalMov').classList.remove('abierto');
        }

        document.querySelectorAll('.abrir-cobro').forEach((button) => {
            button.addEventListener('click', () => abrirMovimiento(button, 'cobro'));
        });
        document.querySelectorAll('.abrir-pago').forEach((button) => {
            button.addEventListener('click', () => abrirMovimiento(button, 'pago'));
        });

        function mostrarToast(mensaje, tipo) {
            let t = document.getElementById('toastGiseca');
            if (! t) {
                t = document.createElement('div');
                t.id = 'toastGiseca';
                t.className = 'toast-giseca';
                document.body.appendChild(t);
            }
            t.textContent = mensaje;
            t.className = 'toast-giseca mostrar ' + (tipo || '');
            setTimeout(() => t.classList.remove('mostrar'), 2800);
        }
        @if(session('exito')) mostrarToast(@json(session('exito')), 'exito'); @endif
        @if(session('error')) mostrarToast(@json(session('error')), 'error'); @endif
    </script>
@endpush
