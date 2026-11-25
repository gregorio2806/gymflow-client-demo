<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { MailPlus } from '@lucide/vue';
import OwnerInvitationController from '@/actions/App/Http/Controllers/Platform/OwnerInvitationController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/platform/owner-invitations';

defineProps<{
    invitations: {
        data: {
            id: number;
            email: string;
            plan: string;
            gymName: string | null;
            status: 'pending' | 'expired' | 'account created' | 'gym created';
            sentAt: string;
        }[];
        total: number;
    };
    accessRequests: {
        id: number;
        name: string;
        email: string;
        phone: string | null;
        gymName: string;
        location: string | null;
        plan: string;
        planLabel: string;
        message: string | null;
        sentAt: string;
    }[];
    plans: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Platform: invite owners', href: index() }],
    },
});

const sentDate = (iso: string) =>
    new Date(iso).toLocaleDateString('en-PH', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
</script>

<template>
    <Head title="Invite gym owners" />

    <div class="space-y-10 px-4 py-6 sm:px-6">
        <h1 class="sr-only">Invite gym owners</h1>

        <!-- New access requests from the website form -->
        <section v-if="accessRequests.length" class="max-w-3xl">
            <Heading
                title="New access requests"
                description="Sent from the Request access form. Send access emails them a link on the plan they asked for."
            />
            <ul class="mt-4 divide-y divide-border rounded-xl border">
                <li
                    v-for="request in accessRequests"
                    :key="request.id"
                    class="grid gap-3 p-4 sm:grid-cols-[1fr_auto] sm:items-center"
                >
                    <div class="min-w-0 text-sm">
                        <p class="text-base font-medium">
                            {{ request.gymName }}
                            <span class="font-normal text-muted-foreground">
                                · {{ request.planLabel }}</span
                            >
                        </p>
                        <p class="text-muted-foreground">
                            {{ request.name }} · {{ request.email }}
                            <template v-if="request.phone">
                                · {{ request.phone }}</template
                            >
                            <template v-if="request.location">
                                · {{ request.location }}</template
                            >
                        </p>
                        <p v-if="request.message" class="mt-1 italic">
                            “{{ request.message }}”
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ sentDate(request.sentAt) }}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <Form
                            v-bind="OwnerInvitationController.store.form()"
                            v-slot="{ processing }"
                        >
                            <input
                                type="hidden"
                                name="email"
                                :value="request.email"
                            />
                            <input
                                type="hidden"
                                name="plan"
                                :value="request.plan"
                            />
                            <input
                                type="hidden"
                                name="gym_name"
                                :value="request.gymName"
                            />
                            <input
                                type="hidden"
                                name="access_request_id"
                                :value="request.id"
                            />
                            <Button type="submit" :disabled="processing">
                                <Spinner v-if="processing" />
                                Send access
                            </Button>
                        </Form>
                        <Form
                            v-bind="
                                OwnerInvitationController.dismissRequest.form(
                                    request.id,
                                )
                            "
                            v-slot="{ processing }"
                        >
                            <Button
                                type="submit"
                                variant="outline"
                                :disabled="processing"
                            >
                                Dismiss
                            </Button>
                        </Form>
                    </div>
                </li>
            </ul>
        </section>

        <section class="max-w-xl">
            <Heading
                title="Give a gym owner access"
                description="Use the email they wrote from. They'll get a link to set their password and create their gym on this plan. Links expire in 7 days; sending again replaces the old link."
            />

            <Form
                v-bind="OwnerInvitationController.store.form()"
                :reset-on-success="['email', 'gym_name']"
                v-slot="{ errors, processing }"
                class="grid gap-4"
            >
                <div class="grid gap-2">
                    <Label for="email">Owner's email</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        maxlength="255"
                        autocomplete="off"
                        placeholder="owner@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="plan">Plan</Label>
                        <NativeSelect id="plan" name="plan" required>
                            <option
                                v-for="plan in plans"
                                :key="plan.value"
                                :value="plan.value"
                            >
                                {{ plan.label }}
                            </option>
                        </NativeSelect>
                        <InputError :message="errors.plan" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="gym_name">Gym name (optional)</Label>
                        <Input
                            id="gym_name"
                            name="gym_name"
                            maxlength="100"
                            placeholder="e.g. DM Fitness"
                        />
                        <InputError :message="errors.gym_name" />
                    </div>
                </div>
                <Button type="submit" :disabled="processing" class="sm:w-fit">
                    <Spinner v-if="processing" />
                    <MailPlus v-else class="size-4" />
                    {{ processing ? 'Sending…' : 'Send access link' }}
                </Button>
            </Form>
        </section>

        <section class="max-w-3xl">
            <Heading
                variant="small"
                title="Sent invitations"
                :description="`${invitations.total} total`"
            />
            <p
                v-if="invitations.data.length === 0"
                class="mt-4 rounded-xl border p-6 text-muted-foreground"
            >
                No invitations yet.
            </p>
            <ul v-else class="mt-4 divide-y divide-border rounded-xl border">
                <li
                    v-for="invitation in invitations.data"
                    :key="invitation.id"
                    class="flex flex-wrap items-center justify-between gap-3 p-4"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium">
                            {{ invitation.email }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ invitation.plan }}
                            <template v-if="invitation.gymName">
                                · {{ invitation.gymName }}</template
                            >
                            · sent {{ sentDate(invitation.sentAt) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge
                            :variant="
                                invitation.status === 'gym created'
                                    ? 'default'
                                    : 'secondary'
                            "
                        >
                            {{ invitation.status }}
                        </Badge>
                        <Form
                            v-if="
                                invitation.status === 'pending' ||
                                invitation.status === 'expired'
                            "
                            v-bind="
                                OwnerInvitationController.destroy.form(
                                    invitation.id,
                                )
                            "
                            v-slot="{ processing }"
                        >
                            <Button
                                type="submit"
                                variant="outline"
                                :disabled="processing"
                            >
                                Cancel
                            </Button>
                        </Form>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>
