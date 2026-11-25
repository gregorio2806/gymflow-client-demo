const STORAGE_KEY = 'gym-saas-session-demo-v2';

const seed = {
    gymName: 'Iron House Fitness',
    headline: 'Train stronger. Live better.',
    members: [
        {
            id: 'IH-1048',
            name: 'Maya Santos',
            initials: 'MS',
            plan: 'Pro Monthly',
            status: 'Active',
            balance: 0,
            branch: 'Main branch',
            phone: '0917 555 0148',
        },
        {
            id: 'IH-1047',
            name: 'Noah Reyes',
            initials: 'NR',
            plan: 'Strength Starter',
            status: 'Expiring soon',
            balance: 1200,
            branch: 'Main branch',
            phone: '0917 555 0131',
        },
        {
            id: 'IH-1046',
            name: 'Ava Dela Cruz',
            initials: 'AD',
            plan: 'Pro Monthly',
            status: 'Active',
            balance: 0,
            branch: 'Main branch',
            phone: '0917 555 0119',
        },
        {
            id: 'IH-1045',
            name: 'Liam Garcia',
            initials: 'LG',
            plan: 'Student Monthly',
            status: 'Active',
            balance: 500,
            branch: 'Main branch',
            phone: '0917 555 0108',
        },
        {
            id: 'IH-1044',
            name: 'Sofia Villanueva',
            initials: 'SV',
            plan: 'Pro Monthly',
            status: 'Expired',
            balance: 0,
            branch: 'Main branch',
            phone: '0917 555 0102',
        },
        {
            id: 'IH-1043',
            name: 'Ethan Flores',
            initials: 'EF',
            plan: 'Strength Starter',
            status: 'Active',
            balance: 0,
            branch: 'Main branch',
            phone: '0917 555 0096',
        },
    ],
    attendance: [
        {
            member: 'Maya Santos',
            initials: 'MS',
            time: '08:42 AM',
            branch: 'Main branch',
            source: 'QR scan',
        },
        {
            member: 'Ava Dela Cruz',
            initials: 'AD',
            time: '08:18 AM',
            branch: 'Main branch',
            source: 'Manual',
        },
        {
            member: 'Liam Garcia',
            initials: 'LG',
            time: '07:56 AM',
            branch: 'Main branch',
            source: 'QR scan',
        },
        {
            member: 'Ethan Flores',
            initials: 'EF',
            time: '07:31 AM',
            branch: 'Main branch',
            source: 'Manual',
        },
        {
            member: 'Noah Reyes',
            initials: 'NR',
            time: '06:48 AM',
            branch: 'Main branch',
            source: 'QR scan',
        },
    ],
    products: [
        {
            name: 'Iron House Shaker',
            sku: 'ACC-001',
            category: 'Accessories',
            price: 450,
            stock: 24,
            low: 10,
            status: 'Active',
        },
        {
            name: 'Whey Protein 1kg',
            sku: 'SUP-012',
            category: 'Supplements',
            price: 1890,
            stock: 7,
            low: 10,
            status: 'Low stock',
        },
        {
            name: 'Gym Towel',
            sku: 'ACC-008',
            category: 'Accessories',
            price: 280,
            stock: 42,
            low: 10,
            status: 'Active',
        },
        {
            name: 'Black Training Tee',
            sku: 'APP-003',
            category: 'Apparel',
            price: 850,
            stock: 0,
            low: 5,
            status: 'Out of stock',
        },
    ],
    appointments: [
        {
            member: 'Maya Santos',
            initials: 'MS',
            service: 'Personal training',
            coach: 'Marco Santos',
            date: 'Nov 25, 09:00 AM',
            status: 'Confirmed',
        },
        {
            member: 'Noah Reyes',
            initials: 'NR',
            service: 'Strength assessment',
            coach: 'Bea Ramos',
            date: 'Nov 25, 10:30 AM',
            status: 'Confirmed',
        },
        {
            member: 'Ava Dela Cruz',
            initials: 'AD',
            service: 'Personal training',
            coach: 'Marco Santos',
            date: 'Nov 25, 01:00 PM',
            status: 'Completed',
        },
        {
            member: 'Lucas Tan',
            initials: 'LT',
            service: 'Mobility session',
            coach: 'Bea Ramos',
            date: 'Nov 26, 08:00 AM',
            status: 'Confirmed',
        },
    ],
    freezes: [
        {
            member: 'Sofia Villanueva',
            initials: 'SV',
            action: 'Freeze',
            period: 'Nov 18–Dec 02, 2026',
            reason: 'Travel',
            by: 'Jamie Dela Cruz',
            date: 'Nov 18, 2026',
        },
        {
            member: 'Liam Garcia',
            initials: 'LG',
            action: 'Resume',
            period: 'Nov 10–Nov 17, 2026',
            reason: 'Back to training',
            by: 'Jamie Dela Cruz',
            date: 'Nov 17, 2026',
        },
        {
            member: 'Rafael Cruz',
            initials: 'RC',
            action: 'Freeze',
            period: 'Nov 12–Nov 26, 2026',
            reason: 'Injury recovery',
            by: 'Jamie Dela Cruz',
            date: 'Nov 12, 2026',
        },
    ],
    benefits: [
        {
            name: 'Pro Monthly',
            duration: '30 days',
            price: 1999,
            members: 126,
            status: 'Active',
        },
        {
            name: 'Strength Starter',
            duration: '30 days',
            price: 999,
            members: 72,
            status: 'Active',
        },
        {
            name: 'Annual Training',
            duration: '365 days',
            price: 19999,
            members: 46,
            status: 'Active',
        },
        {
            name: 'Student Monthly',
            duration: '30 days',
            price: 899,
            members: 34,
            status: 'Active',
        },
    ],
    subscriptions: [
        {
            name: 'Monthly Gym Access',
            duration: '30 days',
            price: 1500,
            members: 118,
            status: 'Active',
        },
        {
            name: 'Quarterly Gym Access',
            duration: '90 days',
            price: 4050,
            members: 74,
            status: 'Active',
        },
        {
            name: 'Annual Gym Access',
            duration: '365 days',
            price: 14400,
            members: 56,
            status: 'Active',
        },
    ],
    training: [
        {
            name: '1-on-1 Starter',
            sessions: 4,
            price: 2400,
            validity: '30 days',
            coach: 'Any coach',
            status: 'Active',
        },
        {
            name: 'Performance Pack',
            sessions: 12,
            price: 6600,
            validity: '90 days',
            coach: 'Marco Santos',
            status: 'Active',
        },
        {
            name: 'Mobility Reset',
            sessions: 6,
            price: 3000,
            validity: 'Per session',
            coach: 'Bea Ramos',
            status: 'Active',
        },
    ],
    payments: [
        {
            member: 'Maya Santos',
            initials: 'MS',
            type: 'Membership',
            amount: 1999,
            paid: 1999,
            balance: 0,
            status: 'Paid',
        },
        {
            member: 'Noah Reyes',
            initials: 'NR',
            type: 'Membership',
            amount: 999,
            paid: 0,
            balance: 999,
            status: 'Outstanding',
        },
        {
            member: 'Ava Dela Cruz',
            initials: 'AD',
            type: 'Training package',
            amount: 6600,
            paid: 6600,
            balance: 0,
            status: 'Paid',
        },
        {
            member: 'Liam Garcia',
            initials: 'LG',
            type: 'Membership',
            amount: 899,
            paid: 399,
            balance: 500,
            status: 'Partially paid',
        },
    ],
    users: [
        {
            name: 'Jamie Dela Cruz',
            initials: 'JD',
            email: 'jamie@ironhouse.demo',
            role: 'Owner',
            branches: 'All branches',
            active: 'Today',
            status: 'Active',
        },
        {
            name: 'Marco Santos',
            initials: 'MS',
            email: 'marco@ironhouse.demo',
            role: 'Coach',
            branches: 'Main branch',
            active: 'Today, 08:32 AM',
            status: 'Active',
        },
        {
            name: 'Bea Ramos',
            initials: 'BR',
            email: 'bea@ironhouse.demo',
            role: 'Staff',
            branches: 'Main branch, Makati',
            active: 'Yesterday',
            status: 'Active',
        },
        {
            name: 'Carlo Mendoza',
            initials: 'CM',
            email: 'carlo@ironhouse.demo',
            role: 'Branch manager',
            branches: 'Makati branch',
            active: 'Nov 23, 2026',
            status: 'Active',
        },
    ],
    branches: [
        {
            name: 'Main branch',
            address: 'Makati Avenue, Makati City',
            phone: '(02) 8555 0148',
            members: 248,
            staff: 4,
            status: 'Active',
            main: true,
        },
        {
            name: 'Makati branch',
            address: 'Chino Roces Avenue, Makati City',
            phone: '(02) 8555 0199',
            members: 116,
            staff: 3,
            status: 'Active',
            main: false,
        },
    ],
    invitations: [],
};

