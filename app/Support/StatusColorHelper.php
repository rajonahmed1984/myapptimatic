<?php

namespace App\Support;

class StatusColorHelper
{
    /**
     * Get standardized color scheme for status values
     * Colors based on activity level:
     * - Green (emerald): Active, Paid, Complete, Synced, Success
     * - Orange (orange): Ongoing, In-progress, Running
     * - Amber/Yellow (amber): Warning, Pending, Unpaid, On hold, Stale
     * - Blue (blue): Partially paid, Customer reply
     * - Rose/Red (rose): Danger, Overdue, Blocked, Suspended, Failed, Cancelled
     * - Slate (slate): Inactive, Closed, Neutral, Never synced
     *
     * Keep in sync with resources/js/utils/statusColors.js.
     */
    
    public static function getStatusColors(?string $status): array
    {
        $status = strtolower(trim((string) $status));
        if ($status === '') {
            $status = 'inactive';
        }

        $statuses = [
            // Invoice statuses
            'paid' => [
                'bg' => 'bg-emerald-100',
                'text' => 'text-emerald-700',
                'color' => 'emerald',
                'dot' => 'bg-emerald-500',
                'icon' => '✓',
            ],
            'unpaid' => [
                'bg' => 'bg-amber-100',
                'text' => 'text-amber-700',
                'color' => 'amber',
                'dot' => 'bg-amber-500',
                'icon' => '◆',
            ],
            'overdue' => [
                'bg' => 'bg-rose-100',
                'text' => 'text-rose-700',
                'color' => 'rose',
                'dot' => 'bg-rose-500',
                'icon' => '!',
            ],
            'cancelled' => [
                'bg' => 'bg-rose-100',
                'text' => 'text-rose-700',
                'color' => 'rose',
                'dot' => 'bg-rose-500',
                'icon' => '×',
            ],

            // Project and task statuses
            'ongoing' => [
                'bg' => 'bg-orange-100',
                'text' => 'text-orange-700',
                'color' => 'orange',
                'dot' => 'bg-orange-500',
                'icon' => '⟳',
            ],
            'in_progress' => [
                'bg' => 'bg-orange-100',
                'text' => 'text-orange-700',
                'color' => 'orange',
                'dot' => 'bg-orange-500',
                'icon' => '⟳',
            ],
            'complete' => [
                'bg' => 'bg-emerald-100',
                'text' => 'text-emerald-700',
                'color' => 'emerald',
                'dot' => 'bg-emerald-500',
                'icon' => '✓',
            ],
            'completed' => [
                'bg' => 'bg-emerald-100',
                'text' => 'text-emerald-700',
                'color' => 'emerald',
                'dot' => 'bg-emerald-500',
                'icon' => '✓',
            ],

            // Subscription statuses
            'active' => [
                'bg' => 'bg-emerald-100',
                'text' => 'text-emerald-700',
                'color' => 'emerald',
                'dot' => 'bg-emerald-500',
                'icon' => '●',
            ],
            'suspended' => [
                'bg' => 'bg-rose-100',
                'text' => 'text-rose-700',
                'color' => 'rose',
                'dot' => 'bg-rose-500',
                'icon' => '⊙',
            ],
            'terminated' => [
                'bg' => 'bg-slate-100',
                'text' => 'text-slate-700',
                'color' => 'slate',
                'dot' => 'bg-slate-400',
                'icon' => '×',
            ],
            
            // License statuses
            'active_license' => [
                'bg' => 'bg-emerald-100',
                'text' => 'text-emerald-700',
                'color' => 'emerald',
                'dot' => 'bg-emerald-500',
                'icon' => '●',
            ],
            'suspended_license' => [
                'bg' => 'bg-rose-100',
                'text' => 'text-rose-700',
                'color' => 'rose',
                'dot' => 'bg-rose-500',
                'icon' => '⊙',
            ],
            'revoked' => [
                'bg' => 'bg-slate-100',
                'text' => 'text-slate-700',
                'color' => 'slate',
                'dot' => 'bg-slate-400',
                'icon' => '×',
            ],
            
            // Sync statuses
            'synced' => [
                'bg' => 'bg-emerald-100',
                'text' => 'text-emerald-700',
                'color' => 'emerald',
                'dot' => 'bg-emerald-500',
                'icon' => '✓',
            ],
            'stale' => [
                'bg' => 'bg-amber-100',
                'text' => 'text-amber-700',
                'color' => 'amber',
                'dot' => 'bg-amber-500',
                'icon' => '◆',
            ],
            'never' => [
                'bg' => 'bg-slate-100',
                'text' => 'text-slate-600',
                'color' => 'slate',
                'dot' => 'bg-slate-400',
                'icon' => '○',
            ],
            
            // Access/Blocking statuses
            'blocked' => [
                'bg' => 'bg-rose-100',
                'text' => 'text-rose-700',
                'color' => 'rose',
                'dot' => 'bg-rose-500',
                'icon' => '🔒',
            ],
            'access_blocked' => [
                'bg' => 'bg-rose-100',
                'text' => 'text-rose-700',
                'color' => 'rose',
                'dot' => 'bg-rose-500',
                'icon' => '🔒',
            ],
            'unblocked' => [
                'bg' => 'bg-emerald-100',
                'text' => 'text-emerald-700',
                'color' => 'emerald',
                'dot' => 'bg-emerald-500',
                'icon' => '🔓',
            ],
            
            // Automation/System statuses
            'success' => [
                'bg' => 'bg-emerald-100',
                'text' => 'text-emerald-700',
                'color' => 'emerald',
                'dot' => 'bg-emerald-500',
                'icon' => '✓',
            ],
            'running' => [
                'bg' => 'bg-orange-100',
                'text' => 'text-orange-700',
                'color' => 'orange',
                'dot' => 'bg-orange-500',
                'icon' => '⟳',
            ],
            'failed' => [
                'bg' => 'bg-rose-100',
                'text' => 'text-rose-700',
                'color' => 'rose',
                'dot' => 'bg-rose-500',
                'icon' => '✕',
            ],
            'pending' => [
                'bg' => 'bg-amber-100',
                'text' => 'text-amber-700',
                'color' => 'amber',
                'dot' => 'bg-amber-500',
                'icon' => '◆',
            ],
            'partial' => [
                'bg' => 'bg-blue-100',
                'text' => 'text-blue-700',
                'color' => 'blue',
                'dot' => 'bg-blue-500',
                'icon' => '*',
            ],
            'partially_paid' => [
                'bg' => 'bg-blue-100',
                'text' => 'text-blue-700',
                'color' => 'blue',
                'dot' => 'bg-blue-500',
                'icon' => '*',
            ],
            'accepted' => [
                'bg' => 'bg-emerald-100',
                'text' => 'text-emerald-700',
                'color' => 'emerald',
                'dot' => 'bg-emerald-500',
                'icon' => '✓',
            ],
            'approved' => [
                'bg' => 'bg-emerald-100',
                'text' => 'text-emerald-700',
                'color' => 'emerald',
                'dot' => 'bg-emerald-500',
                'icon' => 'check',
            ],

            // Ticket statuses
            'open' => [
                'bg' => 'bg-amber-100',
                'text' => 'text-amber-700',
                'color' => 'amber',
                'dot' => 'bg-amber-500',
                'icon' => '◆',
            ],
            'answered' => [
                'bg' => 'bg-emerald-100',
                'text' => 'text-emerald-700',
                'color' => 'emerald',
                'dot' => 'bg-emerald-500',
                'icon' => '✓',
            ],
            'customer_reply' => [
                'bg' => 'bg-blue-100',
                'text' => 'text-blue-700',
                'color' => 'blue',
                'dot' => 'bg-blue-500',
                'icon' => '●',
            ],
            'closed' => [
                'bg' => 'bg-slate-100',
                'text' => 'text-slate-700',
                'color' => 'slate',
                'dot' => 'bg-slate-400',
                'icon' => '×',
            ],
            
            // Generic statuses
            'inactive' => [
                'bg' => 'bg-slate-100',
                'text' => 'text-slate-600',
                'color' => 'slate',
                'dot' => 'bg-slate-400',
                'icon' => '○',
            ],
        ];

        return $statuses[$status] ?? [
            'bg' => 'bg-slate-100',
            'text' => 'text-slate-600',
            'color' => 'slate',
            'dot' => 'bg-slate-400',
            'icon' => '?',
        ];
    }

