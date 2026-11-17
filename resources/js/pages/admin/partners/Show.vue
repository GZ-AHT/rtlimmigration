<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import AppIcon from '@/components/panel/AppIcon.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import PanelTabs from '@/components/panel/PanelTabs.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatCard from '@/components/panel/StatCard.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDate, relativeTime } from '@/lib/format';
import { index, status, update } from '@/routes/admin/partners';
import {
    store as storeCommission,
    update as updateCommission,
} from '@/routes/admin/partners/commissions';
import {
    destroy as destroyDocument,
    store as storeDocument,
} from '@/routes/admin/partners/documents';
import { store as storePayment } from '@/routes/admin/partners/payments';
import type { ActivityEntry, Option, Tone } from '@/types';

type Partner = {
    id: number;
    partner_number: string;
    organisation: string;
    contact_name: string;
    email: string;
    phone: string;
    country_id: string;
    country: string | null;
    city: string;
    website: string;
    type: string;
    commission_rate: string;
    status: string;
    verified_at: string | null;
    notes: string;
    created_at: string | null;
};
type Doc = {
    id: number;
    title: string;
    name: string;
    size: number;
    url: string;
    created_at: string | null;
};
type Commission = {
    id: number;
    description: string;
    student: string | null;
    amount: string;
    currency: string;
    status: string;
    status_label: string;
    status_tone: Tone;
    created_at: string | null;
};
type Payment = {
    id: number;
    amount: string;
    currency: string;
    method: string;
    reference: string | null;
    paid_on: string;
    note: string | null;
    recorder: string | null;
};

const props = defineProps<{
    partner: Partner;
    documents: Doc[];
    commissions: Commission[];
    payments: Payment[];
    totals: { owed: string; earned: string; paid: string };
    activity: ActivityEntry[];
    options: {
        countries: Option[];
        types: Option[];
        commissionStatuses: Option[];
    };
    canManage: boolean;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Partners', href: index() }] },
});

const tab = ref('profile');
const p = props.partner;
const websitePlaceholder = 'https://…';

const profile = useForm({
    organisation: p.organisation,
    contact_name: p.contact_name,
    email: p.email,
    phone: p.phone,
    country_id: p.country_id,
    city: p.city,
    website: p.website,
    type: p.type,
    commission_rate: p.commission_rate,
    notes: p.notes,
});
const docForm = useForm({ title: '', file: null as File | null });
const commissionForm = useForm({
    description: '',
    amount: '',
    currency: 'BDT',
});
const paymentForm = useForm({
    amount: '',
    currency: 'BDT',
    method: 'Bank transfer',
    reference: '',
    paid_on: new Date().toISOString().slice(0, 10),
    note: '',
});

const onError = (e: Record<string, string>) =>
    toast.error(Object.values(e)[0] ?? 'Could not save.');
const setStatus = (data: Record<string, unknown>) =>
    router.patch(status(p.id).url, data as Record<string, never>, {
        preserveScroll: true,
        onError,
    });
</script>

