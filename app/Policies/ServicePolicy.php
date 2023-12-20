<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ServicePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): Response
    {
        if ($user->role === 'administrator') {
            return Response::allow();
        }

        return Response::deny('Esta tela exige permissão de administrador.');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Service $service): Response
    {
        if ($user->role === 'administrator') {
            return Response::allow();
        }

        return Response::deny('Esta tela exige permissão de administrador.');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): Response
    {
        if ($user->role === 'administrator') {
            return Response::allow();
        }

        return Response::deny('Esta tela exige permissão de administrador.');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Service $service): Response
    {
        if ($user->role === 'administrator') {
            return Response::allow();
        }

        return Response::deny('Esta tela exige permissão de administrador.');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Service $service): Response
    {
        if ($user->role === 'administrator') {
            return Response::allow();
        }

        return Response::deny('Esta tela exige permissão de administrador.');
    }
}
