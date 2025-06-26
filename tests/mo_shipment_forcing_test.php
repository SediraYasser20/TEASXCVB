<?php

// Global Test Variables
$db_host = 'localhost';
$db_user = 'dolibarr_user'; // Replace with your test DB user
$db_pass = 'dolibarr_pass'; // Replace with your test DB password
$db_name = 'dolibarr_test_db'; // Replace with your test DB name
$dolibarr_url = 'http://localhost/dolibarr/'; // Replace with your Dolibarr URL

// Mock Dolibarr Environment (Simplified)
define('DOL_DOCUMENT_ROOT', '/var/www/html/dolibarr/htdocs'); // Adjust if needed
define('MAIN_DB_PREFIX', 'llx_');

// --- Database Connection ---
function getTestDB() {
    global $db_host, $db_name, $db_user, $db_pass;
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    // Set UTF-8 if not default
    $conn->set_charset("utf8");
    return $conn;
}

// --- Dolibarr Classes (Mocked or Included if possible in a real test env) ---
// For this conceptual script, we'll assume these classes can be loaded or key methods mocked.
// require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
// require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
// require_once DOL_DOCUMENT_ROOT.'/product/stock/class/productlot.class.php';
// require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
// require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
// require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
// require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php'; // for price2num etc.

// --- Test Utility Functions ---
function test_log($message) {
    echo "[" . date("Y-m-d H:i:s") . "] " . $message . "\n";
}

function begin_test_scenario($scenario_name) {
    test_log("BEGIN SCENARIO: $scenario_name");
    echo "------------------------------------------------------\n";
}

function end_test_scenario() {
    echo "------------------------------------------------------\n\n";
}

function assert_true($condition, $message) {
    if ($condition) {
        test_log("PASS: $message");
    } else {
        test_log("FAIL: $message");
    }
}

function assert_equal($expected, $actual, $message) {
    if ($expected == $actual) {
        test_log("PASS: $message (Expected: $expected, Actual: $actual)");
    } else {
        test_log("FAIL: $message (Expected: $expected, Actual: $actual)");
    }
}

function execute_sql($db, $sql) {
    if (!$db->query($sql)) {
        test_log("SQL ERROR: " . $db->error . " --- SQL: " . $sql);
        return false;
    }
    return true;
}

function fetch_sql_value($db, $sql, $column) {
    $result = $db->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row[$column];
    }
    test_log("SQL FETCH WARNING: No rows/value found for column '$column' --- SQL: $sql");
    return null;
}

function fetch_sql_row($db, $sql) {
    $result = $db->query($sql);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    test_log("SQL FETCH WARNING: No row found --- SQL: $sql");
    return null;
}

function fetch_sql_rows($db, $sql) {
    $result = $db->query($sql);
    $rows = array();
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    } else {
        test_log("SQL ERROR: " . $db->error . " --- SQL: " . $sql);
    }
    return $rows;
}


// --- Data Setup Functions ---

/**
 * Creates or updates a product.
 * @param mysqli $db
 * @param int $productId
 * @param string $ref
 * @param string $label
 * @param int $statusBatch 0=not managed, 1=batch, 2=serial
 * @param int|null $targetId The specific rowid to use for this product (e.g., 31)
 * @return int product rowid
 */
function setup_product($db, $ref, $label, $statusBatch = 0, $isEntity = 1, $type = 0, $targetId = null) {
    if ($targetId !== null) {
        // Check if targetId is free or already matches our product ref
        $resTarget = $db->query("SELECT rowid, ref FROM ".MAIN_DB_PREFIX."product WHERE rowid = $targetId AND entity = $isEntity");
        if ($resTarget && $resTarget->num_rows > 0) {
            $objTarget = $resTarget->fetch_object();
            if ($objTarget->ref == $ref) {
                execute_sql($db, "UPDATE ".MAIN_DB_PREFIX."product SET status_batch = $statusBatch, label = '".$db->real_escape_string($label)."' WHERE rowid = $targetId");
                test_log("Updated product $ref at specific ID: $targetId");
                return $targetId;
            } else {
                test_log("WARNING: Product ID $targetId already exists with a different ref ('".$objTarget->ref."'). Cannot create '$ref' with this specific ID. Will attempt to create with auto ID.");
                // Fall through to create with auto ID if ref is different, or handle as error. For now, fall through.
            }
        } else { // ID is free, try to insert with it. This often requires AUTO_INCREMENT manipulation or specific SQL mode.
            // Standard INSERT won't guarantee the ID. For testing, this is tricky without direct DB control.
            // We'll log a warning and proceed with auto-increment, then check.
            test_log("INFO: Target ID $targetId is free. Standard INSERT will be used. Actual ID might differ unless DB config allows explicit ID insertion.");
        }
    }

    // Check if product exists by ref (standard behavior)
    $res = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE ref = '".$db->real_escape_string($ref)."' AND entity = $isEntity");
    if ($res && $res->num_rows > 0) {
        $obj = $res->fetch_object();
        $productId = $obj->rowid;
        execute_sql($db, "UPDATE ".MAIN_DB_PREFIX."product SET status_batch = $statusBatch, label = '".$db->real_escape_string($label)."' WHERE rowid = $productId");
        test_log("Updated product $ref (ID: $productId)");
         if ($targetId && $productId != $targetId) {
            test_log("WARNING: Product $ref was expected at ID $targetId but found/updated at ID $productId.");
        }
        return $productId;
    } else {
        // Attempt to insert. If $targetId was specified and free, this *might* take it depending on SQL mode.
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."product (".($targetId ? "rowid, " : "")."ref, label, entity, status, status_batch, type, tosell, tobuy, datec, tms) 
                VALUES (".($targetId ? "$targetId, " : "")."'".$db->real_escape_string($ref)."', '".$db->real_escape_string($label)."', $isEntity, 1, $statusBatch, $type, 1, 1, NOW(), NOW())";
        
        if (execute_sql($db, $sql)) {
            $newProductId = $db->insert_id;
            if ($targetId && $newProductId != $targetId && $db->warning_count == 0) { // Check if insert_id matches targetId, and no warning about it was suppressed
                 // This path is less likely if $targetId was in the INSERT and SQL mode allows it.
                 // More likely if $targetId was not in INSERT or SQL mode doesn't allow specifying it.
                 test_log("WARNING: Product $ref was created with ID $newProductId, not the target $targetId (if specified and free). This might be due to AUTO_INCREMENT behavior.");
            } elseif ($targetId && $newProductId == $targetId) {
                 test_log("Created product $ref with specific ID: $newProductId");
            } else {
                 test_log("Created product $ref (ID: $newProductId)");
            }
            return $newProductId;
        }
        return 0;
    }
}

/**
 * Adds stock for a product, optionally with a serial/batch number.
 * @param mysqli $db
 * @param int $productId
 * @param int $warehouseId
 * @param int $qty
 * @param string|null $batchOrSerial Serial number if product is serial managed.
 * @return int product_lot rowid or 0
 */
