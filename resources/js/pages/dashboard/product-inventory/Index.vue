<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Archive,
    ArrowDownToLine,
    ArrowUpFromLine,
    ChevronDown,
    Eye,
    Plus,
    RotateCcw,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ProductInventoryController from '@/actions/App/Http/Controllers/Dashboard/ProductInventoryController';
import InputError from '@/components/InputError.vue';
import RecordList from '@/components/RecordList.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type ProductRow = {
    id: number;
    name: string;
    sku: string | null;
    category: string | null;
    cost_centavos?: number;
    price_centavos: number;
    low_stock_threshold: number;
    status: 'active' | 'inactive' | 'out_of_stock';
    stockQuantity: number;
};
type MovementPage = {
    data: {
        id: number;
        productName: string;
        sku: string | null;
        branchName: string;
        type: 'received' | 'removed';
        quantity: number;
        stockBefore: number;
        stockAfter: number;
        reason: string;
        createdAt: string;
        createdBy: string;
    }[];
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};
const props = defineProps<{
    products: {
        data: ProductRow[];
        total: number;
        from: number | null;
        to: number | null;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    movements: MovementPage | null;
    branches: { id: number; name: string }[];
    branchId: number | null;
    filters: { search: string; status: string; tab: string };
    canManage: boolean;
    canArchive: boolean;
    canAdjustStock: boolean;
}>();
defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Product inventory',
                href: ProductInventoryController.index(),
            },
        ],
    },
});

const currency = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
});
const search = ref(props.filters.search);
const productDialog = ref(false);
const stockDialog = ref(false);
const selectedProduct = ref<ProductRow | null>(null);
const productForm = useForm({
    name: '',
    sku: '',
    category: '',
    cost_php: '0.00',
    price_php: '0.00',
    low_stock_threshold: 0,
    status: 'active' as 'active' | 'inactive' | 'out_of_stock',
    initial_stock: 0,
    initial_branch_id: props.branchId ?? props.branches[0]?.id ?? 0,
});
const stockForm = useForm({
    branch_id: props.branchId ?? props.branches[0]?.id ?? 0,
    type: 'received' as 'received' | 'removed',
    quantity: 1,
    reason: '',
});
const activeTab = computed(() => props.filters.tab);

function filter(tab = props.filters.tab, status = props.filters.status) {
    router.get(
        ProductInventoryController.index().url,
        { search: search.value, status, tab },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}
function openProduct() {
    productForm.defaults({
        name: '',
        sku: '',
        category: '',
        cost_php: '0.00',
        price_php: '0.00',
        low_stock_threshold: 0,
        status: 'active',
        initial_stock: 0,
        initial_branch_id: props.branchId ?? props.branches[0]?.id ?? 0,
    });
    productForm.reset();
    productForm.clearErrors();
    productDialog.value = true;
}
function saveProduct() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            productDialog.value = false;
        },
    };
    productForm.post(ProductInventoryController.store().url, options);
}
function openStock(
    product: ProductRow,
    type: 'received' | 'removed' = 'received',
) {
    selectedProduct.value = product;
    stockForm.defaults({
        branch_id: props.branchId ?? props.branches[0]?.id ?? 0,
        type,
        quantity: 1,
        reason: '',
    });
    stockForm.reset();
    stockForm.clearErrors();
    stockDialog.value = true;
}
function saveStock() {
    if (!selectedProduct.value) return;
    stockForm.post(
        ProductInventoryController.adjustStock(selectedProduct.value.id).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                stockDialog.value = false;
            },
        },
    );
}
function toggleArchive(product: ProductRow) {
    router.patch(
        ProductInventoryController.archive(product.id).url,
        { is_active: product.status === 'inactive' },
        { preserveScroll: true },
    );
}
function stockStatus(product: ProductRow) {
    return product.stockQuantity <= product.low_stock_threshold;
}
</script>

