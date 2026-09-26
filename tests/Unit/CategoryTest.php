<?php

declare(strict_types=1);

namespace Tests\Unit;

use Category;
use TestDb;
use Tests\Support\AppTestCase;

final class CategoryTest extends AppTestCase
{
    public function testCreateAndTypeOf(): void
    {
        $exp = TestDb::categoryId('Food & Dining');
        $inc = TestDb::categoryId('Salary');
        $this->assertSame('expense', Category::typeOf($exp));
        $this->assertSame('income', Category::typeOf($inc));
    }

    public function testValidForTypeMatching(): void
    {
        $exp = TestDb::categoryId('Food & Dining');
        $inc = TestDb::categoryId('Salary');
        $this->assertTrue(Category::isValidForType($exp, 'expense'));
        $this->assertTrue(Category::isValidForType($inc, 'income'));
    }

    public function testInvalidForTypeMismatch(): void
    {
        $exp = TestDb::categoryId('Food & Dining');
        $inc = TestDb::categoryId('Salary');
        $this->assertFalse(Category::isValidForType($exp, 'income'));
        $this->assertFalse(Category::isValidForType($inc, 'expense'));
    }

    public function testEmptyCategoryIsAllowed(): void
    {
        $this->assertTrue(Category::isValidForType(0, 'expense'));
        $this->assertTrue(Category::isValidForType(0, 'income'));
    }

    public function testMissingCategoryIsRejected(): void
    {
        $this->assertNull(Category::typeOf(999999));
        $this->assertFalse(Category::isValidForType(999999, 'expense'));
    }

    public function testDeletedCategoryBecomesInvalid(): void
    {
        $id = Category::create('TEMP_DEL', 'expense', '#3155C8') ? (int) TestDb::scalar("SELECT id FROM categories WHERE name = 'TEMP_DEL'") : 0;
        $this->assertTrue(Category::isValidForType($id, 'expense'));
        Category::delete($id);
        $this->assertFalse(Category::isValidForType($id, 'expense'));
    }
}
