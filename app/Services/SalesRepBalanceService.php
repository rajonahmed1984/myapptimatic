<?php

namespace App\Services;

use App\Models\CommissionPayout;
use Illuminate\Support\Facades\DB;

class SalesRepBalanceService
{
    /**
     * Total earned from assigned project + maintenance rep amounts.
     */
    public function totalEarned(int $repId): float
    {
        return $this->fromCents(
            $this->projectEarnedCents($repId) + $this->maintenanceEarnedCents($repId)
        );
    }

    /**
     * Total paid including advances, regular payouts, and negative adjustments if stored as paid amounts.
     */
    public function totalPaidInclAdvance(int $repId): float
    {
        $paid = CommissionPayout::query()
            ->where('sales_representative_id', $repId)
            ->where('status', 'paid')
            ->sum('total_amount');

        return $this->fromCents($this->toCents($paid));
    }

    /**
     * Net payable = earned - paid (can be negative).
     */
    public function payableNet(int $repId): float
    {
        return $this->fromCents(
            $this->toCents($this->totalEarned($repId)) - $this->toCents($this->totalPaidInclAdvance($repId))
        );
    }

    public function breakdown(int $repId): array
    {
        $projectEarned = $this->fromCents($this->projectEarnedCents($repId));
        $maintenanceEarned = $this->fromCents($this->maintenanceEarnedCents($repId));
        $totalEarned = $this->fromCents($this->toCents($projectEarned) + $this->toCents($maintenanceEarned));
        $totalPaid = $this->totalPaidInclAdvance($repId);
        $payableNet = $this->fromCents($this->toCents($totalEarned) - $this->toCents($totalPaid));

        return [
            'project_earned' => $projectEarned,
            'maintenance_earned' => $maintenanceEarned,
            'total_earned' => $totalEarned,
            'total_paid_incl_advance' => $totalPaid,
            'payable_net' => $payableNet,
        ];
    }

    /**
     * Bulk breakdown for multiple reps using the same formula as breakdown().
     *
     * @return array<int, array<string, float>>
     */
    public function breakdownMany(array $repIds): array
    {
        $repIds = array_values(array_filter(array_unique(array_map('intval', $repIds))));
        if (empty($repIds)) {
            return [];
        }

        $projectEarnedByRep = DB::table('project_sales_representative as psr')
            ->join('projects as p', 'p.id', '=', 'psr.project_id')
            ->whereIn('psr.sales_representative_id', $repIds)
            ->where('psr.amount', '>', 0)
            ->whereNotIn('p.status', ['cancel', 'cancelled', 'void', 'voided'])
            ->whereNull('p.deleted_at')
            ->groupBy('psr.sales_representative_id')
            ->selectRaw('psr.sales_representative_id, SUM(psr.amount) as amount')
            ->pluck('amount', 'psr.sales_representative_id');

        $maintenanceEarnedByRep = DB::table('project_maintenance_sales_representative as pmsr')
            ->join('project_maintenances as pm', 'pm.id', '=', 'pmsr.project_maintenance_id')
            ->whereIn('pmsr.sales_representative_id', $repIds)
            ->where('pmsr.amount', '>', 0)
            ->whereNotIn('pm.status', ['cancel', 'cancelled', 'void', 'voided'])
            ->whereNull('pm.deleted_at')
            ->groupBy('pmsr.sales_representative_id')
            ->selectRaw('pmsr.sales_representative_id, SUM(pmsr.amount) as amount')
            ->pluck('amount', 'pmsr.sales_representative_id');

        $paidByRep = CommissionPayout::query()
            ->whereIn('sales_representative_id', $repIds)
            ->where('status', 'paid')
            ->groupBy('sales_representative_id')
            ->selectRaw('sales_representative_id, SUM(total_amount) as amount')
            ->pluck('amount', 'sales_representative_id');

        $rows = [];
        foreach ($repIds as $repId) {
            $projectEarned = $this->fromCents($this->toCents((float) ($projectEarnedByRep[$repId] ?? 0)));
            $maintenanceEarned = $this->fromCents($this->toCents((float) ($maintenanceEarnedByRep[$repId] ?? 0)));
            $totalEarned = $this->fromCents($this->toCents($projectEarned) + $this->toCents($maintenanceEarned));
            $totalPaid = $this->fromCents($this->toCents((float) ($paidByRep[$repId] ?? 0)));
            $payableNet = $this->fromCents($this->toCents($totalEarned) - $this->toCents($totalPaid));

            $rows[$repId] = [
                'project_earned' => $projectEarned,
                'maintenance_earned' => $maintenanceEarned,
                'total_earned' => $totalEarned,
                'total_paid_incl_advance' => $totalPaid,
                'payable_net' => $payableNet,
            ];
        }

        return $rows;
    }

    private function projectEarnedCents(int $repId): int
    {
        $sum = DB::table('project_sales_representative as psr')
            ->join('projects as p', 'p.id', '=', 'psr.project_id')
            ->where('psr.sales_representative_id', $repId)
            ->where('psr.amount', '>', 0)
            ->whereNotIn('p.status', ['cancel', 'cancelled', 'void', 'voided'])
            ->whereNull('p.deleted_at')
            ->sum('psr.amount');

        return $this->toCents($sum);
    }

    private function maintenanceEarnedCents(int $repId): int
    {
        $sum = DB::table('project_maintenance_sales_representative as pmsr')
            ->join('project_maintenances as pm', 'pm.id', '=', 'pmsr.project_maintenance_id')
            ->where('pmsr.sales_representative_id', $repId)
            ->where('pmsr.amount', '>', 0)
            ->whereNotIn('pm.status', ['cancel', 'cancelled', 'void', 'voided'])
            ->whereNull('pm.deleted_at')
            ->sum('pmsr.amount');

        return $this->toCents($sum);
    }

    private function toCents(float|int|string|null $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function fromCents(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