    /**
     * Get just the background class
     */
    public static function getBgClass(?string $status): string
    {
        return self::getStatusColors($status)['bg'];
    }

    /**
     * Get just the text color class
     */
    public static function getTextClass(?string $status): string
    {
        return self::getStatusColors($status)['text'];
    }

    /**
     * Get just the dot/indicator color class
     */
    public static function getDotClass(?string $status): string
    {
        return self::getStatusColors($status)['dot'];
    }

    /**
     * Get badge classes (combined bg + text)
     */
    public static function getBadgeClasses(?string $status): string
    {
        $colors = self::getStatusColors($status);
        return "{$colors['bg']} {$colors['text']}";
    }

    /**
     * Format status with badge
     */
    public static function badge(?string $status, ?string $label = null): string
    {
        $colors = self::getStatusColors($status);
        $displayLabel = $label ?? ucfirst(str_replace('_', ' ', (string) $status));
        
        return <<<HTML
        <div class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {$colors['bg']} {$colors['text']}">
            {$displayLabel}
        </div>
        HTML;
    }

    /**
     * Get color map for all active statuses in a category
     */
    public static function getColorMap(string $category = null): array
    {
        $allStatuses = [
            'invoice' => ['paid', 'unpaid', 'overdue', 'cancelled'],
            'subscription' => ['active', 'suspended', 'terminated'],
            'license' => ['active_license', 'suspended_license', 'revoked'],
            'sync' => ['synced', 'stale', 'never'],
            'blocking' => ['blocked', 'unblocked'],
            'automation' => ['success', 'running', 'failed', 'pending'],
            'ticket' => ['open', 'closed'],
        ];

        if ($category && isset($allStatuses[$category])) {
            $result = [];
            foreach ($allStatuses[$category] as $status) {
                $result[$status] = self::getStatusColors($status);
            }
            return $result;
        }

        return $allStatuses;
    }
}
