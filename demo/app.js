const STORAGE_KEY = 'gymflow-static-demo-v1';

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
};

let state = loadState();
let activeView = location.hash.replace('#', '') || 'dashboard';
let toastTimer;

function loadState() {
    try {
        const saved = localStorage.getItem(STORAGE_KEY);
        return saved
            ? { ...seed, ...JSON.parse(saved) }
            : structuredClone(seed);
    } catch {
        return structuredClone(seed);
    }
}

function saveState() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
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
            : status === 'Expiring soon' || status === 'Low stock'
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
        'members',
        'attendance',
        'products',
        'plans',
        'reports',
        'website',
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
    const title =
        activeView === 'public'
            ? 'Public website'
            : activeView === 'plans'
              ? 'Plans & Pricing'
              : activeView[0].toUpperCase() + activeView.slice(1);
    document.getElementById('page-title').textContent = title;
    document.getElementById('breadcrumb-current').textContent = title;
    document.title = `${title} · GymFlow Demo`;
    document.getElementById('sidebar-gym-name').textContent = state.gymName;
    document.getElementById('public-gym-name').textContent = state.gymName;
    renderDashboard();
    renderMembers();
    renderAttendance();
    renderProducts();
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

document.addEventListener('click', (event) => {
    const target = event.target.closest(
        '[data-view], [data-action], [data-modal-close]',
    );
    if (!target) return;
    if (target.dataset.view) return navigate(target.dataset.view);
    if (target.dataset.modalClose !== undefined) return closeModal();
    if (target.dataset.action === 'add-member') return addMemberModal();
    if (target.dataset.action === 'check-in') return checkInModal();
    if (target.dataset.action === 'stock')
        return productModal(target.dataset.product);
    if (target.dataset.action === 'add-product') return productAddModal();
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
    if (target.dataset.action === 'show-toast')
        return showToast('This presentation control is simulated.');
});

document.getElementById('modal-close').addEventListener('click', closeModal);
document.getElementById('modal').addEventListener('click', (event) => {
    if (event.target.id === 'modal') closeModal();
});
document.getElementById('reset-demo').addEventListener('click', () => {
    if (
        confirm('Reset all demo changes and restore the fictional sample data?')
    ) {
        localStorage.removeItem(STORAGE_KEY);
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
    .getElementById('product-search')
    .addEventListener('input', renderProducts);
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
