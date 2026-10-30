<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Model;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Model\Organization;
use Pidgey\Model\Row;

#[CoversClass(Organization::class)]
#[UsesClass(Row::class)]
final class OrganizationTest extends TestCase
{
    public function testFromRowMapsEveryColumn(): void
    {
        $organization = Organization::fromRow($this->row());

        $this->assertSame(7, $organization->id);
        $this->assertSame('Acme', $organization->name);
        $this->assertSame('2026-01-02 03:04:05', $organization->createdAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $organization->createdAt->getTimezone()->getName());
        $this->assertSame('2026-02-03 04:05:06', $organization->updatedAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $organization->updatedAt->getTimezone()->getName());
    }

    public function testFromRowRejectsAMissingColumn(): void
    {
        $row = $this->row();
        unset($row['name']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(Organization::class . ": missing column 'name'");

        Organization::fromRow($row);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(): array
    {
        return [
            'id' => 7,
            'name' => 'Acme',
            'created_at' => '2026-01-02 03:04:05',
            'updated_at' => '2026-02-03 04:05:06',
        ];
    }
}
