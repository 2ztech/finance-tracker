<?php
require_once __DIR__ . '/../src/Account.php';
require_once __DIR__ . '/../src/Bill.php';
require_once __DIR__ . '/../src/PaylaterPlan.php';

$account = Account::active();
$msg = $_GET['msg'] ?? '';
$accounts = Account::all();

$toasts = [
    'paid'     => ['Payment recorded.', 'success'],
    'settled'  => ['Plan settled.', 'success'],
    'refunded' => ['Refund recorded.', 'success'],
];

$isLiability = $account ? Account::isLiability($account) : false;
$isPaylater = $account && $account['kind'] === 'paylater';
if ($isLiability) {
    Bill::sync((int) $account['id']);
}
$bills = $isLiability ? Bill::forAccount((int) $account['id']) : [];
$plans = $isPaylater ? PaylaterPlan::forAccountByKind((int) $account['id'], 'purchase') : [];

ob_start();
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold" style="color:var(--text);">Bills</h2>
    <p class="mt-0.5 text-sm" style="color:var(--text-secondary);">
        <?= $account ? htmlspecialchars((string) $account['name'], ENT_QUOTES, 'UTF-8') : 'No account' ?> · statements and repayments
    </p>
</div>

<?php if (isset($toasts[$msg])): $t = $toasts[$msg]; ?>
    <div class="mb-5 rounded-lg border px-4 py-3 text-sm" style="background:var(--success-soft);border-color:var(--success);color:var(--success);"><?= htmlspecialchars($t[0], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if (!$account || !$isLiability): ?>
    <div class="rounded-xl border py-16 text-center" style="background:var(--bg-alt);border-color:var(--border);">
        <p class="text-sm" style="color:var(--text-muted);">Bills apply to credit and paylater accounts. Switch to one from the sidebar.</p>
    </div>
<?php else: ?>

<?php if (empty($bills)): ?>
    <div class="rounded-xl border py-16 text-center" style="background:var(--bg-alt);border-color:var(--border);">
        <p class="text-sm" style="color:var(--text-muted);">No bills yet. Add a purchase on this account from Transactions.</p>
    </div>
<?php else: ?>
    <?php
    $bnplBills = [];
    $financingBills = [];
    foreach ($bills as $b) {
        if (Bill::kindOf((int) $account['id'], (string) $b['due_date']) === 'financing') {
            $financingBills[] = $b;
        } else {
            $bnplBills[] = $b;
        }
    }
    $renderBills = function (array $list, string $emptyText, string $slug) use ($account) {
        $unpaid = [];
        $paid = [];
        foreach ($list as $b) {
            if ((string) ($b['status'] ?? '') === 'paid') {
                $paid[] = $b;
            } else {
                $unpaid[] = $b;
            }
        }
        // Unpaid: soonest upcoming first. Paid: most recent first.
        usort($unpaid, fn($a, $b) => strcmp((string) $a['due_date'], (string) $b['due_date']));
        usort($paid, fn($a, $b) => strcmp((string) $b['due_date'], (string) $a['due_date']));

        if (empty($unpaid) && empty($paid)) {
            echo '<p class="py-3 text-sm" style="color:var(--text-muted);">' . htmlspecialchars($emptyText, ENT_QUOTES, 'UTF-8') . '</p>';
            return;
        }

        $renderCard = function (array $b) use ($account) {
            $pending = round((float) $b['amount_due'] - (float) $b['paid_amount'], 2);
            $items = Bill::details((int) $account['id'], $b);
            echo '<div class="rounded-xl border p-4" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">';
            echo '<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">';
            echo '<div><p class="text-base font-semibold" style="color:var(--text);">Due ' . date('d M Y', strtotime((string) $b['due_date'])) . '</p>';
            echo '<p class="text-xs" style="color:var(--text-muted);">RM ' . number_format((float) $b['paid_amount'], 2) . ' paid of RM ' . number_format((float) $b['amount_due'], 2) . '</p>';
            if (!empty($b['period_start']) && !empty($b['period_end'])) {
                echo '<p class="text-xs" style="color:var(--text-muted);">Statement ' . date('j M', strtotime((string) $b['period_start'])) . ' – ' . date('j M Y', strtotime((string) $b['period_end'])) . '</p>';
            }
            echo '</div>';
            echo '<div class="flex items-center gap-3">';
            $statusColor = $b['status'] === 'paid' ? 'var(--success-soft)' : ($b['status'] === 'partial' ? 'var(--accent-soft)' : 'var(--danger-soft)');
            $statusText = $b['status'] === 'paid' ? 'var(--success)' : ($b['status'] === 'partial' ? 'var(--accent)' : 'var(--danger)');
            echo '<span class="rounded px-2 py-0.5 text-xs font-semibold" style="background:' . $statusColor . ';color:' . $statusText . ';">' . ucfirst((string) $b['status']) . '</span>';
            if ($pending > 0) {
                echo '<span class="text-lg font-bold" style="color:var(--expense);">RM ' . number_format($pending, 2) . '</span>';
                echo '<button type="button" onclick="openPay(\'' . htmlspecialchars((string) $b['due_date'], ENT_QUOTES, 'UTF-8') . '\', ' . $pending . ')" class="rounded-lg px-3 py-2 text-sm font-semibold text-white" style="background:var(--accent);">Pay</button>';
            }
            echo '</div></div>';
            if (!empty($items)) {
                echo '<details class="mt-3 border-t pt-2" style="border-color:var(--border-light);">';
                echo '<summary class="cursor-pointer text-xs font-medium" style="color:var(--accent);">View details (' . count($items) . ' items)</summary>';
                echo '<div class="mt-1">';
                foreach ($items as $it) {
                    $negative = (float) $it['amount'] < 0;
                    echo '<div class="flex items-center justify-between gap-3 border-b py-1.5" style="border-color:var(--border-light);">';
                    echo '<div class="min-w-0"><p class="truncate text-sm" style="color:var(--text);">' . htmlspecialchars((string) $it['title'], ENT_QUOTES, 'UTF-8') . '</p>';
                    echo '<p class="text-[11px]" style="color:var(--text-muted);">' . htmlspecialchars((string) $it['meta'], ENT_QUOTES, 'UTF-8') . '</p></div>';
                    echo '<span class="whitespace-nowrap text-sm font-semibold" style="color:' . ($negative ? 'var(--income)' : 'var(--text)') . ';">' . ($negative ? '−' : '') . 'RM ' . number_format(abs((float) $it['amount']), 2) . '</span>';
                    echo '</div>';
                }
                echo '</div></details>';
            }
            echo '</div>';
        };

        echo '<div class="mb-3 inline-flex items-center gap-1 rounded-lg border p-1 text-xs" role="tablist" style="border-color:var(--border);">';
        echo '<button type="button" class="bill-tab rounded-md px-3 py-1.5 font-semibold" data-group="' . $slug . '" data-show="unpaid" aria-pressed="true" style="background:var(--accent-soft);color:var(--accent);">Unpaid (' . count($unpaid) . ')</button>';
        echo '<button type="button" class="bill-tab rounded-md px-3 py-1.5 font-medium" data-group="' . $slug . '" data-show="paid" aria-pressed="false" style="color:var(--text-secondary);">Paid (' . count($paid) . ')</button>';
        echo '</div>';

        echo '<div id="' . $slug . '-unpaid" class="space-y-3">';
        if (empty($unpaid)) {
            echo '<p class="py-3 text-sm" style="color:var(--text-muted);">No unpaid bills.</p>';
        } else {
            foreach ($unpaid as $b) { $renderCard($b); }
        }
        echo '</div>';

        echo '<div id="' . $slug . '-paid" class="hidden space-y-3">';
        if (empty($paid)) {
            echo '<p class="py-3 text-sm" style="color:var(--text-muted);">No paid bills yet.</p>';
        } else {
            foreach ($paid as $b) { $renderCard($b); }
        }
        echo '</div>';
    };
    ?>
    <section class="mb-8">
        <h3 class="mb-3 text-base font-semibold" style="color:var(--text);">BNPL</h3>
        <?php $renderBills($bnplBills, 'No BNPL bills.', 'bnpl'); ?>
    </section>
    <?php if ($isPaylater): ?>
    <section>
        <h3 class="mb-3 text-base font-semibold" style="color:var(--text);">Personal Financing</h3>
        <?php $renderBills($financingBills, 'No personal financing bills.', 'financing'); ?>
    </section>
    <?php endif; ?>
<?php endif; ?>

<!-- Plans -->
<?php if ($isPaylater): ?>
<div class="mt-8">
    <h3 class="mb-3 text-base font-semibold" style="color:var(--text);">BNPL purchases</h3>
    <?php if (empty($plans)): ?>
        <p class="text-sm" style="color:var(--text-muted);">No BNPL purchases.</p>
    <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($plans as $p): ?>
                <?php $remaining = PaylaterPlan::remaining((int) $p['id']); ?>
                <div class="rounded-xl border p-4" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold" style="color:var(--text);">
                                RM <?= number_format($p['total_payable'], 2) ?> over <?= (int)$p['months'] ?> month(s)
                                <span class="ml-1 rounded px-1.5 py-0.5 text-[10px] font-bold" style="background:<?= $p['status'] === 'active' ? 'var(--accent-soft)' : 'var(--bg-hover)' ?>;color:<?= $p['status'] === 'active' ? 'var(--accent)' : 'var(--text-muted)' ?>;"><?= strtoupper($p['status']) ?></span>
                            </p>
                            <p class="text-xs" style="color:var(--text-muted);">
                                RM <?= number_format($p['installment_amount'], 2) ?>/mo · first due <?= date('d M Y', strtotime($p['first_due_date'])) ?>
                                <?php if ((float)$p['interest'] > 0): ?> · interest RM <?= number_format($p['interest'], 2) ?><?php endif; ?>
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold" style="color:var(--expense);">RM <?= number_format($remaining, 2) ?> left</span>
                            <?php if ($remaining > 0): ?>
                            <form method="POST" action="/plans/settle" data-confirm="Settle the entire remaining balance now?" data-confirm-label="Settle" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>">
                                <input type="hidden" name="from_account_id" value="<?= (int)(Account::defaultId() ?? 0) ?>">
                                <input type="hidden" name="date" value="<?= date('Y-m-d') ?>">
                                <button type="submit" class="rounded-lg border px-3 py-1.5 text-xs font-semibold" style="border-color:var(--border);color:var(--text);">Settle</button>
                            </form>
                            <?php endif; ?>
                            <button type="button" onclick="openRefund(<?= (int)$p['id'] ?>, <?= $remaining ?>)" class="rounded-lg border px-3 py-1.5 text-xs font-semibold" style="border-color:var(--border);color:var(--text-secondary);">Refund</button>
                            <form method="POST" action="/plans/set-kind" class="inline" data-confirm="Move this plan to Personal Financing?" data-confirm-label="Move">
                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>">
                                <input type="hidden" name="kind" value="financing">
                                <input type="hidden" name="return" value="/bills">
                                <button type="submit" class="rounded-lg border px-3 py-1.5 text-xs font-semibold" style="border-color:var(--border);color:var(--accent);">Move to Financing</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- Pay modal -->
<div id="payModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" style="backdrop-filter:blur(4px);">
    <div class="w-full max-w-sm rounded-xl border p-6" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow-lg);">
        <h3 class="mb-4 text-lg font-semibold" style="color:var(--text);">Pay Bill</h3>
        <form method="POST" action="/bills/pay" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
            <input type="hidden" name="account_id" value="<?= (int)($account['id'] ?? 0) ?>">
            <input type="hidden" name="due_date" id="pay_due">
            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">From account</label>
                <select name="from_account_id" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                    <?php foreach ($accounts as $a): if ($a['id'] === ($account['id'] ?? 0)) continue; ?><option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars((string)$a['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Amount (RM)</label>
                <input type="number" step="0.01" min="0.01" name="amount" id="pay_amount" required class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Date</label>
                <input type="date" name="date" value="<?= date('Y-m-d') ?>" required class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
            </div>
            <div class="flex gap-2 pt-1">
                <button type="submit" class="flex-1 rounded-lg py-2.5 text-sm font-semibold text-white" style="background:var(--accent);">Record Payment</button>
                <button type="button" onclick="closePay()" class="rounded-lg border px-4 py-2.5 text-sm" style="border-color:var(--border);color:var(--text-secondary);">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Refund modal -->
<div id="refundModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" style="backdrop-filter:blur(4px);">
    <div class="w-full max-w-sm rounded-xl border p-6" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow-lg);">
        <h3 class="mb-4 text-lg font-semibold" style="color:var(--text);">Refund</h3>
        <form method="POST" action="/plans/refund" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
            <input type="hidden" name="plan_id" id="refund_plan">
            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Refund amount (RM)</label>
                <input type="number" step="0.01" min="0.01" name="amount" id="refund_amount" required class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Date</label>
                <input type="date" name="date" value="<?= date('Y-m-d') ?>" required class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
            </div>
            <div class="flex gap-2 pt-1">
                <button type="submit" class="flex-1 rounded-lg py-2.5 text-sm font-semibold text-white" style="background:var(--danger);">Record Refund</button>
                <button type="button" onclick="closeRefund()" class="rounded-lg border px-4 py-2.5 text-sm" style="border-color:var(--border);color:var(--text-secondary);">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPay(due, amount) {
    document.getElementById('pay_due').value = due;
    document.getElementById('pay_amount').value = amount.toFixed(2);
    var m = document.getElementById('payModal'); m.classList.remove('hidden'); m.classList.add('flex');
}
function closePay() { var m = document.getElementById('payModal'); m.classList.add('hidden'); m.classList.remove('flex'); }
function openRefund(planId, remaining) {
    document.getElementById('refund_plan').value = planId;
    document.getElementById('refund_amount').value = remaining > 0 ? remaining.toFixed(2) : '';
    var m = document.getElementById('refundModal'); m.classList.remove('hidden'); m.classList.add('flex');
}
function closeRefund() { var m = document.getElementById('refundModal'); m.classList.add('hidden'); m.classList.remove('flex'); }
document.getElementById('payModal').addEventListener('click', function(e){ if(e.target===this) closePay(); });
document.getElementById('refundModal').addEventListener('click', function(e){ if(e.target===this) closeRefund(); });

document.querySelectorAll('.bill-tab').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var group = btn.getAttribute('data-group');
        var showPaid = btn.getAttribute('data-show') === 'paid';
        document.querySelectorAll('.bill-tab[data-group="' + group + '"]').forEach(function (b) {
            var active = b === btn;
            b.setAttribute('aria-pressed', active ? 'true' : 'false');
            b.classList.toggle('font-semibold', active);
            b.classList.toggle('font-medium', !active);
            b.style.background = active ? 'var(--accent-soft)' : 'transparent';
            b.style.color = active ? 'var(--accent)' : 'var(--text-secondary)';
        });
        var unpaid = document.getElementById(group + '-unpaid');
        var paid = document.getElementById(group + '-paid');
        if (unpaid) unpaid.classList.toggle('hidden', showPaid);
        if (paid) paid.classList.toggle('hidden', !showPaid);
    });
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