function add_product_stock($db, $productId, $warehouseId, $qty, $batchOrSerial = null, $eatby = null, $sellby = null) {
    $lotId = 0;
    if ($batchOrSerial) {
        // Check if serial/lot exists for this product
        $resLot = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."product_lot WHERE fk_product = $productId AND batch = '".$db->real_escape_string($batchOrSerial)."' AND entity = 1");
        if ($resLot && $resLot->num_rows > 0) {
            $objLot = $resLot->fetch_object();
            $lotId = $objLot->rowid;
        } else {
            $sqlLot = "INSERT INTO ".MAIN_DB_PREFIX."product_lot (fk_product, entity, batch, eatby, sellby, datec, tms) 
                       VALUES ($productId, 1, '".$db->real_escape_string($batchOrSerial)."', ".($eatby ? "'".$eatby."'" : "NULL").", ".($sellby ? "'".$sellby."'" : "NULL").", NOW(), NOW())";
            if (execute_sql($db, $sqlLot)) {
                $lotId = $db->insert_id;
            } else {
                test_log("Failed to create lot $batchOrSerial for product ID $productId");
                return 0;
            }
        }
    }

    // Add stock movement
    $sqlStock = "INSERT INTO ".MAIN_DB_PREFIX."stock_mouvement (fk_product, fk_entrepot, value, type_mouvement, fk_lot, label, inventorycode, datec, tms)
                 VALUES ($productId, $warehouseId, $qty, 1, ".($lotId ? $lotId : "NULL").", 'Initial Test Stock', 'TEST_STOCK_".time().uniqid()."', NOW(), NOW())";
    if (execute_sql($db, $sqlStock)) {
        test_log("Added $qty units of product ID $productId (Lot/Serial: ".($batchOrSerial ? $batchOrSerial : 'N/A').", LotID: $lotId) to warehouse ID $warehouseId");
    } else {
        test_log("Failed to add stock for product ID $productId");
        if ($lotId && (!$resLot || $resLot->num_rows == 0)) { // If lot was newly created for this, roll it back potentially
            execute_sql($db, "DELETE FROM ".MAIN_DB_PREFIX."product_lot WHERE rowid = $lotId");
            test_log("Rolled back creation of lot ID $lotId due to stock add failure.");
            return 0;
        }
    }
    return $lotId;
}

/**
 * Creates a basic warehouse if it doesn't exist.
 * @param mysqli $db
 * @param string $ref
 * @param string $label
 * @return int warehouse rowid
 */
function setup_warehouse($db, $ref = 'TESTWH', $label = 'Test Warehouse', $entity = 1) {
    $res = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."entrepot WHERE ref = '".$db->real_escape_string($ref)."' AND entity = $entity");
    if ($res && $res->num_rows > 0) {
        $obj = $res->fetch_object();
        test_log("Using existing warehouse $ref (ID: ".$obj->rowid.")");
        return $obj->rowid;
    } else {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."entrepot (ref, label, entity, lieu, description, statut, datec, tms) 
                VALUES ('".$db->real_escape_string($ref)."', '".$db->real_escape_string($label)."', $entity, 'Test Location', 'Test warehouse for automated tests', 1, NOW(), NOW())";
        if (execute_sql($db, $sql)) {
            $newWarehouseId = $db->insert_id;
            test_log("Created warehouse $ref (ID: $newWarehouseId)");
            return $newWarehouseId;
        }
        return 0;
    }
}

/**
 * Creates a customer order with specified lines.
 * @param mysqli $db
 * @param int $socId Thirdparty ID
 * @param array $lines Array of line details. Each line is an array:
 *                     ['fk_product' => (int) or null, 'description' => (string), 'qty' => (int), 'subprice' => (float)]
 * @return int order rowid
 */
function create_customer_order($db, $socId, $lines, $ref_client = 'TEST_ORDER_REF_CLIENT', $entity = 1) {
    // Create order
    $orderRef = "CO-TEST-" . time();
    $sqlOrder = "INSERT INTO ".MAIN_DB_PREFIX."commande (ref, ref_client, fk_soc, entity, date_commande, fk_statut, amount_ht, tva, amount_ttc, datec, tms)
                 VALUES ('".$db->real_escape_string($orderRef)."', '".$db->real_escape_string($ref_client)."', $socId, $entity, NOW(), 0, 0, 0, 0, NOW(), NOW())";
    if (!execute_sql($db, $sqlOrder)) {
        test_log("Failed to create order header.");
        return 0;
    }
    $orderId = $db->insert_id;
    test_log("Created order $orderRef (ID: $orderId)");

    $total_ht = 0;
    // Add order lines
    foreach ($lines as $index => $line) {
        $desc = $line['description'];
        $qty = $line['qty'];
        $subprice = isset($line['subprice']) ? $line['subprice'] : 10; // Default price
        $fk_product = isset($line['fk_product']) ? $line['fk_product'] : "NULL";
        $product_type = 0; // 0 for product, 1 for service. Assume product for MO lines.
        if ($fk_product != "NULL") {
             $ptype_res = fetch_sql_value($db, "SELECT type FROM ".MAIN_DB_PREFIX."product WHERE rowid = $fk_product", "type");
             if ($ptype_res !== null) $product_type = $ptype_res;
        }


        $sqlLine = "INSERT INTO ".MAIN_DB_PREFIX."commandedet (fk_commande, fk_product, description, qty, subprice, total_ht, tva_tx, product_type, rang, datec, tms)
                    VALUES ($orderId, $fk_product, '".$db->real_escape_string($desc)."', $qty, $subprice, ".($qty*$subprice).", 0, $product_type, $index, NOW(), NOW())";
        if (!execute_sql($db, $sqlLine)) {
            test_log("Failed to add line '$desc' to order ID $orderId");
            // Consider rolling back order creation or handling more gracefully
        }
        $total_ht += ($qty*$subprice);
    }
    
    // Update order total
    execute_sql($db, "UPDATE ".MAIN_DB_PREFIX."commande SET amount_ht = $total_ht, amount_ttc = $total_ht WHERE rowid = $orderId");
    
    // Optionally validate the order (set fk_statut = 1 or call a validation function)
    // For now, we assume it's validated if needed by shipment module, or test will fail there.
    // execute_sql($db, "UPDATE ".MAIN_DB_PREFIX."commande SET fk_statut = 1 WHERE rowid = $orderId");
    // test_log("Validated order $orderRef (ID: $orderId)");


    return $orderId;
}

/**
 * Creates a dummy thirdparty if it doesn't exist.
 * @param mysqli $db
 * @param string $nom
 * @return int thirdparty rowid
 */
function setup_thirdparty($db, $nom = 'Test Customer Inc.', $entity = 1) {
    $res = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE nom = '".$db->real_escape_string($nom)."' AND entity = $entity");
    if ($res && $res->num_rows > 0) {
        $obj = $res->fetch_object();
        test_log("Using existing thirdparty '$nom' (ID: ".$obj->rowid.")");
        return $obj->rowid;
    } else {
        $client_code = "CU-TEST-" . time();
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."societe (nom, entity, code_client, client, datec, tms) 
                VALUES ('".$db->real_escape_string($nom)."', $entity, '".$db->real_escape_string($client_code)."', 1, NOW(), NOW())";
        if (execute_sql($db, $sql)) {
            $newSocId = $db->insert_id;
            test_log("Created thirdparty '$nom' (ID: $newSocId)");
            return $newSocId;
        }
        return 0;
    }
}

/**
 * Clean up test data.
 * @param mysqli $db
 * @param array $productIds
 * @param array $orderIds
 * @param array $shipmentIds
 * @param array $warehouseIds
 * @param array $thirdpartyIds
 * @param array $lotIds
 */
