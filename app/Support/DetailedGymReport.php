<?php

namespace App\Support;

use App\Enums\AppointmentService;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CoachSchedule;
use App\Models\Member;
use App\Models\MemberPurchase;
use App\Models\MemberSubscription;
use App\Models\Payment;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;

class DetailedGymReport
{
    private function membershipLedger(array $filters, callable $memberSearch, callable $date, string $timezone): array
    {
        $gymId = app(CurrentGym::class)->id();
        $paymentsEnabled = Feature::active('payments');
        $paid = fn ($key) => Payment::withoutGlobalScope('branch')->where('gym_id', $gymId)->where('status', 'recorded')->whereNotNull($key)->selectRaw($key.', SUM(amount_centavos) AS paid, SUM(discount_centavos) AS payment_discount')->groupBy($key);
        $creator = AuditLog::where('subject_type', (new MemberSubscription)->getMorphClass())->whereColumn('subject_id', 'member_subscriptions.id')->whereIn('action', ['membership.assigned', 'membership.renewed'])->orderBy('id')->limit(1)->select('user_id');
        $subscriptions = MemberSubscription::query()->select(['member_subscriptions.id as record_id', 'member_subscriptions.member_id', 'member_subscriptions.plan_name', 'member_subscriptions.created_at', 'member_subscriptions.starts_at', 'member_subscriptions.ends_at', 'member_subscriptions.status', 'member_subscriptions.price_centavos as agreed', 'member_subscriptions.pricing_snapshot as snapshot', 'member_subscriptions.discount_type', 'member_subscriptions.discount_mode', 'member_subscriptions.discount_basis_points'])->selectSub($creator, 'creator_id')->selectRaw("'subscription' AS kind, NULL AS branch_id, COALESCE(member_subscriptions.original_price_centavos, member_subscriptions.price_centavos) AS original, 0 AS sessions, 0 AS used_sessions, 0 AS session_adjustment");
        $purchases = MemberPurchase::query()->select(['member_purchases.id as record_id', 'member_purchases.member_id', 'member_purchases.plan_name', 'member_purchases.created_at', 'member_purchases.starts_at', 'member_purchases.ends_at', 'member_purchases.status', 'member_purchases.price_centavos as agreed', 'member_purchases.snapshot', 'member_purchases.created_by as creator_id', 'member_purchases.kind', 'member_purchases.branch_id', 'member_purchases.original_price_centavos as original', 'member_purchases.sessions', 'member_purchases.used_sessions', 'member_purchases.session_adjustment'])->selectRaw("'regular' AS discount_type, 'peso' AS discount_mode, 0 AS discount_basis_points");
        // Both sides keep their tenant/manager scopes; settlement includes prior recorded branches.
        foreach ([[$subscriptions, 'member_subscriptions', 'member_subscription_id'], [$purchases, 'member_purchases', 'member_purchase_id']] as [$query, $table, $key]) {
            if ($paymentsEnabled) {
                $query->leftJoinSub($paid($key), 'settled', fn ($join) => $join->on($table.'.id', '=', 'settled.'.$key))->selectRaw('COALESCE(settled.paid, 0) AS paid, COALESCE(settled.payment_discount, 0) AS payment_discount');
                if ($filters['method'] !== '') {
                    $query->whereExists(Payment::withoutGlobalScope('branch')->where('gym_id', $gymId)->where('status', 'recorded')->where('method', $filters['method'])->whereColumn($key, $table.'.id')->selectRaw('1')->toBase());
                }
            } else {
                $query->selectRaw('0 AS paid, 0 AS payment_discount');
            }
        }
        // Union columns must have identical order, including on MariaDB.
        $fields = ['record_id', 'member_id', 'plan_name', 'created_at', 'starts_at', 'ends_at', 'status', 'agreed', 'snapshot', 'creator_id', 'kind', 'branch_id', 'original', 'sessions', 'used_sessions', 'session_adjustment', 'discount_type', 'discount_mode', 'discount_basis_points', 'paid', 'payment_discount'];
        $normalize = fn ($query) => DB::query()->fromSub($query->toBase(), 'terms')->select($fields);
        $union = $normalize($subscriptions)->unionAll($normalize($purchases));
        $id = DB::getDriverName() === 'sqlite' ? "terms.kind || '-' || terms.record_id" : "CONCAT(terms.kind, '-', terms.record_id)";
        $ledger = DB::query()->fromSub($union, 'terms')->join('members', 'members.id', '=', 'terms.member_id')->where('members.gym_id', $gymId)->leftJoin('users', 'users.id', '=', 'terms.creator_id')->select('terms.*', 'members.name as member_name', 'users.name as creator_name')->selectRaw($id.' AS id, COALESCE(terms.branch_id, members.home_branch_id) AS recorded_branch, terms.agreed - terms.payment_discount AS amount, terms.original - terms.agreed + terms.payment_discount AS discount, CASE WHEN terms.status = ? OR terms.agreed - terms.payment_discount - terms.paid < 0 THEN 0 ELSE terms.agreed - terms.payment_discount - terms.paid END AS balance', ['cancelled']);
        if ($filters['search'] !== '') {
            $ledger->whereIn('terms.member_id', Member::withoutGlobalScope('branch')->where($memberSearch)->select('id'));
        }
        $query = DB::query()->fromSub($ledger, 'ledger')->select('ledger.*')->when($filters['branch_id'], fn ($q, $branch) => $q->where('recorded_branch', $branch))->when($filters['kind'] ?? '', fn ($q, $kind) => $q->where('kind', $kind));
        if ($paymentsEnabled && ($filters['payment_state'] ?? '') !== '') {
            $query->where('status', '!=', 'cancelled');
            match ($filters['payment_state']) {
                'paid' => $query->where('balance', 0),
                'partial' => $query->where('balance', '>', 0)->where('paid', '>', 0),
                'unpaid' => $query->where('balance', '>', 0)->where('paid', 0),
            };
        }
        $branchNames = Branch::pluck('name', 'id');
        $map = function ($row) use ($timezone, $date, $paymentsEnabled, $branchNames) {
            $snapshot = json_decode($row->snapshot ?? '{}', true) ?? [];
            $savings = collect($snapshot['discounts'] ?? [])->map(fn ($d) => $d['reason'].': ₱'.number_format(($d['savings_centavos'] + ($d['vat_exemption_centavos'] ?? 0)) / 100, 2))->all();
            if (! $savings && $row->original > $row->agreed) {
                $savings[] = ucfirst($row->discount_type).' '.($row->discount_mode === 'percentage' ? ($row->discount_basis_points / 100).'%' : 'peso discount');
            }
            if ($row->payment_discount > 0) {
                $savings[] = 'Payment discount: ₱'.number_format($row->payment_discount / 100, 2);
            }
            $starts = CarbonImmutable::parse($row->starts_at, 'UTC');
            $ends = $row->ends_at ? CarbonImmutable::parse($row->ends_at, 'UTC') : null;
            $state = $row->kind === 'subscription'
                ? (new MemberSubscription)->newFromBuilder(['status' => $row->status, 'starts_at' => $row->starts_at, 'ends_at' => $row->ends_at])->displayStatus(null, $timezone)
                : ($row->status === 'cancelled' ? 'Cancelled' : ($ends && $ends <= now() ? 'Expired' : ($starts > now() ? 'Scheduled' : 'Active')));
            $settlement = $row->status === 'cancelled' ? 'Cancelled' : ($row->balance <= 0 ? 'Paid' : ($row->paid > 0 ? 'Partial' : 'Unpaid'));

            return ['id' => $row->id, 'memberId' => (int) $row->member_id, 'member' => $row->member_name, 'plan' => $row->plan_name, 'kindLabel' => ['subscription' => 'Gym subscription', 'benefit' => 'Membership plan', 'package' => 'Training package'][$row->kind], 'date' => $date(CarbonImmutable::parse($row->created_at, 'UTC')), 'createdAt' => $date(CarbonImmutable::parse($row->created_at, 'UTC')), 'createdBy' => $row->creator_name ?? 'Not recorded', 'original' => (int) $row->original, 'agreed' => (int) $row->agreed, 'amount' => (int) $row->amount, 'discount' => (int) $row->discount, 'paid' => (int) $row->paid, 'balance' => (int) $row->balance, 'status' => $paymentsEnabled && $row->status !== 'cancelled' ? $settlement.' · '.$state : $state, 'discountDetails' => implode('; ', $savings), 'starts' => $starts->setTimezone($timezone)->format('F j, Y'), 'through' => $ends?->subSecond()->setTimezone($timezone)->format('F j, Y') ?? 'No expiry', 'branch' => $branchNames[$row->recorded_branch] ?? 'Removed branch', 'sessions' => $row->kind === 'package' ? max(0, $row->sessions + $row->session_adjustment - $row->used_sessions).' of '.($row->sessions + $row->session_adjustment).' remaining' : ''];
        };

        return [$query, $map];
    }

