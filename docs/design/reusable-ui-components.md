# Reusable UI components

Use these components before creating a page-specific table, control, or history layout. They keep desktop, mobile, dark mode, keyboard access, and spacing consistent.

## Record lists

Use [`RecordList.vue`](../../resources/js/components/RecordList.vue) for record tables. It automatically switches to stacked mobile cards and renders optional creation/update metadata.

```vue
<RecordList
    :rows="records.data"
    :columns="[
        { key: 'name', label: 'Name' },
        { key: 'status', label: 'Status' },
        { key: 'actions', label: 'Actions' },
    ]"
    caption="Members"
    row-key="id"
    empty="No records found."
>
    <template #cell-name="{ row }">{{ row.name }}</template>
</RecordList>
```

Use `wide` when the desktop table should wait for a wider container. Keep the first column as the record identity and put row actions in the `actions` column.

## Pagination

Paginate on the server. Pass the paginator's `data`, `from`, `to`, `total`, `prev_page_url`, and `next_page_url` props to the page. Use Inertia `Link` for navigation and preserve scroll where appropriate.

```vue
<p v-if="records.total" class="text-sm text-muted-foreground">
    Showing {{ records.from }}–{{ records.to }} of {{ records.total }} records
</p>
<nav
    v-if="records.prev_page_url || records.next_page_url"
    class="flex justify-between"
>
    <Button v-if="records.prev_page_url" variant="outline" as-child>
        <Link :href="records.prev_page_url" preserve-scroll>Previous</Link>
    </Button>
    <span v-else />
    <Button v-if="records.next_page_url" variant="outline" as-child>
        <Link :href="records.next_page_url" preserve-scroll>Next</Link>
    </Button>
</nav>
```

Do not load an entire record collection just to paginate it in Vue. Keep filters in the URL with `withQueryString()` so pagination retains the current search and filter state.

## Change logs

Use [`ChangeLog.vue`](../../resources/js/components/ChangeLog.vue) for an entity-specific audit trail. It provides the History icon, accessible show/hide button, chevron state, actor, timestamp, changed fields, empty state, and pagination.

```vue
<ChangeLog
    :history="history"
    title="Change log"
    :subtitle="`${product.name} · Manila time · Latest first`"
    content-id="product-change-log-content"
    class="lg:col-start-2 lg:row-span-2"
/>
```

The log starts open. The toggle has `aria-expanded`, `aria-controls`, a visible up/down chevron, and a text label for screen readers. Keep the `content-id` unique when a page renders more than one log.

The backend should query only the subject's audit records, eager-load `user:id,name`, paginate newest first, and expose readable `action`, `at`, `by`, and `details` values. Write changes with [`Audit::log()`](../../app/Support/Audit.php); never place passwords, tokens, or other secrets in `changes`.

Current examples:

- Product inventory: product create, update, activation, and deactivation history.
- Plans & Pricing: offering create, update, activation, and archive history.

## Forms and controls

- [`Button.vue`](../../resources/js/components/ui/button/Button.vue): primary, outline, ghost, destructive, and icon actions. Use 44px touch targets and an `aria-label` for icon-only buttons.
- [`Input.vue`](../../resources/js/components/ui/input/Input.vue) and [`NativeSelect.vue`](../../resources/js/components/NativeSelect.vue): standard text and native select controls.
- [`InputError.vue`](../../resources/js/components/InputError.vue): field and form-level validation messages.
- [`Badge.vue`](../../resources/js/components/ui/badge/Badge.vue): status labels using existing theme tokens.
- Dialog and dropdown components under [`components/ui`](../../resources/js/components/ui): use for short forms and row actions; keep destructive actions behind confirmation.

## Page layout

Use `p-4 sm:p-6`, `space-y-5`, `rounded-xl border bg-card`, and the existing theme tokens. For a detail page with a form and change log, use a responsive two-column grid on desktop and stack the cards on mobile. Keep page-specific content in slots or props instead of duplicating shared table, pagination, and history markup.
