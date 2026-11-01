const TONE_CLASSES = {
    emerald: 'border-emerald-200 bg-emerald-100 text-emerald-700',
    orange: 'border-orange-200 bg-orange-100 text-orange-700',
    amber: 'border-amber-200 bg-amber-100 text-amber-700',
    sky: 'border-sky-200 bg-sky-100 text-sky-700',
    rose: 'border-rose-200 bg-rose-100 text-rose-700',
    slate: 'border-slate-200 bg-slate-100 text-slate-600',
};

// The system-wide meaning of each status colour. Keep in sync with App\Support\StatusColorHelper.
const STATUS_TONES = {
    emerald: [
        'active', 'paid', 'complete', 'completed', 'done', 'success', 'successful', 'approved', 'accepted', 'executed',
        'answered', 'synced', 'unblocked', 'provisioned', 'present', 'working', 'published', 'verified', 'delivered', 'resolved',
    ],
    orange: ['ongoing', 'in_progress', 'inprogress', 'running', 'processing', 'provisioning', 'sending'],
    amber: [
        'pending', 'unpaid', 'open', 'todo', 'draft', 'hold', 'on_hold', 'paused', 'trial', 'stale', 'waiting', 'idle',
        'leave', 'half_day', 'late', 'warning', 'not_invoiced',
    ],
    sky: ['partial', 'partially_paid', 'customer_reply', 'payable', 'earned', 'finalized', 'info', 'sent', 'scheduled', 'holiday'],
    rose: [
        'overdue', 'failed', 'blocked', 'access_blocked', 'rejected', 'declined', 'suspended', 'cancel', 'cancelled', 'canceled',
        'expired', 'reversed', 'absent', 'stopped', 'error', 'critical', 'fraud',
    ],
};

const toneByStatus = Object.entries(STATUS_TONES).reduce((map, [tone, statuses]) => {
    statuses.forEach((status) => {
        map[status] = tone;
    });

    return map;
}, {});

const normalizeStatusKey = (status) => String(status ?? '').trim().toLowerCase().replace(/[\s-]+/g, '_');

export const statusBadgeClass = (status) => TONE_CLASSES[toneByStatus[normalizeStatusKey(status)] || 'slate'];
