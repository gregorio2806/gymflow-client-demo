<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { index as staffIndex } from '@/routes/staff';
import { index as branchesIndex } from '@/routes/branches';
const page = usePage();
</script>

<template>
    <div
        v-if="page.props.planReview"
        class="space-y-2 border-b border-border bg-muted px-4 py-3 text-sm sm:px-6"
        role="status"
    >
        <p v-if="page.props.planReview.staff">
            Your plan includes {{ page.props.planReview.staffLimit }} staff{{
                page.props.planReview.perBranch ? ' per branch' : ''
            }}.
            <Link
                :href="staffIndex()"
                class="font-medium text-primary underline underline-offset-4"
                >Choose who stays active</Link
            >.
        </p>
        <p v-if="page.props.planReview.branches">
            Your plan includes {{ page.props.planReview.branchLimit }} active
            {{
                page.props.planReview.branchLimit === 1 ? 'branch' : 'branches'
            }}.
            <Link
                :href="branchesIndex()"
                class="font-medium text-primary underline underline-offset-4"
                >Choose active branches</Link
            >.
        </p>
    </div>
</template>
