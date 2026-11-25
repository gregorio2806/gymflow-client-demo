<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Pencil } from '@lucide/vue';
import ProductInventoryController from '@/actions/App/Http/Controllers/Dashboard/ProductInventoryController';
import ChangeLog from '@/components/ChangeLog.vue';
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    product: {
        id: number;
        name: string;
        sku: string | null;
        category: string | null;
        cost_centavos?: number;
        price_centavos: number;
        low_stock_threshold: number;
        status: 'active' | 'inactive' | 'out_of_stock';
        stockQuantity: number;
        stocks: { branchName: string; quantity: number }[];
    };
    readOnly: boolean;
    canManage: boolean;
    history: {
        data: {
            id: number;
            action: string;
            at: string;
            by: string;
            details: string[];
        }[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();
defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Product inventory',
                href: ProductInventoryController.index(),
            },
            { title: 'Product details' },
        ],
    },
});

const currency = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
});
const form = useForm({
    name: props.product.name,
    sku: props.product.sku ?? '',
    category: props.product.category ?? '',
    cost_php: ((props.product.cost_centavos ?? 0) / 100).toFixed(2),
    price_php: (props.product.price_centavos / 100).toFixed(2),
    low_stock_threshold: props.product.low_stock_threshold,
    status: props.product.status,
});
function save() {
    form.patch(ProductInventoryController.update(props.product.id).url);
}
</script>

<template>
    <Head :title="(readOnly ? 'View ' : 'Edit ') + product.name" />
    <div class="space-y-5 p-4 sm:p-6">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">
                    {{ readOnly ? 'View product' : 'Edit product' }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    Review product details, stock, and availability status.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button v-if="readOnly && canManage" variant="outline" as-child>
                    <Link :href="ProductInventoryController.edit(product.id)">
                        <Pencil class="size-4" />Edit product
                    </Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="ProductInventoryController.index()">
                        <ArrowLeft class="size-4" />Back to inventory
                    </Link>
                </Button>
            </div>
        </header>

        <div
            class="grid items-start gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]"
        >
            <form
                class="min-w-0 space-y-4 rounded-xl border bg-card p-4 sm:p-5"
                @submit.prevent="save"
            >
                <fieldset
                    :disabled="form.processing || readOnly"
                    class="grid gap-4 sm:grid-cols-2"
                >
                    <div class="space-y-2 sm:col-span-2">
                        <Label for="product-name">Product name</Label>
                        <Input
                            id="product-name"
                            v-model="form.name"
                            required
                            maxlength="160"
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="space-y-2">
                        <Label for="product-sku">SKU (optional)</Label>
                        <Input
                            id="product-sku"
                            v-model="form.sku"
                            maxlength="80"
                        />
                        <InputError :message="form.errors.sku" />
                    </div>
                    <div class="space-y-2">
                        <Label for="product-category"
                            >Category (optional)</Label
                        >
                        <Input
                            id="product-category"
                            v-model="form.category"
                            maxlength="100"
                        />
                        <InputError :message="form.errors.category" />
                    </div>
                    <div class="space-y-2">
                        <Label for="product-cost">Unit cost (₱)</Label>
                        <Input
                            id="product-cost"
                            v-model="form.cost_php"
                            inputmode="decimal"
                            required
                        />
                        <InputError :message="form.errors.cost_php" />
                    </div>
                    <div class="space-y-2">
                        <Label for="product-price">Selling price (₱)</Label>
                        <Input
                            id="product-price"
                            v-model="form.price_php"
                            inputmode="decimal"
                            required
                        />
                        <InputError :message="form.errors.price_php" />
                    </div>
                    <div class="space-y-2">
                        <Label for="product-status">Status</Label>
                        <NativeSelect
                            id="product-status"
                            v-model="form.status"
                            required
                        >
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="out_of_stock">Out of stock</option>
                        </NativeSelect>
                        <InputError :message="form.errors.status" />
                    </div>
                    <div class="space-y-2">
                        <Label for="product-threshold"
                            >Low-stock alert at</Label
                        >
                        <Input
                            id="product-threshold"
                            v-model.number="form.low_stock_threshold"
                            type="number"
                            min="0"
                            max="1000000"
                            required
                        />
                        <InputError
                            :message="form.errors.low_stock_threshold"
                        />
                    </div>
                </fieldset>
                <div role="alert">
                    <InputError
                        v-for="(error, key) in form.errors"
                        :key="key"
                        :message="error"
                    />
                </div>
                <div v-if="!readOnly" class="flex gap-2">
                    <Button :disabled="form.processing">Save changes</Button>
                    <Button variant="outline" as-child>
                        <Link
                            :href="ProductInventoryController.show(product.id)"
                            >Cancel</Link
                        >
                    </Button>
                </div>
            </form>

            <section class="min-w-0 rounded-xl border bg-card p-4 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="font-semibold">Inventory</h2>
                        <p class="text-sm text-muted-foreground">
                            Combined stock: {{ product.stockQuantity }}
                        </p>
                    </div>
                    <Badge
                        variant="outline"
                        :class="
                            product.status === 'active'
                                ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
                                : product.status === 'out_of_stock'
                                  ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
                                  : ''
                        "
                        >{{
                            product.status === 'out_of_stock'
                                ? 'Out of stock'
                                : product.status === 'active'
                                  ? 'Active'
                                  : 'Inactive'
                        }}</Badge
                    >
                </div>
                <div
                    v-if="product.stocks.length"
                    class="mt-4 divide-y rounded-md border"
                >
                    <div
                        v-for="stock in product.stocks"
                        :key="stock.branchName"
                        class="flex items-center justify-between px-3 py-2 text-sm"
                    >
                        <span>{{ stock.branchName }}</span>
                        <span class="font-medium">{{ stock.quantity }}</span>
                    </div>
                </div>
                <p v-else class="mt-4 text-sm text-muted-foreground">
                    No stock recorded for the selected branch.
                </p>
                <p class="mt-3 text-xs text-muted-foreground">
                    Selling price:
                    {{ currency.format(product.price_centavos / 100) }} ·
                    Low-stock alert at {{ product.low_stock_threshold }}
                </p>
            </section>

            <ChangeLog
                :history="history"
                :subtitle="`${product.name} · Manila time · Latest first`"
                content-id="product-change-log-content"
                class="lg:col-start-2 lg:row-span-2 lg:row-start-1"
            />
        </div>
    </div>
</template>