<template>
    <Head :title="partner.organisation" />
    <PageHeader
        :eyebrow="partner.partner_number"
        :title="partner.organisation"
        :description="`Partner since ${formatDate(partner.created_at)}`"
    >
        <template #actions>
            <StatusBadge
                :label="partner.status === 'active' ? 'Active' : 'Suspended'"
                :tone="partner.status === 'active' ? 'success' : 'warning'"
            />
            <StatusBadge
                :label="partner.verified_at ? 'Verified' : 'Not verified'"
                :tone="partner.verified_at ? 'info' : 'neutral'"
            />
            <template v-if="canManage">
                <Button
                    variant="outline"
                    size="sm"
                    @click="setStatus({ verified: !partner.verified_at })"
                    >{{
                        partner.verified_at
                            ? 'Remove verification'
                            : 'Mark verified'
                    }}</Button
                >
                <Button
                    variant="outline"
                    size="sm"
                    @click="
                        setStatus({
                            status:
                                partner.status === 'active'
                                    ? 'suspended'
                                    : 'active',
                        })
                    "
                    >{{
                        partner.status === 'active' ? 'Suspend' : 'Activate'
                    }}</Button
                >
            </template>
        </template>
    </PageHeader>

    <div class="mb-4 grid gap-3 sm:grid-cols-3">
        <StatCard
            label="Commission earned"
            :value="totals.earned"
            icon="payments"
        />
        <StatCard
            label="Still owed"
            :value="totals.owed"
            icon="pending_actions"
        />
        <StatCard
            label="Paid to partner"
            :value="totals.paid"
            icon="account_balance"
        />
    </div>

    <PanelTabs
        v-model="tab"
        label="Partner sections"
        :tabs="[
            { key: 'profile', label: 'Profile' },
            { key: 'documents', label: 'Documents', count: documents.length },
            {
                key: 'commission',
                label: 'Commission',
                count: commissions.length,
            },
            { key: 'payments', label: 'Payments', count: payments.length },
            { key: 'activity', label: 'Activity' },
        ]"
    />

    <PanelCard v-if="tab === 'profile'">
        <form
            class="grid max-w-3xl gap-3.5 sm:grid-cols-2"
            :inert="!canManage || undefined"
            @submit.prevent="
                profile
                    .transform((d) => ({
                        ...d,
                        country_id: d.country_id || null,
                        commission_rate: d.commission_rate || null,
                        website: d.website || null,
                    }))
                    .put(update(p.id).url, { preserveScroll: true })
            "
        >
            <FormField
                v-slot="{ id, invalid }"
                label="Organisation"
                required
                :error="profile.errors.organisation"
                ><Input
                    :id="id"
                    v-model="profile.organisation"
                    :aria-invalid="invalid"
            /></FormField>
            <FormField
                v-slot="{ id, invalid }"
                label="Contact person"
                required
                :error="profile.errors.contact_name"
                ><Input
                    :id="id"
                    v-model="profile.contact_name"
                    :aria-invalid="invalid"
            /></FormField>
            <FormField
                v-slot="{ id, invalid }"
                label="Email"
                required
                :error="profile.errors.email"
                ><Input
                    :id="id"
                    v-model="profile.email"
                    type="email"
                    :aria-invalid="invalid"
            /></FormField>
            <FormField
                v-slot="{ id }"
                label="Phone"
                :error="profile.errors.phone"
                ><Input :id="id" v-model="profile.phone"
            /></FormField>
            <FormField
                v-slot="{ id }"
                label="Country"
                :error="profile.errors.country_id"
                ><SelectInput
                    :id="id"
                    v-model="profile.country_id"
                    :options="options.countries"
                    placeholder="Not set"
            /></FormField>
            <FormField v-slot="{ id }" label="City" :error="profile.errors.city"
                ><Input :id="id" v-model="profile.city"
            /></FormField>
            <FormField
                v-slot="{ id, invalid }"
                label="Website"
                :error="profile.errors.website"
                ><Input
                    :id="id"
                    v-model="profile.website"
                    type="url"
                    :placeholder="websitePlaceholder"
                    :aria-invalid="invalid"
            /></FormField>
            <FormField v-slot="{ id }" label="Type" :error="profile.errors.type"
                ><SelectInput
                    :id="id"
                    v-model="profile.type"
                    :options="options.types"
            /></FormField>
            <FormField
                v-slot="{ id, invalid }"
                label="Commission rate (%)"
                :error="profile.errors.commission_rate"
                ><Input
                    :id="id"
                    v-model="profile.commission_rate"
                    type="number"
                    min="0"
                    max="100"
                    step="0.01"
                    :aria-invalid="invalid"
            /></FormField>
            <div />
            <FormField
                v-slot="{ id }"
                label="Internal notes"
                :error="profile.errors.notes"
                class="sm:col-span-2"
                ><TextArea
                    :id="id"
                    v-model="profile.notes"
                    :rows="4"
                    :maxlength="5000"
            /></FormField>
            <div v-if="canManage">
                <Button type="submit" :disabled="profile.processing"
                    >Save profile</Button
                >
            </div>
        </form>
    </PanelCard>

    <PanelCard
        v-else-if="tab === 'documents'"
        title="Documents"
        description="Agreements, licences and ID. Stored privately."
    >
        <ul
            v-if="documents.length"
            class="mb-4 flex flex-col divide-y divide-border"
        >
            <li
                v-for="d in documents"
                :key="d.id"
                class="flex items-center justify-between gap-2 py-2 text-[13px]"
            >
                <span
                    ><a
                        :href="d.url"
                        class="font-semibold text-primary hover:underline"
                        >{{ d.title }}</a
                    >
                    <span class="text-muted-foreground"
                        >{{ d.name }} · {{ relativeTime(d.created_at) }}</span
                    ></span
                >
                <button
                    v-if="canManage"
                    type="button"
                    class="tiny-action"
                    @click="
                        router.delete(destroyDocument(d.id).url, {
                            preserveScroll: true,
                        })
                    "
                >
                    Delete
                </button>
            </li>
        </ul>
        <p v-else class="mb-4 text-[13px] text-muted-foreground">
            No documents yet.
        </p>
        <form
            v-if="canManage"
            class="flex flex-wrap items-end gap-2"
            @submit.prevent="
                docForm.post(storeDocument(p.id).url, {
                    forceFormData: true,
                    preserveScroll: true,
                    onSuccess: () => docForm.reset(),
                })
            "
        >
            <FormField
                v-slot="{ id }"
                label="Title"
                :error="docForm.errors.title"
                ><Input
                    :id="id"
                    v-model="docForm.title"
                    placeholder="e.g. Agency agreement"
            /></FormField>
            <FormField v-slot="{ id }" label="File" :error="docForm.errors.file"
                ><input
                    :id="id"
                    type="file"
                    class="text-[13px]"
                    accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"
                    @change="
                        (e) =>
                            (docForm.file =
                                (e.target as HTMLInputElement).files?.[0] ??
                                null)
                    "
            /></FormField>
            <Button
                type="submit"
                :disabled="
                    docForm.processing || !docForm.file || !docForm.title
                "
                ><AppIcon name="upload" :size="16" /> Upload</Button
            >
        </form>
    </PanelCard>

    <PanelCard v-else-if="tab === 'commission'" title="Commission">
        <ul
            v-if="commissions.length"
            class="mb-4 flex flex-col divide-y divide-border"
        >
            <li
                v-for="c in commissions"
                :key="c.id"
                class="flex flex-wrap items-center gap-3 py-2 text-[13px]"
            >
                <span class="min-w-0 flex-1"
                    ><b>{{ c.description }}</b
                    ><span v-if="c.student" class="text-muted-foreground">
                        · {{ c.student }}</span
                    ><span class="block text-[11px] text-muted-foreground">{{
                        formatDate(c.created_at)
                    }}</span></span
                >
                <span class="font-semibold"
                    >{{ c.currency }}
                    {{ Number(c.amount).toLocaleString() }}</span
                >
                <SelectInput
                    v-if="canManage"
                    :model-value="c.status"
                    :options="options.commissionStatuses"
                    compact
                    :label="`Status of ${c.description}`"
                    @update:model-value="
                        (v) =>
                            router.patch(
                                updateCommission(c.id).url,
                                { status: v },
                                { preserveScroll: true, onError },
                            )
                    "
                />
                <StatusBadge
                    v-else
                    :label="c.status_label"
                    :tone="c.status_tone"
                />
            </li>
        </ul>
        <p v-else class="mb-4 text-[13px] text-muted-foreground">
            No commission recorded yet.
        </p>
        <form
            v-if="canManage"
            class="flex flex-wrap items-end gap-2"
            @submit.prevent="
                commissionForm.post(storeCommission(p.id).url, {
                    preserveScroll: true,
                    onSuccess: () => commissionForm.reset(),
                })
            "
        >
            <FormField
                v-slot="{ id, invalid }"
                label="For"
                :error="commissionForm.errors.description"
                ><Input
                    :id="id"
                    v-model="commissionForm.description"
                    placeholder="e.g. Referral: Rafi Ahmed, Fall intake"
                    class="min-w-64"
                    :aria-invalid="invalid"
            /></FormField>
            <FormField
                v-slot="{ id, invalid }"
                label="Amount"
                :error="commissionForm.errors.amount"
                ><Input
                    :id="id"
                    v-model="commissionForm.amount"
                    type="number"
                    min="0"
                    step="0.01"
                    class="w-32"
                    :aria-invalid="invalid"
            /></FormField>
            <FormField
                v-slot="{ id }"
                label="Currency"
                :error="commissionForm.errors.currency"
                ><Input
                    :id="id"
                    v-model="commissionForm.currency"
                    maxlength="3"
                    class="w-20 uppercase"
            /></FormField>
            <Button type="submit" :disabled="commissionForm.processing"
                >Add commission</Button
            >
        </form>
    </PanelCard>

    <PanelCard v-else-if="tab === 'payments'" title="Payment history">
        <ul
            v-if="payments.length"
            class="mb-4 flex flex-col divide-y divide-border"
        >
            <li
                v-for="pay in payments"
                :key="pay.id"
                class="flex flex-wrap items-center gap-3 py-2 text-[13px]"
            >
                <span class="min-w-0 flex-1"
                    ><b
                        >{{ pay.currency }}
                        {{ Number(pay.amount).toLocaleString() }}</b
                    >
                    · {{ pay.method
                    }}<template v-if="pay.reference">
                        · {{ pay.reference }}</template
                    >
                    <span class="block text-[11px] text-muted-foreground"
                        >{{ formatDate(pay.paid_on)
                        }}<template v-if="pay.recorder">
                            · recorded by {{ pay.recorder }}</template
                        ><template v-if="pay.note">
                            · {{ pay.note }}</template
                        ></span
                    >
                </span>
            </li>
        </ul>
        <p v-else class="mb-4 text-[13px] text-muted-foreground">
            No payments recorded yet.
        </p>
        <form
            v-if="canManage"
            class="grid max-w-3xl gap-2 sm:grid-cols-3"
            @submit.prevent="
                paymentForm.post(storePayment(p.id).url, {
                    preserveScroll: true,
                    onSuccess: () =>
                        paymentForm.reset('amount', 'reference', 'note'),
                })
            "
        >
            <FormField
                v-slot="{ id, invalid }"
                label="Amount"
                :error="paymentForm.errors.amount"
                ><Input
                    :id="id"
                    v-model="paymentForm.amount"
                    type="number"
                    min="0"
                    step="0.01"
                    :aria-invalid="invalid"
            /></FormField>
            <FormField
                v-slot="{ id }"
                label="Currency"
                :error="paymentForm.errors.currency"
                ><Input
                    :id="id"
                    v-model="paymentForm.currency"
                    maxlength="3"
                    class="uppercase"
            /></FormField>
            <FormField
                v-slot="{ id, invalid }"
                label="Paid on"
                :error="paymentForm.errors.paid_on"
                ><Input
                    :id="id"
                    v-model="paymentForm.paid_on"
                    type="date"
                    :aria-invalid="invalid"
            /></FormField>
            <FormField
                v-slot="{ id }"
                label="Method"
                :error="paymentForm.errors.method"
                ><Input :id="id" v-model="paymentForm.method" maxlength="40"
            /></FormField>
            <FormField
                v-slot="{ id }"
                label="Reference"
                :error="paymentForm.errors.reference"
                ><Input
                    :id="id"
                    v-model="paymentForm.reference"
                    maxlength="120"
            /></FormField>
            <FormField
                v-slot="{ id }"
                label="Note"
                :error="paymentForm.errors.note"
                ><Input :id="id" v-model="paymentForm.note" maxlength="1000"
            /></FormField>
            <div>
                <Button type="submit" :disabled="paymentForm.processing"
                    >Record payment</Button
                >
            </div>
        </form>
    </PanelCard>

    <PanelCard v-else title="Activity log">
        <p
            v-if="activity.length === 0"
            class="text-[13px] text-muted-foreground"
        >
            Nothing yet.
        </p>
        <ul v-else class="flex flex-col gap-2 text-[13px]">
            <li v-for="e in activity" :key="e.id">
                <b>{{ e.summary }}</b>
                <span class="text-muted-foreground"
                    >· {{ e.causer ?? 'System' }} ·
                    {{ relativeTime(e.created_at) }}</span
                >
            </li>
        </ul>
        <Link
            :href="index()"
            class="mt-4 inline-block text-[12px] font-semibold text-muted-foreground hover:underline"
            >← All partners</Link
        >
    </PanelCard>
</template>
