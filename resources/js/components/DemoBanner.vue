<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { FlaskConical } from '@lucide/vue';
import DemoController from '@/actions/App/Http/Controllers/DemoController';
import { Button } from '@/components/ui/button';

const page = usePage();
</script>

<template>
    <div
        v-if="page.props.demo"
        role="status"
        class="flex flex-wrap items-center justify-between gap-3 border-b border-primary/30 bg-primary/10 px-4 py-3 text-sm"
    >
        <p class="flex items-center gap-2">
            <FlaskConical class="size-5 shrink-0 text-primary" />
            <span>
                <span class="font-semibold">You're exploring a demo gym.</span>
                Nothing here is real, and it's deleted within 24 hours.
            </span>
        </p>
        <Form v-bind="DemoController.destroy.form()">
            <Button type="submit" size="sm">Get your own gym</Button>
        </Form>
    </div>
</template>
