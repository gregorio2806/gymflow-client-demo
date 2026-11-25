import type { RouteDefinition } from '@/wayfinder';

// Portal routes are on the current gym host. Keep its development port as well.
export function portalUrl(
    route: RouteDefinition<'get' | 'post' | 'delete'>,
): string {
    const url = new URL(route.url, window.location.href);
    return url.pathname + url.search;
}
