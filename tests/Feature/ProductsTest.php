<?php

use App\Models\Product;
use App\Models\User;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Volt\Volt;

test('guests cannot access products', function () {
    $this->get(route('products.index'))->assertRedirect(route('login'));
});

test('authenticated users can access the product catalog', function () {
    $product = Product::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('products.index'))
        ->assertOk()
        ->assertSee($product->name)
        ->assertSee($product->sku);
});

test('products can be created with normalized values', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('products.index')
        ->call('create')
        ->set('name', ' Test product ')
        ->set('sku', ' prd-001 ')
        ->set('description', ' Product description ')
        ->set('price', '125.50')
        ->set('stock', '12')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false)
        ->assertSee('Product created successfully.');

    $this->assertDatabaseHas('products', [
        'name' => 'Test product',
        'sku' => 'PRD-001',
        'description' => 'Product description',
        'price' => '125.50',
        'stock' => 12,
        'is_active' => true,
    ]);
});

test('product validation rejects invalid values without saving', function ($field, $value) {
    $this->actingAs(User::factory()->create());

    Volt::test('products.index')
        ->set('name', 'Valid product')
        ->set('sku', 'PRD-001')
        ->set('price', '10.00')
        ->set('stock', '5')
        ->set($field, $value)
        ->call('save')
        ->assertHasErrors([$field]);

    $this->assertDatabaseCount('products', 0);
})->with([
    'blank name' => ['name', '   '],
    'blank SKU' => ['sku', '   '],
    'missing price' => ['price', ''],
    'negative price' => ['price', '-0.01'],
    'excessive price' => ['price', '100000000.00'],
    'fractional cents' => ['price', '1.001'],
    'negative stock' => ['stock', '-1'],
    'fractional stock' => ['stock', '1.5'],
    'excessive stock' => ['stock', '2147483648'],
]);

test('SKU must be unique after normalization', function () {
    Product::factory()->create(['sku' => 'PRD-001']);
    $this->actingAs(User::factory()->create());

    Volt::test('products.index')
        ->set('name', 'Another product')
        ->set('sku', ' prd-001 ')
        ->set('price', '10')
        ->call('save')
        ->assertHasErrors(['sku' => 'unique']);

    $this->assertDatabaseCount('products', 1);
});

test('products can be updated while keeping their own SKU', function () {
    $product = Product::factory()->create(['sku' => 'PRD-001']);
    $this->actingAs(User::factory()->create());

    Volt::test('products.index')
        ->call('edit', $product->id)
        ->assertSet('name', $product->name)
        ->set('name', 'Updated product')
        ->set('price', '99.95')
        ->set('stock', '0')
        ->set('description', '')
        ->set('is_active', false)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Product updated successfully.');

    expect($product->refresh())
        ->name->toBe('Updated product')
        ->price->toBe('99.95')
        ->stock->toBe(0)
        ->description->toBeNull()
        ->is_active->toBeFalse();
    $this->assertDatabaseCount('products', 1);
});

test('updating a product cannot reuse another products SKU', function () {
    $product = Product::factory()->create(['sku' => 'PRD-001']);
    Product::factory()->create(['sku' => 'PRD-002']);
    $this->actingAs(User::factory()->create());

    Volt::test('products.index')
        ->call('edit', $product->id)
        ->set('sku', 'PRD-002')
        ->call('save')
        ->assertHasErrors(['sku' => 'unique']);

    expect($product->refresh()->sku)->toBe('PRD-001');
});

test('cancel discards edits and create starts with a clean form', function () {
    $product = Product::factory()->create();
    $this->actingAs(User::factory()->create());

    Volt::test('products.index')
        ->call('edit', $product->id)
        ->set('name', 'Unsaved change')
        ->call('cancel')
        ->assertSet('showForm', false)
        ->call('create')
        ->assertSet('editingProductId', null)
        ->assertSet('name', '')
        ->assertSet('stock', '0')
        ->assertSet('is_active', true);

    expect($product->refresh()->name)->not->toBe('Unsaved change');
});

test('product details can be viewed', function () {
    $product = Product::factory()->create(['description' => 'Detailed product information']);
    $this->actingAs(User::factory()->create());

    Volt::test('products.index')
        ->call('viewProduct', $product->id)
        ->assertSet('showDetails', true)
        ->assertSee('Detailed product information');
});

test('products can be deleted after selecting a product for confirmation', function () {
    $product = Product::factory()->create();
    $other = Product::factory()->create();
    $this->actingAs(User::factory()->create());

    $component = Volt::test('products.index')
        ->call('confirmDelete', $product->id)
        ->assertSet('showDelete', true);

    $this->assertDatabaseHas('products', ['id' => $product->id]);

    $component->call('delete')
        ->assertSet('showDelete', false)
        ->assertSet('deletingProductId', null)
        ->assertSee('Product deleted successfully.');

    $this->assertDatabaseMissing('products', ['id' => $product->id]);
    $this->assertDatabaseHas('products', ['id' => $other->id]);
});

test('product search matches names and SKUs and combines with status filter', function () {
    Product::factory()->create(['name' => 'Blue keyboard', 'sku' => 'KEY-001']);
    Product::factory()->create(['name' => 'Red keyboard', 'sku' => 'KEY-002', 'is_active' => false]);
    Product::factory()->create(['name' => 'Wireless mouse', 'sku' => 'MSE-001']);
    $this->actingAs(User::factory()->create());

    Volt::test('products.index')
        ->set('search', 'keyboard')
        ->assertSee('Blue keyboard')
        ->assertSee('Red keyboard')
        ->assertDontSee('Wireless mouse')
        ->set('status', 'inactive')
        ->assertSee('Red keyboard')
        ->assertDontSee('Blue keyboard')
        ->set('status', '')
        ->set('search', 'MSE-001')
        ->assertSee('Wireless mouse')
        ->assertDontSee('Blue keyboard');
});

test('search and status changes reset pagination', function () {
    Product::factory()->count(11)->create();
    $this->actingAs(User::factory()->create());

    Volt::test('products.index')
        ->call('setPage', 2)
        ->assertSet('paginators.page', 2)
        ->set('search', 'not-found')
        ->assertSet('paginators.page', 1)
        ->assertSee('No products match your filters.')
        ->set('search', '')
        ->call('setPage', 2)
        ->set('status', 'active')
        ->assertSet('paginators.page', 1);
});

test('product mutation IDs cannot be changed from the client', function ($property) {
    $this->actingAs(User::factory()->create());

    Volt::test('products.index')->set($property, 123);
})->with(['editingProductId', 'deletingProductId'])
    ->throws(CannotUpdateLockedPropertyException::class);

test('editing a product deleted since opening the form does not recreate it', function () {
    $product = Product::factory()->create();
    $this->actingAs(User::factory()->create());

    $component = Volt::test('products.index')->call('edit', $product->id);
    $product->delete();

    $component->call('save')->assertNotFound();

    $this->assertDatabaseCount('products', 0);
});
