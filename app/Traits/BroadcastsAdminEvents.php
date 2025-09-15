<?php

namespace App\Traits;

use App\Models\AdminNotification;
use App\Models\AdminActivity;
use App\Events\AdminNotificationCreated;
use App\Events\AdminActivityCreated;

trait BroadcastsAdminEvents
{
    private function createAdminNotification($adminId, $title, $type, $content = null, $meta = [])
    {
        $notification = AdminNotification::create([
            'admin_id' => $adminId,
            'title'    => $title,
            'type'     => $type,
            'content'  => $content,
            'meta'     => $meta,
        ]);

        broadcast(new AdminNotificationCreated($notification))->toOthers();

        return $notification;
    }

    private function createAdminActivity($adminId, $action, $title = null, $detail = null, $meta = [])
    {
        $activity = AdminActivity::create([
            'admin_id' => $adminId,
            'action'   => $action,
            'title'    => $title,
            'detail'   => $detail,
            'meta'     => $meta,
        ]);

        broadcast(new AdminActivityCreated($activity))->toOthers();

        return $activity;
    }
}
