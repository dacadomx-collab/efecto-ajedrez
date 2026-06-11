<?php

declare(strict_types=1);

// =============================================================================
// PEGASO EXPEDICIONES — ExpeditionRepository.php
// Patrón: Repository + DTO | Motor: PDO + Prepared Statements
// Mandamiento #2: Seguridad Nivel Militar — Cero SQL Injection
// Mandamiento #7: snake_case para Backend/DB
// =============================================================================

// ---------------------------------------------------------------------------
// DTO: ExpeditionDTO — Contrato de datos tipado (PHP 8+ readonly properties)
// ---------------------------------------------------------------------------

final class ExpeditionDTO
{
    public function __construct(
        public readonly ?int    $id,
        public readonly string  $name,
        public readonly string  $slug,
        public readonly string  $description,
        public readonly string  $difficulty_level,
        public readonly int     $duration_days,
        public readonly float   $base_price,
        public readonly int     $max_capacity,
        public readonly string  $status,
        /** @var array<string, mixed> */
        public readonly array   $custom_fields,
        public readonly ?string $created_at = null,
        public readonly ?string $updated_at = null,
    ) {}

    /**
     * Valida las reglas de negocio antes de persistir.
     * Lanza InvalidArgumentException con mensaje descriptivo.
     */
    public function validate(): void
    {
        $allowed_difficulties = ['easy', 'moderate', 'hard', 'extreme'];
        $allowed_statuses     = ['draft', 'active', 'inactive'];

        if (empty(trim($this->name))) {
            throw new \InvalidArgumentException('El nombre de la expedición no puede estar vacío.');
        }
        if (empty(trim($this->slug))) {
            throw new \InvalidArgumentException('El slug no puede estar vacío.');
        }
        if (!in_array($this->difficulty_level, $allowed_difficulties, true)) {
            throw new \InvalidArgumentException("difficulty_level inválido: '{$this->difficulty_level}'.");
        }
        if (!in_array($this->status, $allowed_statuses, true)) {
            throw new \InvalidArgumentException("status inválido: '{$this->status}'.");
        }
        if ($this->duration_days < 1) {
            throw new \InvalidArgumentException('duration_days debe ser al menos 1.');
        }
        if ($this->base_price < 0) {
            throw new \InvalidArgumentException('base_price no puede ser negativo.');
        }
        if ($this->max_capacity < 1) {
            throw new \InvalidArgumentException('max_capacity debe ser al menos 1.');
        }
    }
}


// ---------------------------------------------------------------------------
// REPOSITORY: ExpeditionRepository
// ---------------------------------------------------------------------------

final class ExpeditionRepository
{
    private \PDO   $pdo;
    private string $log_path;

    public function __construct(\PDO $pdo, string $log_path = __DIR__ . '/../../logs/error.log')
    {
        $this->pdo      = $pdo;
        $this->log_path = $log_path;
    }

    // -------------------------------------------------------------------------
    // INSERT: Persiste una nueva expedición y retorna su ID generado.
    // -------------------------------------------------------------------------

    /**
     * @throws \RuntimeException si falla la inserción en BD
     * @throws \InvalidArgumentException si el DTO no pasa validación
     */
    public function insert(ExpeditionDTO $dto): int
    {
        $dto->validate();

        // Serializar custom_fields a JSON de forma segura
        $custom_fields_json = $this->encodeCustomFields($dto->custom_fields);

        $sql = "
            INSERT INTO `expeditions`
                (`name`, `slug`, `description`, `difficulty_level`,
                 `duration_days`, `base_price`, `max_capacity`, `status`, `custom_fields`)
            VALUES
                (:name, :slug, :description, :difficulty_level,
                 :duration_days, :base_price, :max_capacity, :status, :custom_fields)
        ";

        try {
            $stmt = $this->pdo->prepare($sql);

            $stmt->execute([
                ':name'             => trim($dto->name),
                ':slug'             => trim($dto->slug),
                ':description'      => trim($dto->description),
                ':difficulty_level' => $dto->difficulty_level,
                ':duration_days'    => $dto->duration_days,
                ':base_price'       => $dto->base_price,
                ':max_capacity'     => $dto->max_capacity,
                ':status'           => $dto->status,
                ':custom_fields'    => $custom_fields_json,
            ]);

            return (int) $this->pdo->lastInsertId();

        } catch (\PDOException $e) {
            $this->logError('ExpeditionRepository::insert', $e);
            throw new \RuntimeException('No se pudo crear la expedición. Consulte el log de errores.');
        }
    }

    // -------------------------------------------------------------------------
    // FIND BY ID: Recupera una expedición y decodifica su columna JSON.
    // -------------------------------------------------------------------------