function cleanup_data($db, $productIds = array(), $orderIds = array(), $shipmentIds = array(), $warehouseIds = array(), $thirdpartyIds = array(), $lotIds = array()) {
    test_log("--- BEGIN DATA CLEANUP ---");

    if (!empty($shipmentIds)) {
        execute_sql($db, "DELETE FROM ".MAIN_DB_PREFIX."expeditiondet_batch WHERE fk_expeditiondet IN (SELECT rowid FROM ".MAIN_DB_PREFIX."expeditiondet WHERE fk_expedition IN (".implode(',', $shipmentIds)."))");
        execute_sql($db, "DELETE FROM ".MAIN_DB_PREFIX."expeditiondet WHERE fk_expedition IN (".implode(',', $shipmentIds).")");
        execute_sql($db, "DELETE FROM ".MAIN_DB_PREFIX."expedition WHERE rowid IN (".implode(',', $shipmentIds).")");
        test_log("Deleted shipments: " . implode(',', $shipmentIds));
    }
    if (!empty($orderIds)) {
        execute_sql($db, "DELETE FROM ".MAIN_DB_PREFIX."commandedet WHERE fk_commande IN (".implode(',', $orderIds).")");
        execute_sql($db, "DELETE FROM ".MAIN_DB_PREFIX."commande WHERE rowid IN (".implode(',', $orderIds).")");
        test_log("Deleted orders: " . implode(',', $orderIds));
    }
     if (!empty($productIds)) {
        // Delete stock movements first, then lots, then products
        execute_sql($db, "DELETE FROM ".MAIN_DB_PREFIX."stock_mouvement WHERE fk_product IN (".implode(',', $productIds).")");
        if (empty($lotIds)) { // If lotIds not provided specifically, try to find them from productIds
            $resLotsToDel = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."product_lot WHERE fk_product IN (".implode(',', $productIds).")");
            if ($resLotsToDel) {
                while($objLot = $resLotsToDel->fetch_object()) {
                    $lotIds[] = $objLot->rowid;
                }
            }
        }
    }
    if (!empty($lotIds)) { // Now delete lots if any found or provided
        $uniqueLotIds = array_unique($lotIds);
         execute_sql($db, "DELETE FROM ".MAIN_DB_PREFIX."stock_mouvement WHERE fk_lot IN (".implode(',', $uniqueLotIds).")"); // Ensure movements tied to these lots are gone
        execute_sql($db, "DELETE FROM ".MAIN_DB_PREFIX."product_lot WHERE rowid IN (".implode(',', $uniqueLotIds).")");
        test_log("Deleted product lots: " . implode(',', $uniqueLotIds));
    }
    if (!empty($productIds)) { // Now delete products
        execute_sql($db, "DELETE FROM ".MAIN_DB_PREFIX."product WHERE rowid IN (".implode(',', $productIds).")");
        test_log("Deleted products: " . implode(',', $productIds));
    }
    
    // Warehouses and Thirdparties are usually kept unless specifically created for a single test run and are uniquely named.
    // For this script, we'll assume they are general test data. If they were test-specific:
    // if (!empty($warehouseIds)) { execute_sql($db, "DELETE FROM ".MAIN_DB_PREFIX."entrepot WHERE rowid IN (".implode(',', $warehouseIds).")"); test_log("Deleted warehouses: ".implode(',', $warehouseIds)); }
    // if (!empty($thirdpartyIds)) { execute_sql($db, "DELETE FROM ".MAIN_DB_PREFIX."societe WHERE rowid IN (".implode(',', $thirdpartyIds).")"); test_log("Deleted thirdparties: ".implode(',', $thirdpartyIds));}

    test_log("--- END DATA CLEANUP ---");
}

/**
 * Gets current stock for a product/serial in a warehouse.
 * Sums up stock movements.
 * @param mysqli $db
 * @param int $productId
 * @param int $warehouseId
 * @param string|null $serialNumber
 * @return int Current stock quantity
 */
function get_stock_level($db, $productId, $warehouseId, $serialNumber = null) {
    $sql = "SELECT SUM(sm.value) as current_stock
            FROM ".MAIN_DB_PREFIX."stock_mouvement as sm";
    if ($serialNumber) {
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product_lot as pl ON sm.fk_lot = pl.rowid
                  WHERE sm.fk_product = $productId 
                  AND sm.fk_entrepot = $warehouseId 
                  AND pl.batch = '".$db->real_escape_string($serialNumber)."'
                  AND pl.fk_product = $productId"; // Ensure lot belongs to the product
    } else {
        $sql .= " WHERE sm.fk_product = $productId 
                  AND sm.fk_entrepot = $warehouseId
                  AND sm.fk_lot IS NULL"; // Stock for non-batch/serial managed part
    }
    
    $result = $db->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return (int)$row['current_stock'];
    }
    return 0;
}

/**
 * Simulates the serial number display filtering logic from expedition/card.php (action=create).
 *
 * @param array $product_stock_details Array of objects, each representing a batch/serial in stock.
 *                                     Each object should have ->id (lot_id), ->batch (serial_name), ->qty (stock_qty).
 * @param string $mo_ref_extracted The MO reference to filter by (e.g., "MO00001").
 * @param int $fk_product_to_use_for_display The product ID being processed.
 * @param int $quantityToBeDeliveredFromOrderLine Original quantity from the order line.
 * @param object $langs Mocked $langs object with a ->trans() method.
 * @param int $mo_product_id_override The ID for MO products (e.g., 31).
 * @return array An array containing:
 *               'serials_shown' => array of batch objects that would be displayed,
 *               'qty_to_ship' => int, the quantity that would be pre-filled,
 *               'qty_disabled' => bool, whether the quantity input would be disabled,
 *               'error_message' => string, any error message generated.
 */
function simulate_mo_serial_display_filtering(
    $product_stock_details, // array of objects like $dbatch from card.php
    $mo_ref_extracted,
    $fk_product_to_use_for_display,
    $quantityToBeDeliveredFromOrderLine,
    $langs,
    $mo_product_id_override
) {
    $serials_to_show = array();
    $qty_to_ship_prefill = 0;
    $qty_input_disabled = false;
    $error_msg = '';
    $is_mo_line_sim = ($fk_product_to_use_for_display == $mo_product_id_override && !empty($mo_ref_extracted));

    if ($is_mo_line_sim) {
        $found_matching_serial_for_mo = false;
        foreach ($product_stock_details as $dbatch) {
            if ($dbatch->batch == $mo_ref_extracted) {
                $found_matching_serial_for_mo = true;
                if ($dbatch->qty >= 1) {
                    $serials_to_show[] = $dbatch;
                    $qty_to_ship_prefill = 1;
                    $qty_input_disabled = true;
                } else {
                    // MO serial found but not enough stock
                    $error_msg = $langs->trans("ErrorNoStockForMORef", $mo_ref_extracted, "ProductRef(P".$fk_product_to_use_for_display.")"); // Using placeholder for product ref
                }
                break; // Found the specific MO serial, stop searching
            }
        }
        if (!$found_matching_serial_for_mo) {
            // MO serial not found in stock at all
            $error_msg = $langs->trans("ErrorNoStockForMORef", $mo_ref_extracted, "ProductRef(P".$fk_product_to_use_for_display.")");
        }
    } else { // Standard behavior for non-MO products or MO products where MO ref is not being enforced
        $qty_to_ship_prefill = $quantityToBeDeliveredFromOrderLine; // Default for regular products
        if (empty($product_stock_details)) {
             // This condition is simplified; card.php might show "NoProductToShipFoundIntoStock" based on warehouse context
        }
        foreach ($product_stock_details as $dbatch) {
            if ($dbatch->qty > 0) { // Only show batches with stock for regular products
                $serials_to_show[] = $dbatch;
            }
        }
         // For regular serial products, qty might be 1 and disabled if product->status_batch == 2 (not simulated here fully)
         // For simplicity, we assume qty input is enabled for non-MO lines here.
    }

    return array(
        'serials_shown' => $serials_to_show,
        'qty_to_ship' => $qty_to_ship_prefill,
        'qty_disabled' => $qty_input_disabled,
        'error_message' => $error_msg,
    );
}

/**
 * Simulates the core logic of expedition/card.php action='add'.
 * This is a highly simplified simulation and does not replicate all Dolibarr object behaviors
 * or hook calls. It focuses on the MO serial validation logic.
 *
 * @param mysqli $db DB connection
 * @param array $post_data Simulates $_POST
 * @param object $langs Mocked $langs object
 * @param object $user_obj Mocked $user object
 * @param int $p31_id Actual ID of the 'Product 31' used in tests
 * @param object $order_object_sim Simplified representation of the source order (id, socid, lines)
 * @param int $mo_product_id_technical The ID used by code to identify MO products (hardcoded 31)
 * @return array 'status' => 'success' or 'error', 
 *               'errors' => array of error messages,
 *               'shipment_object_sim' => (on success) a simplified object representing the shipment that would be created.
 */
