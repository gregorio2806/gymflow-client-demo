export function isCurrentOrChildPath(current: string, target: string): boolean {
    return (
        current === target ||
        (target !== '/' && current.startsWith(`${target.replace(/\/$/, '')}/`))
    );
}
