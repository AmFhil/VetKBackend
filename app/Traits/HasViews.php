<?php

namespace App\Traits;

use App\Models\View;

trait HasViews
{
    public function views()
    {
        return $this->morphMany(View::class, 'viewable');
    }

    /**
     * Record a view (logged-in users OR guests).
     */
    public function recordView(): void
    {
        $user = auth()->user();

        if ($user) {
            // logged-in users
            $alreadyViewed = $this->views()
                ->where('user_id', $user->id)
                ->exists();

            if (!$alreadyViewed) {
                $this->views()->create(['user_id' => $user->id]);
                $this->increment('view');
            }
        } else {
            // guest users
            $sessionId = session()->getId();
            $ip = request()->ip();
            $ua = request()->userAgent();

            $alreadyViewed = $this->views()
                ->where('session_id', $sessionId)
                ->exists();

            if (!$alreadyViewed) {
                $this->views()->create([
                    'session_id' => $sessionId,
                    'ip_address' => $ip,
                    'user_agent' => $ua,
                ]);
                $this->increment('view');
            }
        }
    }

    /**
     * Get the total unique views count.
     */
    public function uniqueViewsCount(): int
    {
        return $this->views()
            ->selectRaw('COUNT(DISTINCT COALESCE(user_id, session_id)) as total')
            ->value('total');
    }
}
