<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { listBarangays, listMuncities, listProvinces } from '@jobuntux/psgc';
import { MapPin, Minus, Plus, X } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import syncEmployeeAboutMeAddresses from '@/actions/App/Http/Controllers/SyncEmployeeAboutMeAddressesController';
import {
    aboutMeClearFieldButtonClass,
    aboutMeDialogScrollAreaClass,
    aboutMeLabelRowClass,
    aboutMeMaxSmOneLineTruncateClass,
    aboutMePrimaryToggleRowClass,
    aboutMePrimaryToggleRuleClass,
} from '@/components/hris/aboutMeDialogUi';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { appToast } from '@/lib/app-toast-client';
import type {
    EmployeeProfileAddressBlock,
    EmployeeProfileDisplay,
} from '@/pages/Employees/employeeProfileDisplay';

type PageErrorsBag = Record<string, string>;

const props = defineProps<{
    profile: EmployeeProfileDisplay;
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

type AddressOption = {
    code: string;
    name: string;
};

type AddressForm = {
    addressLine1: string;
    addressLine2: string;
    country: string;
    province: string;
    provinceCode: string;
    city: string;
    cityCode: string;
    barangay: string;
    barangayCode: string;
    zipCode: string;
    isPrimary: boolean;
};

const countryOptions = ['Philippines'] as const;

function emptyAddress(): AddressForm {
    return {
        addressLine1: '',
        addressLine2: '',
        country: 'Philippines',
        province: '',
        provinceCode: '',
        city: '',
        cityCode: '',
        barangay: '',
        barangayCode: '',
        zipCode: '',
        isPrimary: false,
    };
}

const permanentAddress = reactive<AddressForm>(emptyAddress());
const currentAddress = reactive<AddressForm>(emptyAddress());

const showPermanentAddress = ref(false);
const showCurrentAddress = ref(false);

const attemptedSave = ref(false);
const formError = ref('');
const processing = ref(false);
const fieldErrors = ref<PageErrorsBag>({});

const provinceOptions = computed<AddressOption[]>(() =>
    listProvinces()
        .filter((item) => (item.provCode ?? '').trim() !== '')
        .map((item) => ({
            code: item.provCode ?? '',
            name: item.provName,
        })),
);

const cityOptions = computed<AddressOption[]>(() => {
    if (permanentAddress.provinceCode === '') {
        return [];
    }

    return listMuncities(permanentAddress.provinceCode).map((item) => ({
        code: item.munCityCode,
        name: item.munCityName,
    }));
});

const barangayOptions = computed<AddressOption[]>(() => {
    if (permanentAddress.cityCode === '') {
        return [];
    }

    return listBarangays(permanentAddress.cityCode).map((item) => ({
        code: item.brgyCode,
        name: item.brgyName,
    }));
});

const currentCityOptions = computed<AddressOption[]>(() => {
    if (currentAddress.provinceCode === '') {
        return [];
    }

    return listMuncities(currentAddress.provinceCode).map((item) => ({
        code: item.munCityCode,
        name: item.munCityName,
    }));
});

const currentBarangayOptions = computed<AddressOption[]>(() => {
    if (currentAddress.cityCode === '') {
        return [];
    }

    return listBarangays(currentAddress.cityCode).map((item) => ({
        code: item.brgyCode,
        name: item.brgyName,
    }));
});

const canUsePermanentAddress = computed<boolean>(() => {
    return (
        permanentAddress.addressLine1.trim() !== '' ||
        permanentAddress.addressLine2.trim() !== '' ||
        permanentAddress.provinceCode !== '' ||
        permanentAddress.cityCode !== '' ||
        permanentAddress.barangayCode !== '' ||
        permanentAddress.zipCode.trim() !== '' ||
        permanentAddress.country !== 'Philippines'
    );
});

function structuredSeed(
    block: EmployeeProfileAddressBlock | null | undefined,
): Partial<AddressForm> {
    if (
        block === undefined ||
        block === null ||
        typeof block.address_line_1 !== 'string' ||
        block.address_line_1.trim() === ''
    ) {
        return {};
    }

    return {
        addressLine1: block.address_line_1.trim(),
        addressLine2:
            typeof block.address_line_2 === 'string'
                ? block.address_line_2.trim()
                : '',
        country:
            typeof block.country === 'string' && block.country.trim() !== ''
                ? block.country.trim()
                : 'Philippines',
        province: typeof block.province === 'string' ? block.province : '',
        provinceCode:
            typeof block.province_code === 'string'
                ? block.province_code
                : '',
        city: typeof block.city === 'string' ? block.city : '',
        cityCode:
            typeof block.city_code === 'string' ? block.city_code : '',
        barangay:
            typeof block.barangay === 'string' ? block.barangay : '',
        barangayCode:
            typeof block.barangay_code === 'string'
                ? block.barangay_code
                : '',
        zipCode: typeof block.zip_code === 'string' ? block.zip_code : '',
        isPrimary: Boolean(block.is_primary),
    };
}

function linesToAddressSeed(lines: string[] | undefined): Partial<AddressForm> {
    const l = lines ?? [];
    if (l.length === 0) {
        return {};
    }

    return {
        addressLine1: l[0] ?? '',
        addressLine2:
            l.length > 1 ? l.slice(1).map((x) => x.trim()).join(', ') : '',
    };
}

function draftHasContent(addr: AddressForm): boolean {
    return (
        addr.addressLine1.trim() !== '' ||
        addr.addressLine2.trim() !== '' ||
        addr.provinceCode !== '' ||
        addr.cityCode !== '' ||
        addr.barangayCode !== '' ||
        addr.zipCode.trim() !== ''
    );
}

function assignAddress(
    target: AddressForm,
    seed: Partial<AddressForm>,
): void {
    Object.assign(target, emptyAddress(), seed);
}

function resetFromProfile(): void {
    attemptedSave.value = false;
    formError.value = '';
    processing.value = false;
    fieldErrors.value = {};

    const permBlock = props.profile.permanent_address;
    const curBlock = props.profile.current_address;

    const permStruct = structuredSeed(permBlock);
    assignAddress(
        permanentAddress,
        Object.keys(permStruct).length > 0
            ? permStruct
            : linesToAddressSeed(permBlock?.lines ?? []),
    );
    permanentAddress.isPrimary = permBlock?.is_primary ?? false;

    const curStruct = structuredSeed(curBlock);
    assignAddress(
        currentAddress,
        Object.keys(curStruct).length > 0
            ? curStruct
            : linesToAddressSeed(curBlock?.lines ?? []),
    );
    currentAddress.isPrimary = curBlock?.is_primary ?? true;

    const permLines = permBlock?.lines ?? [];
    const curLines = curBlock?.lines ?? [];
    showPermanentAddress.value =
        Object.keys(permStruct).length > 0 || permLines.length > 0;
    showCurrentAddress.value =
        Object.keys(curStruct).length > 0 || curLines.length > 0;
    if (!showPermanentAddress.value && !showCurrentAddress.value) {
        showPermanentAddress.value = true;
        showCurrentAddress.value = true;
    }
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            resetFromProfile();
        }
    },
);

