/**
 * Colour names sent by the PHP enums (App\Enums\*::color()) mapped to
 * literal Tailwind classes, so Tailwind can see and generate them.
 */
type Tone = { badge: string; dot: string; soft: string };

const tones: Record<string, Tone> = {
    slate: {
        badge: 'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200',
        dot: 'bg-slate-400',
        soft: 'bg-slate-50 dark:bg-slate-900/40',
    },
    zinc: {
        badge: 'border-zinc-200 bg-zinc-100 text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400',
        dot: 'bg-zinc-400',
        soft: 'bg-zinc-50 dark:bg-zinc-900/40',
    },
    violet: {
        badge: 'border-violet-200 bg-violet-100 text-violet-700 dark:border-violet-800 dark:bg-violet-950 dark:text-violet-300',
        dot: 'bg-violet-500',
        soft: 'bg-violet-50 dark:bg-violet-950/40',
    },
    sky: {
        badge: 'border-sky-200 bg-sky-100 text-sky-700 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-300',
        dot: 'bg-sky-500',
        soft: 'bg-sky-50 dark:bg-sky-950/40',
    },
    blue: {
        badge: 'border-blue-200 bg-blue-100 text-blue-700 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-300',
        dot: 'bg-blue-500',
        soft: 'bg-blue-50 dark:bg-blue-950/40',
    },
    indigo: {
        badge: 'border-indigo-200 bg-indigo-100 text-indigo-700 dark:border-indigo-800 dark:bg-indigo-950 dark:text-indigo-300',
        dot: 'bg-indigo-500',
        soft: 'bg-indigo-50 dark:bg-indigo-950/40',
    },
    orange: {
        badge: 'border-orange-200 bg-orange-100 text-orange-700 dark:border-orange-800 dark:bg-orange-950 dark:text-orange-300',
        dot: 'bg-orange-500',
        soft: 'bg-orange-50 dark:bg-orange-950/40',
    },
    amber: {
        badge: 'border-amber-200 bg-amber-100 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300',
        dot: 'bg-amber-500',
        soft: 'bg-amber-50 dark:bg-amber-950/40',
    },
    emerald: {
        badge: 'border-emerald-200 bg-emerald-100 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
        dot: 'bg-emerald-500',
        soft: 'bg-emerald-50 dark:bg-emerald-950/40',
    },
    green: {
        badge: 'border-green-200 bg-green-100 text-green-700 dark:border-green-800 dark:bg-green-950 dark:text-green-300',
        dot: 'bg-green-500',
        soft: 'bg-green-50 dark:bg-green-950/40',
    },
    teal: {
        badge: 'border-teal-200 bg-teal-100 text-teal-700 dark:border-teal-800 dark:bg-teal-950 dark:text-teal-300',
        dot: 'bg-teal-500',
        soft: 'bg-teal-50 dark:bg-teal-950/40',
    },
    rose: {
        badge: 'border-rose-200 bg-rose-100 text-rose-700 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-300',
        dot: 'bg-rose-500',
        soft: 'bg-rose-50 dark:bg-rose-950/40',
    },
    red: {
        badge: 'border-red-200 bg-red-100 text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-300',
        dot: 'bg-red-500',
        soft: 'bg-red-50 dark:bg-red-950/40',
    },
};

export function tone(color: string | null | undefined): Tone {
    return tones[color ?? 'slate'] ?? tones.slate;
}
