<?php declare(strict_types=1);

namespace AdventureTech\DataTransferObject\Reflection;

use AdventureTech\DataTransferObject\Attributes\DefaultValue;
use AdventureTech\DataTransferObject\Attributes\MapFrom;
use AdventureTech\DataTransferObject\Attributes\Optional;
use AdventureTech\DataTransferObject\Exceptions\MissingPropertyTypeException;
use AdventureTech\DataTransferObject\Exceptions\PropertyAssignmentException;
use AdventureTech\DataTransferObject\ValidateProperty;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use ReflectionAttribute;
use ReflectionProperty;
use stdClass;

final class DataTransferObjectProperty
{
    private ReflectionProperty $reflection;
    private Collection $attributes;
    private stdClass $source;

    /**
     * @throws MissingPropertyTypeException
     * @throws PropertyAssignmentException
     */
    public function __construct(ReflectionProperty $reflectionProperty, stdClass $source)
    {
        $this->reflection = $reflectionProperty;
        $this->attributes = collect($this->reflection->getAttributes());
        $this->source = $source;
        ValidateProperty::validate($this);
    }

    /**
     * Get the name of the property
     * @return string
     */
    public function getName(): string
    {
        return $this->reflection->getName();
    }

    /**
     * Get the declared type of the property
     * @return ?string
     */
    public function getType(): ?string
    {
        return $this->reflection->getType()?->getName() ?: null;
    }

    /**
     * Get the name of the class this property belongs to
     * @return string
     */
    public function getDeclaringClassName(): string
    {
        return $this->reflection->getDeclaringClass()->getName();
    }

    /**
     * Fetch a specific attribute if attached to property
     * @param  string  $attributeName
     * @return ReflectionAttribute|null
     */
    public function getAttribute(string $attributeName): ?ReflectionAttribute
    {
        foreach ($this->attributes as $attribute) {
            if ($attribute->getName() == $attributeName) {
                return $attribute;
            }
        }
        return null;
    }

    /**
     * Check if a specific attribute is attached to the property
     * @param  string  $attributeName
     * @return bool
     */
    public function hasAttribute(string $attributeName): bool
    {
        return (bool) $this->getAttribute($attributeName);
    }

    /**
     * Determine which source property to map value from
     * @return string
     */
    public function getSourcePropertyToMapFrom(): string
    {
        $mapFrom = $this->getAttribute(MapFrom::class);
        return $mapFrom ? $mapFrom->getArguments()[0] : $this->reflection->getName();
    }

    /**
     * Determine if the property has the Optional attribute attached
     * @return bool
     */
    public function isOptional(): bool
    {
        return $this->hasAttribute(Optional::class);
    }

    /**
     * Alias for the isOptional() method
     * @return bool
     */
    public function isRequired(): bool
    {
        return false == $this->isOptional();
    }

    /**
     * Determine if the property is declared nullable
     * @return bool
     */
    public function allowsNull(): bool
    {
        return $this->reflection->getType()->allowsNull();
    }

    /**
     * Check if the DefaultValue attribute is attached to the property
     * @return bool
     */
    public function hasDefaultValue(): bool
    {
        return $this->hasAttribute(DefaultValue::class);
    }

    /**
     * Determine if the source property being mapped from is actually present on the source object
     * @return bool
     */
    public function propertyIsNotPresentOnSourceObject(): bool
    {
        return false == property_exists($this->source, $this->getSourcePropertyToMapFrom());
    }

    /**
     * Check if the source property being mapped from is null
     * Assumed the property is present on the source object
     * @return bool
     */
    public function sourcePropertyIsNull(): bool
    {
        if (property_exists($this->source, $this->getSourcePropertyToMapFrom())) {
            return is_null($this->source->{$this->getSourcePropertyToMapFrom()});
        }
        return true;
    }

    /**
     * Check if property has a type declaration of Carbon
     * @return bool
     */
    public function isCarbon(): bool
    {
        return $this->reflection->getType()->getName() === 'Carbon\Carbon';
    }

    /**
     * Check property has a type declaration of bool
     * @return bool
     */
    public function isBoolean(): bool
    {
        return $this->reflection->getType()->getName() === 'bool';
    }

    /**
     * Fetch the default value of this property if the DefaultValue attribute is attached.
     * @return mixed
     */
    public function getDefaultValue(): mixed
    {
        $defaultValueAttribute = $this->getAttribute(DefaultValue::class);

        $defaultValue = $defaultValueAttribute ? $defaultValueAttribute->getArguments()[0] : null;

        return $this->castValue($defaultValue);
    }


    /**
     * Inspects the type declaration of this property and casts the value if necessary
     * @param  mixed  $value
     * @return mixed
     */
    private function castValue(mixed $value): mixed
    {
        if (is_null($value)) {
            return null;
        }
        if ($this->isCarbon()) {
            return Carbon::parse($value);
        }
        if ($this->isBoolean()) {
            return (bool) $value;
        }
        return $value;
    }

    /**
     * Get the value mapped from the source object
     * @return mixed
     */
    private function getSourcePropertyValue(): mixed
    {
        if (property_exists($this->source, $this->getSourcePropertyToMapFrom())) {
            return $this->castValue($this->source->{$this->getSourcePropertyToMapFrom()});
        }
        return null;
    }

    /**
     * Map and return the value from the source object
     */
    public function getSourceValue(): mixed
    {
        return $this->getSourcePropertyValue() ?: $this->getDefaultValue();
    }
}