function onProvinceChange(code: unknown): void {
    const normalizedCode = typeof code === 'string' ? code : '';
    permanentAddress.provinceCode = normalizedCode;
    permanentAddress.province =
        provinceOptions.value.find((item) => item.code === normalizedCode)
            ?.name ?? '';
    permanentAddress.cityCode = '';
    permanentAddress.city = '';
    permanentAddress.barangayCode = '';
    permanentAddress.barangay = '';
}

function onCityChange(code: unknown): void {
    const normalizedCode = typeof code === 'string' ? code : '';
    permanentAddress.cityCode = normalizedCode;
    permanentAddress.city =
        cityOptions.value.find((item) => item.code === normalizedCode)?.name ??
        '';
    permanentAddress.barangayCode = '';
    permanentAddress.barangay = '';
}

function onBarangayChange(code: unknown): void {
    const normalizedCode = typeof code === 'string' ? code : '';
    permanentAddress.barangayCode = normalizedCode;
    permanentAddress.barangay =
        barangayOptions.value.find((item) => item.code === normalizedCode)
            ?.name ?? '';
}

function onCurrentProvinceChange(code: unknown): void {
    const normalizedCode = typeof code === 'string' ? code : '';
    currentAddress.provinceCode = normalizedCode;
    currentAddress.province =
        provinceOptions.value.find((item) => item.code === normalizedCode)
            ?.name ?? '';
    currentAddress.cityCode = '';
    currentAddress.city = '';
    currentAddress.barangayCode = '';
    currentAddress.barangay = '';
}

function onCurrentCityChange(code: unknown): void {
    const normalizedCode = typeof code === 'string' ? code : '';
    currentAddress.cityCode = normalizedCode;
    currentAddress.city =
        currentCityOptions.value.find((item) => item.code === normalizedCode)
            ?.name ?? '';
    currentAddress.barangayCode = '';
    currentAddress.barangay = '';
}

function onCurrentBarangayChange(code: unknown): void {
    const normalizedCode = typeof code === 'string' ? code : '';
    currentAddress.barangayCode = normalizedCode;
    currentAddress.barangay =
        currentBarangayOptions.value.find(
            (item) => item.code === normalizedCode,
        )?.name ?? '';
}

function clearProvince(): void {
    onProvinceChange('');
}

function clearCity(): void {
    onCityChange('');
}

function clearBarangay(): void {
    onBarangayChange('');
}

function clearCurrentProvince(): void {
    onCurrentProvinceChange('');
}

function clearCurrentCity(): void {
    onCurrentCityChange('');
}

function clearCurrentBarangay(): void {
    onCurrentBarangayChange('');
}

function setPermanentAddressPrimary(checked: unknown): void {
    permanentAddress.isPrimary = Boolean(checked);
}

function setCurrentAddressPrimary(checked: unknown): void {
    currentAddress.isPrimary = Boolean(checked);
}

