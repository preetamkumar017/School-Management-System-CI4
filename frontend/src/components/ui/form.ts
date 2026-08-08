// Shared Tailwind class strings for form controls, kept in one place so
// every module's forms look consistent without a component library.
export const inputClass =
  "w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100";

export const labelClass = "mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300";

export const primaryButtonClass =
  "rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-700 disabled:opacity-50 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300";

export const secondaryButtonClass =
  "rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-100 disabled:opacity-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-900";

export const dangerButtonClass =
  "rounded-md border border-red-300 px-3 py-2 text-sm text-red-700 transition hover:bg-red-50 disabled:opacity-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950";
