<?php
require_once __DIR__ . '/../src/Account.php';
require_once __DIR__ . '/../src/Category.php';
require_once __DIR__ . '/../src/Institution.php';
require_once __DIR__ . '/../src/InstitutionTemplate.php';

$msg = $_GET['msg'] ?? '';
$institutions = Institution::grouped(true);
$categories = Category::getAll();
$activeId = Account::activeId();
$hasMain = Account::primaryId() !== null;

$toasts = [
    'created'       => ['Account created.', 'success'],
    'updated'       => ['Account updated.', 'success'],
    'deleted'       => ['Account deleted.', 'success'],
    'blocked'       => ['Cannot delete: this account still has transactions.', 'danger'],
    'name_required' => ['Account name is required.', 'danger'],
    'create_failed' => ['Could not create the account. Please try again.', 'danger'],
    'icon_upload_invalid' => ['Icon rejected. Use a square PNG, JPEG, WebP, or ICO within the displayed size and file limits.', 'danger'],
    'reassigned'    => ['Transactions reassigned.', 'success'],
];

$institutionLogo = function (array $institution): ?array {
    if (!empty($institution['icon_data'])) {
        return ['data' => (string) $institution['icon_data'], 'mime' => (string) ($institution['icon_mime'] ?? 'image/png')];
    }
    $url = InstitutionTemplate::logoUrlForName((string) ($institution['name'] ?? ''));
    return $url !== null ? ['url' => $url] : null;
};

ob_start();
?>

<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h2 class="text-2xl font-bold" style="color:var(--text);">Accounts</h2>
        <p class="mt-0.5 text-sm" style="color:var(--text-secondary);">One account per provider, with sections for each product you use.</p>
    </div>
    <button onclick="openTemplateChooser()" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-white" style="background:var(--accent);">+ Add Account</button>
</div>