function usePermanentAddressForCurrent(): void {
    if (!canUsePermanentAddress.value) {
        return;
    }

    currentAddress.addressLine1 = permanentAddress.addressLine1;
    currentAddress.addressLine2 = permanentAddress.addressLine2;
    currentAddress.country = permanentAddress.country;
    currentAddress.provinceCode = permanentAddress.provinceCode;
    currentAddress.province = permanentAddress.province;
    currentAddress.cityCode = permanentAddress.cityCode;
    currentAddress.city = permanentAddress.city;
    currentAddress.barangayCode = permanentAddress.barangayCode;
    currentAddress.barangay = permanentAddress.barangay;
    currentAddress.zipCode = permanentAddress.zipCode;
}

const permInvalid = computed(() => {
    const a = attemptedSave.value;
    const req = a && showPermanentAddress.value;
    const has = draftHasContent(permanentAddress);

    if (!req || !has) {
        return {
            addressLine1: false,
            provinceCode: false,
            cityCode: false,
            barangayCode: false,
            zipCode: false,
        };
    }

    return {
        addressLine1:
            req && has && permanentAddress.addressLine1.trim() === '',
        provinceCode: req && has && permanentAddress.provinceCode === '',
        cityCode: req && has && permanentAddress.cityCode === '',
        barangayCode: req && has && permanentAddress.barangayCode === '',
        zipCode: req && has && permanentAddress.zipCode.trim() === '',
    };
});

const curInvalid = computed(() => {
    const a = attemptedSave.value;
    const req = a && showCurrentAddress.value;
    const has = draftHasContent(currentAddress);

    if (!req || !has) {
        return {
            addressLine1: false,
            provinceCode: false,
            cityCode: false,
            barangayCode: false,
            zipCode: false,
        };
    }

    return {
        addressLine1: req && has && currentAddress.addressLine1.trim() === '',
        provinceCode: req && has && currentAddress.provinceCode === '',
        cityCode: req && has && currentAddress.cityCode === '',
        barangayCode: req && has && currentAddress.barangayCode === '',
        zipCode: req && has && currentAddress.zipCode.trim() === '',
    };
});

function validate(): boolean {
    formError.value = '';

    const permEmpty =
        !showPermanentAddress.value || !draftHasContent(permanentAddress);
    const curEmpty =
        !showCurrentAddress.value || !draftHasContent(currentAddress);

    if (permEmpty && curEmpty) {
        formError.value =
            'Expand and complete at least one address (or remove all fields).';

        return false;
    }

    if (showPermanentAddress.value && draftHasContent(permanentAddress)) {
        const ok =
            permanentAddress.addressLine1.trim() !== '' &&
            permanentAddress.provinceCode !== '' &&
            permanentAddress.cityCode !== '' &&
            permanentAddress.barangayCode !== '' &&
            permanentAddress.zipCode.trim() !== '';
        if (!ok) {
            formError.value =
                'Complete all required permanent address fields (line 1, province, city, barangay, ZIP).';

            return false;
        }
    }

    if (showCurrentAddress.value && draftHasContent(currentAddress)) {
        const ok =
            currentAddress.addressLine1.trim() !== '' &&
            currentAddress.provinceCode !== '' &&
            currentAddress.cityCode !== '' &&
            currentAddress.barangayCode !== '' &&
            currentAddress.zipCode.trim() !== '';
        if (!ok) {
            formError.value =
                'Complete all required current address fields (line 1, province, city, barangay, ZIP).';

            return false;
        }
    }

    return true;
}

function cloneAddressForm(addr: AddressForm): AddressForm {
    return { ...addr };
}

function addressApiPayload(addr: AddressForm): {
    address_line_1: string;
    address_line_2: string | null;
    barangay: string;
    barangay_code: string | null;
    city: string;
    city_code: string | null;
    province: string;
    province_code: string | null;
    zip_code: string;
    country: string;
    is_primary: boolean;
} {
    return {
        address_line_1: addr.addressLine1.trim(),
        address_line_2:
            addr.addressLine2.trim() === '' ? null : addr.addressLine2.trim(),
        barangay: addr.barangay.trim(),
        barangay_code:
            addr.barangayCode.trim() === '' ? null : addr.barangayCode.trim(),
        city: addr.city.trim(),
        city_code:
            addr.cityCode.trim() === '' ? null : addr.cityCode.trim(),
        province: addr.province.trim(),
        province_code:
            addr.provinceCode.trim() === '' ? null : addr.provinceCode.trim(),
        zip_code: addr.zipCode.trim(),
        country:
            addr.country.trim() !== '' ? addr.country.trim() : 'Philippines',
        is_primary: addr.isPrimary,
    };
}