    public function definition(string $type, array $filters): array
    {
        $timezone = app(CurrentGym::class)->get()->timezone;
        $start = CarbonImmutable::parse($filters['from'], $timezone)->startOfDay();
        $end = CarbonImmutable::parse($filters['through'], $timezone)->startOfDay()->addDay();
        $branch = $filters['branch_id'];
        $date = fn ($value) => $value?->setTimezone($timezone)->format('F j, Y g:i A');
        $column = fn ($key, $label, $format = null) => ['key' => $key, 'label' => $label, 'format' => $format];
        $memberSearch = function ($query) use ($filters) {
            $search = $filters['search'];
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%';
            $query->where(fn ($match) => $match->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("mobile LIKE ? ESCAPE '!'", [$pattern]));
        };
        if (in_array($type, ['revenue', 'payments'], true)) {
            $query = Payment::query()->when($branch, fn ($q) => $q->where('branch_id', $branch));
            if ($type === 'revenue') {
                $query->where('status', 'recorded');
            } elseif ($filters['status'] !== '') {
                $query->where('status', $filters['status']);
            }
            if ($filters['method'] !== '') {
                $query->where('method', $filters['method']);
            }
            if ($filters['search'] !== '') {
                $query->whereHas('member', $memberSearch);
            }
            $columns = [$column('date', 'Date', 'date'), $column('member', 'Member'), $column('description', 'Purchase'), $column('branch', 'Branch'), $column('method', 'Method'), $column('amount', 'Amount', 'money'), $column('status', 'Status')];
            if ($type === 'payments') {
                $columns[] = $column('reference', 'Reference');
            }
            $relations = ['member:id,name', 'subscription:id,plan_name', 'purchase:id,plan_name', 'branch:id,name', 'recorder:id,name'];
            $map = fn ($p) => ['id' => $p->id, 'date' => $date($p->paid_at), 'member' => $p->member?->name ?? 'Removed member', 'description' => $p->subscription?->plan_name ?? $p->purchase?->plan_name ?? 'Offering payment', 'branch' => $p->branch?->name ?? 'Removed branch', 'method' => ['cash' => 'Cash', 'gcash' => 'GCash', 'maya' => 'Maya', 'bank-transfer' => 'Bank transfer'][$p->method->value], 'amount' => $p->amount_centavos, 'status' => ucfirst($p->status->value), 'reference' => $p->reference ?? '', 'createdAt' => $date($p->created_at), 'createdBy' => $p->recorder?->name ?? 'Not recorded'];
            $time = 'paid_at';
            $notes = ['Revenue is recorded cash received for gym subscriptions, membership plans and training packages, excluding voided payments. No product/service sales are recorded yet.'];
        } elseif ($type === 'attendance') {
            $query = AttendanceRecord::query()->when($branch, fn ($q) => $q->where('branch_id', $branch));
            if ($filters['source'] !== '') {
                $query->where('source', $filters['source']);
            }
            if ($filters['search'] !== '') {
                $query->whereHas('member', $memberSearch);
            }
            $columns = [$column('date', 'Check-in', 'date'), $column('member', 'Member'), $column('branch', 'Branch'), $column('source', 'Source')];
            $relations = ['member:id,name', 'branch:id,name', 'recorder:id,name'];
            $map = fn ($r) => ['id' => $r->id, 'date' => $date($r->checked_in_at), 'member' => $r->member?->name ?? 'Removed member', 'branch' => $r->branch?->name ?? 'Removed branch', 'source' => $r->source === 'qr' ? 'QR scan' : 'Manual', 'createdAt' => $date($r->created_at), 'createdBy' => $r->recorder?->name ?? 'Not recorded'];
            $time = 'checked_in_at';
            $notes = ['Check-ins follow their recorded branch, including after member transfers. Unique members are counted once across this entire range.'];
        } elseif ($type === 'membership') {
            [$query, $map] = $this->membershipLedger($filters, $memberSearch, $date, $timezone);
            $columns = [$column('member', 'Member'), $column('plan', 'Purchase'), $column('amount', 'Final amount', 'money'), $column('discount', 'Savings', 'money')];
            if (Feature::active('payments')) {
                $columns = [...$columns, $column('paid', 'Paid', 'money'), $column('balance', 'Balance', 'money')];
            }
            $columns[] = $column('status', 'Status');
            $relations = [];
            $time = 'created_at';
            $notes = ['One row per purchased gym subscription, membership plan or training package, selected by creation date. Amounts use saved purchase terms, not current catalog prices.', 'Final amount = original amount - purchase savings - additional payment discounts. Paid includes recorded cash received to date; voided payments are excluded. Balance = final amount - paid; cancelled purchases have no collectible balance.', 'Recorded branch applies to plans/packages; subscriptions follow their registration branch. Revenue uses payment dates instead.'];
        } else {
            $query = CoachSchedule::where('kind', 'appointment')->when($branch, fn ($q) => $q->where('branch_id', $branch));
            if ($filters['search'] !== '') {
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['search']).'%';
                $query->where(fn ($match) => $match->whereRaw("customer_name LIKE ? ESCAPE '!'", [$pattern])->orWhereHas('member', $memberSearch));
            }
            if ($filters['status'] !== '') {
                $query->where('status', $filters['status']);
            }
            $columns = [$column('date', 'Start', 'date'), $column('ends', 'End', 'date'), $column('customer', 'Member / customer'), $column('service', 'Service'), $column('coach', 'Coach'), $column('branch', 'Branch'), $column('location', 'Location'), $column('status', 'Status')];
            $relations = ['member:id,name', 'coach:id,name', 'branch:id,name', 'creator:id,name'];
            $map = fn ($r) => ['id' => $r->id, 'date' => $date($r->starts_at), 'ends' => $date($r->ends_at), 'customer' => $r->member?->name ?? $r->customer_name, 'service' => AppointmentService::from($r->service)->label(), 'coach' => $r->coach?->name ?? 'Removed coach', 'branch' => $r->branch?->name ?? 'Removed branch', 'location' => $r->location ?? '', 'status' => ucfirst($r->status), 'createdAt' => $date($r->created_at), 'createdBy' => $r->creator?->name ?? 'Not recorded'];
            $time = 'starts_at';
            $notes = ['Bookings use their scheduled start date and recorded status. Blocked time is excluded. Appointment payments are not linked, so service revenue is not reported.'];
        }
        $base = clone $query;
        $query->where($time, '>=', $start->utc())->where($time, '<', $end->utc());

