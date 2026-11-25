export function memberTabUrl(
    url: string,
    tab: 'membership' | 'payment' | 'history',
): string {
    const target = new URL(url, 'http://localhost');
    target.searchParams.set('tab', tab);
    return target.pathname + target.search;
}