function simulate_expedition_add_action(
    $db, 
    $post_data, 
    $langs, 
    $user_obj, 
    $p31_id, // Actual ID of P31_MO_TEST or PRODUCT_ID_31_REF
    $order_object_sim, // Contains ->id, ->socid, ->lines (array of objects with ->rowid, ->fk_product, ->description, ->qty)
    $mo_product_id_technical // This is the hardcoded '31' from the PHP logic
) {
    global $conf; // Uses the global $conf mocked in the test scenario

    $simulation_errors = array();
    $lines_to_add_to_shipment = array(); // To store validated lines data

    // Simplified product details cache for MO product
    $product_for_mo_details_sim = new stdClass();
    $product_for_mo_details_sim->status_batch = 2; // Assume serial managed
    $product_for_mo_details_sim->ref = "PRODUCT_ID_".$mo_product_id_technical."_REF"; // Mock ref for Product 31

    $product_batch_used_for_serial_check_sim = array(); // Simulation of $product_batch_used_for_serial_check

    foreach ($order_object_sim->lines as $i => $current_order_line) {
        $effective_fk_product_for_line = $current_order_line->fk_product;
        $product_management_mode = 0; // 0: not managed, 1: batch, 2: serial
        $product_ref_for_error_msg = $current_order_line->description; // Fallback
        $is_mo_line_sim = false;
        $mo_ref_extracted_add_sim = '';

        if (empty($current_order_line->fk_product) &&
            strpos($current_order_line->description, 'MO') === 0 &&
            strpos($current_order_line->description, '(Fabrication)') !== false) {
            $is_mo_line_sim = true;
        }

        if ($is_mo_line_sim) {
            $effective_fk_product_for_line = $mo_product_id_technical; // Use hardcoded 31 for logic path
            $product_management_mode = $product_for_mo_details_sim->status_batch;
            $product_ref_for_error_msg = $product_for_mo_details_sim->ref;
            $desc_parts_add = explode(' ', $current_order_line->description);
            if (!empty($desc_parts_add[0])) {
                $mo_ref_extracted_add_sim = $desc_parts_add[0];
            }
        } elseif (!empty($current_order_line->fk_product)) {
            // Simulate fetching regular product details (simplified)
            $prod_temp_res = fetch_sql_row($db, "SELECT ref, status_batch FROM ".MAIN_DB_PREFIX."product WHERE rowid = ".$current_order_line->fk_product);
            if ($prod_temp_res) {
                $product_management_mode = $prod_temp_res['status_batch'];
                $product_ref_for_error_msg = $prod_temp_res['ref'];
            }
        }

        // Check if there's any data submitted for this line (e.g., qtyl<line_index>_0)
        // This simplified loop assumes only one sub-line for batches (e.g., qtyl0_0, not qtyl0_1)
        $current_qty_field_name = 'qtyl'.$i.'_0';
        $current_batch_field_name = 'batchl'.$i.'_0'; // For batch/serial products

        if (isset($post_data[$current_qty_field_name]) && $post_data[$current_qty_field_name] > 0) {
            if ($product_management_mode > 0) { // Batch or Serial
                $qty_val = (float)$post_data[$current_qty_field_name];
                $batch_id_val = (int)$post_data[$current_batch_field_name];

                if ($is_mo_line_sim && $effective_fk_product_for_line == $mo_product_id_technical) {
                    if ($qty_val > 1) {
                        $simulation_errors[] = $langs->trans("ErrorMOToolManyQty", $product_ref_for_error_msg, $mo_ref_extracted_add_sim);
                        break; // Stop processing this order
                    }
                    // Fetch actual batch name for comparison
                    $batch_info_sim = fetch_sql_row($db, "SELECT batch FROM ".MAIN_DB_PREFIX."product_lot WHERE rowid = ".$batch_id_val." AND fk_product = ".$p31_id); // Check against actual P31 ID
                    if ($batch_info_sim) {
                        if ($batch_info_sim['batch'] != $mo_ref_extracted_add_sim) {
                            $simulation_errors[] = $langs->trans("ErrorMOSerialMismatch", $product_ref_for_error_msg, $batch_info_sim['batch'], $mo_ref_extracted_add_sim);
                            break;
                        }
                    } else {
                        $simulation_errors[] = $langs->trans("ErrorFailedToFetchBatchInfoForMO", $batch_id_val);
                        break;
                    }
                    // Serial already used check (simulated)
                    $serial_key_sim = $effective_fk_product_for_line . '_' . $batch_id_val;
                    if (in_array($serial_key_sim, $product_batch_used_for_serial_check_sim)) {
                        $simulation_errors[] = $langs->trans("SerialAlreadyUsed", $product_ref_for_error_msg);
                        break;
                    }
                    $product_batch_used_for_serial_check_sim[] = $serial_key_sim;

                } elseif ($product_management_mode == 2) { // Standard serial
                    if ($qty_val > 1) {
                        $simulation_errors[] = $langs->trans("TooManyQtyForSerialNumber", $product_ref_for_error_msg);
                        break;
                    }
                     $serial_key_sim = $effective_fk_product_for_line . '_' . $batch_id_val;
                    if (in_array($serial_key_sim, $product_batch_used_for_serial_check_sim)) {
                        $simulation_errors[] = $langs->trans("SerialAlreadyUsed", $product_ref_for_error_msg);
                        break;
                    }
                    $product_batch_used_for_serial_check_sim[] = $serial_key_sim;
                }
                
                // If no errors for this batch line, add it
                $lines_to_add_to_shipment[] = array(
                    'fk_origin_line' => $current_order_line->rowid,
                    'fk_product' => $is_mo_line_sim ? $p31_id : $current_order_line->fk_product, // Use actual P31 ID for DB
                    'qty' => $qty_val,
                    'fk_lot' => $batch_id_val,
                    'batch_name' => isset($batch_info_sim) ? $batch_info_sim['batch'] : fetch_sql_value($db, "SELECT batch FROM ".MAIN_DB_PREFIX."product_lot WHERE rowid = ".$batch_id_val, "batch"),
                    'warehouse_id' => $post_data['entrepot_id'] // Assuming global warehouse for simplicity
                );

            } else { // Simple product (no batch/serial)
                 // Logic for simple products if needed for other test cases
            }
        }
    } // End loop over order lines

    if (!empty($simulation_errors)) {
        return array('status' => 'error', 'errors' => $simulation_errors, 'shipment_object_sim' => null);
    }

    // If successful, simulate creating shipment and lines in DB for verification
    $shipment_object_sim = new stdClass();
    $shipment_object_sim->id = 0;
    $shipment_object_sim->lines = $lines_to_add_to_shipment;

    if (empty($lines_to_add_to_shipment)) { // No lines to ship
        $simulation_errors[] = $langs->trans("ErrorFieldRequired", $langs->trans("QtyToShip")); // Simplified error
        return array('status' => 'error', 'errors' => $simulation_errors, 'shipment_object_sim' => null);
    }

    // --- Simulate DB insertion for verification ---
    $db->begin_transaction();
    $shipmentRef_sim = "SH-SIM-" . time();
    $sqlShip_sim = "INSERT INTO ".MAIN_DB_PREFIX."expedition (ref, fk_soc, entity, date_creation, fk_statut, origin, origin_id, fk_entrepot, datec, tms, fk_shipping_method) 
                    VALUES ('".$db->real_escape_string($shipmentRef_sim)."', ".$order_object_sim->socid.", 1, NOW(), 0, 'commande', ".$order_object_sim->id.", ".$post_data['entrepot_id'].", NOW(), NOW(), ".$post_data['shipping_method_id'].")";
    
    if (execute_sql($db, $sqlShip_sim)) {
        $created_shipment_id_sim = $db->insert_id;
        $shipment_object_sim->id = $created_shipment_id_sim;

        foreach($lines_to_add_to_shipment as $line_to_add) {
            $sqlShipDet_sim = "INSERT INTO ".MAIN_DB_PREFIX."expeditiondet (fk_expedition, fk_origin_line, fk_entrepot, qty, fk_product)
                               VALUES ($created_shipment_id_sim, ".$line_to_add['fk_origin_line'].", ".$line_to_add['warehouse_id'].", ".$line_to_add['qty'].", ".$line_to_add['fk_product'].")";
            if(execute_sql($db, $sqlShipDet_sim)) {
                $shipmentDetId_sim = $db->insert_id;
                if ($line_to_add['fk_lot']) {
                    $sqlShipDetBatch_sim = "INSERT INTO ".MAIN_DB_PREFIX."expeditiondet_batch (fk_expeditiondet, fk_lot, qty, batch)
                                            VALUES ($shipmentDetId_sim, ".$line_to_add['fk_lot'].", ".$line_to_add['qty'].", '".$db->real_escape_string($line_to_add['batch_name'])."')";
                    if (!execute_sql($db, $sqlShipDetBatch_sim)) {
                        $simulation_errors[] = "DB Error: Failed to insert expeditiondet_batch."; $db->rollback(); break;
                    }
                }
                // Simulate stock movement
                $sqlStockMove_sim = "INSERT INTO ".MAIN_DB_PREFIX."stock_mouvement (fk_product, fk_entrepot, value, type_mouvement, fk_lot, label, datec, tms)
                                     VALUES (".$line_to_add['fk_product'].", ".$line_to_add['warehouse_id'].", ".(-$line_to_add['qty']).", 2, ".($line_to_add['fk_lot'] ? $line_to_add['fk_lot'] : "NULL").", 'Shipment $shipmentRef_sim', NOW(), NOW())";
                if (!execute_sql($db, $sqlStockMove_sim)) {
                    $simulation_errors[] = "DB Error: Failed to insert stock_mouvement."; $db->rollback(); break;
                }
            } else {
                 $simulation_errors[] = "DB Error: Failed to insert expeditiondet."; $db->rollback(); break;
            }
        }
    } else {
        $simulation_errors[] = "DB Error: Failed to insert expedition header.";
        $db->rollback();
    }

    if (!empty($simulation_errors)) {
        $db->rollback();
        return array('status' => 'error', 'errors' => $simulation_errors, 'shipment_object_sim' => null);
    } else {
        $db->commit();
        return array('status' => 'success', 'errors' => array(), 'shipment_object_sim' => $shipment_object_sim);
    }
}


