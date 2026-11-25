<?php

namespace App\Actions;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductStockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AdjustProductStock
{
    public function handle(Product $product, array $data): ProductStockMovement
    {
        return DB::transaction(function () use ($product, $data) {
            $product = Product::lockForUpdate()->findOrFail($product->id);
            $branch = Branch::whereKey($data['branch_id'])->where('is_active', true)->firstOrFail();
            Gate::authorize('adjustStock', [$product, (int) $branch->id]);

            $stock = ProductStock::where('product_id', $product->id)
                ->where('branch_id', $branch->id)
                ->lockForUpdate()
                ->first();
            if (! $stock) {
                $stock = new ProductStock(['quantity' => 0]);
                $stock->product()->associate($product);
                $stock->branch()->associate($branch);
                $stock->save();
            }
            $before = $stock->quantity;
            $quantity = (int) $data['quantity'];
            if ($data['type'] === 'removed' && $quantity > $before) {
                throw ValidationException::withMessages(['quantity' => __('Cannot remove more than the current stock of :count.', ['count' => $before])]);
            }
            $after = $data['type'] === 'received' ? $before + $quantity : $before - $quantity;
            if ($after > 1000000) {
                throw ValidationException::withMessages(['quantity' => __('Stock cannot exceed 1,000,000 units.')]);
            }

            $stock->quantity = $after;
            $stock->save();

            $movement = new ProductStockMovement([
                'type' => $data['type'],
                'quantity' => $quantity,
                'stock_before' => $before,
                'stock_after' => $after,
                'reason' => $data['reason'] ?? '',
                'user_id' => auth()->id(),
            ]);
            $movement->product()->associate($product);
            $movement->branch()->associate($branch);
            $movement->save();

            return $movement;
        });
    }
}
