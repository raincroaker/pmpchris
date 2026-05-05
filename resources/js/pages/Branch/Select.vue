<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Building2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import BranchContextController from '@/actions/App/Http/Controllers/BranchContextController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { appToast } from '@/lib/app-toast-client';
import { branchPickerGroups } from '@/lib/branch-picker-groups';

type BranchOption = {
    id: number;
    code: string;
    name: string;
    area_name?: string | null;
    group_label?: string | null;
};

type Props = {
    organization: { name: string; code: string | null } | null;
    branches: BranchOption[];
};

const props = defineProps<Props>();

const page = usePage();

const branchIdModel = ref<string>('');

const form = useForm({
    branch_id: null as number | null,
    return_to: '',
});

/** Avoid redirecting back to the picker after first selection; otherwise use current path + query. */
function currentReturnTo(): string {
    const url = page.url;
    if (url === '/select-branch' || url.startsWith('/select-branch?')) {
        return '';
    }
    return url.startsWith('/') ? url : `/${url}`;
}

const branchGroups = computed(() => branchPickerGroups(props.branches));

const canSubmit = computed(
    () => branchIdModel.value !== '' && props.branches.length > 0,
);

const selectedBranchLabel = computed(() => {
    const id = Number(branchIdModel.value);
    if (!Number.isFinite(id)) {
        return '';
    }
    const b = props.branches.find((x) => x.id === id);
    if (!b) {
        return '';
    }
    return b.code ? `${b.name} (${b.code})` : b.name;
});

const selectedBranch = computed(() => {
    const id = Number(branchIdModel.value);
    if (!Number.isFinite(id)) {
        return null;
    }

    return props.branches.find((branch) => branch.id === id) ?? null;
});

function submit() {
    if (!canSubmit.value || form.processing) {
        return;
    }
    const description = selectedBranchLabel.value || undefined;
    const loadingToastId = appToast.loading('Saving branch…', {
        description,
    });

    let submitSucceeded = false;
    let genericErrorAfterFinish = false;

    form.branch_id = Number(branchIdModel.value);
    form.return_to = currentReturnTo();
    form.post(BranchContextController.store.url(), {
        onSuccess: () => {
            submitSucceeded = true;
        },
        onError: (errors) => {
            if (!errors.branch_id) {
                genericErrorAfterFinish = true;
            }
        },
        onFinish: () => {
            appToast.dismiss(loadingToastId);
            if (submitSucceeded) {
                submitSucceeded = false;
                appToast.success('Branch selected', {
                    description: selectedBranchLabel.value || undefined,
                });
                return;
            }
            if (genericErrorAfterFinish) {
                genericErrorAfterFinish = false;
                appToast.error('Could not select branch');
            }
        },
    });
}
</script>

<template>
    <div
        class="relative min-h-dvh overflow-hidden bg-linear-to-b from-background via-background to-muted/35"
    >
        <Head title="Select branch" />

        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_center,currentColor_1px,transparent_1px)] bg-size-[24px_24px] text-muted-foreground opacity-[0.35]"
            aria-hidden="true"
        />

        <div
            class="relative z-10 flex min-h-dvh items-center justify-center p-4"
        >
            <div
                class="w-full max-w-md rounded-xl border border-border bg-card text-card-foreground shadow-lg ring-1 ring-border/60"
            >
                <div class="flex flex-col gap-6 p-6">
                    <div
                        v-if="organization"
                        class="flex flex-col items-center gap-3 text-center"
                    >
                        <span
                            class="inline-flex items-center justify-center rounded-full bg-primary/10 p-3 text-primary"
                        >
                            <Building2
                                class="size-7 shrink-0"
                                aria-hidden="true"
                            />
                        </span>
                        <p class="text-lg font-semibold text-foreground">
                            {{ organization.name }}
                        </p>
                        <p
                            v-if="organization.code"
                            class="text-sm text-muted-foreground"
                        >
                            {{ organization.code }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Pick where you're working.
                        </p>
                    </div>

                    <p v-else class="text-center text-sm text-muted-foreground">
                        No default organization is configured. Contact an
                        administrator.
                    </p>

                    <form class="flex flex-col gap-4" @submit.prevent="submit">
                        <div class="grid gap-2">
                            <Label for="branch_id">Branch</Label>
                            <Select
                                v-model="branchIdModel"
                                :disabled="branches.length === 0"
                            >
                                <SelectTrigger
                                    id="branch_id"
                                    class="w-full cursor-pointer"
                                    aria-label="Branch"
                                >
                                    <div
                                        v-if="selectedBranch"
                                        class="flex w-full min-w-0 items-center justify-start gap-2"
                                    >
                                        <span class="min-w-0 shrink truncate">
                                            {{ selectedBranch.name }}
                                        </span>
                                        <Badge
                                            v-if="selectedBranch.code"
                                            variant="outline"
                                            class="h-5 shrink-0 border-foreground/50 px-1.5 text-[10px] dark:border-foreground/60"
                                        >
                                            {{ selectedBranch.code }}
                                        </Badge>
                                    </div>
                                    <span v-else class="text-muted-foreground">
                                        Select a branch
                                    </span>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup
                                        v-for="group in branchGroups"
                                        :key="group.label"
                                    >
                                        <SelectLabel
                                            class="px-2 py-1.5 text-xs font-medium text-muted-foreground"
                                        >
                                            {{ group.label }}
                                        </SelectLabel>
                                        <SelectItem
                                            v-for="b in group.branches"
                                            :key="b.id"
                                            :value="String(b.id)"
                                            class="cursor-pointer"
                                        >
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <span>{{ b.name }}</span>
                                                <Badge
                                                    v-if="b.code"
                                                    variant="outline"
                                                    class="h-5 border-foreground/50 px-1.5 text-[10px] dark:border-foreground/60"
                                                >
                                                    {{ b.code }}
                                                </Badge>
                                            </div>
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.branch_id" />
                        </div>

                        <p
                            v-if="branches.length === 0"
                            class="text-center text-sm text-muted-foreground"
                        >
                            No branches are available. Contact an administrator.
                        </p>

                        <Button
                            type="submit"
                            class="mt-4 w-full"
                            :disabled="!canSubmit || form.processing"
                            data-test="branch-continue-button"
                        >
                            <Spinner v-if="form.processing" />
                            Continue
                        </Button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
