<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Comprobante;
use App\Models\Configuracion;
use App\Models\EventoSiat;
use App\Models\Producto;
use App\Models\Proforma;
use App\Models\Proveedor;
use App\Models\Venta;
use App\Services\SiatConfig;
use App\Services\SiatService;
use Database\Seeders\ModulosBaseSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ConfiguracionController extends Controller
{
    public function index()
    {
        return view('configuracion.index', [

            'empresa' => Configuracion::empresa(),

            // Logo del sistema
            'logo' => Configuracion::logo(),

            // Membrete documentos
            'membretado' => Configuracion::membretado(),

            'siat' => SiatConfig::todo(),

            'eventos' => EventoSiat::orderByDesc('id')
                ->limit(10)
                ->get(),

            'conteos' => [

                'productos' => Producto::count(),

                'clientes' => Cliente::count(),

                'proveedores' => Proveedor::count(),

                'proformas' => Proforma::count(),

                'compras' => Compra::count(),

                'ventas' => Venta::count(),

                'comprobantes' => Comprobante::count(),

            ],

        ]);
    }

    public function update(Request $request)
    {

        $data = $request->validate([

            'empresa_nombre' => 'required|string|max:255',

            'empresa_nit' => 'nullable|string|max:50',

            'empresa_direccion' => 'nullable|string|max:255',

            'empresa_telefono' => 'nullable|string|max:50',

            'empresa_email' => 'nullable|email|max:150',

            // MEMBRETE DOCUMENTOS

            'membretado' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',

            'quitar_membretado' => 'nullable|boolean',

            // LOGO SISTEMA

            'logo' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',

            'quitar_logo' => 'nullable|boolean',

        ]);

        /*
        DATOS EMPRESA
        */

        Configuracion::set(
            'empresa_nombre',
            $data['empresa_nombre'],
            'text'
        );

        Configuracion::set(
            'empresa_nit',
            $data['empresa_nit'] ?? null,
            'text'
        );

        Configuracion::set(
            'empresa_direccion',
            $data['empresa_direccion'] ?? null,
            'text'
        );

        Configuracion::set(
            'empresa_telefono',
            $data['empresa_telefono'] ?? null,
            'text'
        );

        Configuracion::set(
            'empresa_email',
            $data['empresa_email'] ?? null,
            'text'
        );

        /*
        MEMBRETADO DOCUMENTOS
        */

        if ($request->boolean('quitar_membretado')) {

            $this->eliminarMembretado();
        } elseif ($request->hasFile('membretado')) {

            $this->eliminarMembretado();

            $file = $request->file('membretado');

            $tipo =
                $file->getMimeType() === 'application/pdf'
                ? 'pdf'
                : 'image';

            $path = $file->store(
                'membretado',
                'public'
            );

            Configuracion::set(
                'membretado_path',
                $path,
                'file'
            );

            Configuracion::set(
                'membretado_tipo',
                $tipo,
                'text'
            );
        }

        /*
        LOGO DEL SISTEMA
        */

        if ($request->boolean('quitar_logo')) {

            $this->eliminarLogo();
        } elseif ($request->hasFile('logo')) {

            $this->eliminarLogo();

            $pathLogo = $request
                ->file('logo')
                ->store(
                    'logos',
                    'public'
                );

            Configuracion::set(
                'logo_path',
                $pathLogo,
                'file'
            );
        }

        return back()
            ->with(
                'exito',
                'Configuración guardada'
            );
    }

    protected function eliminarMembretado(): void
    {

        $anterior =
            Configuracion::get(
                'membretado_path'
            );

        if (
            $anterior &&
            Storage::disk('public')
                ->exists($anterior)
        ) {

            Storage::disk('public')
                ->delete($anterior);
        }

        Configuracion::whereIn(
            'clave',
            [
                'membretado_path',
                'membretado_tipo',
            ]
        )->delete();
    }

    protected function eliminarLogo(): void
    {

        $anterior =
            Configuracion::get(
                'logo_path'
            );

        if (
            $anterior &&
            Storage::disk('public')
                ->exists($anterior)
        ) {

            Storage::disk('public')
                ->delete($anterior);
        }

        Configuracion::where(
            'clave',
            'logo_path'
        )->delete();
    }

    public function resetearDatos(Request $request)
    {

        $request->validate([

            'confirmacion' => 'required|in:REINICIAR',

        ]);

        Schema::disableForeignKeyConstraints();

        foreach (
            [

                'comprobante_pagos',
                'comprobantes',
                'detalle_venta',
                'detalle_compra',
                'detalle_proforma',
                'facturas_electronicas',
                'eventos_siat',
                'ventas',
                'compras',
                'proformas',
                'clientes',
                'proveedores',
                'productos',
                'contadores',

            ] as $tabla
        ) {

            DB::table($tabla)->truncate();
        }

        Schema::enableForeignKeyConstraints();

        app(ModulosBaseSeeder::class)->run();

        return back()
            ->with(
                'exito',
                'Datos reiniciados con valores de ejemplo'
            );
    }

    public function updateSiat(Request $request)
    {

        $data = $request->validate([

            'siat_modo' => 'required|in:simulador,real',

            'siat_ambiente' => 'required|in:pruebas,produccion',

            'siat_modalidad' => 'required|in:electronica,computarizada',

            'siat_nit' => 'required|numeric',

            'siat_razon_social' => 'required|string|max:255',

            'siat_codigo_sistema' => 'nullable|string|max:100',

            'siat_sucursal' => 'nullable|string|max:10',

            'siat_punto_venta' => 'nullable|string|max:10',

            'siat_telefono' => 'nullable|string|max:50',

            'siat_direccion' => 'nullable|string|max:255',

            'siat_ciudad' => 'nullable|string|max:100',

            'siat_leyenda' => 'nullable|string|max:500',

            'siat_cafc' => 'nullable|string|max:100',

            'siat_cert_password' => 'nullable|string|max:255',

            'siat_token' => 'nullable|string|max:4000',

            'siat_certificado' => 'nullable|file|mimetypes:application/x-pkcs12,application/octet-stream|max:2048',

        ]);

        $guardar = $data;

        unset(
            $guardar['siat_certificado'],
            $guardar['_token']
        );

        // Combinación peligrosa: simular facturas en ambiente de producción
        // genera documentos sin valor fiscal que parecen oficiales.
        if (($guardar['siat_modo'] ?? '') === 'simulador' && ($guardar['siat_ambiente'] ?? '') === 'produccion') {
            return back()->withInput()->with(
                'error',
                'Combinación no permitida: el SIMULADOR no puede usarse con ambiente PRODUCCIÓN. Usa modo REAL con credenciales del SIN.'
            );
        }

        if ($request->hasFile('siat_certificado')) {

            $anterior =
                Configuracion::get(
                    'siat_certificado_path'
                );

            if (
                $anterior &&
                Storage::exists($anterior)
            ) {

                Storage::delete($anterior);
            }

            $guardar['siat_certificado_path']
                =
                $request
                    ->file('siat_certificado')
                    ->store(
                        'siat',
                        'local'
                    );
        }

        SiatConfig::guardar($guardar);

        return back()
            ->with(
                'exito',
                'Configuración SIAT guardada'
            );
    }

    public function probarSiat(SiatService $siat)
    {

        try {

            $cuis = $siat->solicitarCuis();

            $cufd = $siat->solicitarCufd();

            return back()->with(

                'exito',

                'Conexión OK. CUIS: '
                    .($cuis['cuis'] ?? '?')
                    .' · CUFD: '
                    .($cufd['cufd'] ?? '?')

            );
        } catch (\Throwable $e) {

            return back()
                ->with(
                    'error',
                    'Falló la prueba: '.$e->getMessage()
                );
        }
    }

    public function sincronizarSiat(SiatService $siat)
    {
        try {

            $res = $siat->sincronizarLeyendas();

            Configuracion::set(

                'siat_leyendas',

                json_encode(
                    $res['leyendas'] ?? [],
                    JSON_UNESCAPED_UNICODE
                ),

                'json'

            );

            return back()
                ->with(
                    'exito',
                    'Catálogos sincronizados'
                );
        } catch (\Throwable $e) {

            return back()
                ->with(
                    'error',
                    $e->getMessage()
                );
        }
    }
}
