---
paths:
  - 'app/Models/InventoryMovement.php|resources/views/pages/owner/*stock*.blade.php'
---

# Owner

## Inventory movements are append-only
Do not edit or delete historical inventory movements. Correct stock through an ADJUSTMENT movement that atomically updates Product.stock and records stock_before, stock_after, an audit reason, and created_by. Reject stale adjustments when stock changed after product selection.