<template>
    <Head title="Product inventory" />
    <div class="@container space-y-5 p-4 sm:p-6">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Product inventory</h1>
                <p class="text-sm text-muted-foreground">
                    Manage products and stock at each branch.
                </p>
            </div>
            <Button v-if="canManage" @click="openProduct()"
                ><Plus class="size-4" />Add product</Button
            >
        </header>

        <nav
            aria-label="Product inventory sections"
            class="flex flex-wrap gap-1 border-b pb-2"
        >
            <Button
                :variant="activeTab === 'inventory' ? 'secondary' : 'ghost'"
                @click="filter('inventory')"
                >Inventory</Button
            >
            <Button
                :variant="activeTab === 'history' ? 'secondary' : 'ghost'"
                @click="filter('history')"
                >Stock history</Button
            >
        </nav>

        <form
            v-if="activeTab === 'inventory'"
            class="flex flex-wrap items-center gap-2"
            @submit.prevent="filter('inventory')"
        >
            <Input
                v-model="search"
                aria-label="Search products"
                placeholder="Search name or SKU"
                class="w-full sm:w-64"
            />
            <NativeSelect
                :value="filters.status"
                aria-label="Product inventory filter"
                class="w-full sm:w-48"
                @change="
                    filter(
                        'inventory',
                        ($event.target as HTMLSelectElement).value,
                    )
                "
            >
                <option value="all">All products</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="out-of-stock">Out of stock</option>
                <option value="low-stock">Low stock</option>
            </NativeSelect>
            <Button type="submit" variant="outline">Search</Button>
        </form>

        <section v-if="activeTab === 'inventory'" class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm text-muted-foreground">
                    {{
                        branchId
                            ? `Stock at ${branches.find((branch) => branch.id === branchId)?.name ?? 'selected branch'}`
                            : 'Combined stock across branches'
                    }}
                </p>
                <p v-if="products.total" class="text-sm text-muted-foreground">
                    Showing {{ products.from }}–{{ products.to }} of
                    {{ products.total }} products
                </p>
            </div>
            <RecordList
                :rows="products.data"
                :columns="[
                    {
                        key: 'name',
                        label: 'Product',
                        width: '24%',
                    },
                    {
                        key: 'category',
                        label: 'Category',
                        width: '14%',
                    },
                    ...(canManage
                        ? [{ key: 'cost', label: 'Cost', width: '12%' }]
                        : []),
                    {
                        key: 'price',
                        label: 'Selling price',
                        width: '14%',
                    },
                    {
                        key: 'stock',
                        label: 'Stock',
                        width: '12%',
                    },
                    {
                        key: 'status',
                        label: 'Status',
                        width: '10%',
                    },
                    {
                        key: 'actions',
                        label: 'Actions',
                        width: '14%',
                    },
                ]"
                caption="Product inventory"
                row-key="id"
                wide
                empty="No products found."
            >
                <template #cell-name="{ row }">
                    <p class="font-medium">{{ row.name }}</p>
                    <p class="text-xs text-muted-foreground">
                        SKU: {{ row.sku || 'Not set' }}
                    </p>
                </template>
                <template #cell-category="{ row }">{{
                    row.category || '—'
                }}</template>
                <template #cell-cost="{ row }">{{
                    currency.format((row.cost_centavos ?? 0) / 100)
                }}</template>
                <template #cell-price="{ row }">{{
                    currency.format(row.price_centavos / 100)
                }}</template>
                <template #cell-stock="{ row }">
                    <span class="font-medium">{{ row.stockQuantity }}</span>
                    <Badge
                        v-if="stockStatus(row)"
                        class="ml-2 bg-amber-500/15 text-amber-700 dark:text-amber-300"
                        variant="outline"
                        >Low</Badge
                    >
                </template>
                <template #cell-status="{ row }">
                    <Badge
                        :class="
                            row.status === 'active'
                                ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
                                : row.status === 'out_of_stock'
                                  ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
                                  : ''
                        "
                        variant="outline"
                        >{{
                            row.status === 'out_of_stock'
                                ? 'Out of stock'
                                : row.status === 'active'
                                  ? 'Active'
                                  : 'Inactive'
                        }}</Badge
                    >
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end">
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    variant="outline"
                                    class="min-h-11 w-full max-w-36 justify-between"
                                    :aria-label="`Actions for ${row.name}`"
                                >
                                    Actions
                                    <ChevronDown class="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-48">
                                <DropdownMenuItem as-child>
                                    <Link
                                        :href="
                                            ProductInventoryController.show(
                                                row.id,
                                            )
                                        "
                                    >
                                        <Eye />View product
                                    </Link>
                                </DropdownMenuItem>
                                <template
                                    v-if="
                                        canAdjustStock &&
                                        row.status !== 'inactive'
                                    "
                                >
                                    <DropdownMenuItem
                                        @select="openStock(row, 'received')"
                                    >
                                        <ArrowDownToLine />Receive stock
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        @select="openStock(row, 'removed')"
                                    >
                                        <ArrowUpFromLine />Remove stock
                                    </DropdownMenuItem>
                                </template>
                                <DropdownMenuSeparator v-if="canArchive" />
                                <DropdownMenuItem
                                    v-if="canArchive"
                                    @select="toggleArchive(row)"
                                >
                                    <RotateCcw
                                        v-if="row.status === 'inactive'"
                                    />
                                    <Archive v-else />
                                    {{
                                        row.status === 'inactive'
                                            ? 'Activate product'
                                            : 'Deactivate product'
                                    }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </template>
            </RecordList>
            <nav
                v-if="products.prev_page_url || products.next_page_url"
                class="flex justify-between"
                aria-label="Product inventory pagination"
            >
                <Button v-if="products.prev_page_url" variant="outline" as-child
                    ><Link :href="products.prev_page_url" preserve-scroll
                        >Previous</Link
                    ></Button
                ><span v-else />
                <Button v-if="products.next_page_url" variant="outline" as-child
                    ><Link :href="products.next_page_url" preserve-scroll
                        >Next</Link
                    ></Button
                >
            </nav>
        </section>

        <section v-else class="space-y-3">
            <p v-if="movements?.total" class="text-sm text-muted-foreground">
                Showing {{ movements.from }}–{{ movements.to }} of
                {{ movements.total }} stock changes
            </p>
            <RecordList
                v-if="movements"
                :rows="movements.data"
                :columns="[
                    { key: 'productName', label: 'Product', width: '20%' },
                    { key: 'branchName', label: 'Branch', width: '14%' },
                    { key: 'type', label: 'Change', width: '12%' },
                    { key: 'quantity', label: 'Quantity', width: '10%' },
                    { key: 'stockAfter', label: 'Stock after', width: '10%' },
                    { key: 'reason', label: 'Reason', width: '18%' },
                    { key: 'createdAt', label: 'Date and staff', width: '16%' },
                ]"
                caption="Product stock history"
                row-key="id"
                wide
                empty="No stock changes yet."
            >
                <template #cell-productName="{ row }"
                    ><p class="font-medium">{{ row.productName }}</p>
                    <p class="text-xs text-muted-foreground">
                        SKU: {{ row.sku || 'Not set' }}
                    </p></template
                >
                <template #cell-type="{ row }"
                    ><Badge
                        :class="
                            row.type === 'received'
                                ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
                                : 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
                        "
                        variant="outline"
                        >{{
                            row.type === 'received' ? 'Received' : 'Removed'
                        }}</Badge
                    ></template
                >
                <template #cell-quantity="{ row }"
                    >{{ row.type === 'received' ? '+' : '−'
                    }}{{ row.quantity }}</template
                >
                <template #cell-createdAt="{ row }"
                    ><p>{{ row.createdAt }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ row.createdBy }}
                    </p></template
                >
                <template #cell-reason="{ row }">{{
                    row.reason || '—'
                }}</template>
            </RecordList>
            <nav
                v-if="movements?.prev_page_url || movements?.next_page_url"
                class="flex justify-between"
                aria-label="Stock history pagination"
            >
                <Button
                    v-if="movements?.prev_page_url"
                    variant="outline"
                    as-child
                    ><Link :href="movements.prev_page_url" preserve-scroll
                        >Previous</Link
                    ></Button
                ><span v-else />
                <Button
                    v-if="movements?.next_page_url"
                    variant="outline"
                    as-child
                    ><Link :href="movements.next_page_url" preserve-scroll
                        >Next</Link
                    ></Button
                >
            </nav>
        </section>

        <Dialog v-model:open="productDialog">
            <DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
                <DialogHeader
                    ><DialogTitle>Add product</DialogTitle
                    ><DialogDescription
                        >Product details are shared across your branches. Stock
                        counts are tracked separately at each
                        branch.</DialogDescription
                    ></DialogHeader
                >
                <form class="space-y-4" @submit.prevent="saveProduct">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="product-name">Product name</Label
                            ><Input
                                id="product-name"
                                v-model="productForm.name"
                                required
                                maxlength="160"
                            /><InputError :message="productForm.errors.name" />
                        </div>
                        <div class="space-y-2">
                            <Label for="product-sku">SKU (optional)</Label
                            ><Input
                                id="product-sku"
                                v-model="productForm.sku"
                                maxlength="80"
                            /><InputError :message="productForm.errors.sku" />
                        </div>
                        <div class="space-y-2">
                            <Label for="product-category"
                                >Category (optional)</Label
                            ><Input
                                id="product-category"
                                v-model="productForm.category"
                                maxlength="100"
                            /><InputError
                                :message="productForm.errors.category"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="product-cost">Unit cost (₱)</Label
                            ><Input
                                id="product-cost"
                                v-model="productForm.cost_php"
                                inputmode="decimal"
                                required
                            /><InputError
                                :message="productForm.errors.cost_php"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="product-price">Selling price (₱)</Label
                            ><Input
                                id="product-price"
                                v-model="productForm.price_php"
                                inputmode="decimal"
                                required
                            /><InputError
                                :message="productForm.errors.price_php"
                            />
                        </div>
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="product-threshold"
                                >Low-stock alert at</Label
                            ><Input
                                id="product-threshold"
                                v-model.number="productForm.low_stock_threshold"
                                type="number"
                                min="0"
                                max="1000000"
                                required
                            /><InputError
                                :message="
                                    productForm.errors.low_stock_threshold
                                "
                            />
                        </div>
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="product-status">Status</Label>
                            <NativeSelect
                                id="product-status"
                                v-model="productForm.status"
                                required
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="out_of_stock">
                                    Out of stock
                                </option>
                            </NativeSelect>
                            <InputError :message="productForm.errors.status" />
                        </div>
                        <template>
                            <div class="space-y-2">
                                <Label for="product-initial-stock"
                                    >Initial stock</Label
                                >
                                <Input
                                    id="product-initial-stock"
                                    v-model.number="productForm.initial_stock"
                                    type="number"
                                    min="0"
                                    max="1000000"
                                    required
                                />
                                <InputError
                                    :message="productForm.errors.initial_stock"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="product-initial-branch"
                                    >Stock branch</Label
                                >
                                <NativeSelect
                                    id="product-initial-branch"
                                    v-model.number="
                                        productForm.initial_branch_id
                                    "
                                    required
                                >
                                    <option
                                        v-for="branch in branches"
                                        :key="branch.id"
                                        :value="branch.id"
                                    >
                                        {{ branch.name }}
                                    </option>
                                </NativeSelect>
                                <InputError
                                    :message="
                                        productForm.errors.initial_branch_id
                                    "
                                />
                                <p class="text-xs text-muted-foreground">
                                    Opening stock is added to this branch.
                                </p>
                            </div>
                        </template>
                    </div>
                    <DialogFooter
                        ><Button
                            type="button"
                            variant="outline"
                            @click="productDialog = false"
                            >Cancel</Button
                        ><Button
                            type="submit"
                            :disabled="productForm.processing"
                            >Add product</Button
                        ></DialogFooter
                    >
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="stockDialog">
            <DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-lg">
                <DialogHeader
                    ><DialogTitle>{{
                        stockForm.type === 'received'
                            ? 'Receive stock'
                            : 'Remove stock'
                    }}</DialogTitle
                    ><DialogDescription
                        >{{ selectedProduct?.name }} · Current count is tracked
                        per branch.</DialogDescription
                    ></DialogHeader
                >
                <form class="space-y-4" @submit.prevent="saveStock">
                    <div class="space-y-2">
                        <Label for="stock-branch">Branch</Label
                        ><NativeSelect
                            id="stock-branch"
                            v-model.number="stockForm.branch_id"
                            required
                            ><option
                                v-for="branch in branches"
                                :key="branch.id"
                                :value="branch.id"
                            >
                                {{ branch.name }}
                            </option></NativeSelect
                        ><InputError :message="stockForm.errors.branch_id" />
                    </div>
                    <div class="space-y-2">
                        <Label for="stock-type">Stock change</Label
                        ><NativeSelect id="stock-type" v-model="stockForm.type"
                            ><option value="received">Receive stock</option>
                            <option value="removed">
                                Remove stock
                            </option></NativeSelect
                        >
                    </div>
                    <div class="space-y-2">
                        <Label for="stock-quantity">Quantity</Label
                        ><Input
                            id="stock-quantity"
                            v-model.number="stockForm.quantity"
                            type="number"
                            min="1"
                            max="1000000"
                            required
                        /><InputError :message="stockForm.errors.quantity" />
                    </div>
                    <div class="space-y-2">
                        <Label for="stock-reason">Reason (optional)</Label
                        ><Textarea
                            id="stock-reason"
                            v-model="stockForm.reason"
                            maxlength="500"
                            placeholder="For example, supplier delivery or damaged items"
                        /><InputError :message="stockForm.errors.reason" />
                    </div>
                    <DialogFooter
                        ><Button
                            type="button"
                            variant="outline"
                            @click="stockDialog = false"
                            >Cancel</Button
                        ><Button
                            type="submit"
                            :disabled="stockForm.processing || !branches.length"
                            >Save stock change</Button
                        ></DialogFooter
                    >
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
