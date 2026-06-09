<?php

declare(strict_types=1);

namespace Nomokonov\SberSdk\Tests\Validation;

use Nomokonov\SberSdk\Exception\ValidationException;
use Nomokonov\SberSdk\Validation\Rule;
use Nomokonov\SberSdk\Validation\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Schema::class)]
#[CoversClass(Rule::class)]
#[CoversClass(ValidationException::class)]
final class SchemaTest extends TestCase
{
    public function testRequiredFieldMissingProducesError(): void
    {
        $schema = Schema::make(['name' => Rule::string()->required()]);

        $errors = $schema->validate([]);

        self::assertCount(1, $errors);
        self::assertSame('name', $errors[0]->field);
    }

    public function testOptionalMissingFieldIsAllowed(): void
    {
        $schema = Schema::make(['name' => Rule::string()]);

        self::assertSame([], $schema->validate([]));
    }

    public function testPatternValidation(): void
    {
        $schema = Schema::make(['bic' => Rule::string()->pattern('/^\d{9}$/')->required()]);

        self::assertSame([], $schema->validate(['bic' => '044525225']));
        self::assertNotSame([], $schema->validate(['bic' => '123']));
    }

    public function testNumberBoundsAndType(): void
    {
        $schema = Schema::make(['amount' => Rule::number()->min(0.01)->required()]);

        self::assertSame([], $schema->validate(['amount' => 10.5]));
        self::assertNotSame([], $schema->validate(['amount' => 0]));
        self::assertNotSame([], $schema->validate(['amount' => 'not-a-number']));
    }

    public function testEnumValidation(): void
    {
        $schema = Schema::make(['type' => Rule::string()->valid('A', 'B')->required()]);

        self::assertSame([], $schema->validate(['type' => 'A']));
        self::assertNotSame([], $schema->validate(['type' => 'C']));
    }

    public function testGuidValidation(): void
    {
        $schema = Schema::make(['id' => Rule::string()->guid()->required()]);

        self::assertSame([], $schema->validate(['id' => '550e8400-e29b-41d4-a716-446655440000']));
        self::assertNotSame([], $schema->validate(['id' => 'not-a-guid']));
    }

    public function testNestedObjectValidation(): void
    {
        $schema = Schema::make([
            'vat' => Rule::object(Schema::make([
                'type' => Rule::string()->required(),
            ]))->required(),
        ]);

        self::assertSame([], $schema->validate(['vat' => ['type' => 'INCLUDED']]));
        $errors = $schema->validate(['vat' => []]);
        self::assertSame('vat.type', $errors[0]->field);
    }

    public function testArrayItemsValidation(): void
    {
        $schema = Schema::make([
            'items' => Rule::array(Rule::string()->required())->min(1)->required(),
        ]);

        self::assertSame([], $schema->validate(['items' => ['a', 'b']]));
        self::assertNotSame([], $schema->validate(['items' => []]));
        $errors = $schema->validate(['items' => [123]]);
        self::assertSame('items[0]', $errors[0]->field);
    }

    public function testKeysExtendsSchema(): void
    {
        $base = Schema::make(['a' => Rule::string()->required()]);
        $extended = $base->keys(['b' => Rule::string()->required()]);

        self::assertCount(1, $extended->validate(['a' => 'x']));
        self::assertSame([], $base->validate(['a' => 'x']));
    }

    public function testAssertThrowsAggregatedException(): void
    {
        $schema = Schema::make([
            'a' => Rule::string()->required(),
            'b' => Rule::string()->required(),
        ]);

        try {
            $schema->assert([]);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertCount(2, $e->getErrors());
            self::assertStringContainsString('Validation failed', $e->getMessage());
        }
    }
}
