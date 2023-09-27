<?php

namespace App\Policies;

use Illuminate\Auth\Access\Response;
use App\Models\OrderStatusLog;
use App\Models\User;

class OrderStatusLogPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): Response
    {
        if ( $user->role === 'administrator' || $user->role === 'provider') {
            return Response::allow();
        }
        return Response::deny('Esta tela exige permissão de administrador ou técnico.');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, OrderStatusLog $orderStatusLog): Response
    {
        if ( $user->role === 'administrator' || $user->role === 'provider') {
            return Response::allow();
        }
        return Response::deny('Esta tela exige permissão de administrador ou técnico.');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): Response
    {
        return Response::deny('Esta função está desabilitada.');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, OrderStatusLog $orderStatusLog): Response
    {
        return Response::deny('Esta função está desabilitada.');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, OrderStatusLog $orderStatusLog): Response
    {
        return Response::deny('Esta função está desabilitada.');
    }

}
