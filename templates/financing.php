<?php
require_once __DIR__ . '/../src/Account.php';
require_once __DIR__ . '/../src/PaylaterPlan.php';

$account = Account::active();
$accountId = $account ? (int) $account['id'] : 0;
$isCyclePaylater = $account !== null && $account['kind'] === 'paylater' && ($account['bnpl_mode'] ?? '') === 'cycle';

$plans = $isCyclePaylater ? PaylaterPlan::forAccountByKind($accountId, 'financing') : [];
$savingsAccounts = array_values(array_filter(Account::all(), fn($a) => $a['kind'] === 'savings'));
$msg = $_GET['msg'] ?? '';

ob_start();
?>

<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h2 class="text-2xl font-bold" style="color:var(--text);">Personal Financing</h2>
        <p class="mt-0.5 text-sm" style="color:var(--text-secondary);">
            <?= $account ? htmlspecialchars((string) $account['name'], ENT_QUOTES, 'UTF-8') : 'No account' ?> · cash financing repaid monthly
        </p>
    </div>
    <a href="#financing-form" class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold text-white" style="background:var(--accent);"><span class="text-lg leading-none">+</span> Add Financing</a>
</div>

<?php if ($msg === 'created'): ?>
    <div class="mb-5 rounded-lg border px-4 py-3 text-sm" style="background:var(--success-soft);border-color:var(--success);color:var(--success);">Financing recorded.</div>
<?php endif; ?>

<?php if (!$isCyclePaylater): ?>
    <div class="rounded-xl border py-16 text-center" style="background:var(--bg-alt);border-color:var(--border);">
        <p class="text-sm" style="color:var(--text-muted);">Personal Financing is available on cycle paylater accounts (e.g. TikTok). Switch to one from the sidebar.</p>
    </div>
<?php else: ?>

<div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
    <section class="min-w-0 overflow-hidden rounded-xl border xl:col-span-8" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <div class="border-b px-5 py-4" style="border-color:var(--border-light);"><p class="card-title">Active financing</p></div>
        <?php if (empty($plans)): ?>
            <p class="px-5 py-12 text-center text-sm" style="color:var(--text-muted);">No personal financing yet.</p>
        <?php else: ?>
            <div class="divide-y" style="border-color:var(--border-light);">
                <?php foreach ($plans as $p): ?>
                    <?php $remaining = PaylaterPlan::remaining((int) $p['id']); $insts = PaylaterPlan::installments((int) $p['id']); ?>
                    <div class="px-5 py-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold" style="color:var(--text);">
                                    <?= htmlspecialchars((string) ($p['description'] ?: 'Personal Financing'), ENT_QUOTES, 'UTF-8') ?>
                                    <span class="ml-1 rounded px-1.5 py-0.5 text-[10px] font-bold" style="background:<?= $p['status'] === 'active' ? 'var(--accent-soft)' : 'var(--bg-hover)' ?>;color:<?= $p['status'] === 'active' ? 'var(--accent)' : 'var(--text-muted)' ?>;"><?= strtoupper((string) $p['status']) ?></span>
                                </p>
                                <p class="text-xs" style="color:var(--text-muted);">
                                    RM <?= number_format((float) $p['total_payable'], 2) ?> over <?= (int) $p['months'] ?> months ·
                                    RM <?= number_format((float) $p['installment_amount'], 2) ?>/mo · first due <?= date('d M Y', strtotime((string) $p['first_due_date'])) ?>
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold" style="color:var(--expense);">RM <?= number_format($remaining, 2) ?> left</span>
                                <form method="POST" action="/plans/set-kind" class="inline" data-confirm="Move this plan back to BNPL?" data-confirm-label="Move">
                                    <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                    <input type="hidden" name="plan_id" value="<?= (int) $p['id'] ?>">
                                    <input type="hidden" name="kind" value="purchase">
                                    <input type="hidden" name="return" value="/financing">
                                    <button type="submit" class="rounded-lg border px-3 py-1.5 text-xs font-semibold" style="border-color:var(--border);color:var(--text-secondary);">Move to BNPL</button>
                                </form>
                            </div>
                        </div>
                        <?php if (!empty($insts)): ?>
                            <details class="mt-3">
                                <summary class="cursor-pointer text-xs font-medium" style="color:var(--accent);">View schedule (<?= count($insts) ?> installments)</summary>
                                <div class="mt-1">
                                    <?php foreach ($insts as $i): ?>
                                        <div class="flex items-center justify-between border-b py-1.5 text-xs" style="border-color:var(--border-light);">
                                            <span style="color:var(--text-secondary);">#<?= (int) $i['seq'] ?> · Due <?= date('d M Y', strtotime((string) $i['due_date'])) ?></span>
                                            <span class="font-semibold" style="color:<?= $i['status'] === 'paid' ? 'var(--income)' : 'var(--text)' ?>;">RM <?= number_format((float) $i['amount'], 2) ?> · <?= ucfirst((string) $i['status']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <aside class="xl:col-span-4">
        <section id="financing-form" class="rounded-xl border p-4" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
            <h3 class="mb-4 flex items-center gap-2 text-base font-semibold" style="color:var(--text);"><span class="text-xl leading-none" style="color:var(--accent);">+</span> Add Financing</h3>
            <form method="POST" action="/financing/save" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Description</label>
                    <input type="text" name="description" required placeholder="e.g. TikTok Personal Financing" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Total repayable (RM)</label>
                        <input type="number" step="0.01" min="0.01" name="total_payable" required placeholder="0.00" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Cash received (RM)</label>
                        <input type="number" step="0.01" min="0.01" name="cash_received" required placeholder="0.00" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Months</label>
                        <input type="number" min="1" max="120" name="months" required value="6" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Borrow date</label>
                        <input type="date" name="date" required value="<?= date('Y-m-d') ?>" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Cash in to</label>
                    <select name="savings_account_id" required class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                        <?php foreach ($savingsAccounts as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $accountId ? '' : '' ?>><?= htmlspecialchars((string) $s['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p class="text-[11px]" style="color:var(--text-muted);">The cash is transferred into the selected savings account; any difference between the total repayable and the cash received is booked as a financing cost.</p>
                <button type="submit" class="w-full rounded-lg py-2.5 text-sm font-semibold text-white" style="background:var(--accent);">Save Financing</button>
            </form>
        </section>
    </aside>
</div>

<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