let state = loadState();
let activeView = location.hash.replace('#', '') || 'dashboard';
let toastTimer;

function loadState() {
    try {
        const saved = sessionStorage.getItem(STORAGE_KEY);
        if (!saved) return structuredClone(seed);
        const parsed = JSON.parse(saved);
        return { ...seed, ...parsed, invitations: parsed.invitations || [] };
    } catch {
        return structuredClone(seed);
    }
}

function saveState() {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
}

function money(value) {
    return `₱${Number(value).toLocaleString('en-PH')}`;
}

function esc(value) {
    return String(value).replace(
        /[&<>'"]/g,
        (char) =>
            ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#39;',
                '"': '&quot;',
            })[char],
    );
}

function initials(name) {
    return name
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function statusBadge(status) {
    const variant =
        status === 'Active' || status === 'Present'
            ? 'active'
            : status === 'Expiring soon' ||
                status === 'Low stock' ||
                status === 'Pending invitation'
              ? 'warning'
              : 'expired';
    return `<span class="status-badge ${variant}">${esc(status)}</span>`;
}

function kpi(label, value, note, trend = 'trend-neutral') {
    return `<div class="kpi-card"><span class="kpi-label">${label}</span><strong>${value}</strong><small class="${trend}">${note}</small></div>`;
}

function render() {
    const validViews = [
        'dashboard',
        'checkin',
        'members',
        'attendance',
        'products',
        'appointments',
        'calendar',
        'freeze',
        'plans',
        'benefits',
        'training',
        'reports',
        'payments-report',
        'website',
        'workouts',
        'users',
        'branches',
        'public',
    ];
    if (!validViews.includes(activeView)) activeView = 'dashboard';
    document
        .querySelectorAll('.view')
        .forEach((view) =>
            view.classList.toggle('active', view.id === `view-${activeView}`),
        );
    document
        .querySelectorAll('[data-view]')
        .forEach((button) =>
            button.classList.toggle(
                'active',
                button.dataset.view === activeView,
            ),
        );
    const titles = {
        public: 'Public website',
        checkin: 'Check-in',
        attendance: 'Attendance History',
        products: 'Product inventory',
        appointments: 'Appointments',
        calendar: 'Coach calendar',
        members: 'Members',
        freeze: 'Freeze records',
        plans: 'Plans & Pricing',
        benefits: 'Membership plans',
        training: 'Training packages',
        reports: 'Revenue',
        'payments-report': 'Membership & Payments',
        website: 'Website',
        workouts: 'Workouts',
        users: 'Staff / Coaches',
        branches: 'Branches',
    };
    const title =
        titles[activeView] || activeView[0].toUpperCase() + activeView.slice(1);
    document.getElementById('page-title').textContent = title;
    document.getElementById('breadcrumb-current').textContent = title;
    document.title = `${title} · Gym SaaS Demo`;
    document.getElementById('sidebar-gym-name').textContent = state.gymName;
    document.getElementById('public-gym-name').textContent = state.gymName;
    document.getElementById('gym-name').value = state.gymName;
    document.getElementById('gym-headline').value = state.headline;
    document.getElementById('public-headline').innerHTML = esc(
        state.headline,
    ).replace(/\. /g, '.<br />');
    renderDashboard();
    renderMembers();
    renderCheckin();
    renderAttendance();
    renderProducts();
    renderAppointments();
    renderCalendar();
    renderFreeze();
    renderBenefits();
    renderSubscriptions();
    renderTraining();
    renderPaymentsReport();
    renderWorkouts();
    renderUsers();
    renderBranches();
    renderReports();
    renderChart();
}

function renderDashboard() {
    const activeMembers = state.members.filter(
        (member) => member.status !== 'Expired',
    ).length;
    const outstanding = state.members.reduce(
        (sum, member) => sum + Number(member.balance),
        0,
    );
    document.getElementById('dashboard-kpis').innerHTML = [
        kpi('ACTIVE MEMBERS', activeMembers, '↑ 8.4% this month', 'trend-up'),
        kpi(
            'TODAY’S CHECK-INS',
            state.attendance.length + 31,
            '↑ 12.1% vs last Tuesday',
            'trend-up',
        ),
        kpi(
            'MONTHLY REVENUE',
            money(142800),
            '↑ 6.8% vs last month',
            'trend-up',
        ),
        kpi(
            'OUTSTANDING BALANCE',
            money(outstanding + 17200),
            '12 members to follow up',
        ),
    ].join('');
    document.getElementById('dashboard-attendance').innerHTML = state.attendance
        .slice(0, 4)
        .map(
            (record) =>
                `<div class="mini-row"><span class="avatar avatar-red">${record.initials}</span><span><strong>${esc(record.member)}</strong><small>${esc(record.branch)} · ${esc(record.source)}</small></span><time>${esc(record.time)}</time></div>`,
        )
        .join('');
}

function renderMembers() {
    const active = state.members.filter(
        (member) => member.status === 'Active',
    ).length;
    const expiring = state.members.filter(
        (member) => member.status === 'Expiring soon',
    ).length;
    const balance = state.members.reduce(
        (sum, member) => sum + Number(member.balance),
        0,
    );
    document.getElementById('members-kpis').innerHTML = [
        kpi(
            'TOTAL MEMBERS',
            state.members.length + 242,
            '↑ 14 this month',
            'trend-up',
        ),
        kpi('ACTIVE', active + 211, `${expiring + 18} expiring soon`),
        kpi('NEW THIS MONTH', 14, '↑ 3 vs last month', 'trend-up'),
        kpi('UNPAID BALANCE', money(balance + 15800), 'Needs follow-up'),
    ].join('');
    const query = (
        document.getElementById('member-search')?.value || ''
    ).toLowerCase();
    const filter = document.getElementById('member-status')?.value || 'all';
    const members = state.members.filter(
        (member) =>
            (filter === 'all' || member.status === filter) &&
            `${member.name} ${member.id} ${member.phone}`
                .toLowerCase()
                .includes(query),
    );
    document.getElementById('members-table').innerHTML = members.length
        ? members
              .map(
                  (member) =>
                      `<tr><td><div class="member-cell"><span class="avatar avatar-red">${member.initials}</span><span><strong>${esc(member.name)}</strong><small>${esc(member.id)} · ${esc(member.phone)}</small></span></div></td><td>${esc(member.plan)}</td><td>${statusBadge(member.status)}</td><td>${member.balance ? money(member.balance) : '<span class="muted">Paid</span>'}</td><td>${esc(member.branch)}</td><td><button class="text-button" data-action="show-toast" type="button">View</button></td></tr>`,
              )
              .join('')
        : '<tr><td colspan="6" class="empty-row">No members match these filters.</td></tr>';
}

function renderAttendance() {
    document.getElementById('attendance-kpis').innerHTML = [
        kpi(
            'CHECK-INS TODAY',
            state.attendance.length + 31,
            '↑ 12.1% vs last Tuesday',
            'trend-up',
        ),
        kpi('UNIQUE MEMBERS', 38, 'of 248 active members'),
        kpi('DAILY AVERAGE', 42, 'last 30 days'),
        kpi('PEAK TIME', '7–8 AM', 'Busiest hour · Manila'),
    ].join('');
    const query = (
        document.getElementById('attendance-search')?.value || ''
    ).toLowerCase();
    const records = state.attendance.filter((record) =>
        `${record.member} ${record.branch}`.toLowerCase().includes(query),
    );
    document.getElementById('attendance-table').innerHTML = records.length
        ? records
              .map(
                  (record) =>
                      `<tr><td><div class="member-cell"><span class="avatar avatar-red">${record.initials}</span><strong>${esc(record.member)}</strong></div></td><td>${esc(record.time)}</td><td>${esc(record.branch)}</td><td>${esc(record.source)}</td><td>${statusBadge('Present')}</td></tr>`,
              )
              .join('')
        : '<tr><td colspan="5" class="empty-row">No check-ins match these filters.</td></tr>';
}

function renderCheckin() {
    const query = (
        document.getElementById('checkin-search')?.value || ''
    ).toLowerCase();
    const available = state.members.filter(
        (member) =>
            member.status !== 'Expired' &&
            `${member.name} ${member.id} ${member.phone}`
                .toLowerCase()
                .includes(query),
    );
    document.getElementById('checkin-members').innerHTML = available
        .slice(0, 4)
        .map(
            (member, index) =>
                `<label class="picker-row"><input type="radio" name="checkin-member" value="${esc(member.name)}" ${index === 0 ? 'checked' : ''} /><span class="avatar avatar-red">${member.initials}</span><span><strong>${esc(member.name)}</strong><small>${esc(member.id)} · ${esc(member.plan)} · ${esc(member.phone)}</small></span>${statusBadge(member.status)}</label>`,
        )
        .join('');
    document.getElementById('checkin-recent').innerHTML = state.attendance
        .slice(0, 5)
        .map(
            (record) =>
                `<div class="mini-row"><span class="avatar avatar-red">${record.initials}</span><span><strong>${esc(record.member)}</strong><small>${esc(record.branch)} · ${esc(record.source)}</small></span><time>${esc(record.time)}</time></div>`,
        )
        .join('');
}

function renderProducts() {
    const query = (
        document.getElementById('product-search')?.value || ''
    ).toLowerCase();
    const products = state.products.filter((product) =>
        `${product.name} ${product.sku} ${product.category}`
            .toLowerCase()
            .includes(query),
    );
    document.getElementById('products-table').innerHTML = products
        .map(
            (product, index) =>
                `<tr><td><div class="member-cell"><span class="avatar ${index % 2 ? 'avatar-dark' : 'avatar-red'}">${product.name.slice(0, 1)}</span><span><strong>${esc(product.name)}</strong><small>${esc(product.sku)}</small></span></div></td><td>${esc(product.category)}</td><td>${money(product.price)}</td><td><strong>${product.stock}</strong> units</td><td>${statusBadge(product.status)}</td><td><button class="text-button" data-action="stock" data-product="${esc(product.name)}" type="button">Stock</button></td></tr>`,
        )
        .join('');
}

function renderAppointments() {
    document.getElementById('appointment-kpis').innerHTML = [
        kpi('TODAY', 8, '5 confirmed'),
        kpi('THIS WEEK', 32, '↑ 4 vs last week', 'trend-up'),
        kpi('COMPLETED', 21, 'this month'),
        kpi('COACHES', 4, 'with availability'),
    ].join('');
    document.getElementById('appointments-table').innerHTML = state.appointments
        .map(
            (appointment) =>
                `<tr><td><div class="member-cell"><span class="avatar avatar-red">${appointment.initials}</span><strong>${esc(appointment.member)}</strong></div></td><td>${esc(appointment.service)}</td><td>${esc(appointment.coach)}</td><td>${esc(appointment.date)}</td><td>${statusBadge(appointment.status)}</td><td><button class="text-button" data-action="show-toast" type="button">View</button></td></tr>`,
        )
        .join('');
}

function renderCalendar() {
    const days = Array.from({ length: 35 }, (_, index) => index - 1);
    document.getElementById('calendar-grid').innerHTML = days
        .map(
            (day) =>
                `<div class="calendar-day ${day === 25 ? 'selected' : ''} ${day < 1 || day > 30 ? 'muted-day' : ''}"><span>${day > 0 && day <= 30 ? day : ''}</span>${day === 25 ? '<i>3 sessions</i>' : day === 26 ? '<i>1 session</i>' : ''}</div>`,
        )
        .join('');
    document.getElementById('calendar-sessions').innerHTML = state.appointments
        .slice(0, 3)
        .map(
            (appointment) =>
                `<div class="session-row"><span class="session-time">${appointment.date.split(', ')[1]}</span><span><strong>${esc(appointment.member)}</strong><small>${esc(appointment.service)} · ${esc(appointment.coach)}</small></span><span class="status-badge ${appointment.status === 'Completed' ? 'active' : 'warning'}">${appointment.status}</span></div>`,
        )
        .join('');
}

function renderFreeze() {
    document.getElementById('freeze-table').innerHTML = state.freezes
        .map(
            (record) =>
                `<tr><td><div class="member-cell"><span class="avatar avatar-red">${record.initials}</span><strong>${esc(record.member)}</strong></div></td><td>${statusBadge(record.action)}</td><td>${esc(record.period)}</td><td>${esc(record.reason)}</td><td>${esc(record.by)}</td><td>${esc(record.date)}</td></tr>`,
        )
        .join('');
}

function renderBenefits() {
    document.getElementById('benefits-table').innerHTML = state.benefits
        .map(
            (plan) =>
                `<tr><td><strong>${esc(plan.name)}</strong></td><td>${esc(plan.duration)}</td><td>${money(plan.price)}</td><td>${plan.members}</td><td>${statusBadge(plan.status)}</td><td><button class="text-button" data-action="edit-benefit" data-plan="${esc(plan.name)}" type="button">Edit</button></td></tr>`,
        )
        .join('');
}

function renderSubscriptions() {
    document.getElementById('subscriptions-table').innerHTML =
        state.subscriptions
            .map(
                (plan) =>
                    `<tr><td><strong>${esc(plan.name)}</strong><small class="table-subtext">${money(plan.price)}</small></td><td>${esc(plan.duration)}</td><td>Gym-wide access</td><td>${plan.members}</td><td>${statusBadge(plan.status)}</td><td><button class="text-button" data-action="edit-subscription" data-plan="${esc(plan.name)}" type="button">Edit</button></td></tr>`,
            )
            .join('');
}

function renderTraining() {
    document.getElementById('training-table').innerHTML = state.training
        .map(
            (packageItem) =>
                `<tr><td><strong>${esc(packageItem.name)}</strong></td><td>${packageItem.sessions} sessions</td><td>${money(packageItem.price)}</td><td>${esc(packageItem.validity)}</td><td>${esc(packageItem.coach)}</td><td>${statusBadge(packageItem.status)}</td><td><button class="text-button" data-action="edit-training" data-plan="${esc(packageItem.name)}" type="button">Edit</button></td></tr>`,
        )
        .join('');
}

function renderPaymentsReport() {
    document.getElementById('payments-report-kpis').innerHTML = [
        kpi('PURCHASES', 83, 'this month'),
        kpi('FINAL AMOUNT', money(118400), 'after discounts'),
        kpi('PAID CASH', money(106200), 'received'),
        kpi('COLLECTIBLE', money(12200), 'outstanding'),
    ].join('');
    document.getElementById('payments-report-table').innerHTML = state.payments
        .map(
            (payment) =>
                `<tr><td><div class="member-cell"><span class="avatar avatar-red">${payment.initials}</span><strong>${esc(payment.member)}</strong></div></td><td>${esc(payment.type)}</td><td>${money(payment.amount)}</td><td>${money(payment.paid)}</td><td>${payment.balance ? money(payment.balance) : '—'}</td><td>${statusBadge(payment.status === 'Paid' ? 'Active' : 'Expiring soon')}</td></tr>`,
        )
        .join('');
}

function renderWorkouts() {
    const routines = [
        {
            name: 'Full Body Foundation',
            level: 'Beginner',
            exercises: 8,
            members: 34,
            updated: 'Updated today',
        },
        {
            name: 'Upper Body Strength',
            level: 'Intermediate',
            exercises: 10,
            members: 28,
            updated: 'Updated yesterday',
        },
        {
            name: 'Mobility & Recovery',
            level: 'All levels',
            exercises: 6,
            members: 19,
            updated: 'Updated Nov 20',
        },
    ];
    document.getElementById('workout-cards').innerHTML = routines
        .map(
            (routine, index) =>
                `<article class="routine-card"><div class="routine-cover cover-${index + 1}"><span>${index === 0 ? 'FB' : index === 1 ? 'US' : 'MR'}</span></div><div class="routine-body"><span class="eyebrow">${routine.level}</span><h3>${routine.name}</h3><p>${routine.exercises} exercises · ${routine.members} assigned members</p><small>${routine.updated}</small><button class="text-button" data-action="show-toast" type="button">Open routine →</button></div></article>`,
        )
        .join('');
}

function renderUsers() {
    document.getElementById('users-kpis').innerHTML = [
        kpi('TEAM MEMBERS', state.users.length, 'of 5 included seats'),
        kpi(
            'COACHES',
            state.users.filter((user) => user.role === 'Coach').length,
            'active coaches',
        ),
        kpi(
            'STAFF',
            state.users.filter((user) => user.role === 'Staff').length,
            'active staff',
        ),
        kpi('INVITATIONS', state.invitations.length, 'awaiting acceptance'),
    ].join('');
    document.getElementById('users-table').innerHTML = state.users
        .map(
            (user) =>
                `<tr><td><div class="member-cell"><span class="avatar avatar-red">${user.initials}</span><span><strong>${esc(user.name)}</strong><small>${esc(user.email || '')}</small></span></div></td><td>${esc(user.role)}</td><td>${esc(user.branches)}</td><td>${esc(user.active)}</td><td>${statusBadge(user.status)}</td><td><button class="text-button" data-action="edit-user" data-email="${esc(user.email || '')}" type="button">Edit</button></td></tr>`,
        )
        .join('');
}

function renderBranches() {
    document.getElementById('branch-cards').innerHTML = state.branches
        .map(
            (branch) =>
                `<article class="branch-card panel"><div class="branch-card-heading"><div><span class="eyebrow">${branch.main ? 'MAIN BRANCH' : 'BRANCH'}</span><h3>${esc(branch.name)}</h3></div>${statusBadge(branch.status)}</div><p>${esc(branch.address)}</p><small>${esc(branch.phone)}</small><div class="branch-stats"><span><strong>${branch.members}</strong> members</span><span><strong>${branch.staff}</strong> team</span></div><button class="outline-button" data-action="show-toast" type="button">Edit branch</button></article>`,
        )
        .join('');
}

function renderReports() {
    document.getElementById('reports-kpis').innerHTML = [
        kpi(
            'RECORDED REVENUE',
            money(142800),
            '↑ 6.8% vs last month',
            'trend-up',
        ),
        kpi('MEMBERSHIP SALES', money(118400), '83 purchases'),
        kpi('PAYMENTS RECEIVED', money(106200), '74.2% collected'),
        kpi('NEW MEMBERS', 14, '↑ 3 vs last month', 'trend-up'),
    ].join('');
}

function renderChart() {
    const heights = [
        33, 46, 40, 64, 52, 73, 48, 79, 57, 66, 88, 69, 94, 75, 61,
    ];
    document.getElementById('chart-bars').innerHTML = heights
        .map(
            (height) =>
                `<span class="chart-bar" style="height:${height}%"></span>`,
        )
        .join('');
}

function navigate(view) {
    activeView = view;
    location.hash = view;
    document.getElementById('sidebar').classList.remove('open');
    document.querySelector('.mobile-overlay')?.classList.remove('show');
    render();
}

function showToast(message = 'Saved in this browser only.') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 2600);
}

