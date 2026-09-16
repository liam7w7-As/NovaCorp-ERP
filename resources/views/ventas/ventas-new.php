{{-- Formulario POS GISECA ERP --}}
{{-- Mantiene la lógica original de ventas --}}

@if($errors->any())
<div class="alert-pos error">
    {{ $errors->first() }}
</div>
@endif

@if(session('error'))
<div class="alert-pos error">
    {{ session('error') }}
</div>
@endif


<form method="POST" action="{{ $action }}" id="formVenta">

    @csrf

    @if($method === 'PUT')
    @method('PUT')
    @endif


    <div class="pos-wrapper">


        <!-- CABECERA -->

        <div class="pos-header">

            <div>
                <h1>
                    Punto de Venta
                </h1>

                <p>
                    Registro rápido de ventas
                </p>
            </div>


            <div class="pos-info">

                <span>
                    🏪 GISECA ERP
                </span>

                <span>
                    {{ date('d/m/Y') }}
                </span>

            </div>

        </div>




        <!-- DATOS CLIENTE -->

        <div class="pos-card">

            <div class="grid-client">


                <div>

                    <label>
                        Cliente *
                    </label>


                    <select
                        id="v_cliente"
                        name="cliente_id"
                        class="pos-input"
                        data-tomselect="{{ route('clientes.buscar') }}">

                        <option value="">
                            Buscar cliente...
                        </option>


                        @foreach($clientes as $c)

                        <option value="{{ $c->id }}"
                            {{ 
                    (isset($venta) && $venta->cliente_id==$c->id)
                    ||
                    old('cliente_id')==$c->id
                    ? 'selected':'' 
                    }}>

                            {{ $c->nombre }}

                        </option>


                        @endforeach


                    </select>

                </div>




                <div>

                    <label>
                        Nuevo cliente
                    </label>


                    <input

                        id="v_clienteNuevo"

                        name="cliente_nuevo"

                        class="pos-input"

                        placeholder="Crear cliente nuevo"

                        value="{{ old('cliente_nuevo') }}">

                </div>




                <div>

                    <label>
                        Tipo
                    </label>


                    <select
                        id="v_tipo"
                        name="tipo"
                        class="pos-input">

                        <option value="con_factura">
                            Con factura
                        </option>


                        <option value="sin_factura">
                            Sin factura
                        </option>


                    </select>


                </div>




                <div>

                    <label>
                        Modalidad
                    </label>


                    <select
                        id="v_modalidad"
                        name="modalidad"
                        class="pos-input">

                        <option value="contado">
                            Contado
                        </option>


                        <option value="credito">
                            Crédito
                        </option>


                    </select>


                </div>



                <div>

                    <label>
                        Fecha
                    </label>


                    <input

                        type="date"

                        id="v_fecha"

                        name="fecha"

                        class="pos-input"

                        value="{{ isset($venta) ? $venta->fecha->format('Y-m-d') : old('fecha',$fechaHoy ?? date('Y-m-d')) }}">

                </div>




                <div>

                    <label>
                        Forma de pago
                    </label>


                    <select
                        id="v_metodo"
                        name="metodo"
                        class="pos-input">

                        @foreach(['Efectivo','Transferencia','QR','Tarjeta','Cheque'] as $m)

                        <option>
                            {{ $m }}
                        </option>

                        @endforeach


                    </select>


                </div>


            </div>


            <div class="mt">

                <label>
                    Observaciones
                </label>


                <input

                    id="v_obs"

                    name="observaciones"

                    class="pos-input"

                    value="{{ isset($venta) ? $venta->observaciones : old('observaciones') }}">

            </div>


        </div>





        <div class="pos-layout">



            <!-- PRODUCTOS -->

            <div class="pos-card">


                <h3>
                    📦 Productos
                </h3>


                <div class="search-box">


                    <input

                        type="text"

                        id="v_buscador"

                        class="pos-input"

                        placeholder="Buscar producto por código o descripción..."

                        autocomplete="off" />


                    <div id="resultadosBusquedaVenta"></div>


                </div>




                <table id="tablaItemsVenta" class="tabla-pos">


                    <thead>

                        <tr>

                            <th>
                                Producto
                            </th>

                            <th>
                                Cantidad
                            </th>

                            <th>
                                Precio
                            </th>

                            <th>
                                Total
                            </th>

                            <th>
                            </th>

                        </tr>


                    </thead>



                    <tbody>


                        @if(isset($venta))

                        @foreach($venta->detalles as $i=>$d)


                        <tr>


                            <td>

                                {{ $d->descripcion_producto }}


                                <input

                                    type="hidden"

                                    name="items[{{ $i }}][producto_id]"

                                    value="{{ $d->producto_id }}">


                                <input

                                    type="hidden"

                                    class="vi-desc"

                                    value="{{ $d->descripcion_producto }}">


                            </td>



                            <td>

                                <input

                                    class="vi-cant"

                                    name="items[{{ $i }}][cantidad]"

                                    value="{{ $d->cantidad }}"

                                    oninput="recalcularVenta()">

                            </td>



                            <td>

                                <input

                                    class="vi-precio"

                                    name="items[{{ $i }}][precio]"

                                    value="{{ $d->precio_unitario }}"

                                    oninput="recalcularVenta()">

                            </td>



                            <td class="vi-total">

                                {{ number_format($d->subtotal,2) }}

                            </td>



                            <td>

                                <button

                                    type="button"

                                    onclick="this.closest('tr').remove();reindexarVenta();recalcularVenta();">

                                    ✕


                                </button>


                            </td>


                        </tr>



                        @endforeach

                        @endif


                    </tbody>


                </table>


            </div>
            {{-- CONTINUACIÓN PARTE 2 --}}


            <!-- CARRITO LATERAL -->

            <div class="pos-card carrito-panel">


                <h3>
                    🛒 Resumen de venta
                </h3>



                <div class="resumen-box">


                    <div>

                        <span>
                            Subtotal
                        </span>

                        <strong id="v_lblSubtotal">
                            0.00
                        </strong>


                    </div>



                    <div class="linea-desc">


                        <span>
                            Descuento
                        </span>


                        <input

                            type="number"

                            id="v_descuento"

                            name="descuento"

                            value="{{ isset($venta) ? $venta->descuento : old('descuento',0) }}"

                            min="0"

                            step="0.01"

                            oninput="recalcularVenta()">


                    </div>




                    <div class="total-box">


                        <span>
                            TOTAL
                        </span>


                        <strong id="v_lblTotal">

                            Bs 0.00

                        </strong>


                    </div>



                </div>




                <div class="metodos-pago">


                    <h4>
                        💳 Método de pago
                    </h4>



                    <div class="botones-pago">


                        <button

                            type="button"

                            onclick="seleccionarPago('Efectivo')"

                            class="btn-pago">

                            💵 Efectivo

                        </button>




                        <button

                            type="button"

                            onclick="seleccionarPago('QR')"

                            class="btn-pago">

                            📱 QR

                        </button>




                        <button

                            type="button"

                            onclick="seleccionarPago('Tarjeta')"

                            class="btn-pago">

                            💳 Tarjeta

                        </button>



                    </div>



                </div>




                <input

                    type="hidden"

                    id="metodo_real"

                    name="metodo"

                    value="Efectivo">




                <button

                    type="submit"

                    class="btn-procesar">

                    ✓ Procesar Venta

                </button>




                <a

                    href="{{ route('ventas.index') }}"

                    class="btn-cancelar">

                    Cancelar

                </a>



            </div>



        </div>

