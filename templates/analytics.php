<?php
require_once __DIR__ . '/../src/Analytics.php';
require_once __DIR__ . '/../src/Widgets.php';

$analyticsQuery = [
    'range' => $_GET['range'] ?? 'last_6',
    'from' => $_GET['from'] ?? '',
    'to' => $_GET['to'] ?? '',
    'institution_id' => $_GET['institution_id'] ?? '0',
];
$analyticsRange = Analytics::range($analyticsQuery);
$analyticsFilter = Analytics::filter($analyticsQuery);
$analyticsLayout = Widgets::load();
$analyticsCatalog = Widgets::catalog();
$analyticsIndex = Widgets::index();
$analyticsEdit = (string) ($_GET['edit'] ?? '') === '1';
$analyticsInstitutions = Analytics::institutions();
$analyticsInstitutionName = $analyticsFilter['institution_name'] ?? 'All accounts';

// Build data for every widget in the saved layout.
$analyticsWidgets = [];
foreach ($analyticsLayout['widgets'] as $widget) {
    $definition = $analyticsIndex[$widget['key']];
    $data = Analytics::build($widget['key'], $widget['config'], $analyticsRange, $analyticsFilter);
    $analyticsWidgets[] = [
        'id' => $widget['id'],
        'key' => $widget['key'],
        'size' => $widget['size'],
        'config' => $widget['config'],
        'title' => $definition['label'],
        'group' => $definition['group'],
        'description' => $definition['description'],
        'meta' => $definition,
        'empty' => Analytics::isEmpty($widget['key'], $data),
        'data' => $data,
    ];
}

$analyticsSizeMap = ['half' => 6, 'wide' => 8, 'full' => 12];
$analyticsUsedKeys = array_flip(array_column($analyticsLayout['widgets'], 'key'));

$analyticsFilterQuery = static function (array $extra = []) use ($analyticsQuery, $analyticsFilter): string {
    $params = ['range' => $analyticsQuery['range'], 'institution_id' => (string) $analyticsFilter['institution_id']];
    if ($analyticsQuery['range'] === 'custom') {
        $params['from'] = $analyticsQuery['from'];
        $params['to'] = $analyticsQuery['to'];
    }
    return '?' . http_build_query(array_merge($params, $extra));
};

$analyticsCatalogByGroup = [];
foreach ($analyticsCatalog as $entry) {
    $analyticsCatalogByGroup[$entry['group']][] = $entry;
}

