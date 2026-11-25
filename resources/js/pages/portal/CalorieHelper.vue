<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { jsonRequest } from '@/lib/json-request';
import { nextTick, reactive, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { portalUrl } from '@/lib/portal-route';
import { calorieSave, calorieDelete, home, logout } from '@/routes/portal';
type Profile = {
    age: number | string;
    height_cm: number | string;
    weight_kg: number | string;
    equation: string;
    activity: string;
    goal: string;
};
const props = defineProps<{
    gymName: string;
    gymSlug: string;
    memberName: string;
    enabled: boolean;
    hasProfile: boolean;
    profile: Profile | null;
    estimate: { resting: number; maintenance: number } | null;
    consentVersion: string;
}>();
const blank = (): Profile => ({
    age: '',
    height_cm: '',
    weight_kg: '',
    equation: '',
    activity: '',
    goal: '',
});
const form = reactive({
    ...(props.profile ?? blank()),
});
const estimate = ref(props.estimate);
const saved = ref(props.hasProfile);
const savedGoal = ref(props.profile?.goal ?? 'maintain');
const resultPanel = ref<HTMLElement | null>(null);
const statusPanel = ref<HTMLElement | null>(null);
const errors = ref<Record<string, string>>({});
const busy = ref(false);
const confirmDelete = ref(false);
const message = ref('');
async function save() {
    busy.value = true;
    errors.value = {};
    message.value = '';
    try {
        const result = await jsonRequest<{
            estimate: { resting: number; maintenance: number };
        }>(portalUrl(calorieSave(props.gymSlug)), {
            ...form,
            consent_version: props.consentVersion,
        });
        estimate.value = result.estimate;
        savedGoal.value = form.goal;
        saved.value = true;
        message.value = 'Saved. Your estimate is shown below.';
        await nextTick();
        resultPanel.value?.scrollIntoView({ block: 'center' });
        busy.value = false;
    } catch (error) {
        message.value =
            error instanceof Error
                ? error.message
                : 'Could not save. Refresh and try again.';
        busy.value = false;
        await nextTick();
        statusPanel.value?.scrollIntoView({ block: 'center' });
    }
}
async function remove() {
    busy.value = true;
    try {
        await jsonRequest(
            portalUrl(calorieDelete(props.gymSlug)),
            {},
            undefined,
            'DELETE',
        );
        Object.assign(form, blank());
        errors.value = {};
        confirmDelete.value = false;
        router.clearHistory();
        router.visit(portalUrl(home(props.gymSlug)));
    } catch {
        message.value = 'Could not delete. Refresh and try again.';
        busy.value = false;
    }
}
function signOut() {
    Object.assign(form, blank());
    router.post(portalUrl(logout(props.gymSlug)));
}
</script>
<template>
    <Head title="Calorie & goal helper"
        ><meta name="robots" content="noindex, nofollow"
    /></Head>
    <div class="space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-xl font-semibold">Calorie &amp; goal helper</h1>
            <Button variant="outline" :disabled="busy" @click="signOut"
                >Sign out</Button
            >
        </header>
        <p class="text-sm leading-6">
            Optional general information for adults aged 18 or older who are not
            pregnant or breastfeeding. Estimates can differ from your actual
            needs. This is not medical advice or a prescribed diet. Ask a
            registered dietitian or healthcare professional for a personal plan,
            especially for weight changes or a medical condition.
        </p>
        <p v-if="!enabled" role="status" class="rounded-lg border p-4">
            Your gym has disabled this helper. You can still delete your saved
            information and withdraw consent below.
        </p>
        <p ref="statusPanel" role="status" class="text-sm">{{ message }}</p>
        <section
            ref="resultPanel"
            aria-live="polite"
            v-if="enabled && estimate"
            class="space-y-3 rounded-xl border p-6"
            aria-label="Calorie estimate"
        >
            <h2 class="text-lg font-semibold">Your rough energy estimate</h2>
            <p>
                Maintenance: about
                <strong
                    >{{
                        estimate.maintenance.toLocaleString()
                    }}
                    kcal/day</strong
                >
            </p>
            <p class="text-sm text-muted-foreground">
                Estimated resting energy:
                {{ estimate.resting.toLocaleString() }} kcal/day. Resting energy
                is not a food intake target.
            </p>
            <p class="text-sm">
                Your goal:
                {{
                    savedGoal === 'lose'
                        ? 'Lose weight'
                        : savedGoal === 'gain'
                          ? 'Gain weight'
                          : 'Maintain weight'
                }}. The estimate is for maintenance; it does not prescribe a
                calorie deficit or surplus.
            </p>
            <p class="text-sm text-muted-foreground">
                Uses the
                <a
                    href="https://pubmed.ncbi.nlm.nih.gov/2305711/"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="underline"
                    >Mifflin–St Jeor resting-energy equation</a
                >
                and approximate activity multipliers. It cannot account for
                every individual's metabolism.
            </p>
        </section>
        <form
            v-if="enabled"
            class="space-y-5"
            autocomplete="off"
            @submit.prevent="save"
        >
            <div class="grid gap-4 sm:grid-cols-3">
                <div
                    v-for="field in [
                        {
                            key: 'age',
                            label: 'Age (years)',
                            min: 18,
                            max: 100,
                            step: 1,
                        },
                        {
                            key: 'height_cm',
                            label: 'Height (cm)',
                            min: 120,
                            max: 230,
                            step: 0.1,
                        },
                        {
                            key: 'weight_kg',
                            label: 'Weight (kg)',
                            min: 35,
                            max: 250,
                            step: 0.1,
                        },
                    ]"
                    :key="field.key"
                    class="space-y-2"
                >
                    <Label :for="field.key">{{ field.label }}</Label
                    ><Input
                        :id="field.key"
                        v-model="
                            form[field.key as 'age' | 'height_cm' | 'weight_kg']
                        "
                        type="number"
                        :min="field.min"
                        :max="field.max"
                        :step="field.step"
                        required
                    /><InputError :message="errors[field.key]" />
                </div>
            </div>
            <div class="space-y-2">
                <Label for="equation">Sex</Label
                ><NativeSelect
                    id="equation"
                    :value="form.equation"
                    required
                    @change="
                        form.equation = (
                            $event.target as HTMLSelectElement
                        ).value
                    "
                    ><option value="" disabled>Choose sex</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option></NativeSelect
                ><InputError :message="errors.equation" />
            </div>
            <div class="space-y-2">
                <Label for="activity">Activity level</Label
                ><NativeSelect
                    id="activity"
                    :value="form.activity"
                    required
                    @change="
                        form.activity = (
                            $event.target as HTMLSelectElement
                        ).value
                    "
                    ><option value="" disabled>Choose activity level</option>
                    <option value="sedentary">
                        Sedentary: little or no exercise
                    </option>
                    <option value="light">Light: exercise 1–3 days/week</option>
                    <option value="moderate">
                        Moderate: exercise 4–5 days/week
                    </option>
                    <option value="high">
                        Active: daily exercise or intense workouts 3–4 days/week
                    </option>
                    <option value="very_active">
                        Very active: intense workouts 6–7 days/week
                    </option></NativeSelect
                ><InputError :message="errors.activity" />
            </div>
            <div class="space-y-2">
                <Label for="goal">Goal</Label
                ><NativeSelect
                    id="goal"
                    :value="form.goal"
                    required
                    @change="
                        form.goal = ($event.target as HTMLSelectElement).value
                    "
                    ><option value="" disabled>Choose your goal</option>
                    <option value="maintain">Maintain weight</option>
                    <option value="lose">Lose weight</option>
                    <option value="gain">Gain weight</option></NativeSelect
                ><InputError :message="errors.goal" />
            </div>
            <p class="text-sm text-muted-foreground">
                By clicking Save and estimate, I agree to {{ gymName }} storing
                these inputs for this estimate. I can delete them anytime.
            </p>
            <InputError :message="errors.consent_version" />
            <Button type="submit" :disabled="busy"
                ><Spinner v-if="busy" />Save and estimate</Button
            >
        </form>
        <section v-if="saved" class="space-y-3 border-t pt-5">
            <h2 class="font-semibold">Delete my information</h2>
            <p class="text-sm">
                Remove saved inputs and withdraw consent. You can opt in again
                later if the helper is enabled.
            </p>
            <Button
                v-if="!confirmDelete"
                variant="outline"
                :disabled="busy"
                @click="confirmDelete = true"
                >Delete saved inputs / withdraw consent</Button
            >
            <div v-else class="space-y-3" role="alert">
                <p>Delete your saved health inputs and consent now?</p>
                <div class="flex flex-wrap gap-3">
                    <Button
                        variant="destructive"
                        :disabled="busy"
                        @click="remove"
                        ><Spinner v-if="busy" />Confirm delete</Button
                    ><Button
                        variant="outline"
                        :disabled="busy"
                        @click="confirmDelete = false"
                        >Keep my information</Button
                    >
                </div>
            </div>
        </section>
    </div>
</template>
