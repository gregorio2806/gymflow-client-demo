<?php

namespace App\Actions;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductStockMovement;
use App\Support\Audit;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SaveProduct
{
    public function handle(array $data, ?Product $product = null): Product
    {
        return DB::transaction(function () use ($data, $product) {
            $creating = $product === null;
            $product = $creating ? new Product : Product::lockForUpdate()->findOrFail($product->id);
            Gate::authorize($creating ? 'create' : 'update', $creating ? Product::class : $product);
            $before = $product->only(['name', 'sku', 'category', 'cost_centavos', 'price_centavos', 'low_stock_threshold', 'status']);
            $product->fill(Arr::only($data, ['name', 'sku', 'category', 'low_stock_threshold']));
            $product->cost_centavos = self::toCentavos($data['cost_php']);
            $product->price_centavos = self::toCentavos($data['price_php']);
            $product->status = $data['status'] ?? ($product->status ?: ($product->exists && $product->is_active === false ? 'inactive' : 'active'));
            $product->is_active = $product->status !== 'inactive';
            $product->save();
            if ($creating) {
                $branch = Branch::whereKey($data['initial_branch_id'])->where('is_active', true)->firstOrFail();
                Gate::authorize('adjustStock', [$product, (int) $branch->id]);
                $initialStock = (int) ($data['initial_stock'] ?? 0);
                if ($initialStock > 0) {
                    $stock = new ProductStock(['quantity' => $initialStock]);
                    $stock->product()->associate($product);
                    $stock->branch()->associate($branch);
                    $stock->save();

                    $movement = new ProductStockMovement(['type' => 'received', 'quantity' => $initialStock, 'stock_before' => 0, 'stock_after' => $initialStock, 'reason' => '', 'user_id' => auth()->id()]);
                    $movement->product()->associate($product);
                    $movement->branch()->associate($branch);
                    $movement->save();
                }
            }
            Audit::log($creating ? 'product.created' : 'product.updated', $product, [
                'before' => $creating ? [] : $before,
                'after' => $product->only(['name', 'sku', 'category', 'cost_centavos', 'price_centavos', 'low_stock_threshold', 'status']),
            ]);

            return $product;
        });
    }

    public static function toCentavos(string $amount): int
    {
        [$pesos, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return (int) $pesos * 100 + (int) str_pad($fraction, 2, '0');
    }
}
