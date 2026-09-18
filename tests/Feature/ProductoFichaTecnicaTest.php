<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductoFichaTecnicaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'rol' => 'admin',
            'activo' => true,
        ]);
    }

    public function test_admin_can_attach_technical_sheet_and_images_to_a_product(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('productos.store'), [
                'codigo' => 'FT-001',
                'descripcion' => 'Filtro técnico con ficha',
                'unidad' => 'PZA',
                'costo' => 10,
                'precio' => 25,
                'stock' => 4,
                'stock_min' => 1,
                'ficha_tecnica' => UploadedFile::fake()->create('ficha-tecnica.pdf', 48, 'application/pdf'),
                'imagenes' => [
                    UploadedFile::fake()->create('frontal.jpg', 20, 'image/jpeg'),
                    UploadedFile::fake()->create('lateral.webp', 20, 'image/webp'),
                ],
            ])
            ->assertSessionHas('exito');

        $producto = Producto::where('codigo', 'FT-001')->firstOrFail();

        $this->assertNotNull($producto->ficha_tecnica_path);
        $this->assertSame('ficha-tecnica.pdf', $producto->ficha_tecnica_nombre);
        $this->assertStringContainsString('/storage/', $producto->ficha_tecnica_url);
        $this->assertCount(2, $producto->imagenes);
        $this->assertCount(2, $producto->imagenes_producto);
        Storage::disk('public')->assertExists($producto->ficha_tecnica_path);

        foreach ($producto->imagenes as $imagen) {
            Storage::disk('public')->assertExists($imagen['path']);
        }

        $this->actingAs($this->admin)
            ->get(route('productos.index'))
            ->assertOk()
            ->assertSee('Ficha')
            ->assertSee('Filtro técnico con ficha');
    }

    public function test_admin_can_remove_existing_product_attachments(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('productos/1/fichas/anterior.pdf', 'pdf');
        Storage::disk('public')->put('productos/1/imagenes/anterior.jpg', 'img');

        $producto = Producto::create([
            'codigo' => 'FT-002',
            'descripcion' => 'Producto con adjuntos previos',
            'unidad' => 'PZA',
            'costo' => 10,
            'precio' => 25,
            'stock' => 4,
            'stock_min' => 1,
            'ficha_tecnica_path' => 'productos/1/fichas/anterior.pdf',
            'ficha_tecnica_nombre' => 'anterior.pdf',
            'ficha_tecnica_mime' => 'application/pdf',
            'ficha_tecnica_tamano' => 3,
            'imagenes' => [[
                'path' => 'productos/1/imagenes/anterior.jpg',
                'nombre' => 'anterior.jpg',
                'mime' => 'image/jpeg',
                'tamano' => 3,
            ]],
        ]);

        $this->actingAs($this->admin)
            ->put(route('productos.update', $producto), [
                'codigo' => 'FT-002',
                'descripcion' => 'Producto con adjuntos previos',
                'unidad' => 'PZA',
                'costo' => 10,
                'precio' => 25,
                'stock' => 4,
                'stock_min' => 1,
                'quitar_ficha_tecnica' => '1',
                'quitar_imagenes' => [0],
            ])
            ->assertSessionHas('exito');

        $producto->refresh();

        $this->assertNull($producto->ficha_tecnica_path);
        $this->assertNull($producto->imagenes);
        Storage::disk('public')->assertMissing('productos/1/fichas/anterior.pdf');
        Storage::disk('public')->assertMissing('productos/1/imagenes/anterior.jpg');
    }
}