function exportDemoCsv() {
    const lines = [
        ['Member', 'Plan', 'Status', 'Balance'],
        ...state.members.map((member) => [
            member.name,
            member.plan,
            member.status,
            member.balance,
        ]),
    ];
    const csv = lines
        .map((row) =>
            row
                .map((value) => `"${String(value).replaceAll('"', '""')}"`)
                .join(','),
        )
        .join('\r\n');
    const link = document.createElement('a');
    link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
    link.download = 'gym-saas-demo-members.csv';
    link.click();
    URL.revokeObjectURL(link.href);
    showToast('Demo CSV downloaded.');
}

function editBranchModal(branch) {
    openModal(
        'Edit branch',
        `<form class="modal-form"><label>Branch name<input name="name" value="${esc(branch.name)}" required /></label><label>Address<input name="address" value="${esc(branch.address)}" required /></label><label>Phone<input name="phone" value="${esc(branch.phone)}" required /></label><div class="form-actions"><button class="outline-button" data-modal-close type="button">Cancel</button><button class="primary-button" type="submit">Save changes</button></div></form>`,
        (data) => {
            branch.name = data.get('name').trim();
            branch.address = data.get('address').trim();
            branch.phone = data.get('phone').trim();
            saveState();
            closeModal();
            render();
            showToast('Branch updated for this session.');
        },
    );
}

