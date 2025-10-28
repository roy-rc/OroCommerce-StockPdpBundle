# Acme Stock Display Bundle

## Overview

The **Acme Stock Display Bundle** is a custom OroCommerce module that displays product inventory status and available stock quantity on the Product Detail Page (PDP). This bundle integrates seamlessly with OroCommerce's inventory management system to provide customers with real-time stock information.

## Features

- **Real-time Stock Display**: Shows the current available stock quantity for products
- **Inventory Status Validation**: Respects OroCommerce's inventory status settings (In Stock, Out of Stock, etc.)
- **CSS Styling**: Dynamic CSS classes based on stock availability for custom styling
- **Low Inventory Detection**: Integration with OroCommerce's low inventory provider
- **Graceful Error Handling**: Safe extraction of product entities from various wrapper objects

## Prerequisites

Install [Docker](https://docs.docker.com/engine/install/) with [Docker Compose](https://docs.docker.com/compose/install/).

**Note:** The application uses port 80, so make sure that other services do not use it.

## Run Application

#### 1. Download Repository With Docker Compose Configuration File

Check out the git repository:
```bash
git clone https://github.com/oroinc/docker-demo.git
cd docker-demo
```
Or download the archive file and extract it:
```bash
wget https://github.com/oroinc/docker-demo/archive/master.tar.gz -O - | tar -xzf -
cd docker-demo
```

#### 2. Run Application Containers

The configuration is entirely predefined, and you can only change the domain name where the application will be located. By default, it is `oro.demo`. If you need to change the domain, edit the `.env` file and change `ORO_APP_DOMAIN=my-custom-domain.demo`.

Modify the compose.yaml file to configure bind mounts
in the volumes section:
```bash
volumes:
      - ./oro:/var/www/oro
```

Run init service:
```bash
docker compose up restore
```

Alternatively, you can install the application from scratch, but it will require more time and resources.

Run install service:
```bash
docker compose up install
```

You can run the application as soon as it is installed or initialized.

Run application:
```bash
docker compose up application
```

The docker compose will download the required images, create networks and run containers.
Application [orocommerce-application](https://github.com/oroinc/orocommerce-application) is used by default.
If you want to get the application in a different locale, add the contents of the file `.env-locale-de_DE` or `.env-locale-fr_FR` to `.env` and restart the restore service and application.
```bash
cat .env-locale-de_DE >> .env
```

To track the logs from the php-fpm-app container, run `docker compose logs -f php-fpm-app`. To get the list of containers, run `docker compose ps`.

#### 3. Add a Record to File `/etc/hosts`

```
127.0.0.1 oro.demo
```

#### 4. Open the Application in a Browser

Now, you can open URL [http://oro.demo](http://oro.demo) in your browser.

To access the back-office, use *admin* as both login and password.
To access the storefront, use the credentials of the predefined demo user roles. To log in as a buyer, use *BrandaJSanborn@example.org* both as your login and password. To log in as a manager, use *AmandaRCole@example.org* both as your login and password.

## Stop the Application

- To stop and remove all containers, run `docker compose down`.

- To stop and remove all containers with the data saved in volumes, run `docker compose down -v`.

**This deployment is NOT intended for a production environment.**

## Technical Architecture

### Components

#### 1. **StockProvider Data Provider** (`StockProvider.php`)

The core service that handles all stock-related logic:

```php
class StockProvider
{
    - getAvailableStock($product): ?int        // Retrieves current stock quantity
    - isInStock($product): bool                // Checks if product has stock available
    - getStockMessage($product): string        // Returns formatted stock message
    - isLowInventory($product): bool           // Checks if inventory is low
}
```

**Key Methods:**

- **`getAvailableStock()`**: 
  - Extracts the Product entity from wrapper objects
  - Validates inventory status via `InventoryStatusProvider`
  - Returns null if status is "out_of_stock" (regardless of quantity)
  - Queries the `InventoryLevel` table for the actual stock quantity
  
- **`isInStock()`**: 
  - Combines inventory status and stock quantity checks
  - Returns true only if status is NOT out_of_stock AND quantity > 0

- **`getStockMessage()`**: 
  - Returns "Out of stock" if inventory status = out_of_stock
  - Returns "Out of stock" if quantity <= 0
  - Returns "Available stock: X units" for in-stock products

#### 2. **Layout Configuration** (`stock_display.yml`)

Defines how the stock information is displayed on the Product Detail Page:

```yaml
layout:
    actions:
        - '@add':              # Add main container block
            id: product_stock_display
            parentId: product_view_primary_container
            blockType: container
            options:
                attr:
                    class: product-stock-display
        
        - '@add':              # Add stock message text block
            id: product_stock_message
            parentId: product_stock_display
            blockType: text
            options:
                text: '=data["stock_provider"].getStockMessage(data["product"])'
                visible: '=true'
```

**Layout Process:**
1. Block is added to `product_view_primary_container`
2. Stock provider is injected via data provider
3. Product entity is extracted from layout context
4. Stock message is rendered via Twig template

#### 3. **Twig Template** (`stock_display.html.twig`)

Renders the stock display UI:

```twig
{% block _product_stock_display_widget %}
    <div{{ block('block_attributes') }}>
        {{ block_widget(block) }}
    </div>
{% endblock %}

{% block _product_stock_message_widget %}
    <div{{ block('block_attributes') }}>
        <strong>{{ text }}</strong>
    </div>
{% endblock %}
```

#### 4. **CSS Styling** (`stock-display.css`)

Provides base styling for the stock display:

```css
.product-stock-display {
    /* Container styling */
}

.stock-available {
    /* Styling for in-stock products */
}

.stock-unavailable {
    /* Styling for out-of-stock products */
}
```

### Data Flow

```
Product Detail Page
    ↓
Layout System loads stock_display.yml
    ↓
StockProvider injected as data provider
    ↓
getStockMessage() called with Product entity
    ↓
[Check 1] Extract entity from wrapper object
    ↓
[Check 2] Get inventory status via InventoryStatusProvider
    ↓
[Check 3] Query InventoryLevel table for quantity
    ↓
Return formatted message
    ↓
Twig template renders the message
    ↓
CSS classes applied based on stock status
    ↓
Display rendered to customer
```
## Dependency Injection

### Services Registered

```yaml
acme_stock_display.layout.data_provider.stock:
    class: Acme\Bundle\StockDisplayBundle\Layout\DataProvider\StockProvider
    arguments:
        - '@doctrine.orm.entity_manager'           # For database queries
        - '@oro_inventory.inventory.low_inventory_provider'  # Low inventory check
        - '@oro_inventory.provider.inventory_status'         # Status provider
    tags:
        - { name: layout.data_provider, alias: stock_provider }
```

### CompilerPass

`MakeInventoryServicePublicPass` ensures private services are exposed:
- `oro_inventory.inventory.low_inventory_provider`
- `oro_inventory.provider.inventory_status`

## Inventory Status Codes

The module validates against OroCommerce's inventory status enum:

| Code | Display | Behavior |
|------|---------|----------|
| `prod_inventory_status.in_stock` | In Stock | Shows "Available stock: X units" |
| `prod_inventory_status.out_of_stock` | Out of Stock | Shows "Out of stock" |
| `prod_inventory_status.discontinued` | Discontinued | Shows "Out of stock" |

**Validation Logic:**
```php
// If inventory status contains "out_of_stock"
if (strpos($inventoryStatusCode, 'out_of_stock') !== false) {
    return 'Out of stock';
}
```

## Error Handling

The module includes robust error handling:

1. **Entity Extraction**: Multiple methods to extract Product from wrappers
   - Direct instance check
   - Method-based extraction (`getEntity()`, `entity()`)
   - Array-based extraction

2. **Null Safety**: Returns safe default values
   - Null product → "Out of stock"
   - Missing inventory level → "Out of stock"
   - Invalid quantity → "Out of stock"

3. **ExtendEntity Support**: Handles OroCommerce's dynamic entity extension system
   - Gracefully handles `ExtendEntityTrait` properties
   - Avoids direct property access that could trigger errors

## Performance Considerations

- **Database Query**: Single query to `InventoryLevel` per product view
- **Caching**: Leverages OroCommerce's layout and query caching
- **Provider Calls**: Minimal calls to `InventoryStatusProvider` (uses local property)

## Testing the Module

### Manual Testing on Product Detail Page

1. **In-Stock Product**:
   - Product with inventory_status = "In Stock"
   - Quantity > 0
   - Expected Display: "Available stock: X units"

2. **Out-of-Stock Product (Status)**:
   - Product with inventory_status = "Out of Stock"
   - Quantity > 0 (quantity is ignored)
   - Expected Display: "Out of stock"

3. **Out-of-Stock Product (Quantity)**:
   - Product with inventory_status = "In Stock"
   - Quantity = 0 or null
   - Expected Display: "Out of stock"

## Extension Points

To extend this module:

### 1. Add Custom Status Support
```php
public function getStockMessage($product): string
{
    // Add custom logic for other status codes
    if ($inventoryStatusCode === 'custom_status') {
        return 'Custom Message';
    }
    // ... existing logic
}
```

### 2. Customize Display Format
Edit `stock_display.yml` to change:
- Parent container
- Block type
- CSS classes
- Template block name

### 3. Add More Stock Information
```php
public function getStockDetails($product): array
{
    return [
        'quantity' => $this->getAvailableStock($product),
        'status' => $this->inventoryStatusProvider->getCode($product),
        'isLow' => $this->isLowInventory($product),
        'restockDate' => $this->getRestockDate($product)
    ];
}
```

## Files Structure

```
StockDisplayBundle/
├── AcmeStockDisplayBundle.php              # Bundle class with compiler pass
├── README.md                                # This file
├── DependencyInjection/
│   ├── AcmeStockDisplayExtension.php       # Service configuration loader
│   └── CompilerPass/
│       └── MakeInventoryServicePublicPass.php  # Makes services public
├── Layout/
│   └── DataProvider/
│       └── StockProvider.php               # Core business logic
└── Resources/
    ├── config/
    │   ├── services.yml                    # Service definitions
    │   └── oro/
    │       └── bundles.yml                 # Bundle registration
    ├── public/
    │   └── css/
    │       └── stock-display.css          # Styling
    └── views/
        └── layouts/
            └── default/
                └── oro_product_frontend_product_view/
                    ├── stock_display.yml         # Layout configuration
                    └── stock_display.html.twig   # Twig template
```

## Troubleshooting

### "Out of stock" shows for all products
- Check if `InventoryStatusProvider::getCode()` is returning the correct enum ID
- Verify inventory levels exist in `oro_inventory_level` table
- Clear cache: `php bin/console cache:clear`

### Module not displaying
- Verify bundle is registered: `php bin/console debug:container stock_provider`
- Check parent container ID: `product_view_primary_container`
- Review layout cache: `php bin/console cache:clear`

### Inventory status not recognized
- Verify status enum values in database
- Check `Product::INVENTORY_STATUS_*` constants match your enum values

## Related Documentation

- [OroCommerce Inventory System](https://doc.oroinc.com/user/back-office/inventory/)
- [OroCommerce Layout System](https://doc.oroinc.com/frontend/storefront/layouts/#dev-doc-frontend-layouts-layout)
- [Extending OroCommerce](https://doc.oroinc.com/backend/extend-commerce/#dev-extend-commerce)
