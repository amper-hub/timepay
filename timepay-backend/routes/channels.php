<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('employee.{employeeId}', function ($user, int $employeeId): bool {
    return (int) $user->id === $employeeId;
});
