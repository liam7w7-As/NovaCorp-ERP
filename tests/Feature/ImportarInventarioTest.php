<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ImportarInventarioTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::firstOrCreate(
            ['email' => 'admin_test@giseca.com'],
            ['name' => 'Admin Test', 'password' => bcrypt('secret123'), 'rol' => 'admin', 'activo' => true]
        );
    }

    /** @param array<int, array<string, mixed>> $filas */
    protected function importar(array $filas): TestResponse
    {
        return $this->actingAs($this->admin)
            ->postJson(route('productos.importar'), ['productos' => $filas]);
    }

    protected function fila(string $codigo, array $extra = []): array
    {
        return array_merge([
            'Codigo' => $codigo,
            'Descripcion' => 'Filtro de combustible',
            'Marca' => 'Fleetguard',
            'Unidad' => 'PZA',
            'Costo' => 125.52,
            'PrecioVenta' => 192.55,
            'Stock' => 14,
            'StockMinimo' => 5,
        ], $extra);
    }

    public function test_reimporta_existente_sin_error_y_suma_stock(): void
    {
        Producto::create([
            'codigo' => 'FDFF5507', 'descripcion' => 'Viejo',
            'costo' => 100, 'precio' => 150, 'stock' => 10, 'stock_min' => 2,
        ]);

        $this->importar([$this->fila('FDFF5507')])
            ->assertOk()
            ->assertJsonPath('creados', 0)
            ->assertJsonPath('actualizados', 1)
            ->assertJsonPath('errores', []);

        $p = Producto::where('codigo', 'FDFF5507')->firstOrFail();
        $this->assertEquals(24, (float) $p->stock);
        $this->assertEquals(125.52, (float) $p->costo);
        $this->assertSame(1, Producto::count());
    }

    public function test_restaura_producto_borrado_en_papelera(): void
    {
        $p = Producto::create([
            'codigo' => 'FS1242', 'descripcion' => 'Separador',
            'costo' => 100, 'precio' => 150, 'stock' => 32,
        ]);
        $p->delete();
        $this->assertTrue($p->fresh()->trashed());

        $this->importar([$this->fila('FS1242', ['Stock' => 5])])
            ->assertOk()
            ->assertJsonPath('restaurados', 1)
            ->assertJsonPath('errores', []);

        $restaurado = Producto::where('codigo', 'FS1242')->firstOrFail();
        $this->assertFalse($restaurado->trashed());
        $this->assertEquals(37, (float) $restaurado->stock);
    }

    public function test_codigo_con_espacios_invisibles_coincide(): void
    {
        Producto::create([
            'codigo' => 'FF5612', 'descripcion' => 'Filtro',
            'costo' => 100, 'precio' => 150, 'stock' => 48,
        ]);

        // nbsp final, como arrastra Excel.
        $codigoConNbsp = 'FF5612'."\u{00A0}";
        $this->importar([$this->fila($codigoConNbsp)])
            ->assertOk()
            ->assertJsonPath('actualizados', 1)
            ->assertJsonPath('errores', []);

        $this->assertSame(1, Producto::count());
    }

    public function test_codigo_nuevo_se_crea(): void
    {
        $this->importar([$this->fila('NUEVO-1')])
            ->assertOk()
            ->assertJsonPath('creados', 1);

        $this->assertEquals(14, (float) Producto::where('codigo', 'NUEVO-1')->firstOrFail()->stock);
    }
}
