<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\AdjustProductStock;
use App\Actions\SaveProduct;
use App\Enums\GymRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\AdjustProductStockRequest;
use App\Http\Requests\Dashboard\SaveProductRequest;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductStockMovement;
use App\Support\Audit;
use App\Support\TeamBranches;
use App\Tenancy\CurrentGym;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductInventoryController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Product::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive', 'out-of-stock', 'archived', 'low-stock'])],
            'tab' => ['nullable', Rule::in(['inventory', 'history'])],
        ]);
        $search = trim($filters['search'] ?? '');
        $status = $filters['status'] ?? 'all';
        $status = $status === 'archived' ? 'inactive' : $status;
        $tab = $filters['tab'] ?? 'inventory';
        $current = app(CurrentGym::class);
        $branchId = $current->viewBranchId();
        $staffBranchIds = $current->role() === GymRole::Staff
            ? TeamBranches::ids($request->user()->gyms()->whereKey($current->id())->firstOrFail()->pivot)
            : [];
        abort_if($current->role() === GymRole::Staff && $staffBranchIds === [], 403);
        abort_if($branchId && $current->role() === GymRole::Staff && ! in_array($branchId, $staffBranchIds, true), 403);
        $stockBranchIds = $branchId ? [$branchId] : $staffBranchIds;
        $searchPattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%';
        $products = Product::query()
            ->withSum(['stocks as stock_quantity' => fn ($query) => $branchId
                ? $query->where('branch_id', $branchId)
                : ($staffBranchIds ? $query->whereIn('branch_id', $staffBranchIds) : $query)], 'quantity')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$searchPattern])->orWhereRaw("LOWER(sku) LIKE ? ESCAPE '!'", [$searchPattern])))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('status', $status))
            ->when($status === 'out-of-stock', fn ($query) => $query->where('status', 'out_of_stock'))
            ->when($status === 'low-stock', function ($query) use ($stockBranchIds) {
                $branchFilter = $stockBranchIds ? ' and product_stocks.branch_id in ('.implode(',', array_fill(0, count($stockBranchIds), '?')).')' : '';
                $query->where('status', '!=', 'inactive')->whereRaw('(select coalesce(sum(product_stocks.quantity), 0) from product_stocks where product_stocks.product_id = products.id'.$branchFilter.') <= products.low_stock_threshold', $stockBranchIds);
            })
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Product $product) => [
                ...$product->only(['id', 'name', 'sku', 'category', 'price_centavos', 'low_stock_threshold', 'status']),
                ...($current->role() === GymRole::BranchManager ? [] : ['cost_centavos' => $product->cost_centavos]),
                'stockQuantity' => (int) ($product->stock_quantity ?? 0),
            ]);
        $branches = Branch::where('is_active', true)->when($current->role() === GymRole::Staff, fn ($query) => $query->whereIn('id', $staffBranchIds))->orderBy('name')->get(['id', 'name'])->map(fn (Branch $branch) => $branch->only(['id', 'name']));

        return Inertia::render('dashboard/product-inventory/Index', [
            'products' => $products,
            'movements' => $tab === 'history'
                ? ProductStockMovement::with(['product:id,name,sku', 'branch:id,name', 'user:id,name'])->when($stockBranchIds, fn ($query) => $query->whereIn('branch_id', $stockBranchIds))->latest('id')->paginate(25, ['*'], 'movement_page')->withQueryString()->through(fn (ProductStockMovement $movement) => [
                    'id' => $movement->id,
                    'productName' => $movement->product?->name ?? __('Archived product'),
                    'sku' => $movement->product?->sku,
                    'branchName' => $movement->branch?->name ?? __('Inactive branch'),
                    'type' => $movement->type,
                    'quantity' => $movement->quantity,
                    'stockBefore' => $movement->stock_before,
                    'stockAfter' => $movement->stock_after,
                    'reason' => $movement->reason,
                    'createdAt' => $movement->created_at->setTimezone($current->get()->timezone)->format('F j, Y g:i A'),
                    'createdBy' => $movement->user?->name ?? __('Not recorded'),
                ])
                : null,
            'branches' => $branches,
            'branchId' => $branchId,
            'filters' => ['search' => $search, 'status' => $status, 'tab' => $tab],
            'canManage' => Gate::allows('create', Product::class),
            'canArchive' => $current->role()?->value === 'owner',
            'canAdjustStock' => $branches->isNotEmpty(),
        ]);
    }

    public function store(SaveProductRequest $request, SaveProduct $save): RedirectResponse
    {
        $save->handle($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product added to inventory.')]);

        return to_route('product-inventory.index');
    }

    public function show(Product $product): Response
    {
        Gate::authorize('viewAny', Product::class);

        return $this->detail($product, true);
    }

    public function edit(Product $product): Response
    {
        Gate::authorize('update', $product);

        return $this->detail($product, false);
    }

    private function detail(Product $product, bool $readOnly): Response
    {
        $current = app(CurrentGym::class);
        $branchId = $current->viewBranchId();
        $staffBranchIds = $current->role() === GymRole::Staff
            ? TeamBranches::ids(request()->user()->gyms()->whereKey($current->id())->firstOrFail()->pivot)
            : [];
        abort_if($current->role() === GymRole::Staff && $staffBranchIds === [], 403);
        abort_if($branchId && $current->role() === GymRole::Staff && ! in_array($branchId, $staffBranchIds, true), 403);
        $stockBranchIds = $branchId ? [$branchId] : $staffBranchIds;
        $stocks = $product->stocks()->with('branch:id,name')->when($stockBranchIds, fn ($query) => $query->whereIn('branch_id', $stockBranchIds))->get();
        $status = $product->status ?: ($product->is_active ? 'active' : 'inactive');
        $history = AuditLog::where('subject_type', $product->getMorphClass())
            ->where('subject_id', $product->id)
            ->whereIn('action', ['product.created', 'product.updated', 'product.activated', 'product.archived'])
            ->with('user:id,name')
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(function (AuditLog $log) use ($current) {
                $before = $log->changes['before'] ?? [];
                $after = $log->changes['after'] ?? [];
                $labels = [
                    'name' => 'Name',
                    'sku' => 'SKU',
                    'category' => 'Category',
                    'cost_centavos' => 'Unit cost',
                    'price_centavos' => 'Selling price',
                    'low_stock_threshold' => 'Low-stock alert',
                    'status' => 'Status',
                ];
                $details = [];
                foreach ($labels as $field => $label) {
                    if (($before[$field] ?? null) === ($after[$field] ?? null)) {
                        continue;
                    }
                    $format = function ($value) use ($field) {
                        if ($field === 'cost_centavos' || $field === 'price_centavos') {
                            return '₱'.number_format(((int) $value) / 100, 2);
                        }

                        return $value === null || $value === '' ? __('Not set') : (string) $value;
                    };
                    $details[] = $label.': '.$format($before[$field] ?? null).' → '.$format($after[$field] ?? null);
                }

                return [
                    'id' => $log->id,
                    'action' => match ($log->action) {
                        'product.created' => 'Created',
                        'product.updated' => 'Updated',
                        'product.activated' => 'Activated',
                        'product.archived' => 'Deactivated',
                    },
                    'at' => $log->created_at->setTimezone($current->get()->timezone)->format('F j, Y g:i A'),
                    'by' => $log->user?->name ?? __('Not recorded'),
                    'details' => $details,
                ];
            });

        return Inertia::render('dashboard/product-inventory/Show', [
            'product' => [
                ...$product->only(['id', 'name', 'sku', 'category', 'price_centavos', 'low_stock_threshold']),
                ...($current->role() === GymRole::BranchManager ? [] : ['cost_centavos' => $product->cost_centavos]),
                'status' => $status,
                'stockQuantity' => (int) $stocks->sum('quantity'),
                'stocks' => $stocks->map(fn ($stock) => ['branchName' => $stock->branch?->name ?? __('Inactive branch'), 'quantity' => $stock->quantity])->values()->all(),
            ],
            'readOnly' => $readOnly,
            'canManage' => Gate::allows('update', $product),
            'canArchive' => Gate::allows('archive', $product),
            'history' => $history,
        ]);
    }

    public function update(SaveProductRequest $request, Product $product, SaveProduct $save): RedirectResponse
    {
        $save->handle($request->validated(), $product);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product updated.')]);

        return to_route('product-inventory.show', $product);
    }

    public function archive(Request $request, Product $product): RedirectResponse
    {
        Gate::authorize('archive', $product);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        DB::transaction(function () use ($product, $data) {
            $product = Product::lockForUpdate()->findOrFail($product->id);
            Gate::authorize('archive', $product);
            if ($product->is_active === (bool) $data['is_active']) {
                return;
            }
            $product->status = (bool) $data['is_active'] ? 'active' : 'inactive';
            $product->is_active = (bool) $data['is_active'];
            $product->save();
            Audit::log($product->is_active ? 'product.activated' : 'product.archived', $product);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => $data['is_active'] ? __('Product activated.') : __('Product deactivated.')]);

        return to_route('product-inventory.index');
    }

    public function adjustStock(AdjustProductStockRequest $request, Product $product, AdjustProductStock $adjust): RedirectResponse
    {
        $adjust->handle($product, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stock updated.')]);

        return to_route('product-inventory.index');
    }
}