function handleDemoButton(target) {
    const label = target.textContent.trim().replace(/\s+/g, ' ');
    if (/dark mode/i.test(target.getAttribute('aria-label') || '')) {
        document.body.classList.toggle('demo-dark');
        target.textContent = document.body.classList.contains('demo-dark')
            ? '☀'
            : '☾';
        return showToast(
            document.body.classList.contains('demo-dark')
                ? 'Dark mode enabled.'
                : 'Light mode enabled.',
        );
    }
    if (/notification/i.test(target.getAttribute('aria-label') || ''))
        return showToast('You are all caught up.');
    if (/New appointment/i.test(label)) return addAppointmentModal();
    if (/Export|Download/i.test(label)) return exportDemoCsv();
    if (/Edit branch/i.test(label))
        return editBranchModal(
            state.branches.find((branch) =>
                target
                    .closest('.branch-card')
                    ?.textContent.includes(branch.name),
            ) || state.branches[0],
        );
    if (/Scan QR|Start camera/i.test(label)) return checkInModal();
    if (/Current plan|Selected plan/i.test(label))
        return showToast('This is the gym’s current demo subscription.');
    if (/Check membership/i.test(label))
        return openModal(
            'Check membership',
            '<form class="modal-form"><label>Phone or email<input name="contact" placeholder="Member phone or email" required /></label><div class="form-actions"><button class="primary-button" type="submit">Check membership</button></div></form>',
            (data) => {
                const member = state.members.find(
                    (item) =>
                        item.phone === data.get('contact') ||
                        item.email === data.get('contact'),
                );
                closeModal();
                showToast(
                    member
                        ? `${member.name} · ${member.status} · ${member.plan}`
                        : 'No matching fictional member was found.',
                );
            },
        );
    return showToast(
        `${label.replace(/[+×⌄⌃→]/g, '').trim() || 'Action'} completed in demo mode.`,
    );
}

