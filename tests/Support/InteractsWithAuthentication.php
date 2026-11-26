<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\Passport;

trait InteractsWithAuthentication
{
    /**
     * Set the currently logged in user for the API.
     *
     * @param Authenticatable|Model|User|mixed $user
     * @return $this
     */
    protected function actingAsForApi(Authenticatable|Model $user)
    {
        Passport::actingAs($user);

        return $this;
    }
}
