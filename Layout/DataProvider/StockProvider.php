<?php

namespace Acme\Bundle\StockDisplayBundle\Layout\DataProvider;

use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\InventoryBundle\Inventory\LowInventoryProvider;
use Oro\Bundle\InventoryBundle\Provider\InventoryStatusProvider;
use Doctrine\ORM\EntityManagerInterface;

class StockProvider
{
    private EntityManagerInterface $entityManager;
    private LowInventoryProvider $lowInventoryProvider;
    private InventoryStatusProvider $inventoryStatusProvider;

    public function __construct(
        EntityManagerInterface $entityManager,
        LowInventoryProvider $lowInventoryProvider,
        InventoryStatusProvider $inventoryStatusProvider
    ) {
        $this->entityManager = $entityManager;
        $this->lowInventoryProvider = $lowInventoryProvider;
        $this->inventoryStatusProvider = $inventoryStatusProvider;
    }

    /**
     * Get available stock quantity for a product
     *
     * @param Product $product
     * @return int|null
     */
    public function getAvailableStock($product): ?int
    {
        // Extraer la entidad si es un objeto envolvente
        $productEntity = $this->extractEntity($product);
        
        if (!$productEntity instanceof Product) {
            return null;
        }

        // Verificar el estado de inventario primero
        $inventoryStatusCode = $this->inventoryStatusProvider->getCode($productEntity);
        
        // Si el estado contiene "out_of_stock", retornar null
        if ($inventoryStatusCode && strpos($inventoryStatusCode, 'out_of_stock') !== false) {
            return null;
        }

        // Query para obtener el nivel de inventario del producto
        $inventoryLevel = $this->entityManager
            ->getRepository(\Oro\Bundle\InventoryBundle\Entity\InventoryLevel::class)
            ->findOneBy(['product' => $productEntity]);

        if (!$inventoryLevel) {
            return null;
        }

        return (int) $inventoryLevel->getQuantity();
    }

    /**
     * Extract entity from wrapper or return as is
     *
     * @param mixed $data
     * @return Product|null
     */
    private function extractEntity($data): ?Product
    {
        if ($data instanceof Product) {
            return $data;
        }

        if (is_object($data) && method_exists($data, 'getEntity')) {
            return $data->getEntity();
        }

        if (is_object($data) && method_exists($data, 'entity')) {
            return $data->entity();
        }

        if (is_array($data) && isset($data['entity'])) {
            return $data['entity'];
        }

        return null;
    }

    /**
     * Check if product is in stock
     *
     * @param Product $product
     * @return bool
     */
    public function isInStock($product): bool
    {
        $productEntity = $this->extractEntity($product);
        
        if (!$productEntity instanceof Product) {
            return false;
        }
        
        // Verificar el estado de inventario
        $inventoryStatusCode = $this->inventoryStatusProvider->getCode($productEntity);
        
        if ($inventoryStatusCode && strpos($inventoryStatusCode, 'out_of_stock') !== false) {
            return false;
        }
        
        $stock = $this->getAvailableStock($product);
        return $stock !== null && $stock > 0;
    }

    /**
     * Get formatted stock message
     *
     * @param Product $product
     * @return string
     */
    public function getStockMessage($product): string
    {
        $productEntity = $this->extractEntity($product);
        
        if (!$productEntity instanceof Product) {
            return 'Out of stock';
        }
        
        // Verificar el estado de inventario
        $inventoryStatusCode = $this->inventoryStatusProvider->getCode($productEntity);
        
        // El código contiene "out_of_stock" en su valor
        if ($inventoryStatusCode && strpos($inventoryStatusCode, 'out_of_stock') !== false) {
            return 'Out of stock';
        }
        
        $stock = $this->getAvailableStock($product);
        
        if ($stock === null || $stock <= 0) {
            return 'Out of stock';
        }
        
        return sprintf('Available stock: %d units', $stock);
    }

    /**
     * Check if product has low inventory
     *
     * @param Product $product
     * @return bool
     */
    public function isLowInventory($product): bool
    {
        $productEntity = $this->extractEntity($product);
        
        if (!$productEntity instanceof Product) {
            return false;
        }
        
        return $this->lowInventoryProvider->isLowInventoryProduct($productEntity);
    }
}