function openModal(title, body, submit) {
    const backdrop = document.getElementById('modal');
    document.getElementById('modal-title').textContent = title;
    document.getElementById('modal-body').innerHTML = body;
    backdrop.classList.add('open');
    backdrop.setAttribute('aria-hidden', 'false');
    const form = backdrop.querySelector('form');
    if (form)
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            submit(new FormData(form));
        });
    backdrop.querySelector('input, select')?.focus();
}

function closeModal() {
    const backdrop = document.getElementById('modal');
    backdrop.classList.remove('open');
    backdrop.setAttribute('aria-hidden', 'true');
}

function addMemberModal() {
    openModal(
        'Add a demo member',
        `<form class="modal-form"><label>Member name<input name="name" placeholder="e.g. Alex Mendoza" required /></label><label>Membership plan<select name="plan"><option>Pro Monthly</option><option>Strength Starter</option><option>Student Monthly</option></select></label><label>Mobile number<input name="phone" placeholder="09XX XXX XXXX" /></label><p>This fictional member will be saved only in your browser’s local storage.</p><div class="form-actions"><button class="outline-button" data-modal-close type="button">Cancel</button><button class="primary-button" type="submit">Add member</button></div></form>`,
        (data) => {
            const name = data.get('name').trim();
            state.members.unshift({
                id: `IH-${1050 + state.members.length}`,
                name,
                initials: initials(name),
                plan: data.get('plan'),
                status: 'Active',
                balance: 0,
                branch: 'Main branch',
                phone: data.get('phone') || 'Not provided',
            });
            saveState();
            closeModal();
            render();
            navigate('members');
            showToast(`${name} added to the demo.`);
        },
    );
}

