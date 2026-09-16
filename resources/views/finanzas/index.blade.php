@extends('layouts.app')

@section('title','Finanzas')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">

    <div>
        <h2 style="margin:0">
            Gestión de Finanzas
        </h2>

        <p style="color:var(--gc-gris-claro)">
            Registro y control de ingresos, gastos y saldos acumulados.
        </p>

    </div>


    <button
        class="btn-giseca btn-primario"
        onclick="abrirMovimiento()">

        <i class="bi bi-plus"></i>
        Registrar Movimiento

    </button>


</div>



<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:25px">


    <div class="kpi verde">

        <div class="label">
            <i class="bi bi-arrow-down-circle-fill"></i>
            Ingresos
        </div>

        <div class="value" style="color:#198754;">
            + Bs {{ formatoMoneda($ingresos) }}
        </div>

    </div>



    <div class="kpi rojo">

        <div class="label">
            <i class="bi bi-arrow-up-circle-fill"></i>
            Gastos
        </div>

        <div class="value" style="color:#dc3545;">
            - Bs {{ formatoMoneda($gastos) }}
        </div>

    </div>



    <div class="kpi gris">

        <div class="label">
            Balance
        </div>

        <div class="value"
            style="color:{{ $balance >= 0 ? '#198754' : '#dc3545' }}">
            Bs {{ formatoMoneda($balance) }}
        </div>

    </div>


</div>

<div class="card-giseca">

    <h3 style="margin-bottom:20px;">
        <i class="bi bi-wallet2"></i>
        Gestión de Finanzas
    </h3>


    <p style="color:var(--gc-gris-claro);">
        Registro y control de ingresos, gastos y saldo acumulado.
    </p>


    <hr>


    <h5>Movimientos registrados</h5>


    <table class="tabla-giseca">

        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Concepto</th>
                <th>Importe</th>
                <th>Origen</th>
            </tr>
        </thead>


        <tbody>

            @forelse($movimientos as $m)

            <tr>

                <td>
                    {{ $m->fecha->format('d/m/Y') }}
                </td>


                <td>

                    @if($m->tipo == 'ingreso')

                    <span style="
    color:#198754;
    font-weight:700;
">
                        ▲ INGRESO
                    </span>

                    @else

                    <span style="
    color:#dc3545;
    font-weight:700;
">
                        ▼ GASTO
                    </span>

                    @endif

                </td>


                <td>
                    {{ $m->concepto }}
                </td>


                <td>

                    @if($m->tipo == 'ingreso')

                    <span style="
color:#198754;
font-weight:700;
">
                        + Bs {{ formatoMoneda($m->importe) }}
                    </span>

                    @else

                    <span style="
color:#dc3545;
font-weight:700;
">
                        - Bs {{ formatoMoneda($m->importe) }}
                    </span>

                    @endif

                </td>


                <td>
                    {{ ucfirst($m->origen) }}
                </td>

            </tr>


            @empty

            <tr>
                <td colspan="5">
                    No existen movimientos registrados.
                </td>
            </tr>

            @endforelse


        </tbody>

    </table>

</div>
<div id="modalMovimiento"
    style="
display:none;
position:fixed;
inset:0;
background:rgba(0,0,0,.45);
z-index:9999;
align-items:center;
justify-content:center;
">


    <div style="
background:var(--gc-superficie);
width:700px;
max-width:95%;
border-radius:16px;
padding:25px;
">


        <div style="
display:flex;
justify-content:space-between;
align-items:center;
margin-bottom:20px;
">

            <h3>
                Nuevo Registro Manual
            </h3>

            <button onclick="cerrarMovimiento()"
                class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-x"></i>
            </button>

        </div>



        <form method="POST" action="{{ route('finanzas.store') }}">

            @csrf


            <div style="
display:grid;
grid-template-columns:1fr 1fr;
gap:15px;
">


                <div>

                    <label>
                        Tipo de Movimiento
                    </label>


                    <select name="tipo" class="form-control-giseca">

                        <option value="ingreso">
                            🟢 Ingreso (+)
                        </option>

                        <option value="gasto">
                            🔴 Gasto (-)
                        </option>

                    </select>


                </div>


                <div>

                    <label>
                        Fecha
                    </label>

                    <input
                        type="date"
                        name="fecha"
                        value="{{ date('Y-m-d') }}"
                        class="form-control-giseca">

                </div>


                <div>

                    <label>
                        Concepto Principal
                    </label>


                    <input
                        name="concepto"
                        class="form-control-giseca"
                        placeholder="Ej: Pago servicio de luz">


                </div>


                <div>

                    <label>
                        Importe (Bs)
                    </label>


                    <input
                        type="number"
                        step="0.01"
                        name="importe"
                        class="form-control-giseca">


                </div>


            </div>



            <div style="margin-top:15px">


                <label>
                    Detalle / Descripción
                </label>


                <textarea
                    name="detalle"
                    class="form-control-giseca"
                    rows="3"></textarea>


            </div>



            <div style="margin-top:15px">


                <label>
                    Observaciones
                </label>


                <textarea
                    name="observaciones"
                    class="form-control-giseca"
                    rows="2"></textarea>


            </div>



            <div style="
display:flex;
justify-content:flex-end;
gap:10px;
margin-top:20px;
">


                <button
                    type="button"
                    onclick="cerrarMovimiento()"
                    class="btn btn-outline-secondary">

                    Cancelar

                </button>


                <button
                    class="btn-giseca btn-primario">

                    Guardar Registro

                </button>


            </div>


        </form>


    </div>

</div>
@endsection

@push('scripts')

<script>
    function mostrarToast(mensaje, tipo) {

        let t = document.getElementById('toastGiseca');

        if (!t) {

            t = document.createElement('div');
            t.id = 'toastGiseca';
            t.className = 'toast-giseca';

            document.body.appendChild(t);
        }


        t.textContent = mensaje;

        t.className = 'toast-giseca mostrar ' + (tipo || '');


        setTimeout(() => {

            t.classList.remove('mostrar');

        }, 2800);

    }



    @if(session('exito'))

    mostrarToast(
        @json(session('exito')),
        'exito'
    );

    @endif



    @if(session('error'))

    mostrarToast(
        @json(session('error')),
        'error'
    );

    @endif




    function abrirMovimiento() {

        document.getElementById('modalMovimiento').style.display = 'flex';

    }



    function cerrarMovimiento() {

        document.getElementById('modalMovimiento').style.display = 'none';

    }
</script>

@endpush