</form>





<script>
    let idxVenta = {
        {
            isset($venta) ? $venta - > detalles - > count() : 0
        }
    };



    const buscadorVenta = document.getElementById('v_buscador');

    const resultadosVenta = document.getElementById('resultadosBusquedaVenta');

    let timerVenta = null;



    /*
     BUSQUEDA PRODUCTOS
    */


    buscadorVenta.addEventListener('input', function() {


        clearTimeout(timerVenta);


        let texto = this.value.trim();



        if (texto.length < 2) {

            resultadosVenta.innerHTML = '';

            return;

        }



        timerVenta = setTimeout(async () => {


            let respuesta = await fetch(

                "{{ route('productos.buscar') }}?q=" + encodeURIComponent(texto),

                {

                    headers: {

                        'Accept': 'application/json'

                    }

                }

            );



            let productos = await respuesta.json();



            resultadosVenta.innerHTML = productos.map(p => `


<div

class="resultado-producto"

data-id="${p.id}"

data-codigo="${p.codigo}"

data-desc="${p.descripcion}"

data-precio="${p.precio}"

>


<strong>
${p.codigo}
</strong>


<br>


${p.descripcion}


<br>


<span>

Stock: ${p.stock}

</span>


</div>



`).join('') ||


                '<div class="resultado-producto">Sin resultados</div>';





            document.querySelectorAll('.resultado-producto[data-id]')

                .forEach(item => {


                    item.onclick = function() {



                        agregarItemVenta({


                            id: this.dataset.id,

                            codigo: this.dataset.codigo,

                            descripcion: this.dataset.desc,

                            precio: this.dataset.precio


                        });



                    };



                });




        }, 250);



    });





    document.addEventListener('click', function(e) {


        if (

            !buscadorVenta.contains(e.target)

            &&

            !resultadosVenta.contains(e.target)

        ) {

            resultadosVenta.innerHTML = '';

        }



    });






    function agregarItemVenta(p) {


        const tbody = document.querySelector('#tablaItemsVenta tbody');



        let fila = document.createElement('tr');



        fila.innerHTML = `


<td>

${p.descripcion}


<input

type="hidden"

name="items[${idxVenta}][producto_id]"

value="${p.id}"

>


<input

type="hidden"

class="vi-desc"

value="${p.descripcion}"

>


</td>



<td>

<input

type="number"

class="vi-cant"

name="items[${idxVenta}][cantidad]"

value="1"

min="1"

oninput="recalcularVenta()"

>

</td>



<td>


<input

type="number"

class="vi-precio"

name="items[${idxVenta}][precio]"

value="${p.precio}"

oninput="recalcularVenta()"

>


</td>



<td class="vi-total">

${Number(p.precio).toFixed(2)}

</td>



<td>


<button

type="button"

onclick="

this.closest('tr').remove();

reindexarVenta();

recalcularVenta();

"

>

✕

</button>


</td>


`;



        tbody.appendChild(fila);



        idxVenta++;


        resultadosVenta.innerHTML = '';

        buscadorVenta.value = '';


        recalcularVenta();



    }







    function reindexarVenta() {


        document.querySelectorAll('#tablaItemsVenta tbody tr')

            .forEach((tr, i) => {


                tr.querySelectorAll('input[name^="items["]')

                    .forEach(input => {


                        input.name = input.name.replace(

                            /items\[\d+\]/,

                            'items[' + i + ']'

                        );


                    });


            });


        idxVenta = document.querySelectorAll('#tablaItemsVenta tbody tr').length;


    }






    function recalcularVenta() {


        let subtotal = 0;



        document.querySelectorAll('#tablaItemsVenta tbody tr')

            .forEach(tr => {


                let cantidad = parseFloat(

                    tr.querySelector('.vi-cant').value

                ) || 0;



                let precio = parseFloat(

                    tr.querySelector('.vi-precio').value

                ) || 0;



                let total = cantidad * precio;



                tr.querySelector('.vi-total').innerHTML =

                    total.toFixed(2);



                subtotal += total;



            });




        let descuento = parseFloat(

            document.getElementById('v_descuento').value

        ) || 0;




        document.getElementById('v_lblSubtotal').innerHTML =

            'Bs ' + subtotal.toFixed(2);



        document.getElementById('v_lblTotal').innerHTML =

            'Bs ' + (subtotal - descuento).toFixed(2);



    }







    function seleccionarPago(valor) {


        document.getElementById('metodo_real').value = valor;


        document.querySelectorAll('.btn-pago')

            .forEach(btn => btn.classList.remove('activo'));



        event.target.classList.add('activo');



    }





    document.getElementById('formVenta')

        .addEventListener('submit', function(e) {


            let cliente =

                document.getElementById('v_cliente').value

                ||

                document.getElementById('v_clienteNuevo').value;



            if (!cliente) {


                e.preventDefault();

                alert('Seleccione un cliente');

                return;


            }



            if (

                document.querySelectorAll('#tablaItemsVenta tbody tr').length === 0

            ) {


                e.preventDefault();

                alert('Agregue productos');

                return;


            }


        });



    recalcularVenta();
