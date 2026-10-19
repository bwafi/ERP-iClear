<?php

namespace Tests\unit;

use CodeIgniter\Test\CIUnitTestCase;

final class MenuRouteCollectionTest extends CIUnitTestCase
{
    public function testProductCategoryWritesUseDistinctRoutesFromCashCategories(): void
    {
        $routes = service('routes');
        $routes->loadRoutes();
        $postRoutes = array_keys($routes->getRoutes('POST'));

        self::assertContains('kategori/insert_kategori', $postRoutes);
        self::assertContains('kategori/update_kategori', $postRoutes);
        self::assertContains('kategori/delete_kategori', $postRoutes);
        self::assertContains('insert_kategori', $postRoutes);
    }
}
