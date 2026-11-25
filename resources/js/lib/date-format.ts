const months = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

/** Format calendar dates without shifting the server's gym-local date or time. */
export function formatDisplayDate(value: string | null | undefined): string {
    if (!value) return '—';
    const date = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
    if (date) {
        const month = months[Number(date[2]) - 1];
        if (month) return `${month} ${Number(date[3])}, ${date[1]}`;
    }
    // Timestamps already arrive in gym-local time with an abbreviated month.
    return value.replace(
        /^[A-Z][a-z]{2}(?= \d{1,2}, \d{4})/,
        (short) => months.find((month) => month.slice(0, 3) === short) ?? short,
    );
}
