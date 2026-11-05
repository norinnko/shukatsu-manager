import { usePage } from '@inertiajs/react';
import type { EnumOption, Enums } from '@/types';

type Lookup = Record<string, EnumOption>;

function index(options: EnumOption[]): Lookup {
    return Object.fromEntries(options.map((o) => [o.value, o]));
}

/**
 * Labels and colours for the PHP enums, shared on every page by
 * HandleInertiaRequests so both sides use the same wording.
 */
export function useEnums(): Enums & {
    stage: Lookup;
    phase: Lookup;
    priority: Lookup;
    eventType: Lookup;
    esCategory: Lookup;
} {
    const { enums } = usePage().props;

    return {
        ...enums,
        stage: index(enums.stages),
        phase: index(enums.phases),
        priority: index(enums.priorities),
        eventType: index(enums.eventTypes),
        esCategory: index(enums.esCategories),
    };
}
