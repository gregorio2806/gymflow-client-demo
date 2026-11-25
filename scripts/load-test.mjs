import { execFileSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { performance } from 'node:perf_hooks';
import assert from 'node:assert/strict';
import http from 'node:http';

const base = new URL(process.env.LOAD_BASE_URL || 'http://localhost:8000');
if (!['localhost', '127.0.0.1'].includes(base.hostname))
    throw new Error('This load test only runs on localhost.');
const concurrency = Number(process.env.LOAD_CONCURRENCY || 4);
const ports = (process.env.LOAD_PORTS || base.port || '8000')
    .split(',')
    .map(Number);
if (ports.some((port) => !Number.isInteger(port) || port < 8000 || port > 8099))
    throw new Error('Use local test ports 8000–8099.');
const freePorts = [...ports];
const waitingForPort = [];
function acquirePort() {
    return freePorts.length
        ? Promise.resolve(freePorts.shift())
        : new Promise((resolve) => waitingForPort.push(resolve));
}
function releasePort(port) {
    if (waitingForPort.length) waitingForPort.shift()(port);
    else freePorts.push(port);
}
const rounds = Number(process.env.LOAD_ROUNDS || 12);
if (
    !Number.isInteger(concurrency) ||
    concurrency < 1 ||
    concurrency > 10 ||
    !Number.isInteger(rounds) ||
    rounds < 1 ||
    rounds > 100
)
    throw new Error('Use concurrency 1–10 and rounds 1–100.');
const info = JSON.parse(
    execFileSync('php', ['scripts/load-test-info.php'], { encoding: 'utf8' }),
);
assert.ok(
    info.member_count >= 1000,
    'Seed the 1,000-member local fixture first.',
);
const publicBase = new URL(base);
publicBase.hostname = info.slug + '.localhost';

function client(origin) {
    const cookies = new Map();
    return async (path, { method = 'GET', data, inertia = false } = {}) => {
        const headers = {
            Host: origin.host,
            Cookie: [...cookies]
                .map(([name, value]) => `${name}=${value}`)
                .join('; '),
            Accept: 'application/json',
            Origin: origin.origin,
        };
        if (inertia) {
            headers['X-Inertia'] = 'true';
            headers['X-Inertia-Version'] = info.version || '';
        }
        if (data) {
            headers['Content-Type'] = 'application/json';
            headers['X-XSRF-TOKEN'] = decodeURIComponent(
                cookies.get('XSRF-TOKEN') || '',
            );
        }
        const start = performance.now();
        const port = await acquirePort();
        const res = await new Promise((resolve, reject) => {
            const target = new URL(path, origin);
            const request = http.request(
                {
                    hostname: '127.0.0.1',
                    port,
                    path: target.pathname + target.search,
                    method,
                    headers,
                    timeout: 20000,
                },
                (response) => {
                    const chunks = [];
                    response.on('data', (chunk) => chunks.push(chunk));
                    response.on('end', () =>
                        resolve({
                            status: response.statusCode,
                            headers: response.headers,
                            text: Buffer.concat(chunks).toString('utf8'),
                        }),
                    );
                    response.on('error', reject);
                },
            );
            request.on('error', reject);
            request.on('timeout', () =>
                request.destroy(new Error('Local request timed out.')),
            );
            if (data) request.write(JSON.stringify(data));
            request.end();
        }).finally(() => releasePort(port));
        for (const cookie of res.headers['set-cookie'] || []) {
            const pair = cookie.split(';')[0];
            const split = pair.indexOf('=');
            cookies.set(pair.slice(0, split), pair.slice(split + 1));
        }
        const text = res.text;
        return {
            status: res.status,
            ms: performance.now() - start,
            bytes: Buffer.byteLength(text),
            json: res.headers['content-type']?.includes('application/json')
                ? JSON.parse(text)
                : null,
        };
    };
}
const owner = client(base),
    staff = client(base),
    member = client(publicBase),
    visitor = client(publicBase);
for (const [actor, email] of [
    [owner, 'owner@loadtest.invalid'],
    [staff, 'staff@loadtest.invalid'],
]) {
    assert.equal((await actor('/login')).status, 200);
    assert.equal(
        (
            await actor('/login', {
                method: 'POST',
                data: { email, password: 'password' },
            })
        ).status,
        200,
    );
}
await member('/portal/login');
assert.equal(
    (
        await member('/portal/login', {
            method: 'POST',
            data: {
                login: 'member0001@loadtest.invalid',
                password: 'password',
            },
        })
    ).status,
    302,
);
assert.equal(
    (
        await member('/portal/agreement', {
            method: 'POST',
            data: { terms_accepted: true, policy_version: info.policy_version },
        })
    ).status,
    302,
);
const cases = [
    ['public website', visitor, '/', false, 200],
    ['dashboard', owner, '/dashboard', true, 500],
    ['members', staff, '/members?page=2', true, 500],
    ['member search', staff, '/check-in/members?search=Fictional', false, 500],
    [
        'attendance',
        owner,
        '/attendance?view=month&date=' + info.date,
        true,
        500,
    ],
    ['payments', owner, '/payments', true, 500],
    ['reports', owner, '/reports', true, 500],
    ['member portal', member, '/portal', true, 500],
];
const samples = Object.fromEntries(cases.map(([name]) => [name, []]));
for (const [name, actor, path, inertia] of cases) {
    for (let i = 0; i < Math.max(2, ports.length * 2); i++)
        assert.equal(
            (await actor(path, { inertia })).status,
            200,
            name + ' warm-up',
        );
}
const jobs = [];
for (let round = 0; round < rounds; round++)
    for (const entry of cases) jobs.push(entry);
let cursor = 0;
const started = performance.now();
await Promise.all(
    Array.from({ length: concurrency }, async () => {
        while (cursor < jobs.length) {
            const [name, actor, path, inertia] = jobs[cursor++];
            samples[name].push(await actor(path, { inertia }));
        }
    }),
);
const readElapsed = Math.round(performance.now() - started);
samples['check-in'] = [];
for (const memberId of info.members.slice(0, 8))
    samples['check-in'].push(
        await staff('/check-in', {
            method: 'POST',
            data: { branch_id: info.branch, member_id: memberId },
        }),
    );
const duplicate = await staff('/check-in', {
    method: 'POST',
    data: { branch_id: info.branch, member_id: info.members[0] },
});
assert.equal(duplicate.json.duplicate, true);
const results = Object.entries(samples).map(([scenario, rows]) => {
    const sorted = rows.map((row) => row.ms).sort((a, b) => a - b);
    const budget =
        scenario === 'check-in'
            ? 300
            : cases.find((entry) => entry[0] === scenario)[4];
    const p95 = Math.round(sorted[Math.ceil(sorted.length * 0.95) - 1]);
    return {
        scenario,
        requests: rows.length,
        errors: rows.filter((row) => row.status !== 200).length,
        p50_ms: Math.round(sorted[Math.ceil(sorted.length * 0.5) - 1]),
        p95_ms: p95,
        max_ms: Math.round(sorted.at(-1)),
        max_bytes: Math.max(...rows.map((row) => row.bytes)),
        budget_ms: budget,
        budget_passed: p95 < budget,
    };
});
const report = {
    timestamp: new Date().toISOString(),
    dataset_members: info.member_count,
    baseline_attendance: info.attendance_count,
    concurrency,
    server_workers: ports.length,
    server_profile: process.env.LOAD_SERVER_PROFILE || 'development server',
    measured_read_requests: jobs.length,
    read_elapsed_ms: readElapsed,
    duplicate_check_passed: true,
    results,
    note: 'Local PHP HTTP servers; includes queueing. Not a Hostinger or 1,000-concurrent-user capacity result. Fictional check-in records and the test member agreement remain for manual testing.',
};
writeFileSync(
    'storage/load-test-results.json',
    JSON.stringify(report, null, 2),
);
console.table(results);
assert.equal(
    results.reduce((total, row) => total + row.errors, 0),
    0,
    'HTTP errors',
);
assert.ok(
    results
        .filter((row) => row.scenario !== 'public website')
        .every((row) => row.max_bytes < 200 * 1024),
    'JSON payload budget',
);
console.log(
    'Saved storage/load-test-results.json. Dataset kept for manual testing.',
);
if (results.some((row) => !row.budget_passed)) process.exitCode = 2;
