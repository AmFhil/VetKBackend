<?php

namespace App\Traits;

trait HasAuthorAttribute
{
    public function getAuthorAttribute()
    {
        if ($this->user instanceof \App\Models\Admin) {
            return [
                'id'    => $this->user->id,
                'name'  => $this->user->first_name . ' ' . $this->user->last_name,
                'image' => $this->user->profile_picture_url,
                'type'  => 'admin',
            ];
        }

        if ($this->user instanceof \App\Models\User) {
            return [
                'id'          => $this->user->id,
                'name'        => $this->user->first_name . ' ' . $this->user->last_name,
                'image'       => optional($this->user->profile)->profile_image_url,
                'type'        => 'user',
                'roles'       => $this->user->roles->pluck('name'),
                'active_role' => $this->user->activeRole?->name,
            ];
        }

        return null;
    }
}
