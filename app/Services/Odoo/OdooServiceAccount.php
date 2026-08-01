<?php

namespace App\Services\Odoo;

class OdooServiceAccount
{
    private ?int $uid = null;

    public function __construct(
        private readonly OdooClient $client
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured() && filled($this->username()) && filled($this->secret());
    }

    public function executeKw(string $model, string $method, array $args = [], array $kwargs = []): mixed
    {
        [$uid, $secret] = $this->session();

        return $this->client->executeKw($uid, $secret, $model, $method, $args, $kwargs);
    }

    /**
     * @return array{0:int,1:string}
     */
    private function session(): array
    {
        if ($this->uid !== null) {
            return [$this->uid, $this->secret()];
        }

        if (! $this->isConfigured()) {
            throw new OdooException(
                'Odoo employee data is unavailable until the Odoo service account is configured in the environment.'
            );
        }

        $uid = $this->client->authenticate($this->username(), $this->secret());

        if (! $uid) {
            throw new OdooException('The configured Odoo service account credentials are invalid.');
        }

        $this->uid = $uid;

        return [$this->uid, $this->secret()];
    }

    private function username(): string
    {
        return (string) config('services.odoo.username');
    }

    private function secret(): string
    {
        return (string) (config('services.odoo.api_key') ?: config('services.odoo.password'));
    }
}
