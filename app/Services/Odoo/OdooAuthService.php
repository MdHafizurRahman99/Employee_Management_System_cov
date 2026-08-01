<?php

namespace App\Services\Odoo;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OdooAuthService
{
    private const MANAGER_GROUPS = [
        ['name' => 'Officer: Manage all employees', 'privilege' => 'Employees'],
        ['name' => 'Administrator', 'privilege' => 'Employees'],
        ['name' => 'Time Off Responsible', 'privilege' => 'Time Off'],
        ['name' => 'Officer: Manage all requests', 'privilege' => 'Time Off'],
        ['name' => 'Administrator', 'privilege' => 'Time Off'],
        ['name' => 'Officer: Manage attendances', 'privilege' => 'Attendances'],
        ['name' => 'Officer: Manage all attendances', 'privilege' => 'Attendances'],
        ['name' => 'Administrator', 'privilege' => 'Attendances'],
    ];

    private ?array $employeeFields = null;

    public function __construct(
        private readonly OdooServiceAccount $serviceAccount
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->serviceAccount->isConfigured();
    }

    public function authenticate(string $email, string $pinCode): ?array
    {
        $email = Str::lower(trim($email));
        $pinCode = trim($pinCode);

        if ($email === '' || $pinCode === '') {
            return null;
        }

        $employee = $this->findEmployeeByEmailAndPin($email, $pinCode);

        if (! $employee) {
            return null;
        }

        $user = $this->extractManyToOne($employee['user_id'] ?? null);
        $workEmail = trim((string) ($employee['work_email'] ?? ''));
        $privateEmail = trim((string) ($employee['private_email'] ?? ''));

        $profile = [
            'odoo_user_id' => $user['id'],
            'odoo_employee_id' => isset($employee['id']) ? (int) $employee['id'] : null,
            'odoo_resource_id' => $this->manyToOneId($employee['resource_id'] ?? null),
            'name' => (string) ($employee['name'] ?? $user['name'] ?? $this->guessDisplayName($email)),
            'email' => Str::lower($workEmail !== '' ? $workEmail : ($privateEmail !== '' ? $privateEmail : $email)),
            'is_manager' => false,
            'role' => 'user',
        ];

        $profile['is_manager'] = $user['id'] ? $this->detectManagerAccess($user['id']) : false;
        $profile['role'] = $profile['is_manager'] ? 'manager' : 'user';

        return $profile;
    }

    private function detectManagerAccess(int $odooUserId): bool
    {
        try {
            if ($this->hasManagerGroups($odooUserId)) {
                return true;
            }
        } catch (OdooException $exception) {
            Log::warning('Unable to evaluate Odoo manager groups during login.', [
                'odoo_user_id' => $odooUserId,
                'message' => $exception->getMessage(),
            ]);
        }

        try {
            if ($this->managesOtherEmployees($odooUserId)) {
                return true;
            }
        } catch (OdooException $exception) {
            Log::warning('Unable to evaluate Odoo manager relationships during login.', [
                'odoo_user_id' => $odooUserId,
                'message' => $exception->getMessage(),
            ]);
        }

        return false;
    }

    private function hasManagerGroups(int $odooUserId): bool
    {
        $user = $this->firstRecord(
            $this->serviceAccount->executeKw(
                'res.users',
                'search_read',
                [[['id', '=', $odooUserId]]],
                [
                    'fields' => ['group_ids'],
                    'limit' => 1,
                ]
            )
        );

        $groupIds = array_values(array_filter(
            is_array($user['group_ids'] ?? null) ? $user['group_ids'] : [],
            fn (mixed $groupId) => is_numeric($groupId)
        ));

        if ($groupIds === []) {
            return false;
        }

        $groups = $this->serviceAccount->executeKw(
            'res.groups',
            'search_read',
            [[['id', 'in', $groupIds]]],
            [
                'fields' => ['name', 'privilege_id'],
            ]
        );

        if (! is_array($groups)) {
            return false;
        }

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $privilege = $this->extractManyToOne($group['privilege_id'] ?? null)['name'];
            $name = isset($group['name']) ? (string) $group['name'] : null;

            foreach (self::MANAGER_GROUPS as $managerGroup) {
                if ($name === $managerGroup['name'] && $privilege === $managerGroup['privilege']) {
                    return true;
                }
            }
        }

