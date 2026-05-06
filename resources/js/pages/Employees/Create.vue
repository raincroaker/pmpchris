<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { getLocalTimeZone, parseDate } from '@internationalized/date';
import type { DateValue } from '@internationalized/date';
import { listBarangays, listMuncities, listProvinces } from '@jobuntux/psgc';
import {
    Briefcase,
    Building2,
    CalendarDays,
    Check,
    ChevronDownIcon,
    Eye,
    EyeOff,
    Home,
    IdCard,
    Info,
    KeyRound,
    LifeBuoy,
    MapPin,
    Minus,
    Phone,
    Plus,
    UserCircle,
    X,
} from 'lucide-vue-next';
import { computed, onUnmounted, reactive, ref, toRefs, watch } from 'vue';
import EmployeePositionPicker from '@/components/employees/EmployeePositionPicker.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Stepper,
    StepperIndicator,
    StepperItem,
    StepperSeparator,
    StepperTitle,
    StepperTrigger,
} from '@/components/ui/stepper';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import { appToast } from '@/lib/app-toast-client';
import { formatCalendarTriggerFromDate } from '@/lib/formatCalendarTriggerDate';
import { cn } from '@/lib/utils';
import {
    employeeStepOneCivilStatusOptions as civilStatusOptions,
    employeeStepOneNationalityOptions as nationalityOptions,
    employeeStepOneReligionOptions as religionOptions,
    employeeStepOneSexOptions as sexOptions,
} from '@/pages/Employees/employeeStepOneOptions';
import { employees } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Employees', href: employees() },
    { title: 'Add Employee', href: '/employees/create' },
];

/** Catalog row from `EmployeesCreateController` (`positions` prop); `id` → `employee_positions.position_id`. */
type PositionOption = {
    id: number;
    code: string;
    title: string;
};

/**
 * One Employment-step row → future `employee_positions`
 * (`position_id`, `is_primary`, `start_date`, `end_date`; add `notes` when needed).
 */
type StepThreeEmployeePositionRow = {
    positionId: string;
    startDate: string;
    endDate: string;
    isPrimary: boolean;
};

/** Hidden default org for future `employee_affiliations.organization_id` when `root_unit_id` is null. */
type AffiliationOrganizationMeta = {
    id: number;
    code: string;
    name: string;
};

/** Root unit row from `BranchContextService::branchesForPicker()` (snake_case JSON keys). */
type AffiliationRootOption = {
    id: number;
    code: string;
    name: string;
    area_name: string | null;
    group_label: string;
};

/**
 * One Employment-step row → future `employee_affiliations`
 * (`organization_id` from hidden `affiliationOrganization`; optional `root_unit_id`; `is_primary`, dates).
 */
type StepThreeEmployeeAffiliationRow = {
    rootUnitId: string;
    startDate: string;
    endDate: string;
    isPrimary: boolean;
};

/** `SelectItem` value when no root branch (maps to `root_unit_id` null on save). */
const employeeAffiliationRootNoneValue = '__org_wide__';

const props = withDefaults(
    defineProps<{
        positions: PositionOption[];
        affiliationOrganization: AffiliationOrganizationMeta | null;
        affiliationRoots: AffiliationRootOption[];
        allowOrgWideAffiliation: boolean;
    }>(),
    {
        positions: () => [],
        affiliationOrganization: null,
        affiliationRoots: () => [],
        allowOrgWideAffiliation: false,
    },
);

const {
    positions,
    affiliationRoots,
    affiliationOrganization,
    allowOrgWideAffiliation,
} = toRefs(props);

const currentStep = ref(1);

const suffixOptions = ['Jr.', 'Sr.', 'II', 'III', 'IV', 'V'] as const;

type StepOnePersonalInfo = {
    firstName: string;
    lastName: string;
    middleName: string;
    suffix: string;
    birthdate: DateValue | undefined;
    sex: string;
    civilStatus: string;
    nationality: string;
    religion: string;
    religionOther: string;
};

const personalInfo = reactive<StepOnePersonalInfo>({
    firstName: '',
    lastName: '',
    middleName: '',
    suffix: '',
    birthdate: undefined,
    sex: '',
    civilStatus: '',
    nationality: '',
    religion: '',
    religionOther: '',
});