// --- Main Test Execution ---
$db = getTestDB();

// Mock $langs for simulation functions
$mock_langs_for_sim = new stdClass();
$mock_langs_for_sim->trans = function($key, ...$params) use (&$mock_langs_for_sim) {
    // Simplified: real translations would be loaded from lang files.
    // For now, just return formatted string or key.
    $translations = array(
        "ErrorNoStockForMORef" => "The specific serial number %s for MO product %s is not found in stock or has insufficient quantity.",
        "NoProductToShipFoundIntoStock" => "No product to ship found in warehouse %s.",
        // Add other keys if needed by the simulation
    );
    $format = isset($translations[$key]) ? $translations[$key] : $key;
    // Basic parameter replacement for %s, %d. More robust would be needed for complex cases.
    // This simple replacement might not handle all Dolibarr translation features.
    // For %s, %d etc. it should be fine for these specific error messages.
    try {
        return vsprintf($format, $params);
    } catch (Exception $e) {
        // If vsprintf fails (e.g. type mismatch, not enough params), return the raw format string.
        return $format;
    }
};


$productIdsToCleanup = array();
$orderIdsToCleanup = array();
$shipmentIdsToCleanup = array();
$warehouseIdsToCleanup = array();
$thirdpartyIdsToCleanup = array();
$lotIdsToCleanup = array();

// Global User for actions (replace with actual user ID from your test DB)
$test_user_id = 1; 
// It's better to fetch this user or ensure it exists.
// global $user; // Dolibarr scripts expect $user to be global
// $user = new User($db);
// $user->fetch($test_user_id);


