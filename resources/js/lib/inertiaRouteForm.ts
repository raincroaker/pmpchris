import type { Method as InertiaMethod } from '@inertiajs/core';

type RouteLike = {
    url: string;
    method: string;
};

/**
 * Convert a Wayfinder route object into Inertia <Form> props.
 */
export function inertiaRouteForm(route: RouteLike): {
    action: string;
    method: InertiaMethod;
} {
    return {
        action: route.url,
        method: route.method.toLowerCase() as InertiaMethod,
    };
}