ob_start();
?>
<div class="dashboard-screen">
    <div class="dashboard-heading flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-3xl font-bold" style="color:var(--text);">Analytics</h2>
            <p class="mt-1 text-sm" style="color:var(--text-secondary);">
                <?= htmlspecialchars((string) $analyticsInstitutionName, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) $analyticsRange['label'], ENT_QUOTES, 'UTF-8') ?>
                <?= $analyticsEdit ? ' · <span style="color:var(--accent);font-weight:600;">Customize mode</span>' : '' ?>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <?php if ($analyticsEdit): ?>
                <button type="button" id="analyticsAddWidget" class="rounded-lg px-3 py-2 text-sm font-semibold text-white" style="background:var(--accent);">+ Add widget</button>
                <button type="button" id="analyticsResetLayout" class="rounded-lg border px-3 py-2 text-sm font-semibold" style="border-color:var(--border);background:var(--bg-alt);color:var(--danger);">Reset</button>
                <a href="<?= $analyticsFilterQuery([]) ?>" class="rounded-lg border px-3 py-2 text-sm font-semibold" style="border-color:var(--border);background:var(--bg-alt);color:var(--text-secondary);">Done</a>
            <?php else: ?>
                <a href="<?= $analyticsFilterQuery(['edit' => '1']) ?>" class="rounded-lg border px-3 py-2 text-sm font-semibold" style="border-color:var(--border);background:var(--bg-alt);color:var(--text-secondary);">Customize</a>
            <?php endif; ?>
        </div>
    </div>

    <form method="get" action="/analytics" class="rounded-xl border p-4" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);" id="analyticsFilters">
        <?php if ($analyticsEdit): ?><input type="hidden" name="edit" value="1"><?php endif; ?>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div>
                <label for="analyticsRange" class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Range</label>
                <select name="range" id="analyticsRange" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg-alt);border-color:var(--border);color:var(--text);" onchange="toggleAnalyticsCustomDates(); this.form.submit();">
                    <?php foreach (Analytics::ranges() as $rangeOption): ?>
                        <option value="<?= htmlspecialchars((string) $rangeOption['value'], ENT_QUOTES, 'UTF-8') ?>" <?= $rangeOption['value'] === $analyticsRange['preset'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $rangeOption['label'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="analyticsInstitution" class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Account</label>
                <select name="institution_id" id="analyticsInstitution" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg-alt);border-color:var(--border);color:var(--text);" onchange="this.form.submit();">
                    <option value="0" <?= $analyticsFilter['institution_id'] === 0 ? 'selected' : '' ?>>All accounts</option>
                    <?php foreach ($analyticsInstitutions as $institution): ?>
                        <option value="<?= (int) $institution['id'] ?>" <?= (int) $institution['id'] === $analyticsFilter['institution_id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $institution['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="analyticsCustomFrom" class="<?= $analyticsRange['preset'] === 'custom' ? '' : 'hidden' ?>">
                <label for="analyticsFrom" class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">From</label>
                <input type="date" name="from" id="analyticsFrom" value="<?= htmlspecialchars((string) $analyticsQuery['from'], ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg-alt);border-color:var(--border);color:var(--text);">
            </div>
            <div id="analyticsCustomTo" class="<?= $analyticsRange['preset'] === 'custom' ? '' : 'hidden' ?>">
                <label for="analyticsTo" class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">To</label>
                <input type="date" name="to" id="analyticsTo" value="<?= htmlspecialchars((string) $analyticsQuery['to'], ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg-alt);border-color:var(--border);color:var(--text);">
            </div>
        </div>
        <div id="analyticsCustomApply" class="mt-3 <?= $analyticsRange['preset'] === 'custom' ? '' : 'hidden' ?>">
            <button type="submit" class="rounded-lg px-4 py-2 text-sm font-semibold text-white" style="background:var(--accent);">Apply</button>
        </div>
    </form>

    <?php if ($analyticsWidgets === []): ?>
        <section class="rounded-xl border p-10 text-center" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
            <p class="text-sm font-medium" style="color:var(--text);">No widgets yet.</p>
            <p class="mt-1 text-sm" style="color:var(--text-secondary);">Add charts and insights to build your own analytics view.</p>
            <?php if (!$analyticsEdit): ?>
                <a href="<?= $analyticsFilterQuery(['edit' => '1']) ?>" class="mt-4 inline-block rounded-lg px-4 py-2 text-sm font-semibold text-white" style="background:var(--accent);">Add your first widget</a>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <div class="analytics-grid" id="analyticsGrid">
        <?php foreach ($analyticsWidgets as $position => $widget): ?>
            <section class="analytics-widget rounded-xl border p-4 span-<?= $analyticsSizeMap[$widget['size']] ?? 6 ?>"
                data-widget-id="<?= htmlspecialchars((string) $widget['id'], ENT_QUOTES, 'UTF-8') ?>"
                data-widget-key="<?= htmlspecialchars((string) $widget['key'], ENT_QUOTES, 'UTF-8') ?>"
                style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold" style="color:var(--text);"><?= htmlspecialchars((string) $widget['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <p class="mt-0.5 text-xs" style="color:var(--text-muted);"><?= htmlspecialchars((string) $widget['description'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <?php if ($analyticsEdit): ?>
                        <div class="flex shrink-0 items-center gap-1" role="toolbar" aria-label="Widget controls">
                            <button type="button" class="analytics-ctl" data-action="move" data-dir="-1" title="Move up" aria-label="Move up" <?= $position === 0 ? 'disabled' : '' ?>>↑</button>
                            <button type="button" class="analytics-ctl" data-action="move" data-dir="1" title="Move down" aria-label="Move down" <?= $position === count($analyticsWidgets) - 1 ? 'disabled' : '' ?>>↓</button>
                            <button type="button" class="analytics-ctl" data-action="resize" title="Resize" aria-label="Resize">⤢</button>
                            <?php if ($widget['config'] !== [] || (Widgets::find($widget['key'])['config_fields'] ?? []) !== []): ?>
                                <button type="button" class="analytics-ctl" data-action="edit" title="Edit" aria-label="Edit">✎</button>
                            <?php endif; ?>
                            <button type="button" class="analytics-ctl" data-action="remove" title="Remove" aria-label="Remove" style="color:var(--danger);">×</button>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($widget['empty']): ?>
                    <div class="flex h-44 items-center justify-center text-sm" style="color:var(--text-muted);">No data for this period.</div>
                <?php else: ?>
                    <div class="analytics-chart" style="height:200px;">
                        <canvas data-chart='<?= htmlspecialchars(json_encode(['key' => $widget['key'], 'config' => $widget['config'], 'data' => $widget['data']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>' aria-label="<?= htmlspecialchars((string) $widget['title'], ENT_QUOTES, 'UTF-8') ?>"></canvas>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>

        <?php if ($analyticsEdit): ?>
            <button type="button" id="analyticsAddWidgetTile" class="analytics-add-tile rounded-xl border-2 border-dashed p-4 text-center" style="border-color:var(--border);color:var(--text-muted);background:transparent;">
                <span class="block text-2xl">+</span>
                <span class="mt-1 block text-sm font-semibold">Add widget</span>
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Add widget modal -->
<div id="analyticsAddModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" style="backdrop-filter:blur(4px);">
    <div class="max-h-[90vh] w-full max-w-lg overflow-auto rounded-xl border p-6" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow-lg);">
        <div class="mb-5 flex items-center justify-between">
            <h3 class="text-lg font-semibold" style="color:var(--text);">Add widget</h3>
            <button type="button" class="rounded p-1" data-close-modal style="color:var(--text-muted);" aria-label="Close">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="space-y-4">
            <?php foreach ($analyticsCatalogByGroup as $group => $entries): ?>
                <div>
                    <p class="mb-2 text-[10px] font-semibold uppercase tracking-wider" style="color:var(--text-muted);"><?= htmlspecialchars((string) $group, ENT_QUOTES, 'UTF-8') ?></p>
                    <div class="space-y-1">
                        <?php foreach ($entries as $entry): $isUsed = isset($analyticsUsedKeys[$entry['key']]); ?>
                            <button type="button" class="analytics-add-option flex w-full items-start gap-3 rounded-lg border px-3 py-2.5 text-left transition <?= $isUsed ? 'is-used' : 'hover:opacity-90' ?>" data-key="<?= htmlspecialchars((string) $entry['key'], ENT_QUOTES, 'UTF-8') ?>" style="border-color:var(--border);background:var(--bg-alt);" <?= $isUsed ? 'disabled aria-disabled="true" title="Already added"' : '' ?>>
                                <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md" style="background:var(--accent-soft);color:var(--accent);">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 17l5-5 4 3 7-8M15 7h5v5"/></svg>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold" style="color:var(--text);"><?= htmlspecialchars((string) $entry['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="block text-xs" style="color:var(--text-secondary);"><?= htmlspecialchars((string) $entry['description'], ENT_QUOTES, 'UTF-8') ?></span>
                                </span>
                                <?php if ($isUsed): ?>
                                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold" style="background:var(--bg-hover);color:var(--text-muted);">Added</span>
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Edit widget config modal -->
<div id="analyticsConfigModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" style="backdrop-filter:blur(4px);">
    <div class="max-h-[90vh] w-full max-w-md overflow-auto rounded-xl border p-6" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow-lg);">
        <div class="mb-5 flex items-center justify-between">
            <h3 class="text-lg font-semibold" style="color:var(--text);">Edit widget</h3>
            <button type="button" class="rounded p-1" data-close-modal style="color:var(--text-muted);" aria-label="Close">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div id="analyticsConfigFields" class="space-y-3"></div>
        <button type="button" id="analyticsConfigSave" class="mt-5 w-full rounded-lg py-2.5 text-sm font-semibold text-white" style="background:var(--accent);">Save</button>
    </div>
</div>

<script>
    window.ANALYTICS_STATE = {
        layout: <?= json_encode($analyticsLayout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        catalog: <?= json_encode($analyticsCatalog, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        csrf: <?= json_encode(Csrf::token(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        editUrl: <?= json_encode($analyticsFilterQuery(['edit' => '1']), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    };
    function toggleAnalyticsCustomDates() {
        var custom = document.getElementById('analyticsRange').value === 'custom';
        document.getElementById('analyticsCustomFrom').classList.toggle('hidden', !custom);
        document.getElementById('analyticsCustomTo').classList.toggle('hidden', !custom);
        document.getElementById('analyticsCustomApply').classList.toggle('hidden', !custom);
    }
</script>
<script src="/analytics.js?v=<?= (int) @filemtime(__DIR__ . '/../public/analytics.js') ?>"></script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
