<?php
// database/migrations/2025_05_01_150853_fix_inventory_transactions_constraints.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FixInventoryTransactionsConstraints extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // For MySQL, we need to check if the foreign key already exists
        // First, get a list of all foreign keys on the table
        $existingForeignKeys = $this->getForeignKeys('inventory_transactions');
        
        // Only add the foreign key if it doesn't already exist
        if (!in_array('inventory_transactions_order_id_foreign', $existingForeignKeys)) {
            Schema::table('inventory_transactions', function (Blueprint $table) {
                // Add the foreign key
                $table->foreign('order_id')
                      ->references('id')
                      ->on('orders')
                      ->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // No need for down method in this fix
    }
    
    /**
     * Get all foreign key names for a table
     *
     * @param string $table
     * @return array
     */
    protected function getForeignKeys($table)
    {
        if (DB::getDriverName() !== 'mysql') {
            return [];
        }

        // Get all foreign keys from the information schema
        $foreignKeys = [];
        
        // For MySQL databases
        $results = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE CONSTRAINT_TYPE = 'FOREIGN KEY' 
            AND TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = '$table'
        ");
        
        foreach ($results as $result) {
            $foreignKeys[] = $result->CONSTRAINT_NAME;
        }
        
        return $foreignKeys;
    }
}