try {
    test_log("===== MO SHIPMENT SERIAL FORCING TESTS =====");

    // 1. Setup Data
    begin_test_scenario("Data Setup");

    $warehouseId = setup_warehouse($db, 'WH_MO_TEST', 'MO Test Warehouse');
    assert_true($warehouseId > 0, "Warehouse setup.");
    $warehouseIdsToCleanup[] = $warehouseId; // if we were to delete it

    $socId = setup_thirdparty($db, 'Customer MO Test');
    assert_true($socId > 0, "Thirdparty setup.");
    $thirdpartyIdsToCleanup[] = $socId; // if we were to delete it

    // Product 31 - The code hardcodes ID 31 for MO override.
    // We attempt to use this ID, but setup_product has fallbacks.
    $THE_PRODUCT_31_ID = 31;
    $p31_ref = 'PRODUCT_ID_31_REF'; // A unique ref for product 31
    // First, ensure no other product is using ID 31 with a different ref, or clean it up.
    $existing_p31 = fetch_sql_row($db, "SELECT * FROM ".MAIN_DB_PREFIX."product WHERE rowid = $THE_PRODUCT_31_ID");
    if ($existing_p31 && $existing_p31['ref'] != $p31_ref) {
        test_log("CRITICAL: A product with ID $THE_PRODUCT_31_ID already exists but has ref '".$existing_p31['ref']."'. This test run may fail or have side effects. Manual cleanup of product ID $THE_PRODUCT_31_ID might be needed.");
        // For a fully automated destructive test, you might delete/archive the conflicting product here.
        // For now, we'll proceed and let setup_product handle it as best it can.
    }

    $p31_id = setup_product($db, $p31_ref, 'Product ID 31 MO Test', 2, 1, 0, $THE_PRODUCT_31_ID); // status_batch = 2 for serials
    assert_true($p31_id > 0, "Product 31 (ref $p31_ref) setup with serial management.");
    if ($p31_id != $THE_PRODUCT_31_ID) {
         test_log("WARNING: Test Product for MOs (ref $p31_ref) was created/updated with ID $p31_id, NOT the hardcoded target $THE_PRODUCT_31_ID. The MO specific code might not trigger correctly if it strictly uses ID 31.");
    }
    $productIdsToCleanup[] = $p31_id; // Add actual ID to cleanup
    
    // Stock for Product 31 (using actual $p31_id from setup)
    $initial_stock_mo001 = 5;
    $initial_stock_mo002 = 3;
    $initial_stock_anotherp31 = 2;

    $lotIdsToCleanup[] = add_product_stock($db, $p31_id, $warehouseId, $initial_stock_mo001, 'MO00001');
    $lotIdsToCleanup[] = add_product_stock($db, $p31_id, $warehouseId, $initial_stock_mo002, 'MO00002');
    $lotIdsToCleanup[] = add_product_stock($db, $p31_id, $warehouseId, 0, 'MO00003'); // Out of stock serial
    $lotIdsToCleanup[] = add_product_stock($db, $p31_id, $warehouseId, $initial_stock_anotherp31, 'ANOTHERSERIAL_P31');
    test_log("Stock added for Product ID $p31_id (ref $p31_ref).");
    assert_equal($initial_stock_mo001, get_stock_level($db, $p31_id, $warehouseId, 'MO00001'), "Initial stock for MO00001 correct.");
    assert_equal(0, get_stock_level($db, $p31_id, $warehouseId, 'MO00003'), "Initial stock for MO00003 correct (0).");
    assert_equal($initial_stock_anotherp31, get_stock_level($db, $p31_id, $warehouseId, 'ANOTHERSERIAL_P31'), "Initial stock for ANOTHERSERIAL_P31 correct.");


    // Regular Serialized Product
    $p_reg_serial_ref = 'P_REG_SERIAL_TEST';
    $p_reg_serial_id = setup_product($db, $p_reg_serial_ref, 'Regular Serial Product Test', 2);
    assert_true($p_reg_serial_id > 0, "Regular Serial Product (ref $p_reg_serial_ref) setup.");
    $productIdsToCleanup[] = $p_reg_serial_id;
    $lotIdsToCleanup[] = add_product_stock($db, $p_reg_serial_id, $warehouseId, 10, 'REGSER001');
    $lotIdsToCleanup[] = add_product_stock($db, $p_reg_serial_id, $warehouseId, 5, 'REGSER002');

    // Regular Non-Serialized Product
    $p_reg_nonserial_ref = 'P_REG_NONSERIAL_TEST';
    $p_reg_nonserial_id = setup_product($db, $p_reg_nonserial_ref, 'Regular Non-Serial Product Test', 0);
    assert_true($p_reg_nonserial_id > 0, "Regular Non-Serial Product (ref $p_reg_nonserial_ref) setup.");
    $productIdsToCleanup[] = $p_reg_nonserial_id;
    add_product_stock($db, $p_reg_nonserial_id, $warehouseId, 20); // No serial for this one


    // Customer Order
    $orderLines = [
        // IMPORTANT: fk_product for MO lines MUST be NULL as per the code's MO detection logic.
        ['fk_product' => null, 'description' => 'MO00001 (Fabrication)', 'qty' => 1, 'subprice' => 100], // Line 0
        ['fk_product' => null, 'description' => 'MO00002 (Fabrication)', 'qty' => 1, 'subprice' => 100], // Line 1
        ['fk_product' => null, 'description' => 'MO00003 (Fabrication)', 'qty' => 1, 'subprice' => 100], // Line 2 (MO serial out of stock)
        ['fk_product' => null, 'description' => 'MO00004 (Fabrication)', 'qty' => 1, 'subprice' => 100], // Line 3 (MO serial not in P31 stock)
        ['fk_product' => $p_reg_serial_id, 'description' => 'Regular Serial Prod Desc', 'qty' => 2, 'subprice' => 50],    // Line 4
        ['fk_product' => $p_reg_nonserial_id, 'description' => 'Regular Non-Serial Desc', 'qty' => 3, 'subprice' => 20], // Line 5
    ];
    $orderId = create_customer_order($db, $socId, $orderLines, 'ORDER_MO_TEST');
    assert_true($orderId > 0, "Customer order created (ID: $orderId).");
    $orderIdsToCleanup[] = $orderId;

    // IMPORTANT: For the expedition/card.php page to work, the order usually needs to be validated.
    // And products must be in stock. The logic in card.php fetches order lines and then product details.
    // We will assume the page can be loaded with a draft order for testing display,
    // but for 'add' action, validation might be required by Dolibarr.
    // Let's validate the order for 'add' action tests.
    execute_sql($db, "UPDATE ".MAIN_DB_PREFIX."commande SET fk_statut = 1 WHERE rowid = $orderId");
    test_log("Validated order ID $orderId for shipment processing.");

    end_test_scenario();


    // --- Test Scenarios ---
    // For these scenarios, we would typically use a library like Goutte or Selenium to simulate web requests
    // and parse HTML responses. For this conceptual script, we'll describe the verification.

    // To simulate `expedition/card.php?action=create&origin=commande&origin_id=$orderId`
    // We would need to:
    // 1. Emulate the Dolibarr environment (session, user, db connection).
    // 2. Include and run `expedition/card.php`, capturing its output or checking global vars.
    // This is complex to do outside the Dolibarr framework.
    // So, verifications will be based on expected DB state or output snippets if we could capture them.

    begin_test_scenario("Shipment Creation - MO Line 1 (MO00001 - In Stock)");
    test_log("VERIFICATION POINT: Load shipment creation page for order ID $orderId.");
    test_log("Expected for line 'MO00001 (Fabrication)':");
    test_log("  - Product identified as P31_MO_TEST (ID: $p31_id).");
    test_log("  - ONLY serial 'MO00001' shown and selectable.");
    test_log("  - Other serials for P31 (MO00002, ANOTHERSERIAL_P31) NOT shown.");
    test_log("  - Quantity defaults to 1 and is read-only/enforced.");
    // In a real test, parse HTML here or check JavaScript variables if possible.
    // --- Simulate the filtering logic for display ---
    $sim_stock_p31_wh1 = array( 
        // Simulating objects that would come from $product->stock_warehouse[$warehouse_id]->detail_batch
        // Each object should have at least 'id' (lot rowid), 'batch' (serial name), 'qty' (stock quantity)
        (object)array('id' => fetch_sql_value($db, "SELECT rowid FROM ".MAIN_DB_PREFIX."product_lot WHERE fk_product=$p31_id AND batch='MO00001'", "rowid"), 'batch' => 'MO00001', 'qty' => get_stock_level($db, $p31_id, $warehouseId, 'MO00001')),
        (object)array('id' => fetch_sql_value($db, "SELECT rowid FROM ".MAIN_DB_PREFIX."product_lot WHERE fk_product=$p31_id AND batch='MO00002'", "rowid"), 'batch' => 'MO00002', 'qty' => get_stock_level($db, $p31_id, $warehouseId, 'MO00002')),
        (object)array('id' => fetch_sql_value($db, "SELECT rowid FROM ".MAIN_DB_PREFIX."product_lot WHERE fk_product=$p31_id AND batch='MO00003'", "rowid"), 'batch' => 'MO00003', 'qty' => get_stock_level($db, $p31_id, $warehouseId, 'MO00003')),
        (object)array('id' => fetch_sql_value($db, "SELECT rowid FROM ".MAIN_DB_PREFIX."product_lot WHERE fk_product=$p31_id AND batch='ANOTHERSERIAL_P31'", "rowid"), 'batch' => 'ANOTHERSERIAL_P31', 'qty' => get_stock_level($db, $p31_id, $warehouseId, 'ANOTHERSERIAL_P31')),
    );

    $display_sim_mo001 = simulate_mo_serial_display_filtering(
        $sim_stock_p31_wh1, 'MO00001', $p31_id, 1, $mock_langs_for_sim, $THE_PRODUCT_31_ID
    );
    assert_true(count($display_sim_mo001['serials_shown']) == 1, "MO00001: Only 1 serial should be shown.");
    assert_equal('MO00001', isset($display_sim_mo001['serials_shown'][0]) ? $display_sim_mo001['serials_shown'][0]->batch : '', "MO00001: Correct serial shown.");
    assert_equal(1, $display_sim_mo001['qty_to_ship'], "MO00001: Quantity to ship is 1.");
    assert_true($display_sim_mo001['qty_disabled'], "MO00001: Quantity input is disabled.");
    assert_equal('', $display_sim_mo001['error_message'], "MO00001: No error message.");
    end_test_scenario();

    begin_test_scenario("Shipment Creation - MO Line 2 (MO00002 - In Stock)");
    $display_sim_mo002 = simulate_mo_serial_display_filtering(
        $sim_stock_p31_wh1, 'MO00002', $p31_id, 1, $mock_langs_for_sim, $THE_PRODUCT_31_ID
    );
    assert_true(count($display_sim_mo002['serials_shown']) == 1, "MO00002: Only 1 serial should be shown.");
    assert_equal('MO00002', isset($display_sim_mo002['serials_shown'][0]) ? $display_sim_mo002['serials_shown'][0]->batch : '', "MO00002: Correct serial shown.");
    assert_equal(1, $display_sim_mo002['qty_to_ship'], "MO00002: Quantity to ship is 1.");
    assert_true($display_sim_mo002['qty_disabled'], "MO00002: Quantity input is disabled.");
    assert_equal('', $display_sim_mo002['error_message'], "MO00002: No error message.");
    end_test_scenario();

    begin_test_scenario("Shipment Creation - MO Line 3 (MO00003 - Out Of Stock)");
    $display_sim_mo003 = simulate_mo_serial_display_filtering(
        $sim_stock_p31_wh1, 'MO00003', $p31_id, 1, $mock_langs_for_sim, $THE_PRODUCT_31_ID
    );
    assert_true(empty($display_sim_mo003['serials_shown']), "MO00003: No serials should be shown (or error state).");
    assert_true(strpos($display_sim_mo003['error_message'], "ErrorNoStockForMORef") !== false, "MO00003: Correct error message 'ErrorNoStockForMORef' displayed. Msg: ".$display_sim_mo003['error_message']);
    end_test_scenario();

    begin_test_scenario("Shipment Creation - MO Line 4 (MO00004 - Non-existent serial for P31)");
    $display_sim_mo004 = simulate_mo_serial_display_filtering(
        $sim_stock_p31_wh1, 'MO00004', $p31_id, 1, $mock_langs_for_sim, $THE_PRODUCT_31_ID
    );
    assert_true(empty($display_sim_mo004['serials_shown']), "MO00004: No serials should be shown.");
    assert_true(strpos($display_sim_mo004['error_message'], "ErrorNoStockForMORef") !== false, "MO00004: Correct error message 'ErrorNoStockForMORef' displayed. Msg: ".$display_sim_mo004['error_message']);
    end_test_scenario();

    begin_test_scenario("Shipment Creation - Regular Serialized Product");
    $sim_stock_reg_serial_wh1 = array(
        (object)array('id' => fetch_sql_value($db, "SELECT rowid FROM ".MAIN_DB_PREFIX."product_lot WHERE fk_product=$p_reg_serial_id AND batch='REGSER001'", "rowid"), 'batch' => 'REGSER001', 'qty' => get_stock_level($db, $p_reg_serial_id, $warehouseId, 'REGSER001')),
        (object)array('id' => fetch_sql_value($db, "SELECT rowid FROM ".MAIN_DB_PREFIX."product_lot WHERE fk_product=$p_reg_serial_id AND batch='REGSER002'", "rowid"), 'batch' => 'REGSER002', 'qty' => get_stock_level($db, $p_reg_serial_id, $warehouseId, 'REGSER002')),
    );
    $display_sim_reg_serial = simulate_mo_serial_display_filtering(
        $sim_stock_reg_serial_wh1, '', $p_reg_serial_id, 2, $mock_langs_for_sim, $THE_PRODUCT_31_ID // No MO ref, different product ID
    );
    assert_true(count($display_sim_reg_serial['serials_shown']) >= 1, "Regular Serial: Expected at least 1 serial with stock to be shown. Found: ".count($display_sim_reg_serial['serials_shown']));
    // Check if REGSER001 and REGSER002 are among those shown (if they have stock)
    $reg_serials_found_in_sim = array_map(function($s){ return $s->batch; }, $display_sim_reg_serial['serials_shown']);
    if(get_stock_level($db, $p_reg_serial_id, $warehouseId, 'REGSER001') > 0) {
      assert_true(in_array('REGSER001', $reg_serials_found_in_sim), "Regular Serial: REGSER001 shown (if in stock).");
    }
    if(get_stock_level($db, $p_reg_serial_id, $warehouseId, 'REGSER002') > 0) {
      assert_true(in_array('REGSER002', $reg_serials_found_in_sim), "Regular Serial: REGSER002 shown (if in stock).");
    }
    assert_false($display_sim_reg_serial['qty_disabled'], "Regular Serial: Quantity input is enabled.");
    assert_equal('', $display_sim_reg_serial['error_message'], "Regular Serial: No error message for MO filtering.");
    end_test_scenario();

    begin_test_scenario("Shipment Creation - Regular Non-Serialized Product");
    // For non-serialized, the `simulate_mo_serial_display_filtering` is less relevant as it focuses on batch details.
    // The main check is that the UI in card.php wouldn't call this part of the logic for serials.
    test_log("Expected for line with product P_REG_NONSERIAL (ID: $p_reg_nonserial_id):");
    test_log("  - No serial number selection section appears (standard Dolibarr).");
    test_log("  - `simulate_mo_serial_display_filtering` would not be invoked for its serials.");
    end_test_scenario();


    // --- Test Shipment Submission (action=add) ---
    
    // Mock global $user object for action processing
    global $user; 
    $user = new stdClass(); 
    $user->id = $test_user_id;
    $user->socid = 0; 
    $user->hasRight = function($component, $permission, $type = null, $param=null) { return true; }; 
    $user->admin = 1;

    // Mock global $conf object with necessary properties
    global $conf;
    $conf = new stdClass();
    $conf->global = array(
        'MAIN_DONT_SHIP_MORE_THAN_ORDERED' => 1,
        'SHIPMENT_GETS_ALL_ORDER_PRODUCTS' => 0, 
        'STOCK_ALLOW_NEGATIVE_TRANSFER' => 0,
        'MAIN_PRODUCT_USE_UNITS' => 0, 
        'MAIN_DISABLE_PDF_AUTOUPDATE' => 1, 
    );
    $conf->modules = array('productbatch'=>1, 'stock'=>1, 'expedition'=>1); 
    $conf->entity = 1; 

    $conf->productbatch = new stdClass(); $conf->productbatch->enabled = 1; 
    $conf->stock = new stdClass(); $conf->stock->enabled = 1; 
    $conf->expedition = new stdClass(); $conf->expedition->dir_output = '/tmp'; 

    // Fetch full order details for simulation of $objectsrc
    $order_header_for_sim_q = "SELECT rowid, fk_soc, ref, fk_project, ref_client, delivery_date, note_public, note_private, fk_delivery_address FROM ".MAIN_DB_PREFIX."commande WHERE rowid = $orderId";
    $order_header_for_sim = fetch_sql_row($db, $order_header_for_sim_q);
    
    $order_lines_for_sim_q = "SELECT rowid, fk_product, description, qty, product_type, label as order_line_label, fk_unit, weight, volume FROM ".MAIN_DB_PREFIX."commandedet WHERE fk_commande = $orderId ORDER BY rang ASC";
    $order_lines_for_sim_db_arr = fetch_sql_rows($db, $order_lines_for_sim_q); // This is the array of order lines from DB
    
    $objectsrc_sim = new stdClass(); // This will be our mock $objectsrc
    foreach($order_header_for_sim as $k => $v) $objectsrc_sim->$k = $v;
    $objectsrc_sim->id = $orderId; 
    $objectsrc_sim->lines = array();
    foreach($order_lines_for_sim_db_arr as $line_data) {
        $line_obj = new stdClass();
        foreach($line_data as $k => $v) $line_obj->$k = $v;
        $objectsrc_sim->lines[] = $line_obj; // $objectsrc_sim->lines is an array of line objects
    }
    $objectsrc_sim->delivery_date = !empty($objectsrc_sim->delivery_date) ? strtotime($objectsrc_sim->delivery_date) : time();


    begin_test_scenario("Shipment Submission & Validation (MO00001 via simulation)");
    
    $lot_mo00001_id = fetch_sql_value($db, "SELECT rowid FROM ".MAIN_DB_PREFIX."product_lot WHERE fk_product = $p31_id AND batch = 'MO00001'", "rowid");
    assert_true($lot_mo00001_id > 0, "Fetched lot ID for MO00001: $lot_mo00001_id");
    
    $mo00001_order_line_obj = null;
    $mo00001_line_idx_for_post = -1;
    foreach($objectsrc_sim->lines as $idx_loop => $line_loop){
        if(strpos($line_loop->description, 'MO00001') === 0) { 
            $mo00001_order_line_obj = $line_loop; 
            $mo00001_line_idx_for_post = $idx_loop;
            break;
        }
    }
    assert_true($mo00001_order_line_obj !== null, "Order line for MO00001 found in simulated order object.");
    $mo00001_order_line_db_id = $mo00001_order_line_obj->rowid;

    // Prepare $_POST data for the valid case
    $_POST_SIM_VALID = array(
        'token' => 'test_token_'.uniqid(), 
        'action' => 'add', 'origin' => 'commande', 'origin_id' => $orderId,
        'date_deliveryday' => date('d'), 'date_deliverymonth' => date('m'), 'date_deliveryyear' => date('Y'),
        'date_shippingday' => date('d'), 'date_shippingmonth' => date('m'), 'date_shippingyear' => date('Y'),
        'shipping_method_id' => 1, 'tracking_number' => 'TRACKMO001_VALID_SIM',
        'note_public' => 'Test shipment for MO00001 - Valid Sim via simulation',
        'entrepot_id' => $warehouseId, 
    );
    foreach($objectsrc_sim->lines as $idx_line_post => $line_in_order_post) {
        $_POST_SIM_VALID['idl'.$idx_line_post] = $line_in_order_post->rowid;
        if ($line_in_order_post->rowid == $mo00001_order_line_db_id) {
             $_POST_SIM_VALID['qtyl'.$idx_line_post.'_0'] = '1'; 
             $_POST_SIM_VALID['batchl'.$idx_line_post.'_0'] = $lot_mo00001_id; 
        }
    }
    
    $sim_add_result_valid = simulate_expedition_add_action(
        $db, $_POST_SIM_VALID, $mock_langs, $user, $p31_id, $objectsrc_sim, $THE_PRODUCT_31_ID
    );

    assert_true($sim_add_result_valid['status'] == 'success', "Simulated 'add' for MO00001 should be successful. Errors: ".implode('; ', $sim_add_result_valid['errors']));
    if ($sim_add_result_valid['status'] == 'success' && !empty($sim_add_result_valid['shipment_object_sim'])) {
        $created_shipment_id_sim = $sim_add_result_valid['shipment_object_sim']->id; 
        $shipmentIdsToCleanup[] = $created_shipment_id_sim; 
        
        test_log("Simulated shipment creation successful (DB ID: $created_shipment_id_sim). Verifying DB data...");

        $shipmentLine = fetch_sql_row($db, "SELECT * FROM ".MAIN_DB_PREFIX."expeditiondet WHERE fk_expedition = $created_shipment_id_sim AND fk_origin_line = ".$mo00001_order_line_db_id);
        assert_true($shipmentLine !== null, "Shipment line exists for MO00001 order line after simulated 'add'.");
        if ($shipmentLine) {
            assert_equal($p31_id, $shipmentLine['fk_product'], "Shipment line product is correct (ID $p31_id) after simulated 'add'.");
            assert_equal(1, $shipmentLine['qty'], "Shipment line quantity is 1 after simulated 'add'.");

            $shipmentBatchDet = fetch_sql_row($db, "SELECT * FROM ".MAIN_DB_PREFIX."expeditiondet_batch WHERE fk_expeditiondet = ".$shipmentLine['rowid']);
            assert_true($shipmentBatchDet !== null, "Shipment batch detail exists after simulated 'add'.");
            if ($shipmentBatchDet) {
                assert_equal($lot_mo00001_id, $shipmentBatchDet['fk_lot'], "Shipment batch detail links to correct lot 'MO00001' after simulated 'add'.");
                assert_equal(1, $shipmentBatchDet['qty'], "Shipment batch detail quantity is 1 after simulated 'add'.");
            }
        }
        
        $stock_after_ship_mo001 = get_stock_level($db, $p31_id, $warehouseId, 'MO00001');
        assert_equal($initial_stock_mo001 - 1, $stock_after_ship_mo001, "Stock for MO00001 decremented correctly after simulated 'add'. Expected: ".($initial_stock_mo001 - 1).", Actual: ".$stock_after_ship_mo001);
    } else {
        test_log("Simulated shipment add failed. Errors: " . (!empty($sim_add_result_valid['errors']) ? implode(", ", $sim_add_result_valid['errors']) : "Unknown reason"));
    }
    end_test_scenario();

    begin_test_scenario("Attempt Invalid Submissions (action=add simulation)");
    
    assert_true($mo00001_line_idx_for_post !== -1, "Re-check MO00001 line index for invalid tests: $mo00001_line_idx_for_post");

    // Case 1: Quantity > 1 for MO line
    $post_qty_too_many = $_POST_SIM_VALID; 
    $post_qty_too_many['qtyl'.$mo00001_line_idx_for_post.'_0'] = '2'; 
    
    $sim_add_result_qty_error = simulate_expedition_add_action(
        $db, $post_qty_too_many, $mock_langs, $user, $p31_id, $objectsrc_sim, $THE_PRODUCT_31_ID
    );
    assert_true($sim_add_result_qty_error['status'] == 'error', "Simulated 'add' with qty > 1 for MO line should fail.");
    $expected_error_qty = $mock_langs->trans("ErrorMOToolManyQty", "PRODUCT_ID_31_REF", "MO00001");
    assert_true(in_array($expected_error_qty, $sim_add_result_qty_error['errors']), "Correct error '$expected_error_qty' for qty > 1. Found: ".implode(';',$sim_add_result_qty_error['errors']));
    

    // Case 2: Wrong serial for MO line
    $lot_anotherserial_p31_id = fetch_sql_value($db, "SELECT rowid FROM ".MAIN_DB_PREFIX."product_lot WHERE fk_product = $p31_id AND batch = 'ANOTHERSERIAL_P31'", "rowid");
    assert_true($lot_anotherserial_p31_id > 0, "Fetched lot ID for ANOTHERSERIAL_P31: $lot_anotherserial_p31_id");

    $post_wrong_serial = $_POST_SIM_VALID; 
    $post_wrong_serial['qtyl'.$mo00001_line_idx_for_post.'_0'] = '1'; // Qty is correct
    $post_wrong_serial['batchl'.$mo00001_line_idx_for_post.'_0'] = $lot_anotherserial_p31_id; // But wrong serial

    $sim_add_result_wrong_serial = simulate_expedition_add_action(
        $db, $post_wrong_serial, $mock_langs, $user, $p31_id, $objectsrc_sim, $THE_PRODUCT_31_ID
    );
    assert_true($sim_add_result_wrong_serial['status'] == 'error', "Simulated 'add' with wrong serial for MO line should fail.");
    $expected_error_serial = $mock_langs->trans("ErrorMOSerialMismatch", "PRODUCT_ID_31_REF", "ANOTHERSERIAL_P31", "MO00001");
    assert_true(in_array($expected_error_serial, $sim_add_result_wrong_serial['errors']), "Correct error '$expected_error_serial' for wrong serial. Found: ".implode(';',$sim_add_result_wrong_serial['errors']));
    
    end_test_scenario();


} catch (Exception $e) {
    test_log("AN EXCEPTION OCCURRED: " . $e->getMessage());
} finally {
    // Cleanup data
    cleanup_data($db, $productIdsToCleanup, $orderIdsToCleanup, $shipmentIdsToCleanup, array() /*warehouses*/, array()/*thirdparties*/, $lotIdsToCleanup);
    $db->close();
    test_log("===== TESTS FINISHED =====");
}

?>
