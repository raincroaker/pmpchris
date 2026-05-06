/** Shared layout tokens for HRIS About Me-style edit dialogs (ScrollArea matches Team Leave mutate). */

export const aboutMeDialogScrollAreaClass =
    'max-h-[70vh] pr-3 **:data-[slot=scroll-area-viewport]:focus-visible:outline-none **:data-[slot=scroll-area-viewport]:focus-visible:ring-0';

export const aboutMeLabelRowClass =
    'relative flex min-h-6 flex-wrap items-center gap-x-2 gap-y-1 pr-8';

export const aboutMeClearFieldButtonClass =
    'absolute right-0 top-1/2 z-[1] size-6 shrink-0 -translate-y-1/2 cursor-pointer rounded-md text-muted-foreground hover:bg-muted/60 hover:text-foreground';

/** Primary checkbox row under section headers — matches Add Employee address blocks. */
export const aboutMePrimaryToggleRowClass =
    'flex min-w-0 flex-nowrap items-center gap-3';

export const aboutMePrimaryToggleRuleClass =
    'min-w-0 flex-1 self-center border-t border-primary/30';

export const aboutMeMaxSmOneLineTruncateClass =
    'max-sm:min-w-0 max-sm:truncate';
