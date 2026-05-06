# HRIS documentation index

| Document | Description |
|----------|-------------|
| [hris-ui-future-proofing.md](hris-ui-future-proofing.md) | **Canonical guide** for extending the UI safely: Sonner (`appToast`), shell/toaster, sidebar state, branch switcher, Wayfinder. Read this before adding features that touch layout or toasts. |
| [primevue-vs-shadcn.md](primevue-vs-shadcn.md) | When to use PrimeVue vs shadcn-vue (Reka) components in this codebase. |
| [cursor-prompts.md](cursor-prompts.md) | Cursor / AI prompts and notes for this project. |

For a chronological list of shipped UI changes, see [`PROJECT_CHANGELOG.md`](../PROJECT_CHANGELOG.md).

## Setup note

- Project bootstrap already automates Laravel public storage linking via Composer scripts (`setup` and `post-create-project-cmd` run `php artisan storage:link`), so avatar and other `/storage/...` public files work on fresh installs without a manual symlink step.
