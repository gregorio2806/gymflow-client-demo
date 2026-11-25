<script setup lang="ts">
import { formatDisplayDate } from '@/lib/date-format';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Monitor,
    Tablet,
    Smartphone,
    ExternalLink,
    Globe,
    House,
    FileText,
    Building2,
    Megaphone,
    CreditCard,
    Phone,
} from '@lucide/vue';
import { useElementSize } from '@vueuse/core';
import WebsiteInquiryController from '@/actions/App/Http/Controllers/Dashboard/WebsiteInquiryController';
import { computed, nextTick, ref, watch } from 'vue';
import WebsiteController from '@/actions/App/Http/Controllers/Dashboard/WebsiteController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Spinner } from '@/components/ui/spinner';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';

type Plan = {
    name: string;
    price_php: string;
    duration: string;
    description: string;
};
type Facility = { name: string; description: string };
type Announcement = { title: string; body: string; date: string };
type Draft = {
    plans_mode: 'cards' | 'image';
    plans_alt: string;
    gym_name: string;
    description: string;
    about: string;
    contact: { phone: string; email: string; address: string; hours: string };
    plans: Plan[];
    facilities: Facility[];
    announcements: Announcement[];
    image_alt: string;
    privacy: {
        contact_name: string;
        contact_email: string;
        details: string;
        reviewed: boolean;
        contact_form_enabled: boolean;
    };
};
const props = defineProps<{
    draft: Draft;
    revision: number;
    hasDraft: boolean;
    imageUrl: string | null;
    logoUrl: string | null;
    plansImageUrl: string | null;
    publishedAt: string | null;
    publicUrl: string;
}>();
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Gym website', href: WebsiteController.edit() }],
    },
});
const form = useForm({
    ...(JSON.parse(JSON.stringify(props.draft)) as Draft),
    revision: props.revision,
    image: null as File | null,
    logo: null as File | null,
    plans_image: null as File | null,
    remove_plans_image: false,
    remove_image: false,
    remove_logo: false,
});
const publishing = useForm({ revision: props.revision });
const previewDevice = ref<'desktop' | 'tablet' | 'mobile'>('desktop');
const previewContainer = ref<HTMLElement | null>(null);
const { width: previewWidth } = useElementSize(previewContainer);
const canvasWidth = computed(
    () => ({ desktop: 1280, tablet: 768, mobile: 390 })[previewDevice.value],
);
const previewScale = computed(() =>
    Math.min(1, (previewWidth.value || 500) / canvasWidth.value),
);
const previewUrl = computed(
    () =>
        WebsiteController.preview({
            query: { embedded: 1, revision: props.revision },
        }).url,
);
const confirmation = ref(false);
const listSections = ['plans', 'facilities', 'announcements'] as const;
type ListSection = (typeof listSections)[number];
const contactLabels = {
    phone: 'Contact number',
    email: 'Contact email',
    address: 'Address',
    hours: 'Opening hours',
};
watch(
    () => props.revision,
    () => {
        form.revision = props.revision;
        publishing.revision = props.revision;
    },
);
function save() {
    form.post(WebsiteController.update().url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.image = null;
            form.logo = null;
            form.plans_image = null;
            form.remove_plans_image = false;
            form.remove_image = false;
            form.remove_logo = false;
            Object.assign(
                form,
                JSON.parse(JSON.stringify(props.draft)) as Draft,
                {
                    revision: props.revision,
                },
            );
            form.defaults();
        },
        onError: async () => {
            await nextTick();
            document.getElementById('website-errors')?.focus();
        },
    });
}
function upload(event: Event) {
    form.image = (event.target as HTMLInputElement).files?.[0] ?? null;
}
function uploadLogo(event: Event) {
    form.logo = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (form.logo) form.remove_logo = false;
}
function uploadPlans(event: Event) {
    form.plans_image = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (form.plans_image) form.remove_plans_image = false;
}
function add(section: ListSection) {
    if (form[section].length >= 12) return;
    if (section === 'plans')
        form.plans.push({
            name: '',
            price_php: '0.00',
            duration: '',
            description: '',
        });
    else if (section === 'facilities')
        form.facilities.push({ name: '', description: '' });
    else form.announcements.push({ title: '', body: '', date: '' });
}
function remove(section: ListSection, index: number) {
    form[section].splice(index, 1);
}
function format(tag: string) {
    const field = document.getElementById('about') as HTMLTextAreaElement;
    const selected =
        form.about.slice(field.selectionStart, field.selectionEnd) ||
        'Your text';
    const html =
        tag === 'ul'
            ? `<ul><li>${selected}</li></ul>`
            : `<${tag}>${selected}</${tag}>`;
    form.about =
        form.about.slice(0, field.selectionStart) +
        html +
        form.about.slice(field.selectionEnd);
    field.focus();
}
function publish() {
    publishing.post(WebsiteController.publish().url, {
        preserveScroll: true,
        onSuccess: () => {
            confirmation.value = false;
        },
        onError: async () => {
            await nextTick();
            document.getElementById('publish-error')?.focus();
        },
    });
}
const error = (key: string) => (form.errors as Record<string, string>)[key];
</script>