type PermanentAddressForm = {
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

const permanentAddress = reactive<PermanentAddressForm>({
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
});

const currentAddress = reactive<PermanentAddressForm>({
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
});

type AddressOption = {
    code: string;
    name: string;
};

const countryOptions = ['Philippines'] as const;

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

function clearBirthdate(): void {
    personalInfo.birthdate = undefined;
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

function clearCurrentProvince(): void {
    onCurrentProvinceChange('');
}

function clearCurrentCity(): void {
    onCurrentCityChange('');
}

function clearCurrentBarangay(): void {
    onCurrentBarangayChange('');
}

/** Permanent address row `is_primary` for future `employee_addresses` (independent of current address). */
function setPermanentAddressPrimary(checked: unknown): void {
    permanentAddress.isPrimary = Boolean(checked);
}

/** Current address row `is_primary` for future `employee_addresses` (independent of permanent address). */
function setCurrentAddressPrimary(checked: unknown): void {
    currentAddress.isPrimary = Boolean(checked);
}

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

/**
 * Fixed for `employee_addresses.type` when this block is persisted; not shown in the UI.
 * (Reserved for future POST payload mapping.)
 */
const permanentAddressType = 'permanent' as const;
void permanentAddressType;
const currentAddressType = 'current' as const;
void currentAddressType;

const showPermanentAddress = ref(false);

/** Step 2 optional panels; when open, fields are required to proceed (see `stepTwo*` validation). */
const showCurrentAddress = ref(false);
const showEmergencyContacts = ref(false);

const stepTwoAttempted = ref(false);

/** Step 3 (Employment): after Next, show Required / aria-invalid on employment period, Company ID, positions and affiliations. */
const stepThreeAttempted = ref(false);

/**
 * Personal contact rows map to future `employee_contacts` rows (`category` personal).
 */
const personalContactCategory = 'personal' as const;
void personalContactCategory;
const emergencyContactCategory = 'emergency' as const;
void emergencyContactCategory;

const personalContactTypeOptions = [
    { value: 'mobile', label: 'Mobile' },
    { value: 'home', label: 'Home' },
    { value: 'work', label: 'Work' },
] as const;
const emergencyContactTypeOptions = [
    { value: 'mobile', label: 'Mobile' },
] as const;

type StepTwoPersonalContact = {
    type: string;
    contactNumber: string;
    email: string;
    isPrimary: boolean;
};

type StepTwoEmergencyContact = {
    type: string;
    contactPerson: string;
    relationship: string;
    contactNumber: string;
    email: string;
    isPrimary: boolean;
};

function createEmptyPersonalContact(): StepTwoPersonalContact {
    return {
        type: '',
        contactNumber: '',
        email: '',
        isPrimary: false,
    };
}

const personalContacts = reactive<StepTwoPersonalContact[]>([
    { ...createEmptyPersonalContact(), isPrimary: true },
]);

function addPersonalContact(): void {
    personalContacts.push(createEmptyPersonalContact());
}

function removePersonalContact(index: number): void {
    if (personalContacts.length <= 1) {
        return;
    }

    personalContacts.splice(index, 1);
}

function setPrimaryPersonalContact(index: number, checked: unknown): void {
    const shouldBePrimary = Boolean(checked);

    personalContacts.forEach((contact, contactIndex) => {
        contact.isPrimary = shouldBePrimary && contactIndex === index;
    });
}

function createEmptyEmergencyContact(): StepTwoEmergencyContact {
    return {
        type: 'mobile',
        contactPerson: '',
        relationship: '',
        contactNumber: '',
        email: '',
        isPrimary: false,
    };
}

const emergencyContacts = reactive<StepTwoEmergencyContact[]>([
    createEmptyEmergencyContact(),
]);

function addEmergencyContact(): void {
    emergencyContacts.push(createEmptyEmergencyContact());
}

function removeEmergencyContact(index: number): void {
    if (emergencyContacts.length <= 1) {
        return;
    }

    emergencyContacts.splice(index, 1);
}

function setPrimaryEmergencyContact(index: number, checked: unknown): void {
    const shouldBePrimary = Boolean(checked);

    emergencyContacts.forEach((contact, contactIndex) => {
        contact.isPrimary = shouldBePrimary && contactIndex === index;
    });
}

/** Default row for the Employment → Positions repeater (not persisted until employee store exists). */
function createEmptyEmployeePosition(): StepThreeEmployeePositionRow {
    return {
        positionId: '',
        startDate: '',
        endDate: '',
        isPrimary: false,
    };
}

/**
 * Repeater state for step 3 → `EmployeePosition` many-create on submit.
 * Keep `position_id` values within `props.positions` (org-scoped catalog from server).
 */
const employeePositionRows = reactive<StepThreeEmployeePositionRow[]>([
    createEmptyEmployeePosition(),
]);

function addEmployeePosition(): void {
    employeePositionRows.push(createEmptyEmployeePosition());
}

/** At least one row remains (same guard as personal contacts). */
function removeEmployeePosition(index: number): void {
    if (employeePositionRows.length <= 1) {
        return;
    }

    employeePositionRows.splice(index, 1);
}

/** At most one position row may be primary (matches personal contact primary semantics). */
function setPrimaryEmployeePosition(index: number, checked: unknown): void {
    const shouldBePrimary = Boolean(checked);

    employeePositionRows.forEach((row, rowIndex) => {
        row.isPrimary = shouldBePrimary && rowIndex === index;
    });
}

/** Serialize calendar value to `Y-m-d` for `employee_positions.start_date` / `end_date`. */
function dateValueToIsoDate(value: DateValue): string {
    return value.toDate(getLocalTimeZone()).toISOString().slice(0, 10);
}

/** Compare ISO `Y-m-d` strings (lexicographic order matches calendar order). */
function employeePositionIsoDateCompare(a: string, b: string): number {
    const as = a.trim();
    const bs = b.trim();
    if (as === '' || bs === '') {
        return 0;
    }

    return as < bs ? -1 : as > bs ? 1 : 0;
}

/** Calendar `model-value` from stored ISO date string (empty → undefined). */
function employeePositionRowCalendarValue(iso: string): DateValue | undefined {
    if (iso.trim() === '') {
        return undefined;
    }
    try {
        return parseDate(iso) as DateValue;
    } catch {
        return undefined;
    }
}

/** ISO `Y-m-d` lexicographic max of two trimmed strings (empty ignored). */
function stepThreeEffectiveMinIsoForRowEnd(
    hireIso: string,
    rowStartIso: string,
): string {
    const h = hireIso.trim();
    const s = rowStartIso.trim();
    if (s === '') {
        return h;
    }
    if (h === '') {
        return s;
    }

    return h >= s ? h : s;
}

/** Calendar min for affiliation/position end: later of hire and row start. */
function stepThreeRowEndCalendarMinFromHireStart(
    hireIso: string,
    rowStartIso: string,
): DateValue | undefined {
    const iso = stepThreeEffectiveMinIsoForRowEnd(hireIso, rowStartIso);
    if (iso === '') {
        return undefined;
    }

    return employeePositionRowCalendarValue(iso);
}

/** Button label for row date pickers (ISO → localized). */
function employeePositionIsoDisplay(iso: string): string {
    const value = employeePositionRowCalendarValue(iso);
    if (value === undefined) {
        return '';
    }
    if (!('toDate' in value) || typeof value.toDate !== 'function') {
        return '';
    }

    return formatCalendarTriggerFromDate(value.toDate(getLocalTimeZone()));
}

function onEmployeePositionRowStartSelect(
    index: number,
    value: unknown,
    close: () => void,
): void {
    const row = employeePositionRows[index];
    if (row === undefined) {
        close();

        return;
    }
    if (!value || Array.isArray(value)) {
        row.startDate = '';
        row.endDate = '';
        close();

        return;
    }
    if (typeof value !== 'object' || value === null) {
        row.startDate = '';
        row.endDate = '';
        close();

        return;
    }
    if (
        !('toDate' in value) ||
        typeof (value as { toDate?: unknown }).toDate !== 'function'
    ) {
        row.startDate = '';
        row.endDate = '';
        close();

        return;
    }

    row.startDate = dateValueToIsoDate(value as DateValue);
    if (
        row.endDate.trim() !== '' &&
        employeePositionIsoDateCompare(row.endDate, row.startDate) < 0
    ) {
        row.endDate = '';
    }
    const hireIso = employmentHireDate.value.trim();
    if (
        row.endDate.trim() !== '' &&
        hireIso !== '' &&
        employeePositionIsoDateCompare(row.endDate, hireIso) < 0
    ) {
        row.endDate = '';
    }
    const sepIso = employmentSeparationDate.value.trim();
    if (
        row.endDate.trim() !== '' &&
        sepIso !== '' &&
        employeePositionIsoDateCompare(row.endDate, sepIso) > 0
    ) {
        row.endDate = '';
    }
    close();
}

function onEmployeePositionRowEndSelect(
    index: number,
    value: unknown,
    close: () => void,
): void {
    const row = employeePositionRows[index];
    if (row === undefined) {
        close();

        return;
    }
    if (!value || Array.isArray(value)) {
        row.endDate = '';
        close();

        return;
    }
    if (typeof value !== 'object' || value === null) {
        row.endDate = '';
        close();

        return;
    }
    if (
        !('toDate' in value) ||
        typeof (value as { toDate?: unknown }).toDate !== 'function'
    ) {
        row.endDate = '';
        close();

        return;
    }

    const candidate = dateValueToIsoDate(value as DateValue);
    if (row.startDate.trim() === '') {
        close();

        return;
    }
    if (employeePositionIsoDateCompare(candidate, row.startDate) < 0) {
        close();

        return;
    }

    const hireIso = employmentHireDate.value.trim();
    if (
        hireIso !== '' &&
        employeePositionIsoDateCompare(candidate, hireIso) < 0
    ) {
        close();

        return;
    }
    const sepIso = employmentSeparationDate.value.trim();
    if (
        sepIso !== '' &&
        employeePositionIsoDateCompare(candidate, sepIso) > 0
    ) {
        close();

        return;
    }

    row.endDate = candidate;
    close();
}

function clearEmployeePositionRowStart(index: number): void {
    const row = employeePositionRows[index];
    if (row !== undefined) {
        row.startDate = '';
        row.endDate = '';
    }
}

function clearEmployeePositionRowEnd(index: number): void {
    const row = employeePositionRows[index];
    if (row !== undefined) {
        row.endDate = '';
    }
}

watch(
    employeePositionRows,
    () => {
        const hasSeparation = employmentSeparationDate.value.trim() !== '';
        employeePositionRows.forEach((row) => {
            if (!hasSeparation && row.endDate.trim() !== '') {
                row.isPrimary = false;
            }
        });
    },
    { deep: true },
);

/** Required company employee identifier → future `employees.id_number` on submit. */
const employmentIdNumber = ref('');

/** Optional timekeeping / access-system identifier → future `employees.attendance_id` on submit. */
const employmentAttendanceId = ref('');

/**
 * Canonical `employee_employments` fields on Add Employee (Step 3).
 * Status values match backend `EmployeeEmployment` model constants (`active`, `resigned`, `terminated`, `retired`, `contract_ended`).
 * Active → active, Resigned → resigned, Terminated → terminated, Retired → retired, Contract Ended → contract_ended.
 * `is_current` is derived server-side: true when separation date is empty.
 */
const employmentStatusValues = [
    'active',
    'resigned',
    'terminated',
    'retired',
    'contract_ended',
] as const;

const employmentStatusLabels: Record<
    (typeof employmentStatusValues)[number],
    string
> = {
    active: 'Active',
    resigned: 'Resigned',
    terminated: 'Terminated',
    retired: 'Retired',
    contract_ended: 'Contract Ended',
};

/** Separation empty → Active only; with separation → non-active endings only */
const selectableEmploymentStatusKeys = computed(
    (): ReadonlyArray<(typeof employmentStatusValues)[number]> => {
        if (employmentSeparationDate.value.trim() === '') {
            return [employmentStatusValues[0]];
        }

        return employmentStatusValues.filter(
            (
                s,
            ): s is Exclude<
                (typeof employmentStatusValues)[number],
                'active'
            > => s !== 'active',
        );
    },
);

/** ISO `Y-m-d` → `employee_employments.hire_date` */
const employmentHireDate = ref('');
/** ISO `Y-m-d` or empty → optional `employee_employments.separation_date` */
const employmentSeparationDate = ref('');
/** Optional separation reason when separation date is present. */
const employmentSeparationReason = ref('');
/** Optional employment note when separation date is present. */
const employmentNotes = ref('');
/** → `employee_employments.employment_status` */
const employmentStatus = ref<(typeof employmentStatusValues)[number]>('active');

watch(employmentSeparationDate, (value) => {
    const separationIso = value.trim();
    if (separationIso === '') {
        employmentStatus.value = 'active';
        employmentSeparationReason.value = '';
        employmentNotes.value = '';
        return;
    }

    if (employmentStatus.value === 'active') {
        employmentStatus.value = 'resigned';
    }

    employeePositionRows.forEach((row) => {
        if (
            row.positionId !== '' &&
            row.startDate.trim() !== '' &&
            row.endDate.trim() === '' &&
            employeePositionIsoDateCompare(row.startDate, separationIso) <= 0
        ) {
            row.endDate = separationIso;
        }
    });

    employeeAffiliationRows.forEach((row) => {
        if (
            row.startDate.trim() !== '' &&
            row.endDate.trim() === '' &&
            employeePositionIsoDateCompare(row.startDate, separationIso) <= 0
        ) {
            row.endDate = separationIso;
        }
    });
});

/** Upper bound for affiliation / position calendars when separation is set. */
const employmentSeparationCalendarMax = computed((): DateValue | undefined =>
    employeePositionRowCalendarValue(employmentSeparationDate.value.trim()),
);

/** Lower bound for affiliation / position start pickers vs hire date. */
const employmentHireCalendarMin = computed((): DateValue | undefined =>
    employeePositionRowCalendarValue(employmentHireDate.value.trim()),
);

function stepThreePositionEndCalendarMin(
    row: StepThreeEmployeePositionRow,
): DateValue | undefined {
    return stepThreeRowEndCalendarMinFromHireStart(
        employmentHireDate.value,
        row.startDate,
    );
}

function stepThreeAffiliationEndCalendarMin(
    row: StepThreeEmployeeAffiliationRow,
): DateValue | undefined {
    return stepThreeRowEndCalendarMinFromHireStart(
        employmentHireDate.value,
        row.startDate,
    );
}

/** Default row for Employment → Affiliations repeater (not persisted until employee store exists). */
function createEmptyEmployeeAffiliation(): StepThreeEmployeeAffiliationRow {
    return {
        rootUnitId: '',
        startDate: '',
        endDate: '',
        isPrimary: false,
    };
}

/**
 * Repeater state for step 3 → future `employee_affiliations` many-create on submit.
 * `rootUnitId` empty = org-wide (`root_unit_id` null); `organization_id` comes from hidden `affiliationOrganization`.
 */
const employeeAffiliationRows = reactive<StepThreeEmployeeAffiliationRow[]>([
    createEmptyEmployeeAffiliation(),
]);

function addEmployeeAffiliation(): void {
    employeeAffiliationRows.push(createEmptyEmployeeAffiliation());
}

/** At least one row remains (same guard as employee positions). */
function removeEmployeeAffiliation(index: number): void {
    if (employeeAffiliationRows.length <= 1) {
        return;
    }

    employeeAffiliationRows.splice(index, 1);
}

/** At most one affiliation row may be primary (independent of position primary). */
function setPrimaryEmployeeAffiliation(index: number, checked: unknown): void {
    const shouldBePrimary = Boolean(checked);

    employeeAffiliationRows.forEach((row, rowIndex) => {
        row.isPrimary = shouldBePrimary && rowIndex === index;
    });
}

/** Calendar `model-value` from stored ISO date string for affiliation row dates. */
function employeeAffiliationRowCalendarValue(
    iso: string,
): DateValue | undefined {
    if (iso.trim() === '') {
        return undefined;
    }
    try {
        return parseDate(iso) as DateValue;
    } catch {
        return undefined;
    }
}

/** Button label for affiliation row date pickers (ISO → localized). */
function employeeAffiliationIsoDisplay(iso: string): string {
    const value = employeeAffiliationRowCalendarValue(iso);
    if (value === undefined) {
        return '';
    }
    if (!('toDate' in value) || typeof value.toDate !== 'function') {
        return '';
    }

    return formatCalendarTriggerFromDate(value.toDate(getLocalTimeZone()));
}

function employmentPeriodRowCalendarValue(iso: string): DateValue | undefined {
    return employeeAffiliationRowCalendarValue(iso);
}

function employmentPeriodIsoDisplay(iso: string): string {
    return employeeAffiliationIsoDisplay(iso);
}

function onEmploymentHireSelect(value: unknown, close: () => void): void {
    if (!value || Array.isArray(value)) {
        employmentHireDate.value = '';
        close();

        return;
    }
    if (typeof value !== 'object' || value === null) {
        employmentHireDate.value = '';
        close();

        return;
    }
    if (
        !('toDate' in value) ||
        typeof (value as { toDate?: unknown }).toDate !== 'function'
    ) {
        employmentHireDate.value = '';
        close();

        return;
    }

    employmentHireDate.value = dateValueToIsoDate(value as DateValue);
    close();
}

function clearEmploymentHireDate(): void {
    employmentHireDate.value = '';
}

function onEmploymentSeparationSelect(value: unknown, close: () => void): void {
    if (!value || Array.isArray(value)) {
        employmentSeparationDate.value = '';
        close();

        return;
    }
    if (typeof value !== 'object' || value === null) {
        employmentSeparationDate.value = '';
        close();

        return;
    }
    if (
        !('toDate' in value) ||
        typeof (value as { toDate?: unknown }).toDate !== 'function'
    ) {
        employmentSeparationDate.value = '';
        close();

        return;
    }

    employmentSeparationDate.value = dateValueToIsoDate(value as DateValue);
    close();
}

function clearEmploymentSeparationDate(): void {
    employmentSeparationDate.value = '';
}

function onEmployeeAffiliationRowStartSelect(
    index: number,
    value: unknown,
    close: () => void,
): void {
    const row = employeeAffiliationRows[index];
    if (row === undefined) {
        close();

        return;
    }
    if (!value || Array.isArray(value)) {
        row.startDate = '';
        close();

        return;
    }
    if (typeof value !== 'object' || value === null) {
        row.startDate = '';
        close();

        return;
    }
    if (
        !('toDate' in value) ||
        typeof (value as { toDate?: unknown }).toDate !== 'function'
    ) {
        row.startDate = '';
        close();

        return;
    }

    row.startDate = dateValueToIsoDate(value as DateValue);
    if (
        row.endDate.trim() !== '' &&
        employeePositionIsoDateCompare(row.endDate, row.startDate) < 0
    ) {
        row.endDate = '';
    }
    const hireIsoAff = employmentHireDate.value.trim();
    if (
        row.endDate.trim() !== '' &&
        hireIsoAff !== '' &&
        employeePositionIsoDateCompare(row.endDate, hireIsoAff) < 0
    ) {
        row.endDate = '';
    }
    const sepIsoAff = employmentSeparationDate.value.trim();
    if (
        row.endDate.trim() !== '' &&
        sepIsoAff !== '' &&
        employeePositionIsoDateCompare(row.endDate, sepIsoAff) > 0
    ) {
        row.endDate = '';
    }
    close();
}

function onEmployeeAffiliationRowEndSelect(
    index: number,
    value: unknown,
    close: () => void,
): void {
    const row = employeeAffiliationRows[index];
    if (row === undefined) {
        close();

        return;
    }
    if (!value || Array.isArray(value)) {
        row.endDate = '';
        close();

        return;
    }
    if (typeof value !== 'object' || value === null) {
        row.endDate = '';
        close();

        return;
    }
    if (
        !('toDate' in value) ||
        typeof (value as { toDate?: unknown }).toDate !== 'function'
    ) {
        row.endDate = '';
        close();

        return;
    }

    const candidateAff = dateValueToIsoDate(value as DateValue);
    if (row.startDate.trim() === '') {
        close();

        return;
    }
    if (employeePositionIsoDateCompare(candidateAff, row.startDate) < 0) {
        close();

        return;
    }
    const hireIsoEnd = employmentHireDate.value.trim();
    if (
        hireIsoEnd !== '' &&
        employeePositionIsoDateCompare(candidateAff, hireIsoEnd) < 0
    ) {
        close();

        return;
    }
    const sepIsoEnd = employmentSeparationDate.value.trim();
    if (
        sepIsoEnd !== '' &&
        employeePositionIsoDateCompare(candidateAff, sepIsoEnd) > 0
    ) {
        close();

        return;
    }

    row.endDate = candidateAff;
    close();
}

function clearEmployeeAffiliationRowStart(index: number): void {
    const row = employeeAffiliationRows[index];
    if (row !== undefined) {
        row.startDate = '';
    }
}

function clearEmployeeAffiliationRowEnd(index: number): void {
    const row = employeeAffiliationRows[index];
    if (row !== undefined) {
        row.endDate = '';
    }
}

/** Group root units by `group_label` (stable: first-seen label order). */
const affiliationRootsByGroup = computed(
    (): { group_label: string; items: AffiliationRootOption[] }[] => {
        const roots = affiliationRoots.value;
        const map = new Map<string, AffiliationRootOption[]>();
        const order: string[] = [];

        for (const r of roots) {
            if (!map.has(r.group_label)) {
                map.set(r.group_label, []);
                order.push(r.group_label);
            }
            map.get(r.group_label)?.push(r);
        }

        return order.map((key) => ({
            group_label: key,
            items: map.get(key) ?? [],
        }));
    },
);

watch(
    employeeAffiliationRows,
    () => {
        const hasSeparation = employmentSeparationDate.value.trim() !== '';
        employeeAffiliationRows.forEach((row) => {
            if (!hasSeparation && row.endDate.trim() !== '') {
                row.isPrimary = false;
            }
        });
    },
    { deep: true },
);

const stepOneAttempted = ref(false);
const birthdateDisplay = computed(() => {
    const selectedBirthdate = personalInfo.birthdate;
    if (!selectedBirthdate) {
        return '';
    }
    if (
        !('toDate' in selectedBirthdate) ||
        typeof selectedBirthdate.toDate !== 'function'
    ) {
        return '';
    }

    return formatCalendarTriggerFromDate(
        selectedBirthdate.toDate(getLocalTimeZone()),
    );
});

watch(
    () => personalInfo.religion,
    (value) => {
        if (value !== 'Other') {
            personalInfo.religionOther = '';
        }
    },
);

type StepOneFieldInvalid = {
    firstName: boolean;
    lastName: boolean;
    birthdate: boolean;
    sex: boolean;
    religionOther: boolean;
    addressLine1: boolean;
    provinceCode: boolean;
    cityCode: boolean;
    barangayCode: boolean;
    zipCode: boolean;
};

const stepOneFieldInvalid = computed((): StepOneFieldInvalid => {
    const attempted = stepOneAttempted.value;
    const requiresPermanentAddress = attempted && showPermanentAddress.value;

    return {
        firstName: attempted && personalInfo.firstName.trim() === '',
        lastName: attempted && personalInfo.lastName.trim() === '',
        birthdate: attempted && personalInfo.birthdate == null,
        sex: attempted && personalInfo.sex.trim() === '',
        religionOther:
            attempted &&
            personalInfo.religion === 'Other' &&
            personalInfo.religionOther.trim() === '',
        addressLine1:
            requiresPermanentAddress &&
            permanentAddress.addressLine1.trim() === '',
        provinceCode:
            requiresPermanentAddress && permanentAddress.provinceCode === '',
        cityCode: requiresPermanentAddress && permanentAddress.cityCode === '',
        barangayCode:
            requiresPermanentAddress && permanentAddress.barangayCode === '',
        zipCode:
            requiresPermanentAddress && permanentAddress.zipCode.trim() === '',
    };
});

const canProceedStepOne = computed(() => {
    const v = stepOneFieldInvalid.value;

    return !(
        v.firstName ||
        v.lastName ||
        v.birthdate ||
        v.sex ||
        v.religionOther ||
        v.addressLine1 ||
        v.provinceCode ||
        v.cityCode ||
        v.barangayCode ||
        v.zipCode
    );
});

const stepTwoPersonalContactRowInvalid = computed(
    (): { type: boolean; contactNumber: boolean }[] => {
        const attempted = stepTwoAttempted.value;

        return personalContacts.map((c) => ({
            type: attempted && c.type.trim() === '',
            contactNumber: attempted && c.contactNumber.trim() === '',
        }));
    },
);

const stepTwoPersonalMissingPrimary = computed(
    (): boolean =>
        stepTwoAttempted.value && !personalContacts.some((c) => c.isPrimary),
);

const stepTwoCurrentAddressInvalid = computed(
    (): {
        addressLine1: boolean;
        provinceCode: boolean;
        cityCode: boolean;
        barangayCode: boolean;
        zipCode: boolean;
    } => {
        const requires = stepTwoAttempted.value && showCurrentAddress.value;

        return {
            addressLine1: requires && currentAddress.addressLine1.trim() === '',
            provinceCode: requires && currentAddress.provinceCode === '',
            cityCode: requires && currentAddress.cityCode === '',
            barangayCode: requires && currentAddress.barangayCode === '',
            zipCode: requires && currentAddress.zipCode.trim() === '',
        };
    },
);

const stepTwoEmergencyContactRowInvalid = computed(
    (): {
        contactPerson: boolean;
        relationship: boolean;
        contactNumber: boolean;
    }[] => {
        const requires = stepTwoAttempted.value && showEmergencyContacts.value;

        return emergencyContacts.map((c) => ({
            contactPerson: requires && c.contactPerson.trim() === '',
            relationship: requires && c.relationship.trim() === '',
            contactNumber: requires && c.contactNumber.trim() === '',
        }));
    },
);

const canProceedStepTwo = computed((): boolean => {
    if (
        stepTwoPersonalContactRowInvalid.value.some(
            (row) => row.type || row.contactNumber,
        )
    ) {
        return false;
    }
    if (stepTwoPersonalMissingPrimary.value) {
        return false;
    }

    const cur = stepTwoCurrentAddressInvalid.value;
    if (
        cur.addressLine1 ||
        cur.provinceCode ||
        cur.cityCode ||
        cur.barangayCode ||
        cur.zipCode
    ) {
        return false;
    }

    if (
        stepTwoEmergencyContactRowInvalid.value.some(
            (row) => row.contactPerson || row.relationship || row.contactNumber,
        )
    ) {
        return false;
    }

    return true;
});

const stepThreeCatalogHasPositions = computed(
    (): boolean => positions.value.length > 0,
);

const stepThreeEmployeePositionRowInvalid = computed(
    (): {
        positionId: boolean;
        startDate: boolean;
        endDate: boolean;
        startBeforeHire: boolean;
        endBeforeHire: boolean;
        startVersusSeparation: boolean;
        endVersusSeparation: boolean;
    }[] => {
        const attempted = stepThreeAttempted.value;
        const requireFields = attempted && stepThreeCatalogHasPositions.value;
        const hireIso = employmentHireDate.value.trim();
        const sepIso = employmentSeparationDate.value.trim();

        return employeePositionRows.map((row) => {
            const start = row.startDate.trim();
            const end = row.endDate.trim();
            const rowActive = requireFields && row.positionId !== '';
            const startBeforeHire =
                rowActive && hireIso !== '' && start !== '' && start < hireIso;
            const endBeforeHire =
                rowActive && hireIso !== '' && end !== '' && end < hireIso;
            const startVersusSeparation =
                rowActive && sepIso !== '' && start !== '' && start > sepIso;
            const endVersusSeparation =
                rowActive && sepIso !== '' && end !== '' && end > sepIso;

            return {
                positionId: requireFields && row.positionId === '',
                startDate: requireFields && start === '',
                endDate:
                    requireFields &&
                    sepIso !== '' &&
                    row.positionId !== '' &&
                    end === '',
                startBeforeHire,
                endBeforeHire,
                startVersusSeparation,
                endVersusSeparation,
            };
        });
    },
);

const stepThreePositionMissingPrimary = computed((): boolean => {
    if (!stepThreeAttempted.value || !stepThreeCatalogHasPositions.value) {
        return false;
    }

    return !employeePositionRows.some((r) => r.isPrimary);
});

const stepThreePositionMissingActive = computed((): boolean => {
    if (!stepThreeAttempted.value || !stepThreeCatalogHasPositions.value) {
        return false;
    }
    if (employmentSeparationDate.value.trim() !== '') {
        return false;
    }

    return !employeePositionRows.some(
        (row) =>
            row.positionId !== '' &&
            row.startDate.trim() !== '' &&
            row.endDate.trim() === '',
    );
});

const stepThreeAffiliationRowInvalid = computed(
    (): {
        startDate: boolean;
        endDate: boolean;
        startBeforeHire: boolean;
        endBeforeHire: boolean;
        startVersusSeparation: boolean;
        endVersusSeparation: boolean;
    }[] => {
        const attempted = stepThreeAttempted.value;
        const requires = attempted && affiliationOrganization.value !== null;
        const hireIso = employmentHireDate.value.trim();
        const sepIso = employmentSeparationDate.value.trim();

        return employeeAffiliationRows.map((row) => {
            const start = row.startDate.trim();
            const end = row.endDate.trim();

            return {
                startDate: requires && start === '',
                endDate: requires && sepIso !== '' && end === '',
                startBeforeHire:
                    requires &&
                    hireIso !== '' &&
                    start !== '' &&
                    start < hireIso,
                endBeforeHire:
                    requires && hireIso !== '' && end !== '' && end < hireIso,
                startVersusSeparation:
                    requires && sepIso !== '' && start !== '' && start > sepIso,
                endVersusSeparation:
                    requires && sepIso !== '' && end !== '' && end > sepIso,
            };
        });
    },
);

const stepThreeAffiliationMissingPrimary = computed((): boolean => {
    if (!stepThreeAttempted.value || affiliationOrganization.value === null) {
        return false;
    }

    return !employeeAffiliationRows.some((r) => r.isPrimary);
});

const stepThreeAffiliationMissingActive = computed((): boolean => {
    if (!stepThreeAttempted.value || affiliationOrganization.value === null) {
        return false;
    }
    if (employmentSeparationDate.value.trim() !== '') {
        return false;
    }

    return !employeeAffiliationRows.some(
        (row) => row.startDate.trim() !== '' && row.endDate.trim() === '',
    );
});

const stepThreeIdNumberInvalid = computed(
    (): boolean =>
        stepThreeAttempted.value && employmentIdNumber.value.trim() === '',
);

const stepThreeEmploymentHireInvalid = computed(
    (): boolean =>
        stepThreeAttempted.value && employmentHireDate.value.trim() === '',
);

const stepThreeEmploymentSeparationBeforeHireInvalid = computed((): boolean => {
    if (!stepThreeAttempted.value) {
        return false;
    }
    const hire = employmentHireDate.value.trim();
    const sep = employmentSeparationDate.value.trim();
    if (hire === '' || sep === '') {
        return false;
    }

    return sep < hire;
});

const stepThreeEmploymentStatusLogicInvalid = computed((): boolean => {
    if (!stepThreeAttempted.value) {
        return false;
    }
    const hasSeparation = employmentSeparationDate.value.trim() !== '';

    if (!hasSeparation) {
        return employmentStatus.value !== 'active';
    }

    return employmentStatus.value === 'active';
});

type AvailabilityStatus =
    | 'idle'
    | 'checking'
    | 'available'
    | 'taken'
    | 'invalid';
type AvailabilityState = {
    status: AvailabilityStatus;
    message: string;
};

const idNumberAvailability = ref<AvailabilityState>({
    status: 'idle',
    message: '',
});
const attendanceIdAvailability = ref<AvailabilityState>({
    status: 'idle',
    message: '',
});
const emailAvailability = ref<AvailabilityState>({
    status: 'idle',
    message: '',
});

let idNumberCheckTimer: ReturnType<typeof setTimeout> | null = null;
let attendanceIdCheckTimer: ReturnType<typeof setTimeout> | null = null;
let emailCheckTimer: ReturnType<typeof setTimeout> | null = null;

let idNumberAbortController: AbortController | null = null;
let attendanceIdAbortController: AbortController | null = null;
let emailAbortController: AbortController | null = null;

const availabilityStatusClassByStatus: Record<AvailabilityStatus, string> = {
    idle: 'text-muted-foreground',
    checking: 'text-muted-foreground',
    available: 'text-green-600 dark:text-green-400',
    taken: 'text-destructive',
    invalid: 'text-destructive',
};

function availabilityStatusClass(status: AvailabilityStatus): string {
    return availabilityStatusClassByStatus[status];
}

async function checkFieldAvailability(
    payload: { id_number?: string; attendance_id?: string; email?: string },
    target: { value: AvailabilityState },
    getController: () => AbortController | null,
    setController: (controller: AbortController | null) => void,
): Promise<void> {
    getController()?.abort();
    const nextController = new AbortController();
    setController(nextController);
    target.value = { status: 'checking', message: 'Checking availability...' };

    const params = new URLSearchParams();
    if (payload.id_number !== undefined) {
        params.set('id_number', payload.id_number);
    }
    if (payload.attendance_id !== undefined) {
        params.set('attendance_id', payload.attendance_id);
    }
    if (payload.email !== undefined) {
        params.set('email', payload.email);
    }
    const query = params.toString();
    try {
        const response = await fetch(`/employees/check-availability?${query}`, {
            method: 'GET',
            headers: { Accept: 'application/json' },
            signal: nextController.signal,
        });
        if (!response.ok) {
            target.value = {
                status: 'invalid',
                message: 'Could not verify right now.',
            };

            return;
        }

        const data = (await response.json()) as {
            id_number?: AvailabilityState;
            attendance_id?: AvailabilityState;
            email?: AvailabilityState;
        };
        if (
            Object.prototype.hasOwnProperty.call(payload, 'id_number') &&
            data.id_number
        ) {
            target.value = data.id_number;
            return;
        }
        if (
            Object.prototype.hasOwnProperty.call(payload, 'attendance_id') &&
            data.attendance_id
        ) {
            target.value = data.attendance_id;
            return;
        }
        if (
            Object.prototype.hasOwnProperty.call(payload, 'email') &&
            data.email
        ) {
            target.value = data.email;
            return;
        }

        target.value = {
            status: 'invalid',
            message: 'Could not verify right now.',
        };
    } catch (error) {
        if ((error as { name?: string }).name === 'AbortError') {
            return;
        }
        target.value = {
            status: 'invalid',
            message: 'Could not verify right now.',
        };
    } finally {
        setController(null);
    }
}

const canProceedStepThree = computed((): boolean => {
    if (employmentIdNumber.value.trim() === '') {
        return false;
    }
    if (employmentHireDate.value.trim() === '') {
        return false;
    }
    const hireIso = employmentHireDate.value.trim();
    const sepIso = employmentSeparationDate.value.trim();
    if (sepIso !== '' && hireIso !== '' && sepIso < hireIso) {
        return false;
    }
    const hasSeparation = sepIso !== '';
    if (!hasSeparation && employmentStatus.value !== 'active') {
        return false;
    }
    if (hasSeparation && employmentStatus.value === 'active') {
        return false;
    }
    if (idNumberAvailability.value.status === 'checking') {
        return false;
    }
    if (idNumberAvailability.value.status === 'taken') {
        return false;
    }
    if (employmentAttendanceId.value.trim() !== '') {
        if (attendanceIdAvailability.value.status === 'checking') {
            return false;
        }
        if (attendanceIdAvailability.value.status === 'taken') {
            return false;
        }
    }

    if (stepThreeCatalogHasPositions.value) {
        for (const row of employeePositionRows) {
            if (row.positionId === '' || row.startDate.trim() === '') {
                return false;
            }
            const pStart = row.startDate.trim();
            const pEnd = row.endDate.trim();
            if (pStart < hireIso || (pEnd !== '' && pEnd < hireIso)) {
                return false;
            }
            if (sepIso !== '') {
                if (pEnd === '') {
                    return false;
                }
                if (pStart > sepIso || pEnd > sepIso) {
                    return false;
                }
            }
        }
        if (!employeePositionRows.some((r) => r.isPrimary)) {
            return false;
        }
        if (
            !hasSeparation &&
            !employeePositionRows.some(
                (row) =>
                    row.positionId !== '' &&
                    row.startDate.trim() !== '' &&
                    row.endDate.trim() === '',
            )
        ) {
            return false;
        }
    }

    if (affiliationOrganization.value !== null) {
        for (const row of employeeAffiliationRows) {
            if (row.startDate.trim() === '') {
                return false;
            }
            const aStart = row.startDate.trim();
            const aEnd = row.endDate.trim();
            if (aStart < hireIso || (aEnd !== '' && aEnd < hireIso)) {
                return false;
            }
            if (sepIso !== '') {
                if (aEnd === '') {
                    return false;
                }
                if (aStart > sepIso || aEnd > sepIso) {
                    return false;
                }
            }
        }
        if (!employeeAffiliationRows.some((r) => r.isPrimary)) {
            return false;
        }
        if (
            !hasSeparation &&
            !employeeAffiliationRows.some(
                (row) =>
                    row.startDate.trim() !== '' && row.endDate.trim() === '',
            )
        ) {
            return false;
        }
    }

    return true;
});

/** Step 4 → future `users.email` / `users.password` (confirmed); `users.name` from step 1 names. */
type StepFourUserAccount = {
    email: string;
    password: string;
    passwordConfirmation: string;
};

const userAccount = reactive<StepFourUserAccount>({
    email: '',
    password: '',
    passwordConfirmation: '',
});

/** When false, the employee is saved without a `users` row or credentials. */
const createUserAccount = ref(false);

const stepFourAttempted = ref(false);

const showStepFourPassword = ref(false);

const showStepFourPasswordConfirmation = ref(false);
const stepFourAvatarInputRef = ref<HTMLInputElement | null>(null);
const stepFourAvatarFile = ref<File | null>(null);
const stepFourAvatarPreviewUrl = ref<string | null>(null);
const stepFourAvatarError = ref('');
const stepFourAvatarMaxBytes = 3 * 1024 * 1024;
const stepFourAvatarAllowedMimeTypes = new Set([
    'image/jpeg',
    'image/png',
    'image/webp',
]);
const stepFourAvatarHintText = 'JPG, PNG, or WEBP up to 3MB.';
const { getInitials } = useInitials();

/** Future `users.name`: first + last from Personal Information (trimmed). */
const userAccountDisplayName = computed((): string => {
    const first = personalInfo.firstName.trim();
    const last = personalInfo.lastName.trim();
    if (first === '' && last === '') {
        return '';
    }

    return `${first} ${last}`.trim();
});

function resetStepFourAvatarState(clearNativeInput: boolean): void {
    if (stepFourAvatarPreviewUrl.value !== null) {
        URL.revokeObjectURL(stepFourAvatarPreviewUrl.value);
    }

    stepFourAvatarFile.value = null;
    stepFourAvatarPreviewUrl.value = null;
    stepFourAvatarError.value = '';
    if (clearNativeInput && stepFourAvatarInputRef.value !== null) {
        stepFourAvatarInputRef.value.value = '';
    }
}

function clearStepFourAvatarSelection(): void {
    resetStepFourAvatarState(true);
}

function onStepFourAvatarChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const selected = input.files?.[0] ?? null;
    resetStepFourAvatarState(false);

    if (selected === null) {
        return;
    }

    if (!stepFourAvatarAllowedMimeTypes.has(selected.type)) {
        stepFourAvatarError.value = 'Use JPG, PNG, or WEBP format only.';
        input.value = '';

        return;
    }

    if (selected.size > stepFourAvatarMaxBytes) {
        stepFourAvatarError.value = 'Image must be 3MB or smaller.';
        input.value = '';

        return;
    }

    stepFourAvatarFile.value = selected;
    stepFourAvatarPreviewUrl.value = URL.createObjectURL(selected);
}

/** Minimal email shape check; server will apply full rules later. */
const stepFourEmailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * Client-side password policy (ASCII upper/lower/digit + length). Align `StoreEmployeeRequest`
 * with Laravel `Password` or equivalent when the store endpoint exists; Unicode letters are not counted here.
 */
const stepFourPasswordPolicyChecks = computed(
    (): {
        minLength: boolean;
        hasUpper: boolean;
        hasLower: boolean;
        hasDigit: boolean;
    } => {
        const p = userAccount.password;

        return {
            minLength: p.length >= 8,
            hasUpper: /[A-Z]/.test(p),
            hasLower: /[a-z]/.test(p),
            hasDigit: /\d/.test(p),
        };
    },
);

const stepFourPasswordMeetsPolicy = computed((): boolean => {
    const c = stepFourPasswordPolicyChecks.value;

    return c.minLength && c.hasUpper && c.hasLower && c.hasDigit;
});

/** Non-empty password equals confirmation (no success state when both empty). */
const stepFourPasswordsMatch = computed((): boolean => {
    const p = userAccount.password;

    return p !== '' && p === userAccount.passwordConfirmation;
});

/**
 * Confirm field is invalid when passwords differ and either the user typed in confirm
 * (real-time) or they already tried to complete the wizard.
 */
const stepFourPasswordConfirmationInvalid = computed((): boolean => {
    if (userAccount.password === userAccount.passwordConfirmation) {
        return false;
    }

    if (stepFourAttempted.value) {
        return true;
    }

    return userAccount.passwordConfirmation.trim() !== '';
});

/** Border/ring: success when match; destructive when mismatch (real-time or post-attempt). */
const stepFourPasswordConfirmationInputClass = computed((): string => {
    if (stepFourPasswordsMatch.value) {
        return 'border-green-600 focus-visible:border-green-600 focus-visible:ring-green-600/30 dark:border-green-500 dark:focus-visible:border-green-500 dark:focus-visible:ring-green-500/30';
    }
    if (stepFourPasswordConfirmationInvalid.value) {
        return 'border-destructive focus-visible:border-destructive focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40';
    }

    return '';
});

const stepFourFieldInvalid = computed(() => {
    if (!createUserAccount.value) {
        return {
            email: false,
            password: false,
            passwordConfirmation: false,
            displayName: false,
        };
    }

    const attempted = stepFourAttempted.value;
    const emailTrim = userAccount.email.trim();

    return {
        email:
            attempted &&
            (emailTrim === '' || !stepFourEmailPattern.test(emailTrim)),
        password: attempted && !stepFourPasswordMeetsPolicy.value,
        passwordConfirmation: stepFourPasswordConfirmationInvalid.value,
        displayName: attempted && userAccountDisplayName.value === '',
    };
});

watch(employmentIdNumber, (value) => {
    if (idNumberCheckTimer !== null) {
        clearTimeout(idNumberCheckTimer);
    }
    const trimmed = value.trim();
    if (trimmed === '') {
        idNumberAbortController?.abort();
        idNumberAvailability.value = { status: 'idle', message: '' };
        return;
    }

    idNumberCheckTimer = setTimeout(() => {
        void checkFieldAvailability(
            { id_number: trimmed },
            idNumberAvailability,
            () => idNumberAbortController,
            (controller) => {
                idNumberAbortController = controller;
            },
        );
    }, 350);
});

watch(employmentAttendanceId, (value) => {
    if (attendanceIdCheckTimer !== null) {
        clearTimeout(attendanceIdCheckTimer);
    }
    const trimmed = value.trim();
    if (trimmed === '') {
        attendanceIdAbortController?.abort();
        attendanceIdAvailability.value = { status: 'idle', message: '' };
        return;
    }

    attendanceIdCheckTimer = setTimeout(() => {
        void checkFieldAvailability(
            { attendance_id: trimmed },
            attendanceIdAvailability,
            () => attendanceIdAbortController,
            (controller) => {
                attendanceIdAbortController = controller;
            },
        );
    }, 350);
});

watch([createUserAccount, () => userAccount.email], ([enabled, emailValue]) => {
    if (emailCheckTimer !== null) {
        clearTimeout(emailCheckTimer);
        emailCheckTimer = null;
    }

    if (!enabled) {
        emailAbortController?.abort();
        emailAvailability.value = { status: 'idle', message: '' };

        return;
    }

    const trimmed = emailValue.trim();
    if (trimmed === '') {
        emailAbortController?.abort();
        emailAvailability.value = { status: 'idle', message: '' };

        return;
    }
    if (!stepFourEmailPattern.test(trimmed)) {
        emailAbortController?.abort();
        emailAvailability.value = {
            status: 'invalid',
            message: 'Enter a valid email address.',
        };

        return;
    }

    emailCheckTimer = setTimeout(() => {
        void checkFieldAvailability(
            { email: trimmed },
            emailAvailability,
            () => emailAbortController,
            (controller) => {
                emailAbortController = controller;
            },
        );
    }, 350);
});

watch(createUserAccount, (enabled) => {
    if (enabled) {
        return;
    }

    clearStepFourAvatarSelection();
    userAccount.email = '';
    userAccount.password = '';
    userAccount.passwordConfirmation = '';
    emailAvailability.value = { status: 'idle', message: '' };
    stepFourAttempted.value = false;
});

const canCompleteStepFour = computed((): boolean => {
    if (!createUserAccount.value) {
        return true;
    }

    const emailTrim = userAccount.email.trim();
    if (emailTrim === '' || !stepFourEmailPattern.test(emailTrim)) {
        return false;
    }
    if (emailAvailability.value.status === 'checking') {
        return false;
    }
    if (
        emailAvailability.value.status === 'taken' ||
        emailAvailability.value.status === 'invalid'
    ) {
        return false;
    }
    if (!stepFourPasswordMeetsPolicy.value) {
        return false;
    }
    if (userAccount.password !== userAccount.passwordConfirmation) {
        return false;
    }
    if (userAccountDisplayName.value === '') {
        return false;
    }

    return true;
});

function completeButtonTitle(): string {
    if (canCompleteStepFour.value) {
        return createUserAccount.value
            ? 'Save employee and sign-in account'
            : 'Save employee record without a sign-in account';
    }

    return 'Fill in all required fields to continue';
}

function onWizardCompleteClick(): void {
    if (!canCompleteStepFour.value) {
        stepFourAttempted.value = true;

        return;
    }

    const payload = {
        personal_info: {
            first_name: personalInfo.firstName.trim(),
            last_name: personalInfo.lastName.trim(),
            middle_name:
                personalInfo.middleName.trim() === ''
                    ? null
                    : personalInfo.middleName.trim(),
            suffix:
                personalInfo.suffix.trim() === ''
                    ? null
                    : personalInfo.suffix.trim(),
            birthdate:
                personalInfo.birthdate == null
                    ? null
                    : dateValueToIsoDate(personalInfo.birthdate as DateValue),
            sex: personalInfo.sex,
            civil_status: personalInfo.civilStatus,
            nationality: personalInfo.nationality,
            religion:
                personalInfo.religion === 'Other'
                    ? personalInfo.religionOther.trim()
                    : personalInfo.religion,
        },
        employment: {
            id_number: employmentIdNumber.value.trim(),
            attendance_id:
                employmentAttendanceId.value.trim() === ''
                    ? null
                    : employmentAttendanceId.value.trim(),
            hire_date: employmentHireDate.value.trim(),
            separation_date:
                employmentSeparationDate.value.trim() === ''
                    ? null
                    : employmentSeparationDate.value.trim(),
            separation_reason:
                employmentSeparationReason.value.trim() === ''
                    ? null
                    : employmentSeparationReason.value.trim(),
            employment_status: employmentStatus.value,
            notes:
                employmentNotes.value.trim() === ''
                    ? null
                    : employmentNotes.value.trim(),
            affiliations: employeeAffiliationRows.map((row) => ({
                root_unit_id:
                    row.rootUnitId === '' ||
                    row.rootUnitId === employeeAffiliationRootNoneValue
                        ? null
                        : Number(row.rootUnitId),
                start_date: row.startDate,
                end_date: row.endDate.trim() === '' ? null : row.endDate,
                is_primary: row.isPrimary,
            })),
            positions: employeePositionRows
                .filter((row) => row.positionId !== '')
                .map((row) => ({
                    position_id: Number(row.positionId),
                    start_date: row.startDate,
                    end_date: row.endDate.trim() === '' ? null : row.endDate,
                    is_primary: row.isPrimary,
                })),
        },
        addresses: [
            {
                type: 'permanent',
                address_line_1: permanentAddress.addressLine1.trim(),
                address_line_2:
                    permanentAddress.addressLine2.trim() === ''
                        ? null
                        : permanentAddress.addressLine2.trim(),
                barangay: permanentAddress.barangay,
                barangay_code:
                    permanentAddress.barangayCode === ''
                        ? null
                        : permanentAddress.barangayCode,
                city: permanentAddress.city,
                city_code:
                    permanentAddress.cityCode === ''
                        ? null
                        : permanentAddress.cityCode,
                province: permanentAddress.province,
                province_code:
                    permanentAddress.provinceCode === ''
                        ? null
                        : permanentAddress.provinceCode,
                zip_code: permanentAddress.zipCode.trim(),
                country: permanentAddress.country,
                is_primary: permanentAddress.isPrimary,
            },
            ...(showCurrentAddress.value
                ? [
                      {
                          type: 'current',
                          address_line_1: currentAddress.addressLine1.trim(),
                          address_line_2:
                              currentAddress.addressLine2.trim() === ''
                                  ? null
                                  : currentAddress.addressLine2.trim(),
                          barangay: currentAddress.barangay,
                          barangay_code:
                              currentAddress.barangayCode === ''
                                  ? null
                                  : currentAddress.barangayCode,
                          city: currentAddress.city,
                          city_code:
                              currentAddress.cityCode === ''
                                  ? null
                                  : currentAddress.cityCode,
                          province: currentAddress.province,
                          province_code:
                              currentAddress.provinceCode === ''
                                  ? null
                                  : currentAddress.provinceCode,
                          zip_code: currentAddress.zipCode.trim(),
                          country: currentAddress.country,
                          is_primary: currentAddress.isPrimary,
                      },
                  ]
                : []),
        ],
        contacts: [
            ...personalContacts.map((contact) => ({
                category: 'personal',
                type: contact.type === '' ? null : contact.type,
                contact_person: null,
                relationship: null,
                contact_number: contact.contactNumber.trim(),
                email:
                    contact.email.trim() === '' ? null : contact.email.trim(),
                is_primary: contact.isPrimary,
            })),
            ...(showEmergencyContacts.value
                ? emergencyContacts.map((contact) => ({
                      category: 'emergency',
                      type: 'mobile',
                      contact_person: contact.contactPerson.trim(),
                      relationship: contact.relationship.trim(),
                      contact_number: contact.contactNumber.trim(),
                      email: null,
                      is_primary: contact.isPrimary,
                  }))
                : []),
        ],
        create_user_account: createUserAccount.value,
        ...(createUserAccount.value
            ? {
                  user_account: {
                      email: userAccount.email.trim(),
                      password: userAccount.password,
                      password_confirmation: userAccount.passwordConfirmation,
                  },
              }
            : {}),
    };

    router.post(
        '/employees',
        {
            ...payload,
            ...(createUserAccount.value && stepFourAvatarFile.value !== null
                ? { avatar: stepFourAvatarFile.value }
                : {}),
        },
        {
            forceFormData:
                createUserAccount.value && stepFourAvatarFile.value !== null,
            onError: () =>
                appToast.error(
                    'Could not save employee. Please review highlighted fields.',
                ),
        },
    );
}

onUnmounted(() => {
    if (idNumberCheckTimer !== null) {
        clearTimeout(idNumberCheckTimer);
    }
    if (attendanceIdCheckTimer !== null) {
        clearTimeout(attendanceIdCheckTimer);
    }
    if (emailCheckTimer !== null) {
        clearTimeout(emailCheckTimer);
    }
    idNumberAbortController?.abort();
    attendanceIdAbortController?.abort();
    emailAbortController?.abort();
    clearStepFourAvatarSelection();
});

/** Label row + badges; `pr-8` reserves space for the absolutely positioned clear control. */
const stepOneLabelRowClass =
    'relative flex min-h-6 flex-wrap items-center gap-x-2 gap-y-1 pr-8';
const clearFieldButtonClass =
    'absolute right-0 top-1/2 z-[1] size-6 shrink-0 -translate-y-1/2 cursor-pointer rounded-md text-muted-foreground hover:bg-muted/60 hover:text-foreground';

/** Single-line ellipsis on small viewports; keep full text in the node for screen readers. */
const maxSmOneLineTruncateClass = 'max-sm:min-w-0 max-sm:truncate';

/** Primary flag row under section headers: checkbox + label + optional inline clear + extending rule. */
const primaryToggleRowClass = 'flex min-w-0 flex-nowrap items-center gap-3';
const primaryToggleRuleClass =
    'min-w-0 flex-1 self-center border-t border-primary/30';

type WizardStepMeta = {
    step: number;
    title: string;
    panelTitle: string;
};

const steps: readonly WizardStepMeta[] = [
    {
        step: 1,
        title: 'Personal Information',
        panelTitle: 'Personal Information',
    },
    { step: 2, title: 'Contact Details', panelTitle: 'Contact Details' },
    { step: 3, title: 'Employment Details', panelTitle: 'Employment Details' },
    { step: 4, title: 'User Account', panelTitle: 'User Account' },
] as const;

/**
 * Styling for the connector after step `afterStep` (between `afterStep` and `afterStep + 1`).
 * Frontier: user is on the step immediately after this segment.
 */
function separatorSegmentClass(currentStep: number, afterStep: number): string {
    const c = currentStep;
    const s = afterStep;
    const base = 'min-w-0 flex-1 shrink self-center rounded-full';
    if (c > s + 1) {
        return `${base} h-1 bg-primary`;
    }
    if (c === s + 1) {
        return `${base} h-1 bg-primary`;
    }

    return `${base} h-px bg-border/70`;
}

/**
 * Step `step` is strictly before the current step and still fails the same gate as **Next**.
 * Step 4 is never "skipped" relative to a later step (no step 5).
 */
function stepperStepSkippedInvalid(step: number): boolean {
    const cur = currentStep.value;
    if (step >= cur) {
        return false;
    }
    if (step === 1) {
        return !canProceedStepOne.value;
    }
    if (step === 2) {
        return !canProceedStepTwo.value;
    }
    if (step === 3) {
        return !canProceedStepThree.value;
    }

    return false;
}

/** Numbered circle: base Reka styles + destructive ring when an earlier step is invalid (not on active). */
function stepperIndicatorClass(step: number): string {
    const base =
        'size-6 shrink-0 text-xs font-semibold group-data-[state=inactive]:border group-data-[state=inactive]:border-border group-data-[state=inactive]:bg-background group-data-[state=inactive]:text-muted-foreground group-data-[state=completed]:bg-primary/90 group-data-[state=completed]:text-primary-foreground group-data-[state=active]:ring-2 group-data-[state=active]:ring-ring group-data-[state=active]:ring-offset-2 group-data-[state=active]:ring-offset-background sm:size-8 sm:text-sm';
    if (!stepperStepSkippedInvalid(step)) {
        return base;
    }

    return cn(
        base,
        'group-data-[state=inactive]:ring-2 group-data-[state=inactive]:ring-destructive group-data-[state=inactive]:ring-offset-2 group-data-[state=inactive]:ring-offset-background',
        'group-data-[state=completed]:ring-2 group-data-[state=completed]:ring-destructive group-data-[state=completed]:ring-offset-2 group-data-[state=completed]:ring-offset-background',
    );
}

/** Forward stepper navigation (including clicks) marks prior steps as attempted so `canProceed*` matches real data. */
watch(currentStep, (newStep, oldStep) => {
    if (oldStep === undefined || newStep <= oldStep) {
        return;
    }
    if (newStep >= 2) {
        stepOneAttempted.value = true;
    }
    if (newStep >= 3) {
        stepTwoAttempted.value = true;
    }
    if (newStep >= 4) {
        stepThreeAttempted.value = true;
    }
});

function handleNextStep(next: () => void, step: number): void {
    if (step === 1) {
        stepOneAttempted.value = true;
        if (!canProceedStepOne.value) {
            return;
        }
    }

    if (step === 2) {
        stepTwoAttempted.value = true;
        if (!canProceedStepTwo.value) {
            return;
        }
    }

    if (step === 3) {
        stepThreeAttempted.value = true;
        if (!canProceedStepThree.value) {
            return;
        }
    }

    next();
}

function toggleCreateUserAccount(): void {
    createUserAccount.value = !createUserAccount.value;
}

function onBirthdateSelect(value: unknown, close: () => void): void {
    if (!value || Array.isArray(value)) {
        personalInfo.birthdate = undefined;

        return;
    }
    if (typeof value !== 'object' || value === null) {
        personalInfo.birthdate = undefined;

        return;
    }
    if (
        !('toDate' in value) ||
        typeof (value as { toDate?: unknown }).toDate !== 'function'
    ) {
        personalInfo.birthdate = undefined;

        return;
    }

    personalInfo.birthdate = value as DateValue;
    close();
}
</script>

<template>
    <Head title="Add Employee" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto rounded-xl p-4 lg:px-16"
        >
            <div class="space-y-1">
                <h1 class="text-xl font-semibold text-foreground">
                    Add Employee
                </h1>
                <p
                    class="max-w-3xl text-sm leading-relaxed text-muted-foreground"
                >
                    Complete each step to capture personal details, employment
                    and assignments, affiliations, and an optional user account.
                </p>
            </div>
            <Stepper v-model="currentStep" class="flex w-full flex-col gap-6">
                <template #default="sp">
                    <div class="flex flex-col gap-4">
                        <section
                            aria-label="Progress steps"
                            class="shrink-0 overflow-visible rounded-lg border border-border/60 bg-muted/90 px-3 py-2.5 sm:px-4 sm:py-3"
                        >
                            <div
                                class="flex w-full min-w-0 flex-row flex-nowrap items-center gap-x-1 overflow-x-auto max-sm:overflow-x-visible sm:items-stretch sm:gap-x-2 sm:overflow-visible"
                            >
                                <StepperItem
                                    v-for="(item, index) in steps"
                                    :key="item.step"
                                    :step="item.step"
                                    :class="[
                                        'flex flex-row items-center gap-1 sm:gap-2',
                                        'max-sm:flex max-sm:min-w-0 max-sm:flex-1 max-sm:basis-0 max-sm:flex-row max-sm:items-center max-sm:gap-0',
                                        index === steps.length - 1
                                            ? 'sm:w-auto sm:shrink-0 sm:justify-end'
                                            : 'min-w-0 flex-1 basis-0',
                                    ]"
                                >
                                    <span
                                        aria-hidden="true"
                                        class="max-sm:min-w-0 max-sm:flex-1 max-sm:shrink sm:hidden"
                                    />
                                    <StepperTrigger
                                        class="flex min-h-10 min-w-0 shrink-0 cursor-pointer flex-row items-center gap-2 rounded-md px-1 py-1 text-left group-data-disabled:cursor-not-allowed hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none max-sm:max-w-none max-sm:justify-center sm:max-w-none sm:justify-start sm:px-2"
                                        :aria-invalid="
                                            stepperStepSkippedInvalid(item.step)
                                                ? true
                                                : undefined
                                        "
                                    >
                                        <StepperIndicator
                                            :class="
                                                stepperIndicatorClass(item.step)
                                            "
                                        >
                                            {{ item.step }}
                                        </StepperIndicator>
                                        <div
                                            class="hidden min-w-0 flex-1 text-left sm:block"
                                        >
                                            <StepperTitle
                                                class="line-clamp-2 text-xs leading-tight text-muted-foreground group-data-[state=active]:font-semibold group-data-[state=active]:text-foreground group-data-[state=completed]:font-medium group-data-[state=completed]:text-foreground group-data-[state=inactive]:text-muted-foreground sm:text-sm"
                                            >
                                                {{ item.title }}
                                            </StepperTitle>
                                        </div>
                                    </StepperTrigger>
                                    <div
                                        v-if="index < steps.length - 1"
                                        class="max-sm:relative max-sm:flex max-sm:min-w-0 max-sm:flex-1 max-sm:items-center max-sm:overflow-visible sm:contents"
                                    >
                                        <StepperSeparator
                                            suppress-default-bar-tint
                                            :class="[
                                                separatorSegmentClass(
                                                    sp.modelValue ?? 1,
                                                    item.step,
                                                ),
                                                'max-sm:w-[calc(100%+clamp(0.75rem,4vw,1.25rem))] max-sm:min-w-0 max-sm:translate-x-[clamp(0.5rem,4vw,1.125rem)] max-sm:self-center',
                                            ]"
                                        />
                                    </div>
                                    <span
                                        v-if="index === steps.length - 1"
                                        aria-hidden="true"
                                        class="max-sm:min-w-0 max-sm:flex-1 max-sm:shrink sm:hidden"
                                    />
                                </StepperItem>
                            </div>
                        </section>

                        <div class="min-w-0">
                            <Card
                                class="flex w-full flex-col gap-y-0 border-border/70 pt-1 pb-1"
                            >
                                <CardContent
                                    :key="currentStep"
                                    class="space-y-2 pt-4 pb-12"
                                >
                                    <p class="sr-only">
                                        Step {{ currentStep }} of
                                        {{ steps.length }}:
                                        {{
                                            steps.find(
                                                (s) => s.step === currentStep,
                                            )?.panelTitle ?? ''
                                        }}
                                    </p>
                                    <section
                                        v-if="currentStep === 1"
                                        aria-label="Personal information fields"
                                        class="space-y-6"
                                    >
                                        <div
                                            class="rounded-lg border border-primary/30 bg-muted/30 p-4 dark:bg-muted/25"
                                        >
                                            <div
                                                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4"
                                            >
                                                <span
                                                    class="inline-flex shrink-0 items-center justify-center rounded-full bg-primary/10 p-3 text-primary"
                                                >
                                                    <UserCircle
                                                        class="size-7 shrink-0"
                                                        aria-hidden="true"
                                                    />
                                                </span>
                                                <div
                                                    class="min-w-0 flex-1 space-y-0.5"
                                                >
                                                    <h2
                                                        class="text-base font-semibold text-foreground"
                                                        :class="
                                                            maxSmOneLineTruncateClass
                                                        "
                                                        title="Personal Details"
                                                    >
                                                        Personal Details
                                                    </h2>
                                                    <p
                                                        class="mt-0 text-sm leading-relaxed text-muted-foreground"
                                                        :class="
                                                            maxSmOneLineTruncateClass
                                                        "
                                                        title="Full legal name, date of birth, sex, and related identity information as they should appear on employment records and official documents."
                                                    >
                                                        Full legal name, date of
                                                        birth, sex, and related
                                                        identity information as
                                                        they should appear on
                                                        employment records and
                                                        official documents.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            class="grid grid-cols-1 gap-x-4 gap-y-6 md:grid-cols-2 lg:grid-cols-3"
                                        >
                                            <div class="grid gap-3">
                                                <Label
                                                    for="first_name"
                                                    :class="
                                                        stepOneLabelRowClass
                                                    "
                                                >
                                                    <span>First name</span>
                                                    <Badge
                                                        v-if="
                                                            stepOneFieldInvalid.firstName
                                                        "
                                                        variant="destructive"
                                                    >
                                                        Required
                                                    </Badge>
                                                    <Button
                                                        v-if="
                                                            personalInfo.firstName !==
                                                            ''
                                                        "
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        :class="
                                                            clearFieldButtonClass
                                                        "
                                                        aria-label="Clear first name"
                                                        @click="
                                                            personalInfo.firstName =
                                                                ''
                                                        "
                                                    >
                                                        <X class="size-3.5" />
                                                    </Button>
                                                </Label>
                                                <Input
                                                    class="w-full"
                                                    id="first_name"
                                                    v-model="
                                                        personalInfo.firstName
                                                    "
                                                    autocomplete="given-name"
                                                    placeholder="e.g. Juan"
                                                    :aria-invalid="
                                                        stepOneFieldInvalid.firstName
                                                    "
                                                />
                                            </div>
                                            <div class="grid gap-3">
                                                <Label
                                                    for="last_name"
                                                    :class="
                                                        stepOneLabelRowClass
                                                    "
                                                >
                                                    <span>Last name</span>
                                                    <Badge
                                                        v-if="
                                                            stepOneFieldInvalid.lastName
                                                        "
                                                        variant="destructive"
                                                    >
                                                        Required
                                                    </Badge>
                                                    <Button
                                                        v-if="
                                                            personalInfo.lastName !==
                                                            ''
                                                        "
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        :class="
                                                            clearFieldButtonClass
                                                        "
                                                        aria-label="Clear last name"
                                                        @click="
                                                            personalInfo.lastName =
                                                                ''
                                                        "
                                                    >
                                                        <X class="size-3.5" />
                                                    </Button>
                                                </Label>
                                                <Input
                                                    class="w-full"
                                                    id="last_name"
                                                    v-model="
                                                        personalInfo.lastName
                                                    "
                                                    autocomplete="family-name"
                                                    placeholder="e.g. Dela Cruz"
                                                    :aria-invalid="
                                                        stepOneFieldInvalid.lastName
                                                    "
                                                />
                                            </div>
                                            <div class="grid gap-3">
                                                <Label
                                                    for="middle_name"
                                                    :class="
                                                        stepOneLabelRowClass
                                                    "
                                                >
                                                    <span>Middle name</span>
                                                    <Badge variant="secondary">
                                                        Optional
                                                    </Badge>
                                                    <Button
                                                        v-if="
                                                            personalInfo.middleName !==
                                                            ''
                                                        "
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        :class="
                                                            clearFieldButtonClass
                                                        "
                                                        aria-label="Clear middle name"
                                                        @click="
                                                            personalInfo.middleName =
                                                                ''
                                                        "
                                                    >
                                                        <X class="size-3.5" />
                                                    </Button>
                                                </Label>
                                                <Input
                                                    class="w-full"
                                                    id="middle_name"
                                                    v-model="
                                                        personalInfo.middleName
                                                    "
                                                    autocomplete="additional-name"
                                                    placeholder="e.g. Santos"
                                                />
                                            </div>
                                            <div class="grid gap-3">
                                                <Label
                                                    for="suffix"
                                                    :class="
                                                        stepOneLabelRowClass
                                                    "
                                                >
                                                    <span>Suffix</span>
                                                    <Badge variant="secondary">
                                                        Optional
                                                    </Badge>
                                                    <Button
                                                        v-if="
                                                            personalInfo.suffix !==
                                                            ''
                                                        "
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        :class="
                                                            clearFieldButtonClass
                                                        "
                                                        aria-label="Clear suffix"
                                                        @click="
                                                            personalInfo.suffix =
                                                                ''
                                                        "
                                                    >
                                                        <X class="size-3.5" />
                                                    </Button>
                                                </Label>
                                                <Select
                                                    v-model="
                                                        personalInfo.suffix
                                                    "
                                                >
                                                    <SelectTrigger
                                                        id="suffix"
                                                        class="w-full min-w-0"
                                                    >
                                                        <SelectValue
                                                            placeholder="Select suffix"
                                                        />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem
                                                            v-for="option in suffixOptions"
                                                            :key="option"
                                                            :value="option"
                                                        >
                                                            {{ option }}
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div class="grid gap-3">
                                                <Label
                                                    for="birthdate"
                                                    :class="
                                                        stepOneLabelRowClass
                                                    "
                                                >
                                                    <span>Birthdate</span>
                                                    <Badge
                                                        v-if="
                                                            stepOneFieldInvalid.birthdate
                                                        "
                                                        variant="destructive"
                                                    >
                                                        Required
                                                    </Badge>
                                                    <Button
                                                        v-if="
                                                            personalInfo.birthdate !=
                                                            null
                                                        "
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        :class="
                                                            clearFieldButtonClass
                                                        "
                                                        aria-label="Clear birthdate"
                                                        @click="clearBirthdate"
                                                    >
                                                        <X class="size-3.5" />
                                                    </Button>
                                                </Label>
                                                <Popover v-slot="{ close }">
                                                    <PopoverTrigger as-child>
                                                        <Button
                                                            id="birthdate"
                                                            variant="outline"
                                                            class="w-full min-w-0 justify-between gap-2 border-input bg-transparent text-sm font-normal dark:bg-input/30"
                                                            :aria-invalid="
                                                                stepOneFieldInvalid.birthdate
                                                            "
                                                        >
                                                            <span
                                                                v-if="
                                                                    birthdateDisplay
                                                                "
                                                                class="min-w-0 flex-1 truncate text-left text-foreground"
                                                            >
                                                                {{
                                                                    birthdateDisplay
                                                                }}
                                                            </span>
                                                            <span
                                                                v-else
                                                                class="flex-1 text-left text-muted-foreground"
                                                            >
                                                                Select birthdate
                                                            </span>
                                                            <ChevronDownIcon
                                                                class="size-4 shrink-0 opacity-50"
                                                                aria-hidden="true"
                                                            />
                                                        </Button>
                                                    </PopoverTrigger>
                                                    <PopoverContent
                                                        class="w-auto overflow-hidden p-0"
                                                        align="start"
                                                    >
                                                        <Calendar
                                                            layout="month-and-year"
                                                            @update:model-value="
                                                                (value) =>
                                                                    onBirthdateSelect(
                                                                        value,
                                                                        close,
                                                                    )
                                                            "
                                                        />
                                                    </PopoverContent>
                                                </Popover>
                                            </div>
                                            <div class="grid gap-3">
                                                <Label
                                                    for="sex"
                                                    :class="
                                                        stepOneLabelRowClass
                                                    "
                                                >
                                                    <span>Sex</span>
                                                    <Badge
                                                        v-if="
                                                            stepOneFieldInvalid.sex
                                                        "
                                                        variant="destructive"
                                                    >
                                                        Required
                                                    </Badge>
                                                    <Button
                                                        v-if="
                                                            personalInfo.sex !==
                                                            ''
                                                        "
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        :class="
                                                            clearFieldButtonClass
                                                        "
                                                        aria-label="Clear sex"
                                                        @click="
                                                            personalInfo.sex =
                                                                ''
                                                        "
                                                    >
                                                        <X class="size-3.5" />
                                                    </Button>
                                                </Label>
                                                <Select
                                                    v-model="personalInfo.sex"
                                                >
                                                    <SelectTrigger
                                                        id="sex"
                                                        class="w-full min-w-0"
                                                        :aria-invalid="
                                                            stepOneFieldInvalid.sex
                                                        "
                                                    >
                                                        <SelectValue
                                                            placeholder="Select sex"
                                                        />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem
                                                            v-for="option in sexOptions"
                                                            :key="option"
                                                            :value="option"
                                                        >
                                                            {{ option }}
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div class="grid gap-3">
                                                <Label
                                                    for="civil_status"
                                                    :class="
                                                        stepOneLabelRowClass
                                                    "
                                                >
                                                    <span>Civil status</span>
                                                    <Badge variant="secondary">
                                                        Optional
                                                    </Badge>
                                                    <Button
                                                        v-if="
                                                            personalInfo.civilStatus !==
                                                            ''
                                                        "
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        :class="
                                                            clearFieldButtonClass
                                                        "
                                                        aria-label="Clear civil status"
                                                        @click="
                                                            personalInfo.civilStatus =
                                                                ''
                                                        "
                                                    >
                                                        <X class="size-3.5" />
                                                    </Button>
                                                </Label>
                                                <Select
                                                    v-model="
                                                        personalInfo.civilStatus
                                                    "
                                                >
                                                    <SelectTrigger
                                                        id="civil_status"
                                                        class="w-full min-w-0"
                                                    >
                                                        <SelectValue
                                                            placeholder="Select civil status"
                                                        />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem
                                                            v-for="option in civilStatusOptions"
                                                            :key="option"
                                                            :value="option"
                                                        >
                                                            {{ option }}
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div class="grid gap-3">
                                                <Label
                                                    for="nationality"
                                                    :class="
                                                        stepOneLabelRowClass
                                                    "
                                                >
                                                    <span>Nationality</span>
                                                    <Badge variant="secondary">
                                                        Optional
                                                    </Badge>
                                                    <Button
                                                        v-if="
                                                            personalInfo.nationality !==
                                                            ''
                                                        "
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        :class="
                                                            clearFieldButtonClass
                                                        "
                                                        aria-label="Clear nationality"
                                                        @click="
                                                            personalInfo.nationality =
                                                                ''
                                                        "
                                                    >
                                                        <X class="size-3.5" />
                                                    </Button>
                                                </Label>
                                                <Select
                                                    v-model="
                                                        personalInfo.nationality
                                                    "
                                                >
                                                    <SelectTrigger
                                                        id="nationality"
                                                        class="w-full min-w-0"
                                                    >
                                                        <SelectValue
                                                            placeholder="Select nationality"
                                                        />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem
                                                            v-for="option in nationalityOptions"
                                                            :key="option"
                                                            :value="option"
                                                        >
                                                            {{ option }}
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div class="grid gap-3">
                                                <Label
                                                    for="religion"
                                                    :class="
                                                        stepOneLabelRowClass
                                                    "
                                                >
                                                    <span>Religion</span>
                                                    <Badge variant="secondary">
                                                        Optional
                                                    </Badge>
                                                    <Button
                                                        v-if="
                                                            personalInfo.religion !==
                                                            ''
                                                        "
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        :class="
                                                            clearFieldButtonClass
                                                        "
                                                        aria-label="Clear religion"
                                                        @click="
                                                            personalInfo.religion =
                                                                ''
                                                        "
                                                    >
                                                        <X class="size-3.5" />
                                                    </Button>
                                                </Label>
                                                <Select
                                                    v-model="
                                                        personalInfo.religion
                                                    "
                                                >
                                                    <SelectTrigger
                                                        id="religion"
                                                        class="w-full min-w-0"
                                                    >
                                                        <SelectValue
                                                            placeholder="Select religion"
                                                        />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem
                                                            v-for="option in religionOptions"
                                                            :key="option"
                                                            :value="option"
                                                        >
                                                            {{ option }}
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div
                                                v-if="
                                                    personalInfo.religion ===
                                                    'Other'
                                                "
                                                class="col-span-full grid gap-3"
                                            >
                                                <Label
                                                    for="religion_other"
                                                    :class="
                                                        stepOneLabelRowClass
                                                    "
                                                >
                                                    <span
                                                        >Specify religion</span
                                                    >
                                                    <Badge
                                                        v-if="
                                                            stepOneFieldInvalid.religionOther
                                                        "
                                                        variant="destructive"
                                                    >
                                                        Required
                                                    </Badge>
                                                    <Button
                                                        v-if="
                                                            personalInfo.religionOther !==
                                                            ''
                                                        "
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        :class="
                                                            clearFieldButtonClass
                                                        "
                                                        aria-label="Clear specified religion"
                                                        @click="
                                                            personalInfo.religionOther =
                                                                ''
                                                        "
                                                    >
                                                        <X class="size-3.5" />
                                                    </Button>
                                                </Label>
                                                <Input
                                                    class="w-full"
                                                    id="religion_other"
                                                    v-model="
                                                        personalInfo.religionOther
                                                    "
                                                    placeholder="Enter religion"
                                                    :aria-invalid="
                                                        stepOneFieldInvalid.religionOther
                                                    "
                                                />
                                            </div>
                                        </div>
                                        <div
                                            class="-mx-6 mt-10 shrink-0 border-t border-border"
                                            role="separator"
                                            aria-hidden="true"
                                        />
                                        <div
                                            class="mt-8 rounded-lg border border-primary/30 bg-muted/30 p-4 dark:bg-muted/25"
                                        >
                                            <div
                                                class="flex flex-row items-center gap-4"
                                            >
                                                <span
                                                    class="inline-flex shrink-0 items-center justify-center rounded-full bg-primary/10 p-3 text-primary"
                                                >
                                                    <MapPin
                                                        class="size-7 shrink-0"
                                                        aria-hidden="true"
                                                    />
                                                </span>
                                                <div class="min-w-0 flex-1">
                                                    <div
                                                        class="flex items-center justify-between gap-3"
                                                    >
                                                        <div
                                                            class="min-w-0 flex-1 space-y-0.5"
                                                        >
                                                            <div
                                                                class="flex flex-row flex-wrap items-center gap-x-2 gap-y-1 max-sm:flex-nowrap"
                                                            >
                                                                <h2
                                                                    class="min-w-0 text-base font-semibold text-foreground"
                                                                    :class="
                                                                        maxSmOneLineTruncateClass
                                                                    "
                                                                    title="Permanent Address"
                                                                >
                                                                    Permanent
                                                                    Address
                                                                </h2>
                                                                <Badge
                                                                    variant="secondary"
                                                                    class="shrink-0"
                                                                >
                                                                    Optional
                                                                </Badge>
                                                            </div>
                                                            <p
                                                                class="mt-0 text-sm leading-relaxed text-muted-foreground"
                                                                :class="
                                                                    maxSmOneLineTruncateClass
                                                                "
                                                                title="Primary residence on file for government IDs, payroll and tax purposes, and official HR correspondence."
                                                            >
                                                                Primary
                                                                residence on
                                                                file for
                                                                government IDs,
                                                                payroll and tax
                                                                purposes, and
                                                                official HR
                                                                correspondence.
                                                            </p>
                                                        </div>
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="icon"
                                                            class="shrink-0 cursor-pointer border-border bg-background/80 hover:border-foreground/40 hover:bg-muted/60"
                                                            :aria-label="
                                                                showPermanentAddress
                                                                    ? 'Remove permanent address'
                                                                    : 'Add permanent address'
                                                            "
                                                            :aria-expanded="
                                                                showPermanentAddress
                                                            "
                                                            aria-controls="permanent-address-fields"
                                                            @click="
                                                                showPermanentAddress =
                                                                    !showPermanentAddress
                                                            "
                                                        >
                                                            <Minus
                                                                v-if="
                                                                    showPermanentAddress
                                                                "
                                                                class="size-4"
                                                                aria-hidden="true"
                                                            />
                                                            <Plus
                                                                v-else
                                                                class="size-4"
                                                                aria-hidden="true"
                                                            />
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            v-if="showPermanentAddress"
                                            id="permanent-address-fields"
                                            class="space-y-6"
                                        >
                                            <div class="min-w-0">
                                                <div
                                                    :class="
                                                        primaryToggleRowClass
                                                    "
                                                >
                                                    <div
                                                        class="flex shrink-0 items-center gap-2"
                                                    >
                                                        <Checkbox
                                                            id="permanent_is_primary"
                                                            :model-value="
                                                                permanentAddress.isPrimary
                                                            "
                                                            class="shrink-0"
                                                            aria-labelledby="permanent_primary_heading"
                                                            @update:model-value="
                                                                setPermanentAddressPrimary
                                                            "
                                                        />
                                                        <label
                                                            id="permanent_primary_heading"
                                                            class="cursor-pointer"
                                                            for="permanent_is_primary"
                                                        >
                                                            <Badge
                                                                >Primary</Badge
                                                            >
                                                        </label>
                                                    </div>
                                                    <div
                                                        :class="
                                                            primaryToggleRuleClass
                                                        "
                                                        role="presentation"
                                                        aria-hidden="true"
                                                    />
                                                </div>
                                            </div>
                                            <div
                                                class="grid grid-cols-1 gap-x-4 gap-y-6 md:grid-cols-2"
                                            >
                                                <div class="grid gap-3">
                                                    <Label
                                                        for="permanent_address_line_1"
                                                        :class="
                                                            stepOneLabelRowClass
                                                        "
                                                    >
                                                        <span
                                                            >Address line
                                                            1</span
                                                        >
                                                        <Badge
                                                            v-if="
                                                                stepOneFieldInvalid.addressLine1
                                                            "
                                                            variant="destructive"
                                                        >
                                                            Required
                                                        </Badge>
                                                        <Button
                                                            v-if="
                                                                permanentAddress.addressLine1 !==
                                                                ''
                                                            "
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            :class="
                                                                clearFieldButtonClass
                                                            "
                                                            aria-label="Clear address line 1"
                                                            @click="
                                                                permanentAddress.addressLine1 =
                                                                    ''
                                                            "
                                                        >
                                                            <X
                                                                class="size-3.5"
                                                            />
                                                        </Button>
                                                    </Label>
                                                    <Input
                                                        id="permanent_address_line_1"
                                                        v-model="
                                                            permanentAddress.addressLine1
                                                        "
                                                        class="w-full"
                                                        maxlength="255"
                                                        autocomplete="address-line1"
                                                        placeholder="Street, building, or unit"
                                                        :aria-invalid="
                                                            stepOneFieldInvalid.addressLine1
                                                        "
                                                    />
                                                </div>
                                                <div class="grid gap-3">
                                                    <Label
                                                        for="permanent_address_line_2"
                                                        :class="
                                                            stepOneLabelRowClass
                                                        "
                                                    >
                                                        <span
                                                            >Address line
                                                            2</span
                                                        >
                                                        <Badge
                                                            variant="secondary"
                                                        >
                                                            Optional
                                                        </Badge>
                                                        <Button
                                                            v-if="
                                                                permanentAddress.addressLine2 !==
                                                                ''
                                                            "
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            :class="
                                                                clearFieldButtonClass
                                                            "
                                                            aria-label="Clear address line 2"
                                                            @click="
                                                                permanentAddress.addressLine2 =
                                                                    ''
                                                            "
                                                        >
                                                            <X
                                                                class="size-3.5"
                                                            />
                                                        </Button>
                                                    </Label>
                                                    <Input
                                                        id="permanent_address_line_2"
                                                        v-model="
                                                            permanentAddress.addressLine2
                                                        "
                                                        class="w-full"
                                                        maxlength="255"
                                                        autocomplete="address-line2"
                                                        placeholder="Floor, subdivision, or additional detail"
                                                    />
                                                </div>
                                            </div>
                                            <div
                                                class="grid grid-cols-1 gap-x-4 gap-y-6 md:grid-cols-2 lg:grid-cols-3"
                                            >
                                                <div class="grid gap-3">
                                                    <Label
                                                        for="permanent_country"
                                                        :class="
                                                            stepOneLabelRowClass
                                                        "
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
                                                            :class="
                                                                clearFieldButtonClass
                                                            "
                                                            aria-label="Reset country to Philippines"
                                                            @click="
                                                                permanentAddress.country =
                                                                    'Philippines'
                                                            "
                                                        >
                                                            <X
                                                                class="size-3.5"
                                                            />
                                                        </Button>
                                                    </Label>
                                                    <Select
                                                        v-model="
                                                            permanentAddress.country
                                                        "
                                                    >
                                                        <SelectTrigger
                                                            id="permanent_country"
                                                            class="w-full min-w-0"
                                                        >
                                                            <SelectValue
                                                                placeholder="Select country"
                                                            />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                v-for="country in countryOptions"
                                                                :key="country"
                                                                :value="country"
                                                            >
                                                                {{ country }}
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div class="grid gap-3">
                                                    <Label
                                                        for="permanent_province"
                                                        :class="
                                                            stepOneLabelRowClass
                                                        "
                                                    >
                                                        <span>Province</span>
                                                        <Badge
                                                            v-if="
                                                                stepOneFieldInvalid.provinceCode
                                                            "
                                                            variant="destructive"
                                                        >
                                                            Required
                                                        </Badge>
                                                        <Button
                                                            v-if="
                                                                permanentAddress.provinceCode !==
                                                                ''
                                                            "
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            :class="
                                                                clearFieldButtonClass
                                                            "
                                                            aria-label="Clear province"
                                                            @click="
                                                                clearProvince
                                                            "
                                                        >
                                                            <X
                                                                class="size-3.5"
                                                            />
                                                        </Button>
                                                    </Label>
                                                    <Select
                                                        :model-value="
                                                            permanentAddress.provinceCode
                                                        "
                                                        @update:model-value="
                                                            onProvinceChange
                                                        "
                                                    >
                                                        <SelectTrigger
                                                            id="permanent_province"
                                                            class="w-full min-w-0"
                                                            :aria-invalid="
                                                                stepOneFieldInvalid.provinceCode
                                                            "
                                                        >
                                                            <SelectValue
                                                                placeholder="Select province"
                                                            />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                v-for="option in provinceOptions"
                                                                :key="
                                                                    option.code
                                                                "
                                                                :value="
                                                                    option.code
                                                                "
                                                            >
                                                                {{
                                                                    option.name
                                                                }}
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div class="grid gap-3">
                                                    <Label
                                                        for="permanent_city"
                                                        :class="
                                                            stepOneLabelRowClass
                                                        "
                                                    >
                                                        <span>City</span>
                                                        <Badge
                                                            v-if="
                                                                stepOneFieldInvalid.cityCode
                                                            "
                                                            variant="destructive"
                                                        >
                                                            Required
                                                        </Badge>
                                                        <Button
                                                            v-if="
                                                                permanentAddress.cityCode !==
                                                                ''
                                                            "
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            :class="
                                                                clearFieldButtonClass
                                                            "
                                                            aria-label="Clear city"
                                                            @click="clearCity"
                                                        >
                                                            <X
                                                                class="size-3.5"
                                                            />
                                                        </Button>
                                                    </Label>
                                                    <Select
                                                        :model-value="
                                                            permanentAddress.cityCode
                                                        "
                                                        @update:model-value="
                                                            onCityChange
                                                        "
                                                    >
                                                        <SelectTrigger
                                                            id="permanent_city"
                                                            class="w-full min-w-0"
                                                            :disabled="
                                                                permanentAddress.provinceCode ===
                                                                ''
                                                            "
                                                            :aria-invalid="
                                                                stepOneFieldInvalid.cityCode
                                                            "
                                                        >
                                                            <SelectValue
                                                                placeholder="Select city/municipality"
                                                            />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                v-for="option in cityOptions"
                                                                :key="
                                                                    option.code
                                                                "
                                                                :value="
                                                                    option.code
                                                                "
                                                            >
                                                                {{
                                                                    option.name
                                                                }}
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div class="grid gap-3">
                                                    <Label
                                                        for="permanent_barangay"
                                                        :class="
                                                            stepOneLabelRowClass
                                                        "
                                                    >
                                                        <span>Barangay</span>
                                                        <Badge
                                                            v-if="
                                                                stepOneFieldInvalid.barangayCode
                                                            "
                                                            variant="destructive"
                                                        >
                                                            Required
                                                        </Badge>
                                                        <Button
                                                            v-if="
                                                                permanentAddress.barangayCode !==
                                                                ''
                                                            "
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            :class="
                                                                clearFieldButtonClass
                                                            "
                                                            aria-label="Clear barangay"
                                                            @click="
                                                                clearBarangay
                                                            "
                                                        >
                                                            <X
                                                                class="size-3.5"
                                                            />
                                                        </Button>
                                                    </Label>
                                                    <Select
                                                        :model-value="
                                                            permanentAddress.barangayCode
                                                        "
                                                        @update:model-value="
                                                            onBarangayChange
                                                        "
                                                    >
                                                        <SelectTrigger
                                                            id="permanent_barangay"
                                                            class="w-full min-w-0"
                                                            :disabled="
                                                                permanentAddress.cityCode ===
                                                                ''
                                                            "
                                                            :aria-invalid="
                                                                stepOneFieldInvalid.barangayCode
                                                            "
                                                        >
                                                            <SelectValue
                                                                placeholder="Select barangay"
                                                            />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                v-for="option in barangayOptions"
                                                                :key="
                                                                    option.code
                                                                "
                                                                :value="
                                                                    option.code
                                                                "
                                                            >
                                                                {{
                                                                    option.name
                                                                }}
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div class="grid gap-3">
                                                    <Label
                                                        for="permanent_zip_code"
                                                        :class="
                                                            stepOneLabelRowClass
                                                        "
                                                    >
                                                        <span>ZIP code</span>
                                                        <Badge
                                                            v-if="
                                                                stepOneFieldInvalid.zipCode
                                                            "
                                                            variant="destructive"
                                                        >
                                                            Required
                                                        </Badge>
                                                        <Button
                                                            v-if="
                                                                permanentAddress.zipCode !==
                                                                ''
                                                            "
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            :class="
                                                                clearFieldButtonClass
                                                            "
                                                            aria-label="Clear ZIP code"
                                                            @click="
                                                                permanentAddress.zipCode =
                                                                    ''
                                                            "
                                                        >
                                                            <X
                                                                class="size-3.5"
                                                            />
                                                        </Button>
                                                    </Label>
                                                    <Input
                                                        id="permanent_zip_code"
                                                        v-model="
                                                            permanentAddress.zipCode
                                                        "
                                                        class="w-full"
                                                        maxlength="20"
                                                        inputmode="numeric"
                                                        autocomplete="postal-code"
                                                        placeholder="e.g. 8000"
                                                        :aria-invalid="
                                                            stepOneFieldInvalid.zipCode
                                                        "
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </section>

                                    <section
                                        v-else-if="currentStep === 2"
                                        aria-label="Contact details"
                                        class="min-w-0 space-y-6"
                                    >
                                        <div
                                            class="rounded-lg border border-primary/30 bg-muted/30 p-4 dark:bg-muted/25"
                                        >
                                            <div
                                                class="flex flex-row items-center gap-4"
                                            >
                                                <span
                                                    class="inline-flex shrink-0 items-center justify-center rounded-full bg-primary/10 p-3 text-primary"
                                                >
                                                    <Phone
                                                        class="size-7 shrink-0"
                                                        aria-hidden="true"
                                                    />
                                                </span>
                                                <div
                                                    class="min-w-0 flex-1 space-y-0.5"
                                                >
                                                    <h2
                                                        class="text-base font-semibold text-foreground"
                                                        :class="
                                                            maxSmOneLineTruncateClass
                                                        "
                                                        title="Personal Contacts"
                                                    >
                                                        Personal Contacts
                                                    </h2>
                                                    <p
                                                        class="mt-0 text-sm leading-relaxed text-muted-foreground"
                                                        :class="
                                                            maxSmOneLineTruncateClass
                                                        "
                                                        title="Phone numbers, email, and messaging channels for reaching the employee directly."
                                                    >
                                                        Phone numbers, email,
                                                        and messaging channels
                                                        for reaching the
                                                        employee directly.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="min-w-0 space-y-6">
                                            <div
                                                v-for="(
                                                    contact, index
                                                ) in personalContacts"
                                                :key="`personal-contact-${index}`"
                                                class="space-y-6"
                                            >
                                                <div
                                                    class="flex min-w-0 items-center gap-3"
                                                >
                                                    <div
                                                        class="flex shrink-0 items-center gap-2"
                                                    >
                                                        <Checkbox
                                                            :id="`personal_contact_is_primary_${index}`"
                                                            :model-value="
                                                                contact.isPrimary
                                                            "
                                                            class="shrink-0 self-center"
                                                            :aria-labelledby="`personal_contact_primary_heading_${index}`"
                                                            @update:model-value="
                                                                (value) =>
                                                                    setPrimaryPersonalContact(
                                                                        index,
                                                                        value,
                                                                    )
                                                            "
                                                        />
                                                        <div
                                                            class="inline-flex items-center gap-2"
                                                        >
                                                            <label
                                                                :id="`personal_contact_primary_heading_${index}`"
                                                                class="inline-flex cursor-pointer items-center"
                                                                :for="`personal_contact_is_primary_${index}`"
                                                            >
                                                                <Badge
                                                                    >Primary</Badge
                                                                >
                                                            </label>
                                                            <Badge
                                                                v-if="
                                                                    stepTwoPersonalMissingPrimary
                                                                "
                                                                variant="destructive"
                                                                class="shrink-0"
                                                            >
                                                                Required
                                                            </Badge>
                                                        </div>
                                                    </div>
                                                    <div
                                                        :class="
                                                            primaryToggleRuleClass
                                                        "
                                                        role="presentation"
                                                        aria-hidden="true"
                                                    />
                                                    <Button
                                                        v-if="index > 0"
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        class="shrink-0 text-muted-foreground hover:text-destructive"
                                                        @click="
                                                            removePersonalContact(
                                                                index,
                                                            )
                                                        "
                                                    >
                                                        Remove
                                                    </Button>
                                                </div>
                                                <div
                                                    class="grid grid-cols-1 gap-x-4 gap-y-6 md:grid-cols-3"
                                                >
                                                    <div class="grid gap-3">
                                                        <Label
                                                            :for="`personal_contact_type_${index}`"
                                                            :class="
                                                                stepOneLabelRowClass
                                                            "
                                                        >
                                                            <span
                                                                >Contact
                                                                type</span
                                                            >
                                                            <Badge
                                                                v-if="
                                                                    stepTwoPersonalContactRowInvalid[
                                                                        index
                                                                    ]?.type
                                                                "
                                                                variant="destructive"
                                                            >
                                                                Required
                                                            </Badge>
                                                            <Button
                                                                v-if="
                                                                    contact.type !==
                                                                    ''
                                                                "
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                :class="
                                                                    clearFieldButtonClass
                                                                "
                                                                aria-label="Clear contact type"
                                                                @click="
                                                                    contact.type =
                                                                        ''
                                                                "
                                                            >
                                                                <X
                                                                    class="size-3.5"
                                                                />
                                                            </Button>
                                                        </Label>
                                                        <Select
                                                            v-model="
                                                                contact.type
                                                            "
                                                        >
                                                            <SelectTrigger
                                                                :id="`personal_contact_type_${index}`"
                                                                class="w-full min-w-0"
                                                                :aria-invalid="
                                                                    stepTwoPersonalContactRowInvalid[
                                                                        index
                                                                    ]?.type
                                                                "
                                                            >
                                                                <SelectValue
                                                                    placeholder="Select type"
                                                                />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem
                                                                    v-for="opt in personalContactTypeOptions"
                                                                    :key="
                                                                        opt.value
                                                                    "
                                                                    :value="
                                                                        opt.value
                                                                    "
                                                                >
                                                                    {{
                                                                        opt.label
                                                                    }}
                                                                </SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                    <div class="grid gap-3">
                                                        <Label
                                                            :for="`personal_contact_number_${index}`"
                                                            :class="
                                                                stepOneLabelRowClass
                                                            "
                                                        >
                                                            <span
                                                                >Contact
                                                                number</span
                                                            >
                                                            <Badge
                                                                v-if="
                                                                    stepTwoPersonalContactRowInvalid[
                                                                        index
                                                                    ]
                                                                        ?.contactNumber
                                                                "
                                                                variant="destructive"
                                                            >
                                                                Required
                                                            </Badge>
                                                            <Button
                                                                v-if="
                                                                    contact.contactNumber !==
                                                                    ''
                                                                "
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                :class="
                                                                    clearFieldButtonClass
                                                                "
                                                                aria-label="Clear contact number"
                                                                @click="
                                                                    contact.contactNumber =
                                                                        ''
                                                                "
                                                            >
                                                                <X
                                                                    class="size-3.5"
                                                                />
                                                            </Button>
                                                        </Label>
                                                        <Input
                                                            :id="`personal_contact_number_${index}`"
                                                            v-model="
                                                                contact.contactNumber
                                                            "
                                                            class="w-full"
                                                            type="tel"
                                                            maxlength="50"
                                                            autocomplete="tel"
                                                            placeholder="e.g. +63 …"
                                                            :aria-invalid="
                                                                stepTwoPersonalContactRowInvalid[
                                                                    index
                                                                ]?.contactNumber
                                                            "
                                                        />
                                                    </div>
                                                    <div class="grid gap-3">
                                                        <Label
                                                            :for="`personal_contact_email_${index}`"
                                                            :class="
                                                                stepOneLabelRowClass
                                                            "
                                                        >
                                                            <span>Email</span>
                                                            <Badge
                                                                variant="secondary"
                                                            >
                                                                Optional
                                                            </Badge>
                                                            <Button
                                                                v-if="
                                                                    contact.email !==
                                                                    ''
                                                                "
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                :class="
                                                                    clearFieldButtonClass
                                                                "
                                                                aria-label="Clear email"
                                                                @click="
                                                                    contact.email =
                                                                        ''
                                                                "
                                                            >
                                                                <X
                                                                    class="size-3.5"
                                                                />
                                                            </Button>
                                                        </Label>
                                                        <Input
                                                            :id="`personal_contact_email_${index}`"
                                                            v-model="
                                                                contact.email
                                                            "
                                                            class="w-full"
                                                            type="email"
                                                            maxlength="255"
                                                            autocomplete="email"
                                                            placeholder="name@example.com"
                                                        />
                                                    </div>
                                                </div>
                                                <div
                                                    class="w-full border-t border-primary/30"
                                                    role="presentation"
                                                    aria-hidden="true"
                                                />
                                            </div>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                class="w-full border-dashed border-primary/40 bg-primary/5 hover:bg-primary/10"
                                                aria-label="Add personal contact"
                                                title="Add personal contact"
                                                @click="addPersonalContact"
                                            >
                                                <Plus
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                        </div>
                                        <div
                                            class="-mx-6 mt-10 shrink-0 border-t border-border"
                                            role="separator"
                                            aria-hidden="true"
                                        />
                                        <div
                                            class="mt-8 rounded-lg border border-primary/30 bg-muted/30 p-4 dark:bg-muted/25"
                                        >
                                            <div
                                                class="flex flex-row items-center gap-4"
                                            >
                                                <span
                                                    class="inline-flex shrink-0 items-center justify-center rounded-full bg-primary/10 p-3 text-primary"
                                                >
                                                    <Home
                                                        class="size-7 shrink-0"
                                                        aria-hidden="true"
                                                    />
                                                </span>
                                                <div class="min-w-0 flex-1">
                                                    <div
                                                        class="flex items-center justify-between gap-3"
                                                    >
                                                        <div
                                                            class="min-w-0 flex-1 space-y-0.5"
                                                        >
                                                            <div
                                                                class="flex flex-row flex-wrap items-center gap-x-2 gap-y-1 max-sm:flex-nowrap"
                                                            >
                                                                <h2
                                                                    class="min-w-0 text-base font-semibold text-foreground"
                                                                    :class="
                                                                        maxSmOneLineTruncateClass
                                                                    "
                                                                    title="Current Address"
                                                                >
                                                                    Current
                                                                    Address
                                                                </h2>
                                                                <Badge
                                                                    variant="secondary"
                                                                    class="shrink-0"
                                                                >
                                                                    Optional
                                                                </Badge>
                                                            </div>
                                                            <p
                                                                class="mt-0 text-sm leading-relaxed text-muted-foreground"
                                                                :class="
                                                                    maxSmOneLineTruncateClass
                                                                "
                                                                title="Where the employee lives or receives mail today. May differ from permanent address on file."
                                                            >
                                                                Where the
                                                                employee lives
                                                                or receives mail
                                                                today. May
                                                                differ from
                                                                permanent
                                                                address on file.
                                                            </p>
                                                        </div>
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="icon"
                                                            class="shrink-0 cursor-pointer border-border bg-background/80 hover:border-foreground/40 hover:bg-muted/60"
                                                            :aria-label="
                                                                showCurrentAddress
                                                                    ? 'Remove current address'
                                                                    : 'Add current address'
                                                            "
                                                            :aria-expanded="
                                                                showCurrentAddress
                                                            "
                                                            aria-controls="current-address-fields"
                                                            @click="
                                                                showCurrentAddress =
                                                                    !showCurrentAddress
                                                            "
                                                        >
                                                            <Minus
                                                                v-if="
                                                                    showCurrentAddress
                                                                "
                                                                class="size-4"
                                                                aria-hidden="true"
                                                            />
                                                            <Plus
                                                                v-else
                                                                class="size-4"
                                                                aria-hidden="true"
                                                            />
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            v-if="showCurrentAddress"
                                            id="current-address-fields"
                                            class="space-y-6"
                                        >
                                            <div class="min-w-0">
                                                <div
                                                    :class="
                                                        primaryToggleRowClass
                                                    "
                                                >
                                                    <div
                                                        class="flex shrink-0 items-center gap-2"
                                                    >
                                                        <Checkbox
                                                            id="current_is_primary"
                                                            :model-value="
                                                                currentAddress.isPrimary
                                                            "
                                                            class="shrink-0"
                                                            aria-labelledby="current_primary_heading"
                                                            @update:model-value="
                                                                setCurrentAddressPrimary
                                                            "
                                                        />
                                                        <label
                                                            id="current_primary_heading"
                                                            class="cursor-pointer"
                                                            for="current_is_primary"
                                                        >
                                                            <Badge
                                                                >Primary</Badge
                                                            >
                                                        </label>
                                                    </div>
                                                    <div
                                                        :class="
                                                            primaryToggleRuleClass
                                                        "
                                                        role="presentation"
                                                        aria-hidden="true"
                                                    />
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        class="shrink-0"
                                                        :disabled="
                                                            !canUsePermanentAddress
                                                        "
                                                        @click="
                                                            usePermanentAddressForCurrent
                                                        "
                                                    >
                                                        Use Permanent Address
                                                    </Button>
                                                </div>
                                            </div>
                                            <div
                                                class="grid grid-cols-1 gap-x-4 gap-y-6 md:grid-cols-2"
                                            >
                                                <div class="grid gap-3">
                                                    <Label
                                                        for="current_address_line_1"
                                                        :class="
                                                            stepOneLabelRowClass
                                                        "
                                                    >
                                                        <span
                                                            >Address line
                                                            1</span
                                                        >
                                                        <Badge
                                                            v-if="
                                                                stepTwoCurrentAddressInvalid.addressLine1
                                                            "
                                                            variant="destructive"
                                                        >
                                                            Required
                                                        </Badge>
                                                        <Button
                                                            v-if="
                                                                currentAddress.addressLine1 !==
                                                                ''
                                                            "
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            :class="
                                                                clearFieldButtonClass
                                                            "
                                                            aria-label="Clear current address line 1"
                                                            @click="
                                                                currentAddress.addressLine1 =
                                                                    ''
                                                            "
                                                        >
                                                            <X
                                                                class="size-3.5"
                                                            />
                                                        </Button>
                                                    </Label>
                                                    <Input
                                                        id="current_address_line_1"
                                                        v-model="
                                                            currentAddress.addressLine1
                                                        "
                                                        class="w-full"
                                                        maxlength="255"
                                                        autocomplete="address-line1"
                                                        placeholder="Street, building, or unit"
                                                        :aria-invalid="
                                                            stepTwoCurrentAddressInvalid.addressLine1
                                                        "
                                                    />
                                                </div>
                                                <div class="grid gap-3">
                                                    <Label
                                                        for="current_address_line_2"
                                                        :class="
                                                            stepOneLabelRowClass
                                                        "
                                                    >
                                                        <span
                                                            >Address line
                                                            2</span
                                                        >
                                                        <Badge
                                                            variant="secondary"
                                                        >
                                                            Optional
                                                        </Badge>
                                                        <Button
                                                            v-if="
                                                                currentAddress.addressLine2 !==
                                                                ''
                                                            "
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            :class="
                                                                clearFieldButtonClass
                                                            "
                                                            aria-label="Clear current address line 2"
                                                            @click="
                                                                currentAddress.addressLine2 =
                                                                    ''
                                                            "
                                                        >
                                                            <X
                                                                class="size-3.5"
                                                            />
                                                        </Button>
                                                    </Label>
                                                    <Input
                                                        id="current_address_line_2"
                                                        v-model="
                                                            currentAddress.addressLine2
                                                        "
                                                        class="w-full"
                                                        maxlength="255"
                                                        autocomplete="address-line2"
                                                        placeholder="Floor, subdivision, or additional detail"
                                                    />
                                                </div>
                                            </div>
                                            <div
                                                class="grid grid-cols-1 gap-x-4 gap-y-6 md:grid-cols-2 lg:grid-cols-3"
                                            >
                                                <div class="grid gap-3">
                                                    <Label
                                                        for="current_country"
                                                        :class="
                                                            stepOneLabelRowClass
                                                        "
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
                                                            :class="
                                                                clearFieldButtonClass
                                                            "
                                                            aria-label="Reset current country to Philippines"
                                                            @click="
                                                                currentAddress.country =
                                                                    'Philippines'
                                                            "
                                                        >
                                                            <X
                                                                class="size-3.5"
                                                            />
                                                        </Button>
                                                    </Label>
                                                    <Select
                                                        v-model="
                                                            currentAddress.country
                                                        "
                                                    >
                                                        <SelectTrigger
                                                            id="current_country"
                                                            class="w-full min-w-0"
                                                        >
                                                            <SelectValue
                                                                placeholder="Select country"
                                                            />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                v-for="country in countryOptions"
                                                                :key="country"
                                                                :value="country"
                                                            >
                                                                {{ country }}
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div class="grid gap-3">
                                                    <Label
                                                        for="current_province"
                                                        :class="
                                                            stepOneLabelRowClass
                                                        "
                                                    >
                                                        <span>Province</span>
                                                        <Badge
                                                            v-if="
                                                                stepTwoCurrentAddressInvalid.provinceCode
                                                            "
                                                            variant="destructive"
                                                        >
                                                            Required
                                                        </Badge>
                                                        <Button
                                                            v-if="
                                                                currentAddress.provinceCode !==
                                                                ''
                                                            "
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            :class="
                                                                clearFieldButtonClass
                                                            "
                                                            aria-label="Clear current province"
                                                            @click="
                                                                clearCurrentProvince
                                                            "
                                                        >
                                                            <X
                                                                class="size-3.5"
                                                            />
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
                                                            id="current_province"
                                                            class="w-full min-w-0"
                                                            :aria-invalid="
                                                                stepTwoCurrentAddressInvalid.provinceCode
                                                            "
                                                        >
                                                            <SelectValue
                                                                placeholder="Select province"
                                                            />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                v-for="option in provinceOptions"
                                                                :key="
                                                                    option.code
                                                                "
                                                                :value="
                                                                    option.code
                                                                "
                                                            >
                                                                {{
                                                                    option.name
                                                                }}
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div class="grid gap-3">
                                                    <Label
                                                        for="current_city"
                                                        :class="
                                                            stepOneLabelRowClass
                                                        "
                                                    >
                                                        <span>City</span>
                                                        <Badge
                                                            v-if="
                                                                stepTwoCurrentAddressInvalid.cityCode
                                                            "
                                                            variant="destructive"
                                                        >
                                                            Required
                                                        </Badge>
                                                        <Button
                                                            v-if="
                                                                currentAddress.cityCode !==
                                                                ''
                                                            "
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            :class="
                                                                clearFieldButtonClass
                                                            "
                                                            aria-label="Clear current city"
                                                            @click="
                                                                clearCurrentCity
                                                            "
                                                        >
                                                            <X
                                                                class="size-3.5"
                                                            />
                                                        </Button>
                                                    </Label>
                                                    <Select
                                                        :model-value="
                                                            currentAddress.cityCode
                                                        "
                                                        @update:model-value="
                                                            onCurrentCityChange
                                                        "
                                                    >
                                                        <SelectTrigger
                                                            id="current_city"
                                                            class="w-full min-w-0"
                                                            :disabled="
                                                                currentAddress.provinceCode ===
                                                                ''
                                                            "
                                                            :aria-invalid="
                                                                stepTwoCurrentAddressInvalid.cityCode
                                                            "
                                                        >
                                                            <SelectValue
                                                                placeholder="Select city/municipality"
                                                            />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                v-for="option in currentCityOptions"
                                                                :key="
                                                                    option.code
                                                                "
                                                                :value="
                                                                    option.code
                                                                "
                                                            >
                                                                {{
                                                                    option.name
                                                                }}
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div class="grid gap-3">
                                                    <Label
                                                        for="current_barangay"
                                                        :class="
                                                            stepOneLabelRowClass
                                                        "
                                                    >
                                                        <span>Barangay</span>
                                                        <Badge
                                                            v-if="
                                                                stepTwoCurrentAddressInvalid.barangayCode
                                                            "
                                                            variant="destructive"
                                                        >
                                                            Required
                                                        </Badge>
                                                        <Button
                                                            v-if="
                                                                currentAddress.barangayCode !==
                                                                ''
                                                            "
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            :class="
                                                                clearFieldButtonClass
                                                            "
                                                            aria-label="Clear current barangay"
                                                            @click="
                                                                clearCurrentBarangay
                                                            "
                                                        >
                                                            <X
                                                                class="size-3.5"
                                                            />
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
                                                            id="current_barangay"
                                                            class="w-full min-w-0"
                                                            :disabled="
                                                                currentAddress.cityCode ===
                                                                ''
                                                            "
                                                            :aria-invalid="
                                                                stepTwoCurrentAddressInvalid.barangayCode
                                                            "
                                                        >
                                                            <SelectValue
                                                                placeholder="Select barangay"
                                                            />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                v-for="option in currentBarangayOptions"
                                                                :key="
                                                                    option.code
                                                                "
                                                                :value="
                                                                    option.code
                                                                "
                                                            >
                                                                {{
                                                                    option.name
                                                                }}
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div class="grid gap-3">
                                                    <Label
                                                        for="current_zip_code"
                                                        :class="
                                                            stepOneLabelRowClass
                                                        "
                                                    >
                                                        <span>ZIP code</span>
                                                        <Badge
                                                            v-if="
                                                                stepTwoCurrentAddressInvalid.zipCode
                                                            "
                                                            variant="destructive"
                                                        >
                                                            Required
                                                        </Badge>
                                                        <Button
                                                            v-if="
                                                                currentAddress.zipCode !==
                                                                ''
                                                            "
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            :class="
                                                                clearFieldButtonClass
                                                            "
                                                            aria-label="Clear current ZIP code"
                                                            @click="
                                                                currentAddress.zipCode =
                                                                    ''
                                                            "
                                                        >
                                                            <X
                                                                class="size-3.5"
                                                            />
                                                        </Button>
                                                    </Label>
                                                    <Input
                                                        id="current_zip_code"
                                                        v-model="
                                                            currentAddress.zipCode
                                                        "
                                                        class="w-full"
                                                        maxlength="20"
                                                        inputmode="numeric"
                                                        autocomplete="postal-code"
                                                        placeholder="e.g. 8000"
                                                        :aria-invalid="
                                                            stepTwoCurrentAddressInvalid.zipCode
                                                        "
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            class="-mx-6 mt-10 shrink-0 border-t border-border"
                                            role="separator"
                                            aria-hidden="true"
                                        />
                                        <div
                                            class="mt-8 rounded-lg border border-primary/30 bg-muted/30 p-4 dark:bg-muted/25"
                                        >
                                            <div
                                                class="flex flex-row items-center gap-4"
                                            >
                                                <span
                                                    class="inline-flex shrink-0 items-center justify-center rounded-full bg-primary/10 p-3 text-primary"
                                                >
                                                    <LifeBuoy
                                                        class="size-7 shrink-0"
                                                        aria-hidden="true"
                                                    />
                                                </span>
                                                <div class="min-w-0 flex-1">
                                                    <div
                                                        class="flex items-center justify-between gap-3"
                                                    >
                                                        <div
                                                            class="min-w-0 flex-1 space-y-0.5"
                                                        >
                                                            <div
                                                                class="flex flex-row flex-wrap items-center gap-x-2 gap-y-1 max-sm:flex-nowrap"
                                                            >
                                                                <h2
                                                                    class="min-w-0 text-base font-semibold text-foreground"
                                                                    :class="
                                                                        maxSmOneLineTruncateClass
                                                                    "
                                                                    title="Emergency Contacts"
                                                                >
                                                                    Emergency
                                                                    Contacts
                                                                </h2>
                                                                <Badge
                                                                    variant="secondary"
                                                                    class="shrink-0"
                                                                >
                                                                    Optional
                                                                </Badge>
                                                            </div>
                                                            <p
                                                                class="mt-0 text-sm leading-relaxed text-muted-foreground"
                                                                :class="
                                                                    maxSmOneLineTruncateClass
                                                                "
                                                                title="People to notify in an emergency, with relationship and alternate phone numbers."
                                                            >
                                                                People to notify
                                                                in an emergency,
                                                                with
                                                                relationship and
                                                                alternate phone
                                                                numbers.
                                                            </p>
                                                        </div>
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="icon"
                                                            class="shrink-0 cursor-pointer border-border bg-background/80 hover:border-foreground/40 hover:bg-muted/60"
                                                            :aria-label="
                                                                showEmergencyContacts
                                                                    ? 'Remove emergency contacts'
                                                                    : 'Add emergency contacts'
                                                            "
                                                            :aria-expanded="
                                                                showEmergencyContacts
                                                            "
                                                            aria-controls="emergency-contact-fields"
                                                            @click="
                                                                showEmergencyContacts =
                                                                    !showEmergencyContacts
                                                            "
                                                        >
                                                            <Minus
                                                                v-if="
                                                                    showEmergencyContacts
                                                                "
                                                                class="size-4"
                                                                aria-hidden="true"
                                                            />
                                                            <Plus
                                                                v-else
                                                                class="size-4"
                                                                aria-hidden="true"
                                                            />
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            v-if="showEmergencyContacts"
                                            id="emergency-contact-fields"
                                            class="space-y-6"
                                        >
                                            <div
                                                v-for="(
                                                    contact, index
                                                ) in emergencyContacts"
                                                :key="`emergency-contact-${index}`"
                                                class="space-y-6"
                                            >
                                                <div
                                                    class="flex min-w-0 items-center gap-3"
                                                >
                                                    <div
                                                        class="flex shrink-0 items-center gap-2"
                                                    >
                                                        <Checkbox
                                                            :id="`emergency_contact_is_primary_${index}`"
                                                            :model-value="
                                                                contact.isPrimary
                                                            "
                                                            class="shrink-0"
                                                            :aria-labelledby="`emergency_contact_primary_heading_${index}`"
                                                            @update:model-value="
                                                                (value) =>
                                                                    setPrimaryEmergencyContact(
                                                                        index,
                                                                        value,
                                                                    )
                                                            "
                                                        />
                                                        <label
                                                            :id="`emergency_contact_primary_heading_${index}`"
                                                            class="cursor-pointer"
                                                            :for="`emergency_contact_is_primary_${index}`"
                                                        >
                                                            <Badge
                                                                >Primary</Badge
                                                            >
                                                        </label>
                                                    </div>
                                                    <div
                                                        :class="
                                                            primaryToggleRuleClass
                                                        "
                                                        role="presentation"
                                                        aria-hidden="true"
                                                    />
                                                    <Button
                                                        v-if="index > 0"
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        class="shrink-0 text-muted-foreground hover:text-destructive"
                                                        @click="
                                                            removeEmergencyContact(
                                                                index,
                                                            )
                                                        "
                                                    >
                                                        Remove
                                                    </Button>
                                                </div>
                                                <div
                                                    class="grid grid-cols-1 gap-x-4 gap-y-6 md:grid-cols-3"
                                                >
                                                    <div class="grid gap-3">
                                                        <Label
                                                            :for="`emergency_contact_type_${index}`"
                                                            :class="
                                                                stepOneLabelRowClass
                                                            "
                                                        >
                                                            <span
                                                                >Contact
                                                                type</span
                                                            >
                                                            <Button
                                                                v-if="
                                                                    contact.type !==
                                                                    'mobile'
                                                                "
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                :class="
                                                                    clearFieldButtonClass
                                                                "
                                                                aria-label="Reset emergency contact type to Mobile"
                                                                @click="
                                                                    contact.type =
                                                                        'mobile'
                                                                "
                                                            >
                                                                <X
                                                                    class="size-3.5"
                                                                />
                                                            </Button>
                                                        </Label>
                                                        <Select
                                                            v-model="
                                                                contact.type
                                                            "
                                                        >
                                                            <SelectTrigger
                                                                :id="`emergency_contact_type_${index}`"
                                                                class="w-full min-w-0"
                                                            >
                                                                <SelectValue
                                                                    placeholder="Select type"
                                                                />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem
                                                                    v-for="opt in emergencyContactTypeOptions"
                                                                    :key="
                                                                        opt.value
                                                                    "
                                                                    :value="
                                                                        opt.value
                                                                    "
                                                                >
                                                                    {{
                                                                        opt.label
                                                                    }}
                                                                </SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                    <div class="grid gap-3">
                                                        <Label
                                                            :for="`emergency_contact_number_${index}`"
                                                            :class="
                                                                stepOneLabelRowClass
                                                            "
                                                        >
                                                            <span
                                                                >Contact
                                                                number</span
                                                            >
                                                            <Badge
                                                                v-if="
                                                                    stepTwoEmergencyContactRowInvalid[
                                                                        index
                                                                    ]
                                                                        ?.contactNumber
                                                                "
                                                                variant="destructive"
                                                            >
                                                                Required
                                                            </Badge>
                                                            <Button
                                                                v-if="
                                                                    contact.contactNumber !==
                                                                    ''
                                                                "
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                :class="
                                                                    clearFieldButtonClass
                                                                "
                                                                aria-label="Clear emergency contact number"
                                                                @click="
                                                                    contact.contactNumber =
                                                                        ''
                                                                "
                                                            >
                                                                <X
                                                                    class="size-3.5"
                                                                />
                                                            </Button>
                                                        </Label>
                                                        <Input
                                                            :id="`emergency_contact_number_${index}`"
                                                            v-model="
                                                                contact.contactNumber
                                                            "
                                                            class="w-full"
                                                            type="tel"
                                                            maxlength="50"
                                                            autocomplete="tel"
                                                            placeholder="e.g. +63 …"
                                                            :aria-invalid="
                                                                stepTwoEmergencyContactRowInvalid[
                                                                    index
                                                                ]?.contactNumber
                                                            "
                                                        />
                                                    </div>
                                                    <div class="grid gap-3">
                                                        <Label
                                                            :for="`emergency_contact_email_${index}`"
                                                            :class="
                                                                stepOneLabelRowClass
                                                            "
                                                        >
                                                            <span>Email</span>
                                                            <Badge
                                                                variant="secondary"
                                                            >
                                                                Optional
                                                            </Badge>
                                                            <Button
                                                                v-if="
                                                                    contact.email !==
                                                                    ''
                                                                "
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                :class="
                                                                    clearFieldButtonClass
                                                                "
                                                                aria-label="Clear emergency email"
                                                                @click="
                                                                    contact.email =
                                                                        ''
                                                                "
                                                            >
                                                                <X
                                                                    class="size-3.5"
                                                                />
                                                            </Button>
                                                        </Label>
                                                        <Input
                                                            :id="`emergency_contact_email_${index}`"
                                                            v-model="
                                                                contact.email
                                                            "
                                                            class="w-full"
                                                            type="email"
                                                            maxlength="255"
                                                            autocomplete="email"
                                                            placeholder="name@example.com"
                                                        />
                                                    </div>
                                                    <div class="grid gap-3">
                                                        <Label
                                                            :for="`emergency_contact_person_${index}`"
                                                            :class="
                                                                stepOneLabelRowClass
                                                            "
                                                        >
                                                            <span
                                                                >Contact
                                                                person</span
                                                            >
                                                            <Badge
                                                                v-if="
                                                                    stepTwoEmergencyContactRowInvalid[
                                                                        index
                                                                    ]
                                                                        ?.contactPerson
                                                                "
                                                                variant="destructive"
                                                            >
                                                                Required
                                                            </Badge>
                                                            <Button
                                                                v-if="
                                                                    contact.contactPerson !==
                                                                    ''
                                                                "
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                :class="
                                                                    clearFieldButtonClass
                                                                "
                                                                aria-label="Clear contact person"
                                                                @click="
                                                                    contact.contactPerson =
                                                                        ''
                                                                "
                                                            >
                                                                <X
                                                                    class="size-3.5"
                                                                />
                                                            </Button>
                                                        </Label>
                                                        <Input
                                                            :id="`emergency_contact_person_${index}`"
                                                            v-model="
                                                                contact.contactPerson
                                                            "
                                                            class="w-full"
                                                            maxlength="150"
                                                            autocomplete="name"
                                                            placeholder="e.g. Maria Dela Cruz"
                                                            :aria-invalid="
                                                                stepTwoEmergencyContactRowInvalid[
                                                                    index
                                                                ]?.contactPerson
                                                            "
                                                        />
                                                    </div>
                                                    <div class="grid gap-3">
                                                        <Label
                                                            :for="`emergency_contact_relationship_${index}`"
                                                            :class="
                                                                stepOneLabelRowClass
                                                            "
                                                        >
                                                            <span
                                                                >Relationship</span
                                                            >
                                                            <Badge
                                                                v-if="
                                                                    stepTwoEmergencyContactRowInvalid[
                                                                        index
                                                                    ]
                                                                        ?.relationship
                                                                "
                                                                variant="destructive"
                                                            >
                                                                Required
                                                            </Badge>
                                                            <Button
                                                                v-if="
                                                                    contact.relationship !==
                                                                    ''
                                                                "
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                :class="
                                                                    clearFieldButtonClass
                                                                "
                                                                aria-label="Clear relationship"
                                                                @click="
                                                                    contact.relationship =
                                                                        ''
                                                                "
                                                            >
                                                                <X
                                                                    class="size-3.5"
                                                                />
                                                            </Button>
                                                        </Label>
                                                        <Input
                                                            :id="`emergency_contact_relationship_${index}`"
                                                            v-model="
                                                                contact.relationship
                                                            "
                                                            class="w-full"
                                                            maxlength="100"
                                                            placeholder="e.g. Spouse"
                                                            :aria-invalid="
                                                                stepTwoEmergencyContactRowInvalid[
                                                                    index
                                                                ]?.relationship
                                                            "
                                                        />
                                                    </div>
                                                </div>
                                                <div
                                                    class="w-full border-t border-primary/30"
                                                    role="presentation"
                                                    aria-hidden="true"
                                                />
                                            </div>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                class="w-full border-dashed border-primary/40 bg-primary/5 hover:bg-primary/10"
                                                aria-label="Add emergency contact"
                                                title="Add emergency contact"
                                                @click="addEmergencyContact"
                                            >
                                                <Plus
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                        </div>
                                    </section>

                                    <section
                                        v-else-if="currentStep === 3"
                                        aria-label="Employment details"
                                        class="min-w-0 space-y-6"
                                    >
                                        <div
                                            class="rounded-lg border border-primary/30 bg-muted/30 p-4 dark:bg-muted/25"
                                        >
                                            <div
                                                class="flex flex-row items-center gap-4"
                                            >
                                                <span
                                                    class="inline-flex shrink-0 items-center justify-center rounded-full bg-primary/10 p-3 text-primary"
                                                >
                                                    <CalendarDays
                                                        class="size-7 shrink-0"
                                                        aria-hidden="true"
                                                    />
                                                </span>
                                                <div
                                                    class="min-w-0 flex-1 space-y-0.5"
                                                >
                                                    <h2
                                                        class="min-w-0 text-base font-semibold text-foreground"
                                                        :class="
                                                            maxSmOneLineTruncateClass
                                                        "
                                                        title="Employment Period"
                                                    >
                                                        Employment Period
                                                    </h2>
                                                    <!--
                                                          employment_status mirrors EmployeeEmployment::
                                                          STATUS_ACTIVE | STATUS_RESIGNED | STATUS_TERMINATED | STATUS_RETIRED | STATUS_CONTRACT_ENDED
                                                          (stored as active, resigned, terminated, retired, contract_ended).
                                                          is_current on the server when separation date is empty.
                                                        -->
                                                    <p
                                                        class="mt-0 text-sm leading-relaxed text-muted-foreground"
                                                        :class="
                                                            maxSmOneLineTruncateClass
                                                        "
                                                        title="Hire date is required; separation is optional. Without a separation date, status must be Active and the record is treated as current employment."
                                                    >
                                                        Hire date is required.
                                                        Separation is
                                                        optional—leave empty for
                                                        current staff (status
                                                        must be Active).
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="min-w-0 space-y-6">
                                            <div
                                                class="grid grid-cols-1 gap-x-4 gap-y-6 md:grid-cols-3"
                                            >
                                                <div class="grid gap-3">
                                                    <Label
                                                        for="employment_hire_date"
                                                        :class="
                                                            stepOneLabelRowClass
                                                        "
                                                    >
                                                        <span>Hire date</span>
                                                        <Badge
                                                            v-if="
                                                                stepThreeEmploymentHireInvalid
                                                            "
                                                            variant="destructive"
                                                        >
                                                            Required
                                                        </Badge>
                                                        <Button
                                                            v-if="
                                                                employmentHireDate !==
                                                                ''
                                                            "
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            :class="
                                                                clearFieldButtonClass
                                                            "
                                                            aria-label="Clear hire date"
                                                            @click="
                                                                clearEmploymentHireDate
                                                            "
                                                        >
                                                            <X
                                                                class="size-3.5"
                                                            />
                                                        </Button>
                                                    </Label>
                                                    <Popover v-slot="{ close }">
                                                        <PopoverTrigger
                                                            as-child
                                                        >
                                                            <Button
                                                                id="employment_hire_date"
                                                                type="button"
                                                                variant="outline"
                                                                class="w-full min-w-0 justify-between gap-2 border-input bg-transparent text-sm font-normal dark:bg-input/30"
                                                                :aria-invalid="
                                                                    stepThreeEmploymentHireInvalid
                                                                "
                                                            >
                                                                <span
                                                                    v-if="
                                                                        employmentPeriodIsoDisplay(
                                                                            employmentHireDate,
                                                                        ) !== ''
                                                                    "
                                                                    class="min-w-0 flex-1 truncate text-left text-foreground"
                                                                >
                                                                    {{
                                                                        employmentPeriodIsoDisplay(
                                                                            employmentHireDate,
                                                                        )
                                                                    }}
                                                                </span>
                                                                <span
                                                                    v-else
                                                                    class="flex-1 text-left text-muted-foreground"
                                                                >
                                                                    Select hire
                                                                    date
                                                                </span>
                                                                <ChevronDownIcon
                                                                    class="size-4 shrink-0 opacity-50"
                                                                    aria-hidden="true"
                                                                />
                                                            </Button>
                                                        </PopoverTrigger>
                                                        <PopoverContent
                                                            class="w-auto overflow-hidden p-0"
                                                            align="start"
                                                        >
                                                            <Calendar
                                                                layout="month-and-year"
                                                                :model-value="
                                                                    employmentPeriodRowCalendarValue(
                                                                        employmentHireDate,
                                                                    )
                                                                "
                                                                @update:model-value="
                                                                    (value) =>
                                                                        onEmploymentHireSelect(
                                                                            value,
                                                                            close,
                                                                        )
                                                                "
                                                            />
                                                        </PopoverContent>
                                                    </Popover>
                                                </div>
                                                <div class="grid gap-3">
                                                    <Label
                                                        for="employment_separation_date"
                                                        :class="
                                                            stepOneLabelRowClass
                                                        "
                                                    >
                                                        <span
                                                            >Separation
                                                            date</span
                                                        >
                                                        <Badge
                                                            variant="secondary"
                                                        >
                                                            Optional
                                                        </Badge>
                                                        <Badge
                                                            v-if="
                                                                stepThreeEmploymentSeparationBeforeHireInvalid
                                                            "
                                                            variant="destructive"
                                                        >
                                                            Before hire
                                                        </Badge>
                                                        <Button
                                                            v-if="
                                                                employmentSeparationDate !==
                                                                ''
                                                            "
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            :class="
                                                                clearFieldButtonClass
                                                            "
                                                            aria-label="Clear separation date"
                                                            @click="
                                                                clearEmploymentSeparationDate
                                                            "
                                                        >
                                                            <X
                                                                class="size-3.5"
                                                            />
                                                        </Button>
                                                    </Label>
                                                    <Popover v-slot="{ close }">
                                                        <PopoverTrigger
                                                            as-child
                                                        >
                                                            <Button
                                                                id="employment_separation_date"
                                                                type="button"
                                                                variant="outline"
                                                                class="w-full min-w-0 justify-between gap-2 border-input bg-transparent text-sm font-normal dark:bg-input/30"
                                                                :disabled="
                                                                    employmentHireDate.trim() ===
                                                                    ''
                                                                "
                                                                :aria-invalid="
                                                                    stepThreeEmploymentSeparationBeforeHireInvalid
                                                                "
                                                            >
                                                                <span
                                                                    v-if="
                                                                        employmentPeriodIsoDisplay(
                                                                            employmentSeparationDate,
                                                                        ) !== ''
                                                                    "
                                                                    class="min-w-0 flex-1 truncate text-left text-foreground"
                                                                >
                                                                    {{
                                                                        employmentPeriodIsoDisplay(
                                                                            employmentSeparationDate,
                                                                        )
                                                                    }}
                                                                </span>
                                                                <span
                                                                    v-else
                                                                    class="min-w-0 flex-1 truncate text-left text-muted-foreground"
                                                                >
                                                                    {{
                                                                        employmentHireDate.trim() ===
                                                                        ''
                                                                            ? 'Select hire date first'
                                                                            : 'Select separation date'
                                                                    }}
                                                                </span>
                                                                <ChevronDownIcon
                                                                    class="size-4 shrink-0 opacity-50"
                                                                    aria-hidden="true"
                                                                />
                                                            </Button>
                                                        </PopoverTrigger>
                                                        <PopoverContent
                                                            class="w-auto overflow-hidden p-0"
                                                            align="start"
                                                        >
                                                            <Calendar
                                                                layout="month-and-year"
                                                                :min-value="
                                                                    employmentHireDate.trim() ===
                                                                    ''
                                                                        ? undefined
                                                                        : employmentPeriodRowCalendarValue(
                                                                              employmentHireDate,
                                                                          )
                                                                "
                                                                :model-value="
                                                                    employmentPeriodRowCalendarValue(
                                                                        employmentSeparationDate,
                                                                    )
                                                                "
                                                                @update:model-value="
                                                                    (value) =>
                                                                        onEmploymentSeparationSelect(
                                                                            value,
                                                                            close,
                                                                        )
                                                                "
                                                            />
                                                        </PopoverContent>
                                                    </Popover>
                                                </div>
                                                <div class="grid gap-3">
                                                    <Label
                                                        for="employment_status"
                                                        :class="
                                                            stepOneLabelRowClass
                                                        "
                                                    >
                                                        <span
                                                            >Employment
                                                            status</span
                                                        >
                                                        <TooltipProvider
                                                            :delay-duration="0"
                                                        >
                                                            <Tooltip>
                                                                <TooltipTrigger
                                                                    as-child
                                                                >
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        class="size-6 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                                                                        aria-label="Employment status guidance"
                                                                    >
                                                                        <Info
                                                                            class="size-3.5"
                                                                            aria-hidden="true"
                                                                        />
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent
                                                                    side="top"
                                                                    class="max-w-xs text-pretty"
                                                                >
                                                                    Must be
                                                                    Active when
                                                                    there is no
                                                                    separation
                                                                    date;
                                                                    otherwise
                                                                    choose
                                                                    Resigned,
                                                                    Terminated,
                                                                    Retired, or
                                                                    Contract
                                                                    Ended.
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </TooltipProvider>
                                                        <Badge
                                                            v-if="
                                                                stepThreeEmploymentStatusLogicInvalid
                                                            "
                                                            variant="destructive"
                                                        >
                                                            Check rules
                                                        </Badge>
                                                    </Label>
                                                    <Select
                                                        v-model="
                                                            employmentStatus
                                                        "
                                                    >
                                                        <SelectTrigger
                                                            id="employment_status"
                                                            class="w-full min-w-0"
                                                            :aria-invalid="
                                                                stepThreeEmploymentStatusLogicInvalid
                                                            "
                                                        >
                                                            <SelectValue
                                                                placeholder="Select status"
                                                            />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                v-for="statusKey in selectableEmploymentStatusKeys"
                                                                :key="statusKey"
                                                                :value="
                                                                    statusKey
                                                                "
                                                            >
                                                                {{
                                                                    employmentStatusLabels[
                                                                        statusKey
                                                                    ]
                                                                }}
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            v-if="
                                                employmentSeparationDate.trim() !==
                                                ''
                                            "
                                            class="grid grid-cols-1 gap-x-4 gap-y-6 md:grid-cols-2"
                                        >
                                            <div class="grid gap-3">
                                                <Label
                                                    for="employment_separation_reason"
                                                    :class="
                                                        stepOneLabelRowClass
                                                    "
                                                >
                                                    <span
                                                        >Separation reason</span
                                                    >
                                                    <Badge variant="secondary">
                                                        Optional
                                                    </Badge>
                                                    <Button
                                                        v-if="
                                                            employmentSeparationReason.trim() !==
                                                            ''
                                                        "
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        :class="
                                                            clearFieldButtonClass
                                                        "
                                                        aria-label="Clear separation reason"
                                                        @click="
                                                            employmentSeparationReason =
                                                                ''
                                                        "
                                                    >
                                                        <X class="size-3.5" />
                                                    </Button>
                                                </Label>
                                                <Textarea
                                                    id="employment_separation_reason"
                                                    v-model="
                                                        employmentSeparationReason
                                                    "
                                                    rows="3"
                                                    class="min-h-26 resize-none"
                                                    maxlength="300"
                                                    placeholder="Reason for separation"
                                                />
                                            </div>
                                            <div class="grid gap-3">
                                                <Label
                                                    for="employment_notes"
                                                    :class="
                                                        stepOneLabelRowClass
                                                    "
                                                >
                                                    <span>Notes</span>
                                                    <Badge variant="secondary">
                                                        Optional
                                                    </Badge>
                                                    <Button
                                                        v-if="
                                                            employmentNotes.trim() !==
                                                            ''
                                                        "
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        :class="
                                                            clearFieldButtonClass
                                                        "
                                                        aria-label="Clear employment notes"
                                                        @click="
                                                            employmentNotes = ''
                                                        "
                                                    >
                                                        <X class="size-3.5" />
                                                    </Button>
                                                </Label>
                                                <Textarea
                                                    id="employment_notes"
                                                    v-model="employmentNotes"
                                                    rows="3"
                                                    class="min-h-26 resize-none"
                                                    maxlength="300"
                                                    placeholder="Additional employment notes"
                                                />
                                            </div>
                                        </div>
                                        <div
                                            class="-mx-6 mt-10 shrink-0 border-t border-border"
                                            role="separator"
                                            aria-hidden="true"
                                        />
                                        <div
                                            class="rounded-lg border border-primary/30 bg-muted/30 p-4 dark:bg-muted/25"
                                        >
                                            <div
                                                class="flex flex-row items-center gap-4"
                                            >
                                                <span
                                                    class="inline-flex shrink-0 items-center justify-center rounded-full bg-primary/10 p-3 text-primary"
                                                >
                                                    <IdCard
                                                        class="size-7 shrink-0"
                                                        aria-hidden="true"
                                                    />
                                                </span>
                                                <div
                                                    class="min-w-0 flex-1 space-y-0.5"
                                                >
                                                    <h2
                                                        class="min-w-0 text-base font-semibold text-foreground"
                                                        :class="
                                                            maxSmOneLineTruncateClass
                                                        "
                                                        title="Company ID"
                                                    >
                                                        Company ID
                                                    </h2>
                                                    <p
                                                        class="mt-0 text-sm leading-relaxed text-muted-foreground"
                                                        :class="
                                                            maxSmOneLineTruncateClass
                                                        "
                                                        title="Company ID is required and unique. Attendance ID is optional—for time clocks or access systems when different."
                                                    >
                                                        Company ID is required
                                                        and unique. Attendance
                                                        ID is optional—for time
                                                        clocks or access when
                                                        different.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="min-w-0">
                                            <div
                                                class="grid grid-cols-1 gap-x-4 gap-y-6 md:grid-cols-2"
                                            >
                                                <div class="grid gap-3">
                                                    <Label
                                                        for="employment_id_number"
                                                        :class="
                                                            stepOneLabelRowClass
                                                        "
                                                    >
                                                        <span>ID number</span>
                                                        <Badge
                                                            v-if="
                                                                stepThreeIdNumberInvalid
                                                            "
                                                            variant="destructive"
                                                        >
                                                            Required
                                                        </Badge>
                                                        <Badge
                                                            v-else-if="
                                                                idNumberAvailability.status ===
                                                                'checking'
                                                            "
                                                            variant="secondary"
                                                        >
                                                            Checking...
                                                        </Badge>
                                                        <Badge
                                                            v-else-if="
                                                                idNumberAvailability.status ===
                                                                'taken'
                                                            "
                                                            variant="destructive"
                                                        >
                                                            Already used
                                                        </Badge>
                                                        <Badge
                                                            v-else-if="
                                                                idNumberAvailability.status ===
                                                                'available'
                                                            "
                                                            class="bg-green-600/15 text-green-700 dark:text-green-300"
                                                        >
                                                            Available
                                                        </Badge>
                                                        <Button
                                                            v-if="
                                                                employmentIdNumber !==
                                                                ''
                                                            "
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            :class="
                                                                clearFieldButtonClass
                                                            "
                                                            aria-label="Clear ID number"
                                                            @click="
                                                                employmentIdNumber =
                                                                    ''
                                                            "
                                                        >
                                                            <X
                                                                class="size-3.5"
                                                            />
                                                        </Button>
                                                    </Label>
                                                    <Input
                                                        id="employment_id_number"
                                                        v-model="
                                                            employmentIdNumber
                                                        "
                                                        class="w-full"
                                                        maxlength="50"
                                                        autocomplete="off"
                                                        placeholder="e.g. EMP-001"
                                                        :aria-invalid="
                                                            stepThreeIdNumberInvalid ||
                                                            idNumberAvailability.status ===
                                                                'taken'
                                                        "
                                                    />
                                                    <p
                                                        class="min-h-4 text-xs"
                                                        :class="
                                                            availabilityStatusClass(
                                                                idNumberAvailability.status,
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            idNumberAvailability.status !==
                                                            'idle'
                                                                ? idNumberAvailability.message
                                                                : '\u00A0'
                                                        }}
                                                    </p>
                                                </div>
                                                <div class="grid gap-3">
                                                    <Label
                                                        for="employment_attendance_id"
                                                        :class="
                                                            stepOneLabelRowClass
                                                        "
                                                    >
                                                        <span
                                                            >Attendance ID</span
                                                        >
                                                        <Badge
                                                            variant="secondary"
                                                        >
                                                            Optional
                                                        </Badge>
                                                        <Badge
                                                            v-if="
                                                                attendanceIdAvailability.status ===
                                                                'checking'
                                                            "
                                                            variant="secondary"
                                                        >
                                                            Checking...
                                                        </Badge>
                                                        <Badge
                                                            v-else-if="
                                                                attendanceIdAvailability.status ===
                                                                'taken'
                                                            "
                                                            variant="destructive"
                                                        >
                                                            Already used
                                                        </Badge>
                                                        <Badge
                                                            v-else-if="
                                                                attendanceIdAvailability.status ===
                                                                'available'
                                                            "
                                                            class="bg-green-600/15 text-green-700 dark:text-green-300"
                                                        >
                                                            Available
                                                        </Badge>
                                                        <Button
                                                            v-if="
                                                                employmentAttendanceId !==
                                                                ''
                                                            "
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            :class="
                                                                clearFieldButtonClass
                                                            "
                                                            aria-label="Clear Attendance ID"
                                                            @click="
                                                                employmentAttendanceId =
                                                                    ''
                                                            "
                                                        >
                                                            <X
                                                                class="size-3.5"
                                                            />
                                                        </Button>
                                                    </Label>
                                                    <Input
                                                        id="employment_attendance_id"
                                                        v-model="
                                                            employmentAttendanceId
                                                        "
                                                        class="w-full"
                                                        maxlength="50"
                                                        autocomplete="off"
                                                        placeholder="e.g. ZK-10042, RFID, or device code"
                                                        :aria-invalid="
                                                            attendanceIdAvailability.status ===
                                                            'taken'
                                                        "
                                                    />
                                                    <p
                                                        class="min-h-4 text-xs"
                                                        :class="
                                                            availabilityStatusClass(
                                                                attendanceIdAvailability.status,
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            attendanceIdAvailability.status !==
                                                            'idle'
                                                                ? attendanceIdAvailability.message
                                                                : '\u00A0'
                                                        }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            class="-mx-6 mt-10 shrink-0 border-t border-border"
                                            role="separator"
                                            aria-hidden="true"
                                        />
                                        <div
                                            class="rounded-lg border border-primary/30 bg-muted/30 p-4 dark:bg-muted/25"
                                        >
                                            <div
                                                class="flex flex-row items-center gap-4"
                                            >
                                                <span
                                                    class="inline-flex shrink-0 items-center justify-center rounded-full bg-primary/10 p-3 text-primary"
                                                >
                                                    <Briefcase
                                                        class="size-7 shrink-0"
                                                        aria-hidden="true"
                                                    />
                                                </span>
                                                <div
                                                    class="min-w-0 flex-1 space-y-0.5"
                                                >
                                                    <h2
                                                        class="min-w-0 text-base font-semibold text-foreground"
                                                        :class="
                                                            maxSmOneLineTruncateClass
                                                        "
                                                        title="Employee Positions"
                                                    >
                                                        Employee Positions
                                                    </h2>
                                                    <p
                                                        class="mt-0 text-sm leading-relaxed text-muted-foreground"
                                                        :class="
                                                            maxSmOneLineTruncateClass
                                                        "
                                                        title="Job titles and roles from your organization's position catalog."
                                                    >
                                                        Their job titles and
                                                        roles from your
                                                        organization's position
                                                        catalog.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <!--
                                                Maps to `employee_positions` repeater on employee create.
                                                Kept outside the section card (same pattern as Personal Contacts).
                                            -->
                                        <div class="min-w-0 space-y-6">
                                            <p
                                                v-if="positions.length === 0"
                                                class="text-sm text-muted-foreground"
                                                :class="
                                                    maxSmOneLineTruncateClass
                                                "
                                            >
                                                No active positions for this
                                                organization. Add catalog rows
                                                under Positions, or check
                                                default organization
                                                configuration.
                                            </p>
                                            <div
                                                v-for="(
                                                    row, index
                                                ) in employeePositionRows"
                                                :key="`employee-position-${index}`"
                                                class="space-y-6"
                                            >
                                                <div
                                                    class="flex min-w-0 items-center gap-3"
                                                >
                                                    <div
                                                        class="flex shrink-0 items-center gap-2"
                                                    >
                                                        <Checkbox
                                                            :id="`employee_position_is_primary_${index}`"
                                                            :model-value="
                                                                row.isPrimary
                                                            "
                                                            class="shrink-0 self-center"
                                                            :disabled="
                                                                row.endDate !==
                                                                    '' &&
                                                                employmentSeparationDate.trim() ===
                                                                    ''
                                                            "
                                                            :aria-labelledby="`employee_position_primary_heading_${index}`"
                                                            @update:model-value="
                                                                (value) =>
                                                                    setPrimaryEmployeePosition(
                                                                        index,
                                                                        value,
                                                                    )
                                                            "
                                                        />
                                                        <div
                                                            class="inline-flex items-center gap-2 whitespace-nowrap"
                                                        >
                                                            <label
                                                                :id="`employee_position_primary_heading_${index}`"
                                                                class="cursor-pointer"
                                                                :class="
                                                                    row.endDate !==
                                                                        '' &&
                                                                    employmentSeparationDate.trim() ===
                                                                        ''
                                                                        ? 'cursor-not-allowed opacity-60'
                                                                        : ''
                                                                "
                                                                :for="`employee_position_is_primary_${index}`"
                                                            >
                                                                <Badge
                                                                    >Primary</Badge
                                                                >
                                                            </label>
                                                            <Badge
                                                                v-if="
                                                                    stepThreePositionMissingPrimary &&
                                                                    stepThreeCatalogHasPositions
                                                                "
                                                                variant="destructive"
                                                                class="shrink-0"
                                                            >
                                                                Required
                                                            </Badge>
                                                            <Badge
                                                                v-if="
                                                                    stepThreePositionMissingActive
                                                                "
                                                                variant="destructive"
                                                                class="shrink-0"
                                                            >
                                                                Active required
                                                            </Badge>
                                                        </div>
                                                    </div>
                                                    <div
                                                        :class="
                                                            primaryToggleRuleClass
                                                        "
                                                        role="presentation"
                                                        aria-hidden="true"
                                                    />
                                                    <Button
                                                        v-if="index > 0"
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        class="shrink-0 text-muted-foreground hover:text-destructive"
                                                        @click="
                                                            removeEmployeePosition(
                                                                index,
                                                            )
                                                        "
                                                    >
                                                        Remove
                                                    </Button>
                                                </div>
                                                <div
                                                    class="grid grid-cols-1 gap-x-4 gap-y-6 md:grid-cols-3"
                                                >
                                                    <div class="grid gap-3">
                                                        <Label
                                                            :for="`employee_position_position_${index}`"
                                                            :class="
                                                                stepOneLabelRowClass
                                                            "
                                                        >
                                                            <span
                                                                >Position</span
                                                            >
                                                            <Badge
                                                                v-if="
                                                                    stepThreeEmployeePositionRowInvalid[
                                                                        index
                                                                    ]
                                                                        ?.positionId
                                                                "
                                                                variant="destructive"
                                                            >
                                                                Required
                                                            </Badge>
                                                            <Button
                                                                v-if="
                                                                    row.positionId !==
                                                                    ''
                                                                "
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                :class="
                                                                    clearFieldButtonClass
                                                                "
                                                                aria-label="Clear position"
                                                                @click="
                                                                    row.positionId =
                                                                        ''
                                                                "
                                                            >
                                                                <X
                                                                    class="size-3.5"
                                                                />
                                                            </Button>
                                                        </Label>
                                                        <EmployeePositionPicker
                                                            v-model="
                                                                row.positionId
                                                            "
                                                            :positions="
                                                                positions
                                                            "
                                                            :disabled="
                                                                positions.length ===
                                                                0
                                                            "
                                                            :trigger-id="`employee_position_position_${index}`"
                                                            :aria-invalid="
                                                                stepThreeEmployeePositionRowInvalid[
                                                                    index
                                                                ]?.positionId
                                                            "
                                                        />
                                                    </div>
                                                    <div class="grid gap-3">
                                                        <Label
                                                            :for="`employee_position_start_${index}`"
                                                            :class="
                                                                stepOneLabelRowClass
                                                            "
                                                        >
                                                            <span
                                                                >Start
                                                                date</span
                                                            >
                                                            <Badge
                                                                v-if="
                                                                    stepThreeEmployeePositionRowInvalid[
                                                                        index
                                                                    ]?.startDate
                                                                "
                                                                variant="destructive"
                                                            >
                                                                Required
                                                            </Badge>
                                                            <Badge
                                                                v-if="
                                                                    stepThreeEmployeePositionRowInvalid[
                                                                        index
                                                                    ]
                                                                        ?.startBeforeHire
                                                                "
                                                                variant="destructive"
                                                            >
                                                                On or after hire
                                                            </Badge>
                                                            <Badge
                                                                v-if="
                                                                    stepThreeEmployeePositionRowInvalid[
                                                                        index
                                                                    ]
                                                                        ?.startVersusSeparation
                                                                "
                                                                variant="destructive"
                                                            >
                                                                On or before
                                                                separation
                                                            </Badge>
                                                            <Button
                                                                v-if="
                                                                    row.startDate !==
                                                                    ''
                                                                "
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                :class="
                                                                    clearFieldButtonClass
                                                                "
                                                                aria-label="Clear start date"
                                                                @click="
                                                                    clearEmployeePositionRowStart(
                                                                        index,
                                                                    )
                                                                "
                                                            >
                                                                <X
                                                                    class="size-3.5"
                                                                />
                                                            </Button>
                                                        </Label>
                                                        <Popover
                                                            v-slot="{ close }"
                                                        >
                                                            <PopoverTrigger
                                                                as-child
                                                            >
                                                                <Button
                                                                    :id="`employee_position_start_${index}`"
                                                                    type="button"
                                                                    variant="outline"
                                                                    class="w-full min-w-0 justify-between gap-2 border-input bg-transparent text-sm font-normal dark:bg-input/30"
                                                                    :aria-invalid="
                                                                        !!(
                                                                            stepThreeEmployeePositionRowInvalid[
                                                                                index
                                                                            ]
                                                                                ?.startDate ||
                                                                            stepThreeEmployeePositionRowInvalid[
                                                                                index
                                                                            ]
                                                                                ?.startBeforeHire ||
                                                                            stepThreeEmployeePositionRowInvalid[
                                                                                index
                                                                            ]
                                                                                ?.startVersusSeparation
                                                                        )
                                                                    "
                                                                >
                                                                    <span
                                                                        v-if="
                                                                            employeePositionIsoDisplay(
                                                                                row.startDate,
                                                                            ) !==
                                                                            ''
                                                                        "
                                                                        class="min-w-0 flex-1 truncate text-left text-foreground"
                                                                    >
                                                                        {{
                                                                            employeePositionIsoDisplay(
                                                                                row.startDate,
                                                                            )
                                                                        }}
                                                                    </span>
                                                                    <span
                                                                        v-else
                                                                        class="flex-1 text-left text-muted-foreground"
                                                                    >
                                                                        Select
                                                                        start
                                                                        date
                                                                    </span>
                                                                    <ChevronDownIcon
                                                                        class="size-4 shrink-0 opacity-50"
                                                                        aria-hidden="true"
                                                                    />
                                                                </Button>
                                                            </PopoverTrigger>
                                                            <PopoverContent
                                                                class="w-auto overflow-hidden p-0"
                                                                align="start"
                                                            >
                                                                <Calendar
                                                                    layout="month-and-year"
                                                                    :min-value="
                                                                        employmentHireCalendarMin
                                                                    "
                                                                    :max-value="
                                                                        employmentSeparationCalendarMax
                                                                    "
                                                                    :model-value="
                                                                        employeePositionRowCalendarValue(
                                                                            row.startDate,
                                                                        )
                                                                    "
                                                                    @update:model-value="
                                                                        (
                                                                            value,
                                                                        ) =>
                                                                            onEmployeePositionRowStartSelect(
                                                                                index,
                                                                                value,
                                                                                close,
                                                                            )
                                                                    "
                                                                />
                                                            </PopoverContent>
                                                        </Popover>
                                                    </div>
                                                    <div class="grid gap-3">
                                                        <Label
                                                            :for="`employee_position_end_${index}`"
                                                            :class="
                                                                stepOneLabelRowClass
                                                            "
                                                        >
                                                            <span
                                                                >End date</span
                                                            >
                                                            <Badge
                                                                v-if="
                                                                    stepThreeEmployeePositionRowInvalid[
                                                                        index
                                                                    ]?.endDate
                                                                "
                                                                variant="destructive"
                                                            >
                                                                Required
                                                            </Badge>
                                                            <Badge
                                                                v-if="
                                                                    stepThreeEmployeePositionRowInvalid[
                                                                        index
                                                                    ]
                                                                        ?.endBeforeHire
                                                                "
                                                                variant="destructive"
                                                            >
                                                                On or after hire
                                                            </Badge>
                                                            <Badge
                                                                v-if="
                                                                    stepThreeEmployeePositionRowInvalid[
                                                                        index
                                                                    ]
                                                                        ?.endVersusSeparation
                                                                "
                                                                variant="destructive"
                                                            >
                                                                On or before
                                                                separation
                                                            </Badge>
                                                            <template
                                                                v-if="
                                                                    !stepThreeEmployeePositionRowInvalid[
                                                                        index
                                                                    ]
                                                                        ?.endDate &&
                                                                    !stepThreeEmployeePositionRowInvalid[
                                                                        index
                                                                    ]
                                                                        ?.endBeforeHire &&
                                                                    !stepThreeEmployeePositionRowInvalid[
                                                                        index
                                                                    ]
                                                                        ?.endVersusSeparation
                                                                "
                                                            >
                                                                <Badge
                                                                    v-if="
                                                                        employmentSeparationDate.trim() !==
                                                                        ''
                                                                    "
                                                                    variant="secondary"
                                                                >
                                                                    Required
                                                                    when
                                                                    separated
                                                                </Badge>
                                                                <Badge
                                                                    v-else
                                                                    variant="secondary"
                                                                >
                                                                    Optional
                                                                </Badge>
                                                            </template>
                                                            <Button
                                                                v-if="
                                                                    row.endDate !==
                                                                    ''
                                                                "
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                :class="
                                                                    clearFieldButtonClass
                                                                "
                                                                aria-label="Clear end date"
                                                                @click="
                                                                    clearEmployeePositionRowEnd(
                                                                        index,
                                                                    )
                                                                "
                                                            >
                                                                <X
                                                                    class="size-3.5"
                                                                />
                                                            </Button>
                                                        </Label>
                                                        <Popover
                                                            v-slot="{ close }"
                                                        >
                                                            <PopoverTrigger
                                                                as-child
                                                            >
                                                                <Button
                                                                    :id="`employee_position_end_${index}`"
                                                                    type="button"
                                                                    variant="outline"
                                                                    class="w-full min-w-0 justify-between gap-2 border-input bg-transparent text-sm font-normal dark:bg-input/30"
                                                                    :disabled="
                                                                        row.startDate ===
                                                                        ''
                                                                    "
                                                                    :aria-invalid="
                                                                        !!stepThreeEmployeePositionRowInvalid[
                                                                            index
                                                                        ]
                                                                            ?.endDate ||
                                                                        !!stepThreeEmployeePositionRowInvalid[
                                                                            index
                                                                        ]
                                                                            ?.endBeforeHire ||
                                                                        !!stepThreeEmployeePositionRowInvalid[
                                                                            index
                                                                        ]
                                                                            ?.endVersusSeparation
                                                                    "
                                                                >
                                                                    <span
                                                                        v-if="
                                                                            employeePositionIsoDisplay(
                                                                                row.endDate,
                                                                            ) !==
                                                                            ''
                                                                        "
                                                                        class="min-w-0 flex-1 truncate text-left text-foreground"
                                                                    >
                                                                        {{
                                                                            employeePositionIsoDisplay(
                                                                                row.endDate,
                                                                            )
                                                                        }}
                                                                    </span>
                                                                    <span
                                                                        v-else
                                                                        class="min-w-0 flex-1 truncate text-left text-muted-foreground"
                                                                    >
                                                                        {{
                                                                            row.startDate ===
                                                                            ''
                                                                                ? 'Select start date first'
                                                                                : 'Select end date'
                                                                        }}
                                                                    </span>
                                                                    <ChevronDownIcon
                                                                        class="size-4 shrink-0 opacity-50"
                                                                        aria-hidden="true"
                                                                    />
                                                                </Button>
                                                            </PopoverTrigger>
                                                            <PopoverContent
                                                                class="w-auto overflow-hidden p-0"
                                                                align="start"
                                                            >
                                                                <Calendar
                                                                    layout="month-and-year"
                                                                    :min-value="
                                                                        stepThreePositionEndCalendarMin(
                                                                            row,
                                                                        )
                                                                    "
                                                                    :max-value="
                                                                        employmentSeparationCalendarMax
                                                                    "
                                                                    :model-value="
                                                                        employeePositionRowCalendarValue(
                                                                            row.endDate,
                                                                        )
                                                                    "
                                                                    @update:model-value="
                                                                        (
                                                                            value,
                                                                        ) =>
                                                                            onEmployeePositionRowEndSelect(
                                                                                index,
                                                                                value,
                                                                                close,
                                                                            )
                                                                    "
                                                                />
                                                            </PopoverContent>
                                                        </Popover>
                                                    </div>
                                                </div>
                                                <div
                                                    class="w-full border-t border-primary/30"
                                                    role="presentation"
                                                    aria-hidden="true"
                                                />
                                            </div>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                class="w-full border-dashed border-primary/40 bg-primary/5 hover:bg-primary/10"
                                                aria-label="Add employee position"
                                                title="Add employee position"
                                                @click="addEmployeePosition"
                                            >
                                                <Plus
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                        </div>
                                        <div
                                            class="-mx-6 mt-10 shrink-0 border-t border-border"
                                            role="separator"
                                            aria-hidden="true"
                                        />
                                        <div
                                            class="mt-8 rounded-lg border border-primary/30 bg-muted/30 p-4 dark:bg-muted/25"
                                        >
                                            <div
                                                class="flex flex-row items-center gap-4"
                                            >
                                                <span
                                                    class="inline-flex shrink-0 items-center justify-center rounded-full bg-primary/10 p-3 text-primary"
                                                >
                                                    <Building2
                                                        class="size-7 shrink-0"
                                                        aria-hidden="true"
                                                    />
                                                </span>
                                                <div
                                                    class="min-w-0 flex-1 space-y-0.5"
                                                >
                                                    <h2
                                                        class="min-w-0 text-base font-semibold text-foreground"
                                                        :class="
                                                            maxSmOneLineTruncateClass
                                                        "
                                                        title="Employee Affiliations"
                                                    >
                                                        Employee Affiliations
                                                    </h2>
                                                    <p
                                                        class="mt-0 text-sm leading-relaxed text-muted-foreground"
                                                        :class="
                                                            maxSmOneLineTruncateClass
                                                        "
                                                        title="Which organization they belong to, and optionally which root branch."
                                                    >
                                                        Which organization they
                                                        belong to, and
                                                        optionally which root
                                                        branch (e.g. head office
                                                        or a regional branch).
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <!--
                                                Maps to future `employee_affiliations` repeater; `organization_id` uses
                                                hidden `affiliationOrganization.id` when `rootUnitId` is empty (org-wide).
                                            -->
                                        <div
                                            v-if="affiliationOrganization"
                                            class="min-w-0 space-y-6"
                                        >
                                            <div
                                                v-for="(
                                                    row, index
                                                ) in employeeAffiliationRows"
                                                :key="`employee-affiliation-${index}`"
                                                class="space-y-6"
                                            >
                                                <div
                                                    class="flex min-w-0 items-center gap-3"
                                                >
                                                    <div
                                                        class="flex shrink-0 items-center gap-2"
                                                    >
                                                        <Checkbox
                                                            :id="`employee_affiliation_is_primary_${index}`"
                                                            :model-value="
                                                                row.isPrimary
                                                            "
                                                            class="shrink-0 self-center"
                                                            :disabled="
                                                                row.endDate !==
                                                                    '' &&
                                                                employmentSeparationDate.trim() ===
                                                                    ''
                                                            "
                                                            :aria-labelledby="`employee_affiliation_primary_heading_${index}`"
                                                            @update:model-value="
                                                                (value) =>
                                                                    setPrimaryEmployeeAffiliation(
                                                                        index,
                                                                        value,
                                                                    )
                                                            "
                                                        />
                                                        <div
                                                            class="inline-flex items-center gap-2 whitespace-nowrap"
                                                        >
                                                            <label
                                                                :id="`employee_affiliation_primary_heading_${index}`"
                                                                class="cursor-pointer"
                                                                :class="
                                                                    row.endDate !==
                                                                        '' &&
                                                                    employmentSeparationDate.trim() ===
                                                                        ''
                                                                        ? 'cursor-not-allowed opacity-60'
                                                                        : ''
                                                                "
                                                                :for="`employee_affiliation_is_primary_${index}`"
                                                            >
                                                                <Badge
                                                                    >Primary</Badge
                                                                >
                                                            </label>
                                                            <Badge
                                                                v-if="
                                                                    stepThreeAffiliationMissingPrimary
                                                                "
                                                                variant="destructive"
                                                                class="shrink-0"
                                                            >
                                                                Required
                                                            </Badge>
                                                            <Badge
                                                                v-if="
                                                                    stepThreeAffiliationMissingActive
                                                                "
                                                                variant="destructive"
                                                                class="shrink-0"
                                                            >
                                                                Active required
                                                            </Badge>
                                                        </div>
                                                    </div>
                                                    <div
                                                        :class="
                                                            primaryToggleRuleClass
                                                        "
                                                        role="presentation"
                                                        aria-hidden="true"
                                                    />
                                                    <Button
                                                        v-if="index > 0"
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        class="shrink-0 text-muted-foreground hover:text-destructive"
                                                        @click="
                                                            removeEmployeeAffiliation(
                                                                index,
                                                            )
                                                        "
                                                    >
                                                        Remove
                                                    </Button>
                                                </div>
                                                <div
                                                    class="grid grid-cols-1 gap-x-4 gap-y-6 md:grid-cols-3"
                                                >
                                                    <div class="grid gap-3">
                                                        <Label
                                                            :for="`employee_affiliation_root_${index}`"
                                                            :class="
                                                                stepOneLabelRowClass
                                                            "
                                                        >
                                                            <span
                                                                >Assigned
                                                                branch</span
                                                            >
                                                            <Badge
                                                                variant="outline"
                                                            >
                                                                Optional
                                                            </Badge>
                                                            <Button
                                                                v-if="
                                                                    row.rootUnitId !==
                                                                    ''
                                                                "
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                :class="
                                                                    clearFieldButtonClass
                                                                "
                                                                aria-label="Clear assigned branch"
                                                                @click="
                                                                    row.rootUnitId =
                                                                        ''
                                                                "
                                                            >
                                                                <X
                                                                    class="size-3.5"
                                                                />
                                                            </Button>
                                                        </Label>
                                                        <Select
                                                            :model-value="
                                                                row.rootUnitId ===
                                                                    '' &&
                                                                allowOrgWideAffiliation
                                                                    ? employeeAffiliationRootNoneValue
                                                                    : row.rootUnitId
                                                            "
                                                            @update:model-value="
                                                                (v) => {
                                                                    row.rootUnitId =
                                                                        v ===
                                                                        employeeAffiliationRootNoneValue
                                                                            ? ''
                                                                            : String(
                                                                                  v,
                                                                              );
                                                                }
                                                            "
                                                        >
                                                            <SelectTrigger
                                                                :id="`employee_affiliation_root_${index}`"
                                                                class="w-full min-w-0"
                                                            >
                                                                <SelectValue
                                                                    :placeholder="
                                                                        allowOrgWideAffiliation
                                                                            ? 'Org-wide (no branch)'
                                                                            : 'Select branch'
                                                                    "
                                                                />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem
                                                                    v-if="
                                                                        allowOrgWideAffiliation
                                                                    "
                                                                    :value="
                                                                        employeeAffiliationRootNoneValue
                                                                    "
                                                                >
                                                                    Org-wide (no
                                                                    branch)
                                                                </SelectItem>
                                                                <SelectGroup
                                                                    v-for="group in affiliationRootsByGroup"
                                                                    :key="
                                                                        group.group_label
                                                                    "
                                                                >
                                                                    <SelectLabel
                                                                        >{{
                                                                            group.group_label
                                                                        }}</SelectLabel
                                                                    >
                                                                    <SelectItem
                                                                        v-for="u in group.items"
                                                                        :key="
                                                                            u.id
                                                                        "
                                                                        :value="
                                                                            String(
                                                                                u.id,
                                                                            )
                                                                        "
                                                                    >
                                                                        {{
                                                                            u.area_name
                                                                                ? `${u.name} — ${u.area_name}`
                                                                                : u.name
                                                                        }}
                                                                    </SelectItem>
                                                                </SelectGroup>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                    <div class="grid gap-3">
                                                        <Label
                                                            :for="`employee_affiliation_start_${index}`"
                                                            :class="
                                                                stepOneLabelRowClass
                                                            "
                                                        >
                                                            <span
                                                                >Start
                                                                date</span
                                                            >
                                                            <Badge
                                                                v-if="
                                                                    stepThreeAffiliationRowInvalid[
                                                                        index
                                                                    ]?.startDate
                                                                "
                                                                variant="destructive"
                                                            >
                                                                Required
                                                            </Badge>
                                                            <Badge
                                                                v-if="
                                                                    stepThreeAffiliationRowInvalid[
                                                                        index
                                                                    ]
                                                                        ?.startBeforeHire
                                                                "
                                                                variant="destructive"
                                                            >
                                                                On or after hire
                                                            </Badge>
                                                            <Badge
                                                                v-if="
                                                                    stepThreeAffiliationRowInvalid[
                                                                        index
                                                                    ]
                                                                        ?.startVersusSeparation
                                                                "
                                                                variant="destructive"
                                                            >
                                                                On or before
                                                                separation
                                                            </Badge>
                                                            <Button
                                                                v-if="
                                                                    row.startDate !==
                                                                    ''
                                                                "
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                :class="
                                                                    clearFieldButtonClass
                                                                "
                                                                aria-label="Clear start date"
                                                                @click="
                                                                    clearEmployeeAffiliationRowStart(
                                                                        index,
                                                                    )
                                                                "
                                                            >
                                                                <X
                                                                    class="size-3.5"
                                                                />
                                                            </Button>
                                                        </Label>
                                                        <Popover
                                                            v-slot="{ close }"
                                                        >
                                                            <PopoverTrigger
                                                                as-child
                                                            >
                                                                <Button
                                                                    :id="`employee_affiliation_start_${index}`"
                                                                    type="button"
                                                                    variant="outline"
                                                                    class="w-full min-w-0 justify-between gap-2 border-input bg-transparent text-sm font-normal dark:bg-input/30"
                                                                    :aria-invalid="
                                                                        !!(
                                                                            stepThreeAffiliationRowInvalid[
                                                                                index
                                                                            ]
                                                                                ?.startDate ||
                                                                            stepThreeAffiliationRowInvalid[
                                                                                index
                                                                            ]
                                                                                ?.startBeforeHire ||
                                                                            stepThreeAffiliationRowInvalid[
                                                                                index
                                                                            ]
                                                                                ?.startVersusSeparation
                                                                        )
                                                                    "
                                                                >
                                                                    <span
                                                                        v-if="
                                                                            employeeAffiliationIsoDisplay(
                                                                                row.startDate,
                                                                            ) !==
                                                                            ''
                                                                        "
                                                                        class="min-w-0 flex-1 truncate text-left text-foreground"
                                                                    >
                                                                        {{
                                                                            employeeAffiliationIsoDisplay(
                                                                                row.startDate,
                                                                            )
                                                                        }}
                                                                    </span>
                                                                    <span
                                                                        v-else
                                                                        class="flex-1 truncate text-left text-muted-foreground"
                                                                    >
                                                                        Select
                                                                        start
                                                                        date
                                                                    </span>
                                                                    <ChevronDownIcon
                                                                        class="size-4 shrink-0 opacity-50"
                                                                        aria-hidden="true"
                                                                    />
                                                                </Button>
                                                            </PopoverTrigger>
                                                            <PopoverContent
                                                                class="w-auto overflow-hidden p-0"
                                                                align="start"
                                                            >
                                                                <Calendar
                                                                    layout="month-and-year"
                                                                    :min-value="
                                                                        employmentHireCalendarMin
                                                                    "
                                                                    :max-value="
                                                                        employmentSeparationCalendarMax
                                                                    "
                                                                    :model-value="
                                                                        employeeAffiliationRowCalendarValue(
                                                                            row.startDate,
                                                                        )
                                                                    "
                                                                    @update:model-value="
                                                                        (
                                                                            value,
                                                                        ) =>
                                                                            onEmployeeAffiliationRowStartSelect(
                                                                                index,
                                                                                value,
                                                                                close,
                                                                            )
                                                                    "
                                                                />
                                                            </PopoverContent>
                                                        </Popover>
                                                    </div>
                                                    <div class="grid gap-3">
                                                        <Label
                                                            :for="`employee_affiliation_end_${index}`"
                                                            :class="
                                                                stepOneLabelRowClass
                                                            "
                                                        >
                                                            <span
                                                                >End date</span
                                                            >
                                                            <Badge
                                                                v-if="
                                                                    stepThreeAffiliationRowInvalid[
                                                                        index
                                                                    ]?.endDate
                                                                "
                                                                variant="destructive"
                                                            >
                                                                Required
                                                            </Badge>
                                                            <Badge
                                                                v-if="
                                                                    stepThreeAffiliationRowInvalid[
                                                                        index
                                                                    ]
                                                                        ?.endBeforeHire
                                                                "
                                                                variant="destructive"
                                                            >
                                                                On or after hire
                                                            </Badge>
                                                            <Badge
                                                                v-if="
                                                                    stepThreeAffiliationRowInvalid[
                                                                        index
                                                                    ]
                                                                        ?.endVersusSeparation
                                                                "
                                                                variant="destructive"
                                                            >
                                                                On or before
                                                                separation
                                                            </Badge>
                                                            <template
                                                                v-if="
                                                                    !stepThreeAffiliationRowInvalid[
                                                                        index
                                                                    ]
                                                                        ?.endDate &&
                                                                    !stepThreeAffiliationRowInvalid[
                                                                        index
                                                                    ]
                                                                        ?.endBeforeHire &&
                                                                    !stepThreeAffiliationRowInvalid[
                                                                        index
                                                                    ]
                                                                        ?.endVersusSeparation
                                                                "
                                                            >
                                                                <Badge
                                                                    v-if="
                                                                        employmentSeparationDate.trim() !==
                                                                        ''
                                                                    "
                                                                    variant="secondary"
                                                                >
                                                                    Required
                                                                    when
                                                                    separated
                                                                </Badge>
                                                                <Badge
                                                                    v-else
                                                                    variant="secondary"
                                                                >
                                                                    Optional
                                                                </Badge>
                                                            </template>
                                                            <Button
                                                                v-if="
                                                                    row.endDate !==
                                                                    ''
                                                                "
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                :class="
                                                                    clearFieldButtonClass
                                                                "
                                                                aria-label="Clear end date"
                                                                @click="
                                                                    clearEmployeeAffiliationRowEnd(
                                                                        index,
                                                                    )
                                                                "
                                                            >
                                                                <X
                                                                    class="size-3.5"
                                                                />
                                                            </Button>
                                                        </Label>
                                                        <Popover
                                                            v-slot="{ close }"
                                                        >
                                                            <PopoverTrigger
                                                                as-child
                                                            >
                                                                <Button
                                                                    :id="`employee_affiliation_end_${index}`"
                                                                    type="button"
                                                                    variant="outline"
                                                                    class="w-full min-w-0 justify-between gap-2 border-input bg-transparent text-sm font-normal dark:bg-input/30"
                                                                    :disabled="
                                                                        row.startDate ===
                                                                        ''
                                                                    "
                                                                    :aria-invalid="
                                                                        !!stepThreeAffiliationRowInvalid[
                                                                            index
                                                                        ]
                                                                            ?.endDate ||
                                                                        !!stepThreeAffiliationRowInvalid[
                                                                            index
                                                                        ]
                                                                            ?.endBeforeHire ||
                                                                        !!stepThreeAffiliationRowInvalid[
                                                                            index
                                                                        ]
                                                                            ?.endVersusSeparation
                                                                    "
                                                                >
                                                                    <span
                                                                        v-if="
                                                                            employeeAffiliationIsoDisplay(
                                                                                row.endDate,
                                                                            ) !==
                                                                            ''
                                                                        "
                                                                        class="min-w-0 flex-1 truncate text-left text-foreground"
                                                                    >
                                                                        {{
                                                                            employeeAffiliationIsoDisplay(
                                                                                row.endDate,
                                                                            )
                                                                        }}
                                                                    </span>
                                                                    <span
                                                                        v-else
                                                                        class="flex-1 truncate text-left text-muted-foreground"
                                                                    >
                                                                        {{
                                                                            row.startDate ===
                                                                            ''
                                                                                ? 'Select start date first'
                                                                                : 'Select end date'
                                                                        }}
                                                                    </span>
                                                                    <ChevronDownIcon
                                                                        class="size-4 shrink-0 opacity-50"
                                                                        aria-hidden="true"
                                                                    />
                                                                </Button>
                                                            </PopoverTrigger>
                                                            <PopoverContent
                                                                class="w-auto overflow-hidden p-0"
                                                                align="start"
                                                            >
                                                                <Calendar
                                                                    layout="month-and-year"
                                                                    :min-value="
                                                                        stepThreeAffiliationEndCalendarMin(
                                                                            row,
                                                                        )
                                                                    "
                                                                    :max-value="
                                                                        employmentSeparationCalendarMax
                                                                    "
                                                                    :model-value="
                                                                        employeeAffiliationRowCalendarValue(
                                                                            row.endDate,
                                                                        )
                                                                    "
                                                                    @update:model-value="
                                                                        (
                                                                            value,
                                                                        ) =>
                                                                            onEmployeeAffiliationRowEndSelect(
                                                                                index,
                                                                                value,
                                                                                close,
                                                                            )
                                                                    "
                                                                />
                                                            </PopoverContent>
                                                        </Popover>
                                                    </div>
                                                </div>
                                                <div
                                                    class="w-full border-t border-primary/30"
                                                    role="presentation"
                                                    aria-hidden="true"
                                                />
                                            </div>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                class="w-full border-dashed border-primary/40 bg-primary/5 hover:bg-primary/10"
                                                aria-label="Add employee affiliation"
                                                title="Add employee affiliation"
                                                @click="addEmployeeAffiliation"
                                            >
                                                <Plus
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                        </div>
                                        <p
                                            v-else
                                            class="text-sm text-muted-foreground"
                                            :class="maxSmOneLineTruncateClass"
                                        >
                                            No default organization is
                                            configured. Affiliations cannot be
                                            edited until HRIS default
                                            organization is set.
                                        </p>
                                    </section>

                                    <section
                                        v-else-if="currentStep === 4"
                                        aria-label="User account setup"
                                        class="min-w-0 space-y-6"
                                    >
                                        <div
                                            class="rounded-lg border border-primary/30 bg-muted/30 p-4 dark:bg-muted/25"
                                        >
                                            <div
                                                class="flex flex-row items-center gap-4"
                                            >
                                                <span
                                                    class="inline-flex shrink-0 items-center justify-center rounded-full bg-primary/10 p-3 text-primary"
                                                >
                                                    <KeyRound
                                                        class="size-7 shrink-0"
                                                        aria-hidden="true"
                                                    />
                                                </span>
                                                <div
                                                    class="min-w-0 flex-1 space-y-0.5"
                                                >
                                                    <h2
                                                        class="min-w-0 text-base font-semibold text-foreground"
                                                        :class="
                                                            maxSmOneLineTruncateClass
                                                        "
                                                        title="User Account"
                                                    >
                                                        User Account
                                                    </h2>
                                                    <p
                                                        class="mt-0 text-sm leading-relaxed text-muted-foreground"
                                                        :class="
                                                            maxSmOneLineTruncateClass
                                                        "
                                                        title="Optional sign-in and profile for this employee."
                                                    >
                                                        Optional sign-in and
                                                        profile for this
                                                        employee.
                                                    </p>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon"
                                                    class="shrink-0"
                                                    :aria-pressed="
                                                        createUserAccount
                                                    "
                                                    :aria-label="
                                                        createUserAccount
                                                            ? 'Remove user account setup'
                                                            : 'Add user account setup'
                                                    "
                                                    @click="
                                                        toggleCreateUserAccount
                                                    "
                                                >
                                                    <Plus
                                                        v-if="
                                                            !createUserAccount
                                                        "
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    <X
                                                        v-else
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />
                                                </Button>
                                            </div>
                                        </div>
                                        <template v-if="createUserAccount">
                                            <p
                                                v-if="
                                                    userAccountDisplayName !==
                                                    ''
                                                "
                                                class="text-sm text-muted-foreground"
                                                :class="
                                                    maxSmOneLineTruncateClass
                                                "
                                            >
                                                <span
                                                    class="font-medium text-foreground"
                                                    >Account name</span
                                                >
                                                (from step 1):
                                                {{ userAccountDisplayName }}
                                            </p>
                                            <div
                                                class="grid min-w-0 gap-6 lg:grid-cols-2"
                                            >
                                                <div class="min-w-0 space-y-6">
                                                    <div class="grid gap-3">
                                                        <div
                                                            class="flex items-start gap-4"
                                                        >
                                                            <div
                                                                class="overflow-hidden rounded-xl border border-border/70 bg-muted/30 shadow-sm"
                                                            >
                                                                <Avatar
                                                                    class="size-40 rounded-none"
                                                                >
                                                                    <AvatarImage
                                                                        v-if="
                                                                            stepFourAvatarPreviewUrl
                                                                        "
                                                                        :src="
                                                                            stepFourAvatarPreviewUrl
                                                                        "
                                                                        alt="Selected profile photo preview"
                                                                        class="h-full w-full object-cover object-center"
                                                                    />
                                                                    <AvatarFallback
                                                                        class="rounded-none bg-muted text-base font-semibold text-foreground"
                                                                    >
                                                                        {{
                                                                            getInitials(
                                                                                userAccountDisplayName,
                                                                            )
                                                                        }}
                                                                    </AvatarFallback>
                                                                </Avatar>
                                                            </div>
                                                            <div
                                                                class="min-w-0 flex-1 space-y-3"
                                                            >
                                                                <Label
                                                                    for="user_account_avatar"
                                                                    :class="
                                                                        stepOneLabelRowClass
                                                                    "
                                                                >
                                                                    <span
                                                                        >Profile
                                                                        photo</span
                                                                    >
                                                                    <Badge
                                                                        variant="outline"
                                                                    >
                                                                        Optional
                                                                    </Badge>
                                                                    <Button
                                                                        v-if="
                                                                            stepFourAvatarFile !==
                                                                            null
                                                                        "
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        :class="
                                                                            clearFieldButtonClass
                                                                        "
                                                                        aria-label="Clear selected profile photo"
                                                                        @click="
                                                                            clearStepFourAvatarSelection
                                                                        "
                                                                    >
                                                                        <X
                                                                            class="size-3.5"
                                                                        />
                                                                    </Button>
                                                                </Label>
                                                                <input
                                                                    id="user_account_avatar"
                                                                    ref="stepFourAvatarInputRef"
                                                                    type="file"
                                                                    accept=".jpg,.jpeg,.png,.webp"
                                                                    autocomplete="off"
                                                                    class="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none selection:bg-primary selection:text-primary-foreground file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30"
                                                                    @change="
                                                                        onStepFourAvatarChange
                                                                    "
                                                                />
                                                                <p
                                                                    class="text-xs text-muted-foreground"
                                                                >
                                                                    {{
                                                                        stepFourAvatarHintText
                                                                    }}
                                                                </p>
                                                                <p
                                                                    class="text-xs text-muted-foreground"
                                                                >
                                                                    Preview will
                                                                    appear on
                                                                    the left.
                                                                </p>
                                                                <p
                                                                    v-if="
                                                                        stepFourAvatarError !==
                                                                        ''
                                                                    "
                                                                    class="text-xs text-destructive"
                                                                >
                                                                    {{
                                                                        stepFourAvatarError
                                                                    }}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="grid gap-3">
                                                        <Label
                                                            for="user_account_email"
                                                            :class="
                                                                stepOneLabelRowClass
                                                            "
                                                        >
                                                            <span
                                                                >Email
                                                                address</span
                                                            >
                                                            <Badge
                                                                v-if="
                                                                    stepFourFieldInvalid.email
                                                                "
                                                                variant="destructive"
                                                            >
                                                                Invalid
                                                            </Badge>
                                                            <Badge
                                                                v-else-if="
                                                                    emailAvailability.status ===
                                                                    'checking'
                                                                "
                                                                variant="secondary"
                                                            >
                                                                Checking
                                                            </Badge>
                                                            <Badge
                                                                v-else-if="
                                                                    emailAvailability.status ===
                                                                    'taken'
                                                                "
                                                                variant="destructive"
                                                            >
                                                                Already used
                                                            </Badge>
                                                            <Badge
                                                                v-else-if="
                                                                    emailAvailability.status ===
                                                                    'available'
                                                                "
                                                                class="bg-green-600/15 text-green-700 dark:text-green-300"
                                                            >
                                                                Available
                                                            </Badge>
                                                            <Button
                                                                v-if="
                                                                    userAccount.email !==
                                                                    ''
                                                                "
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                :class="
                                                                    clearFieldButtonClass
                                                                "
                                                                aria-label="Clear email address"
                                                                @click="
                                                                    userAccount.email =
                                                                        ''
                                                                "
                                                            >
                                                                <X
                                                                    class="size-3.5"
                                                                />
                                                            </Button>
                                                        </Label>
                                                        <Input
                                                            id="user_account_email"
                                                            v-model="
                                                                userAccount.email
                                                            "
                                                            class="w-full"
                                                            type="email"
                                                            autocomplete="email"
                                                            placeholder="name@example.com"
                                                            :aria-invalid="
                                                                stepFourFieldInvalid.email ||
                                                                emailAvailability.status ===
                                                                    'taken' ||
                                                                emailAvailability.status ===
                                                                    'invalid'
                                                            "
                                                        />
                                                        <p
                                                            v-if="
                                                                emailAvailability.status !==
                                                                'idle'
                                                            "
                                                            class="text-sm"
                                                            :class="
                                                                availabilityStatusClass(
                                                                    emailAvailability.status,
                                                                )
                                                            "
                                                        >
                                                            {{
                                                                emailAvailability.message
                                                            }}
                                                        </p>
                                                        <p
                                                            v-else
                                                            class="text-sm opacity-0"
                                                            aria-hidden="true"
                                                        >
                                                            Passwords match.
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="min-w-0 space-y-6">
                                                    <div class="grid gap-3">
                                                        <Label
                                                            for="user_account_password"
                                                            :class="
                                                                stepOneLabelRowClass
                                                            "
                                                        >
                                                            <span
                                                                >Password</span
                                                            >
                                                            <Badge
                                                                v-if="
                                                                    stepFourFieldInvalid.password
                                                                "
                                                                variant="destructive"
                                                            >
                                                                Requirements not
                                                                met
                                                            </Badge>
                                                        </Label>
                                                        <div class="relative">
                                                            <Input
                                                                id="user_account_password"
                                                                v-model="
                                                                    userAccount.password
                                                                "
                                                                class="w-full pr-10"
                                                                :type="
                                                                    showStepFourPassword
                                                                        ? 'text'
                                                                        : 'password'
                                                                "
                                                                autocomplete="new-password"
                                                                placeholder="At least 8 characters"
                                                                aria-describedby="user_account_password_requirements"
                                                                :aria-invalid="
                                                                    stepFourFieldInvalid.password
                                                                "
                                                            />
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                class="absolute top-1/2 right-0 mr-1 size-8 shrink-0 -translate-y-1/2 cursor-pointer rounded-md text-muted-foreground hover:bg-transparent hover:text-foreground"
                                                                aria-label="Toggle password visibility"
                                                                @click="
                                                                    showStepFourPassword =
                                                                        !showStepFourPassword
                                                                "
                                                            >
                                                                <Eye
                                                                    v-if="
                                                                        !showStepFourPassword
                                                                    "
                                                                    class="size-4"
                                                                />
                                                                <EyeOff
                                                                    v-else
                                                                    class="size-4"
                                                                />
                                                            </Button>
                                                        </div>
                                                        <ul
                                                            id="user_account_password_requirements"
                                                            class="list-none space-y-1.5 pl-0 text-sm"
                                                            aria-label="Password requirements"
                                                        >
                                                            <li
                                                                class="flex items-center gap-2"
                                                                :class="
                                                                    stepFourPasswordPolicyChecks.minLength
                                                                        ? 'text-green-600 dark:text-green-400'
                                                                        : 'text-muted-foreground'
                                                                "
                                                            >
                                                                <Check
                                                                    class="size-3.5 shrink-0"
                                                                    aria-hidden="true"
                                                                />
                                                                <span
                                                                    >At least 8
                                                                    characters</span
                                                                >
                                                            </li>
                                                            <li
                                                                class="flex items-center gap-2"
                                                                :class="
                                                                    stepFourPasswordPolicyChecks.hasUpper
                                                                        ? 'text-green-600 dark:text-green-400'
                                                                        : 'text-muted-foreground'
                                                                "
                                                            >
                                                                <Check
                                                                    class="size-3.5 shrink-0"
                                                                    aria-hidden="true"
                                                                />
                                                                <span
                                                                    >One
                                                                    uppercase
                                                                    letter</span
                                                                >
                                                            </li>
                                                            <li
                                                                class="flex items-center gap-2"
                                                                :class="
                                                                    stepFourPasswordPolicyChecks.hasLower
                                                                        ? 'text-green-600 dark:text-green-400'
                                                                        : 'text-muted-foreground'
                                                                "
                                                            >
                                                                <Check
                                                                    class="size-3.5 shrink-0"
                                                                    aria-hidden="true"
                                                                />
                                                                <span
                                                                    >One
                                                                    lowercase
                                                                    letter</span
                                                                >
                                                            </li>
                                                            <li
                                                                class="flex items-center gap-2"
                                                                :class="
                                                                    stepFourPasswordPolicyChecks.hasDigit
                                                                        ? 'text-green-600 dark:text-green-400'
                                                                        : 'text-muted-foreground'
                                                                "
                                                            >
                                                                <Check
                                                                    class="size-3.5 shrink-0"
                                                                    aria-hidden="true"
                                                                />
                                                                <span
                                                                    >One
                                                                    number</span
                                                                >
                                                            </li>
                                                        </ul>
                                                        <p
                                                            v-if="
                                                                stepFourPasswordMeetsPolicy
                                                            "
                                                            class="sr-only"
                                                            aria-live="polite"
                                                        >
                                                            All password
                                                            requirements are
                                                            met.
                                                        </p>
                                                    </div>
                                                    <div class="grid gap-3">
                                                        <Label
                                                            for="user_account_password_confirmation"
                                                            :class="
                                                                stepOneLabelRowClass
                                                            "
                                                        >
                                                            <span
                                                                >Confirm
                                                                password</span
                                                            >
                                                            <Badge
                                                                v-if="
                                                                    stepFourPasswordConfirmationInvalid
                                                                "
                                                                variant="destructive"
                                                            >
                                                                Must match
                                                            </Badge>
                                                        </Label>
                                                        <div class="relative">
                                                            <Input
                                                                id="user_account_password_confirmation"
                                                                v-model="
                                                                    userAccount.passwordConfirmation
                                                                "
                                                                class="w-full pr-10"
                                                                :class="
                                                                    stepFourPasswordConfirmationInputClass
                                                                "
                                                                :type="
                                                                    showStepFourPasswordConfirmation
                                                                        ? 'text'
                                                                        : 'password'
                                                                "
                                                                autocomplete="new-password"
                                                                placeholder="Re-enter password"
                                                                :aria-describedby="
                                                                    stepFourPasswordsMatch
                                                                        ? 'user_account_password_match'
                                                                        : stepFourPasswordConfirmationInvalid
                                                                          ? 'user_account_password_mismatch'
                                                                          : undefined
                                                                "
                                                                :aria-invalid="
                                                                    stepFourPasswordConfirmationInvalid
                                                                "
                                                            />
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                class="absolute top-1/2 right-0 mr-1 size-8 shrink-0 -translate-y-1/2 cursor-pointer rounded-md text-muted-foreground hover:bg-transparent hover:text-foreground"
                                                                aria-label="Toggle confirm password visibility"
                                                                @click="
                                                                    showStepFourPasswordConfirmation =
                                                                        !showStepFourPasswordConfirmation
                                                                "
                                                            >
                                                                <Eye
                                                                    v-if="
                                                                        !showStepFourPasswordConfirmation
                                                                    "
                                                                    class="size-4"
                                                                />
                                                                <EyeOff
                                                                    v-else
                                                                    class="size-4"
                                                                />
                                                            </Button>
                                                        </div>
                                                        <p
                                                            v-if="
                                                                stepFourPasswordConfirmationInvalid &&
                                                                !stepFourPasswordsMatch
                                                            "
                                                            id="user_account_password_mismatch"
                                                            class="text-sm text-destructive"
                                                        >
                                                            Passwords do not
                                                            match.
                                                        </p>
                                                        <p
                                                            v-if="
                                                                stepFourPasswordsMatch
                                                            "
                                                            id="user_account_password_match"
                                                            class="text-sm text-green-600 dark:text-green-400"
                                                        >
                                                            Passwords match.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                        <p
                                            v-else
                                            class="rounded-lg border border-dashed border-border/70 bg-muted/20 p-4 text-sm leading-relaxed text-muted-foreground dark:bg-muted/15"
                                        >
                                            You are saving this employee without
                                            app sign-in. You can add a user
                                            account later from admin if your
                                            process allows it.
                                        </p>
                                    </section>
                                </CardContent>
                            </Card>
                        </div>

                        <div
                            class="shrink-0 border-t border-border/60 bg-background/95 pt-3 backdrop-blur supports-backdrop-filter:bg-background/80"
                        >
                            <div
                                class="flex w-full gap-2 sm:items-center sm:justify-between"
                            >
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="flex-1 sm:flex-none"
                                    :disabled="sp.isFirstStep"
                                    @click="sp.prevStep"
                                >
                                    Back
                                </Button>
                                <div class="flex flex-1 sm:flex-none">
                                    <Button
                                        v-if="!sp.isLastStep"
                                        type="button"
                                        @click="
                                            handleNextStep(
                                                sp.nextStep,
                                                sp.modelValue ?? 1,
                                            )
                                        "
                                        class="w-full sm:w-auto"
                                    >
                                        Next
                                    </Button>
                                    <Button
                                        v-else
                                        type="button"
                                        class="w-full sm:w-auto"
                                        :title="completeButtonTitle()"
                                        @click="onWizardCompleteClick"
                                    >
                                        Complete
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </Stepper>
        </div>
    </AppLayout>
</template>
