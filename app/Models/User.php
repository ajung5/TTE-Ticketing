<?php

namespace App\Models;

use App\Notifications\TtePasswordResetNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'initials'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable {
    use HasFactory, Notifiable, SoftDeletes;

    public function isAdmin(): bool {
        return $this->role === 'admin';
    }

    public function isVerifier(): bool {
        return $this->role === 'verifier';
    }

    public function sendPasswordResetNotification($token): void {
        $this->notify(new TtePasswordResetNotification($token));
    }

    protected function casts(): array {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed'
        ];
    }
}