<template>
    <Head title="Gym website" />
    <div class="@container space-y-5 p-4 sm:p-6">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-2">
                <h1 class="text-2xl font-semibold">Gym website</h1>
                <p class="text-sm text-muted-foreground">
                    These sections appear together on one scrolling page. Save a
                    draft, then publish when you're ready.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button as-child variant="outline"
                    ><a
                        :href="publicUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        ><ExternalLink class="size-4" aria-hidden="true" />View
                        public site</a
                    ></Button
                >
                <Button
                    form="website-editor"
                    :disabled="form.processing || publishing.processing"
                    ><Spinner v-if="form.processing" />Save draft</Button
                >
            </div>
        </header>
        <div
            class="grid items-start gap-4 @min-[64rem]:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]"
        >
            <form
                id="website-editor"
                class="min-w-0 space-y-4"
                @submit.prevent="save"
            >
                <section class="space-y-4 rounded-xl border bg-card p-4">
                    <header
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <h2
                            class="flex items-center gap-2 text-base font-semibold"
                        >
                            <Globe
                                class="size-5 text-primary"
                                aria-hidden="true"
                            />Basic information
                        </h2>
                        <span
                            class="rounded-full px-2 py-1 text-xs font-medium"
                            :class="
                                publishedAt
                                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                    : 'bg-muted text-muted-foreground'
                            "
                            >{{ publishedAt ? 'Published' : 'Draft' }}</span
                        >
                    </header>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="website-gym-name">Gym name</Label
                            ><Input
                                id="website-gym-name"
                                v-model="form.gym_name"
                                required
                                maxlength="255"
                                :aria-invalid="!!form.errors.gym_name"
                                aria-describedby="website-gym-name-help website-gym-name-error"
                            />
                            <p
                                id="website-gym-name-help"
                                class="text-xs text-muted-foreground"
                            >
                                Shown on your public website.
                            </p>
                            <InputError
                                id="website-gym-name-error"
                                :message="form.errors.gym_name"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="website-address"
                                >Public website address</Label
                            ><Input
                                id="website-address"
                                :model-value="publicUrl"
                                readonly
                            />
                        </div>
                    </div>
                    <div class="flex flex-wrap items-start gap-4 border-t pt-4">
                        <div
                            class="flex size-20 shrink-0 items-center justify-center rounded-xl border bg-muted/30"
                        >
                            <img
                                v-if="logoUrl && !form.remove_logo"
                                :src="logoUrl"
                                alt="Saved website logo"
                                class="size-16 object-contain"
                            />
                            <Globe
                                v-else
                                class="size-8 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </div>
                        <div class="min-w-0 flex-1 space-y-2">
                            <Label for="website-logo"
                                >Logo / icon (optional)</Label
                            >
                            <Input
                                id="website-logo"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                :aria-invalid="!!error('logo')"
                                aria-describedby="website-logo-help"
                                @change="uploadLogo"
                            />
                            <p
                                id="website-logo-help"
                                class="text-xs text-muted-foreground"
                            >
                                JPEG, PNG or WebP, up to 4 MB. A square
                                transparent PNG or WebP works best. Used in the
                                public header and browser icon after publishing.
                            </p>
                            <InputError :message="error('logo')" />
                            <label
                                v-if="logoUrl"
                                class="flex min-h-11 items-center gap-2 text-sm"
                                ><input
                                    v-model="form.remove_logo"
                                    type="checkbox"
                                    class="size-4 accent-primary"
                                    @change="form.logo = null"
                                />Remove logo from draft</label
                            >
                        </div>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{
                            publishedAt
                                ? `Last published: ${formatDisplayDate(publishedAt)} (Manila time)`
                                : 'Your website is not published yet.'
                        }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Button as-child variant="outline"
                            ><Link :href="WebsiteInquiryController.index()"
                                >View inquiries</Link
                            ></Button
                        >
                    </div>
                </section>
                <InputError
                    :message="error('revision') || publishing.errors.revision"
                />
                <div
                    v-if="form.hasErrors"
                    id="website-errors"
                    tabindex="-1"
                    role="alert"
                    class="rounded-lg border p-4 text-sm text-destructive"
                >
                    Check the highlighted fields below.
                    <ul class="mt-2 list-inside list-disc">
                        <li v-for="(message, key) in form.errors" :key="key">
                            {{ message }}
                        </li>
                    </ul>
                </div>
                <section class="space-y-5 rounded-xl border bg-card p-4">
                    <h2 class="flex items-center gap-2 text-base font-semibold">
                        <House
                            class="size-5 text-primary"
                            aria-hidden="true"
                        />Home section
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        Set the description and hero image visitors see first.
                    </p>
                    <div class="grid gap-2">
                        <Label for="description"
                            >Website description (optional)</Label
                        ><Textarea
                            id="description"
                            v-model="form.description"
                            maxlength="300"
                            :aria-invalid="!!error('description')"
                            :aria-describedby="
                                error('description')
                                    ? 'description-error'
                                    : undefined
                            "
                        /><InputError
                            id="description-error"
                            :message="error('description')"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="image">Home image (optional)</Label
                        ><Input
                            id="image"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            @change="upload"
                            :aria-invalid="!!error('image')"
                            :aria-describedby="
                                error('image') ? 'image-error' : undefined
                            "
                        />
                        <p class="text-sm text-muted-foreground">
                            JPEG, PNG, or WebP, up to 4 MB and 5000 pixels per
                            side. Upload images you own or have permission to
                            use.
                        </p>
                        <InputError
                            id="image-error"
                            :message="error('image')"
                        />
                    </div>
                    <img
                        v-if="imageUrl && !form.remove_image"
                        :src="imageUrl"
                        :alt="form.image_alt"
                        class="max-h-48 w-full rounded-lg object-cover"
                    />
                    <div class="grid gap-2">
                        <Label for="image_alt"
                            >Image description (required with an image)</Label
                        ><Input
                            id="image_alt"
                            v-model="form.image_alt"
                            maxlength="200"
                            :aria-invalid="!!error('image_alt')"
                            :aria-describedby="
                                error('image_alt')
                                    ? 'image-alt-error'
                                    : undefined
                            "
                        /><InputError
                            id="image-alt-error"
                            :message="error('image_alt')"
                        />
                    </div>
                    <label
                        v-if="imageUrl"
                        class="flex min-h-11 items-center gap-3"
                        ><input
                            v-model="form.remove_image"
                            type="checkbox"
                            class="size-5 accent-primary"
                        />Remove the home image from this draft</label
                    >
                </section>
                <section class="space-y-3 rounded-xl border bg-card p-4">
                    <h2 class="flex items-center gap-2 text-base font-semibold">
                        <FileText
                            class="size-5 text-primary"
                            aria-hidden="true"
                        />About section
                    </h2>
                    <Label for="about">About the gym (optional)</Label>
                    <div
                        class="flex flex-wrap gap-2"
                        aria-label="Text formatting"
                    >
                        <Button
                            v-for="[tag, label] in [
                                ['strong', 'Bold'],
                                ['em', 'Italic'],
                                ['p', 'Paragraph'],
                                ['h2', 'Heading'],
                                ['ul', 'List'],
                            ]"
                            :key="tag"
                            type="button"
                            variant="outline"
                            @click="format(tag)"
                            >{{ label }}</Button
                        >
                    </div>
                    <Textarea
                        id="about"
                        v-model="form.about"
                        class="min-h-60"
                        maxlength="12000"
                    />
                    <p class="text-sm text-muted-foreground">
                        Select text and choose a format. Formatting tags appear
                        here. Links, scripts, and embedded content are removed.
                    </p>
                    <InputError :message="error('about')" />
                </section>
                <section
                    v-for="section in listSections"
                    :key="section"
                    class="space-y-5 rounded-xl border bg-card p-4"
                >
                    <h2
                        class="flex items-center gap-2 text-base font-semibold capitalize"
                    >
                        <component
                            :is="
                                section === 'plans'
                                    ? CreditCard
                                    : section === 'facilities'
                                      ? Building2
                                      : Megaphone
                            "
                            class="size-5 text-primary"
                            aria-hidden="true"
                        />{{ section }}
                    </h2>
                    <div v-if="section === 'plans'" class="space-y-4">
                        <fieldset class="flex flex-wrap gap-4">
                            <legend class="mb-2 text-sm font-medium">
                                Display plans as
                            </legend>
                            <label
                                class="flex cursor-pointer items-center gap-2 text-sm"
                            >
                                <input
                                    v-model="form.plans_mode"
                                    type="radio"
                                    name="plans-display"
                                    value="cards"
                                    class="size-4 accent-primary"
                                />Plan cards
                            </label>
                            <label
                                class="flex cursor-pointer items-center gap-2 text-sm"
                            >
                                <input
                                    v-model="form.plans_mode"
                                    type="radio"
                                    name="plans-display"
                                    value="image"
                                    class="size-4 accent-primary"
                                />Uploaded image
                            </label>
                        </fieldset>
                        <InputError :message="form.errors.plans_mode" />
                        <div
                            v-if="form.plans_mode === 'image'"
                            class="space-y-4"
                        >
                            <p class="text-sm text-muted-foreground">
                                Upload your designed price list instead of
                                entering each plan.
                            </p>
                            <img
                                v-if="plansImageUrl && !form.remove_plans_image"
                                :src="plansImageUrl"
                                :alt="form.plans_alt"
                                class="max-h-80 w-full rounded-lg border object-contain"
                            />
                            <div class="space-y-2">
                                <Label for="plans-image">Plans image</Label>
                                <Input
                                    id="plans-image"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    :aria-invalid="!!form.errors.plans_image"
                                    aria-describedby="plans-image-help"
                                    @change="uploadPlans"
                                />
                                <p
                                    id="plans-image-help"
                                    class="text-xs text-muted-foreground"
                                >
                                    JPEG, PNG or WebP, up to 4 MB. Use a clear
                                    image with readable text.
                                </p>
                                <InputError
                                    :message="form.errors.plans_image"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="plans-alt">Image description</Label>
                                <Textarea
                                    id="plans-alt"
                                    v-model="form.plans_alt"
                                    required
                                    maxlength="2000"
                                    :aria-invalid="!!form.errors.plans_alt"
                                    aria-describedby="plans-alt-help"
                                />
                                <p
                                    id="plans-alt-help"
                                    class="text-xs text-muted-foreground"
                                >
                                    Include plan names, prices and duration for
                                    visitors who cannot read the image.
                                </p>
                                <InputError :message="form.errors.plans_alt" />
                            </div>
                            <label
                                v-if="plansImageUrl"
                                class="flex items-center gap-2 text-sm"
                            >
                                <input
                                    v-model="form.remove_plans_image"
                                    type="checkbox"
                                    class="size-4 accent-primary"
                                />Remove saved image from draft
                            </label>
                            <p
                                v-if="form.remove_plans_image"
                                class="text-xs text-muted-foreground"
                            >
                                Upload a replacement or switch to Plan cards
                                before saving.
                            </p>
                        </div>
                    </div>
                    <template
                        v-if="
                            section !== 'plans' || form.plans_mode === 'cards'
                        "
                    >
                        <p class="text-sm text-muted-foreground">
                            Up to 12 {{ section }}. These website entries are
                            separate from your membership catalog.
                        </p>
                        <p v-if="!form[section].length" class="text-sm">
                            No {{ section }} added yet.
                        </p>
                        <div
                            v-for="(_, index) in form[section]"
                            :key="`${section}-${index}`"
                            class="space-y-4 rounded-xl border p-4"
                        >
                            <h2 class="font-medium">
                                {{
                                    section === 'plans'
                                        ? 'Plan'
                                        : section === 'facilities'
                                          ? 'Facility'
                                          : 'Announcement'
                                }}
                                {{ index + 1 }}
                            </h2>
                            <template v-if="section === 'plans'">
                                <div
                                    v-for="[key, label] in [
                                        ['name', 'Name'],
                                        ['price_php', 'Price (PHP pesos)'],
                                        ['duration', 'Duration'],
                                        [
                                            'description',
                                            'Description (optional)',
                                        ],
                                    ]"
                                    :key="key"
                                    class="grid gap-2"
                                >
                                    <Label :for="`plan-${index}-${key}`">{{
                                        label
                                    }}</Label
                                    ><Input
                                        :id="`plan-${index}-${key}`"
                                        v-model="
                                            form.plans[index][key as keyof Plan]
                                        "
                                        :inputmode="
                                            key === 'price_php'
                                                ? 'decimal'
                                                : 'text'
                                        "
                                    /><InputError
                                        :message="
                                            error(`plans.${index}.${key}`)
                                        "
                                    />
                                </div>
                            </template>
                            <template v-else-if="section === 'facilities'">
                                <div class="grid gap-2">
                                    <Label :for="`facility-${index}-name`"
                                        >Name</Label
                                    ><Input
                                        :id="`facility-${index}-name`"
                                        v-model="form.facilities[index].name"
                                    /><InputError
                                        :message="
                                            error(`facilities.${index}.name`)
                                        "
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label
                                        :for="`facility-${index}-description`"
                                        >Description (optional)</Label
                                    ><Textarea
                                        :id="`facility-${index}-description`"
                                        v-model="
                                            form.facilities[index].description
                                        "
                                    /><InputError
                                        :message="
                                            error(
                                                `facilities.${index}.description`,
                                            )
                                        "
                                    />
                                </div>
                            </template>
                            <template v-else>
                                <div class="grid gap-2">
                                    <Label :for="`announcement-${index}-title`"
                                        >Title</Label
                                    ><Input
                                        :id="`announcement-${index}-title`"
                                        v-model="
                                            form.announcements[index].title
                                        "
                                    /><InputError
                                        :message="
                                            error(
                                                `announcements.${index}.title`,
                                            )
                                        "
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label :for="`announcement-${index}-body`"
                                        >Announcement</Label
                                    ><Textarea
                                        :id="`announcement-${index}-body`"
                                        v-model="form.announcements[index].body"
                                    /><InputError
                                        :message="
                                            error(`announcements.${index}.body`)
                                        "
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label :for="`announcement-${index}-date`"
                                        >Date</Label
                                    ><Input
                                        :id="`announcement-${index}-date`"
                                        v-model="form.announcements[index].date"
                                        type="date"
                                    /><InputError
                                        :message="
                                            error(`announcements.${index}.date`)
                                        "
                                    />
                                </div>
                            </template>
                            <Button
                                type="button"
                                variant="outline"
                                :aria-label="`Remove from draft: ${section} entry ${index + 1}`"
                                @click="remove(section, index)"
                                >Remove from draft</Button
                            >
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="form[section].length >= 12"
                            @click="add(section)"
                            >Add
                            {{
                                section === 'plans'
                                    ? 'plan'
                                    : section === 'facilities'
                                      ? 'facility'
                                      : 'announcement'
                            }}</Button
                        >
                    </template>
                </section>
                <section class="space-y-5 rounded-xl border bg-card p-4">
                    <h2 class="flex items-center gap-2 text-base font-semibold">
                        <Phone
                            class="size-5 text-primary"
                            aria-hidden="true"
                        />Contact section
                    </h2>
                    <div
                        v-for="(label, key) in contactLabels"
                        :key="key"
                        class="grid gap-2"
                    >
                        <Label :for="`contact-${key}`"
                            >{{ label }} (optional)</Label
                        ><Input
                            :id="`contact-${key}`"
                            v-model="form.contact[key]"
                            :type="
                                key === 'email'
                                    ? 'email'
                                    : key === 'phone'
                                      ? 'tel'
                                      : 'text'
                            "
                            :maxlength="
                                key === 'phone'
                                    ? 30
                                    : key === 'email'
                                      ? 255
                                      : 500
                            "
                        /><InputError :message="error(`contact.${key}`)" />
                    </div>
                    <p class="text-sm text-muted-foreground">
                        These details will be public when published. Use your
                        gym's business contact details.
                    </p>
                </section>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <Button :disabled="form.processing || publishing.processing"
                        ><Spinner v-if="form.processing" />Save draft</Button
                    >
                    <Button
                        v-if="hasDraft"
                        type="button"
                        variant="outline"
                        :disabled="
                            form.isDirty ||
                            form.processing ||
                            publishing.processing
                        "
                        @click="confirmation = true"
                        >Publish website</Button
                    >
                </div>
                <p v-if="form.isDirty" class="text-sm text-muted-foreground">
                    You have unsaved changes. Save before publishing.
                </p>
            </form>
            <aside
                class="min-w-0 overflow-hidden rounded-xl border bg-card @min-[64rem]:sticky @min-[64rem]:top-20"
                aria-label="Saved website preview"
            >
                <div
                    class="flex flex-wrap justify-center gap-2 border-b bg-muted/30 p-3"
                >
                    <Button
                        v-for="device in [
                            'desktop',
                            'tablet',
                            'mobile',
                        ] as const"
                        :key="device"
                        type="button"
                        :variant="
                            previewDevice === device ? 'secondary' : 'ghost'
                        "
                        :aria-pressed="previewDevice === device"
                        @click="previewDevice = device"
                        class="capitalize"
                        ><component
                            :is="
                                device === 'desktop'
                                    ? Monitor
                                    : device === 'tablet'
                                      ? Tablet
                                      : Smartphone
                            "
                            class="size-4"
                            aria-hidden="true"
                        />{{ device }}</Button
                    >
                </div>
                <p class="px-4 py-3 text-xs text-muted-foreground">
                    Saved draft preview. Save draft to refresh your changes.
                </p>
                <div
                    ref="previewContainer"
                    class="relative mx-3 mb-3 overflow-hidden rounded-lg border bg-white"
                    :style="{ height: hasDraft ? '36rem' : '12rem' }"
                >
                    <iframe
                        v-if="hasDraft"
                        :key="revision"
                        :src="previewUrl"
                        title="Saved gym website draft preview"
                        class="absolute top-0 left-1/2 border-0"
                        :style="{
                            width: canvasWidth + 'px',
                            height: 576 / previewScale + 'px',
                            transform: `translateX(-50%) scale(${previewScale})`,
                            transformOrigin: 'top center',
                        }"
                    />
                    <p
                        v-else
                        class="flex h-full items-center justify-center p-6 text-center text-sm text-slate-500"
                    >
                        Save your first draft to preview your website.
                    </p>
                </div>
            </aside>
        </div>
        <Dialog v-model:open="confirmation"
            ><DialogContent
                ><DialogHeader
                    ><DialogTitle>Publish your gym website?</DialogTitle
                    ><DialogDescription
                        >The saved draft will replace the public
                        website.</DialogDescription
                    ></DialogHeader
                ><InputError
                    id="publish-error"
                    tabindex="-1"
                    :message="publishing.errors.revision"
                /><DialogFooter
                    ><Button
                        variant="outline"
                        :disabled="publishing.processing"
                        @click="confirmation = false"
                        >Keep draft</Button
                    ><Button :disabled="publishing.processing" @click="publish"
                        ><Spinner v-if="publishing.processing" />Confirm
                        publish</Button
                    ></DialogFooter
                ></DialogContent
            ></Dialog
        >
    </div>
</template>
