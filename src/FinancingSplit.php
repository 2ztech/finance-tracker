<?php

declare(strict_types=1);

/**
 * One-time migration that separates legacy combined PayLater sections.
 *
 * Old snapshots kept BNPL purchases and personal-financing plans on the same
 * account. When an institution has a section with both kinds, this creates (or
 * reuses) a financing section and moves the financing plans there, leaving the
 * original section as BNPL. The run is guarded by a settings marker so it
 * never overrides sections the user intentionally combines later.
 */
final class FinancingSplit
{
    public static function run(PDO $db): void
    {
        if (Settings::get('financing_split_v1') !== null) {
            return;
        }

        $db->beginTransaction();
        try {
            $institutions = $db->query("SELECT * FROM institutions WHERE archived = 0 ORDER BY sort_order, id")
                ->fetchAll(PDO::FETCH_ASSOC);

            foreach ($institutions as $institution) {
                self::splitInstitution($db, $institution);
            }

            Settings::set('financing_split_v1', date('Y-m-d H:i:s'));
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /** @param array<string, mixed> $institution */
    private static function splitInstitution(PDO $db, array $institution): void
    {
        $institutionId = (int) $institution['id'];
        $sections = $db->prepare("SELECT * FROM accounts
            WHERE institution_id = ? AND kind = 'paylater' AND archived = 0
            ORDER BY sort_order, id");
        $sections->execute([$institutionId]);
        $sections = $sections->fetchAll(PDO::FETCH_ASSOC);

        $countFinancing = $db->prepare("SELECT COUNT(*) FROM paylater_plans WHERE account_id = ? AND kind = 'financing'");
        $countPurchases = $db->prepare("SELECT COUNT(*) FROM paylater_plans WHERE account_id = ? AND kind = 'purchase'");
        $countOneOff = $db->prepare("SELECT COUNT(*) FROM transactions WHERE account_id = ? AND plan_id IS NULL");
        $updateRole = $db->prepare("UPDATE accounts SET paylater_role = ? WHERE id = ?");

        foreach ($sections as $section) {
            $role = (string) ($section['paylater_role'] ?? 'bnpl');
            // Explicit financing sections already own their plans.
            if ($role === 'financing') {
                continue;
            }

            $countFinancing->execute([(int) $section['id']]);
            $financing = (int) $countFinancing->fetchColumn();
            if ($financing === 0) {
                continue;
            }

            $countPurchases->execute([(int) $section['id']]);
            $purchases = (int) $countPurchases->fetchColumn();
            $countOneOff->execute([(int) $section['id']]);
            $oneOff = (int) $countOneOff->fetchColumn();

            if ($purchases === 0 && $oneOff === 0) {
                // Financing-only legacy account: just mark its role.
                $updateRole->execute(['financing', (int) $section['id']]);
                continue;
            }

            $target = self::financingSection($db, $institutionId);
            if ($target === null) {
                $targetId = self::createFinancingSection($db, $institution);
                $target = ['id' => $targetId];
            }
            $targetId = (int) $target['id'];
            if ($targetId === (int) $section['id']) {
                continue;
            }

            self::moveFinancingPlans($db, (int) $section['id'], $targetId);
            $updateRole->execute(['bnpl', (int) $section['id']]);
        }
    }

    /** @return array<string, mixed>|null */
    private static function financingSection(PDO $db, int $institutionId): ?array
    {
        $stmt = $db->prepare("SELECT * FROM accounts
            WHERE institution_id = ? AND kind = 'paylater' AND paylater_role = 'financing' AND archived = 0
            ORDER BY sort_order, id LIMIT 1");
        $stmt->execute([$institutionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @param array<string, mixed> $institution */
    private static function createFinancingSection(PDO $db, array $institution): int
    {
        $institutionId = (int) $institution['id'];
        $sort = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM accounts WHERE institution_id = ?");
        $sort->execute([$institutionId]);

        return Account::create([
            'institution_id' => $institutionId,
            'name' => self::financingSectionName((string) $institution['name']),
            'kind' => 'paylater',
            'paylater_role' => 'financing',
            'color_hex' => (string) ($institution['color_hex'] ?? '#4f6ef7'),
            'bnpl_mode' => 'cycle',
            'first_due_offset' => 1,
            'allow_partial' => 0,
            'credit_limit' => null,
            'opening_balance' => 0.0,
            'start_month' => null,
            'sort_order' => (int) $sort->fetchColumn(),
        ]);
    }

    private static function financingSectionName(string $institutionName): string
    {
        $needle = strtolower($institutionName);
        foreach (InstitutionTemplate::all() as $template) {
            if (!str_contains($needle, strtolower($template['key'])) && !str_contains($needle, strtolower($template['default_name']))) {
                continue;
            }
            foreach ($template['sections'] as $section) {
                if (($section['presets']['role'] ?? null) === 'financing') {
                    return (string) $section['default_name'];
                }
            }
        }
        return $institutionName . ' Personal Financing';
    }

    /**
     * Move all financing plans (and their linked data) to another section.
     * Installments follow the plan; paid allocation dates are copied.
     */
    public static function moveFinancingPlans(PDO $db, int $sourceId, int $targetId): int
    {
        if ($sourceId === $targetId) {
            return 0;
        }

        $plans = $db->prepare("SELECT id, purchase_txn_id, description FROM paylater_plans
            WHERE account_id = ? AND kind = 'financing'");
        $plans->execute([$sourceId]);
        $plans = $plans->fetchAll(PDO::FETCH_ASSOC);
        if ($plans === []) {
            return 0;
        }

        $updatePlan = $db->prepare("UPDATE paylater_plans SET account_id = ? WHERE id = ?");
        $updateTxn = $db->prepare("UPDATE transactions SET account_id = ?
            WHERE plan_id = ? OR (id = ? AND ? IS NOT NULL)");
        $updateTransfer = $db->prepare("UPDATE transfers SET from_account_id = ?
            WHERE from_account_id = ? AND kind = 'financing' AND description LIKE ?");
        $installments = $db->prepare("SELECT due_date, amount, paid_amount FROM paylater_installments
            WHERE plan_id = ? ORDER BY seq");
        $sourceAllocation = $db->prepare("SELECT paid_date, transfer_id FROM bill_payment_allocations
            WHERE account_id = ? AND due_date = ? ORDER BY paid_date LIMIT 1");
        $hasAllocation = $db->prepare("SELECT 1 FROM bill_payment_allocations
            WHERE account_id = ? AND due_date = ? AND paid_date = ? LIMIT 1");
        $insertAllocation = $db->prepare("INSERT INTO bill_payment_allocations
            (account_id, due_date, paid_date, amount, transfer_id, created_at) VALUES (?, ?, ?, ?, ?, ?)");

        $moved = 0;
        foreach ($plans as $plan) {
            $planId = (int) $plan['id'];
            $updatePlan->execute([$targetId, $planId]);
            $updateTxn->execute([$targetId, $planId, $plan['purchase_txn_id'], $plan['purchase_txn_id']]);

            if (!empty($plan['description'])) {
                $updateTransfer->execute([
                    $targetId,
                    $sourceId,
                    '%Financing disbursement (' . $plan['description'] . ')%',
                ]);
            }

            $installments->execute([$planId]);
            foreach ($installments->fetchAll(PDO::FETCH_ASSOC) as $installment) {
                $paid = (float) $installment['paid_amount'];
                if ($paid <= 0) {
                    continue;
                }
                $sourceAllocation->execute([$sourceId, $installment['due_date']]);
                $allocation = $sourceAllocation->fetch(PDO::FETCH_ASSOC);
                if (!$allocation) {
                    continue;
                }
                $hasAllocation->execute([$targetId, $installment['due_date'], $allocation['paid_date']]);
                if ($hasAllocation->fetchColumn()) {
                    continue;
                }
                $insertAllocation->execute([
                    $targetId,
                    $installment['due_date'],
                    $allocation['paid_date'],
                    round($paid, 2),
                    $allocation['transfer_id'] ?? null,
                    date('Y-m-d H:i:s'),
                ]);
            }
            $moved++;
        }

        Bill::sync($sourceId);
        Bill::sync($targetId);
        return $moved;
    }
}