function onSave(): void {
    attemptedSave.value = true;
    if (!validate()) {
        return;
    }

    let curPrimary = currentAddress.isPrimary;
    let permPrimary = permanentAddress.isPrimary;

    const sendingPerm =
        showPermanentAddress.value &&
        draftHasContent(permanentAddress) &&
        permanentAddress.addressLine1.trim() !== ''
            ? cloneAddressForm(permanentAddress)
            : null;
    const sendingCur =
        showCurrentAddress.value &&
        draftHasContent(currentAddress) &&
        currentAddress.addressLine1.trim() !== ''
            ? cloneAddressForm(currentAddress)
            : null;

    if (sendingPerm && !sendingCur) {
        permPrimary = true;
        curPrimary = false;
    } else if (sendingCur && !sendingPerm) {
        curPrimary = true;
        permPrimary = false;
    } else if (
        sendingCur !== null &&
        sendingPerm !== null &&
        !curPrimary &&
        !permPrimary
    ) {
        curPrimary = true;
    }

    if (sendingPerm !== null) {
        sendingPerm.isPrimary = permPrimary;
    }
    if (sendingCur !== null) {
        sendingCur.isPrimary = curPrimary;
    }

    processing.value = true;
    fieldErrors.value = {};

    router.patch(
        syncEmployeeAboutMeAddresses.url(props.profile.employee_id),
        {
            permanent:
                sendingPerm !== null
                    ? addressApiPayload(sendingPerm)
                    : null,
            current:
                sendingCur !== null ? addressApiPayload(sendingCur) : null,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
            onSuccess: () => {
                appToast.success('Addresses updated.');
                emit('update:open', false);
                router.reload({
                    only: ['profile'],
                });
            },
            onError: (pageErrors: PageErrorsBag) => {
                fieldErrors.value = pageErrors ?? {};
                const top =
                    (typeof pageErrors.current === 'string'
                        ? pageErrors.current
                        : null) ??
                    (typeof pageErrors.permanent === 'string'
                        ? pageErrors.permanent
                        : null);
                if (top !== null && top !== '') {
                    formError.value = top;

                    return;
                }
                if (
                    pageErrors !== null &&
                    typeof pageErrors === 'object' &&
                    Object.keys(pageErrors).length === 0
                ) {
                    appToast.error(
                        'Could not save addresses. Please try again.',
                    );
                }
            },
        },
    );
}