        return false;
    }

    private function managesOtherEmployees(int $odooUserId): bool
    {
        foreach ([
            [['parent_id.user_id', '=', $odooUserId]],
            [['leave_manager_id', '=', $odooUserId]],
            [['attendance_manager_id', '=', $odooUserId]],
        ] as $domain) {
            try {
                $count = $this->serviceAccount->executeKw(
                    'hr.employee',
                    'search_count',
                    [$domain]
                );
            } catch (OdooException $exception) {
                continue;
            }

            if (is_numeric($count) && (int) $count > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findEmployeeByEmailAndPin(string $email, string $pinCode): ?array
    {
        $fields = $this->employeeFields();
        $matchingEmployees = [];

        foreach (['work_email', 'private_email'] as $emailField) {
            if (! isset($fields[$emailField])) {
                continue;
            }

            foreach ($this->searchEmployeesByEmailField($emailField, $email) as $employee) {
                if (! $this->employeeMatchesPin($employee, $pinCode) || ! $this->employeeMatchesEmail($employee, $emailField, $email)) {
                    continue;
                }

                $matchingEmployees[(int) $employee['id']] = $employee;
            }

            if ($matchingEmployees !== []) {
                break;
            }
        }

        if ($matchingEmployees === []) {
            return null;
        }

        if (count($matchingEmployees) > 1) {
            throw new OdooException('Multiple Odoo employee records match this email. Please contact an administrator.');
        }

        return array_values($matchingEmployees)[0] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchEmployeesByEmailField(string $emailField, string $email): array
    {
        $domain = [
            [$emailField, 'ilike', $email],
        ];

        if (isset($this->employeeFields()['active'])) {
            $domain[] = ['active', '=', true];
        }

        $records = $this->serviceAccount->executeKw(
            'hr.employee',
            'search_read',
            [$domain],
            [
                'fields' => ['id', 'name', 'work_email', 'private_email', 'pin', 'user_id', 'resource_id'],
                'order' => 'id asc',
                'limit' => 25,
            ]
        );

        return is_array($records) ? array_values(array_filter($records, 'is_array')) : [];
    }

    private function employeeMatchesEmail(array $employee, string $emailField, string $email): bool
    {
        $value = trim((string) ($employee[$emailField] ?? ''));

        return $value !== '' && Str::lower($value) === $email;
    }

    private function employeeMatchesPin(array $employee, string $pinCode): bool
    {
        return trim((string) ($employee['pin'] ?? '')) === $pinCode;
    }

    private function employeeFields(): array
    {
        if ($this->employeeFields !== null) {
            return $this->employeeFields;
        }

        $fields = $this->serviceAccount->executeKw(
            'hr.employee',
            'fields_get',
            [],
            [
                'attributes' => ['string', 'type', 'relation'],
            ]
        );

        $this->employeeFields = is_array($fields) ? $fields : [];

        return $this->employeeFields;
    }

    private function firstRecord(mixed $records): ?array
    {
        if (! is_array($records) || ! isset($records[0]) || ! is_array($records[0])) {
            return null;
        }

        return $records[0];
    }

    private function manyToOneId(mixed $value): ?int
    {
        if (is_array($value) && isset($value[0]) && is_numeric($value[0])) {
            return (int) $value[0];
        }

        return null;
    }

    /**
     * @return array{id:int|null,name:string|null}
     */
    private function extractManyToOne(mixed $value): array
    {
        if (is_array($value)) {
            return [
                'id' => isset($value[0]) && is_numeric($value[0]) ? (int) $value[0] : null,
                'name' => isset($value[1]) ? (string) $value[1] : null,
            ];
        }

        return ['id' => null, 'name' => null];
    }

    private function guessDisplayName(string $login): string
    {
        return Str::of(Str::before($login, '@'))
            ->replace(['.', '_', '-'], ' ')
            ->title()
            ->value();
    }
}
