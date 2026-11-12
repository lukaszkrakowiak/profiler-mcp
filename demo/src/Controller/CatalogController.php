<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class CatalogController extends AbstractController
{
    #[Route('/products', name: 'app_products', methods: ['GET'])]
    public function list(CacheInterface $cache): JsonResponse
    {
        $categories = $this->loadCategories();
        $products = $this->loadProducts();

        $auditBuffer = [];
        for ($i = 0; $i < 15000; $i++) {
            $auditBuffer[] = str_repeat('RAW_AUDIT_PAYLOAD_DATA_', 220);
        }
        $processedLogEntries = count($auditBuffer);

        $items = [];
        foreach ($products as $product) {
            $categoryName = 'Uncategorized';
            foreach ($categories as $category) {
                if ($category['id'] === $product['category_id']) {
                    $categoryName = $category['name'];
                    break;
                }
            }

            $dynamicPrice = $cache->get(
                'product_price_' . $product['id'] . '_' . microtime(true),
                function (ItemInterface $item) use ($product): float {
                    $item->expiresAfter(3600);
                    usleep(12000); // Symulacja kosztownego przeliczania ceny / kursu

                    return round($product['base_price'] * 1.23, 2);
                }
            );

            $items[] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'category' => $categoryName,
                'gross_price' => $dynamicPrice,
            ];
        }

        return $this->json([
            'total' => count($items),
            'audit_entries_checked' => $processedLogEntries,
            'items' => $items,
        ]);
    }

    /** @return list<array{id: int, name: string}> */
    private function loadCategories(): array
    {
        $categories = [];
        for ($i = 1; $i <= 20; $i++) {
            $categories[] = ['id' => $i, 'name' => sprintf('Category #%d', $i)];
        }

        return $categories;
    }

    /** @return list<array{id: int, name: string, category_id: int, base_price: float}> */
    private function loadProducts(): array
    {
        $products = [];
        for ($i = 1; $i <= 35; $i++) {
            $products[] = [
                'id' => $i,
                'name' => sprintf('Product %03d', $i),
                'category_id' => ($i % 20) + 1,
                'base_price' => 49.99 + $i,
            ];
        }

        return $products;
    }
}