function onOpenChange(value: boolean): void {
    emit('update:open', value);
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent class="gap-6 sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>Edit addresses</DialogTitle>
                <DialogDescription>
                    Same structure as Add Employee — PSGC province / city /
                    barangay and “Use Permanent Address” for current. Changes
                    save to HR records immediately.
                </DialogDescription>
            </DialogHeader>

            <ScrollArea :class="aboutMeDialogScrollAreaClass">
                <div class="grid gap-8 px-1 py-1">
                    <p
                        v-if="
                            fieldErrors.error !== undefined &&
                            fieldErrors.error !== ''
                        "
                        class="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                    >
                        {{ fieldErrors.error }}
                    </p>
                    <section class="min-w-0 space-y-3">
                        <div
                            class="rounded-lg border border-primary/30 bg-muted/30 p-4 dark:bg-muted/25"
                        >
                            <div class="flex flex-row items-start gap-4">
                                <span
                                    class="inline-flex shrink-0 rounded-full bg-primary/10 p-3 text-primary"
                                >
                                    <MapPin
                                        class="size-7 shrink-0"
                                        aria-hidden="true"
                                    />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div class="min-w-0 flex-1 space-y-0.5">
                                            <div
                                                class="flex flex-wrap items-center gap-x-2 gap-y-1"
                                            >
                                                <h2
                                                    class="text-base font-semibold text-foreground"
                                                    :class="
                                                        aboutMeMaxSmOneLineTruncateClass
                                                    "
                                                    title="Permanent Address"
                                                >
                                                    Permanent Address
                                                </h2>
                                                <Badge
                                                    variant="secondary"
                                                    class="shrink-0"
                                                    >Optional</Badge
                                                >
                                            </div>
                                            <p
                                                class="mt-0 text-sm leading-relaxed text-muted-foreground"
                                                :class="
                                                    aboutMeMaxSmOneLineTruncateClass
                                                "
                                            >
                                                Primary residence on file for IDs,
                                                payroll, and HR correspondence.
                                            </p>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            class="shrink-0 cursor-pointer border-border bg-background/80 hover:border-foreground/40 hover:bg-muted/60"
                                            :aria-expanded="showPermanentAddress"
                                            aria-controls="about-me-perm-fields"
                                            :aria-label="
                                                showPermanentAddress
                                                    ? 'Hide permanent address'
                                                    : 'Show permanent address'
                                            "
                                            @click="
                                                showPermanentAddress =
                                                    !showPermanentAddress
                                            "
                                        >
                                            <Minus
                                                v-if="showPermanentAddress"
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                            <Plus v-else class="size-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="showPermanentAddress"
                            id="about-me-perm-fields"
                            class="space-y-6"
                        >
                            <div :class="aboutMePrimaryToggleRowClass">
                                <div class="flex shrink-0 items-center gap-2">
                                    <Checkbox
                                        id="about_me_perm_primary"
                                        class="shrink-0"
                                        :model-value="permanentAddress.isPrimary"
                                        aria-labelledby="about_me_perm_primary_lbl"
                                        @update:model-value="
                                            setPermanentAddressPrimary
                                        "
                                    />
                                    <label
                                        id="about_me_perm_primary_lbl"
                                        class="cursor-pointer text-sm font-medium text-foreground"
                                        for="about_me_perm_primary"
                                    >
                                        <Badge>Primary</Badge>
                                    </label>
                                </div>
                                <div
                                    :class="aboutMePrimaryToggleRuleClass"
                                    role="presentation"
                                    aria-hidden="true"
                                />
                            </div>

                            <div
                                class="grid grid-cols-1 gap-x-4 gap-y-6 md:grid-cols-2"
                            >
                                <div class="grid gap-3">
                                    <Label
                                        for="about_me_perm_l1"
                                        :class="aboutMeLabelRowClass"
                                    >
                                        <span>Address line 1</span>
                                        <Badge
                                            v-if="permInvalid.addressLine1"
                                            variant="destructive"
                                            >Required</Badge
                                        >
                                        <Button
                                            v-if="
                                                permanentAddress.addressLine1 !==
                                                ''
                                            "
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :class="aboutMeClearFieldButtonClass"
                                            aria-label="Clear address line 1"
                                            @click="
                                                permanentAddress.addressLine1 =
                                                    ''
                                            "
                                        >
                                            <X class="size-3.5" />
                                        </Button>
                                    </Label>
                                    <Input
                                        id="about_me_perm_l1"
                                        v-model="permanentAddress.addressLine1"
                                        class="w-full"
                                        maxlength="255"
                                        autocomplete="address-line1"
                                        placeholder="Street, building, or unit"
                                        :aria-invalid="
                                            permInvalid.addressLine1
                                        "
                                    />
                                </div>
                                <div class="grid gap-3">
                                    <Label
                                        for="about_me_perm_l2"
                                        :class="aboutMeLabelRowClass"
                                    >
                                        <span>Address line 2</span>
                                        <Badge variant="secondary"
                                            >Optional</Badge
                                        >
                                        <Button
                                            v-if="
                                                permanentAddress.addressLine2 !==
                                                ''
                                            "
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :class="aboutMeClearFieldButtonClass"
                                            aria-label="Clear address line 2"
                                            @click="
                                                permanentAddress.addressLine2 =
                                                    ''
                                            "
                                        >
                                            <X class="size-3.5" />
                                        </Button>
                                    </Label>
                                    <Input
                                        id="about_me_perm_l2"
                                        v-model="permanentAddress.addressLine2"
                                        class="w-full"
                                        maxlength="255"
                                        autocomplete="address-line2"
                                        placeholder="Floor, subdivision, or detail"
                                    />
                                </div>
                            </div>

                            <div
                                class="grid grid-cols-1 gap-x-4 gap-y-6 md:grid-cols-2 lg:grid-cols-3"
                            >
                                <div class="grid gap-3">
                                    <Label
                                        for="about_me_perm_country"
                                        :class="aboutMeLabelRowClass"
                                    >
                                        <span>Country</span>
                                        <Button
                                            v-if="
                                                permanentAddress.country !==
                                                'Philippines'
                                            "
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :class="aboutMeClearFieldButtonClass"
                                            aria-label="Reset country"
                                            @click="
                                                permanentAddress.country =
                                                    'Philippines'
                                            "
                                        >
                                            <X class="size-3.5" />
                                        </Button>
                                    </Label>
                                    <Select
                                        :model-value="permanentAddress.country"
                                        @update:model-value="
                                            (v) => {
                                                permanentAddress.country =
                                                    typeof v === 'string'
                                                        ? v
                                                        : String(v);
                                            }
                                        "
                                    >
                                        <SelectTrigger
                                            id="about_me_perm_country"
                                            class="w-full min-w-0"
                                        >
                                            <SelectValue
                                                placeholder="Select country"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="c in countryOptions"
                                                :key="c"
                                                :value="c"
                                            >
                                                {{ c }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-3">
                                    <Label
                                        for="about_me_perm_province"
                                        :class="aboutMeLabelRowClass"
                                    >
                                        <span>Province</span>
                                        <Badge
                                            v-if="permInvalid.provinceCode"
                                            variant="destructive"
                                            >Required</Badge
                                        >
                                        <Button
                                            v-if="
                                                permanentAddress.provinceCode !==
                                                ''
                                            "
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :class="aboutMeClearFieldButtonClass"
                                            aria-label="Clear province"
                                            @click="clearProvince"
                                        >
                                            <X class="size-3.5" />
                                        </Button>
                                    </Label>
                                    <Select
                                        :model-value="
                                            permanentAddress.provinceCode
                                        "
                                        @update:model-value="onProvinceChange"
                                    >
                                        <SelectTrigger
                                            id="about_me_perm_province"
                                            class="w-full min-w-0"
                                            :aria-invalid="
                                                permInvalid.provinceCode
                                            "
                                        >
                                            <SelectValue
                                                placeholder="Select province"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="opt in provinceOptions"
                                                :key="opt.code"
                                                :value="opt.code"
                                            >
                                                {{ opt.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-3">
                                    <Label
                                        for="about_me_perm_city"
                                        :class="aboutMeLabelRowClass"
                                    >
                                        <span>City</span>
                                        <Badge
                                            v-if="permInvalid.cityCode"
                                            variant="destructive"
                                            >Required</Badge
                                        >
                                        <Button
                                            v-if="
                                                permanentAddress.cityCode !== ''
                                            "
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :class="aboutMeClearFieldButtonClass"
                                            aria-label="Clear city"
                                            @click="clearCity"
                                        >
                                            <X class="size-3.5" />
                                        </Button>
                                    </Label>
                                    <Select
                                        :model-value="permanentAddress.cityCode"
                                        @update:model-value="onCityChange"
                                    >
                                        <SelectTrigger
                                            id="about_me_perm_city"
                                            class="w-full min-w-0"
                                            :disabled="
                                                permanentAddress.provinceCode ===
                                                ''
                                            "
                                            :aria-invalid="permInvalid.cityCode"
                                        >
                                            <SelectValue
                                                placeholder="Select city/municipality"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="opt in cityOptions"
                                                :key="opt.code"
                                                :value="opt.code"
                                            >
                                                {{ opt.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-3">
                                    <Label
                                        for="about_me_perm_brgy"
                                        :class="aboutMeLabelRowClass"
                                    >
                                        <span>Barangay</span>
                                        <Badge
                                            v-if="permInvalid.barangayCode"
                                            variant="destructive"
                                            >Required</Badge
                                        >
                                        <Button
                                            v-if="
                                                permanentAddress.barangayCode !==
                                                ''
                                            "
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :class="aboutMeClearFieldButtonClass"
                                            aria-label="Clear barangay"
                                            @click="clearBarangay"
                                        >
                                            <X class="size-3.5" />
                                        </Button>
                                    </Label>
                                    <Select
                                        :model-value="
                                            permanentAddress.barangayCode
                                        "
                                        @update:model-value="onBarangayChange"
                                    >
                                        <SelectTrigger
                                            id="about_me_perm_brgy"
                                            class="w-full min-w-0"
                                            :disabled="
                                                permanentAddress.cityCode === ''
                                            "
                                            :aria-invalid="
                                                permInvalid.barangayCode
                                            "
                                        >
                                            <SelectValue
                                                placeholder="Select barangay"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="opt in barangayOptions"
                                                :key="opt.code"
                                                :value="opt.code"
                                            >
                                                {{ opt.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-3">
                                    <Label
                                        for="about_me_perm_zip"
                                        :class="aboutMeLabelRowClass"
                                    >
                                        <span>ZIP code</span>
                                        <Badge
                                            v-if="permInvalid.zipCode"
                                            variant="destructive"
                                            >Required</Badge
                                        >
                                        <Button
                                            v-if="
                                                permanentAddress.zipCode !== ''
                                            "
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :class="aboutMeClearFieldButtonClass"
                                            aria-label="Clear ZIP code"
                                            @click="
                                                permanentAddress.zipCode = ''
                                            "
                                        >
                                            <X class="size-3.5" />
                                        </Button>
                                    </Label>
                                    <Input
                                        id="about_me_perm_zip"
                                        v-model="permanentAddress.zipCode"
                                        class="w-full"
                                        maxlength="20"
                                        inputmode="numeric"
                                        autocomplete="postal-code"
                                        placeholder="e.g. 1600"
                                        :aria-invalid="permInvalid.zipCode"
                                    />
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="min-w-0 space-y-3">
                        <div
                            class="rounded-lg border border-primary/30 bg-muted/30 p-4 dark:bg-muted/25"
                        >
                            <div class="flex flex-row items-start gap-4">
                                <span
                                    class="inline-flex shrink-0 rounded-full bg-primary/10 p-3 text-primary"
                                >
                                    <MapPin
                                        class="size-7 shrink-0"
                                        aria-hidden="true"
                                    />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div class="min-w-0 flex-1 space-y-0.5">
                                            <div
                                                class="flex flex-wrap items-center gap-x-2 gap-y-1"
                                            >
                                                <h2
                                                    class="text-base font-semibold text-foreground"
                                                    :class="
                                                        aboutMeMaxSmOneLineTruncateClass
                                                    "
                                                    title="Current Address"
                                                >
                                                    Current Address
                                                </h2>
                                                <Badge
                                                    variant="secondary"
                                                    class="shrink-0"
                                                    >Optional</Badge
                                                >
                                            </div>
                                            <p
                                                class="mt-0 text-sm leading-relaxed text-muted-foreground"
                                                :class="
                                                    aboutMeMaxSmOneLineTruncateClass
                                                "
                                            >
                                                Where you live or receive mail
                                                today — may differ from permanent.
                                            </p>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            class="shrink-0 cursor-pointer border-border bg-background/80 hover:border-foreground/40 hover:bg-muted/60"
                                            :aria-expanded="showCurrentAddress"
                                            aria-controls="about-me-current-fields"
                                            :aria-label="
                                                showCurrentAddress
                                                    ? 'Hide current address'
                                                    : 'Show current address'
                                            "
                                            @click="
                                                showCurrentAddress =
                                                    !showCurrentAddress
                                            "
                                        >
                                            <Minus
                                                v-if="showCurrentAddress"
                                                class="size-4"
                                            />
                                            <Plus v-else class="size-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="showCurrentAddress"
                            id="about-me-current-fields"
                            class="space-y-6"
                        >
                            <div :class="aboutMePrimaryToggleRowClass">
                                <div class="flex shrink-0 items-center gap-2">
                                    <Checkbox
                                        id="about_me_cur_primary"
                                        class="shrink-0"
                                        :model-value="currentAddress.isPrimary"
                                        aria-labelledby="about_me_cur_primary_lbl"
                                        @update:model-value="
                                            setCurrentAddressPrimary
                                        "
                                    />
                                    <label
                                        id="about_me_cur_primary_lbl"
                                        class="cursor-pointer text-sm font-medium text-foreground"
                                        for="about_me_cur_primary"
                                    >
                                        <Badge>Primary</Badge>
                                    </label>
                                </div>
                                <div
                                    :class="aboutMePrimaryToggleRuleClass"
                                    role="presentation"
                                    aria-hidden="true"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="shrink-0 rounded-lg"
                                    :disabled="!canUsePermanentAddress"
                                    @click="usePermanentAddressForCurrent"
                                >
                                    Use Permanent Address
                                </Button>
                            </div>

                            <div
                                class="grid grid-cols-1 gap-x-4 gap-y-6 md:grid-cols-2"
                            >
                                <div class="grid gap-3">
                                    <Label
                                        for="about_me_cur_l1"
                                        :class="aboutMeLabelRowClass"
                                    >
                                        <span>Address line 1</span>
                                        <Badge
                                            v-if="curInvalid.addressLine1"
                                            variant="destructive"
                                            >Required</Badge
                                        >
                                        <Button
                                            v-if="
                                                currentAddress.addressLine1 !==
                                                ''
                                            "
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :class="aboutMeClearFieldButtonClass"
                                            aria-label="Clear address line 1"
                                            @click="
                                                currentAddress.addressLine1 = ''
                                            "
                                        >
                                            <X class="size-3.5" />
                                        </Button>
                                    </Label>
                                    <Input
                                        id="about_me_cur_l1"
                                        v-model="currentAddress.addressLine1"
                                        class="w-full"
                                        maxlength="255"
                                        autocomplete="address-line1"
                                        placeholder="Street, building, or unit"
                                        :aria-invalid="
                                            curInvalid.addressLine1
                                        "
                                    />
                                </div>
                                <div class="grid gap-3">
                                    <Label
                                        for="about_me_cur_l2"
                                        :class="aboutMeLabelRowClass"
                                    >
                                        <span>Address line 2</span>
                                        <Badge variant="secondary"
                                            >Optional</Badge
                                        >
                                        <Button
                                            v-if="
                                                currentAddress.addressLine2 !==
                                                ''
                                            "
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :class="aboutMeClearFieldButtonClass"
                                            aria-label="Clear address line 2"
                                            @click="
                                                currentAddress.addressLine2 = ''
                                            "
                                        >
                                            <X class="size-3.5" />
                                        </Button>
                                    </Label>
                                    <Input
                                        id="about_me_cur_l2"
                                        v-model="currentAddress.addressLine2"
                                        class="w-full"
                                        maxlength="255"
                                        autocomplete="address-line2"
                                        placeholder="Floor, subdivision, or detail"
                                    />
                                </div>
                            </div>

                            <div
                                class="grid grid-cols-1 gap-x-4 gap-y-6 md:grid-cols-2 lg:grid-cols-3"
                            >
                                <div class="grid gap-3">
                                    <Label
                                        for="about_me_cur_country"
                                        :class="aboutMeLabelRowClass"
                                    >
                                        <span>Country</span>
                                        <Button
                                            v-if="
                                                currentAddress.country !==
                                                'Philippines'
                                            "
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :class="aboutMeClearFieldButtonClass"
                                            aria-label="Reset country"
                                            @click="
                                                currentAddress.country =
                                                    'Philippines'
                                            "
                                        >
                                            <X class="size-3.5" />
                                        </Button>
                                    </Label>
                                    <Select
                                        :model-value="currentAddress.country"
                                        @update:model-value="
                                            (v) => {
                                                currentAddress.country =
                                                    typeof v === 'string'
                                                        ? v
                                                        : String(v);
                                            }
                                        "
                                    >
                                        <SelectTrigger
                                            id="about_me_cur_country"
                                            class="w-full min-w-0"
                                        >
                                            <SelectValue
                                                placeholder="Select country"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="c in countryOptions"
                                                :key="c"
                                                :value="c"
                                            >
                                                {{ c }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-3">
                                    <Label
                                        for="about_me_cur_province"
                                        :class="aboutMeLabelRowClass"
                                    >
                                        <span>Province</span>
                                        <Badge
                                            v-if="curInvalid.provinceCode"
                                            variant="destructive"
                                            >Required</Badge
                                        >
                                        <Button
                                            v-if="
                                                currentAddress.provinceCode !==
                                                ''
                                            "
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :class="aboutMeClearFieldButtonClass"
                                            aria-label="Clear province"
                                            @click="clearCurrentProvince"
                                        >
                                            <X class="size-3.5" />
                                        </Button>
                                    </Label>
                                    <Select
                                        :model-value="
                                            currentAddress.provinceCode
                                        "
                                        @update:model-value="
                                            onCurrentProvinceChange
                                        "
                                    >
                                        <SelectTrigger
                                            id="about_me_cur_province"
                                            class="w-full min-w-0"
                                            :aria-invalid="
                                                curInvalid.provinceCode
                                            "
                                        >
                                            <SelectValue
                                                placeholder="Select province"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="opt in provinceOptions"
                                                :key="opt.code"
                                                :value="opt.code"
                                            >
                                                {{ opt.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-3">
                                    <Label
                                        for="about_me_cur_city"
                                        :class="aboutMeLabelRowClass"
                                    >
                                        <span>City</span>
                                        <Badge
                                            v-if="curInvalid.cityCode"
                                            variant="destructive"
                                            >Required</Badge
                                        >
                                        <Button
                                            v-if="
                                                currentAddress.cityCode !== ''
                                            "
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :class="aboutMeClearFieldButtonClass"
                                            aria-label="Clear city"
                                            @click="clearCurrentCity"
                                        >
                                            <X class="size-3.5" />
                                        </Button>
                                    </Label>
                                    <Select
                                        :model-value="currentAddress.cityCode"
                                        @update:model-value="
                                            onCurrentCityChange
                                        "
                                    >
                                        <SelectTrigger
                                            id="about_me_cur_city"
                                            class="w-full min-w-0"
                                            :disabled="
                                                currentAddress.provinceCode ===
                                                ''
                                            "
                                            :aria-invalid="curInvalid.cityCode"
                                        >
                                            <SelectValue
                                                placeholder="Select city/municipality"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="opt in currentCityOptions"
                                                :key="opt.code"
                                                :value="opt.code"
                                            >
                                                {{ opt.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-3">
                                    <Label
                                        for="about_me_cur_brgy"
                                        :class="aboutMeLabelRowClass"
                                    >
                                        <span>Barangay</span>
                                        <Badge
                                            v-if="curInvalid.barangayCode"
                                            variant="destructive"
                                            >Required</Badge
                                        >
                                        <Button
                                            v-if="
                                                currentAddress.barangayCode !==
                                                ''
                                            "
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :class="aboutMeClearFieldButtonClass"
                                            aria-label="Clear barangay"
                                            @click="clearCurrentBarangay"
                                        >
                                            <X class="size-3.5" />
                                        </Button>
                                    </Label>
                                    <Select
                                        :model-value="
                                            currentAddress.barangayCode
                                        "
                                        @update:model-value="
                                            onCurrentBarangayChange
                                        "
                                    >
                                        <SelectTrigger
                                            id="about_me_cur_brgy"
                                            class="w-full min-w-0"
                                            :disabled="
                                                currentAddress.cityCode === ''
                                            "
                                            :aria-invalid="
                                                curInvalid.barangayCode
                                            "
                                        >
                                            <SelectValue
                                                placeholder="Select barangay"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="opt in currentBarangayOptions"
                                                :key="opt.code"
                                                :value="opt.code"
                                            >
                                                {{ opt.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-3">
                                    <Label
                                        for="about_me_cur_zip"
                                        :class="aboutMeLabelRowClass"
                                    >
                                        <span>ZIP code</span>
                                        <Badge
                                            v-if="curInvalid.zipCode"
                                            variant="destructive"
                                            >Required</Badge
                                        >
                                        <Button
                                            v-if="
                                                currentAddress.zipCode !== ''
                                            "
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :class="aboutMeClearFieldButtonClass"
                                            aria-label="Clear ZIP code"
                                            @click="currentAddress.zipCode = ''"
                                        >
                                            <X class="size-3.5" />
                                        </Button>
                                    </Label>
                                    <Input
                                        id="about_me_cur_zip"
                                        v-model="currentAddress.zipCode"
                                        class="w-full"
                                        maxlength="20"
                                        inputmode="numeric"
                                        autocomplete="postal-code"
                                        placeholder="e.g. 1600"
                                        :aria-invalid="curInvalid.zipCode"
                                    />
                                </div>
                            </div>
                        </div>
                    </section>

                    <p v-if="formError !== ''" class="text-sm text-destructive">
                        {{ formError }}
                    </p>
                </div>
            </ScrollArea>

            <DialogFooter class="gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="rounded-lg"
                    :disabled="processing"
                    @click="onOpenChange(false)"
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    class="rounded-lg"
                    :disabled="processing"
                    @click="onSave"
                >
                    Save
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
