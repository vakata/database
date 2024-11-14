<?php
namespace vakata\database\schema;

/**
 * A column definition
 */
class TableColumn
{
    protected string $name;
    protected string $type;
    protected string $btype = 'text';
    protected array $values = [];
    protected mixed $default = null;
    protected ?string $comment = null;
    protected bool $nullable = false;
    protected ?int $length = null;

    public function __construct(string $name)
    {
        $this->setName($name);
    }

    public static function fromArray(string $name, array $data = []): self
    {
        $instance = new self($name);
        if (isset($data['Type'])) {
            $instance->setType($data['Type']);
        }
        if (isset($data['type'])) {
            $instance->setType($data['type']);
        }
        if (isset($data['Comment'])) {
            $instance->setComment($data['Comment']);
        }
        if (isset($data['comment'])) {
            $instance->setComment($data['comment']);
        }
        if (isset($data['Null']) && $data['Null'] === 'YES') {
            $instance->setNullable(true);
        }
        if (isset($data['nullable']) && is_bool($data['nullable'])) {
            $instance->setNullable($data['nullable']);
        }
        if (isset($data['notnull'])) {
            $instance->setNullable(!((int)$data['notnull']));
        }
        if (isset($data['Default'])) {
            $instance->setDefault($data['Default']);
        }
        if (isset($data['default'])) {
            $instance->setDefault($data['default']);
        }
        if (isset($data['dflt_value'])) {
            $instance->setDefault($data['dflt_value']);
        }
        if (isset($data['length']) && $data['length'] !== 0) {
            $instance->setLength($data['length']);
        }
        if ($instance->getBasicType() === 'enum' && strpos($instance->getType(), 'enum(') === 0) {
            $temp = array_map(function ($v) {
                return str_replace("''", "'", $v);
            }, explode("','", substr($instance->getType(), 6, -2)));
            $instance->setValues($temp);
        }
        if (isset($data['values']) && is_array($data['values'])) {
            $instance->setValues($data['values']);
        }
        if (isset($data['DATA_TYPE'])) {
            $instance->setType($data['DATA_TYPE']);
        }
        if (isset($data['NULLABLE']) && $data['NULLABLE'] !== 'N') {
            $instance->setNullable(true);
        }
        if (isset($data['DATA_DEFAULT'])) {
            $instance->setDefault($data['DATA_DEFAULT']);
        }
        if (isset($data['data_type'])) {
            $instance->setType($data['data_type']);
        }
        if (isset($data['column_default'])) {
            $instance->setDefault(trim("'", explode('::', $data['column_default'])[0]));
        }
        if (isset($data['is_nullable']) && $data['is_nullable'] == 'YES') {
            $instance->setNullable(true);
            if (!isset($data['column_default'])) {
                $instance->setDefault(null);
            }
        }
        if ($instance->getBasicType() === 'text' && isset($data['CHAR_LENGTH']) && (int)$data['CHAR_LENGTH']) {
            $instance->setLength($data['CHAR_LENGTH']);
        }
        return $instance;
    }

    public function getName(): string
    {
        return $this->name;
    }
    public function getType(): string
    {
        return $this->type;
    }
    public function getValues(): array
    {
        return $this->values;
    }
    public function getDefault(): mixed
    {
        return $this->default;
    }
    public function isNullable(): bool
    {
        return $this->nullable;
    }
    public function getComment(): ?string
    {
        return $this->comment;
    }
    public function getBasicType(): string
    {
        return $this->btype;
    }
    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }
    public function setType(string $type): static
    {
        $this->type = $type;
        $type = strtolower($type);
        if (strpos($type, 'enum') !== false || strpos($type, 'set') !== false) {
            $this->btype = 'enum';
        } elseif (strpos($type, 'json') !== false) {
            $this->btype = 'json';
        } elseif (strpos($type, 'text') !== false || strpos($type, 'char') !== false) {
            $this->btype = 'text';
        } elseif (strpos($type, 'int') !== false ||
            strpos($type, 'bit') !== false ||
            strpos($type, 'number') !== false
        ) {
            $this->btype = 'int';
        } elseif (strpos($type, 'float') !== false ||
            strpos($type, 'double') !== false ||
            strpos($type, 'decimal') !== false ||
            strpos($type, 'numeric') !== false
        ) {
            $this->btype = 'float';
        } elseif (strpos($type, 'datetime') !== false || strpos($type, 'timestamp') !== false) {
            $this->btype = 'datetime';
        } elseif (strpos($type, 'date') !== false) {
            $this->btype = 'date';
        } elseif (strpos($type, 'time') !== false) {
            $this->btype = 'time';
        } elseif (strpos($type, 'lob') !== false ||
            strpos($type, 'binary') !== false ||
            strpos($type, 'byte') !== false
        ) {
            $this->btype = 'blob';
        }
        return $this;
    }
    public function setValues(array $values): static
    {
        $this->values = $values;
        return $this;
    }
    public function setDefault(mixed $default = null): static
    {
        $this->default = $default;
        return $this;
    }
    public function setNullable(bool $nullable): static
    {
        $this->nullable = $nullable;
        return $this;
    }
    public function setComment(string $comment): static
    {
        $this->comment = $comment;
        return $this;
    }
    public function hasLength(): bool
    {
        return $this->length !== null && $this->length !== 0;
    }
    public function getLength(): int
    {
        return (int)$this->length;
    }
    public function setLength(int $length): static
    {
        $this->length = $length;
        return $this;
    }
}