function checkInModal() {
    openModal(
        'Check in a member',
        `<form class="modal-form"><label>Member<select name="member">${state.members
            .filter((member) => member.status !== 'Expired')
            .map(
                (member) =>
                    `<option value="${esc(member.name)}">${esc(member.name)} · ${esc(member.id)}</option>`,
            )
            .join(
                '',
            )}</select></label><label>Check-in method<select name="source"><option>Manual</option><option>QR scan</option></select></label><p>The check-in is added to today’s fictional attendance history.</p><div class="form-actions"><button class="outline-button" data-modal-close type="button">Cancel</button><button class="primary-button" type="submit">Record check-in</button></div></form>`,
        (data) => {
            const member = state.members.find(
                (item) => item.name === data.get('member'),
            );
            state.attendance.unshift({
                member: member.name,
                initials: member.initials,
                time: new Date().toLocaleTimeString('en-PH', {
                    hour: '2-digit',
                    minute: '2-digit',
                }),
                branch: 'Main branch',
                source: data.get('source'),
            });
            saveState();
            closeModal();
            render();
            showToast(`${member.name} checked in.`);
        },
    );
}

function productModal(productName) {
    const product = state.products.find((item) => item.name === productName);
    openModal(
        `Update ${product.name}`,
        `<form class="modal-form"><label>Stock movement<select name="movement"><option value="receive">Receive stock</option><option value="remove">Remove stock</option></select></label><label>Quantity<input name="quantity" type="number" min="1" value="1" required /></label><label>Reason<input name="reason" placeholder="Optional reason" /></label><div class="form-actions"><button class="outline-button" data-modal-close type="button">Cancel</button><button class="primary-button" type="submit">Save movement</button></div></form>`,
        (data) => {
            const quantity = Number(data.get('quantity'));
            product.stock = Math.max(
                0,
                product.stock +
                    (data.get('movement') === 'receive' ? quantity : -quantity),
            );
            product.status =
                product.stock === 0
                    ? 'Out of stock'
                    : product.stock <= product.low
                      ? 'Low stock'
                      : 'Active';
            saveState();
            closeModal();
            render();
            showToast(`${product.name} stock updated.`);
        },
    );
}

function productAddModal() {
    openModal(
        'Add a demo product',
        `<form class="modal-form"><label>Product name<input name="name" placeholder="e.g. Training Gloves" required /></label><label>Category<select name="category"><option>Accessories</option><option>Supplements</option><option>Apparel</option></select></label><label>Selling price<input name="price" type="number" min="0" placeholder="650" required /></label><label>Opening stock<input name="stock" type="number" min="0" value="0" required /></label><div class="form-actions"><button class="outline-button" data-modal-close type="button">Cancel</button><button class="primary-button" type="submit">Add product</button></div></form>`,
        (data) => {
            const name = data.get('name').trim();
            const stock = Number(data.get('stock'));
            state.products.push({
                name,
                sku: `NEW-${state.products.length + 1}`,
                category: data.get('category'),
                price: Number(data.get('price')),
                stock,
                low: 5,
                status:
                    stock === 0
                        ? 'Out of stock'
                        : stock <= 5
                          ? 'Low stock'
                          : 'Active',
            });
            saveState();
            closeModal();
            render();
            showToast(`${name} added to the catalog.`);
        },
    );
}

function subscriptionModal(existing = null) {
    const plan = existing || {
        name: '',
        duration: '30 days',
        price: 0,
        status: 'Active',
    };
    openModal(
        existing ? 'Edit Gym Access Plan' : 'Add Gym Access Plan',
        `<form class="modal-form"><label>Name<input name="name" value="${esc(plan.name)}" required /></label><label>Duration in days<input name="duration" type="number" min="1" value="${Number.parseInt(plan.duration, 10) || 30}" required /></label><label>Price (₱)<input name="price" type="number" min="0" step="0.01" value="${plan.price}" required /></label><label>Status<select name="status"><option ${plan.status === 'Active' ? 'selected' : ''}>Active</option><option ${plan.status === 'Inactive' ? 'selected' : ''}>Inactive</option></select></label><p>These prices belong to this gym’s member access catalog.</p><div class="form-actions"><button class="outline-button" data-modal-close type="button">Cancel</button><button class="primary-button" type="submit">${existing ? 'Save changes' : 'Add gym access plan'}</button></div></form>`,
        (data) => {
            const values = {
                name: data.get('name').trim(),
                duration: `${data.get('duration')} days`,
                price: Number(data.get('price')),
                status: data.get('status'),
            };
            if (existing) Object.assign(existing, values);
            else state.subscriptions.push({ ...values, members: 0 });
            saveState();
            closeModal();
            render();
            showToast(
                existing
                    ? 'Gym Access Plan updated.'
                    : 'Gym Access Plan added.',
            );
        },
    );
}

