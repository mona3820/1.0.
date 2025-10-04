<?php
require_once __DIR__ . '/config/db.php';
checkAuth();
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="inventory_export_'.date('Ymd_His').'.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['ID','Name','Code','Category','Supplier','Quantity','MinQty','CostPrice','SellingPrice','TotalValue']);
$q = $conn->query("SELECT p.id, p.name, p.code, c.name AS category, s.name AS supplier, p.quantity, p.min_quantity, p.cost_price, p.selling_price, (p.quantity*p.cost_price) AS total_value FROM products p LEFT JOIN categories c ON p.category_id=c.id LEFT JOIN suppliers s ON p.supplier_id=s.id ORDER BY p.name");
while($row = $q->fetch_assoc()) {
    fputcsv($out, [
        $row['id'], $row['name'], $row['code'], $row['category'], $row['supplier'],
        $row['quantity'], $row['min_quantity'], $row['cost_price'], $row['selling_price'], $row['total_value']
    ]);
}
fclose($out);
exit;
