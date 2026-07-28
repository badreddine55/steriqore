<?php

namespace App\Console\Commands;

use App\Domain\Inventory\Enums\AlertState;
use App\Domain\Inventory\Mail\DigestMail;
use App\Domain\Inventory\Models\Alert;
use App\Domain\Inventory\Models\AlertSettings;
use App\Domain\Inventory\Models\DigestSubscription;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Scheduled in routes/console.php. Runs tenant by tenant, same
 * makeCurrent()/forgetCurrent() pattern as DetectAlerts — every query here
 * already qualifies tenant_id explicitly, it doesn't rely on RLS session
 * scoping. Recipients are resolved live from whoever currently holds the
 * subscribed role (DigestSubscription's own docblock), never a stored
 * address list, so this stays correct as team membership (UI-26) changes.
 */
class SendDigests extends Command
{
    protected $signature = 'digests:send';

    protected $description = 'Send the alerts digest to every due, subscribed role in every tenant';

    public function handle(): int
    {
        Tenant::query()->each(function (Tenant $tenant) {
            $tenant->makeCurrent();

            $settings = AlertSettings::forTenant($tenant);

            if (! $settings->digests_enabled) {
                Tenant::forgetCurrent();

                return;
            }

            $openAlerts = Alert::where('tenant_id', $tenant->id)->where('state', AlertState::Open)->get();

            DigestSubscription::where('tenant_id', $tenant->id)
                ->where('enabled', true)
                ->each(function (DigestSubscription $subscription) use ($tenant, $openAlerts) {
                    if (! $this->isDue($subscription) || $openAlerts->isEmpty()) {
                        return;
                    }

                    $recipients = $this->usersWithRole($tenant, $subscription->role);

                    foreach ($recipients as $recipient) {
                        Mail::to($recipient->email)->queue(new DigestMail($recipient, $tenant, $openAlerts));
                    }

                    $subscription->update(['last_sent_at' => now()]);
                    $this->line("{$tenant->name}: sent {$subscription->role} digest to {$recipients->count()} recipient(s)");
                });

            Tenant::forgetCurrent();
        });

        return self::SUCCESS;
    }

    private function isDue(DigestSubscription $subscription): bool
    {
        if ($subscription->last_sent_at === null) {
            return true;
        }

        return match ($subscription->frequency) {
            'weekly' => $subscription->last_sent_at->lt(now()->subWeek()),
            default => $subscription->last_sent_at->lt(now()->subDay()),
        };
    }

    /**
     * @return Collection<int, User>
     */
    private function usersWithRole(Tenant $tenant, string $role)
    {
        $userIds = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.tenant_id', $tenant->id)
            ->where('roles.name', $role)
            ->pluck('model_has_roles.model_uuid');

        return User::whereIn('id', $userIds)->get();
    }
}