function membershipPlanModal(existing = null) {
    const plan = existing || { name: '', duration: '30 days', price: '' };
    openModal(
        existing ? 'Edit membership plan' : 'Add membership plan',
        `<form class="modal-form"><label>Plan name<input name="name" value="${esc(plan.name)}" required /></label><label>Duration<select name="duration"><option ${plan.duration === '30 days' ? 'selected' : ''}>30 days</option><option ${plan.duration === '90 days' ? 'selected' : ''}>90 days</option><option ${plan.duration === '365 days' ? 'selected' : ''}>365 days</option></select></label><label>Membership fee (₱)<input name="price" type="number" min="0" step="0.01" value="${plan.price}" required /></label><div class="form-actions"><button class="outline-button" data-modal-close type="button">Cancel</button><button class="primary-button" type="submit">${existing ? 'Save changes' : 'Add membership plan'}</button></div></form>`,
        (data) => {
            const values = {
                name: data.get('name').trim(),
                duration: data.get('duration'),
                price: Number(data.get('price')),
            };
            if (existing) Object.assign(existing, values);
            else
                state.benefits.push({
                    ...values,
                    members: 0,
                    status: 'Active',
                });
            saveState();
            closeModal();
            render();
            showToast(
                existing
                    ? 'Membership plan updated.'
                    : 'Membership plan added.',
            );
        },
    );
}

function trainingModal(existing = null) {
    const plan = existing || {
        name: '',
        sessions: 4,
        price: 0,
        validity: '30 days',
        coach: 'Any coach',
        status: 'Active',
    };
    openModal(
        existing ? 'Edit training package' : 'Add training package',
        `<form class="modal-form"><label>Package name<input name="name" value="${esc(plan.name)}" required /></label><label>Sessions<input name="sessions" type="number" min="1" value="${plan.sessions}" required /></label><label>Price (₱)<input name="price" type="number" min="0" step="0.01" value="${plan.price}" required /></label><label>Validity<select name="validity"><option ${plan.validity === '30 days' ? 'selected' : ''}>30 days</option><option ${plan.validity === '90 days' ? 'selected' : ''}>90 days</option><option ${plan.validity === 'Per session' ? 'selected' : ''}>Per session</option></select></label><label>Coach<select name="coach"><option ${plan.coach === 'Any coach' ? 'selected' : ''}>Any coach</option><option ${plan.coach === 'Marco Santos' ? 'selected' : ''}>Marco Santos</option><option ${plan.coach === 'Bea Ramos' ? 'selected' : ''}>Bea Ramos</option></select></label><div class="form-actions"><button class="outline-button" data-modal-close type="button">Cancel</button><button class="primary-button" type="submit">${existing ? 'Save changes' : 'Add package'}</button></div></form>`,
        (data) => {
            const values = {
                name: data.get('name').trim(),
                sessions: Number(data.get('sessions')),
                price: Number(data.get('price')),
                validity: data.get('validity'),
                coach: data.get('coach'),
                status: 'Active',
            };
            if (existing) Object.assign(existing, values);
            else state.training.push(values);
            saveState();
            closeModal();
            render();
            showToast(
                existing
                    ? 'Training package updated.'
                    : 'Training package added.',
            );
        },
    );
}

function userModal(existing = null) {
    const user = existing || {
        name: '',
        email: '',
        role: 'Staff',
        branches: 'All branches',
    };
    const selected =
        user.branches === 'All branches' ? ['all'] : user.branches.split(', ');
    const branchOptions = state.branches
        .map(
            (branch) =>
                `<label class="branch-choice"><input type="${user.role === 'Branch manager' ? 'radio' : 'checkbox'}" name="branches" value="${esc(branch.name)}" ${selected.includes(branch.name) ? 'checked' : ''} />${esc(branch.name)}</label>`,
        )
        .join('');
    openModal(
        existing ? 'Edit user' : 'Add user',
        `<form class="modal-form"><label>Name<input name="name" value="${esc(user.name)}" required /></label><label>Email<input name="email" type="email" value="${esc(user.email)}" placeholder="person@example.com" required /></label><label>Role<select name="role" id="user-role"><option ${user.role === 'Staff' ? 'selected' : ''}>Staff</option><option ${user.role === 'Coach' ? 'selected' : ''}>Coach</option><option ${user.role === 'Branch manager' ? 'selected' : ''}>Branch manager</option></select></label><fieldset class="branch-picker"><legend>Branch access</legend><label class="branch-choice" data-all-branches ${user.role === 'Branch manager' ? 'hidden' : ''}><input type="checkbox" name="branches" value="all" ${selected.includes('all') ? 'checked' : ''} />All branches</label>${branchOptions}<p class="branch-error" data-branch-error hidden>Select a branch or choose All branches.</p></fieldset>${existing ? '' : '<p>An invitation will be recorded for this email in the demo. No email is sent from this static site.</p>'}<div class="form-actions"><button class="outline-button" data-modal-close type="button">Cancel</button><button class="primary-button" type="submit">${existing ? 'Save changes' : 'Create user and send invite'}</button></div></form>`,
        (data) => {
            const email = data.get('email').trim().toLowerCase();
            const choices = data.getAll('branches');
            if (!choices.length) {
                document.querySelector('#modal [data-branch-error]').hidden = false;
                return;
            }
            const branchNames =
                choices.includes('all')
                    ? 'All branches'
                    : choices.join(', ');
            if (existing) {
                existing.name = data.get('name').trim();
                existing.email = email;
                existing.role = data.get('role');
                existing.branches = branchNames;
            } else {
                const name = data.get('name').trim();
                state.users.push({
                    name,
                    email,
                    initials: initials(name),
                    role: data.get('role'),
                    branches: branchNames,
                    active: 'Invitation sent just now',
                    status: 'Pending invitation',
                });
                state.invitations.push({
                    email,
                    name,
                    status: 'Pending',
                    sentAt: new Date().toLocaleString(),
                });
            }
            saveState();
            closeModal();
            render();
            showToast(
                existing
                    ? 'User details updated.'
                    : `Demo invitation recorded for ${email}.`,
            );
        },
    );
    const roleSelect = document.querySelector('#modal #user-role');
    const allBranchLabel = document.querySelector('#modal [data-all-branches]');
    const allBranches = document.querySelector('#modal input[value="all"]');
    const branchInputs = [...document.querySelectorAll(
        '#modal input[name="branches"]:not([value="all"])',
    )];
    roleSelect?.addEventListener('change', () => {
        const manager = roleSelect.value === 'Branch manager';
        allBranchLabel.hidden = manager;
        branchInputs.forEach((input) => {
            input.type = manager ? 'radio' : 'checkbox';
        });
        if (manager) {
            allBranches.checked = false;
            if (!branchInputs.some((input) => input.checked) && branchInputs[0])
                branchInputs[0].checked = true;
        }
    });
    allBranches?.addEventListener('change', () => {
        if (allBranches.checked)
            document
                .querySelectorAll(
                    '#modal input[name="branches"]:not([value="all"])',
                )
                .forEach((input) => {
                    input.checked = false;
                });
    });
    branchInputs.forEach((input) =>
        input.addEventListener('change', () => {
            document.querySelector('#modal [data-branch-error]').hidden = true;
            if (input.checked && allBranches) allBranches.checked = false;
        }),
    );
    allBranches?.addEventListener('change', () => {
        document.querySelector('#modal [data-branch-error]').hidden = true;
    });
}

