export async function jsonRequest<T>(
    url: string,
    body: unknown,
    signal?: AbortSignal,
    method: 'POST' | 'DELETE' = 'POST',
): Promise<T> {
    const cookie = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith('XSRF-TOKEN='));
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        signal,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': cookie
                ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length))
                : '',
        },
        body: JSON.stringify(body),
    });
    if (
        response.redirected ||
        !response.headers.get('content-type')?.includes('application/json')
    ) {
        throw new Error(
            'Your session may have expired. Refresh the page and log in again.',
        );
    }
    if (!response.ok) {
        if (response.status === 429)
            throw new Error('Too many attempts. Wait a minute and try again.');
        if (response.status === 419 || response.status === 401)
            throw new Error(
                'Your session has expired. Refresh the page and log in again.',
            );
        const data = await response.json().catch(() => null);
        const validation = data?.errors
            ? Object.values(data.errors).flat()[0]
            : null;
        throw new Error(
            typeof validation === 'string'
                ? validation
                : 'Could not complete the request. Refresh the page and try again.',
        );
    }
    return response.json();
}