<?php if (isset($toasts[$msg])): $t = $toasts[$msg]; ?>
    <div class="mb-5 rounded-lg border px-4 py-3 text-sm" style="background:<?= $t[1] === 'success' ? 'var(--success-soft)' : 'var(--danger-soft)' ?>;border-color:<?= $t[1] === 'success' ? 'var(--success)' : 'var(--danger)' ?>;color:<?= $t[1] === 'success' ? 'var(--success)' : 'var(--danger)' ?>;">
        <?= htmlspecialchars($t[0], ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<!-- Institution list -->
<div class="space-y-4">
    <?php foreach ($institutions as $inst): ?>
        <?php
        $instId = (int) $inst['id'];
        $sections = $inst['sections'];
        $summary = Institution::summary($instId);
        $instHasTx = Institution::hasTransactions($instId);
        $instArchived = (int) ($inst['archived'] ?? 0) === 1;
        $logo = $institutionLogo($inst);
        $instIcon = AccountIcon::for((string) $inst['name'], 'savings', (string) ($inst['color_hex'] ?? ''));
        $isActiveInst = false;
        foreach ($sections as $s) {
            if ((int) $s['id'] === $activeId) {
                $isActiveInst = true;
            }
        }
        $instForJs = $inst;
        $instForJs['logo_url'] = $logo['url'] ?? null;
        ?>
        <div class="rounded-xl p-4" data-institution-card="<?= $instId ?>" style="background:var(--bg-alt);border:1px solid <?= $isActiveInst ? 'var(--accent)' : 'var(--border)' ?>;box-shadow:var(--shadow);">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-center gap-3">
                    <?php if ($logo): ?>
                        <?php if (isset($logo['url'])): ?>
                            <img class="h-11 w-11 shrink-0 rounded-xl object-contain" src="<?= htmlspecialchars($logo['url'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                        <?php else: ?>
                            <img class="h-11 w-11 shrink-0 rounded-xl object-contain" src="data:<?= htmlspecialchars((string) $logo['mime'], ENT_QUOTES, 'UTF-8') ?>;base64,<?= htmlspecialchars((string) $logo['data'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-lg font-bold" style="background:<?= $instIcon['background'] ?>;color:<?= $instIcon['foreground'] ?>;"><?= htmlspecialchars((string) $instIcon['mark'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                    <div class="min-w-0">
                        <p class="truncate text-base font-semibold" style="color:var(--text);">
                            <?= htmlspecialchars((string) $inst['name'], ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($isActiveInst): ?><span class="ml-1 rounded px-1.5 py-0.5 text-[10px] font-bold" style="background:var(--accent-soft);color:var(--accent);">ACTIVE</span><?php endif; ?>
                            <?php if ($instArchived): ?><span class="ml-1 rounded px-1.5 py-0.5 text-[10px]" style="background:var(--bg-hover);color:var(--text-muted);">ARCHIVED</span><?php endif; ?>
                        </p>
                        <p class="truncate text-xs" style="color:var(--text-muted);">
                            <?= count($sections) ?> section<?= count($sections) === 1 ? '' : 's' ?>
                            · Net RM <?= number_format($summary['net'], 2) ?>
                            <?php if ($summary['owed'] > 0): ?> · Owed RM <?= number_format($summary['owed'], 2) ?><?php endif; ?>
                            <?php if ($summary['limit'] > 0): ?> · Available RM <?= number_format(max(0.0, $summary['limit'] - $summary['owed']), 2) ?><?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <button type="button" onclick="openAccountForm(<?= $instId ?>)" class="rounded-lg border px-2.5 py-1.5 text-xs font-medium" style="border-color:var(--border);color:var(--text-secondary);">+ Section</button>
                    <button type="button" onclick='editInstitution(<?= json_encode($instForJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' class="rounded p-1.5" style="color:var(--text-muted);" title="Edit account">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    </button>
                    <form method="POST" action="/institutions/delete" data-confirm="Delete this account and all its empty sections?" data-confirm-label="Delete" data-confirm-danger="true">
                        <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                        <input type="hidden" name="id" value="<?= $instId ?>">
                        <button type="submit" class="rounded p-1.5" style="color:<?= $instHasTx ? 'var(--text-muted)' : 'var(--text-muted)' ?>;opacity:<?= $instHasTx ? '0.35' : '1' ?>;" title="<?= $instHasTx ? 'Cannot delete: sections still have transactions' : 'Delete account' ?>" <?= $instHasTx ? 'disabled' : '' ?>>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </form>
                </div>
            </div>

            <div class="mt-3 space-y-2">
                <?php if (!$sections): ?>
                    <p class="rounded-lg border border-dashed px-3 py-4 text-center text-xs" style="border-color:var(--border);color:var(--text-muted);">No sections yet. Add the product you use (Wallet, PayLater, Card, Savings).</p>
                <?php endif; ?>
                <?php foreach ($sections as $a): ?>
                    <?php
                    $isLiability = Account::isLiability($a);
                    $bal = Account::balance((int) $a['id']);
                    $limitAmount = ($a['credit_limit'] ?? null) !== null ? (float) $a['credit_limit'] : 0.0;
                    $hasLimit = $isLiability && $limitAmount > 0;
                    $available = $hasLimit ? max(0.0, $limitAmount - max(0.0, $bal)) : 0.0;
                    $usedPct = $hasLimit ? min(100.0, max(0.0, ($bal / $limitAmount) * 100)) : 0.0;
                    $isActive = $activeId === (int) $a['id'];
                    $kindLabel = Institution::sectionRoleLabel($a);
                    $sectionIcon = AccountIcon::for((string) $a['name'], (string) $a['kind'], (string) ($a['color_hex'] ?? ''));
                    $sectionName = (string) $a['name'];
                    $sectionLogoSrc = null;
                    if (!empty($a['icon_data'])) {
                        $sectionLogoSrc = 'data:' . (string) ($a['icon_mime'] ?? 'image/png') . ';base64,' . (string) $a['icon_data'];
                    } elseif (isset($logo['url'])) {
                        $sectionLogoSrc = $logo['url'];
                    } elseif (isset($logo['data'])) {
                        $sectionLogoSrc = 'data:' . (string) $logo['mime'] . ';base64,' . (string) $logo['data'];
                    }
                    ?>
                    <div class="flex flex-col gap-2 rounded-lg px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between" data-section-row="<?= (int) $a['id'] ?>" style="background:var(--bg);border:1px solid var(--border);">
                        <div class="flex min-w-0 items-center gap-3">
                            <?php if ($sectionLogoSrc): ?>
                                <img class="h-9 w-9 shrink-0 rounded-lg object-contain" src="<?= htmlspecialchars($sectionLogoSrc, ENT_QUOTES, 'UTF-8') ?>" alt="">
                            <?php else: ?>
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-sm font-bold" style="background:<?= $sectionIcon['background'] ?>;color:<?= $sectionIcon['foreground'] ?>;"><?= htmlspecialchars((string) $sectionIcon['mark'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium" style="color:var(--text);">
                                    <?= htmlspecialchars($sectionName, ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ((int) ($a['is_primary'] ?? 0) === 1): ?><span class="ml-1 rounded px-1.5 py-0.5 text-[10px] font-bold" style="background:var(--success-soft);color:var(--income);">MAIN</span><?php endif; ?>
                                    <?php if ($isActive): ?><span class="ml-1 rounded px-1.5 py-0.5 text-[10px] font-bold" style="background:var(--accent-soft);color:var(--accent);">ACTIVE</span><?php endif; ?>
                                    <?php if ((int) $a['archived'] === 1): ?><span class="ml-1 rounded px-1.5 py-0.5 text-[10px]" style="background:var(--bg-hover);color:var(--text-muted);">ARCHIVED</span><?php endif; ?>
                                </p>
                                <p class="truncate text-[11px]" style="color:var(--text-muted);">
                                    <?= htmlspecialchars($kindLabel, ENT_QUOTES, 'UTF-8') ?>
                                    <?php if (($a['credit_limit'] ?? null) !== null): ?> · Limit RM <?= number_format((float) $a['credit_limit'], 2) ?><?php endif; ?>
                                    <?php if (($a['statement_day'] ?? null) !== null || ($a['due_day'] ?? null) !== null): ?>
                                        · <?= $a['statement_day'] !== null ? 'cut-off ' . (int) $a['statement_day'] : '' ?><?= $a['due_day'] !== null ? ($a['statement_day'] !== null ? ' · ' : '') . 'due ' . (int) $a['due_day'] : '' ?>
                                    <?php endif; ?>
                                </p>
                                <?php if ($hasLimit): ?>
                                    <div class="mt-1 flex items-center gap-2">
                                        <div class="h-1 w-24 overflow-hidden rounded-full" style="background:var(--bg-hover);">
                                            <div style="width:<?= number_format($usedPct, 1, '.', '') ?>%;height:100%;background:<?= $usedPct >= 90 ? 'var(--danger)' : 'var(--accent)' ?>;"></div>
                                        </div>
                                        <span class="text-[10px]" style="color:var(--text-muted);">RM <?= number_format($available, 2) ?> available</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <div class="text-right">
                                <p class="text-[10px]" style="color:var(--text-muted);"><?= $isLiability ? 'Outstanding' : 'Balance' ?></p>
                                <p class="text-sm font-bold" style="color:<?= $isLiability ? 'var(--expense)' : 'var(--text)' ?>;">RM <?= number_format($bal, 2) ?></p>
                            </div>
                            <div class="flex items-center gap-1">
                                <?php if (!$isActive): ?>
                                    <form method="POST" action="/accounts/activate">
                                        <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                        <input type="hidden" name="account_id" value="<?= (int) $a['id'] ?>">
                                        <input type="hidden" name="return" value="/accounts">
                                        <button type="submit" class="rounded-lg border px-2 py-1.5 text-[11px] font-medium" style="border-color:var(--border);color:var(--text-secondary);">Switch</button>
                                    </form>
                                <?php endif; ?>
                                <button type="button" onclick='editAccount(<?= json_encode($a, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' class="rounded p-1.5" style="color:var(--text-muted);" title="Edit">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                </button>
                                <form method="POST" action="/accounts/delete" data-confirm="Delete this section?" data-confirm-label="Delete" data-confirm-danger="true">
                                    <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                    <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                    <button type="submit" class="rounded p-1.5" style="color:var(--text-muted);" title="Delete section">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Reassign tool -->
<div class="mt-8 rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
    <h3 class="mb-1 text-base font-semibold" style="color:var(--text);">Move Transactions Between Sections</h3>
    <p class="mb-4 text-sm" style="color:var(--text-secondary);">Bulk-move transactions (and commitments) from one section to another. Use this to reorganise after adding a section.</p>
    <form method="POST" action="/accounts/reassign" data-confirm="Move transactions? This cannot be undone automatically." data-confirm-label="Move transactions" class="grid grid-cols-1 gap-3 sm:grid-cols-4">
        <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
        <div>
            <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">From</label>
            <select name="from_account_id" required class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                <?php foreach ($institutions as $inst): ?>
                    <optgroup label="<?= htmlspecialchars((string) $inst['name'], ENT_QUOTES, 'UTF-8') ?>">
                        <?php foreach ($inst['sections'] as $a): ?><option value="<?= (int) $a['id'] ?>"><?= htmlspecialchars((string) $a['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">To</label>
            <select name="to_account_id" required class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                <?php foreach ($institutions as $inst): ?>
                    <optgroup label="<?= htmlspecialchars((string) $inst['name'], ENT_QUOTES, 'UTF-8') ?>">
                        <?php foreach ($inst['sections'] as $a): ?><option value="<?= (int) $a['id'] ?>"><?= htmlspecialchars((string) $a['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Category (optional)</label>
            <select name="category_id" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                <option value="0">All categories</option>
                <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars((string) $c['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string) $c['type'], ENT_QUOTES, 'UTF-8') ?>)</option><?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="w-full rounded-lg py-2.5 text-sm font-semibold text-white" style="background:var(--accent);">Move</button>
        </div>
    </form>
</div>

<!-- Template chooser -->
<div id="templateModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" style="backdrop-filter:blur(4px);">
    <div class="max-h-[90vh] w-full max-w-lg overflow-auto rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow-lg);">
        <div class="mb-4 flex items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold" style="color:var(--text);">Add Account</h3>
                <p class="mt-0.5 text-xs" style="color:var(--text-secondary);">Choose a provider to pre-fill the details, or start from scratch.</p>
            </div>
            <button type="button" onclick="closeTemplateChooser()" class="rounded p-1" style="color:var(--text-muted);" aria-label="Close">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <input type="search" id="templateSearch" oninput="filterTemplates()" placeholder="Search provider" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
        <div id="templateGroups" class="mt-4 space-y-4">
            <?php foreach (InstitutionTemplate::groups() as $group): ?>
                <section data-group>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted);"><?= htmlspecialchars((string) $group['label'], ENT_QUOTES, 'UTF-8') ?></p>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <?php foreach ($group['institutions'] as $tpl): ?>
                            <?php
                            $tplLogoUrl = InstitutionTemplate::logoUrl($tpl['logo'] ?? null);
                            $tplIcon = AccountIcon::for((string) $tpl['default_name'], 'savings', (string) $tpl['color_hex']);
                            $sectionLabels = implode(' · ', array_map(static fn (array $s): string => (string) $s['label'], $tpl['sections']));
                            $search = strtolower($group['label'] . ' ' . $tpl['label'] . ' ' . $tpl['default_name'] . ' ' . $sectionLabels);
                            ?>
                            <button type="button" class="flex items-center gap-3 rounded-xl border p-3 text-left transition hover:opacity-90" data-template-key="<?= htmlspecialchars((string) $tpl['key'], ENT_QUOTES, 'UTF-8') ?>" data-search="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" onclick="selectTemplate('<?= htmlspecialchars((string) $tpl['key'], ENT_QUOTES, 'UTF-8') ?>')" style="border-color:var(--border);background:var(--bg);">
                                <?php if ($tplLogoUrl): ?>
                                    <img data-tile class="h-9 w-9 shrink-0 rounded-lg object-contain" src="<?= htmlspecialchars($tplLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
                                <?php else: ?>
                                    <span data-tile class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-sm font-bold" style="background:<?= $tplIcon['background'] ?>;color:<?= $tplIcon['foreground'] ?>;"><?= htmlspecialchars((string) $tplIcon['mark'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold" style="color:var(--text);"><?= htmlspecialchars((string) $tpl['default_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="block truncate text-[11px]" style="color:var(--text-muted);"><?= htmlspecialchars($sectionLabels, ENT_QUOTES, 'UTF-8') ?></span>
                                </span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
            <section data-group>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted);">Custom</p>
                <button type="button" onclick="openCustomAccountForm()" class="flex w-full items-center gap-3 rounded-xl border p-3 text-left transition hover:opacity-90" style="border-color:var(--border);background:var(--bg);">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" style="background:var(--bg-hover);color:var(--text-secondary);">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold" style="color:var(--text);">Custom account</span>
                        <span class="block text-[11px]" style="color:var(--text-muted);">Create a single section from scratch.</span>
                    </span>
                </button>
            </section>
        </div>
        <p class="mt-4 text-[11px]" style="color:var(--text-muted);">Provider details are presets only; verify against current terms. Brand names and logos belong to their owners.</p>
    </div>
</div>

<!-- One-question-per-screen wizard -->
<div id="wizardModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" style="backdrop-filter:blur(4px);">
    <div class="max-h-[90vh] w-full max-w-lg overflow-auto rounded-xl border p-6" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow-lg);">
        <div class="mb-4 flex items-start justify-between gap-3">
            <div class="flex items-center gap-3">
                <span id="wiz_tile" class="shrink-0"></span>
                <div>
                    <h3 id="wizardTitle" class="text-lg font-semibold" style="color:var(--text);">Add Account</h3>
                    <p id="wiz_subtitle" class="text-xs" style="color:var(--text-muted);"></p>
                </div>
            </div>
            <button type="button" onclick="closeWizard()" class="rounded p-1" style="color:var(--text-muted);" aria-label="Close">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <p id="wiz_step_label" class="mb-4 text-xs font-medium" style="color:var(--text-muted);">Step 1</p>
        <div id="wiz_body" class="min-h-[7rem] space-y-3"></div>
        <p id="wiz_error" class="mt-2 hidden text-xs" style="color:var(--danger);" role="alert"></p>
        <div class="mt-5 flex items-center justify-between gap-2">
            <button type="button" id="wiz_back" onclick="wizardBack()" class="invisible rounded-lg border px-3 py-2 text-sm font-medium" style="border-color:var(--border);color:var(--text-secondary);">Back</button>
            <div class="flex items-center gap-2">
                <button type="button" onclick="closeWizard()" class="rounded-lg border px-3 py-2 text-sm font-medium" style="border-color:var(--border);color:var(--text-secondary);">Cancel</button>
                <button type="button" id="wiz_next" onclick="wizardNext()" class="rounded-lg px-4 py-2 text-sm font-semibold text-white" style="background:var(--accent);">Next</button>
                <button type="button" id="wiz_create" onclick="wizardCreate()" class="hidden rounded-lg px-4 py-2 text-sm font-semibold text-white" style="background:var(--accent);">Create Account</button>
            </div>
        </div>
        <form id="wizardForm" method="POST" action="/institutions/create-from-template" class="hidden">
            <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
            <input type="hidden" name="template_key" id="wiz_template_key" value="">
            <input type="hidden" name="payload" id="wiz_payload" value="">
        </form>
    </div>
</div>

<!-- Institution edit modal -->
<div id="institutionModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" style="backdrop-filter:blur(4px);">
    <div class="max-h-[90vh] w-full max-w-lg overflow-auto rounded-xl border p-6" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow-lg);">
        <div class="mb-5 flex items-center justify-between">
            <h3 class="text-lg font-semibold" style="color:var(--text);">Edit Account</h3>
            <button type="button" onclick="closeInstitutionForm()" class="rounded p-1" style="color:var(--text-muted);" aria-label="Close">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" action="/institutions/save" enctype="multipart/form-data" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
            <input type="hidden" name="id" id="inst_id" value="">
            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Account name</label>
                <input type="text" name="name" id="inst_name" required class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Colour</label>
                <input type="color" name="color_hex" id="inst_color" value="#4f6ef7" class="h-9 w-full cursor-pointer rounded border-0 bg-transparent p-0">
            </div>
            <div class="rounded-xl border p-3" style="border-color:var(--border);background:var(--bg-hover);">
                <div class="flex items-center gap-3">
                    <img id="inst_icon_preview" class="hidden h-10 w-10 rounded-lg object-contain" alt="Account icon preview">
                    <p class="text-xs" style="color:var(--text-secondary);">Optional account logo. PNG, JPEG, WebP, or ICO; square 32–512 px; max 512 KiB.</p>
                </div>
                <input type="file" name="icon_file" id="inst_icon_file" accept="image/png,image/jpeg,image/webp,image/x-icon,image/vnd.microsoft.icon,.ico" class="mt-2 block w-full text-xs" style="color:var(--text-secondary);">
                <label id="inst_clear_icon_wrap" class="mt-2 hidden items-center gap-2 text-xs" style="color:var(--text-secondary);"><input type="checkbox" name="clear_icon" id="inst_clear_icon"> Remove uploaded logo</label>
            </div>
            <label class="flex items-center gap-2 text-sm" style="color:var(--text-secondary);">
                <input type="checkbox" name="archived" id="inst_archived" class="h-4 w-4" style="accent-color:var(--accent);"> Archived
            </label>
            <input type="hidden" name="sort_order" id="inst_sort" value="0">
            <button type="submit" class="w-full rounded-lg py-2.5 text-sm font-semibold text-white" style="background:var(--accent);">Save Account</button>
        </form>
    </div>
</div>

<!-- Section add/edit modal -->
<div id="accountModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" style="backdrop-filter:blur(4px);">
    <div class="max-h-[90vh] w-full max-w-lg overflow-auto rounded-xl border p-6" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow-lg);">
        <div class="mb-5 flex items-center justify-between">
            <h3 id="accountModalTitle" class="text-lg font-semibold" style="color:var(--text);">Add Section</h3>
            <button onclick="closeAccountForm()" class="rounded p-1" style="color:var(--text-muted);" aria-label="Close">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" action="/accounts/save" enctype="multipart/form-data" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
            <input type="hidden" name="id" id="acc_id" value="">
            <input type="hidden" name="institution_id" id="acc_institution_id" value="">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Name</label>
                    <input type="text" name="name" id="acc_name" required class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Kind</label>
                    <select name="kind" id="acc_kind" onchange="toggleAccountFields()" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                        <option value="savings">Savings / Wallet</option>
                        <option value="credit">Credit card</option>
                        <option value="paylater">PayLater</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Colour</label>
                <input type="color" name="color_hex" id="acc_color" value="#4f6ef7" class="h-9 w-full cursor-pointer rounded border-0 bg-transparent p-0">
            </div>
            <div class="rounded-xl border p-3" style="border-color:var(--border);background:var(--bg-hover);">
                <div class="flex items-center gap-3">
                    <img id="acc_icon_preview" class="hidden h-10 w-10 rounded-lg object-contain" alt="Section icon preview">
                    <p class="text-xs" style="color:var(--text-secondary);">Optional section icon. PNG, JPEG, WebP, or ICO; square 32–512 px; max 512 KiB.</p>
                </div>
                <div class="mt-2 flex items-center gap-2"><button type="button" id="acc_icon_choose" aria-controls="acc_icon_file" class="rounded-lg border px-3 py-2 text-xs font-semibold transition hover:opacity-80" style="border-color:var(--border);background:var(--bg-alt);color:var(--text);">Choose image</button><span id="acc_icon_filename" class="truncate text-xs" style="color:var(--text-muted);">No file selected</span></div>
                <input type="file" name="icon_file" id="acc_icon_file" accept="image/png,image/jpeg,image/webp,image/x-icon,image/vnd.microsoft.icon,.ico" class="sr-only" aria-label="Upload section icon">
                <p id="acc_icon_error" class="mt-1 hidden text-xs" style="color:var(--danger);" role="alert"></p>
                <label id="acc_clear_icon_wrap" class="mt-2 hidden items-center gap-2 text-xs" style="color:var(--text-secondary);"><input type="checkbox" name="clear_icon" id="acc_clear_icon"> Remove uploaded icon</label>
            </div>

            <div>
                <label id="acc_opening_label" class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Opening Balance (RM)</label>
                <input type="number" step="0.01" name="opening_balance" id="acc_opening" value="0" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                <p id="acc_opening_hint" class="mt-1 hidden text-[11px]" style="color:var(--text-muted);">Use a negative value for money already owed; new purchases add to the outstanding automatically.</p>
            </div>

            <div class="acc-savings space-y-3">
                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Tracking Start Month</label>
                    <input type="month" name="start_month" id="acc_start" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                </div>
                <label class="flex items-center gap-2 text-sm" style="color:var(--text-secondary);">
                    <input type="checkbox" name="is_primary" id="acc_primary" class="h-4 w-4" style="accent-color:var(--accent);"> Main account (only one; editable later)
                </label>
            </div>

            <div class="acc-liability hidden space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Statement / Cut-off Day</label>
                        <input type="number" min="1" max="31" name="statement_day" id="acc_stmt" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Due Day</label>
                        <input type="number" min="1" max="31" name="due_day" id="acc_due" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Credit Limit (optional)</label>
                    <input type="number" step="0.01" name="credit_limit" id="acc_limit" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                </div>
                <div class="acc-paylater hidden">
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Section type</label>
                    <select name="paylater_role" id="acc_role" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                        <option value="bnpl">PayLater (bills &amp; instalments)</option>
                        <option value="financing">Personal Financing / Loan</option>
                        <option value="both">PayLater + Financing (legacy)</option>
                    </select>
                    <p class="mt-1 text-[11px]" style="color:var(--text-muted);">Financing sections use the Financing page; their bills appear under their own tab.</p>
                </div>
            </div>

            <details id="acc_advanced" class="rounded-lg border px-3 py-2" style="border-color:var(--border);">
                <summary class="cursor-pointer text-xs font-semibold" style="color:var(--text-secondary);">Advanced</summary>
                <div class="mt-3 space-y-3">
                    <div class="acc-paylater grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">BNPL Mode</label>
                            <select name="bnpl_mode" id="acc_bnpl" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                                <option value="cycle">Cycle (monthly statement)</option>
                                <option value="per_purchase">Per-purchase (instalments)</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">First Due</label>
                            <select name="first_due_offset" id="acc_offset" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                                <option value="1">Next cycle</option>
                                <option value="0">At purchase</option>
                            </select>
                        </div>
                    </div>
                    <div class="acc-paylater">
                        <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Interest-free months (optional)</label>
                        <input type="number" min="1" name="no_interest_months" id="acc_nointerest" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                    </div>
                    <label class="acc-liability flex items-center gap-2 text-sm" style="color:var(--text-secondary);">
                        <input type="checkbox" name="allow_partial" id="acc_partial" class="h-4 w-4" style="accent-color:var(--accent);"> Allow partial bill payments
                    </label>
                    <label class="flex items-center gap-2 text-sm" style="color:var(--text-secondary);">
                        <input type="checkbox" name="archived" id="acc_archived" class="h-4 w-4" style="accent-color:var(--accent);"> Archived
                    </label>
                </div>
            </details>

            <button type="submit" class="w-full rounded-lg py-2.5 text-sm font-semibold text-white" style="background:var(--accent);">Save Section</button>
        </form>
    </div>
</div>

<script>
window.INSTITUTION_TEMPLATES = <?= json_encode(InstitutionTemplate::all(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.HAS_MAIN_ACCOUNT = <?= $hasMain ? 'true' : 'false' ?>;

/* ---------- helpers ---------- */
function templateShow(id) { var m = document.getElementById(id); m.classList.remove('hidden'); m.classList.add('flex'); }
function templateHide(id) { var m = document.getElementById(id); m.classList.add('hidden'); m.classList.remove('flex'); }
function kindLabel(kind) { return kind === 'paylater' ? 'Paylater' : (kind === 'credit' ? 'Credit card' : 'Savings'); }
function escHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}
function focusAccountsTrigger() {
    var trigger = document.querySelector('button[onclick="openTemplateChooser()"]');
    if (trigger) trigger.focus();
}

/* ---------- chooser ---------- */
function openTemplateChooser() {
    document.getElementById('templateSearch').value = '';
    filterTemplates();
    templateShow('templateModal');
    document.getElementById('templateSearch').focus();
}
function closeTemplateChooser() { templateHide('templateModal'); }
function openCustomAccountForm() {
    closeTemplateChooser();
    focusAccountsTrigger();
    openAccountForm(0);
}
function filterTemplates() {
    var q = document.getElementById('templateSearch').value.trim().toLowerCase();
    document.querySelectorAll('#templateGroups [data-group]').forEach(function (group) {
        var cards = group.querySelectorAll('[data-template-key]');
        if (cards.length === 0) return;
        var visible = 0;
        cards.forEach(function (card) {
            var match = !q || (card.dataset.search || '').indexOf(q) !== -1;
            card.classList.toggle('hidden', !match);
            if (match) visible++;
        });
        group.classList.toggle('hidden', visible === 0);
    });
}

/* ---------- one-question wizard ---------- */
let wiz = { key: null, tpl: null, steps: [], idx: 0, state: { sections: {} } };

function sectionState(key) {
    if (!wiz.state.sections[key]) wiz.state.sections[key] = {};
    return wiz.state.sections[key];
}

function selectTemplate(key) {
    var tpl = window.INSTITUTION_TEMPLATES[key];
    if (!tpl) return;
    closeTemplateChooser();

    wiz.key = key;
    wiz.tpl = tpl;
    wiz.state = { sections: {} };
    tpl.sections.forEach(function (s) {
        wiz.state.sections[s.key] = {};
        if (tpl.sections.length === 1) wiz.state.sections[s.key].enabled = 1;
    });

    document.getElementById('wiz_template_key').value = key;
    document.getElementById('wizardTitle').textContent = tpl.default_name;
    document.getElementById('wiz_subtitle').textContent = tpl.group_label + ' · ' + tpl.sections.length + ' section' + (tpl.sections.length === 1 ? '' : 's');

    var src = document.querySelector('[data-template-key="' + key + '"] [data-tile]');
    document.getElementById('wiz_tile').innerHTML = src ? src.outerHTML : '';

    buildSteps();
    wiz.idx = 0;
    focusAccountsTrigger();
    templateShow('wizardModal');
    renderWizard();
}

function enabledSections() {
    if (!wiz.tpl) return [];
    if (wiz.tpl.sections.length === 1) return wiz.tpl.sections;
    return wiz.tpl.sections.filter(function (s) { return !!sectionState(s.key).enabled; });
}

function buildSteps() {
    var steps = [];
    if (wiz.tpl.sections.length > 1) steps.push({ type: 'products' });
    enabledSections().forEach(function (s) {
        s.questions.forEach(function (q) {
            steps.push({ type: 'question', section: s, q: q });
        });
    });
    steps.push({ type: 'review' });
    wiz.steps = steps;
}

function closeWizard() { templateHide('wizardModal'); }

function renderWizard() {
    var step = wiz.steps[wiz.idx];
    if (!step) return;
    document.getElementById('wiz_step_label').textContent = 'Step ' + (wiz.idx + 1) + ' of ' + wiz.steps.length;
    document.getElementById('wiz_back').classList.toggle('invisible', wiz.idx === 0);
    document.getElementById('wiz_next').classList.toggle('hidden', step.type === 'review');
    document.getElementById('wiz_create').classList.toggle('hidden', step.type !== 'review');
    document.getElementById('wiz_error').classList.add('hidden');
    document.getElementById('wiz_error').textContent = '';
    var body = document.getElementById('wiz_body');

    if (step.type === 'products') {
        var html = '<p class="text-sm font-medium" style="color:var(--text);">Which ' + escHtml(wiz.tpl.label) + ' products do you use?</p>'
            + '<p class="text-[11px]" style="color:var(--text-muted);">Select only the sections you want to add.</p>';
        wiz.tpl.sections.forEach(function (s) {
            var st = sectionState(s.key);
            html += '<label class="flex items-center gap-3 rounded-lg border px-3 py-2.5 text-sm" style="border-color:var(--border);background:var(--bg);">'
                + '<input type="checkbox" onchange="toggleProduct(\'' + s.key + '\', this.checked)" ' + (st.enabled ? 'checked' : '') + ' class="h-4 w-4" style="accent-color:var(--accent);">'
                + '<span class="min-w-0"><span class="block font-medium" style="color:var(--text);">' + escHtml(s.default_name) + '</span>'
                + '<span class="block text-[11px]" style="color:var(--text-muted);">' + escHtml(kindLabel(s.kind)) + ' · ' + escHtml(s.label) + '</span></span></label>';
        });
        body.innerHTML = html;
        return;
    }

    if (step.type === 'question') {
        var s = step.section;
        var q = step.q;
        var st = sectionState(s.key);
        if (q.type === 'name') {
            body.innerHTML = '<p class="text-sm font-medium" style="color:var(--text);">What should we call this section?</p>'
                + '<input id="wiz_q_input" type="text" value="' + escHtml(st.name || s.default_name) + '" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">';
            return;
        }
        if (q.type === 'money') {
            var label = q.label + (q.optional ? ' <span style="color:var(--text-muted);">(optional)</span>' : '');
            body.innerHTML = '<p class="text-sm font-medium" style="color:var(--text);">' + label + '</p>'
                + '<div class="flex items-center gap-2"><span class="text-sm" style="color:var(--text-secondary);">RM</span>'
                + '<input id="wiz_q_input" type="number" step="0.01" min="0" value="' + escHtml(st[q.field] || '') + '" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);"></div>';
            return;
        }
        if (q.type === 'choice') {
            var html = '<p class="text-sm font-medium" style="color:var(--text);">' + escHtml(q.label) + '</p><div class="space-y-2">';
            q.options.forEach(function (opt) {
                var checked = String(st[q.field] || '') === String(opt.value);
                html += '<label class="flex items-center gap-3 rounded-lg border px-3 py-2.5 text-sm" style="border-color:' + (checked ? 'var(--accent)' : 'var(--border)') + ';background:var(--bg);">'
                    + '<input type="radio" name="wiz_choice" value="' + escHtml(opt.value) + '" ' + (checked ? 'checked' : '') + ' onchange="chooseOption(\'' + s.key + '\',\'' + opt.value + '\')" style="accent-color:var(--accent);">'
                    + '<span style="color:var(--text);">' + escHtml(opt.label) + '</span></label>';
            });
            html += '</div><p id="wiz_choice_confirm" class="text-[11px]" style="color:var(--text-secondary);">' + escHtml(currentConfirm(s, st)) + '</p>';
            body.innerHTML = html;
            return;
        }
        if (q.type === 'bill_date') {
            var due = st.bill_date ? Math.min(31, Number(st.bill_date) + (q.due_offset || 0)) : '';
            body.innerHTML = '<p class="text-sm font-medium" style="color:var(--text);">' + escHtml(q.label) + '</p>'
                + '<input id="wiz_q_input" type="number" min="1" max="31" value="' + escHtml(st.bill_date || '') + '" oninput="updateBillDue(' + (q.due_offset || 0) + ')" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">'
                + '<p id="wiz_bill_due" class="text-[11px]" style="color:var(--text-secondary);">' + (due ? 'Payment due on the ' + due + 'th.' : '') + '</p>';
            return;
        }
        if (q.type === 'main') {
            var yes = String(st.make_main || '') === '1';
            var note = window.HAS_MAIN_ACCOUNT
                ? 'You already have a main account. Choosing this one will replace it.'
                : 'Only one account can be the main account. You can change this later in account settings.';
            body.innerHTML = '<p class="text-sm font-medium" style="color:var(--text);">Make this your main account?</p>'
                + '<p class="text-[11px]" style="color:var(--text-muted);">' + note + '</p>'
                + '<div class="space-y-2">'
                + '<label class="flex items-center gap-3 rounded-lg border px-3 py-2.5 text-sm" style="border-color:var(--border);background:var(--bg);"><input type="radio" name="wiz_main" value="1" ' + (yes ? 'checked' : '') + ' style="accent-color:var(--accent);"><span style="color:var(--text);">Yes, use this as my main account</span></label>'
                + '<label class="flex items-center gap-3 rounded-lg border px-3 py-2.5 text-sm" style="border-color:var(--border);background:var(--bg);"><input type="radio" name="wiz_main" value="0" ' + (!yes ? 'checked' : '') + ' style="accent-color:var(--accent);"><span style="color:var(--text);">No, keep the current main account</span></label>'
                + '</div>';
            return;
        }
        if (q.type === 'info') {
            body.innerHTML = '<p class="text-sm" style="color:var(--text);">' + escHtml(q.text) + '</p>';
            return;
        }
    }

    renderReview(body);
}

function toggleProduct(key, checked) {
    sectionState(key).enabled = checked ? 1 : 0;
}

function chooseOption(sectionKey, value) {
    var st = sectionState(sectionKey);
    st.choice = String(value);
    var section = wiz.tpl.sections.find(function (s) { return s.key === sectionKey; });
    var choiceQ = section && section.questions.find(function (q) { return q.type === 'choice'; });
    var opt = choiceQ && choiceQ.options.find(function (o) { return String(o.value) === String(value); });
    var confirm = document.getElementById('wiz_choice_confirm');
    if (confirm && opt) confirm.textContent = opt.confirm || '';
}

function currentConfirm(section, st) {
    if (!st.choice) return '';
    var choiceQ = section.questions.find(function (q) { return q.type === 'choice'; });
    if (!choiceQ) return '';
    var opt = choiceQ.options.find(function (o) { return String(o.value) === String(st.choice); });
    return opt ? (opt.confirm || '') : '';
}

function updateBillDue(offset) {
    var input = document.getElementById('wiz_q_input');
    var out = document.getElementById('wiz_bill_due');
    if (!input || !out) return;
    if (!input.value) { out.textContent = ''; return; }
    out.textContent = 'Payment due on the ' + Math.min(31, Number(input.value) + offset) + 'th.';
}

function captureStep() {
    var step = wiz.steps[wiz.idx];
    if (!step) return true;
    var err = document.getElementById('wiz_error');
    if (step.type === 'products') {
        if (enabledSections().length === 0) {
            err.textContent = 'Select at least one product.';
            err.classList.remove('hidden');
            return false;
        }
        return true;
    }
    if (step.type !== 'question') return true;
    var s = step.section;
    var q = step.q;
    var st = sectionState(s.key);
    if (q.type === 'name') {
        var name = document.getElementById('wiz_q_input').value.trim();
        if (name === '') {
            err.textContent = 'Please enter a name.';
            err.classList.remove('hidden');
            return false;
        }
        st.name = name;
    } else if (q.type === 'money') {
        st[q.field] = document.getElementById('wiz_q_input').value;
    } else if (q.type === 'bill_date') {
        st.bill_date = document.getElementById('wiz_q_input').value;
    } else if (q.type === 'choice') {
        var selected = document.querySelector('input[name="wiz_choice"]:checked');
        if (!selected) {
            err.textContent = 'Please choose an option.';
            err.classList.remove('hidden');
            return false;
        }
        st[q.field] = selected.value;
    } else if (q.type === 'main') {
        var main = document.querySelector('input[name="wiz_main"]:checked');
        st.make_main = main ? main.value : '0';
    }
    return true;
}

function wizardNext() {
    if (!captureStep()) return;
    var step = wiz.steps[wiz.idx];
    if (step.type === 'products') {
        buildSteps();
        wiz.idx = 1;
    } else {
        wiz.idx = Math.min(wiz.idx + 1, wiz.steps.length - 1);
    }
    renderWizard();
}

function wizardBack() {
    if (wiz.idx > 0) {
        wiz.idx--;
        renderWizard();
    }
}

function dueSummary(s, st) {
    var choiceQ = s.questions.find(function (q) { return q.type === 'choice'; });
    if (choiceQ && st.choice) {
        var opt = choiceQ.options.find(function (o) { return String(o.value) === String(st.choice); });
        if (opt) return opt.due || opt.label || '';
    }
    var billQ = s.questions.find(function (q) { return q.type === 'bill_date'; });
    if (billQ && st.bill_date) {
        return 'the ' + Math.min(31, Number(st.bill_date) + (billQ.due_offset || 0)) + 'th';
    }
    if (s.presets && s.presets.due_day) return s.presets.due_day + 'th';
    return '';
}

function renderReview(body) {
    var cards = [];
    wiz.tpl.sections.forEach(function (s) {
        var st = sectionState(s.key);
        if (wiz.tpl.sections.length > 1 && !st.enabled) return;

        var rows = [];
        if (s.kind === 'savings') {
            if (st.balance !== '' && st.balance !== undefined) rows.push(['Balance', 'RM ' + Number(st.balance).toFixed(2)]);
            if (String(st.make_main) === '1') rows.push(['Main account', 'Yes']);
        } else {
            if (st.credit_limit) rows.push(['Credit limit', 'RM ' + Number(st.credit_limit).toFixed(2)]);
            var due = dueSummary(s, st);
            if (due) rows.push(['Payment due', due]);
        }

        var rowsHtml = rows.map(function (row) {
            return '<div class="flex items-center justify-between gap-3"><span>' + escHtml(row[0]) + '</span><span class="font-medium" style="color:var(--text);">' + escHtml(row[1]) + '</span></div>';
        }).join('');

        cards.push('<div class="rounded-lg border p-3" style="border-color:var(--border);background:var(--bg);">'
            + '<div class="flex items-center justify-between gap-2"><p class="truncate text-sm font-semibold" style="color:var(--text);">' + escHtml(st.name || s.default_name) + '</p>'
            + '<span class="shrink-0 rounded px-1.5 py-0.5 text-[10px] uppercase" style="background:var(--bg-hover);color:var(--text-muted);">' + escHtml(kindLabel(s.kind)) + '</span></div>'
            + (rowsHtml ? '<div class="mt-1.5 space-y-0.5 text-[11px]" style="color:var(--text-secondary);">' + rowsHtml + '</div>' : '')
            + '</div>');
    });

    body.innerHTML = cards.length
        ? '<p class="text-xs" style="color:var(--text-muted);">Review your sections</p><div class="space-y-2">' + cards.join('') + '</div>'
        : '<p class="text-sm" style="color:var(--danger);">No products selected.</p>';
}

function wizardCreate() {
    if (!captureStep()) return;
    document.getElementById('wiz_payload').value = JSON.stringify(wiz.state);
    document.getElementById('wizardForm').submit();
}

/* ---------- section modal ---------- */
function toggleAccountFields() {
    var kind = document.getElementById('acc_kind').value;
    document.querySelectorAll('.acc-savings').forEach(function (el) { el.classList.toggle('hidden', kind !== 'savings'); });
    document.querySelectorAll('.acc-liability').forEach(function (el) { el.classList.toggle('hidden', kind === 'savings'); });
    document.querySelectorAll('.acc-paylater').forEach(function (el) { el.classList.toggle('hidden', kind !== 'paylater'); });
    document.getElementById('acc_opening_label').textContent = (kind === 'savings')
        ? 'Opening Balance (RM)'
        : 'Current Outstanding (RM)';
    document.getElementById('acc_opening_hint').classList.toggle('hidden', kind === 'savings');
}

function openAccountForm(institutionId) {
    document.getElementById('accountModalTitle').textContent = 'Add Section';
    document.getElementById('acc_id').value = '';
    document.getElementById('acc_institution_id').value = institutionId || '';
    document.getElementById('acc_name').value = '';
    document.getElementById('acc_kind').value = 'savings';
    document.getElementById('acc_role').value = 'bnpl';
    document.getElementById('acc_color').value = '#4f6ef7';
    document.getElementById('acc_icon_file').value = '';
    document.getElementById('acc_icon_filename').textContent = 'No file selected';
    document.getElementById('acc_icon_error').classList.add('hidden');
    document.getElementById('acc_icon_preview').classList.add('hidden');
    document.getElementById('acc_icon_preview').removeAttribute('src');
    document.getElementById('acc_clear_icon').checked = false;
    document.getElementById('acc_clear_icon_wrap').classList.add('hidden');
    document.getElementById('acc_opening').value = '0';
    document.getElementById('acc_start').value = '';
    document.getElementById('acc_stmt').value = '';
    document.getElementById('acc_due').value = '';
    document.getElementById('acc_bnpl').value = 'cycle';
    document.getElementById('acc_offset').value = '1';
    document.getElementById('acc_limit').value = '';
    document.getElementById('acc_nointerest').value = '';
    document.getElementById('acc_partial').checked = false;
    document.getElementById('acc_archived').checked = false;
    document.getElementById('acc_primary').checked = false;
    document.getElementById('acc_advanced').open = false;
    toggleAccountFields();
    showModal();
}

function editAccount(a) {
    document.getElementById('accountModalTitle').textContent = 'Edit Section';
    document.getElementById('acc_id').value = a.id;
    document.getElementById('acc_institution_id').value = a.institution_id || '';
    document.getElementById('acc_name').value = a.name;
    document.getElementById('acc_kind').value = a.kind;
    document.getElementById('acc_role').value = a.paylater_role || 'bnpl';
    document.getElementById('acc_color').value = a.color_hex || '#4f6ef7';
    document.getElementById('acc_icon_file').value = '';
    document.getElementById('acc_icon_filename').textContent = 'No file selected';
    document.getElementById('acc_icon_error').classList.add('hidden');
    document.getElementById('acc_clear_icon').checked = false;
    document.getElementById('acc_clear_icon_wrap').classList.toggle('hidden', !a.icon_data);
    if (a.icon_data) {
        document.getElementById('acc_icon_preview').src = 'data:' + (a.icon_mime || 'image/png') + ';base64,' + a.icon_data;
        document.getElementById('acc_icon_preview').classList.remove('hidden');
    } else {
        document.getElementById('acc_icon_preview').classList.add('hidden');
        document.getElementById('acc_icon_preview').removeAttribute('src');
    }
    document.getElementById('acc_opening').value = a.opening_balance;
    document.getElementById('acc_start').value = a.start_month || '';
    document.getElementById('acc_stmt').value = a.statement_day || '';
    document.getElementById('acc_due').value = a.due_day || '';
    document.getElementById('acc_bnpl').value = a.bnpl_mode || 'cycle';
    document.getElementById('acc_offset').value = a.first_due_offset;
    document.getElementById('acc_limit').value = a.credit_limit || '';
    document.getElementById('acc_nointerest').value = a.no_interest_months || '';
    document.getElementById('acc_partial').checked = String(a.allow_partial) === '1';
    document.getElementById('acc_archived').checked = String(a.archived) === '1';
    document.getElementById('acc_primary').checked = String(a.is_primary) === '1';
    document.getElementById('acc_advanced').open = false;
    toggleAccountFields();
    showModal();
}

document.getElementById('acc_icon_choose').addEventListener('click', function () { document.getElementById('acc_icon_file').click(); });
document.getElementById('acc_icon_file').addEventListener('change', function () {
    var file = this.files[0];
    var error = document.getElementById('acc_icon_error');
    var filename = document.getElementById('acc_icon_filename');
    error.classList.add('hidden');
    error.textContent = '';
    if (!file) { filename.textContent = 'No file selected'; return; }
    filename.textContent = file.name;
    var input = this;
    function reject(message) {
        error.textContent = message;
        error.classList.remove('hidden');
        input.value = '';
        filename.textContent = 'No file selected';
    }
    var ico = file.type === 'image/x-icon' || file.type === 'image/vnd.microsoft.icon' || /\.ico$/i.test(file.name);
    var accepted = ['image/png', 'image/jpeg', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon'].includes(file.type) || ico;
    if (!accepted || file.size > 524288) {
        reject('Choose a PNG, JPEG, WebP, or ICO file no larger than 512 KiB.');
        return;
    }
    var img = new Image();
    img.onload = function () {
        var max = ico ? 256 : 512;
        if (img.naturalWidth !== img.naturalHeight || img.naturalWidth < 32 || img.naturalWidth > max) {
            reject(ico ? 'ICO frames must be square and 32–256 pixels.' : 'The icon must be square and 32–512 pixels.');
            URL.revokeObjectURL(img.src);
            return;
        }
        var preview = document.getElementById('acc_icon_preview');
        var previewUrl = img.src;
        preview.onload = function () { URL.revokeObjectURL(previewUrl); };
        preview.onerror = function () { URL.revokeObjectURL(previewUrl); };
        preview.src = previewUrl;
        preview.classList.remove('hidden');
        document.getElementById('acc_clear_icon').checked = false;
    };
    img.onerror = function () { reject('This image could not be opened. Choose a valid icon file.'); URL.revokeObjectURL(img.src); };
    img.src = URL.createObjectURL(file);
});
document.getElementById('acc_clear_icon').addEventListener('change', function () {
    if (this.checked) {
        var preview = document.getElementById('acc_icon_preview');
        preview.classList.add('hidden');
        preview.removeAttribute('src');
        document.getElementById('acc_icon_file').value = '';
        document.getElementById('acc_icon_filename').textContent = 'No file selected';
        document.getElementById('acc_icon_error').classList.add('hidden');
    }
});

function showModal() {
    var m = document.getElementById('accountModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function closeAccountForm() {
    var m = document.getElementById('accountModal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}
document.getElementById('accountModal').addEventListener('click', function (e) { if (e.target === this) closeAccountForm(); });

/* ---------- institution modal ---------- */
function editInstitution(inst) {
    document.getElementById('inst_id').value = inst.id;
    document.getElementById('inst_name').value = inst.name;
    document.getElementById('inst_color').value = inst.color_hex || '#4f6ef7';
    document.getElementById('inst_archived').checked = String(inst.archived) === '1';
    document.getElementById('inst_sort').value = inst.sort_order || 0;
    document.getElementById('inst_icon_file').value = '';
    document.getElementById('inst_clear_icon').checked = false;
    document.getElementById('inst_clear_icon_wrap').classList.toggle('hidden', !inst.icon_data);
    var preview = document.getElementById('inst_icon_preview');
    if (inst.icon_data) {
        preview.src = 'data:' + (inst.icon_mime || 'image/png') + ';base64,' + inst.icon_data;
        preview.classList.remove('hidden');
    } else if (inst.logo_url) {
        preview.src = inst.logo_url;
        preview.classList.remove('hidden');
    } else {
        preview.classList.add('hidden');
        preview.removeAttribute('src');
    }
    templateShow('institutionModal');
}
function closeInstitutionForm() { templateHide('institutionModal'); }
document.getElementById('institutionModal').addEventListener('click', function (e) { if (e.target === this) closeInstitutionForm(); });
document.getElementById('inst_icon_file').addEventListener('change', function () {
    var file = this.files[0];
    var preview = document.getElementById('inst_icon_preview');
    if (!file) { preview.classList.add('hidden'); preview.removeAttribute('src'); return; }
    preview.src = URL.createObjectURL(file);
    preview.classList.remove('hidden');
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
