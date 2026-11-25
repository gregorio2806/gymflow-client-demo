<script setup lang="ts">
import RecordList from '@/components/RecordList.vue';
import { formatDisplayDate } from '@/lib/date-format';
import { Form, Link } from '@inertiajs/vue3';
import { Pause, Play } from '@lucide/vue';
import { ref } from 'vue';
import MemberSubscriptionController from '@/actions/App/Http/Controllers/Dashboard/MemberSubscriptionController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { memberTabUrl } from '@/lib/member-tab-url';
import { index as purchases } from '@/routes/purchases';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
defineProps<{
    member: {
        id: number;
        name: string;
        is_active: boolean;
        mobile: string;
        email: string | null;
        branchName: string | null;
        hasConsent: boolean;
        membershipStatus: string;
    };
    canManage: boolean;
    today: string;
    plans: {
        id: number;
        name: string;
        price_centavos: number;
        duration_amount: number;
        duration_unit: string;
    }[];
    subscriptions: {
        data: {
            id: number;
            createdAt: string;
            createdBy: string;
            plan_name: string;
            price_centavos: number;
            discountPercent: number;
            discountType: string;
            discountMode: string;
            discountCentavos: number;
            originalPriceCentavos: number | null;
            status: string;
            startsOn: string;
            validThrough: string;
            canSuspend: boolean;
            canResume: boolean;
            canCancel: boolean;
        }[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    membershipPlans: {
        data: {
            id: number;
            planName: string;
            priceCentavos: number;
            startsOn: string;
            validThrough: string | null;
            status: string;
        }[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();
const currency = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
});
const statusDialog = ref<number | null>(null);
</script>
<template>
    <div class="space-y-5">
        <p v-if="!member.is_active" class="text-sm text-muted-foreground">
            This member profile is inactive.
        </p>
        <p v-else-if="!canManage" class="text-sm text-muted-foreground">
            This branch is read-only.
        </p>
        <section
            v-if="canManage && member.is_active"
            class="flex flex-wrap items-center gap-3"
        >
            <Button as-child
                ><Link :href="purchases(member.id)"
                    >Assign or renew</Link
                ></Button
            >
            <p class="text-sm text-muted-foreground">
                Choose subscriptions, benefits or training packages and review
                the price before saving.
            </p>
        </section>
        <section>
            <h3 class="mb-4 text-base font-semibold">
                Gym access plan history ({{ subscriptions.total }})
            </h3>
            <p
                v-if="!subscriptions.data.length"
                class="border-y py-8 text-sm text-muted-foreground"
            >
                No memberships yet.
            </p>
            <RecordList
                :rows="subscriptions.data"
                :columns="[
                    { key: 'plan_name', label: 'Membership', width: '24%' },
                    {
                        key: 'price_centavos',
                        label: 'Agreed price',
                        width: '12%',
                    },
                    { key: 'startsOn', label: 'Starts on', width: '14%' },
                    {
                        key: 'validThrough',
                        label: 'Valid through',
                        width: '14%',
                    },
                    { key: 'status', label: 'Status', width: '12%' },
                    { key: 'actions', label: 'Actions', width: '24%' },
                ]"
                caption="Gym access plan history"
                v-else
                row-key="id"
                wide
                ><template #cell-plan_name="{ row: subscription }"
                    ><p class="font-medium">
                        {{ subscription.plan_name }}
                    </p></template
                ><template #cell-price_centavos="{ row: subscription }"
                    >{{ currency.format(subscription.price_centavos / 100) }}
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ subscription.discountType
                        }}<template
                            v-if="
                                subscription.discountPercent > 0 ||
                                subscription.discountCentavos > 0
                            "
                        >
                            ·
                            <template
                                v-if="subscription.discountMode === 'peso'"
                                >{{
                                    currency.format(
                                        subscription.discountCentavos / 100,
                                    )
                                }}</template
                            ><template v-else
                                >{{ subscription.discountPercent }}%</template
                            >
                            off
                            {{
                                currency.format(
                                    (subscription.originalPriceCentavos ??
                                        subscription.price_centavos) / 100,
                                )
                            }}</template
                        >
                    </p> </template
                ><template #cell-startsOn="{ row: subscription }">{{
                    formatDisplayDate(subscription.startsOn)
                }}</template
                ><template #cell-validThrough="{ row: subscription }">{{
                    formatDisplayDate(subscription.validThrough)
                }}</template
                ><template #cell-status="{ row: subscription }"
                    ><Badge variant="outline">{{
                        subscription.status
                    }}</Badge></template
                ><template #cell-actions="{ row: subscription }"
                    ><div class="flex flex-wrap items-start justify-end gap-2">
                        <Dialog
                            v-if="
                                subscription.canSuspend ||
                                subscription.canResume
                            "
                            :open="statusDialog === subscription.id"
                            @update:open="
                                statusDialog = $event ? subscription.id : null
                            "
                        >
                            <DialogTrigger as-child
                                ><Button variant="outline"
                                    ><Play
                                        v-if="subscription.canResume"
                                        class="size-4"
                                    /><Pause v-else class="size-4" />{{
                                        subscription.canResume
                                            ? 'Resume'
                                            : 'Freeze'
                                    }}</Button
                                ></DialogTrigger
                            >
                            <DialogContent
                                ><DialogHeader
                                    ><DialogTitle
                                        >{{
                                            subscription.canResume
                                                ? 'Resume'
                                                : 'Freeze'
                                        }}
                                        this subscription?</DialogTitle
                                    ><DialogDescription
                                        >Freezing pauses gym access. The expiry
                                        date stays unchanged; resuming restores
                                        access for any remaining valid
                                        days.</DialogDescription
                                    ></DialogHeader
                                >
                                <Form
                                    v-bind="
                                        MemberSubscriptionController.update.form(
                                            {
                                                member: member.id,
                                                subscription: subscription.id,
                                            },
                                        )
                                    "
                                    v-slot="{ errors, processing }"
                                    class="space-y-4"
                                    @success="statusDialog = null"
                                >
                                    <input
                                        type="hidden"
                                        name="action"
                                        :value="
                                            subscription.canResume
                                                ? 'resume'
                                                : 'suspend'
                                        "
                                    />
                                    <Label
                                        :for="
                                            'freeze-reason-' + subscription.id
                                        "
                                        >Reason (optional)</Label
                                    ><Input
                                        :id="'freeze-reason-' + subscription.id"
                                        name="reason"
                                        maxlength="500"
                                    />
                                    <InputError
                                        :message="
                                            errors.action ||
                                            errors.reason ||
                                            errors.membership_plan_id
                                        "
                                    />
                                    <DialogFooter
                                        ><DialogClose as-child
                                            ><Button
                                                type="button"
                                                variant="outline"
                                                >Keep current status</Button
                                            ></DialogClose
                                        ><Button
                                            type="submit"
                                            :disabled="processing"
                                            >Confirm
                                            {{
                                                subscription.canResume
                                                    ? 'resume'
                                                    : 'freeze'
                                            }}</Button
                                        ></DialogFooter
                                    >
                                </Form>
                            </DialogContent>
                        </Dialog>
                        <Dialog v-if="subscription.canCancel"
                            ><DialogTrigger as-child
                                ><Button variant="outline"
                                    >Cancel</Button
                                ></DialogTrigger
                            ><DialogContent
                                ><DialogHeader
                                    ><DialogTitle
                                        >Cancel this membership?</DialogTitle
                                    ><DialogDescription
                                        >The period stays in history, but no
                                        longer grants access. Cancellation
                                        cannot be undone.</DialogDescription
                                    ></DialogHeader
                                ><Form
                                    v-bind="
                                        MemberSubscriptionController.update.form(
                                            {
                                                member: member.id,
                                                subscription: subscription.id,
                                            },
                                        )
                                    "
                                    v-slot="{ errors, processing }"
                                    ><input
                                        type="hidden"
                                        name="action"
                                        value="cancel"
                                    /><InputError
                                        :message="
                                            errors.action ||
                                            errors.membership_plan_id
                                        "
                                    /><DialogFooter
                                        ><DialogClose as-child
                                            ><Button variant="outline"
                                                >Keep membership</Button
                                            ></DialogClose
                                        ><Button
                                            type="submit"
                                            variant="destructive"
                                            :disabled="processing"
                                            >Cancel membership</Button
                                        ></DialogFooter
                                    ></Form
                                ></DialogContent
                            ></Dialog
                        >
                    </div></template
                ></RecordList
            >
            <nav
                v-if="
                    subscriptions.prev_page_url || subscriptions.next_page_url
                "
                class="mt-4 flex justify-between"
                aria-label="Membership history pagination"
            >
                <Button
                    v-if="subscriptions.prev_page_url"
                    variant="outline"
                    as-child
                    ><Link
                        :href="
                            memberTabUrl(
                                subscriptions.prev_page_url,
                                'membership',
                            )
                        "
                        preserve-scroll
                        >Previous</Link
                    ></Button
                ><span v-else /><Button
                    v-if="subscriptions.next_page_url"
                    variant="outline"
                    as-child
                    ><Link
                        :href="
                            memberTabUrl(
                                subscriptions.next_page_url,
                                'membership',
                            )
                        "
                        preserve-scroll
                        >Next</Link
                    ></Button
                >
            </nav>
        </section>
        <section>
            <h3 class="mb-4 text-base font-semibold">
                Membership plans purchased ({{ membershipPlans.total }})
            </h3>
            <p
                v-if="!membershipPlans.data.length"
                class="border-y py-8 text-sm text-muted-foreground"
            >
                No membership plans purchased.
            </p>
            <RecordList
                v-else
                :rows="membershipPlans.data"
                :columns="[
                    { key: 'planName', label: 'Plan' },
                    { key: 'priceCentavos', label: 'Agreed price' },
                    { key: 'startsOn', label: 'Starts on' },
                    { key: 'validThrough', label: 'Valid through' },
                    { key: 'status', label: 'Status' },
                ]"
                caption="Purchased membership plans"
                row-key="id"
                ><template #cell-priceCentavos="{ row }">{{
                    currency.format(row.priceCentavos / 100)
                }}</template
                ><template #cell-startsOn="{ row }">{{
                    formatDisplayDate(row.startsOn)
                }}</template
                ><template #cell-validThrough="{ row }">{{
                    row.validThrough
                        ? formatDisplayDate(row.validThrough)
                        : 'No expiry'
                }}</template
                ><template #cell-status="{ row }"
                    ><Badge variant="outline">{{ row.status }}</Badge>
                </template></RecordList
            >
            <nav
                v-if="
                    membershipPlans.prev_page_url ||
                    membershipPlans.next_page_url
                "
                class="mt-4 flex justify-between"
                aria-label="Membership plan history pagination"
            >
                <Button
                    v-if="membershipPlans.prev_page_url"
                    variant="outline"
                    as-child
                    ><Link
                        :href="
                            memberTabUrl(
                                membershipPlans.prev_page_url,
                                'membership',
                            )
                        "
                        preserve-scroll
                        >Previous</Link
                    ></Button
                ><span v-else /><Button
                    v-if="membershipPlans.next_page_url"
                    variant="outline"
                    as-child
                    ><Link
                        :href="
                            memberTabUrl(
                                membershipPlans.next_page_url,
                                'membership',
                            )
                        "
                        preserve-scroll
                        >Next</Link
                    ></Button
                >
            </nav>
        </section>
    </div>
</template>
