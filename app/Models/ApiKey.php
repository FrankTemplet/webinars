<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = ['name', 'client_id', 'key_hash', 'key_prefix', 'last_used_at', 'revoked_at'];

    protected $casts = [
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = ['key_hash'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Genera una llave nueva. Devuelve [ApiKey, string $plainKey];
     * el texto plano solo se puede ver en este momento.
     *
     * @return array{0: self, 1: string}
     */
    public static function generate(string $name, ?int $clientId = null): array
    {
        $plain = 'wbn_'.Str::random(48);

        $apiKey = static::create([
            'name' => $name,
            'client_id' => $clientId,
            'key_hash' => hash('sha256', $plain),
            'key_prefix' => substr($plain, 0, 12),
        ]);

        return [$apiKey, $plain];
    }

    public static function findByPlainKey(string $plain): ?self
    {
        return static::whereNull('revoked_at')
            ->where('key_hash', hash('sha256', $plain))
            ->first();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
