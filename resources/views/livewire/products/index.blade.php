<?php

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Title('Products')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public bool $showForm = false;
    public bool $showDelete = false;
    public bool $showDetails = false;

    #[Locked]
    public ?int $editingProductId = null;

    #[Locked]
    public ?int $deletingProductId = null;

    #[Locked]
    public ?int $viewingProductId = null;

    public string $name = '';
    public string $sku = '';
    public string $description = '';
    public string $price = '';
    public string $stock = '0';
    public bool $is_active = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $product = Product::findOrFail($id);
        $this->resetForm();
        $this->editingProductId = $product->id;
        $this->name = $product->name;
        $this->sku = $product->sku;
        $this->description = $product->description ?? '';
        $this->price = $product->price;
        $this->stock = (string) $product->stock;
        $this->is_active = $product->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $product = $this->editingProductId
            ? Product::findOrFail($this->editingProductId)
            : new Product;

        $this->name = trim($this->name);
        $this->sku = strtoupper(trim($this->sku));
        $this->description = trim($this->description);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product)],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'stock' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'is_active' => ['boolean'],
        ]);

        $validated['description'] = $validated['description'] ?: null;
        $product->fill($validated)->save();

        session()->flash('product-status', $this->editingProductId ? 'Product updated successfully.' : 'Product created successfully.');
        $this->resetForm();
        $this->resetPage();
    }

    public function viewProduct(int $id): void
    {
        $this->viewingProductId = Product::findOrFail($id)->id;
        $this->showDetails = true;
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingProductId = Product::findOrFail($id)->id;
        $this->showDelete = true;
    }

    public function delete(): void
    {
        Product::findOrFail($this->deletingProductId)->delete();
        $this->reset('deletingProductId', 'showDelete');
        $this->resetPage();
        session()->flash('product-status', 'Product deleted successfully.');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset('editingProductId', 'name', 'sku', 'description', 'price', 'stock', 'is_active', 'showForm');
        $this->resetValidation();
    }

    public function with(): array
    {
        return [
            'products' => Product::query()
                ->when(trim($this->search) !== '', function (Builder $query) {
                    $search = '%'.trim($this->search).'%';

                    $query->where(function (Builder $query) use ($search) {
                        $query->where('name', 'like', $search)->orWhere('sku', 'like', $search);
                    });
                })
                ->when(in_array($this->status, ['active', 'inactive'], true), fn (Builder $query) => $query->where('is_active', $this->status === 'active'))
                ->latest('id')
                ->paginate(10),
            'viewingProduct' => $this->showDetails ? Product::find($this->viewingProductId) : null,
            'deletingProduct' => $this->showDelete ? Product::find($this->deletingProductId) : null,
        ];
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Products') }}</flux:heading>
            <flux:subheading>{{ __('Manage your product catalog, prices, and stock.') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add product') }}</flux:button>
    </div>

    @if (session('product-status'))
        <div role="status" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
            {{ session('product-status') }}
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-[1fr_12rem]">
        <flux:input wire:model.live.debounce.300ms="search" label="{{ __('Search products') }}" placeholder="{{ __('Search by name or SKU...') }}" icon="magnifying-glass" type="search" />
        <flux:select wire:model.live="status" label="{{ __('Status') }}">
            <option value="">{{ __('All statuses') }}</option>
            <option value="active">{{ __('Active') }}</option>
            <option value="inactive">{{ __('Inactive') }}</option>
        </flux:select>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">{{ __('Product catalog') }}</caption>
            <thead class="bg-zinc-50 text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                <tr>
                    <th scope="col" class="px-4 py-3 font-medium">{{ __('Product') }}</th>
                    <th scope="col" class="px-4 py-3 font-medium">{{ __('SKU') }}</th>
                    <th scope="col" class="px-4 py-3 text-right font-medium">{{ __('Price') }}</th>
                    <th scope="col" class="px-4 py-3 text-right font-medium">{{ __('Stock') }}</th>
                    <th scope="col" class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                    <th scope="col" class="px-4 py-3 text-right font-medium">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($products as $product)
                    <tr wire:key="product-{{ $product->id }}">
                        <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">{{ $product->name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $product->sku }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ number_format((float) $product->price, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($product->stock) }}</td>
                        <td class="px-4 py-3">
                            <flux:badge :color="$product->is_active ? 'green' : 'zinc'" size="sm">{{ $product->is_active ? __('Active') : __('Inactive') }}</flux:badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <flux:button size="sm" variant="ghost" wire:click="viewProduct({{ $product->id }})" aria-label="{{ __('View :name', ['name' => $product->name]) }}">{{ __('View') }}</flux:button>
                                <flux:button size="sm" wire:click="edit({{ $product->id }})" aria-label="{{ __('Edit :name', ['name' => $product->name]) }}">{{ __('Edit') }}</flux:button>
                                <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $product->id }})" aria-label="{{ __('Delete :name', ['name' => $product->name]) }}">{{ __('Delete') }}</flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                            {{ $search !== '' || $status !== '' ? __('No products match your filters.') : __('No products yet. Add your first product to get started.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $products->links() }}

    <flux:modal wire:model="showForm" name="product-form" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingProductId ? __('Edit product') : __('Add product') }}</flux:heading>
                <flux:subheading>{{ __('Enter the product details below.') }}</flux:subheading>
            </div>
            <flux:input wire:model="name" label="{{ __('Product name') }}" required maxlength="255" autofocus />
            <flux:input wire:model="sku" label="{{ __('SKU') }}" required maxlength="100" placeholder="PRD-001" />
            <flux:textarea wire:model="description" label="{{ __('Description') }}" rows="3" maxlength="5000" />
            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="price" label="{{ __('Price') }}" type="number" min="0" max="99999999.99" step="0.01" required />
                <flux:input wire:model="stock" label="{{ __('Stock') }}" type="number" min="0" max="2147483647" step="1" required />
            </div>
            <flux:checkbox wire:model="is_active" label="{{ __('Active product') }}" />
            <div class="flex justify-end gap-3">
                <flux:button type="button" wire:click="cancel">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">{{ __('Save product') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDetails" name="product-details" class="w-full max-w-lg">
        @if ($viewingProduct)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $viewingProduct->name }}</flux:heading>
                    <flux:subheading>{{ $viewingProduct->sku }}</flux:subheading>
                </div>
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-zinc-500 dark:text-zinc-400">{{ __('Price') }}</dt><dd class="mt-1 font-medium">{{ number_format((float) $viewingProduct->price, 2) }}</dd></div>
                    <div><dt class="text-zinc-500 dark:text-zinc-400">{{ __('Stock') }}</dt><dd class="mt-1 font-medium">{{ number_format($viewingProduct->stock) }}</dd></div>
                    <div><dt class="text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</dt><dd class="mt-1">{{ $viewingProduct->is_active ? __('Active') : __('Inactive') }}</dd></div>
                    <div><dt class="text-zinc-500 dark:text-zinc-400">{{ __('Created') }}</dt><dd class="mt-1">{{ $viewingProduct->created_at->format('M j, Y') }}</dd></div>
                    <div class="col-span-2"><dt class="text-zinc-500 dark:text-zinc-400">{{ __('Description') }}</dt><dd class="mt-1 whitespace-pre-wrap break-words">{{ $viewingProduct->description ?: __('No description provided.') }}</dd></div>
                </dl>
                <div class="flex justify-end"><flux:modal.close><flux:button>{{ __('Close') }}</flux:button></flux:modal.close></div>
            </div>
        @endif
    </flux:modal>

    <flux:modal wire:model="showDelete" name="delete-product" class="w-full max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete product?') }}</flux:heading>
                <flux:subheading>{{ __('You are about to delete :name. This action cannot be undone.', ['name' => $deletingProduct?->name ?? __('this product')]) }}</flux:subheading>
            </div>
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button>{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button variant="danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">{{ __('Delete product') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