    /**
     * @return ExpeditionDTO|null  null si no existe el registro
     * @throws \RuntimeException   si falla la consulta en BD
     */
    public function findById(int $id): ?ExpeditionDTO
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('El ID debe ser un entero positivo.');
        }

        $sql = "
            SELECT `id`, `name`, `slug`, `description`, `difficulty_level`,
                   `duration_days`, `base_price`, `max_capacity`, `status`,
                   `custom_fields`, `created_at`, `updated_at`
            FROM   `expeditions`
            WHERE  `id` = :id
            LIMIT  1
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':id' => $id]);

            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row === false) {
                return null;
            }

            return $this->hydrateDTO($row);

        } catch (\PDOException $e) {
            $this->logError('ExpeditionRepository::findById', $e);
            throw new \RuntimeException('Error al recuperar la expedición. Consulte el log de errores.');
        }
    }

    // -------------------------------------------------------------------------
    // FIND ALL ACTIVE: Listado público de expediciones activas (con paginación simple)
    // -------------------------------------------------------------------------

    /**
     * @return ExpeditionDTO[]
     * @throws \RuntimeException
     */
    public function findAllActive(int $limit = 20, int $offset = 0): array
    {
        $limit  = max(1, min($limit, 100));  // sanitizar: entre 1 y 100
        $offset = max(0, $offset);

        $sql = "
            SELECT `id`, `name`, `slug`, `description`, `difficulty_level`,
                   `duration_days`, `base_price`, `max_capacity`, `status`,
                   `custom_fields`, `created_at`, `updated_at`
            FROM   `expeditions`
            WHERE  `status` = 'active'
            ORDER  BY `name` ASC
            LIMIT  :limit OFFSET :offset
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            // LIMIT/OFFSET requieren bindValue con tipo explícito (no funcionan via array)
            $stmt->bindValue(':limit',  $limit,  \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return array_map([$this, 'hydrateDTO'], $rows);

        } catch (\PDOException $e) {
            $this->logError('ExpeditionRepository::findAllActive', $e);
            throw new \RuntimeException('Error al listar expediciones. Consulte el log de errores.');
        }
    }

    // =========================================================================
    // MÉTODOS PRIVADOS / HELPERS
    // =========================================================================

    /**
     * Convierte una fila de BD en un ExpeditionDTO, decodificando JSON de forma segura.
     *
     * @param array<string, mixed> $row
     */
    private function hydrateDTO(array $row): ExpeditionDTO
    {
        // Decodificación segura: si el JSON es inválido o NULL, retorna array vacío
        $custom_fields = $this->decodeCustomFields((string) ($row['custom_fields'] ?? '{}'));

        return new ExpeditionDTO(
            id:               (int)    $row['id'],
            name:             (string) $row['name'],
            slug:             (string) $row['slug'],
            description:      (string) ($row['description'] ?? ''),
            difficulty_level: (string) $row['difficulty_level'],
            duration_days:    (int)    $row['duration_days'],
            base_price:       (float)  $row['base_price'],
            max_capacity:     (int)    $row['max_capacity'],
            status:           (string) $row['status'],
            custom_fields:    $custom_fields,
            created_at:       (string) ($row['created_at'] ?? ''),
            updated_at:       (string) ($row['updated_at'] ?? ''),
        );
    }

    /**
     * Codifica el array custom_fields a JSON.
     * Lanza \JsonException si los datos no son serializables.
     */
    private function encodeCustomFields(array $fields): string
    {
        if (empty($fields)) {
            return '{}';
        }

        try {
            $json = json_encode(
                $fields,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException(
                "custom_fields contiene datos no serializables: {$e->getMessage()}"
            );
        }

        return $json;
    }

    /**
     * Decodifica la columna JSON a un array PHP de forma segura.
     * Retorna [] si el valor es null, vacío o JSON malformado.
     *
     * @return array<string, mixed>
     */
    private function decodeCustomFields(string $json_string): array
    {
        if (empty($json_string) || $json_string === 'null') {
            return [];
        }

        try {
            $decoded = json_decode($json_string, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\JsonException $e) {
            $this->logError('ExpeditionRepository::decodeCustomFields (JSON malformado)', $e);
            return [];
        }
    }

    /**
     * Registra un error en el log del servidor con contexto suficiente para debug.
     */
    private function logError(string $context, \Throwable $e): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $message   = "[{$timestamp}] [{$context}] {$e->getMessage()} | File: {$e->getFile()}:{$e->getLine()}" . PHP_EOL;

        // error_log() escribe en el destino configurado; el segundo parámetro 3 es "append to file"
        error_log($message, 3, $this->log_path);
    }
}
