<?php

namespace Tests\Unit\Rules;

use App\Enums\AttributeValueType;
use App\Rules\CategoryCode;
use App\Rules\Sku;
use App\Rules\TypedAttributeValue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogRulesTest extends TestCase
{
    protected function passes(object $rule, mixed $value): bool
    {
        $failed = false;
        $rule->validate('field', $value, function () use (&$failed) {
            $failed = true;

            return new class
            {
                public function translate(): void {}
            };
        });

        return ! $failed;
    }

    public function test_sku_rule_and_normalisation(): void
    {
        $this->assertSame('AX1-128.BLK_2', Sku::normalize('  ax1-128.blk_2 '));
        $this->assertTrue($this->passes(new Sku, 'AX1-128.BLK_2'));
        $this->assertFalse($this->passes(new Sku, 'AX1 128'));
        $this->assertFalse($this->passes(new Sku, 'AX1/128'));
        $this->assertFalse($this->passes(new Sku, str_repeat('A', 81)));
        $this->assertTrue($this->passes(new Sku, str_repeat('A', 80)));
        $this->assertFalse($this->passes(new Sku, 123));
    }

    public function test_category_code_rule_and_normalisation(): void
    {
        $this->assertSame('smart-home_2', CategoryCode::normalize(' Smart-Home_2 '));
        $this->assertTrue($this->passes(new CategoryCode, 'smart-home_2'));
        $this->assertFalse($this->passes(new CategoryCode, 'Smart Home'));
        $this->assertFalse($this->passes(new CategoryCode, str_repeat('a', 51)));
    }

    /**
     * @return array<string, array{AttributeValueType, mixed, bool}>
     */
    public static function typedValues(): array
    {
        return [
            'integer number' => [AttributeValueType::Number, '128', true],
            'decimal number' => [AttributeValueType::Number, '6.125', true],
            'six decimals' => [AttributeValueType::Number, '1.123456', true],
            'seven decimals exceed decimal(18,6)' => [AttributeValueType::Number, '1.1234567', false],
            'trailing zeros are fine' => [AttributeValueType::Number, '1.5000000', true],
            'negative number' => [AttributeValueType::Number, '-3.5', true],
            'too large' => [AttributeValueType::Number, '1000000000000', false],
            'not a number' => [AttributeValueType::Number, '12GB', false],
            'boolean true' => [AttributeValueType::Boolean, 'true', true],
            'boolean upper case' => [AttributeValueType::Boolean, 'FALSE', true],
            'boolean one' => [AttributeValueType::Boolean, '1', true],
            'boolean json' => [AttributeValueType::Boolean, false, true],
            'boolean yes is not accepted' => [AttributeValueType::Boolean, 'yes', false],
            'text' => [AttributeValueType::Text, 'Titanium', true],
            'text too long' => [AttributeValueType::Text, str_repeat('x', 2001), false],
            'text must be a string' => [AttributeValueType::Text, ['x'], false],
        ];
    }

    #[DataProvider('typedValues')]
    public function test_typed_attribute_values(AttributeValueType $type, mixed $value, bool $expected): void
    {
        $this->assertSame($expected, TypedAttributeValue::passes($type, $value));
    }

    public function test_values_are_cast_to_exactly_one_typed_column(): void
    {
        $this->assertSame(['text_value' => null, 'number_value' => '6.1', 'boolean_value' => null], TypedAttributeValue::toColumns(AttributeValueType::Number, ' 6.1 '));
        $this->assertSame(['text_value' => null, 'number_value' => null, 'boolean_value' => false], TypedAttributeValue::toColumns(AttributeValueType::Boolean, 'FALSE'));
        $this->assertSame(['text_value' => 'Blue', 'number_value' => null, 'boolean_value' => null], TypedAttributeValue::toColumns(AttributeValueType::Text, 'Blue'));
    }
}