        return compact('query', 'base', 'columns', 'relations', 'map', 'time', 'notes', 'start', 'end');
    }

    public function build(string $type, array $filters, string $url): array
    {
        $d = $this->definition($type, $filters);
        $query = $d['query'];
        $metric = fn ($label, $value, $money = false) => ['label' => $label, 'value' => $value, 'money' => $money];
        $total = (clone $query)->count();
        $metrics = [$metric('Records in period', $total)];
        $breakdown = [];
        $analysis = [];
        if (in_array($type, ['revenue', 'payments'], true)) {
            $recorded = (clone $query)->where('status', 'recorded');
            $amount = (int) (clone $recorded)->sum('amount_centavos');
            $metrics = [$metric('Recorded revenue', $amount, true), $metric('Recorded payments', (clone $recorded)->count()), $metric('Voided payments', (clone $query)->where('status', 'voided')->count())];
            foreach ((clone $recorded)->selectRaw('method, SUM(amount_centavos) as total')->groupBy('method')->get() as $row) {
                $breakdown[] = ['label' => ucfirst($row->method->value), 'value' => (int) $row->total, 'money' => true];
            }
            $seconds = (int) $d['start']->diffInSeconds($d['end']);
            $previous = (int) (clone $d['base'])->where('status', 'recorded')->where('paid_at', '>=', $d['start']->subSeconds($seconds)->utc())->where('paid_at', '<', $d['start']->utc())->sum('amount_centavos');
            $metrics[] = $metric('Previous equal-length period', $previous, true);
        } elseif ($type === 'attendance') {
            $unique = (clone $query)->distinct()->count('member_id');
            $metrics = [$metric('Total check-ins', $total), $metric('Unique members', $unique), $metric('Check-ins per day', round($total / max(1, $d['start']->diffInDays($d['end'])), 1))];
            $offset = $d['start']->offset;
            $hour = DB::getDriverName() === 'sqlite' ? "CAST(strftime('%H', checked_in_at, '{$offset} seconds') AS INTEGER)" : "HOUR(DATE_ADD(checked_in_at, INTERVAL {$offset} SECOND))";
            $peak = (clone $query)->selectRaw($hour.' as hour, COUNT(*) as total')->groupByRaw($hour)->orderByDesc('total')->orderBy('hour')->first();
            $metrics[] = $metric('Peak hour (Manila)', $peak ? $d['start']->startOfDay()->addHours((int) $peak->hour)->format('g A') : 'No check-ins');
            foreach ((clone $query)->selectRaw('source, COUNT(*) as total')->groupBy('source')->get() as $row) {
                $breakdown[] = ['label' => $row->source === 'qr' ? 'QR scan' : 'Manual', 'value' => (int) $row->total, 'money' => false];
            }
        } elseif ($type === 'membership') {
            $metrics = [$metric('Purchases in period', $total), $metric('Original amount', (int) (clone $query)->sum('original'), true), $metric('Savings', (int) (clone $query)->sum('discount'), true), $metric('Final amount', (int) (clone $query)->sum('amount'), true)];
            if (Feature::active('payments')) {
                $metrics[] = $metric('Paid to date', (int) (clone $query)->sum('paid'), true);
                $metrics[] = $metric('Balance due', (int) (clone $query)->sum('balance'), true);
            }
            foreach ((clone $query)->select([])->selectRaw('kind, COUNT(*) as total')->groupBy('kind')->get() as $row) {
                $breakdown[] = ['label' => ['subscription' => 'Gym subscriptions', 'benefit' => 'Membership plans', 'package' => 'Training packages'][$row->kind], 'value' => (int) $row->total, 'money' => false];
            }
        } else {
            $metrics = [$metric('Total appointments', $total)];
            foreach (['completed' => 'Completed', 'cancelled' => 'Cancelled', 'no-show' => 'No-show'] as $value => $label) {
                $metrics[] = $metric($label, (clone $query)->where('status', $value)->count());
            }
            foreach ((clone $query)->selectRaw('status, COUNT(*) as total')->groupBy('status')->get() as $row) {
                $breakdown[] = ['label' => ucfirst($row->status), 'value' => (int) $row->total, 'money' => false];
            }
            foreach (['coach_id' => 'Coach performance', 'service' => 'Service popularity', 'branch_id' => 'Branch comparison'] as $field => $label) {
                $groups = (clone $query)->selectRaw($field." , COUNT(*) as total, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")->groupBy($field)->orderByDesc('total')->limit(10);
                if ($field === 'coach_id') {
                    $groups->with('coach:id,name');
                }
                if ($field === 'branch_id') {
                    $groups->with('branch:id,name');
                }
                $analysis[] = ['label' => $label, 'rows' => $groups->get()->map(fn ($row) => ['label' => $field === 'service' ? AppointmentService::from($row->service)->label() : ($field === 'coach_id' ? $row->coach?->name : $row->branch?->name) ?? 'Removed', 'total' => (int) $row->total, 'completed' => (int) $row->completed])->all()];
            }
        }
        $minutes = (int) ($d['start']->offset / 60);
        $time = $d['time'];
        $bucket = DB::getDriverName() === 'sqlite' ? "date({$time}, '{$minutes} minutes')" : "DATE(DATE_ADD({$time}, INTERVAL {$minutes} MINUTE))";
        $trendQuery = clone $query;
        if (in_array($type, ['revenue', 'payments'], true)) {
            $trendQuery->where('status', 'recorded');
        }
        $aggregate = in_array($type, ['revenue', 'payments'], true) ? 'SUM(amount_centavos)' : 'COUNT(*)';
        $daily = $trendQuery->select([])->selectRaw($bucket.' as bucket, '.$aggregate.' as total')->groupBy('bucket')->get()->keyBy('bucket');
        $trend = [];
        for ($at = $d['start']; $at->lessThan($d['end']); $at = $at->addDay()) {
            $trend[] = ['label' => $at->format('M j'), 'value' => (int) ($daily[$at->toDateString()]->total ?? 0)];
        }
        $rowQuery = clone $query;
        if ($d['relations']) {
            $rowQuery->with($d['relations']);
        }
        $rows = $rowQuery->orderByDesc($time)->orderByDesc('id')->paginate($filters['per_page'])->withPath($url)->appends($filters)->onEachSide(1)->through($d['map']);

        return ['columns' => $d['columns'], 'rows' => $rows, 'metrics' => $metrics, 'breakdown' => $breakdown, 'trend' => $trend, 'notes' => $d['notes'], 'analysis' => $analysis];
    }

    public function csv(string $type, array $filters)
    {
        $d = $this->definition($type, $filters);

        return response()->streamDownload(function () use ($d) {
            $out = fopen('php://output', 'w');
            $columns = [...$d['columns'], ...($d['relations'] === [] ? [['key' => 'original', 'label' => 'Original amount', 'format' => 'money'], ['key' => 'agreed', 'label' => 'Agreed amount', 'format' => 'money'], ['key' => 'discountDetails', 'label' => 'Savings details'], ['key' => 'branch', 'label' => 'Branch'], ['key' => 'kindLabel', 'label' => 'Purchase type'], ['key' => 'date', 'label' => 'Purchase date', 'format' => 'date'], ['key' => 'starts', 'label' => 'Start'], ['key' => 'through', 'label' => 'Valid through'], ['key' => 'sessions', 'label' => 'Training sessions']] : []), ['key' => 'createdAt', 'label' => 'Created (Manila)', 'format' => 'date'], ['key' => 'createdBy', 'label' => 'Created by']];
            fputcsv($out, array_column($columns, 'label'), ',', '"', '');
            $exportQuery = clone $d['query'];
            if ($d['relations']) {
                $exportQuery->with($d['relations']);
            }
            $exportQuery->chunkById(500, function ($records) use ($d, $columns, $out) {
                foreach ($records as $record) {
                    $row = ($d['map'])($record);
                    $values = array_map(function ($c) use ($row) {
                        $value = $row[$c['key']] ?? '';
                        if (($c['format'] ?? null) === 'money') {
                            $value = intdiv($value, 100).'.'.str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
                        }
                        if (is_string($value) && preg_match('/^[\s]*[=+@\-]/u', $value)) {
                            $value = "'".$value;
                        }

                        return $value;
                    }, $columns);
                    fputcsv($out, $values, ',', '"', '');
                }
            }, $d['relations'] === [] ? 'ledger.id' : null, 'id');
            fclose($out);
        }, $type.'-'.$filters['from'].'-'.$filters['through'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