</script>
<style>
    /* ==========================
   GISECA ERP - POS STYLE
========================== */


    .pos-wrapper {

        background: #f8fafc;

        padding: 25px;

        border-radius: 20px;

        min-height: 100vh;

        font-family:
            "Inter",
            Arial,
            sans-serif;

    }




    /* CABECERA */

    .pos-header {

        display: flex;

        justify-content: space-between;

        align-items: center;

        margin-bottom: 20px;

    }



    .pos-header h1 {

        margin: 0;

        font-size: 28px;

        font-weight: 800;

        color: #0f172a;

    }



    .pos-header p {

        margin-top: 5px;

        color: #64748b;

    }



    .pos-info {

        display: flex;

        gap: 15px;

    }



    .pos-info span {

        background: white;

        padding: 10px 15px;

        border-radius: 12px;

        border: 1px solid #e2e8f0;

        color: #475569;

        font-size: 14px;

    }





    /* TARJETAS */

    .pos-card {

        background: white;

        border-radius: 18px;

        padding: 20px;

        margin-bottom: 20px;

        border: 1px solid #e2e8f0;

        box-shadow:

            0 5px 20px rgba(15, 23, 42, .05);

    }




    /* DATOS CLIENTE */


    .grid-client {

        display: grid;

        grid-template-columns:

            repeat(3, 1fr);

        gap: 15px;

    }



    .grid-client label,

    .pos-card label {

        display: block;

        margin-bottom: 7px;

        font-size: 13px;

        font-weight: 600;

        color: #334155;

    }



    .pos-input {

        width: 100%;

        height: 42px;

        border: 1px solid #cbd5e1;

        border-radius: 10px;

        padding: 0 12px;

        background: white;

        color: #0f172a;

    }



    .pos-input:focus {

        border-color: #2563eb;

        outline: none;

    }





    /* DISTRIBUCION */


    .pos-layout {

        display: grid;

        grid-template-columns:

            2fr 1fr;

        gap: 20px;

    }




    /* BUSCADOR */


    .search-box {

        position: relative;

    }



    #resultadosBusquedaVenta {

        position: absolute;

        width: 100%;

        background: white;

        z-index: 50;

        border-radius: 12px;

        border: 1px solid #e2e8f0;

        overflow: hidden;

    }



    .resultado-producto {

        padding: 12px;

        cursor: pointer;

        border-bottom: 1px solid #e2e8f0;

        transition: .2s;

    }



    .resultado-producto:hover {

        background: #eff6ff;

    }



    .resultado-producto span {

        color: #16a34a;

        font-size: 13px;

    }







    /* TABLA PRODUCTOS */


    .tabla-pos {

        width: 100%;

        margin-top: 20px;

        border-collapse: collapse;

    }



    .tabla-pos th {

        text-align: left;

        background: #f1f5f9;

        padding: 12px;

        font-size: 13px;

        color: #475569;

    }



    .tabla-pos td {

        padding: 12px;

        border-bottom: 1px solid #e2e8f0;

    }



    .tabla-pos input {

        width: 90px;

        height: 35px;

        border-radius: 8px;

        border: 1px solid #cbd5e1;

        padding: 5px;

    }







    /* RESUMEN */


    .carrito-panel {

        position: sticky;

        top: 20px;

        height: max-content;

    }



    .resumen-box {

        margin-top: 20px;

    }



    .resumen-box>div {

        display: flex;

        justify-content: space-between;

        padding: 12px 0;

        color: #475569;

    }



    .linea-desc input {

        width: 100px;

        height: 35px;

        text-align: right;

    }





    .total-box {

        margin-top: 15px;

        background: #eff6ff;

        padding: 20px;

        border-radius: 15px;

    }



    .total-box span {

        display: block;

        font-size: 14px;

        color: #475569;

    }



    .total-box strong {

        font-size: 32px;

        color: #2563eb;

    }







    /* METODOS PAGO */


    .metodos-pago {

        margin-top: 25px;

    }



    .botones-pago {

        display: grid;

        grid-template-columns:

            repeat(3, 1fr);

        gap: 10px;

    }



    .btn-pago {

        height: 55px;

        border-radius: 12px;

        border: 1px solid #cbd5e1;

        background: white;

        cursor: pointer;

        font-size: 15px;

        transition: .2s;

    }



    .btn-pago:hover,

    .btn-pago.activo {

        background: #2563eb;

        color: white;

        border-color: #2563eb;

    }






    /* BOTONES */


    .btn-procesar {

        margin-top: 20px;

        width: 100%;

        height: 60px;

        border: none;

        border-radius: 14px;

        background: #16a34a;

        color: white;

        font-size: 20px;

        font-weight: 700;

        cursor: pointer;

        transition: .2s;

    }



    .btn-procesar:hover {

        background: #15803d;

    }



    .btn-cancelar {

        display: block;

        text-align: center;

        margin-top: 10px;

        padding: 12px;

        border-radius: 12px;

        border: 1px solid #cbd5e1;

        color: #475569;

        text-decoration: none;

    }






    /* ALERTAS */


    .alert-pos {

        padding: 15px;

        border-radius: 12px;

        margin-bottom: 15px;

    }



    .alert-pos.error {

        background: #fee2e2;

        color: #b91c1c;

    }





    /* RESPONSIVE */


    @media(max-width:1200px) {


        .pos-layout {

            grid-template-columns: 1fr;

        }


        .carrito-panel {

            position: relative;

        }


        .grid-client {

            grid-template-columns: repeat(2, 1fr);

        }



    }



    @media(max-width:700px) {


        .pos-wrapper {

            padding: 10px;

        }


        .pos-header {

            flex-direction: column;

            align-items: flex-start;

            gap: 15px;

        }



        .grid-client {

            grid-template-columns: 1fr;

        }



        .botones-pago {

            grid-template-columns: 1fr;

        }


    }
</style>