document.addEventListener('click', (event) => {
    const target = event.target.closest(
        '[data-view], [data-action], [data-modal-close], button:not([type="submit"])',
    );
    if (!target) return;
    if (target.dataset.view) return navigate(target.dataset.view);
    if (target.dataset.modalClose !== undefined) return closeModal();
    if (target.dataset.action === 'add-member') return addMemberModal();
    if (target.dataset.action === 'check-in') return checkInModal();
    if (target.dataset.action === 'stock')
        return productModal(target.dataset.product);
    if (target.dataset.action === 'add-product') return productAddModal();
    if (target.dataset.action === 'add-subscription')
        return subscriptionModal();
    if (target.dataset.action === 'edit-subscription')
        return subscriptionModal(
            state.subscriptions.find(
                (plan) => plan.name === target.dataset.plan,
            ),
        );
    if (target.dataset.action === 'add-benefit') return membershipPlanModal();
    if (target.dataset.action === 'edit-benefit')
        return membershipPlanModal(
            state.benefits.find((plan) => plan.name === target.dataset.plan),
        );
    if (target.dataset.action === 'add-training') return trainingModal();
    if (target.dataset.action === 'edit-training')
        return trainingModal(
            state.training.find((plan) => plan.name === target.dataset.plan),
        );
    if (target.dataset.action === 'add-user') return userModal();
    if (target.dataset.action === 'edit-user')
        return userModal(
            state.users.find((user) => user.email === target.dataset.email),
        );
    if (target.dataset.action === 'clear-attendance') {
        const input = document.getElementById('attendance-search');
        input.value = '';
        renderAttendance();
        return showToast('Attendance filters cleared.');
    }
    if (target.dataset.action === 'save-website') {
        state.gymName =
            document.getElementById('gym-name').value.trim() || seed.gymName;
        state.headline =
            document.getElementById('gym-headline').value.trim() ||
            seed.headline;
        saveState();
        document.getElementById('public-headline').innerHTML = esc(
            state.headline,
        ).replace(/\. /g, '.<br />');
        render();
        return showToast('Website draft saved locally.');
    }
    if (target.dataset.action === 'public-preview') return navigate('public');
    if (target.dataset.action === 'show-toast') return handleDemoButton(target);
});

function addAppointmentModal() {
    openModal(
        'New appointment',
        `<form class="modal-form"><label>Member<select name="member">${state.members
            .filter((member) => member.status !== 'Expired')
            .map((member) => `<option>${esc(member.name)}</option>`)
            .join(
                '',
            )}</select></label><label>Service<input name="service" value="Personal training" required /></label><label>Coach<select name="coach"><option>Marco Santos</option><option>Bea Ramos</option></select></label><label>Date and time<input name="date" type="datetime-local" required /></label><div class="form-actions"><button class="outline-button" data-modal-close type="button">Cancel</button><button class="primary-button" type="submit">Create appointment</button></div></form>`,
        (data) => {
            const member = state.members.find(
                (item) => item.name === data.get('member'),
            );
            state.appointments.unshift({
                member: member.name,
                initials: member.initials,
                service: data.get('service'),
                coach: data.get('coach'),
                date: new Date(data.get('date')).toLocaleString('en-PH', {
                    month: 'short',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                }),
                status: 'Confirmed',
            });
            saveState();
            closeModal();
            render();
            navigate('appointments');
            showToast('Appointment added for this session.');
        },
    );
}

document.getElementById('modal-close').addEventListener('click', closeModal);
document.getElementById('modal').addEventListener('click', (event) => {
    if (event.target.id === 'modal') closeModal();
});
document.getElementById('reset-demo').addEventListener('click', () => {
    if (
        confirm('Reset all demo changes and restore the fictional sample data?')
    ) {
        sessionStorage.removeItem(STORAGE_KEY);
        state = loadState();
        render();
        showToast('Demo data reset.');
    }
});
document.getElementById('menu-toggle').addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('open');
    document.querySelector('.mobile-overlay')?.classList.toggle('show');
});
document
    .getElementById('member-search')
    .addEventListener('input', renderMembers);
document
    .getElementById('member-status')
    .addEventListener('change', renderMembers);
document
    .getElementById('attendance-search')
    .addEventListener('input', renderAttendance);
document
    .getElementById('checkin-search')
    .addEventListener('input', renderCheckin);
document
    .getElementById('product-search')
    .addEventListener('input', renderProducts);
document
    .querySelectorAll('#view-reports .report-tabs button')
    .forEach((button, index) =>
        button.addEventListener('click', () => {
            document
                .querySelectorAll('#view-reports .report-tabs button')
                .forEach((item) => item.classList.remove('active'));
            button.classList.add('active');
            if (index === 1) navigate('payments-report');
            else if (index === 2) navigate('appointments');
            else showToast('Revenue report selected.');
        }),
    );
document.querySelector('.topbar-branch').addEventListener('change', (event) => {
    document.querySelector('.workspace-plan').textContent =
        `Pro plan · ${event.target.value}`;
    showToast(`Showing ${event.target.value.toLowerCase()}.`);
});
window.addEventListener('hashchange', () => {
    activeView = location.hash.replace('#', '') || 'dashboard';
    render();
});
const overlay = document.createElement('div');
overlay.className = 'mobile-overlay';
overlay.addEventListener('click', () => {
    document.getElementById('sidebar').classList.remove('open');
    overlay.classList.remove('show');
});
document.body.appendChild(overlay);
render();
